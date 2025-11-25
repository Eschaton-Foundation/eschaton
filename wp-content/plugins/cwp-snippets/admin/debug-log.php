<?php

// Ensure Pro check function is available for logging and other features
require_once dirname(__DIR__) . '/includes/utilities.php';

/**
 * CWP Snippets - Debug Log Viewer
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the custom logging toggle setting.
 */
function fmcwp_register_log_settings() {
    // Register the setting with a sanitize callback to ensure only '1' or '0' is stored.
    register_setting(
        'fmcwp_log_options_group',
        'fmcwp_enable_custom_log',
        array(
            'type' => 'string',
            'sanitize_callback' => 'fmcwp_sanitize_enable_custom_log',
            'default' => '0',
        )
    );
}
add_action('admin_init', 'fmcwp_register_log_settings');

/**
 * Sanitization callback for the fmcwp_enable_custom_log option.
 * Ensures the option is stored as '1' (enabled) or '0' (disabled).
 *
 * @param mixed $value The raw submitted value.
 * @return string '1' or '0'
 */
function fmcwp_sanitize_enable_custom_log( $value ) {
    // Accept boolean true/false as well as string '1'.
    if ( $value === true || $value === 1 || $value === '1' ) {
        return '1';
    }
    return '0';
}

/**
 * Callback function to display the content of the debug log page.
 */
function fmcwp_debug_log_page_html() {    

    // Define the tab order conditionally
    if ( function_exists('cwp_is_pro_active') && cwp_is_pro_active() && current_user_can('manage_options') ) {
        $default_tab = 'fmcwp_custom_log';
         $tabs = [
                'fmcwp_custom_log' => 'CWP Log',
                'wp_debug_log' => 'WP Log',
            ];
    } else {
        $default_tab = 'wp_debug_log';
         $tabs = [
                'wp_debug_log' => 'WP Log',
                'fmcwp_custom_log' => 'CWP Log (Pro)',                
            ];
    }
    
     // Uns lash and sanitize the tab parameter from the URL
     /* phpcs:ignore WordPress.Security.NonceVerification.Recommended --
         This 'tab' parameter only controls UI display and does not change state. Nonce verification is not required here.
     */
     $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab;
    $custom_log_enabled = get_option('fmcwp_enable_custom_log');

    fmcwp_header();

    ?>
    <div class="content" style="padding-left: 15px;">
        <h1 style="margin-bottom: 20px;">Debug Log</h1>
        
        <div class="cwp-snippet-tabs">
            <?php            
           
            
            foreach ($tabs as $tab_id => $label) {
                $tab_class = 'cwp-snippet-tab';
                if ($current_tab === $tab_id) {
                    $tab_class .= ' cwp-snippet-tab-active';
                }
                ?>
                <a href="<?php echo esc_url('?page=fmcwp-debug-log&tab=' . $tab_id); ?>" class="<?php echo esc_attr($tab_class); ?>" style="font-size: 14px;">
                    <?php echo esc_html($label); ?>
                </a>
            <?php } ?>
        </div>

        <div class="tab-content">
            <?php
            // Render the content based on the active tab
            if ($current_tab == 'fmcwp_custom_log') {
                if(function_exists('cwp_is_pro_active') && cwp_is_pro_active() && current_user_can('manage_options')) {
                    fmcwp_render_fmcwp_custom_log_tab($custom_log_enabled);
                } else {
                    fmcwp_render_fmcwp_show_log_tab();
                }
            } else {
                fmcwp_render_wp_debug_log_tab();
            }
            ?>
        </div>
    </div>
    <?php
    fmcwp_footer();
}

