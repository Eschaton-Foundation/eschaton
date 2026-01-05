<?php
/**
 * CWP Snippets - Import/Export Functionality (Admin)
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// *********************************************************************************************************************************
// Import Snippets

// Hook for processing the import form submission
add_action('admin_post_import_snippets', 'fmcwp_handle_import_snippets');

function fmcwp_handle_import_snippets() {
    // Verify the nonce for security
    if (!isset($_POST['import_snippets_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['import_snippets_nonce'])), 'import_snippets_action')) {
        wp_die('Nonce verification failed for import.');
    }

    $snippets_data = null;
    $bundled_update_type = isset($_POST['bundled_update_type']) ? sanitize_key($_POST['bundled_update_type']) : null;
    $is_reload = isset($_POST['is_reload']) && $_POST['is_reload'] === '1';

    if ($bundled_update_type) {
        // This is a bundled update, get data from the file system.
        if ($bundled_update_type === 'all') {
            $snippet_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];
            $all_snippets_to_update = [];
            foreach ($snippet_types as $type) {
                if (get_transient('cwp_update_available_' . $type)) {
                    $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $type . '.json';
                    if (file_exists($file_path) && is_readable($file_path)) {
                        $file_content = file_get_contents($file_path);
                        $data = json_decode($file_content, true);
                        if (is_array($data)) {
                            $all_snippets_to_update = array_merge($all_snippets_to_update, $data);
                        }
                    }
                }
            }
            $snippets_data = $all_snippets_to_update;
        } else {
            $allowed_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];
            if (in_array($bundled_update_type, $allowed_types)) {
                $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $bundled_update_type . '.json';
                if (file_exists($file_path)) {
                    $file_content = file_get_contents($file_path);
                    $snippets_data = json_decode($file_content, true);
                }
            }
        }
    } elseif (isset($_FILES['snippets_import_file']) && $_FILES['snippets_import_file']['error'] === UPLOAD_ERR_OK) {
        // This is a file upload.
        $file_content = file_get_contents($_FILES['snippets_import_file']['tmp_name']);
        $snippets_data = json_decode($file_content, true);
    }

    if ($snippets_data !== null && is_array($snippets_data)) {
        // The main processing logic, which was previously inside the file upload check, now goes here.
        // This logic is now common for both file uploads and bundled updates.

        // Get the lists of snippets to add and update from the POST data
        $snippets_to_add = isset($_POST['snippets_to_add']) && is_array($_POST['snippets_to_add'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['snippets_to_add']))
            : [];
        $snippets_to_update = isset($_POST['snippets_to_update']) && is_array($_POST['snippets_to_update'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['snippets_to_update']))
            : [];

            global $wpdb;
            $table_name = $wpdb->prefix . 'cwp_snippets';

            $added_by_type = [];
            $updated_by_type = [];

            $imported_count = 0;
            $functions_to_activate = []; // Array to hold IDs of functions that were originally active

            foreach ($snippets_data as $snippet) {
                // Ensure code and CSS are properly escaped and serialized
                $name = sanitize_text_field($snippet['name']);

                if (!in_array($name, $snippets_to_add) && !in_array($name, $snippets_to_update)) {
                    continue; // Skip this snippet if it wasn't selected for import/update
                }

                $snippet_type_from_file = isset($snippet['type']) ? sanitize_text_field($snippet['type']) : 'Snippet';
                $normalized_type_key = strtolower($snippet_type_from_file);
                $shortcode = sanitize_text_field($snippet['shortcode']);
                $code = isset($snippet['code']) ? $snippet['code'] : '';
                $css = isset($snippet['css']) ? $snippet['css'] : '';
                // Get the original status from the import file.
                $original_status = isset($snippet['status']) ? intval($snippet['status']) : 1;

                // --- START: Handle new imported fields ---
                $description = isset($snippet['description']) ? wp_kses_post($snippet['description']) : '';
                $location = isset($snippet['location']) ? sanitize_text_field($snippet['location']) : 'everywhere';
                $priority = isset($snippet['priority']) ? intval($snippet['priority']) : 10;
                $time = isset($snippet['time']) ? sanitize_text_field($snippet['time']) : current_time('mysql');
                $modified_time = isset($snippet['modified_time']) ? sanitize_text_field($snippet['modified_time']) : current_time('mysql');
                // --- END: Handle new imported fields ---
                $status_to_set = $original_status; // Default to using the original status

                // For 'Function' snippets, always import them as inactive first to prevent fatal errors.
                if ($snippet_type_from_file === 'Function') {
                    $status_to_set = 0;
                }

                // Check if a snippet with the same name and type exists
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name built from trusted $wpdb->prefix and a literal; safe to concatenate.
                $existing_snippet_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $table_name . ' WHERE name = %s AND type = %s', $name, $snippet_type_from_file ) );

                if (!$existing_snippet_id && in_array($name, $snippets_to_add)) {
                    // Insert a new snippet
                    $inserted = $wpdb->insert(
                        $table_name,
                        array(
                            'name' => $name,
                            'shortcode' => $shortcode,
                            'code' => $code,
                            'css' => $css,
                            'type' => $snippet_type_from_file,
                            'status' => $status_to_set, // Use the potentially modified status
                            'time' => $time,
                            'modified_time' => $modified_time,
                            'description' => $description,
                            'location' => $location,
                            'priority' => $priority,
                        )
                    );

                    if ($inserted !== false) {
                        $imported_count++;
                        $added_by_type[$normalized_type_key] = ($added_by_type[$normalized_type_key] ?? 0) + 1;
                        // If it's a 'Function' snippet and was originally active, queue it for an activation attempt.
                        if ($snippet_type_from_file === 'Function' && $original_status == 1) {
                            $functions_to_activate[] = $wpdb->insert_id;
                        }
                    } else {
                        cwp_snippets_conditional_log('Import Error: Failed to insert snippet with name ' . $name . ' and type ' . $snippet_type_from_file);
                    }
                } else if ($existing_snippet_id && in_array($name, $snippets_to_update)) {
                    // Update existing snippet
                    $updated = $wpdb->update(
                        $table_name,
                        array(
                            'name' => $name,
                            'shortcode' => $shortcode,
                            'code' => $code,
                            'css' => $css,
                            'status' => $status_to_set,
                            'modified_time' => current_time('mysql'), // Always update modified time on update
                            'description' => $description,
                            'location' => $location,
                            'priority' => $priority,
                        ),
                        array('id' => $existing_snippet_id), // WHERE clause
                        array( // data formats
                            '%s', // name
                            '%s', // shortcode
                            '%s', // code
                            '%s', // css
                            '%d', // status
                            '%s', // modified_time
                            '%s', // description
                            '%s', // location
                            '%d', // priority
                        ),
                        array('%d') // where format
                    );

                    if ($updated !== false) {
                        $imported_count++;
                        $updated_by_type[$normalized_type_key] = ($updated_by_type[$normalized_type_key] ?? 0) + 1;
                        if ($snippet_type_from_file === 'Function' && $original_status == 1) {
                            $functions_to_activate[] = $existing_snippet_id;
                        }
                    }
                }
            }

            // --- START: Attempt to activate 'Function' snippets that were originally active ---
            if (!empty($functions_to_activate)) {
                $activation_failures = [];
                $activated_count = 0;

                foreach ($functions_to_activate as $snippet_id) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name built from trusted $wpdb->prefix and a literal; safe to concatenate.
                    $snippet_to_activate = $wpdb->get_row( $wpdb->prepare( 'SELECT id, name, code FROM ' . $table_name . ' WHERE id = %d', $snippet_id ) );

                    if (!$snippet_to_activate) {
                        continue; // Snippet not found, skip.
                    }

                    $has_error = false;

                    // 1. Check for syntax errors (only fatal ones block activation).
                    $syntax_check = fmcwp_check_php_syntax($snippet_to_activate->code, $snippet_to_activate->name, $snippet_to_activate->id);
                    if ($syntax_check['error'] && isset($syntax_check['type']) && $syntax_check['type'] === 'fatal') {
                        /* translators: 1: snippet name (will be wrapped in <strong> tags), 2: the syntax error message (may contain code HTML) */
                        $activation_failures[] = sprintf(__('Snippet "<strong>%1$s</strong>" has a syntax error: %2$s', 'cwp-snippets'),
                            esc_html($snippet_to_activate->name),
                            '<code>' . esc_html($syntax_check['message']) . '</code>'
                        );
                        $has_error = true;
                    }

                    // 2. Check for code conflicts.
                    $conflict_result = fmcwp_check_code_conflicts($snippet_to_activate->code, $snippet_to_activate->id);
                    if ($conflict_result['conflict']) {
                        /* translators: 1: snippet name (will be wrapped in <strong> tags), 2: conflict type (e.g. "function", will be wrapped in <strong>), 3: existing name that caused the conflict (will be wrapped in <code>) */
                        $activation_failures[] = sprintf(__('Snippet "<strong>%1$s</strong>" has a conflict: A %2$s named %3$s already exists.', 'cwp-snippets'),
                            esc_html($snippet_to_activate->name),
                            '<strong>' . esc_html(ucfirst($conflict_result['type'])) . '</strong>',
                            '<code>' . esc_html($conflict_result['name']) . '</code>'
                        );
                        $has_error = true;
                    }

                    // 3. If all checks pass, activate the snippet. Otherwise, skip.
                    if (!$has_error) {
                        $wpdb->update($table_name, ['status' => 1], ['id' => $snippet_id], ['%d'], ['%d']);
                        $activated_count++;
                    }
                }

                // After checking all snippets, if there were failures, store them for a notice and redirect.
                if (!empty($activation_failures)) {
                    set_transient('fmcwp_import_activation_errors', $activation_failures, 60); // Store for 1 minute
                    $args = array(
                        'fmcwp_notice' => 'import_activation_multiple_errors',
                        'imported_count' => $imported_count,
                        'activated_count' => $activated_count,
                    );
                    $referer_url = wp_get_referer();
                    $base_redirect_url = remove_query_arg(array('fmcwp_notice', 'snippet_name', 'error_message', 'conflict_name', 'conflict_type', 'imported_count', 'activated_count'), $referer_url);
                    $redirect_url = add_query_arg($args, $base_redirect_url);
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
            // --- END: Attempt to activate 'Function' snippets ---

            // If this was a bundled update, clear the transient and update the hash
            if ($bundled_update_type && !$is_reload) {
                if ($bundled_update_type === 'all') {
                    $snippet_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];
                    foreach ($snippet_types as $type) {
                        // We update the hash and clear the transient for all types,
                        // as the user's intent was to resolve all pending updates.
                        $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $type . '.json';
                        if (file_exists($file_path)) {
                            $hash = md5_file($file_path);
                            update_option('cwp_snippets_' . $type . '_hash', $hash);
                        }
                        delete_transient('cwp_update_available_' . $type);
                    }
                } else {
                    $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $bundled_update_type . '.json';
                    if (file_exists($file_path)) {
                        $hash = md5_file($file_path);
                        update_option('cwp_snippets_' . $bundled_update_type . '_hash', $hash);
                    }
                    delete_transient('cwp_update_available_' . $bundled_update_type);
                }
            }


            // Redirect on success
            $notice_code = 'import_success';
            $args = ['fmcwp_notice' => $notice_code];
            if (!empty($added_by_type)) {
                $args['added'] = $added_by_type;
            }
            if (!empty($updated_by_type)) {
                $args['updated'] = $updated_by_type;
            }
            $referer_url = wp_get_referer();
            $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'import_status', 'imported_count'), $referer_url );
            $redirect_url = add_query_arg( $args, $base_redirect_url );
            wp_safe_redirect( $redirect_url );
            exit;
    } else {
        // Redirect on invalid format or upload error
        $notice_code = isset($_FILES['snippets_import_file']) ? 'import_upload_error' : 'import_invalid_format';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'import_status', 'imported_count'), $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}


