<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


// *********************************************************************************************************************************
// Register Shortcodes

function fm_cwp_register_dynamic_shortcodes() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Fetch all shortcodes from the database
    $snippets_for_shortcodes = $wpdb->get_results("SELECT id, shortcode, type FROM " . $wpdb->prefix . "cwp_snippets");

    $allowed_shortcode_types = ['Snippet', 'Template', 'Sample']; // Define types that can have shortcodes

    foreach ($snippets_for_shortcodes as $snippet) {
        if (in_array($snippet->type, $allowed_shortcode_types)) {
            // Ensure the shortcode is formatted correctly by removing square brackets if present
            $formatted_shortcode = trim($snippet->shortcode, '[]');
            // Dynamically register the shortcode if it's not empty
            if (!empty($formatted_shortcode)) {
                add_shortcode($formatted_shortcode, 'fm_cwp_execute_php_shortcode');
            }
        }
    }
}
add_action('init', 'fm_cwp_register_dynamic_shortcodes');


// *********************************************************************************************************************************
// Execute shortcodes

function fm_cwp_execute_php_shortcode($atts = [], $content = null, $tag = '')
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Convert attributes array to an associative array for easier access
    $atts = shortcode_atts($atts, $tag);

    // Reconstruct the shortcode with brackets for the database lookup.
    $base_shortcode = '[' . $tag . ']';

    // Query the database to find the snippet by shortcode
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared --
    // $table_name is derived from $wpdb->prefix and is safe to include for table identifiers.
    $snippet = $wpdb->get_row($wpdb->prepare(
        "SELECT id, code, suppress_cache FROM $table_name WHERE shortcode = %s AND status = '1'",
        $base_shortcode
    ));


    // Check if a valid snippet was found
    if ($snippet === null) {
        return 'No Snippet Found.';
    }

    // Check if cache suppression is enabled for this snippet
    if ( ! empty( $snippet->suppress_cache ) && $snippet->suppress_cache == 1 ) {
        // Define constant to prevent caching by plugins
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        // Send headers to prevent browser caching
        if ( ! headers_sent() ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
        }
    }

    // Initialize the code variable
    $code = $snippet->code;

    // Check if there are parameters in $atts
    if ( function_exists('cwp_is_pro_active') && cwp_is_pro_active() && !empty($atts)) {
        // Convert the $atts array to a string that PHP's extract() function can use.
    $php_atts = '<?php extract(' . var_export($atts, true) . '); ?>';

        // Prepend the $atts extraction code to the existing snippet code.
        $code = $php_atts . $code;

    }

    // Prepare code for evaluation
    $code = prepare_code_for_evaluation($code);

    // On the frontend, use the robust shutdown handler to catch fatal errors and deactivate.
    $GLOBALS['cwp_last_run_snippet_id'] = $snippet->id;
    register_shutdown_function('fmcwp_check_for_fatal_error');

    // Evaluate code and capture output
    ob_start();
    try {
        // add extract($atts) to the code that is evaluated
        eval($code);
        $output = ob_get_clean();
        } catch (Throwable $e) {
        $output = ob_get_clean(); // Clean buffer in case of partial output before error
        // Log the error (only when WP_DEBUG is enabled)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "CWP Snippets Error: Failed to evaluate shortcode snippet [{$tag}]. Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() );
        }
        $output = esc_html($e->getMessage()) . ' in ' . esc_html($e->getFile()) . ' on line ' . esc_html($e->getLine());
    }

    // If we get here, the snippet ran without a fatal error, so clear the global.
    $GLOBALS['cwp_last_run_snippet_id'] = null;

    return $output;
}


// *********************************************************************************************************************************
// Add Query Variables

function fm_cwp_add_query_vars($vars) {
    $vars[] = 'fm_cwp_data_id';
    return $vars;
}
add_filter('query_vars', 'fm_cwp_add_query_vars');

// *********************************************************************************************************************************
// Preview Shortcode Handler

