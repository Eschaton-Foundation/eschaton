<?php
/**
 * CWP Snippets - Preview Template
 *
 * This template is loaded through the 'template_include' filter when viewing the
 * 'cwp-snippet-preview' page. It provides a hybrid preview that uses the theme's
 * header and footer but takes full control over the content area to ensure a
 * consistent, full-width preview experience.
 *
 * It includes a fallback for modern block themes that may not have a header.php or footer.php file.
 *
 * @package CWP-Snippets
 * @since 1.6.3
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Conditionally load header or print a fallback for block themes
if ( locate_template( 'header.php' ) !== '' ) {
    get_header();
} else {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    <?php
}
?>

<div id="primary" class="content-area" style="width: 100%;">
    <main id="main" class="site-main" role="main">

        <?php
        // This logic is adapted from the original cwp_snippet_preview_content_shortcode_handler()

    // Check for the preview ID from the URL, using $_GET for reliability
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended --
    // Read-only admin preview; no form processing occurs here. Access is limited to users with 'manage_options'.
    $data_id = isset($_GET['fm_cwp_data_id']) ? intval($_GET['fm_cwp_data_id']) : 0;

        if ($data_id && current_user_can('manage_options')) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cwp_snippets';

            // Fetch the specific snippet being previewed
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
            $snippet = $wpdb->get_row($wpdb->prepare("SELECT name, code, css FROM {$table_name} WHERE id = %d", $data_id));

            if ($snippet) {
                // New, simple title with controlled styling
                echo '<style>
                    .cwp-preview-title {
                        font-size: 20px;
                        font-weight: 600;
                        margin: 2em 1em; /* Give it some space */
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    }
                </style>';
                echo '<h1 class="cwp-preview-title">' . esc_html($snippet->name) . '</h1>';

                $code = prepare_code_for_evaluation($snippet->code);

                // Fetch all active "Style" type snippets
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
                $style_snippets = $wpdb->get_results($wpdb->prepare("SELECT css FROM {$table_name} WHERE type = %s AND status = 1", 'Style'));

                $style_css = '';
                foreach ($style_snippets as $style_snippet) {
                    if (!empty($style_snippet->css) && function_exists('fm_cwp_is_valid_css') && fm_cwp_is_valid_css($style_snippet->css)) {
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
                    cwp_snippets_conditional_log('Failed to evaluate preview for snippet ID ' . $data_id, '', $data_id, $e->getMessage(), $e->getLine());
                    $output = esc_html($e->getMessage()) . ' in ' . esc_html($e->getFile()) . ' on line ' . esc_html($e->getLine());
                }

                // If we get here, the snippet ran without a fatal error, so clear the global.
                $GLOBALS['cwp_last_run_snippet_id'] = null;

                if (!empty($all_css)) {
                    $output = "<style>{$all_css}</style>" . $output;
                }

                // Echo the final output
                echo esc_html($output);

            } else {
                echo "<p>You are not authorized to view this preview or the snippet was not found.</p>";
            }
        }
        else {
            // If no data_id or permissions are wrong, show an error.
            echo "<p>No preview ID was provided or you do not have permission to view previews.</p>";
        }
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
// Conditionally load footer or print a fallback for block themes
if ( locate_template( 'footer.php' ) !== '' ) {
    get_footer();
} else {
    wp_footer();
    ?>
    </body>
    </html>
    <?php
}