// ...existing code...
function fmcwp_render_wp_debug_log_tab() {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    $log_content = '';
    $cwp_log_entries = [];

    if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
        // Inform the user that WP_DEBUG_LOG is disabled and show the wp-config.php constants to enable
        ?>
        <div class="notice notice-warning">
            <p><strong><?php echo esc_html__( 'Logging is Disabled.', 'cwp-snippets' ); ?></strong></p>
            <p><?php echo esc_html__( 'To use the debug log viewer, you must enable logging in your wp-config.php file by adding or setting the following constants to true:', 'cwp-snippets' ); ?></p>
            <pre><code><?php echo esc_html( "define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );" ); ?></code></pre>
        </div>
        <?php
    } elseif ( file_exists( $log_file ) && is_readable( $log_file ) ) {
        $lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        if ( $lines !== false ) {
            foreach ( $lines as $line ) {
                if ( strpos( $line, 'CWP Snippets' ) !== false ) {
                    $clean_line = preg_replace( '/^(\[.*?\])\s*(?:PHP (?:Fatal error|Warning|Notice):\s*)?CWP Snippets:?\s*/', '$1 ', $line );
                    $cwp_log_entries[] = trim( $clean_line );
                }
            }
        }
        
        // Reverse to show most recent first
        if ( ! empty( $cwp_log_entries ) ) {
            $cwp_log_entries = array_reverse( $cwp_log_entries );
            $log_content = implode( "\n", $cwp_log_entries );
        } else {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'No CWP Snippets specific errors found in the debug log.', 'cwp-snippets' ) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-info"><p>' . esc_html__( 'The debug.log file does not exist yet. It will be created automatically when the first error occurs.', 'cwp-snippets' ) . '</p></div>';
    }

    // Display the log content if available
    if ( ! empty( $log_content ) ) {
        ?>
        <p><span style="font-size:15px;">
            <?php echo wp_kses_post( __( 'Showing the most recent CWP Snippets errors from the <strong>WordPress debug log</strong>.', 'cwp-snippets' ) ); ?>
        </span></p>

        <textarea readonly style="width: 98%; height: 500px; font-family: monospace; background-color: #f9f9f9; border: 1px solid #ccc; padding: 10px;">
<?php echo esc_textarea( $log_content ); ?>
</textarea>
        <?php
    }
}

    //fmcwp_check_and_create_log_table();
/* ****************************************************************************************
* Display our custom debug log tab
*/
function fmcwp_render_fmcwp_custom_log_tab($custom_log_enabled) {
    ?>
    <form method="post" action="options.php" id="fmcwp-log-settings-form">
        <?php settings_fields('fmcwp_log_options_group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row" style="width:175px; padding:20px 0 20px 0; line-height:1.5;">Enable Custom Logging</th>
                <td style="line-height:2;">
                    <label class="cwp-switch" style="margin-right: 15px;">
                        <input type="checkbox" name="fmcwp_enable_custom_log" value="1" <?php checked(1, $custom_log_enabled, true); ?> />
                        <span class="cwp-slider round"></span>
                    </label>
                    <a href="<?php echo esc_url(add_query_arg(array('fmcwp_export_log' => '1', 'fmcwp_nonce' => wp_create_nonce('fmcwp_export_log_nonce')), admin_url('admin.php?page=fmcwp-debug-log'))); ?>" class="button button-primary fmcwp-log-export-button">Export Log</a>
                </td>
            </tr>
        </table>
    </form>
    
    <div style="margin-top: 20px;">
        <?php if ($custom_log_enabled) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cwp_error_log';
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name built from trusted $wpdb->prefix and a literal; safe to concatenate.
            // Use concatenation for the table name and prepare the LIMIT value
            $log_entries = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $table_name . ' ORDER BY id DESC LIMIT %d', 500 ) );
            $log_output = '';
            if ($log_entries) {
                foreach ($log_entries as $entry) {
                    $log_output .= "[{$entry->timestamp}] {$entry->error_issue} in Snippet '{$entry->snippet_name}' (ID: {$entry->snippet_id}), Line {$entry->error_line}: {$entry->error}\n";
                }
            } else {
                $log_output = "No log entries found.";
            }
        ?>
            <p>Custom logging is enabled. Log entries from your CWP Snippets database will be displayed here.</p>
            <textarea readonly style="width: 98%; height: 500px; font-family: monospace; background-color: #f9f9f9; border: 1px solid #ccc; padding: 10px;"><?php 
                 echo esc_textarea($log_output);
            ?></textarea>
            
            <p style="margin-top: 10px;">
                <span style="color: red; font-weight: bold;">WARNING:</span> This action cannot be undone.
                <button type="button" id="fmcwp-clear-log-button" class="button button-secondary">Clear all log entries</button>
            </p>
            
        <?php } else { ?>
            <p>Custom logging is currently disabled. Use the toggle switch to enable it.</p>
        <?php } ?>
    </div>
    <?php
    show_clear_log_js();
}

/**
 * Checks and creates the log table when the custom logging toggle is enabled.
 *
 * This function is hooked into the update_option_fmcwp_enable_custom_log action.
 *
 * @param mixed $old_value The old value of the option.
 * @param mixed $value     The new value of the option.
 * @return void
 */
function fmcwp_handle_log_toggle($old_value, $value) {
    // We only need to act if the logging is being turned ON.
    // The value "1" indicates the checkbox is checked.
    if ($value == '1' && $old_value != '1') {
        fmcwp_check_and_create_log_table();
    }

}

