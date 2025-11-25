<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// *********************************************************************************************************************************
// Inject function snippets

function fmcwp_inject_function_snippets() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Determine allowed locations and build placeholders
    $location_args = [];
    $location_sql_part = '';

    if (is_admin()) {
        // Admin: Load 'admin', 'everywhere', or 'frontend' if on a post edit screen
        $allowed_locations = ['admin', 'everywhere'];
        $is_post_edit_screen = isset($GLOBALS['pagenow']) && in_array($GLOBALS['pagenow'], ['post.php', 'post-new.php'], true);

        if ($is_post_edit_screen) {
            $allowed_locations[] = 'frontend'; // Also allow 'frontend' scoped functions on post edit screens
        }
        // Remove duplicates in case 'frontend' was already there (though it shouldn't be for admin by default)
        $allowed_locations = array_unique($allowed_locations);

        $location_placeholders = implode( ', ', array_fill( 0, count( $allowed_locations ), '%s' ) );
        $location_sql_part = "AND (location IN ($location_placeholders) OR location IS NULL OR location = '')";
        $location_args = $allowed_locations;
    } else {
        // Frontend: Load 'frontend', 'everywhere', OR treat NULL/empty as 'frontend'
        $allowed_locations = ['frontend', 'everywhere'];
        $location_placeholders = implode( ', ', array_fill( 0, count( $allowed_locations ), '%s' ) );
        $location_sql_part = "AND (location IN ($location_placeholders) OR location IS NULL OR location = '')";
        $location_args = $allowed_locations;
    }

    // Prepare base arguments for the query (type, status)
    $base_args = ['Function', 1];
    // Combine base args with location args for the final prepare call
    $args = array_merge( $base_args, $location_args );

    // Construct the final SQL query string with ALL placeholders
    // Use COALESCE(priority, 10) to treat NULL priority as 10 for ordering
    $sql = "SELECT id, name, code, priority, location FROM $table_name
            WHERE type = %s
            AND status = %d
            $location_sql_part
            ORDER BY COALESCE(priority, 10) ASC"; // Default NULL priority to 10 for sorting

    // Fetch active function snippets using ONE prepare call
    // Use argument unpacking when passing an array of arguments to prepare()
    $function_snippets = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

    // Inject each snippet
    foreach ($function_snippets as $snippet) {
        if (!empty($snippet->code)) {
            if (is_admin()) {
                // In the admin area, use a standard try/catch. This will not catch
                // "cannot redeclare function" errors, but it will catch other runtime
                // errors. For AJAX requests, we buffer and discard all output to prevent
                // it from corrupting JSON responses.
                if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
                    ob_start();
                    try {
                        eval(prepare_code_for_evaluation($snippet->code));
                    } catch (Throwable $e) {
                        // Log the error, but produce no output.
                        cwp_snippets_conditional_log(
                            'Admin AJAX Runtime Error', $snippet->name, $snippet->id, $e->getMessage(), $e->getLine()
                        );
                    }
                    ob_end_clean();
                } else {
                    // For regular admin pages, execute with the original error handling that shows a notice.
                    try {
                        eval(prepare_code_for_evaluation($snippet->code));
                    } catch (Throwable $e) {
                        // Log the error
                        cwp_snippets_conditional_log(
                            'Admin Runtime Error', $snippet->name, $snippet->id, $e->getMessage(), $e->getLine()
                        );

                        // Store an admin notice to be displayed on the page
                        if (!isset($GLOBALS['cwp_runtime_notices'])) {
                            $GLOBALS['cwp_runtime_notices'] = [];
                        }
                        $edit_url = admin_url('admin.php?page=fmcwp-snippets&action=edit&id=' . intval($snippet->id) . '&filter_type=Function');
                        // translators: 1: snippet name, 2: snippet ID, 3: snippet edit URL, 4: error message
                        $message = sprintf(__('<strong>CWP Snippets Runtime Error:</strong> The snippet "<strong>%1$s</strong>" (ID: %2$d) failed to execute and was skipped. Please <a href="%3$s">review the snippet</a> for errors. <br><small>Error details: %4$s</small>', 'cwp-snippets'),
                            esc_html($snippet->name),
                            intval($snippet->id),
                            esc_url($edit_url),
                            esc_html($e->getMessage())
                        );
                        $GLOBALS['cwp_runtime_notices'][] = ['message' => $message, 'type' => 'error'];
                    }
                }
            } else {
                // On the frontend, use the robust shutdown handler to catch fatal errors and deactivate.
                $GLOBALS['cwp_last_run_snippet_id'] = $snippet->id;
                register_shutdown_function('fmcwp_check_for_fatal_error');
                eval(prepare_code_for_evaluation($snippet->code));
                // If we get here, the snippet ran without a fatal error, so clear the global.
                $GLOBALS['cwp_last_run_snippet_id'] = null;
            }
        }
    }
}

