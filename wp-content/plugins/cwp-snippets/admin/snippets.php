<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


// *********************************************************************************************************************************
// Handle Create Snippet Form Submission (Revised for minimal change + notice fix)
function fmcwp_handle_create_snippet() {
    // Add global $wpdb declaration
    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';
    $transient_key = 'fmcwp_active_editor';

    // Check if the form was submitted for creating a new snippet
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'fmcwp-snippets' && isset($_POST['new_name'], $_POST['new_code'], $_POST['new_css'], $_POST['new_type'], $_POST['active_editor'], $_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'create_edit_snippet')) {


        // Use wp_unslash as in original block
        $new_name = wp_unslash($_POST['new_name']);
        $new_code = wp_unslash($_POST['new_code']);
        $new_css = wp_unslash($_POST['new_css']);
        $new_type = wp_unslash($_POST['new_type']);
        $active_editor_val = wp_unslash($_POST['active_editor']);
        // --- START: Retrieve Description ---
        $description = isset($_POST['new_description']) ? wp_kses_post(wp_unslash($_POST['new_description'])) : ''; // Added description, sanitize with wp_kses_post
        // --- END: Retrieve Description ---


        // --- START: Retrieve Location and Priority ---
        $new_location = 'everywhere'; // Default for non-applicable types or if not set/invalid
        $new_priority = 10; // Default priority
        $allowed_locations = ['frontend', 'admin', 'everywhere'];

        if (in_array($new_type, ['Function', 'Script', 'Style'])) {
            if (isset($_POST['new_location']) && in_array($_POST['new_location'], $allowed_locations)) {
                $new_location = sanitize_text_field(wp_unslash($_POST['new_location']));
            }
            if (isset($_POST['new_priority'])) {
                $new_priority = max(1, intval($_POST['new_priority'])); // Ensure priority is at least 1
            }
        }
        // --- END: Retrieve Location and Priority ---


        // Generate shortcode (original logic)
        $shortcode = ''; // Default to empty for types that shouldn't have shortcodes
        if (in_array($new_type, ['Snippet', 'Template', 'Sample'])) {
            if ( $new_type == 'Template' ){ $scprefix = 'cwp-tmpl-'; }
            elseif($new_type == 'Sample'){ $scprefix = 'cwp-smpl-'; }
            // elseif($new_type == 'Function'){ $scprefix = 'cwp-fnct-'; } // No shortcode
            // elseif($new_type == 'Script'){ $scprefix = 'cwp-script-'; } // No shortcode
            // elseif($new_type == 'Style'){ $scprefix = 'cwp-style-'; }   // No shortcode
            else{ $scprefix = 'cwp-snip-'; } // Default for 'Snippet'

            $name_formatted = strtolower(trim(sanitize_text_field($new_name)));
            $shortcode = '[' . $scprefix . str_replace(' ', '-', $name_formatted) . ']';
        }

        // Get Version
        $version = !empty($_POST['new_version']) ? $_POST['new_version'] : '1.0.0';

        // Check for function conflicts
        $conflict_result = fmcwp_check_code_conflicts($new_code, 0, $new_location);
        $had_conflict = $conflict_result['conflict'];

        // Get status from the form, default to 1 (active) if not set
        $new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 1;

        // Only block saving if the snippet is active and has a conflict
        if ($had_conflict && $new_status == 1) {
            // --- START: Store submitted data in a transient for repopulation ---
            $conflict_data_transient_key = 'fmcwp_conflict_data_' . get_current_user_id();
            $submitted_data = array(
                'name'        => $new_name,
                'code'        => $new_code,
                'css'         => $new_css,
                'type'        => $new_type,
                'description' => $description,
                'location'    => $new_location,
                'priority'    => $new_priority,
                'status'      => $new_status,
                'version'     => $version,
            );
            set_transient($conflict_data_transient_key, $submitted_data, 60); // Store for 60 seconds
            // --- END: Store submitted data ---

            // --- Conflict Handling: Redirect with notice ---
            $notice_code = 'create_conflict';
            $args = array(
                'page' => 'fmcwp-snippets',
                'action' => 'new',
                'filter_type' => sanitize_text_field($new_type),
                'fmcwp_notice' => $notice_code,
                'conflict_name' => urlencode($conflict_result['name']),
                'conflict_type' => $conflict_result['type']
            );
            $referer_url = admin_url('admin.php'); // Base URL for this redirect
            $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'action', 'id', 'migrate_ls', 'filter_type', 'conflict_name', 'conflict_type'), $referer_url ); // Clean potential existing args
            $redirect_url = add_query_arg( $args, $base_redirect_url ); // Add all args to cleaned base

            wp_safe_redirect($redirect_url);
            exit;
        }

           if(isset($_GET['id'])) {
                $tID = absint($_GET['id']);
           }  else {
                $tID = "N\A";
           }

           // Only check for PHP syntax errors on PHP-based snippets
           if (!in_array($new_type, ['Script', 'Style'])) {
               // check PHP code for syntax errors  (important: atm we can only handle PHP with our libraries)
                // @todo alex add JS and HTML syntax validation
                $syntax_check = fmcwp_check_php_syntax($new_code, $new_name, $tID);
                
                // for now, let them go on and give them a warning
                // do not prevent them from redirecting
                // pull variable from options, at the moment we'll set it ourself
                 // $syntax_check['type'] = 'fatal'; // for debugging

                if($syntax_check['error']) {
                    
                    $syntax_check['snippet_name'] = $new_name;
                    if($syntax_check['type'] == 'warning') {

                            fmcwp_show_syntax_warning($syntax_check, 'warning');

                    } else if($syntax_check['type'] == 'fatal') {
                            
                            // backup code 
                            set_transient('new_snippet_code', $new_code, HOUR_IN_SECONDS * 24);

                            // fatal should be handled differently
                             $syntax_check['message'] = "(Fatal Error in new snippet syntax!) " . $syntax_check['message'];                   
                            // fmcwp_show_syntax_warning($syntax_check, 'fatal');
                            $notice_code = 'fatal_creation_syntax_error'; // New notice code
                            $referer_url = wp_get_referer();
                            $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'error_message'), $referer_url );
                            $redirect_url = add_query_arg(
                                array(
                                    'fmcwp_notice' => $notice_code,
                                    'error_message' => urlencode($syntax_check['message'])
                                ),
                                $base_redirect_url
                            );
                            fmcwp_show_syntax_warning($syntax_check, 'fatal');
                            wp_safe_redirect( $redirect_url );                                 
                            exit;
                    }

                }
           }
        
// ####### END SYNTAX CHECK


            // --- Success Handling ---
            // Get status from the form, default to 1 (active) if not set
            $new_status = isset($_POST['new_status']) ? intval($_POST['new_status']) : 1; // <-- Add this line

            $insert_data = array( // <-- Keep original name $insert_data
                'name' => sanitize_text_field($new_name),
                'shortcode' => $shortcode,
                'code' => $new_code,
                'css' => $new_css,
                'time' => current_time('mysql'),
                'modified_time' => current_time('mysql'), // Add this line
                'type' => sanitize_text_field($new_type),
                'status' => $new_status, // <-- Use the variable here
                'location' => 'everywhere', // Default value if not overridden
                'priority' => 10,         // Default value if not overridden
                'description' => $description, // Added description
                'version' => sanitize_text_field($version), // added version 1.8.0
            );
            // --- START: Define data format array ---
            $data_format = array( // <-- Keep original name $data_format
                '%s', // name
                '%s', // shortcode
                '%s', // code
                '%s', // css
                '%s', // time
                '%s', // modified_time <-- Add this line
                '%s', // type
                '%d', // status (already %d)
                '%s', // location
                '%d', // priority
                '%s', // description <-- Add format for description
                '%s', // version (varchar(20))
            );


            // Only override defaults if the type is applicable and values were submitted
            if (in_array($new_type, ['Function', 'Script', 'Style'])) {
                $insert_data['location'] = $new_location; // Use sanitized/validated value
                $insert_data['priority'] = $new_priority; // Use sanitized/validated value
                // Formats are already set above, no change needed here
            }
            // --- END: Add location and priority to insert data ---

            $inserted = $wpdb->insert(
                $table_name,
                $insert_data, // Use original variable name
                $data_format  // Use original variable name
            );


                if ( $inserted === false) {
                // --- Database Error Handling: Redirect with notice ---
                $notice_code = 'create_db_error';
                // Log the error for debugging
                //cwp_snippets_conditional_log('DB Insert Error: ' . $wpdb->last_error . ' | Data: ' . print_r( $insert_data, true ));
                cwpLog('DB Insert Error', $wpdb->last_error . ' | Data: ' . print_r( $insert_data, true ), $new_name, 0, 0);
                $args = array(
                    'page' => 'fmcwp-snippets',
                    'action' => 'new',
                    'filter_type' => sanitize_text_field($new_type),
                    'fmcwp_notice' => $notice_code
                );
                $referer_url = admin_url('admin.php'); // Base URL for this redirect
                $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'action', 'id', 'migrate_ls', 'filter_type', 'conflict_func'), $referer_url ); // Clean potential existing args
                $redirect_url = add_query_arg( $args, $base_redirect_url ); // Add all args to cleaned base

                wp_safe_redirect($redirect_url);
                exit;
            }

            // --- Successful Insertion ---
            $new_id = $wpdb->insert_id;

            // Store the active editor state in a transient (still useful for immediate redirect)
            set_transient($transient_key, sanitize_text_field($active_editor_val), 15);

            // Determine the notice code for the redirect.
            $notice_code = 'create_success'; // Default success notice.

            // If the snippet was saved inactive but had a conflict, we add a special notice.
            if ($had_conflict && $new_status == 0) {
                $notice_code = 'create_success_with_conflict_warning';
            }

            $args = array(
                'page' => 'fmcwp-snippets',
                'action' => 'edit',
                'id' => $new_id,
                'filter_type' => sanitize_text_field($new_type),
                'fmcwp_notice' => $notice_code,
                'migrate_ls' => 'new' // Add flag: migrate from 'new'
            );

            // If we have the special warning, add the conflict details to the URL for the notice.
            if ($notice_code === 'create_success_with_conflict_warning') {
                $args['conflict_name'] = urlencode($conflict_result['name']);
                $args['conflict_type'] = $conflict_result['type'];
            }

            // --- START: Modify remove_query_arg to include migrate_ls ---
            $referer_url = admin_url('admin.php'); // Base URL for this redirect
            // Clean potential existing args from the base URL before adding new ones
            $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'action', 'id', 'migrate_ls', 'filter_type', 'conflict_func'), $referer_url );
            // --- END: Modify remove_query_arg ---
            $redirect_url = add_query_arg( $args, $base_redirect_url ); // Add all args to cleaned base

            // Use wp_safe_redirect
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

add_action('admin_init', 'fmcwp_handle_create_snippet');