// Hook the function to run when the option is updated.
// The "10" is the priority, and the "2" tells WordPress to pass 2 arguments to our function.
add_action('update_option_fmcwp_enable_custom_log', 'fmcwp_handle_log_toggle', 10, 2);

/***************************************************************************************************************
 * Handles the custom log export to a JSON file.
 */
function fmcwp_handle_log_export() {
    // Check for our custom URL parameter.
    if (!isset($_GET['fmcwp_export_log'])) {
        return;
    }

    // Security check: Uns lash and sanitize the nonce, then verify and check capability.
    $fmcwp_nonce = isset( $_GET['fmcwp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['fmcwp_nonce'] ) ) : '';
    if ( ! $fmcwp_nonce || ! wp_verify_nonce( $fmcwp_nonce, 'fmcwp_export_log_nonce' ) ) {
        wp_die( 'CWP Snippets Authentification Error: Security check failed.' );
    }

    if (!current_user_can('manage_options')) {
        wp_die('CWP Snippets Authentification Error: You do not have permission to perform this action.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_error_log';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name built from trusted $wpdb->prefix and a literal; safe to concatenate.
    // Retrieve all log entries. Table name concatenated from trusted $wpdb prefix.
    $log_entries = $wpdb->get_results( 'SELECT * FROM ' . $table_name . ' ORDER BY id DESC', ARRAY_A );

    // Set the headers for the file download. Use gmdate() to avoid timezone issues.
    $filename = 'cwp-debug-log-' . gmdate( 'Y-m-d' ) . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Encode the log entries array to a JSON string and output it.
    echo json_encode($log_entries, JSON_PRETTY_PRINT);
    
    // Exit to prevent any other output from WordPress.
    exit;
}

// Hook the function to the 'admin_init' action to catch the URL parameter.
add_action('admin_init', 'fmcwp_handle_log_export');



/**
 * Print the JS to clear the debug log.  Handling by AJAX for simplicity.
 * Submits to the fmcwp_clear_log_entries_callback() function.
 */
function show_clear_log_js() {
    ?>
        <script>
        jQuery(document).ready(function($) {
        $('#fmcwp-clear-log-button').on('click', function() {
            if (confirm('Are you sure you want to clear all log entries? This action cannot be undone.')) {
                var data = {
                    'action': 'fmcwp_clear_log_entries',
                    'nonce': '<?php echo esc_js( wp_create_nonce( 'fmcwp_clear_log_nonce' ) ); ?>'
                };

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        alert('Log cleared successfully!');
                        $('textarea').val('');
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            }
        });


            // Auto-submit the form when the toggle checkbox is changed.
            $('#fmcwp-log-settings-form input[type="checkbox"]').on('change', function() {
                $('#fmcwp-log-settings-form').submit();
            });
        });
   
    </script>
<?php
}

/**
 * AJAX handler to clear all entries from the custom debug log table.
 */
function fmcwp_clear_log_entries_callback() {
    global $wpdb;

    // Security check: Uns lash and sanitize the POSTed nonce, then verify and check capability.
    $posted_nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! $posted_nonce || ! wp_verify_nonce( $posted_nonce, 'fmcwp_clear_log_nonce' ) ) {
        wp_send_json_error( 'Security check failed.' );
        wp_die();
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
        wp_die();
    }

    // Define the table name.
    $table_name = $wpdb->prefix . 'cwp_error_log';

    // Delete all rows from the table. Table name concatenated from trusted $wpdb prefix.
    $result = $wpdb->query( 'TRUNCATE TABLE ' . $table_name );

    if ($result !== false) {
        wp_send_json_success('Log entries cleared successfully.');
    } else {
        wp_send_json_error('Failed to clear log entries.  Check your database settings and try again.');
    }

    wp_die();
}

// Hook the function to handle the AJAX request.
add_action('wp_ajax_fmcwp_clear_log_entries', 'fmcwp_clear_log_entries_callback');



/**
 * Renders a placeholder/upgrade notice for the CWP Custom Log tab for non-Pro users.
 */
function fmcwp_render_fmcwp_show_log_tab() {
    ?>
    <div class="fmcwp-debug-banner" style="background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    margin: 5px 15px 2px; padding: 1px 12px; border-left-color: #72aee6;">
        <p><strong><?php esc_html_e( 'CWP Custom Logging is a Pro feature.', 'cwp-snippets' ); ?></strong></p>
        <p><?php esc_html_e( 'Upgrade to the Pro version to enable custom database logging and view your CWP Snippets errors here without needing to enable WordPress debug logging.', 'cwp-snippets' ); ?>
        <a href="https://cwpsnippets.com">Get Pro</a></p>
    </div>
    <?php
}