function cwp_snippet_preview_content_shortcode_handler() {
    // Check for the preview ID from the URL, using $_GET for reliability
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview access limited to users with 'manage_options'; no form processing occurs here.
    $data_id = isset($_GET['fm_cwp_data_id']) ? intval($_GET['fm_cwp_data_id']) : 0;

    if ($data_id && current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_snippets';

    // Fetch the specific snippet being previewed
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers
    $snippet = $wpdb->get_row($wpdb->prepare("SELECT name, code, css FROM {$table_name} WHERE id = %d", $data_id));

        if ($snippet) {
            
            // set the snippet name to a variable for easy JS handling
            $snippetName = esc_html($snippet->name);
            
            // Lets override the default title to make the preview look better
            $js = "<script>                
                document.title = 'Previewing Snippet: ' + " . json_encode($snippetName) . ";
                
                // Find and replace the H1 page title on the page
                const pageTitle = document.querySelector('body h1'); 

                if (pageTitle) {
                    // Replace the default title text with the snippet name
                    pageTitle.textContent = 'Preview: " . json_encode($snippetName) . "';
                }
                
            </script>";

            // Create a visible preview header bar. This is more reliable than fighting for the <title> tag.
            // add in the JS to adjust the page title first
            $preview_bar_html = $js . '
            <style>


                body h1 {
                    text-align:center;
                    font-size: 33px !important;
                }

                #cwp-preview-bar {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    background-color: #23282d;
                    color: #fff;
                    padding: 10px 20px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    font-size: 14px;
                    z-index: 999999;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                }
                #cwp-preview-bar strong {
                    font-weight: 600;
                }
                body {
                    padding-top: 46px !important; /* Push content down to avoid overlap */
                }
            </style>
            <!-- 
            <div id="cwp-preview-bar">
                <span>Previewing Snippet: <strong>' . esc_html($snippet->name) . '</strong></span>
            </div> 
            -->';

            $code = prepare_code_for_evaluation($snippet->code);

            // Fetch all active "Style" type snippets
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers
            $style_snippets = $wpdb->get_results($wpdb->prepare("SELECT css FROM {$table_name} WHERE type = %s AND status = 1", 'Style'));

            $style_css = '';
            foreach ($style_snippets as $style_snippet) {
                if (!empty($style_snippet->css) && fm_cwp_is_valid_css($style_snippet->css)) {
                   $style_css .= trim($style_snippet->css) . "\n";
                }
            }

            $snippet_css = trim($snippet->css);

            $all_css = '';
            if (!empty($style_css)) {
                $all_css .= $style_css;
            }
            if (!empty($snippet_css)) {
                $all_css .= $snippet_css;
            }

            // Use the fatal error handler to catch crashes during preview.
            $GLOBALS['cwp_last_run_snippet_id'] = $data_id;
            register_shutdown_function('fmcwp_check_for_fatal_error');

            ob_start();
            try {
                eval($code);
                $output = ob_get_clean();
            } catch (Throwable $e) {
                $output = ob_get_clean();
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "CWP Snippets Error: Failed to evaluate preview for snippet ID {$data_id}. Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() );
                }
                $output = esc_html($e->getMessage()) . ' in ' . esc_html($e->getFile()) . ' on line ' . esc_html($e->getLine());
            }

            // If we get here, the snippet ran without a fatal error, so clear the global.
            $GLOBALS['cwp_last_run_snippet_id'] = null;

            if (!empty($all_css)) {
                $output = "<style>{$all_css}</style>" . $output;
            }

            // Prepend the preview bar to the final output
            return $preview_bar_html . $output;
        } else {
            return "You are not authorized to view this preview or the snippet was not found.";
        }
    }
    // If no data_id or permissions are wrong, the shortcode returns an empty string.
    return '';
}
add_shortcode('cwp_snippet_preview_content', 'cwp_snippet_preview_content_shortcode_handler');