add_action('init', 'fmcwp_inject_function_snippets', 5);

/**
 * A shutdown function to catch fatal errors caused by snippets.
 *
 * This function checks if a fatal error occurred during the last script
 * execution. If the error was caused by a CWP Snippet, it deactivates
 * the snippet and sets a transient to notify the admin.
 */
function fmcwp_check_for_fatal_error() {
    // Check if a snippet was being evaluated when the script shut down
    $last_run_id = $GLOBALS['cwp_last_run_snippet_id'] ?? null;
    if ($last_run_id === null) {
        return;
    }

    // Clear the global immediately to prevent this from running again on the same error
    $GLOBALS['cwp_last_run_snippet_id'] = null;

    $error = error_get_last();

    // Check if a fatal error occurred
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_snippets';

    // Fetch the snippet's name for the notice
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
    $snippet = $wpdb->get_row($wpdb->prepare("SELECT id, name FROM {$table_name} WHERE id = %d", $last_run_id));
        if (!$snippet) {
            return; // Snippet might have been deleted
        }

        // Deactivate the snippet
        $wpdb->update($table_name, ['status' => 0], ['id' => $snippet->id]);

        // Log the error
        cwp_snippets_conditional_log(
            'Runtime Error & Deactivation',
            $snippet->name,
            $snippet->id,
            $error['message'],
            $error['line']
        );

        // Set a transient to show a persistent admin notice
        $notice_key = 'cwp_fatal_error_' . $snippet->id;
        $notice_data = ['name' => $snippet->name, 'id' => $snippet->id, 'message' => $error['message']];
        set_transient($notice_key, $notice_data, WEEK_IN_SECONDS);

        // Attempt to display a user-friendly message on the crashed page.
        // This runs late in the execution, so we can't use standard WP functions.
        if (!headers_sent()) {
            // If we can, clear any partial output to make our message cleaner.
            @ob_end_clean();
        }
        echo '<div style="position:fixed; top:30px; left:50%; transform:translateX(-50%); background:#d63638; color:white; padding:15px 25px; border-radius:5px; box-shadow:0 5px 15px rgba(0,0,0,0.3); z-index:999999; font-family:sans-serif; text-align:center;">';
        echo '<strong>CWP Snippets Recovery</strong><br><br>';
        echo 'A snippet caused a critical error and has been automatically disabled.<br>';
        echo '<a href="#" onclick="window.location.reload(); return false;" style="color: white; font-weight: bold; text-decoration: underline;">Click here to refresh the page.</a>';
        echo '</div>';
    }
}

/**
 * Checks all inactive 'Function' type snippets for PHP syntax errors and displays an admin notice if any are found.
 * This helps prevent activating a snippet that would cause a fatal error.
 */