// *********************************************************************************************************************************
// Handle Update Snippet Form Submission (Revised for minimal change + notice fix)
function fmcwp_handle_update_snippet() {
    // Add global $wpdb declaration

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';
    $transient_key = 'fmcwp_active_editor';

    // Check if the form was submitted for updating an existing snippet
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'fmcwp-snippets' && isset($_POST['update_id'], $_POST['update_name'], $_POST['update_code'], $_POST['update_css'], $_POST['update_type'], $_POST['active_editor'], $_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'create_edit_snippet')) {


        // Use wp_unslash as in original block
        $update_id = intval($_POST['update_id']);
        $update_name = wp_unslash($_POST['update_name']);
        $update_code = wp_unslash($_POST['update_code']);
        $update_css = wp_unslash($_POST['update_css']);
        $update_type = wp_unslash($_POST['update_type']);
        $active_editor_val = wp_unslash($_POST['active_editor']);
        // --- START: Retrieve Description ---
        $description = isset($_POST['update_description']) ? wp_kses_post(wp_unslash($_POST['update_description'])) : ''; // Added description
        // --- END: Retrieve Description ---


        // --- START: Retrieve Location and Priority ---
        $update_location = 'frontend'; // Default for non-applicable types or if not set/invalid
        $update_priority = 10; // Default priority
        $allowed_locations = ['frontend', 'admin', 'everywhere'];

        if (in_array($update_type, ['Function', 'Script', 'Style'])) {
            if (isset($_POST['update_location']) && in_array($_POST['update_location'], $allowed_locations)) {
                $update_location = sanitize_text_field(wp_unslash($_POST['update_location']));
            }
            if (isset($_POST['update_priority'])) {
                $update_priority = max(1, intval($_POST['update_priority'])); // Ensure priority is at least 1
            }
        }
        // --- END: Retrieve Location and Priority ---

        // Set Version
        $version = !empty($_POST['update_version']) ? $_POST['update_version'] : '1.0.0';

        // Generate shortcode (original logic)
        $shortcode = ''; // Default to empty for types that shouldn't have shortcodes
        if (in_array($update_type, ['Snippet', 'Template', 'Sample'])) {
            if ( $update_type == 'Template' ){ $scprefix = 'cwp-tmpl-'; }
            elseif($update_type == 'Sample'){ $scprefix = 'cwp-smpl-'; }
            // elseif($update_type == 'Function'){ $scprefix = 'cwp-fnct-'; } // No shortcode
            // elseif($update_type == 'Script'){ $scprefix = 'cwp-script-'; } // No shortcode
            // elseif($update_type == 'Style'){ $scprefix = 'cwp-style-'; }   // No shortcode
            else{ $scprefix = 'cwp-snip-'; } // Default for 'Snippet'

            $name_formatted = strtolower(trim(sanitize_text_field($update_name)));
            $shortcode = '[' . $scprefix . str_replace(' ', '-', $name_formatted) . ']';
        }



        // Get status from the form, default to 1 (active) if not set
        $update_status = isset($_POST['update_status']) ? intval($_POST['update_status']) : 1;

        // Check for function conflicts
        $conflict_result = fmcwp_check_code_conflicts($update_code, $update_id, $update_location);
        $had_conflict = $conflict_result['conflict'];

        // Only block saving if the snippet is active and has a conflict
        if ($had_conflict && $update_status == 1) {
            // --- START: Store submitted data in a transient for repopulation ---
            $conflict_data_transient_key = 'fmcwp_conflict_data_' . get_current_user_id();
            $submitted_data = array(
                'name'        => $update_name,
                'code'        => $update_code,
                'css'         => $update_css,
                'type'        => $update_type,
                'description' => $description,
                'location'    => $update_location,
                'priority'    => $update_priority,
                'status'      => $update_status,
                'version'     => $version,
            );
            set_transient($conflict_data_transient_key, $submitted_data, 60); // Store for 60 seconds
            // --- END: Store submitted data ---


            // --- Conflict Handling: Redirect with notice ---
            $notice_code = 'update_conflict';
            $args = array(
                'page' => 'fmcwp-snippets',
                'action' => 'edit',
                'id' => $update_id,
                'filter_type' => sanitize_text_field($update_type),
                'fmcwp_notice' => $notice_code,
                'conflict_name' => urlencode($conflict_result['name']),
                'conflict_type' => $conflict_result['type']
            );
            $referer_url = admin_url('admin.php');
            $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'conflict_name', 'conflict_type'), $referer_url );
            $redirect_url = add_query_arg( $args, $base_redirect_url );

            wp_safe_redirect($redirect_url);
            exit;

        }

        // Only check for PHP syntax errors on PHP-based snippets
        if (!in_array($update_type, ['Script', 'Style'])) {
            // Success on the conflict, lets check syntax before we push the update
            // ######## STANDARD DISPLAY WARNING FOR SYNTAX

            // check PHP code for syntax errors  (important: atm we can only handle PHP with our libraries)
            // @todo alex add JS and HTML syntax validation
            $syntax_check = fmcwp_check_php_syntax($update_code, $update_name, $update_id);
            
            // for now, let them go on and give them a warning
            // do not prevent them from redirecting
            // pull variable from options, at the moment we'll set it ourself
            // $syntax_check['type'] = 'fatal'; // pull from options later
            
            if($syntax_check['error'] != '') {
                $syntax_check['snippet_type'] = $update_type;
                // if we have an error we need to pass the snippet name
                $syntax_check['snippet_name'] = $update_name;
                // what kind of error :: warning : fatal
                // not perfected, but built to handle multiple types based on what
                // our function returns
            
                if($syntax_check['type'] == 'warning') {
            
                        // warning should be handled differently
                        fmcwp_show_syntax_warning($syntax_check, 'warning');
            
                } else if($syntax_check['type'] == 'fatal') {
            
                        // this tells our html form to "remember" last updates
                        set_transient('new_snippet_code', $update_code, HOUR_IN_SECONDS * 7);
            
                        // fatal should be handled uniquely
                        $syntax_check['message'] = "Fatal Error - Update Prevented!! " . $syntax_check['message'];                   
                            // fmcwp_show_syntax_warning($syntax_check, 'fatal');
                        $notice_code = 'fatal_update_error';
                        $referer_url = wp_get_referer();
                        $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'error_message'), $referer_url );
                        $redirect_url = add_query_arg(
                            array(
                                'fmcwp_notice' => $notice_code,
                                'error_message' => urlencode($syntax_check['message'])
                            ),
                            $base_redirect_url
                        );
                        fmcwp_show_syntax_warning($syntax_check, 'fatal');
                        wp_safe_redirect( $redirect_url );                                 
                        exit;
                }
            
            }
            // ####### END SYNTAX CHECK
        }

            // --- Success Handling ---
            // Note: $update_status was already fetched before the conflict check
            $update_data = array( // <-- Keep original name $update_data
                'name' => sanitize_text_field($update_name),
                'shortcode' => $shortcode,
                'code' => $update_code,
                'css' => $update_css,
                'type' => sanitize_text_field($update_type),
                'status' => $update_status, // <-- Add status here
                'modified_time' => current_time('mysql'), // Add this line
                'description' => $description, // Added description
                'version' => sanitize_text_field($version), // added version 1.8.0
                // Location and priority handled below
            );
            // --- START: Define data format array ---
            $data_format = array( // <-- Keep original name $data_format
                '%s', // name
                '%s', // shortcode
                '%s', // code
                '%s', // css
                '%s', // type
                '%d', // status <-- Add format here
                '%s', // modified_time <-- Add this line
                '%s', // description <-- Add format for description
                '%s', // version (varchar(20)
            );
            // --- END: Define data format array ---

            if (in_array($update_type, ['Function', 'Script', 'Style'])) {
                $update_data['location'] = $update_location; // Use sanitized/validated value
                $update_data['priority'] = $update_priority; // Use sanitized/validated value
                // --- START: Add formats for location and priority ---
                $data_format[] = '%s'; // location
                $data_format[] = '%d'; // priority
                // --- END: Add formats for location and priority ---
            } else {
                // If type changed *from* Function/Script/Style to something else,
                // reset location/priority to their database defaults.
                $update_data['location'] = 'frontend'; // Match DB default
                $update_data['priority'] = 10;         // Match DB default
                // --- START: Add formats for location and priority (defaults) ---
                $data_format[] = '%s'; // location
                $data_format[] = '%d'; // priority
                // --- END: Add formats for location and priority (defaults) ---
            }
            // --- END: Add location and priority to update data ---

            // --- START: Define where format array ---
            $where_format = array('%d'); // id
            // --- END: Define where format array ---

            $updated = $wpdb->update(
                $table_name,
                $update_data, // Use original variable name
                array('id' => $update_id), // WHERE clause
                $data_format,              // Use original variable name
                $where_format              // Use original variable name
            );


                if ($updated === false) {
                // --- Database Error Handling: Redirect with notice ---
                $notice_code = 'update_db_error';
                // Log the error for debugging
                // cwp_snippets_conditional_log('DB Update Error: ' . $wpdb->last_error . ' | Data: ' . print_r( $update_data, true )); // legacy               
                cwpLog('DB Update Error', $wpdb->last_error . ' | Data: ' . print_r( $update_data, true ), $update_name, $update_id, 0);
                $args = array(
                    'page' => 'fmcwp-snippets',
                    'action' => 'edit',
                    'id' => $update_id,
                    'filter_type' => sanitize_text_field($update_type),
                    'fmcwp_notice' => $notice_code
                );
                $referer_url = admin_url('admin.php');
                $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
                $redirect_url = add_query_arg( $args, $base_redirect_url );

                wp_safe_redirect($redirect_url);
                exit;
            }

            // --- Successful Update ---
            // Automatically dismiss any fatal error notices for this snippet upon successful save.
            delete_transient('cwp_fatal_error_' . $update_id);

            // Store the active editor state in a transient
            set_transient($transient_key, sanitize_text_field($active_editor_val), 15);

            // Determine the notice code for the redirect.
            $notice_code = 'update_success'; // Default success notice.

            // If the snippet was saved inactive but had a conflict, we add a special notice.
            if ($had_conflict && $update_status == 0) {
                $notice_code = 'update_success_with_conflict_warning';
            }

            $args = array(
                'page' => 'fmcwp-snippets',
                'action' => 'edit',
                'id' => $update_id,
                'filter_type' => sanitize_text_field($update_type),
                'fmcwp_notice' => $notice_code
            );

            // If we have the special warning, add the conflict details to the URL for the notice.
            if ($notice_code === 'update_success_with_conflict_warning') {
                $args['conflict_name'] = urlencode($conflict_result['name']);
                $args['conflict_type'] = $conflict_result['type'];
            }

            $referer_url = admin_url('admin.php');
            $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'conflict_name', 'conflict_type'), $referer_url );
            $redirect_url = add_query_arg( $args, $base_redirect_url );

            // Use wp_safe_redirect
            wp_safe_redirect($redirect_url);
            exit;
        
        // --- End of minimally changed block ---
     }
}
add_action('admin_init', 'fmcwp_handle_update_snippet');



// *********************************************************************************************************************************
// Handle Delete Snippet Form Submission
function fmcwp_handle_delete_snippet() {
    // 1. Verify Nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fmcwp_delete_snippet_action_' . intval( $_POST['delete_id'] ?? 0 ) ) ) {
        wp_die( esc_html__( 'Nonce verification failed.', 'cwp-snippets' ) );
    }

    // Check User Capability
    if ( ! current_user_can( 'manage_options' ) ) { // Or a more specific capability
        wp_die( esc_html__( 'You do not have permission to delete snippets.', 'cwp-snippets' ) );
    }

    // Check if delete_id is set
    if ( isset( $_POST['delete_id'] ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_snippets';
        $id_to_delete = intval( $_POST['delete_id'] );

        // Perform Deletion
        $deleted = $wpdb->delete(
            $table_name,
            array( 'id' => $id_to_delete ),
            array( '%d' )
        );

        // Prepare Redirect URL with Notice
        $notice_code = ( $deleted !== false ) ? 'deleted' : 'delete_error';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );


    } else {
        // If delete_id wasn't set, redirect back without a specific notice or with an 'invalid_request' notice
        $notice_code = 'invalid_request';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
    }

    // Redirect
    wp_safe_redirect( $redirect_url );
    exit;
}

add_action( 'admin_post_fmcwp_delete_snippet', 'fmcwp_handle_delete_snippet' );



// *********************************************************************************************************************************
// Handle Toggle Snippet Status Form Submission
function fmcwp_handle_toggle_status() {

   
    // Verify Nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fmcwp_toggle_status_action_' . intval( $_POST['toggle_status_id'] ?? 0 ) ) ) {
        wp_die( esc_html__( 'Nonce verification failed.', 'cwp-snippets' ) );
    }

    // Check User Capability
    if ( ! current_user_can( 'manage_options' ) ) { // Or a more specific capability
        wp_die( esc_html__( 'You do not have permission to change snippet status.', 'cwp-snippets' ) );
    }

    // Check if toggle_status_id is set
    if ( isset( $_POST['toggle_status_id'] ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_snippets';
        $id_to_toggle = intval( $_POST['toggle_status_id'] );

        // --- START: Modified to fetch full snippet for conflict check ---
        // Get the full snippet data to check its type and code before activating
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is derived from $wpdb->prefix and is trusted
    $snippet_to_toggle = $wpdb->get_row( $wpdb->prepare( "SELECT id, code, type, status, location FROM $table_name WHERE id = %d", $id_to_toggle ) );

        // Check if snippet exists
        if ( $snippet_to_toggle === null ) {
             $notice_code = 'toggle_not_found';
             $referer_url = wp_get_referer();
             $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
             $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
             wp_safe_redirect( $redirect_url );
             exit;
        }

        // --- START: Add conflict check on activation ---
        // If we are trying to ACTIVATE (current status is 0) a snippet that contains PHP code
        if ( $snippet_to_toggle->status == 0 && in_array($snippet_to_toggle->type, ['Function', 'Snippet', 'Template', 'Sample']) ) {
            
            // get snippet name & type for logging and for potential syntax error handling
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
            $d = $wpdb->get_row( $wpdb->prepare( "SELECT name FROM $table_name WHERE id = %d", $id_to_toggle ) );
                if($d) {
                    $syntax_check['snippet_name'] = $d->name;
                    $syntax_check['snippet_type'] = $d->type; // we need this if there is an error
                }

            // check PHP code for syntax errors  (important: atm we can only handle PHP with our libraries)
            // @todo alex add JS and HTML syntax validation
            $syntax_check = fmcwp_check_php_syntax($snippet_to_toggle->code, $d->name, $id_to_toggle);

            // for now, let them go on and give them a warning
            // do not prevent them from redirecting
            // pull variable from options, at the moment we'll set it ourself
            
            // $syntax_check['type'] = 'fatal'; // DEBUG ONLY!! || this should be getting returned from our fmcwp_check_php_syntax function above
            
            if($syntax_check['error']) {
                                
                if($syntax_check['type'] == 'warning') {

                        fmcwp_show_syntax_warning($syntax_check, 'warning');

                } else if($syntax_check['type'] == 'fatal') {

                        // fatal should be handled differently
                        $syntax_check['message'] = "(Fatal Error - Update Prevented!!) " . $syntax_check['message'];                   
                        // fmcwp_show_syntax_warning($syntax_check, 'fatal');
                        $notice_code = 'activation_syntax_error'; // New notice code
                        $referer_url = wp_get_referer();
                        $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'error_message'), $referer_url );
                        $redirect_url = add_query_arg(
                            array(
                                'fmcwp_notice' => $notice_code,
                                'error_message' => urlencode($syntax_check['message'])
                            ),
                            $base_redirect_url
                        );
                        fmcwp_show_syntax_warning($syntax_check, 'fatal');
                        wp_safe_redirect( $redirect_url );                                 
                        exit;
                }

            }

            $conflict_result = fmcwp_check_code_conflicts($snippet_to_toggle->code, $snippet_to_toggle->id, $snippet_to_toggle->location);
            if ($conflict_result['conflict']) {
                // Conflict found, redirect with an error notice and do NOT activate.
                $notice_code = 'activation_conflict';
                $referer_url = wp_get_referer();
                $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'conflict_name', 'conflict_type'), $referer_url );
                $redirect_url = add_query_arg(
                    array(
                        'fmcwp_notice' => $notice_code,
                        'conflict_name' => urlencode($conflict_result['name']),
                        'conflict_type' => $conflict_result['type']
                    ),
                    $base_redirect_url
                );
                wp_safe_redirect( $redirect_url );
                exit;
            }
        }
        // --- END: Add conflict check on activation ---

        // Determine new status and update
        $new_status = ( $snippet_to_toggle->status == 1 ) ? 0 : 1;
        $updated = $wpdb->update(
            $table_name,
            array( 'status' => $new_status ),
            array( 'id' => $id_to_toggle ),
            array( '%d' ),
            array( '%d' )
        );

        // --- END: Modified to fetch full snippet for conflict check ---
        // Prepare Redirect URL with Notice
        $notice_code = ( $updated !== false ) ? 'status_updated' : 'status_update_error';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );


    } else {
        $notice_code = 'invalid_request';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}