// *********************************************************************************************************************************
// Handle Bulk Action Export Submission

// Use the admin_post_{action_name} hook
add_action('admin_post_fmcwp_bulk_export', 'fmcwp_handle_bulk_export_action');

function fmcwp_handle_bulk_export_action() {
    // Check if the correct bulk action was selected
    if ( ! isset($_POST['bulk_action']) || $_POST['bulk_action'] !== 'export' ) {
         // Redirect back with an error if the action isn't 'export'
         // No change needed here as it uses 'bulk_status'
         wp_safe_redirect( add_query_arg('bulk_status', 'invalid_action', wp_get_referer()) );
         exit;
    }

    // Verify nonce (Matches the field added in the form)
    if ( ! isset( $_POST['_wpnonce_bulk_action'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['_wpnonce_bulk_action'])), 'fmcwp_bulk_action_nonce' ) ) {
        wp_die( 'Nonce verification failed for bulk export.' );
    }

    // Check user capability
    if ( ! current_user_can( 'manage_options' ) ) {
         // Using wp_die is better here as admin_notices won't show before exit
         wp_die( 'You do not have permission to export snippets.' );
    }

    if ( ! cwp_is_pro_active() ) {
         wp_die( 'Export functionality requires CWP Snippets Pro.' );
    }

    // Check if IDs were submitted
    if ( ! isset($_POST['bulk_action_ids']) || empty($_POST['bulk_action_ids']) ) {
        // Redirect back with an error message
        // No change needed here as it uses 'bulk_status'
         wp_safe_redirect( add_query_arg('bulk_status', 'no_ids', wp_get_referer()) );
         exit;
    }

    $ids = array_filter(array_map('intval', explode(',', sanitize_text_field(wp_unslash($_POST['bulk_action_ids'])))));

    if (!empty($ids)) {
        fmcwp_export_snippets($ids);
        // Note: fmcwp_export_snippets() calls exit(), so code below won't run after export.
    } else {
        // Redirect back with an error message (should be caught by the check above, but good to have)
        // No change needed here as it uses 'bulk_status'
         wp_safe_redirect( add_query_arg('bulk_status', 'no_ids_filtered', wp_get_referer()) );
         exit;
    }
}