function fmcwp_check_inactive_function_snippets_syntax() {
    // Only run for admins in the admin area
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Get all inactive 'Function' snippets
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
    $inactive_functions = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code FROM $table_name WHERE type = %s AND status = %d",
        'Function',
        0 // Status 0 = Inactive
    ));

    if (empty($inactive_functions)) {
        return;
    }

    foreach ($inactive_functions as $snippet) {
        if (empty(trim($snippet->code))) {
            continue;
        }

        $syntax_check = fmcwp_check_php_syntax($snippet->code, $snippet->name, $snippet->id);

        if ($syntax_check['error'] && $syntax_check['message'] !== 'shell_exec disabled') {
            // Store an admin notice
            if ( ! isset( $GLOBALS['cwp_runtime_notices'] ) ) {
                $GLOBALS['cwp_runtime_notices'] = [];
            }
            $edit_url = admin_url('admin.php?page=fmcwp-snippets&action=edit&id=' . intval($snippet->id) . '&filter_type=Function');

            // Note: Using 'warning' type to differentiate from runtime errors
            // translators: 1: snippet name, 2: snippet ID, 3: snippet edit URL, 4: error message
            $message = sprintf(__('<strong>CWP Snippets New Warning:</strong> The inactive "Function" snippet "<strong>%1$s</strong>" (ID: %2$d) has a syntax error and will cause a site failure if activated. Please <a href="%3$s">review the snippet</a>.<br><small>Error details: %4$s</small>', 'cwp-snippets'),
                esc_html($snippet->name),
                intval($snippet->id),
                esc_url($edit_url),
                esc_html($syntax_check['message'])
            );
            $GLOBALS['cwp_runtime_notices'][] = ['message' => $message, 'type' => 'warning'];
        }
    }
}
//add_action('admin_init', 'fmcwp_check_inactive_function_snippets_syntax');

// Add this (if not already present) in your main plugin file or an admin-related include
function fmcwp_display_syntax_error_notice() {
    if ( $error_message = get_transient( 'fmcwp_syntax_error_notice' ) ) {
        delete_transient( 'fmcwp_syntax_error_notice' ); // Delete it so it only shows once
        printf(
            '<div class="notice notice-error is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
            esc_html__( 'Syntax Error Warning:', 'cwp-snippets' ),
            esc_html( $error_message )
        );
    }
}
add_action( 'admin_notices', 'fmcwp_display_syntax_error_notice' );

// *********************************************************************************************************************************
// Inject script snippets (Frontend/Everywhere)