add_action( 'admin_post_fmcwp_toggle_status', 'fmcwp_handle_toggle_status' );


// *********************************************************************************************************************************
// Handle Toggle Snippet Cache Suppression
function fmcwp_handle_toggle_cache_suppression() {
    // Verify Nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fmcwp_toggle_cache_action_' . intval( $_POST['toggle_cache_id'] ?? 0 ) ) ) {
        wp_die( esc_html__( 'Nonce verification failed.', 'cwp-snippets' ) );
    }

    // Check User Capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to change snippet cache settings.', 'cwp-snippets' ) );
    }

    // Check if toggle_cache_id is set
    if ( isset( $_POST['toggle_cache_id'] ) ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_snippets';
        $id_to_toggle = intval( $_POST['toggle_cache_id'] );

        // Get the current suppress_cache status
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
    $current_suppress_status = $wpdb->get_var( $wpdb->prepare( "SELECT suppress_cache FROM $table_name WHERE id = %d", $id_to_toggle ) );

        // Check if snippet exists (get_var returns NULL if not found)
        if ( $current_suppress_status === null ) {
             $notice_code = 'toggle_not_found'; // Can reuse this notice
             $referer_url = wp_get_referer();
             $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
             $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
             wp_safe_redirect( $redirect_url );
             exit;
        }

        // Determine new status and update
        $new_suppress_status = ( $current_suppress_status == 1 ) ? 0 : 1;
        $updated = $wpdb->update(
            $table_name,
            array( 'suppress_cache' => $new_suppress_status ),
            array( 'id' => $id_to_toggle ),
            array( '%d' ), // format for value
            array( '%d' )  // format for where
        );

        // Prepare Redirect URL with Notice
        $notice_code = ( $updated !== false ) ? 'cache_status_updated' : 'cache_status_update_error';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );

    } else {
        $notice_code = 'invalid_request';
        $referer_url = wp_get_referer();
        $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
        $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_fmcwp_toggle_cache_suppression', 'fmcwp_handle_toggle_cache_suppression' );



// *********************************************************************************************************************************
// Handle Duplicate Snippet Form Submission
function fmcwp_handle_duplicate_snippet() {
    // 1. Verify Nonce
    $duplicate_id = isset($_POST['duplicate_id']) ? intval($_POST['duplicate_id']) : 0;
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'fmcwp_duplicate_snippet_action_' . $duplicate_id ) ) {
        wp_die( esc_html__( 'Nonce verification failed for duplicate snippet.', 'cwp-snippets' ) );
    }

    // 2. Check User Capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to duplicate snippets.', 'cwp-snippets' ) );
    }

    // 3. Check if duplicate_id is set
    if ( $duplicate_id > 0 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_snippets';

        // 4. Fetch the original snippet
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
    $original_snippet = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $duplicate_id ), ARRAY_A );

        if ( $original_snippet ) {
            // 5. Prepare new snippet data
            $new_snippet_data = $original_snippet;
            unset( $new_snippet_data['id'] ); // Remove original ID

            // --- START: Generate a unique name for the new snippet ---
            $base_name = $original_snippet['name'];
            $base_type = $original_snippet['type'];
            $counter = 1;
            $new_name = $base_name . ' (copy)';

            // Loop to find a unique name by checking for "Name (copy)", "Name (copy 2)", etc.
            while (null !== $wpdb->get_var($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                "SELECT id FROM {$table_name} WHERE name = %s AND type = %s",
                $new_name,
                $base_type
            ))) {
                $counter++;
                $new_name = $base_name . ' (copy ' . $counter . ')';
            }
            $new_snippet_data['name'] = $new_name;
            // --- END: Generate a unique name ---

            // Generate a new shortcode if applicable (or adjust existing)
            // This logic should mirror your create/update shortcode generation
            $new_snippet_data['shortcode'] = ''; // Default to empty
            if (in_array($original_snippet['type'], ['Snippet', 'Template', 'Sample'])) {
                if ( $original_snippet['type'] == 'Template' ){ $scprefix = 'cwp-tmpl-'; }
                elseif($original_snippet['type'] == 'Sample'){ $scprefix = 'cwp-smpl-'; }
                // elseif($original_snippet['type'] == 'Function'){ $scprefix = 'cwp-fnct-'; }
                // elseif($original_snippet['type'] == 'Script'){ $scprefix = 'cwp-script-'; }
                // elseif($original_snippet['type'] == 'Style'){ $scprefix = 'cwp-style-'; }
                else{ $scprefix = 'cwp-snip-'; } // Default for 'Snippet'
                $name_formatted = strtolower(trim(sanitize_text_field($new_snippet_data['name'])));
                $new_snippet_data['shortcode'] = '[' . $scprefix . str_replace(' ', '-', $name_formatted) . ']';
            }

            // Set time and modified_time
            $new_snippet_data['time'] = current_time('mysql');
            $new_snippet_data['modified_time'] = current_time('mysql');

            // If type is 'Function', set status to inactive (0) by default
            if ( $original_snippet['type'] === 'Function' ) {
                $new_snippet_data['status'] = 0; // Inactive
            } else {
                // For other types, you might want to keep the original status or set to active/inactive
                // $new_snippet_data['status'] = $original_snippet['status']; // To keep original
                $new_snippet_data['status'] = 1; // Or set to active by default
            }

            // Insert the new snippet
            $inserted = $wpdb->insert( $table_name, $new_snippet_data );

            $notice_code = ( $inserted !== false ) ? 'duplicate_success' : 'duplicate_error';
        } else {
            $notice_code = 'duplicate_not_found';
        }
    } else {
        $notice_code = 'invalid_request';
    }

    // 6. Redirect
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field(wp_unslash($_POST['filter_type'])) : 'Snippet';
    $referer_url = admin_url('admin.php');
    $args = array(
        'page' => 'fmcwp-snippets',
        'filter_type' => $filter_type,
        'fmcwp_notice' => $notice_code
    );
    $redirect_url = add_query_arg( $args, $referer_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
add_action( 'admin_post_fmcwp_duplicate_snippet', 'fmcwp_handle_duplicate_snippet' );


// *********************************************************************************************************************************
// Handle ALL Bulk Action Form Submissions (Unified)
function fmcwp_handle_bulk_form() {
    // Verify Nonce (Matches the nonce in the form)
    if ( ! isset( $_POST['_wpnonce_bulk_action'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_bulk_action'] ) ), 'fmcwp_bulk_action_nonce' ) ) {
        wp_die( esc_html__( 'Nonce verification failed for bulk action.', 'cwp-snippets' ) );
    }

    // Check User Capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to perform bulk actions on snippets.', 'cwp-snippets' ) );
    }

    // Check if action and IDs are set
    if ( isset( $_POST['bulk_action'], $_POST['bulk_action_ids'] ) ) {
        $action = sanitize_key( $_POST['bulk_action'] );
        $ids_raw = sanitize_text_field( wp_unslash( $_POST['bulk_action_ids'] ) );
        $ids = empty($ids_raw) ? [] : array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );

        $notice_code = '';

        // Check if any IDs were actually selected
        if ( empty( $ids ) && $action !== '' ) {
             $notice_code = 'bulk_no_ids';
        } else {
            // Decide what to do based on the selected action
            switch ( $action ) {
                case 'delete':
                case 'activate':
                case 'deactivate':
                    // Process these actions here
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'cwp_snippets';
                    $items_processed = false; // Flag to track success

                    if ($action === 'delete') {
                        $ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is derived from $wpdb->prefix and is trusted
                        $sql = $wpdb->prepare( "DELETE FROM $table_name WHERE id IN ($ids_placeholder)", ...$ids );
                        $items_processed = $wpdb->query( $sql );
                        $notice_code = ( $items_processed !== false ) ? 'bulk_deleted' : 'bulk_delete_error';
                    } elseif ($action === 'activate') {
                        // --- START: Bulk Activation with Conflict/Syntax Checks ---
                        $ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                        // Fetch all 'Function' snippets that are part of the bulk activation request
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is a trusted table name built from $wpdb->prefix
                        $snippets_to_check = $wpdb->get_results( $wpdb->prepare(
                            "SELECT id, name, code, location FROM $table_name WHERE type = 'Function' AND id IN ($ids_placeholder)",
                            ...$ids
                        ) );

                        // Perform checks before any activation
                        foreach ($snippets_to_check as $snippet) {

                            // 2. Conflict Check
                            // check syntax secondary
                            $conflict_result = fmcwp_check_code_conflicts($snippet->code, $snippet->id, $snippet->location);
                            if ($conflict_result['conflict']) {
                                $notice_code = 'bulk_activation_conflict';
                                $referer_url = wp_get_referer();
                                $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'conflict_name', 'conflict_type', 'snippet_name'), $referer_url );
                                $redirect_url = add_query_arg(
                                    array(
                                        'fmcwp_notice' => $notice_code,
                                        'conflict_name' => urlencode($conflict_result['name']),
                                        'conflict_type' => urlencode($conflict_result['type']),
                                        'snippet_name' => urlencode($snippet->name)
                                    ),
                                    $base_redirect_url
                                );
                                wp_safe_redirect( $redirect_url );
                                exit;
                            }

                            // 2) Syntax Check
                            // Better to do syntax check AFTER conflict, as conflicts are higher priority
                            $syntax_check = fmcwp_check_php_syntax($snippet->code, $snippet->name, $snippet->id);
                            if ($syntax_check['error'] && $syntax_check['message']) {
                                $notice_code = 'bulk_activation_syntax_error';
                                $referer_url = wp_get_referer();
                                $base_redirect_url = remove_query_arg( array('fmcwp_notice', 'error_message', 'snippet_name'), $referer_url );
                                $redirect_url = add_query_arg(
                                    array(
                                        'fmcwp_notice' => $notice_code,
                                        'error_message' => urlencode($syntax_check['message']),
                                        'snippet_name' => urlencode($snippet->name)
                                    ),
                                    $base_redirect_url
                                );
                                wp_safe_redirect( $redirect_url );
                                exit;
                            }

                        }

                        // If all checks pass, proceed with activation
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
                        $sql = $wpdb->prepare( "UPDATE $table_name SET status = 1 WHERE id IN ($ids_placeholder)", ...$ids );
                        $items_processed = $wpdb->query( $sql );
                        $notice_code = ( $items_processed !== false ) ? 'bulk_activated' : 'bulk_status_error';
                        // --- END: Bulk Activation with Conflict/Syntax Checks ---

                    } else { // This is now just for 'deactivate'
                        $new_status = 0;
                        $ids_placeholder = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                        // Note: $wpdb->prepare requires the values as separate arguments after the format string
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
                        $sql = $wpdb->prepare( "UPDATE $table_name SET status = %d WHERE id IN ($ids_placeholder)", $new_status, ...$ids );
                        $items_processed = $wpdb->query( $sql );

                        if ( $items_processed !== false ) {
                            $notice_code = 'bulk_deactivated';
                        } else {
                            $notice_code = 'bulk_status_error';
                        }
                    }
                    break;

                case 'export':
                    // Call the existing export handler function directly
                    // Ensure the function exists (it's in import-export.php)
                    if ( function_exists('fmcwp_handle_bulk_export_action') ) {
                        // Note: fmcwp_handle_bulk_export_action does its own checks and calls exit()
                        fmcwp_handle_bulk_export_action();
                    } else {
                         wp_die( esc_html__( 'Export handler function not found.', 'cwp-snippets' ) );
                    }
                    // Execution stops here if export runs successfully
                    break;

                case '': // No action selected
                     $notice_code = 'bulk_no_action';
                     break;

                default:
                    // Invalid action selected
                    $notice_code = 'bulk_invalid_action';
                    break;
            }
        }

    } else {
        // Action or IDs missing
        $notice_code = 'bulk_missing_data';
    }

    // Redirect (only if not exporting)
    $referer_url = wp_get_referer();
    $base_redirect_url = remove_query_arg( 'fmcwp_notice', $referer_url );
    $redirect_url = add_query_arg( 'fmcwp_notice', $notice_code, $base_redirect_url );
    wp_safe_redirect( $redirect_url );
    exit;
}
// Hook to the new action name used in the form
add_action( 'admin_post_fmcwp_handle_bulk_form', 'fmcwp_handle_bulk_form' );

/**
 * Handles the user-triggered skipping of a bundled snippet update.
 */