// *********************************************************************************************************************************
// Export Snippets Function

function fmcwp_export_snippets($ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // Fetch snippets based on the provided IDs
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $query = $wpdb->prepare("SELECT * FROM $table_name WHERE id IN ($placeholders)", $ids);
    $snippets = $wpdb->get_results($query);

    if (!empty($snippets)) {
        // Get the type from the first snippet (assuming all snippets have the same type)
        $type = $snippets[0]->type;

        // Prepare the data for export
        $export_data = array();
        foreach ($snippets as $snippet) {
            $export_data[] = array(
                'id' => $snippet->id,
                'name' => $snippet->name,
                'shortcode' => $snippet->shortcode,
                'code' => $snippet->code,
                'css' => $snippet->css,
                'type' => $snippet->type,
                'status' => $snippet->status,
                'time' => $snippet->time,
                'modified_time' => $snippet->modified_time,
                'description' => $snippet->description,
                'location' => $snippet->location,
                'priority' => $snippet->priority,
            );
        }

    // Convert the data to JSON using WP helper
    $json_data = json_encode($export_data, JSON_PRETTY_PRINT);

        // Set headers to force download with the customized file name
        $file_name = 'selected-' . $type . 's.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . strlen($json_data));

    // Echo the JSON-encoded export data directly
    echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
    exit;
    }
}