function fmcwp_inject_script_snippets_frontend() {
    if (is_admin()) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Frontend: Load 'frontend', 'everywhere', OR treat NULL/empty as 'frontend'
    $allowed_locations = ['frontend', 'everywhere'];
    $location_placeholders = implode( ', ', array_fill( 0, count( $allowed_locations ), '%s' ) );
    $location_sql_part = "AND (location IN ($location_placeholders) OR location IS NULL OR location = '')";
    $location_args = $allowed_locations;

    $base_args = ['Script', 1]; // Type = Script, Status = 1 (Active)
    $args = array_merge( $base_args, $location_args );

    $sql = "SELECT id, code, priority, location FROM $table_name
            WHERE type = %s
            AND status = %d
            $location_sql_part
            ORDER BY COALESCE(priority, 10) ASC"; // Default NULL priority to 10

    $prepared_sql = $wpdb->prepare( $sql, ...$args );
    $script_snippets = $wpdb->get_results( $prepared_sql );

    if (empty($script_snippets)) {
        return; // Exit if no snippets found
    }

    // Enqueue jQuery if needed
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }

    // Register and Enqueue the main handle 'fm-cwp-snippets'
    wp_register_script('fm-cwp-snippets', false, array('jquery'), CWP_SNIPPETS_VERSION, true); // Use version constant
    wp_enqueue_script('fm-cwp-snippets');

    // Loop through snippets
    foreach ($script_snippets as $snippet) {
        if (!empty($snippet->code)) {
            $code = preg_replace('/^<script[^>]*>/i', '', $snippet->code);
            $code = preg_replace('/<\/script>$/i', '', $code);
            $code = trim($code);
            if (!empty($code)) {
                wp_add_inline_script('fm-cwp-snippets', $code);
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'fmcwp_inject_script_snippets_frontend', 999);


// includes/snippets-functions.php

// *********************************************************************************************************************************
// Inject script snippets (Admin/Everywhere)

function fmcwp_inject_script_snippets_admin() {
    if (!is_admin()) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Admin: Only load 'admin' or 'everywhere'
    $allowed_locations = ['admin', 'everywhere'];
    $location_placeholders = implode( ', ', array_fill( 0, count( $allowed_locations ), '%s' ) );
    $location_sql_part = "AND (location IN ($location_placeholders) OR location IS NULL OR location = '')";
    $location_args = $allowed_locations;

    $base_args = ['Script', 1]; // Type = Script, Status = 1 (Active)
    $args = array_merge( $base_args, $location_args );

    $sql = "SELECT id, code, priority, location FROM $table_name
            WHERE type = %s
            AND status = %d
            $location_sql_part
            ORDER BY COALESCE(priority, 10) ASC"; // Default NULL priority to 10

    $prepared_sql = $wpdb->prepare( $sql, ...$args );
    $script_snippets = $wpdb->get_results( $prepared_sql );

    if (empty($script_snippets)) {
        return; // Exit if no snippets found
    }

    // Enqueue jQuery if needed
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }

    // Register and Enqueue the admin handle 'fm-cwp-admin-snippets'
    $version = defined('CWP_SNIPPETS_VERSION') ? CWP_SNIPPETS_VERSION : null;
    wp_register_script('fm-cwp-admin-snippets', false, array('jquery'), $version, true);
    wp_enqueue_script('fm-cwp-admin-snippets');

    // Loop through snippets
    foreach ($script_snippets as $snippet) {
        if (!empty($snippet->code)) {
            $code = preg_replace('/^<script[^>]*>/i', '', $snippet->code);
            $code = preg_replace('/<\/script>$/i', '', $code);
            $code = trim($code);
            if (!empty($code)) {
                wp_add_inline_script('fm-cwp-admin-snippets', $code);
            }
        }
    }
}
add_action('admin_enqueue_scripts', 'fmcwp_inject_script_snippets_admin', 999);


// *********************************************************************************************************************************
// Inject All Styles (Frontend/Everywhere)
function fmcwp_inject_all_styles() {
    if (is_admin()) return;

    global $wpdb, $post;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // --- Inject Style Snippets (Type = 'Style') ---
    // Frontend: Load 'frontend', 'everywhere', OR treat NULL/empty as 'frontend'
    $allowed_locations = ['frontend', 'everywhere'];
    $location_placeholders = implode( ', ', array_fill( 0, count( $allowed_locations ), '%s' ) );
    $location_sql_part = "AND (location IN ($location_placeholders) OR location IS NULL OR location = '')";
    $location_args = $allowed_locations;

    $base_args = ['Style', 1];
    $args = array_merge( $base_args, $location_args );

    $sql = "SELECT css, priority, location FROM $table_name
            WHERE type = %s
            AND status = %d
            $location_sql_part
            ORDER BY COALESCE(priority, 10) ASC"; // Default NULL priority to 10

    $style_snippets = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

    $all_css = '';
    foreach ($style_snippets as $snippet) {
        if (!empty($snippet->css)) {
            $css = trim($snippet->css);
            if (fm_cwp_is_valid_css($css)) {
                // error_log("Injecting Style Snippet CSS (Priority: {$snippet->priority}, Location: {$snippet->location})");
                $all_css .= "/* Style Snippet (Priority: " . esc_attr( $snippet->priority ?? 10 ) . ") */\n" . $css . "\n\n"; // Use default 10 if priority is NULL
            }
        }
    }

    // --- Inject Snippet CSS (Type != 'Style') based on shortcode presence ---
    // This part remains the same as it depends on shortcode presence, not location/priority directly
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name is derived from $wpdb->prefix and safe to include for table identifiers.
    $other_snippets_with_css = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT shortcode, css FROM $table_name
             WHERE type != %s
             AND status = %d
             AND css IS NOT NULL AND css != ''",
            'Style', 1
        )
    );

    if (!empty($other_snippets_with_css) && is_a($post, 'WP_Post')) {
        foreach ($other_snippets_with_css as $snippet) {
            $tag = trim($snippet->shortcode, '[]');
            if (!empty($tag) && !empty($snippet->css) && fm_cwp_is_valid_css($snippet->css) && has_shortcode($post->post_content, $tag)) {
                // error_log("Injecting CSS for Shortcode: {$tag}");
                $all_css .= "/* CSS for shortcode: {$tag} */\n" . trim($snippet->css) . "\n\n";
            }
        }
    }

    if (!empty($all_css)) {
        wp_register_style('fm-cwp-all-styles', false, array(), gmdate('YmdHi'));
        wp_enqueue_style('fm-cwp-all-styles');
        wp_add_inline_style('fm-cwp-all-styles', $all_css);
    }
}
add_action('wp_enqueue_scripts', 'fmcwp_inject_all_styles', 999);