function fmcwp_handle_skip_bundled_update() {
    // Check if this is our action
    if (!isset($_GET['fmcwp_action']) || $_GET['fmcwp_action'] !== 'skip_bundled_update' || !isset($_GET['type'])) {
        return;
    }

    $type = sanitize_key($_GET['type']);
    $allowed_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];
    if (!in_array($type, $allowed_types)) {
        // Invalid type, do nothing.
        return;
    }

    // Security checks
    check_admin_referer('fmcwp_skip_bundled_' . $type);
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'You do not have permission to skip bundled snippet updates.', 'cwp-snippets' ) );
    }

    // Update the stored hash to the current file's hash. This marks the update as "seen".
    $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $type . '.json';
    if (file_exists($file_path)) {
        $hash = md5_file($file_path);
        update_option('cwp_snippets_' . $type . '_hash', $hash);
    }

    // Delete the transient that triggered the notice
    delete_transient('cwp_update_available_' . $type);

    // Redirect back with a success message
    $redirect_url = remove_query_arg(['fmcwp_action', 'type', '_wpnonce']);
    $redirect_url = add_query_arg(['fmcwp_notice' => 'skip_bundled_success', 'skipped_type' => $type], $redirect_url);
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'fmcwp_handle_skip_bundled_update');

/**
 * Handles the user-triggered skipping of all bundled snippet updates.
 */
function fmcwp_handle_skip_all_bundled() {
    // Check if this is our action
    if (!isset($_GET['fmcwp_action']) || $_GET['fmcwp_action'] !== 'skip_all_bundled') {
        return;
    }

    // Security checks
    check_admin_referer('fmcwp_skip_all_bundled_nonce');
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'You do not have permission to skip bundled snippet updates.', 'cwp-snippets' ) );
    }

    $snippet_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];
    $skipped_count = 0;

    foreach ($snippet_types as $type) {
        // Only skip if a transient for this type exists
        if (get_transient('cwp_update_available_' . $type)) {
            // Update the stored hash to the current file's hash. This marks the update as "seen".
            $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $type . '.json';
            if (file_exists($file_path)) {
                $hash = md5_file($file_path);
                update_option('cwp_snippets_' . $type . '_hash', $hash);
            }

            // Delete the transient
            delete_transient('cwp_update_available_' . $type);
            $skipped_count++;
        }
    }

    // Redirect back with a success message
    $redirect_url = remove_query_arg(['fmcwp_action', '_wpnonce']);
    $redirect_url = add_query_arg(['fmcwp_notice' => 'skip_all_bundled_success', 'skipped_count' => $skipped_count], $redirect_url);
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'fmcwp_handle_skip_all_bundled');

/**
 * Handles the dismissal of a fatal error notice.
 */
function fmcwp_handle_dismiss_fatal_error_notice() {
    if (!isset($_GET['fmcwp_action']) || $_GET['fmcwp_action'] !== 'dismiss_fatal_error' || !isset($_GET['snippet_id'])) {
        return;
    }

    $snippet_id = intval($_GET['snippet_id']);
    if ($snippet_id <= 0) {
        return;
    }

    // Security checks
    check_admin_referer('fmcwp_dismiss_fatal_' . $snippet_id, '_cwp_nonce_dismiss_fatal');
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'cwp-snippets' ) );
    }

    // Delete the transient that triggers the notice
    delete_transient('cwp_fatal_error_' . $snippet_id);

    // Check if this is a "Review & Dismiss" action
    if (isset($_GET['review']) && $_GET['review'] === '1' && isset($_GET['snippet_type'])) {
        $snippet_type = sanitize_text_field(wp_unslash($_GET['snippet_type']));
        $redirect_url = admin_url('admin.php?page=fmcwp-snippets&action=edit&id=' . $snippet_id . '&filter_type=' . urlencode($snippet_type));
    } else {
        // Just a "Dismiss" action, so redirect back to the current page, removing the action parameters
        $redirect_url = remove_query_arg(['fmcwp_action', 'snippet_id', '_cwp_nonce_dismiss_fatal', 'review', 'snippet_type']);
    }
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'fmcwp_handle_dismiss_fatal_error_notice');

/**
 * Handles the dismissal of the demo database update notice.
 */
function fmcwp_handle_dismiss_demo_db_notice() {
    // Check if this is our action
    if (!isset($_GET['fmcwp_action']) || $_GET['fmcwp_action'] !== 'dismiss_demo_db_update_notice') {
        return;
    }

    // Security checks
    check_admin_referer('fmcwp_dismiss_demo_db_notice_nonce');
    if (!current_user_can('manage_options')) {
        wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'cwp-snippets' ) );
    }

    // Update the stored hash to the current file's hash to mark as "seen".
    $demo_db_file = FMCWP_PLUGIN_PATH . 'assets/demo/CWP Snippets.fmp12';
    if (file_exists($demo_db_file)) {
        $hash = md5_file($demo_db_file);
        update_option('cwp_snippets_demo_db_hash', $hash);
    }

    // Delete the transient that triggered the notice
    delete_transient('cwp_update_available_demo_db');

    // Redirect back to the current page, removing the action parameters
    $redirect_url = remove_query_arg(['fmcwp_action', '_wpnonce']);
    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_init', 'fmcwp_handle_dismiss_demo_db_notice');

// *********************************************************************************************************************************
// This will handle creating a consistent warning for syntax issues
// we need to pass the warning array 
function fmcwp_show_syntax_warning($warning, $warningAction = null) {

    // warningAction = warning, update (for updating function), fatal
    // warning['error'] = boolean, ['error_type'] = str type, ['message'] = str message,

    if ( ! is_array( $warning ) || $warningAction == null ) {
        // nothing to return
        return;
    }

    // @todo Alex  - pull the url/snippet name for the error
    if ( isset( $warning['url'] ) ) {
        $warning['url'] = "<span><a href='" . $warning['url'] . "' target='_blank'>Click here to edit the snippet.</a></span>";
    } else {
        $warning['url'] = "";
    }

    if ( $warningAction == 'warning' ) {

        // PHP syntax failed - Prepare the error message
        /* translators: 1: the error message, 2: optional URL HTML to edit the snippet, 3: starting line number where error occurred */
        $formatted_error_message = sprintf( __( '<span style="background-color:#efefef; padding:3px; color:#000;">%1$s</span>.  %2$s<br><span style="color:#dba617;">Error occurred on line %3$d.</span>', 'cwp-snippets' ),
            $warning['error_message'],
            $warning['url'],
            $warning['starting_line']
        );

        // Include the error type for more detail, if available.
        if ( ! empty( $warning['error_type'] ) ) {
            /* translators: 1: error type (e.g. "Parse"), 2: formatted error message HTML */
            $formatted_error_message = sprintf( __( '<span style="font-size:18px;">CWP %1$s Warning</span>: %2$s', 'cwp-snippets' ),
                $warning['error_type'],
                $formatted_error_message
            );
        }

        // $GLOBALS['cwp_runtime_notices'][] = ['message' => $formatted_error_message, 'type' => 'warning'];
        set_transient( 'cwp_custom_admin_notice', $warning, MINUTE_IN_SECONDS * 10 ); // will work, wp_admin_notice
        cwp_display_admin_notice_from_transient();
        return; // end of function

    } else if ( $warningAction == 'allowed' ) {

        // PHP syntax failed - Prepare the error message for 'allowed'
        /* translators: 1: the error message, 2: optional URL HTML to edit the snippet, 3: starting line number where error occurred */
        $formatted_error_message = sprintf( __( '<span style="background-color:#efefef; padding:3px; color:#000;">%1$s</span>.  %2$s<br><span style="color:#dba617;">Error occurred on line %3$d.</span>', 'cwp-snippets' ),
            $warning['error_message'],
            $warning['url'],
            $warning['starting_line']
        );

        // Include the error type for more detail, if available.
        if ( ! empty( $warning['error_type'] ) ) {
            /* translators: 1: error type (e.g. "Parse"), 2: formatted error message HTML */
            $formatted_error_message = sprintf( __( '<span style="font-size:18px;">CWP %1$s Warning</span>: %2$s', 'cwp-snippets' ),
                $warning['error_type'],
                $formatted_error_message
            );
        }

        // $GLOBALS['cwp_runtime_notices'][] = ['message' => $formatted_error_message, 'type' => 'warning'];
        set_transient( 'cwp_custom_admin_notice', $warning, MINUTE_IN_SECONDS * 10 ); // will work, wp_admin_notice
        cwp_display_admin_notice_from_transient();

        return; // end of function

    } else if ( $warningAction == 'fatal' ) {

        // do additional logic for fatal error (PREVENT RELOAD/UPDATE)
        // PHP syntax failed - Prepare the error message
        /* translators: 1: the error message, 2: optional URL HTML to edit the snippet, 3: starting line number where error occurred */
        $formatted_error_message = sprintf( __( '<span style="background-color:#efefef; padding:3px; color:#000;">%1$s</span>.  %2$s<br><span style="color:#dba617;">Error occurred on line %3$d.</span>', 'cwp-snippets' ),
            $warning['error_message'],
            $warning['url'],
            $warning['starting_line']
        );

        // Include the error type for more detail, if available.
        if ( ! empty( $warning['error_type'] ) ) {
            /* translators: 1: error type, 2: formatted error message HTML */
            $formatted_error_message = sprintf( __( '[%1$s] %2$s', 'cwp-snippets' ),
                $warning['error_type'],
                $formatted_error_message
            );
        }
        // since this is a fatal error we need to overrride that even if the syntax is NOT
        $warning['type'] = "Fatal";

        set_transient( 'cwp_custom_admin_notice', $warning, MINUTE_IN_SECONDS * 10 ); // will work, wp_admin_notice
        cwp_display_admin_notice_from_transient();
        // $GLOBALS['cwp_runtime_notices'][] = ['message' => $formatted_error_message, 'type' => 'error'];

    }

    // Add to global notices to be displayed later.
    // $GLOBALS['cwp_runtime_notices'][] = [ 'message' => $formatted_error_message, 'type' => $notice_type ];

}

/***************************************************************************************
 * Creates/converts our custom syntax warnings into an HTML wordpress notice format
 * Checks and toggles between different error types for the color || 'warning' = yellow, 'fatal' = red
 * These are stored in our cwp_custom_admin_notice transient
 * for 10 minutes since its just on a page load
 * 
 * $notice_data = array[] from a transient we set while checking syntax
 */
function cwp_display_admin_notice_from_transient() {
        // Check if our custom notice transient exists
    
    
    if ( $notice_data = get_transient( 'cwp_custom_admin_notice' ) ) {
     if ( ! get_transient('cwp_syntax_notice_html') ) {
        delete_transient('cwp_custom_admin_notice');
        // $notice_data['custom_message'] = "Here is where I would display a custom message about the error.";
        // Ensure we have valid data
        $message = isset($notice_data['message']) ? $notice_data['message'] : '';        
        $starting_line = isset($notice_data['starting_line']) ? $notice_data['starting_line'] : 'N\A';
        $error_type = isset($notice_data['error_type']) ? $notice_data['error_type'] : 'Unspecified Error';
        $type    = isset($notice_data['type']) ? $notice_data['type'] : 'Warning';
        $snippet_name = isset($notice_data['snippet_name']) ? $notice_data['snippet_name'] : 'Unspecified';
        $snippet_type = isset($notice_data['snippet_type']) ? $notice_data['snippet_type'] : 'Unspecified';
            $type = ucfirst($type);
        
        $temp_cm = isset($notice_data['custom_message']) ? $notice_data['custom_message'] : "";
            $custom_message = cwp_create_custom_message($temp_cm);
            
        switch($type) {
            case "Warning":
                $type_class = 'notice-warning';
                break;
            case "Fatal";
                $type_class = 'notice-error';
                $type = 'Fatal Error';
                break;
            default:
                $type_class = 'notice-warning';
                break;        
        }


        // Delete the transient immediately so it only shows once
        // delete_transient( 'cwp_custom_admin_notice' );

        // Escape message for display
        $message = wp_kses_post($message); // Sanitize HTML in message

        // Determine notice class (WordPress uses 'notice-<type>')
        $class = 'notice ' . $type_class . ' notice-' . $type . ' is-dismissible';


        // Output the admin notice HTML
        $cwp_syntax_notice = sprintf( '<div class="%1$s"><p><span style="font-size:20px; line-height: 1.5;">CWP Snippets ' . $type .':</span><br>
            <strong>Snippet Name: </strong><span style="color:#008000;"><strong>%5$s </strong></span>|<span style="color:#008000;"> %6$s</span><br>
            <strong>Error Type:</strong><span style="color: red;"> %3$s</span><br>
            <strong>Error Details:</strong><span style="color: #000;"> %2$s</span></p>
            %4$s</div>',
            esc_attr( $class ), $message, esc_html( $type ), $custom_message, $snippet_name, $snippet_type
        );

        set_transient('cwp_syntax_notice_html', $cwp_syntax_notice, 15); // 15 sec timer
     }
    }
}

// **********************************************************************************************************************************
// Called from the 
function cwp_display_syntax_notice() {
    
    // Get the HTML string directly from the transient
    if ($html_notice = get_transient('cwp_syntax_notice_html')) {

        // Echo the HTML (saved in our transient from our cwp_display_admin_notices_from_transient() function -> called from our syntax check)
        // the reason for this runaround is so it displays in the right place.
        echo wp_kses_post( $html_notice );

        // Delete the transient
        delete_transient('cwp_syntax_notice_html');

        // Double check the original is deleted
        if(get_transient('cwp_custom_admin_notice')) {
            delete_transient('cwp_custom_admin_notice');
        }

    }
}

// **********************************************************************************************************************************
// This is to handle our custom message - which is an optional variable
// in our syntax error array
function cwp_create_custom_message($custom_message) {

    if($custom_message == "") {
        return '';
    }

    $custom_message = strtolower($custom_message);

    switch($custom_message) {
        case "test":
            $msg = 'This is a test message';
            break;

        default:
            $msg = '';
            break;
    }

    if($msg !== '') {
        $final_message = '<br><span style="color:#000;">' . $msg . '</span>';
    } else {
        $final_message = '';
    }


    return $final_message;
}
// *********************************************************************************************************************************
//Dispaly HTML Admin Page