// *********************************************************************************************************************************
// Inject All Styles (Admin/Everywhere) - Separate function for admin styles

function fmcwp_inject_admin_styles() {
    if (!is_admin()) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Admin: Only load 'admin' or 'everywhere'
    $allowed_locations = ['admin', 'everywhere'];
    $location_placeholders = implode( ', ', array_fill( 0, count( $allowed_locations ), '%s' ) );
    $location_sql_part = "AND (location IN ($location_placeholders) OR location IS NULL OR location = '')";
    $location_args = $allowed_locations;

    $base_args = ['Style', 1];
    $args = array_merge( $base_args, $location_args );

    $sql = "SELECT css, priority, location FROM $table_name
            WHERE type = %s
            AND status = %d
            $location_sql_part
            ORDER BY COALESCE(priority, 10) ASC"; // Default NULL priority to 10

    $style_snippets = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) );

    $admin_css = '';
    foreach ($style_snippets as $snippet) {
        if (!empty($snippet->css)) {
            $css = trim($snippet->css);
            if (fm_cwp_is_valid_css($css)) {
                // error_log("Injecting Admin Style Snippet CSS (Priority: {$snippet->priority}, Location: {$snippet->location})");
                $admin_css .= "/* Admin Style Snippet (Priority: " . esc_attr( $snippet->priority ?? 10 ) . ") */\n" . $css . "\n\n"; // Use default 10 if priority is NULL
            }
        }
    }

    if (!empty($admin_css)) {
        wp_register_style('fm-cwp-admin-styles', false, array(), gmdate('YmdHi'));
        wp_enqueue_style('fm-cwp-admin-styles');
        wp_add_inline_style('fm-cwp-admin-styles', $admin_css);
    }
}
add_action('admin_enqueue_scripts', 'fmcwp_inject_admin_styles', 999);


// *********************************************************************************************************************************
//Font awesome (Keep separate for clarity, or combine if desired)
function enqueue_font_awesome() {
    if (!is_admin()) {
        $version = '5.15.3';
        // Prefer a bundled local copy if available (recommended for distribution). If not present, fall back to CDN.
        $local_path = FMCWP_PLUGIN_PATH . 'assets/vendor/fontawesome/css/all.min.css';
        $local_url  = FMCWP_PLUGIN_URL . 'assets/vendor/fontawesome/css/all.min.css';
        if ( file_exists( $local_path ) ) {
            wp_enqueue_style( 'font-awesome', $local_url, array(), $version );
        } else {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Bundled asset not found in this installation; fall back to CDN for development. For plugin distribution, include the Font Awesome files under assets/vendor/fontawesome/ to avoid offloading.
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css', array(), $version);
        }
    }
}
add_action('wp_enqueue_scripts', 'enqueue_font_awesome');

function enqueue_font_awesome_admin() {
    $screen = get_current_screen();
    if ( $screen && strpos($screen->id, 'fmcwp') !== false ) {
        $version = '5.15.3';
        $local_path = FMCWP_PLUGIN_PATH . 'assets/vendor/fontawesome/css/all.min.css';
        $local_url  = FMCWP_PLUGIN_URL . 'assets/vendor/fontawesome/css/all.min.css';
        if ( file_exists( $local_path ) ) {
            wp_enqueue_style( 'font-awesome-admin', $local_url, array(), $version );
        } else {
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Bundled asset not found in this installation; fall back to CDN for development. For plugin distribution, include the Font Awesome files under assets/vendor/fontawesome/ to avoid offloading.
            wp_enqueue_style('font-awesome-admin', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css', array(), $version);
        }
    }
}
add_action('admin_enqueue_scripts', 'enqueue_font_awesome_admin');