function fmcwp_page_html() {

    enqueue_font_awesome();

    fmcwp_header();

    fmcwp_display_runtime_notices();
    // show our syntax errors
    
    cwp_display_syntax_notice();   


    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';

    // --- START: Repopulate form from transient on conflict ---
    $repop_data = null;
    if (isset($_GET['fmcwp_notice']) && in_array($_GET['fmcwp_notice'], ['create_conflict', 'update_conflict'])) {
        $conflict_data_transient_key = 'fmcwp_conflict_data_' . get_current_user_id();
        $repop_data = get_transient($conflict_data_transient_key);
        if ($repop_data) {
            delete_transient($conflict_data_transient_key); // Clean up immediately
        }
    }
    // --- END: Repopulate form from transient ---

    // Check Pro status once
    $is_pro = function_exists('cwp_is_pro_active') && cwp_is_pro_active();

    // Set default filter type to 'Snippet' if no type is selected
    $current_filter = isset($_GET['filter_type']) ? sanitize_text_field(wp_unslash($_GET['filter_type'])) : 'Snippet';

     //New Array for Snippet Types
     $snippet_types = [
        'Snippet' => 'Snippets',
        'Function' => 'Functions',
        'Script' => 'Scripts',
        'Style' => 'Styles',
    ];

    // Determine active tab - Snippet, Functions, Script or Style
    $active_tab = $current_filter;
    if ($current_filter === 'Template' || $current_filter === 'Sample') {
        $active_tab = 'Snippet';
    }

    // Check if the filter is for Snippet, Template, or Sample
    $is_snippet_category = in_array($current_filter, ['Snippet', 'Template', 'Sample']);

    // Set default code for new snippets based on type
    $default_code = "<?php\n\n\n\n";
    if ($current_filter === 'Script') {
        $default_code = "<script>\n\n\n\n</script>";
    }

    // Retrieve or default the active editor
    $transient_key = 'fmcwp_active_editor';
    $active_editor = ($current_filter === 'Style') ? 'css' : (get_transient($transient_key) ? get_transient($transient_key) : 'code');

    // --- START: Calculate Counts for Subsubsub Links ---
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
    $total_count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE type = %s", $current_filter);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
    $active_count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE type = %s AND status = %d", $current_filter, 1);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
    $inactive_count_sql = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE type = %s AND status = %d", $current_filter, 0);

    $total_count = (int) $wpdb->get_var($total_count_sql);
    $active_count = (int) $wpdb->get_var($active_count_sql);
    $inactive_count = (int) $wpdb->get_var($inactive_count_sql);
    // --- END: Calculate Counts ---

    // --- START: Determine Current Status Filter ---
    $current_status_filter = isset($_GET['status_filter']) ? sanitize_key($_GET['status_filter']) : 'all';
    // --- END: Determine Current Status Filter ---


    // --- START: Build Main Query SQL and Args BEFORE Prepare ---
    $main_query_sql = "SELECT * FROM {$table_name} WHERE type = %s"; // Base SQL with placeholder
    $main_query_args = [$current_filter]; // Start args array

    if ($current_status_filter === 'active') {
        $main_query_sql .= " AND status = %d"; // Append placeholder string
        $main_query_args[] = 1; // Add argument
    } elseif ($current_status_filter === 'inactive') {
        $main_query_sql .= " AND status = %d"; // Append placeholder string
        $main_query_args[] = 0; // Add argument
    }

    $main_query_sql .= " ORDER BY name ASC"; // Append ORDER BY

    // Fetch results using ONE prepare call with the fully constructed SQL and args
    // Use argument unpacking when passing an array of args
    $results = $wpdb->get_results($wpdb->prepare($main_query_sql, ...$main_query_args));


    // Check if we're in edit mode
    $is_edit_mode = (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id']));
    $edit_data = null;

    if ($is_edit_mode) {
        $edit_id = intval($_GET['id']);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $table_name is trusted
    $edit_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $edit_id));
    }

?>

<div class="content">

    <?php
        fmcwp_display_snippet_tabs($is_pro, $current_filter, $active_tab);
    ?>

    <h1 style="margin-bottom: 20px;"><?php echo ($active_tab === 'Snippet') ? esc_html($current_filter) . 's' : esc_html($snippet_types[$active_tab]); ?></h1> <?php // Reduced margin ?>

    <?php
        // --- START: Generate Subsubsub Links ---
        $base_url = admin_url('admin.php');
        $base_args = ['page' => 'fmcwp-snippets', 'filter_type' => $current_filter];

        $all_url = add_query_arg($base_args, $base_url); // 'all' is default, no status_filter needed
        $active_url = add_query_arg(array_merge($base_args, ['status_filter' => 'active']), $base_url);
        $inactive_url = add_query_arg(array_merge($base_args, ['status_filter' => 'inactive']), $base_url);

        // Only display if not editing/creating
        if (!($is_edit_mode || isset($_GET['action']) && $_GET['action'] === 'new')) :

    ?>

    <ul class="subsubsub" style="margin-top: 0; margin-bottom: 10px;">
        <li class="all">
            <a href="<?php echo esc_url($all_url); ?>" class="<?php echo ($current_status_filter === 'all') ? 'current' : ''; ?>" aria-current="<?php echo ($current_status_filter === 'all') ? 'page' : 'false'; ?>">
                <?php esc_html_e('All', 'cwp-snippets'); ?> <span class="count">(<?php echo esc_html($total_count); ?>)</span>
            </a> |
        </li>
        <li class="active">
            <a href="<?php echo esc_url($active_url); ?>" class="<?php echo ($current_status_filter === 'active') ? 'current' : ''; ?>" aria-current="<?php echo ($current_status_filter === 'active') ? 'page' : 'false'; ?>">
                <?php esc_html_e('Active', 'cwp-snippets'); ?> <span class="count">(<?php echo esc_html($active_count); ?>)</span>
            </a> |
        </li>
        <li class="inactive">
            <a href="<?php echo esc_url($inactive_url); ?>" class="<?php echo ($current_status_filter === 'inactive') ? 'current' : ''; ?>" aria-current="<?php echo ($current_status_filter === 'inactive') ? 'page' : 'false'; ?>">
                <?php esc_html_e('Inactive', 'cwp-snippets'); ?> <span class="count">(<?php echo esc_html($inactive_count); ?>)</span>
            </a>
        </li>
    </ul>

    <?php
        endif;
        echo '<div style="clear: both;"></div>';

        // Call the new function to display the action bar
        fmcwp_display_snippet_actions_bar($is_edit_mode, $current_filter, $is_snippet_category, $is_pro);

        // Check if the current filter type should display Location and Priority
        $show_location_priority_columns = in_array($current_filter, ['Function', 'Script', 'Style']);

        // --- START: Calculate column count for colspan ---
        $column_count = 6; // Base columns: Checkbox, ID, Name, Type, Status, Actions
        if ($show_location_priority_columns) {
            $column_count += 2; // Add Location, Priority
        } else {
            $column_count += 1; // Add Shortcode
        }
        $column_count += 1; // Add Modified Time
        // --- END: Calculate column count ---

    ?>

    <?php
        // --- START: Determine if submit buttons should be enabled on load ---
        // This is true after a conflict redirect, so the user can immediately resubmit.
        // On a normal load or successful save, they are disabled until an edit is made.
        $enable_form_on_load = false;
        if (isset($_GET['fmcwp_notice']) && in_array($_GET['fmcwp_notice'], ['create_conflict', 'update_conflict'])) {
            $enable_form_on_load = true;
        }
        // --- END: Determine if submit buttons should be enabled on load ---
    ?>
    <script type="text/javascript">
        // Pass the PHP flag to JavaScript. This will be used to prevent the form-watcher script
        // from disabling the buttons on a conflict redirect.
        var cwpEnableFormOnLoad = <?php echo json_encode($enable_form_on_load); ?>;
    </script>



    <!-- Table of Data -->
    <table class="widefat fixed" cellspacing="0" id="data-table" style="margin-bottom: 20px; display: <?php echo ($is_edit_mode || isset($_GET['action']) && $_GET['action'] === 'new') ? 'none' : 'table'; ?>; table-layout: fixed; width: 99%;">
        <thead>
            <tr>
                <th class="manage-column" style="width: 30px; text-align: center;"><input type="checkbox" id="select_all"></th>
                <th class="manage-column" style="width: 60px; text-align: center;">Status</th>
                <th class="manage-column" style="width: 50px; text-align: center;">Id</th>
                <th class="manage-column" style="width: <?php echo $show_location_priority_columns ? '25%' : '35%'; ?>;">Name</th>
                <th class="manage-column" style="width: 10%;">Type</th>
                <?php // --- Conditional Header: Location or Shortcode --- ?>
                <th class="manage-column" style="width: <?php echo $show_location_priority_columns ? '15%' : '20%'; ?>;">
                    <?php echo $show_location_priority_columns ? esc_html__('Location', 'cwp-snippets') : esc_html__('Shortcode', 'cwp-snippets'); ?>
                </th>
                <?php // --- START: Conditional Header: Priority --- ?>
                <?php if ($show_location_priority_columns) : ?>
                <th class="manage-column" style="width: 10%; text-align: center;">
                    <?php esc_html_e('Priority', 'cwp-snippets'); ?>
                </th>
                <?php endif; ?>
                <?php // --- END: Conditional Header: Priority --- ?>
                <?php // --- START: Added Modified Time Header --- ?>
                <th class="manage-column" style="width: <?php echo $show_location_priority_columns ? '15%' : '10%'; ?>;">
                    <?php esc_html_e('Modified', 'cwp-snippets'); ?>
                </th>
                <?php // --- END: Added Modified Time Header --- ?>
                <th class="manage-column" style="width: 100px; text-align: center;">No Cache</th>
                <th class="manage-column" style="width: 230px; text-align: right; white-space: nowrap;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php // --- Ensure $results is an array before looping --- ?>
            <?php if (is_array($results) && !empty($results)) : ?>
                <?php foreach ($results as $row) { ?>
                    <tr>
                        <td style="text-align: center;"><input type="checkbox" class="bulk-select" value="<?php echo esc_attr($row->id); ?>"></td>
                        <td style="text-align: center;">
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;">
                                <input type="hidden" name="action" value="fmcwp_toggle_status">
                                <input type="hidden" name="toggle_status_id" value="<?php echo esc_attr($row->id); ?>">
                                <?php wp_nonce_field( 'fmcwp_toggle_status_action_' . $row->id ); ?>
                                <button type="submit" class="status-toggle-button" style="background:none;border:none;padding:0;margin-right:10px;font-size:1.5em;vertical-align:middle;cursor:pointer;">
                                    <?php if ($row->status) { ?>
                                        <i class="fa fa-toggle-on" style="color: green;"></i>
                                    <?php } else { ?>
                                        <i class="fa fa-toggle-off" style="color: #bbb;"></i>
                                    <?php } ?>
                                </button>
                            </form>
                        </td>
                        <td style="text-align: center;"><?php echo esc_html($row->id); ?></td>
                        <td
                            <?php // Add title attribute for description tooltip
                                // start with title and version
                                $toolTipFull = ' title="';
                                if(!empty($row->version)) {
                                    $toolTipFull .= 'Version: ' . $row->version . ' ';
                                }
                                // add description if it exists
                                if (!empty($row->description)) {
                                    // attempt a new line if description exists
                                    $toolTipFull .= (!empty($row->version) ? "\n" : '');

                                    // Use wp_strip_all_tags instead of strip_tags for better coverage
                                    $toolTipFull .= wp_strip_all_tags( $row->description );
                                }
                                
                                $toolTipFull .= '"';
                                echo $toolTipFull;
                                

                            ?>>
                            <?php echo esc_html($row->name); ?>
                        </td>

                        <td><?php echo esc_html($row->type); ?></td>
                        <?php // --- Conditional Cell: Location or Shortcode --- ?>
                        <td>
                            <?php
                            if ($show_location_priority_columns) {
                                // Display location (handle potential NULL/empty for older snippets)
                                $location_display = !empty($row->location) ? ucfirst($row->location) : __('Frontend', 'cwp-snippets'); // Default to Frontend if empty/NULL
                                echo esc_html($location_display);
                            } else {
                                // Display shortcode
                                echo esc_html($row->shortcode);
                            }
                            ?>
                        </td>
                        <?php // --- START: Conditional Cell: Priority --- ?>
                        <?php if ($show_location_priority_columns) : ?>
                        <td style="text-align: center;">
                            <?php echo esc_html($row->priority ?? 10); // Default to 10 if priority is NULL ?>
                        </td>
                        <?php endif; ?>
                        <?php // --- END: Conditional Cell: Priority --- ?>

                        <?php // --- START: Modified Modified Time Cell with Tooltip --- ?>
                        <td title="<?php
                            // Always show the created date in the tooltip
                            $timestamp_create_tooltip = strtotime($row->time);
                            // Translators: "Modified: [date] (Created: [date])" or "Created: [date]" if no modified date
                            echo esc_attr(sprintf(__('Created: %s', 'cwp-snippets'),date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp_create_tooltip)
                            ));
                        ?>">
                            <?php
                            // Display the modified date as the main text, or created date if modified is not set
                            if (!empty($row->modified_time) && $row->modified_time !== '0000-00-00 00:00:00') {
                                $timestamp_modified = strtotime($row->modified_time);
                                echo esc_html(date_i18n(get_option('date_format'), $timestamp_modified));
                            } else {
                                // If modified time is not set, display the creation time as the main text
                                $timestamp_create_main = strtotime($row->time);
                                echo esc_html(date_i18n(get_option('date_format'), $timestamp_create_main));
                                // Removed the '(Created)' label as it's now in the tooltip
                            }
                            ?>
                        </td>
                        <?php // --- END: Modified Modified Time Cell with Tooltip --- ?>
                        <td style="text-align: center;">
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;">
                                <input type="hidden" name="action" value="fmcwp_toggle_cache_suppression">
                                <input type="hidden" name="toggle_cache_id" value="<?php echo esc_attr($row->id); ?>">
                                <?php wp_nonce_field( 'fmcwp_toggle_cache_action_' . $row->id ); ?>
                                <button type="submit" class="cache-toggle-button" title="Enable cache suppression." style="background:none;border:none;padding:0;margin-right:10px;font-size:1.5em;vertical-align:middle;cursor:pointer;">
                                    <?php // This assumes a 'suppress_cache' column will be added to the database. ?>
                                    <?php if (!empty($row->suppress_cache)) { ?>
                                        <i class="fa fa-toggle-on" style="color: green;"></i>
                                    <?php } else { ?>
                                        <i class="fa fa-toggle-off" style="color: #bbb;"></i>
                                    <?php } ?>
                                </button>
                            </form>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if ($is_pro) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to duplicate this snippet?');">
                                    <input type="hidden" name="action" value="fmcwp_duplicate_snippet">
                                    <input type="hidden" name="duplicate_id" value="<?php echo esc_attr($row->id); ?>">
                                    <input type="hidden" name="filter_type" value="<?php echo esc_attr($current_filter); ?>">
                                    <?php wp_nonce_field( 'fmcwp_duplicate_snippet_action_' . $row->id ); ?>
                                    <button type="submit" class="button cwp-action-icon-button" title="<?php esc_attr_e('Duplicate Snippet', 'cwp-snippets'); ?>" style="font-size: 13px; padding: 0 5px; height: 28px; line-height: 26px;">
                                        <i class="far fa-copy"></i>
                                    </button>
                                </form>
                            <?php else : ?>
                                <button type="button" class="button cwp-action-icon-button cwp-pro-feature-button cwp-disabled-button" title="<?php esc_attr_e('Duplicate Snippet (Pro)', 'cwp-snippets'); ?>" style="font-size: 13px; padding: 0 5px; height: 28px; line-height: 26px;">
                                    <i class="far fa-copy"></i>
                                </button>
                            <?php endif; ?>
                            <a href="?page=<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ?? 'fmcwp-snippets' ) ) ); ?>&action=edit&id=<?php echo esc_attr($row->id); ?>&filter_type=<?php echo esc_attr($current_filter); ?>" class="button" style="font-size: 13px;">Edit</a>
                            <?php // --- START: Updated Preview Button Condition --- ?>
                            <?php if ($row->type !== 'Style') : // Hide only for Style type ?>
                                <?php fm_cwp_add_preview_button($row->id); ?>
                            <?php endif; ?>
                            <?php // --- END: Updated Preview Button Condition --- ?>
                            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline;" onsubmit="return confirmSnippetDelete();">
                                    <input type="hidden" name="action" value="fmcwp_delete_snippet">
                                    <input type="hidden" name="delete_id" value="<?php echo esc_attr($row->id); ?>">
                                    <?php wp_nonce_field( 'fmcwp_delete_snippet_action_' . $row->id ); ?>
                                    <button type="submit" class="button" style="font-size: 13px;">Delete</button>
                                </form>
                        </td>
                    </tr>
                <?php } // End foreach ?>
            <?php else : // Handle case where $results is empty or not an array ?>
                <tr>
                    <td colspan="<?php echo esc_attr($column_count); // Use calculated colspan ?>" style="text-align: center;">
                        <?php esc_html_e('No snippets found.', 'cwp-snippets'); ?>
                    </td>
                </tr>
            <?php endif; // End if is_array($results) && !empty($results) ?>
        </tbody>
    </table>



    <!-- New/Edit Form -->

    <div id="form-container" style="display: <?php echo ($is_edit_mode || isset($_GET['action']) && $_GET['action'] === 'new') ? 'block' : 'none'; ?>;">
        <?php if ($is_pro) { cwp_toolbar(); } ?>
        <form method="post" id="data-form">
            <?php wp_nonce_field('create_edit_snippet'); ?>
            <?php wp_nonce_field('check_snippet_uniqueness_action', 'check_snippet_uniqueness_nonce'); ?>

            <?php // --- START: Modified this DIV for layout --- ?>
            <div style="margin-bottom: 15px; display: flex; align-items: center; flex-wrap: wrap; gap: 10px;"> <?php // Added flex-wrap and gap ?>
                <input type="hidden" name="active_editor" id="active_editor" value="<?php echo esc_attr($active_editor); ?>">
                <input type="hidden" id="snippet_id" name="snippet_id" value="<?php echo isset($edit_data->id) ? esc_attr($edit_data->id) : ''; ?>">
                <button type="button" id="back-button" class="button" style="margin-right: 5px;">Back</button> 
                <input type="text" id="name_field" name="<?php echo $is_edit_mode ? 'update_name' : 'new_name'; ?>" value="<?php echo $repop_data ? esc_attr($repop_data['name']) : ($is_edit_mode ? esc_attr($edit_data->name) : ''); ?>" placeholder="<?php echo esc_attr($current_filter); ?> Name" style="width: 250px;" required>

                <?php if ($is_snippet_category) { ?>
                <select id="type_selector" name="<?php echo $is_edit_mode ? 'update_type' : 'new_type'; ?>" style="width: 100px;">
                    <option value="<?php echo esc_attr($current_filter); ?>"><?php echo esc_html($current_filter); ?></option>
                    <option value="Snippet">Snippet</option>
                    <option value="Template">Template</option>
                    <option value="Sample">Sample</option>
                </select>
                <?php }else{ ?>
                    <input type="hidden" name="<?php echo $is_edit_mode ? 'update_type' : 'new_type'; ?>" id="type_hidden" value="<?php echo esc_attr($current_filter); ?>"> <?php // Changed id ?>

                    <?php // --- Location and Priority fields --- ?>
                    <?php if (in_array($current_filter, ['Function', 'Script', 'Style'])) : ?>
                        <label for="location_selector" style="margin-left: 10px; margin-right: 5px;">Location:</label>
                        <select id="location_selector" name="<?php echo $is_edit_mode ? 'update_location' : 'new_location'; ?>" style="width: 120px;">
                            <?php
                            $current_location = $repop_data ? $repop_data['location'] : ($is_edit_mode && isset($edit_data->location) ? $edit_data->location : 'frontend');
                            $locations = [
                                'frontend' => 'Frontend Only',
                                'admin' => 'Admin Only',
                                'everywhere' => 'Everywhere',
                            ];
                            foreach ($locations as $value => $label) {
                                echo '<option value="' . esc_attr($value) . '"' . selected($current_location, $value, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>

                        <label for="priority_field" style="margin-left: 10px; margin-right: 5px;">Priority:</label>
                        <input type="number" id="priority_field" name="<?php echo $is_edit_mode ? 'update_priority' : 'new_priority'; ?>" value="<?php echo $repop_data ? esc_attr($repop_data['priority']) : ($is_edit_mode && isset($edit_data->priority) ? esc_attr($edit_data->priority) : '10'); ?>" style="width: 60px;" min="1">
                    <?php endif; ?>

                <?php } ?>

                <?php if ($is_snippet_category) { ?>
                    <?php if ($is_edit_mode) { ?>
                        <input type="text" name="update_shortcode" value="<?php echo esc_attr($edit_data->shortcode); ?>" readonly style="width: 250px;">
                    <?php } ?>
                <?php }else{ ?>
                    <?php if ($is_edit_mode) { ?>
                    <input type="hidden" name="update_shortcode" value="<?php echo esc_attr($edit_data->shortcode); ?>" readonly style="width: 250px;">
                    <?php } ?>
                <?php } ?>

                <?php if ($is_snippet_category) { ?>
                <!-- Code / CSS Buttons -->
                <div style="margin: 0 10px;"> <?php // Reduced margin ?>
                    <button type="button" id="show-code-editor" class="button button-primary"> Code </button>
                    <button type="button" id="show-css-editor" class="button"> CSS </button>
                </div>
                <?php } ?>

                <?php // --- Submit and Preview Button Group --- ?>
                <div class="cwp-form-actions-group" style="display: flex; align-items: center; gap: 5px;">                    
                    <input type="submit" class="button button-primary" value="<?php echo $is_edit_mode ? 'Update ' . esc_attr($current_filter) : 'Create ' . esc_attr($current_filter) ?>" <?php echo $enable_form_on_load ? '' : 'disabled'; ?>>

                    <?php // Only show preview button in edit mode and if type is not 'Style' ?>
                    <?php if ( $is_edit_mode && isset($edit_data) && $edit_data->type !== 'Style' ) { ?>
                        <?php fm_cwp_add_preview_button(intval($edit_data->id)); ?>
                    <?php } ?>
                </div>
                <?php // --- End Submit and Preview Button Group --- ?>


                <?php // --- START: Added Status Toggle HTML (Moved to end, pushed right) --- ?>
                <div class="cwp-form-status-toggle" style="display: flex; align-items: center; padding-left: 20px;"> <?php // Push to the right ?>
                    <?php
                        // Determine initial status: 1 (active) for new, or existing status for edit
                        $initial_status = $repop_data ? intval($repop_data['status']) : ($is_edit_mode ? (isset($edit_data->status) ? intval($edit_data->status) : 1) : 1);
                        $icon_class = $initial_status ? 'fa-toggle-on' : 'fa-toggle-off';
                        $icon_color = $initial_status ? 'green' : '#bbb';
                        $label_text = $initial_status ? __('Active', 'cwp-snippets') : __('Inactive', 'cwp-snippets');
                    ?>
                    <?php // Hidden input to store the actual value (0 or 1) ?>
                    <input type="hidden" name="<?php echo $is_edit_mode ? 'update_status' : 'new_status'; ?>" id="snippet_status_hidden" value="<?php echo esc_attr($initial_status); ?>">
                    <?php // Button for visual toggle interaction ?>
                    <button type="button" id="status-toggle-button-form" class="status-toggle-button" style="background:none; border:none; padding:0; margin-right: 5px; font-size:1.5em; vertical-align:middle; cursor:pointer;">
                        <i class="fa <?php echo esc_attr($icon_class); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></i>
                    </button>
                    <?php // Text label showing current state ?>
                    <span id="status-toggle-label" style="<?php echo !$initial_status ? 'color: #bbb; font-style: italic;' : ''; ?>"><?php echo esc_html($label_text); ?></span>
                </div>
                <?php // --- END: Added Status Toggle HTML --- ?>
                <?php // --- START: Add Version --- ?>
                <label for="version_field" style="margin-left: auto; font-weight: bold;">Version:</label>
                <input
                    type="text"
                    id="version_field"
                    name="<?php echo $is_edit_mode ? 'update_version' : 'new_version'; ?>"
                    value="<?php echo $repop_data ? esc_attr($repop_data['version'] ?? '') : ($is_edit_mode && isset($edit_data->version) ? esc_attr($edit_data->version) : ''); ?>"
                    style="width: 70px; text-align: right; margin-right: 34px;"
                    placeholder="1.0"
                />
                <?php // --- END: Add Version --- ?>

            </div>
            <?php // --- END: Modified this DIV for layout --- ?>

            <div id="error_message" style="margin: -5px 0px 10px 10px; display: none; color: red;"></div>

            <?php
                // Determine initial visibility styles based on $active_editor
                $code_editor_visibility_style = ($active_editor === 'code' || $active_editor === '') ? 'visibility: visible; position: relative;' : 'visibility: hidden; position: absolute;';
                $css_editor_visibility_style = ($active_editor === 'css') ? 'visibility: visible; position: relative;' : 'visibility: hidden; position: absolute;';
            ?>

            <?php
                $invalid_code = get_transient('new_snippet_code');
                if(false !== $invalid_code) {
                    delete_transient('new_snippet_code');
                    $repop_data['code'] = $invalid_code;
                    
                        if(!isset($repop_data['description'])) {
                            $repop_data['description'] = '';
                        }
                }
            ?>
            
            <!-- Code / CSS Text Areas -->
            <div id="code-editor-container" style="<?php echo esc_attr($code_editor_visibility_style); ?> width: 98%; margin-bottom: 15px;">
                <textarea name="<?php echo $is_edit_mode ? 'update_code' : 'new_code'; ?>" id="code_editor_textarea" rows="10" style="width: 100%;"><?php echo $repop_data ? esc_textarea($repop_data['code']) : ($is_edit_mode ? esc_textarea($edit_data->code) : esc_textarea($default_code)); ?></textarea>
            </div>

            <div id="css-editor-container" style="<?php echo esc_attr($css_editor_visibility_style); ?> width: 98%; margin-bottom: 15px;">
                <textarea name="<?php echo $is_edit_mode ? 'update_css' : 'new_css'; ?>" id="css_editor_textarea" rows="10" style="width: 100%;"><?php echo $repop_data ? esc_textarea($repop_data['css']) : ($is_edit_mode ? esc_textarea($edit_data->css) : "/* CSS */\n\n\n\n"); ?></textarea>
            </div>



            <?php if ($is_edit_mode) { ?>
                <input type="hidden" name="update_id" value="<?php echo intval($edit_data->id); ?>">
            <?php } ?>
            <button type="button" id="back-button-2" class="button" style="margin-left: 5px; margin-right: 15px;">Back</button>
            <input type="submit" class="button button-primary" style="margin-right: 5px;" value="<?php echo $is_edit_mode ? 'Update Snippet' : 'Create Snippet' ?>" <?php echo $enable_form_on_load ? '' : 'disabled'; ?>>

            <?php // Only show preview button in edit mode and if type is not 'Style' ?>
            <?php if ( $is_edit_mode && isset($edit_data) && $edit_data->type !== 'Style' ) { ?>
                <?php fm_cwp_add_preview_button(intval($edit_data->id)); ?>
            <?php } ?>

            <?php // --- START: Added Status Toggle HTML (Bottom) --- ?>
            <div class="cwp-form-status-toggle-bottom" style="display: inline-flex; align-items: center; vertical-align: middle; margin-left: 15px;"> <?php // Adjusted style for inline display ?>
                <?php
                    // Reuse the same PHP variables calculated earlier for initial state
                    // $initial_status, $icon_class, $icon_color, $label_text
                ?>
                <?php // Hidden input to store the actual value (0 or 1) - NOTE: ID is different! ?>
                <input type="hidden" name="<?php echo $is_edit_mode ? 'update_status_confirm' : 'new_status_confirm'; ?>" id="snippet_status_hidden-2" value="<?php echo esc_attr($initial_status); ?>"> <?php // Use a different name/id if needed, but value should match ?>
                <?php // Button for visual toggle interaction - NOTE: ID is different! ?>
                <button type="button" id="status-toggle-button-form-2" class="status-toggle-button" style="background:none; border:none; padding:0; margin-right: 5px; font-size:1.5em; vertical-align:middle; cursor:pointer;">
                    <i class="fa <?php echo esc_attr($icon_class); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></i>
                </button>
                <?php // Text label showing current state - NOTE: ID is different! ?>
                <span id="status-toggle-label-2" style="<?php echo !$initial_status ? 'color: #bbb; font-style: italic;' : ''; ?>"><?php echo esc_html($label_text); ?></span>
            </div>
            <?php // --- END: Added Status Toggle HTML (Bottom) --- ?>
        

            <div id="preview_message" style="display: none; color: red;"></div>

            <div id="description-container" style="width: 98%; margin: 25px 0;">
                <label for="description_textarea" style="display: block; margin-bottom: 5px; font-weight: bold;"><?php esc_html_e('Description', 'cwp-snippets'); ?></label>
                <textarea name="<?php echo $is_edit_mode ? 'update_description' : 'new_description'; ?>" id="description_textarea" rows="4" style="width: 100%;"><?php echo $repop_data ? esc_textarea($repop_data['description']) : ($is_edit_mode && isset($edit_data->description) ? esc_textarea($edit_data->description) : ''); ?></textarea>
                <p class="description"><?php esc_html_e('Optional: Add notes or details about this snippet.', 'cwp-snippets'); ?></p>
            </div>

        </form>
    </div>
</div>


<?php
fmcwp_footer();
delete_transient($transient_key);
?>


<?php
}




// *********************************************************************************************************************************
// Display Snippet Type Tabs
function fmcwp_display_snippet_tabs($is_pro, $current_filter, $active_tab) {
    ?>
    <!-- New Tabs -->
    <div class="cwp-snippet-tabs">
        <?php
        // $is_pro is passed as an argument

        // Array defining snippet types and their labels
        $snippet_types_tabs = [ // Renamed to avoid conflict with $snippet_types used elsewhere
            'Snippet'  => 'Snippets',
            'Function' => 'Functions',
            'Script' => 'Scripts',
            'Style'    => 'Styles',
        ];

        // $current_filter and $active_tab are passed as arguments

        foreach ($snippet_types_tabs as $type => $label) :
            $is_pro_feature = in_array($type, ['Function', 'Script', 'Style']); // Define which types are Pro
            $tab_class = 'cwp-snippet-tab';
            $pro_indicator = '';
            // Ensure $_GET['page'] is sanitized before use
            $page_slug = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : 'fmcwp-snippets';
            $href = '?page=' . $page_slug . '&filter_type=' . $type; // Default link

            if ($active_tab === $type) {
                $tab_class .= ' cwp-snippet-tab-active';
            }

            // If it's a Pro feature and Pro is NOT active
            $indicator_html = '';
            if ($is_pro_feature && !$is_pro) {
                $tab_class .= ' cwp-pro-feature-tab cwp-disabled-tab'; // Add classes for styling and disabling
                $indicator_html = ' <span class="cwp-pro-indicator">' . esc_html__( 'Pro', 'cwp-snippets' ) . '</span>'; // Add the Pro indicator text safely
                $href = '#'; // Change href to '#' to prevent navigation
            }
        ?>
            <a href="<?php echo esc_url($href); ?>" class="<?php echo esc_attr($tab_class); ?>" style="font-size: 14px;">
                <?php echo wp_kses_post( esc_html( $label ) . $indicator_html ); // Output label and safe Pro indicator ?>
            </a>
        <?php endforeach; ?>
    </div>
    <!-- End New Tabs -->
    <?php
}





// *********************************************************************************************************************************
// Display Snippet Actions Bar (Bulk Actions, Filters, New/Import/Generate Buttons)
function fmcwp_display_snippet_actions_bar($is_edit_mode, $current_filter, $is_snippet_category, $is_pro) {
    ?>
    <!-- Container for filter and actions -->
    <div style="margin-bottom: 20px; display: <?php echo ($is_edit_mode || isset($_GET['action']) && $_GET['action'] === 'new') ? 'none' : 'flex'; ?>; align-items: center;">
        <!-- Bulk Actions -->
        <form method="post" id="bulk-action-form" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display: flex; align-items: center; margin-right: 50px;">
            <input type="hidden" name="action" value="fmcwp_handle_bulk_form">
            <?php wp_nonce_field( 'fmcwp_bulk_action_nonce', '_wpnonce_bulk_action' ); // Add nonce field ?>

            <select name="bulk_action" id="bulk-action-selector" style="font-size: 14px;">
                <option value=""><?php esc_html_e( 'Bulk Actions', 'cwp-snippets' ); ?></option>
                <option value="delete"><?php esc_html_e( 'Delete', 'cwp-snippets' ); ?></option>
                <option value="activate"><?php esc_html_e( 'Activate', 'cwp-snippets' ); ?></option>
                <option value="deactivate"><?php esc_html_e( 'Deactivate', 'cwp-snippets' ); ?></option>
                <?php
                // Export option for Pro users
                // $is_pro is passed as an argument now
                if ( $is_pro ) {
                    echo '<option value="export">' . esc_html__( 'Export', 'cwp-snippets' ) . '</option>';
                } else {
                    echo '<option value="export" disabled>' . esc_html__( 'Export', 'cwp-snippets' ) . ' ' . esc_html__( '(Pro)', 'cwp-snippets' ) . '</option>';
                }
                ?>
            </select>

            <input type="submit" value="Apply" class="button" style="font-size: 14px; margin-left: 10px;">
            <input type="hidden" id="bulk_action_ids" name="bulk_action_ids" value="">
            <?php // Pass the current page/filter back for potential redirects on failure ?>
                <?php // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- this is the current URL for referral and will be escaped
                ?>
                <input type="hidden" name="_wp_http_referer" value="<?php echo esc_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); ?>">
        </form>


        <!-- Filter Buttons and New Button -->
        <div style="display: flex; align-items: center;">

            <!-- Filter Buttons -->
            <?php if ($is_snippet_category) : ?>
                    <div style="margin-right: 50px;">
                                    <a href="?page=<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ?? 'fmcwp-snippets' ) ) ); ?>&filter_type=Snippet" class="button <?php echo ($current_filter === 'Snippet') ? 'button-primary' : ''; ?>" style="font-size: 14px;">Snippets</a>
                                    <a href="?page=<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ?? 'fmcwp-snippets' ) ) ); ?>&filter_type=Template" class="button <?php echo ($current_filter === 'Template') ? 'button-primary' : ''; ?>" style="font-size: 14px;">Templates</a>
                                    <a href="?page=<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['page'] ?? 'fmcwp-snippets' ) ) ); ?>&filter_type=Sample" class="button <?php echo ($current_filter === 'Sample') ? 'button-primary' : ''; ?>" style="font-size: 14px;">Samples</a>
                    </div>
            <?php endif; ?>

            <!-- New Button -->
                <button id="new-button" class="button" style="font-size: 14px;">
                <?php /* translators: %s: current snippet filter (e.g. "Snippets", "Templates") */
                printf( esc_html__( 'New %s', 'cwp-snippets' ), esc_html( $current_filter ) ); ?>
            </button>

        </div>

        <!-- Templates / Samples Generate -->
        <div style="display: flex; align-items: center;">

        <!-- Generate Templates / Samples -->
        <div style="display: flex; align-items: center; margin-left: 5px; display: <?php echo (in_array($current_filter, ['Template', 'Sample'])) ? 'flex' : 'none'; ?>;">
            <button type="button" class="button cwp-reload-bundled-btn" data-type="<?php echo esc_attr(strtolower($current_filter)) . 's'; ?>" style="font-size: 14px; margin-left: 10px;">
                Reload <?php echo esc_html($current_filter); ?>s
            </button>
        </div>

        <!-- Import Button -->
        <?php fmcwp_display_import_button($is_pro); ?>


        </div>



    </div>
    <?php
}

/**
 * Displays the import button, handling Pro version status.
 *
 * @param bool $is_pro Whether the Pro version is active.
 */
function fmcwp_display_import_button($is_pro) {
    $import_label = esc_html__( 'Import', 'cwp-snippets' );
    $import_class = 'button button'; // Default classes
    $import_style = 'display: inline-block; cursor: pointer; margin-left: 10px; margin-right: 10px; font-size: 14px; padding: 6px 12px 8px; line-height: normal;'; // Default style
    $disable_import_js = false;

    if ( ! $is_pro ) {
        $import_label .= ' <span class="cwp-pro-indicator">' . esc_html__( 'Pro', 'cwp-snippets' ) . '</span>';
        $import_class .= ' cwp-pro-feature-button cwp-disabled-button';
        $import_style = 'display: inline-block; margin-left: 10px; margin-right: 10px; font-size: 14px; padding: 6px 12px 8px; line-height: normal;';
        $disable_import_js = true;
    }

    // The label for the file input is what the user sees as the button.
    // The actual file input is hidden.
    ?>
    <label for="snippets_import_file" class="<?php echo esc_attr($import_class); ?>" style="<?php echo esc_attr($import_style); ?>">
        <?php echo wp_kses_post( $import_label ); ?>
    </label>
    <?php
    // The form itself will be in the footer, but the file input needs to be triggered.
    // The JS will handle the file input which is now part of the form in the footer.
}





// *********************************************************************************************************************************
// Code Mirror

add_action('admin_enqueue_scripts', 'codemirror_enqueue_scripts');
 
function codemirror_enqueue_scripts($hook) {
  $cm_settings['codeEditor'] = wp_enqueue_code_editor(array('type' => 'text/x-php'));
  wp_localize_script('jquery', 'cm_settings', $cm_settings);

  wp_enqueue_script('wp-theme-plugin-editor');
  wp_enqueue_style('wp-codemirror');
  
}



// *********************************************************************************************************************************
// Preview Button

function fm_cwp_add_preview_button($data_id) {
    $preview_page_id = get_option('fm_cwp_preview_page_id');
    if ($preview_page_id) {
        $preview_url = add_query_arg('fm_cwp_data_id', $data_id, get_permalink($preview_page_id));
        echo '<button type="button" onclick="window.open(\'' . esc_url($preview_url) . '\', \'_blank\');" id="preview-button" class="button">Preview</button>';
    }
}


// *********************************************************************************************************************************
// Display Runtime Notices (from failed functions, etc.)
function fmcwp_display_runtime_notices() {
    if ( ! isset( $GLOBALS['cwp_runtime_notices'] ) || empty( $GLOBALS['cwp_runtime_notices'] ) || ! is_array( $GLOBALS['cwp_runtime_notices'] ) ) {
        return;
    }

    foreach ( $GLOBALS['cwp_runtime_notices'] as $notice ) {
        if ( isset( $notice['message'], $notice['type'] ) ) {
            echo '<div class="notice notice-' . esc_attr( $notice['type'] ) . ' is-dismissible"><p>' . wp_kses_post( $notice['message'] ) . '</p></div>';
        }
    }

    // Clear the global to prevent notices from showing on subsequent page loads within the same request.
    $GLOBALS['cwp_runtime_notices'] = [];
}



// *********************************************************************************************************************************
// Display Admin Notices based on URL parameter
function fmcwp_display_admin_notices() {
    // Handle the generic "Settings saved." notice from the WordPress Settings API
    if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] && ! isset( $_GET['fmcwp_notice'] ) ) {
        $message = __( 'Settings saved.', 'cwp-snippets' );
        $type = 'success';
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    if ( isset( $_GET['fmcwp_notice'] ) ) {
        $notice_code = sanitize_key( $_GET['fmcwp_notice'] );
        $message = '';
        $type = 'info'; // Default type

        switch ( $notice_code ) {
            case 'import_success':
                // Sanitize the input arrays from the URL
                $added_by_type = isset($_GET['added']) && is_array($_GET['added'])
                    ? array_map('intval', $_GET['added'])
                    : [];
                $updated_by_type = isset($_GET['updated']) && is_array($_GET['updated'])
                    ? array_map('intval', $_GET['updated'])
                    : [];

                $message_parts = [];
                if (!empty($added_by_type)) {
                    $parts = [];
                    foreach ($added_by_type as $type_key => $count) {
                        // Map known type keys to literal singular/plural strings for _n() to accept literals
                        switch ( $type_key ) {
                            case 'samples':
                                $label = _n( 'Sample', 'Samples', $count, 'cwp-snippets' );
                                break;
                            case 'templates':
                                $label = _n( 'Template', 'Templates', $count, 'cwp-snippets' );
                                break;
                            case 'functions':
                                $label = _n( 'Function', 'Functions', $count, 'cwp-snippets' );
                                break;
                            case 'scripts':
                                $label = _n( 'Script', 'Scripts', $count, 'cwp-snippets' );
                                break;
                            case 'styles':
                                $label = _n( 'Style', 'Styles', $count, 'cwp-snippets' );
                                break;
                            default:
                                // Fallback to a generic label
                                $label = _n( 'item', 'items', $count, 'cwp-snippets' );
                                break;
                        }
                        $parts[] = sprintf( '%d %s', $count, esc_html( $label ) );
                    }
                    /* translators: label for added items in import summary */
                    $message_parts[] = '<strong>' . __( 'Added:', 'cwp-snippets' ) . '</strong> ' . implode( ', ', $parts );
                }

                if (!empty($updated_by_type)) {
                    $parts = [];
                    foreach ($updated_by_type as $type_key => $count) {
                        switch ( $type_key ) {
                            case 'samples':
                                $label = _n( 'Sample', 'Samples', $count, 'cwp-snippets' );
                                break;
                            case 'templates':
                                $label = _n( 'Template', 'Templates', $count, 'cwp-snippets' );
                                break;
                            case 'functions':
                                $label = _n( 'Function', 'Functions', $count, 'cwp-snippets' );
                                break;
                            case 'scripts':
                                $label = _n( 'Script', 'Scripts', $count, 'cwp-snippets' );
                                break;
                            case 'styles':
                                $label = _n( 'Style', 'Styles', $count, 'cwp-snippets' );
                                break;
                            default:
                                $label = _n( 'item', 'items', $count, 'cwp-snippets' );
                                break;
                        }
                        $parts[] = sprintf( '%d %s', $count, esc_html( $label ) );
                    }
                    /* translators: label for updated items in import summary */
                    $message_parts[] = '<strong>' . __( 'Updated:', 'cwp-snippets' ) . '</strong> ' . implode( ', ', $parts );
                }

                if (!empty($message_parts)) {
                    $message = __( 'Import successful.', 'cwp-snippets' ) . '<br>' . implode( '<br>', $message_parts );
                    $type = 'success';
                } else {
                    $message = __( 'No snippets were imported or updated. This may happen if you deselected all items before confirming the import.', 'cwp-snippets' );
                    $type = 'info';
                }
                break;

            case 'import_activation_multiple_errors':
                $imported_count = isset($_GET['imported_count']) ? intval($_GET['imported_count']) : 0;
                $activated_count = isset($_GET['activated_count']) ? intval($_GET['activated_count']) : 0;
                $failures = get_transient('fmcwp_import_activation_errors');

                if ($failures && is_array($failures)) {
                    /* translators: 1: number of imported snippets, 2: number of activated function snippets */
                    $message = sprintf( __( '<strong>Import Complete with Warnings:</strong> %1$d snippets were imported. %2$d function snippets were successfully activated.', 'cwp-snippets' ), $imported_count, $activated_count );
                    $message .= '<br>' . __( 'The following snippets could not be activated and require manual review:', 'cwp-snippets' );
                    $message .= '<ul style="list-style: disc; margin-left: 20px;">';
                    foreach ($failures as $failure_message) {
                        $message .= '<li>' . wp_kses_post($failure_message) . '</li>';
                    }
                    $message .= '</ul>';
                    delete_transient('fmcwp_import_activation_errors');
                } else {
                    $message = __( 'Import complete, but some snippets may require manual activation.', 'cwp-snippets' );
                }
                $type = 'warning';
                break;

            case 'import_activation_syntax_error':
                $snippet_name = isset($_GET['snippet_name']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['snippet_name'] ) ) ) ) : 'a snippet';
                $error_message = isset($_GET['error_message']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['error_message'] ) ) ) ) : 'unknown error';
                /* translators: 1: snippet name, 2: error details (HTML-safe) */
                $message = sprintf( __( '<strong>Import Warning:</strong> Snippets were imported, but the function "<strong>%1$s</strong>" could not be auto-activated due to a syntax error. Please fix the error and activate it manually. <br><small>Error details: %2$s</small>', 'cwp-snippets' ), $snippet_name, '<code>' . $error_message . '</code>' );
                $type = 'warning';
                break;

            case 'import_activation_conflict':
                $snippet_name = isset($_GET['snippet_name']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['snippet_name'] ) ) ) ) : 'a snippet';
                $conflict_name = isset($_GET['conflict_name']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['conflict_name'] ) ) ) ) : 'unknown';
                $conflict_type = isset($_GET['conflict_type']) ? esc_html( sanitize_text_field( wp_unslash( $_GET['conflict_type'] ) ) ) : 'item';
                /* translators: 1: snippet name, 2: conflict type (HTML-wrapped), 3: conflict name (HTML-wrapped) */
                $message = sprintf( __( '<strong>Import Warning:</strong> Snippets were imported, but the function "<strong>%1$s</strong>" could not be auto-activated due to a conflict. A %2$s named %3$s already exists. Please resolve the conflict and activate it manually.', 'cwp-snippets' ), $snippet_name, '<strong>' . ucfirst($conflict_type) . '</strong>', '<code>' . $conflict_name . '</code>' );
                $type = 'warning';
                break;

            case 'import_invalid_format':
                $message = __( 'Invalid file format. Please upload a valid JSON file.', 'cwp-snippets' );
                $type = 'error';
                break;

            case 'import_upload_error':
                $message = __( 'There was an error uploading the file. Please try again.', 'cwp-snippets' );
                $type = 'error';
                break;

            case 'create_success':
                $message = __( 'New snippet created successfully.', 'cwp-snippets' );
                $type = 'success';
                break;

            case 'update_success':
                $message = __( 'Snippet updated successfully.', 'cwp-snippets' );
                $type = 'success';
                break;

            case 'create_success_with_conflict_warning':
            case 'update_success_with_conflict_warning':
                $conflict_name = isset($_GET['conflict_name']) ? esc_html(urldecode($_GET['conflict_name'])) : 'unknown';
                $conflict_type = isset($_GET['conflict_type']) ? esc_html($_GET['conflict_type']) : 'item';
                $base_message = ($notice_code === 'create_success_with_conflict_warning')
                    ? __( 'Snippet created successfully but is inactive.', 'cwp-snippets' )
                    : __( 'Snippet updated successfully.', 'cwp-snippets' );

                /* translators: 1: Base success message, 2: conflict type (e.g. "Function"), 3: conflict name */
                $message = sprintf(
                    __( '%1$s <strong>Warning:</strong> A %2$s conflict was detected for <code>%3$s</code>. The snippet cannot be activated until this conflict is resolved.', 'cwp-snippets' ),
                    $base_message,
                    '<strong>' . ucfirst($conflict_type) . '</strong>',
                    $conflict_name
                );
                $type = 'warning';
                break;

            case 'create_conflict':
                $conflict_name = isset($_GET['conflict_name']) ? esc_html(urldecode($_GET['conflict_name'])) : 'unknown';
                $conflict_type = isset($_GET['conflict_type']) ? esc_html($_GET['conflict_type']) : 'item';
                /* translators: 1: conflict type (HTML-wrapped), 2: conflict name (HTML-wrapped) */
                $message = sprintf( __( 'Error: %1$s name conflict detected: %2$s already exists. Snippet not saved.', 'cwp-snippets' ), '<strong>' . ucfirst($conflict_type) . '</strong>', '<code>' . $conflict_name . '</code>' );
                $type = 'error';
                break;

            case 'update_conflict':
                $conflict_name = isset($_GET['conflict_name']) ? esc_html(urldecode($_GET['conflict_name'])) : 'unknown';
                $conflict_type = isset($_GET['conflict_type']) ? esc_html($_GET['conflict_type']) : 'item';
                /* translators: 1: conflict type (HTML-wrapped), 2: conflict name (HTML-wrapped) */
                $message = sprintf( __( 'Error: %1$s name conflict detected: %2$s already exists. Snippet not updated.', 'cwp-snippets' ), '<strong>' . ucfirst($conflict_type) . '</strong>', '<code>' . $conflict_name . '</code>' );
                $type = 'error';
                break;

            case 'activation_conflict':
                $conflict_name = isset($_GET['conflict_name']) ? esc_html(urldecode($_GET['conflict_name'])) : 'unknown';
                $conflict_type = isset($_GET['conflict_type']) ? esc_html($_GET['conflict_type']) : 'item';
                /* translators: 1: conflict type (HTML-wrapped), 2: conflict name (HTML-wrapped) */
                $message = sprintf( __( 'Error: Snippet could not be activated. A %1$s name conflict was detected: %2$s already exists.', 'cwp-snippets' ), '<strong>' . ucfirst($conflict_type) . '</strong>', '<code>' . $conflict_name . '</code>' );
                $type = 'error';
                break;

            case 'bulk_activation_conflict':
                $conflict_name = isset($_GET['conflict_name']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['conflict_name'] ) ) ) ) : 'unknown';
                $conflict_type = isset($_GET['conflict_type']) ? esc_html( sanitize_text_field( wp_unslash( $_GET['conflict_type'] ) ) ) : 'item';
                $snippet_name = isset($_GET['snippet_name']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['snippet_name'] ) ) ) ) : 'a snippet';
                /* translators: 1: snippet name, 2: conflict type (HTML-wrapped), 3: conflict_type label, 4: conflict name (HTML-wrapped) */
                $message = sprintf( __( '<strong>Error:</strong> Bulk activation failed. The snippet "<strong>%1$s</strong>" has a %2$s name conflict with an existing %3$s: %4$s. No snippets were activated.', 'cwp-snippets' ), $snippet_name, '<strong>' . ucfirst($conflict_type) . '</strong>', esc_html($conflict_type), '<code>' . $conflict_name . '</code>' );
                $type = 'error';
                break;

            case 'bulk_activation_syntax_error':
                $error_message = isset($_GET['error_message']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['error_message'] ) ) ) ) : 'unknown error';
                $snippet_name = isset($_GET['snippet_name']) ? esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['snippet_name'] ) ) ) ) : 'a snippet';
                /* translators: 1: snippet name, 2: error details (HTML-wrapped) */
                $message = sprintf( __( '<strong>Error:</strong> Bulk activation failed. The snippet "<strong>%1$s</strong>" has a syntax error. No snippets were activated. <br><small>Error details: %2$s</small>', 'cwp-snippets' ), $snippet_name, '<code>' . $error_message . '</code>' );
                $type = 'error';
                break;

            case 'update_syntax_error_warning':
                $error_message = isset($_GET['error_message']) ? esc_html(urldecode($_GET['error_message'])) : 'unknown error';
                /* translators: 1: the specific PHP error message (HTML-wrapped) */
                $message = sprintf( __( '<strong>Syntax Warning:</strong> The snippet was updated, but it contains a syntax error that may cause issues. <br><small>Error details: %1$s</small>', 'cwp-snippets' ), '<code>' . $error_message . '</code>' );
                $type = 'warning';
                break;

            case 'fatal_creation_syntax_error':
                $error_message = isset($_GET['error_message']) ? esc_html(urldecode($_GET['error_message'])) : 'unknown error';
                /* translators: 1: the specific PHP error message (HTML-wrapped) */
                $message = sprintf( __( '<strong>New Snippet Error:</strong> The snippet was <strong>NOT</strong> created.  It contains a fatal syntax error that may cause issues. <br><small>Error details: %1$s</small>', 'cwp-snippets' ), '<code>' . $error_message . '</code>' );
                $type = 'error';
                break;

            case 'update_syntax_error_fatal':
                $error_message = isset($_GET['error_message']) ? esc_html(urldecode($_GET['error_message'])) : 'unknown error';
                /* translators: 1: the specific PHP error message (HTML-wrapped) */
                $message = sprintf( __( '<strong>Error:</strong> Snippet was <strong>not</strong> updated due to a fatal syntax error. <br><small>Error details: %1$s</small>', 'cwp-snippets' ), '<code>' . $error_message . '</code>' );
                $type = 'error';
                break;

            case 'activation_syntax_error':
                $error_message = isset($_GET['error_message']) ? esc_html(urldecode($_GET['error_message'])) : 'unknown error';
                $snippet_name = isset($_GET['snippet_name']) ? ' in snippet "<strong>' . esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['snippet_name'] ) ) ) ) . '</strong>"' : '';
                /* translators: 1: snippet name context (may be empty), 2: error details (HTML-wrapped) */
                $message = sprintf( __( '<strong>Error:</strong> Snippet could not be activated due to a syntax error%1$s. Please fix the error before activating. <br><small>Error details: %2$s</small>', 'cwp-snippets' ), $snippet_name, '<code>' . $error_message . '</code>' );
                $type = 'error';
                break;

            case 'fatal_update_error':
                $error_message = isset($_GET['error_message']) ? esc_html(urldecode($_GET['error_message'])) : 'unknown error';
                $snippet_name = isset($_GET['snippet_name']) ? ' in snippet "<strong>' . esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['snippet_name'] ) ) ) ) . '</strong>"' : '';
                /* translators: 1: snippet name context (may be empty), 2: error details (HTML-wrapped) */
                $message = sprintf( __( '<strong>Fatal Error:</strong> This snippet could not be updated due to a fatal syntax error%1$s. Please fix the error and resave your snippet. <br><small>Error details: %2$s</small>', 'cwp-snippets' ), $snippet_name, '<code>' . $error_message . '</code>' );
                $type = 'error';
                break;

            // ... (rest of cases unchanged) ...
        }

        if ( $message ) {
            echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>'; // Use wp_kses_post for messages with HTML (like <code>)
        }
    }
}
