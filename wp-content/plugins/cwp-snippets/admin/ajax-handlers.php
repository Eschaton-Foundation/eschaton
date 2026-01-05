<?php
/**
 * AJAX Handlers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// *********************************************************************************************************************************
// Validate unique snippet name and type (AJAX Handler - Modified)

function check_snippet_uniqueness_handler() {
    // Check for nonce first
    // Use the correct nonce name passed from JS ('nonce' in this case)
    if (!isset($_POST['nonce']) || !check_ajax_referer('check_snippet_uniqueness_action', 'nonce', false)) {
        wp_send_json(array('error' => 'Nonce verification failed'));
        exit; // Use exit after wp_send_json in AJAX handlers
    }

    if (isset($_POST['name'], $_POST['type'])) {
        global $wpdb;
        $name = strtolower(trim(sanitize_text_field(wp_unslash($_POST['name']))));
        $type = sanitize_text_field(wp_unslash($_POST['type']));
        // Get the snippet ID being edited (0 if new)
        $snippet_id = isset($_POST['snippet_id']) ? intval($_POST['snippet_id']) : 0;
        $table_name = $wpdb->prefix . 'cwp_snippets';

        // Base query (build table name via concatenation to avoid interpolated variables inside the prepared string)
        $sql = 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE name = %s AND type = %s';
        $args = array( $name, $type );

        // If we are editing an existing snippet, exclude its ID from the check
        if ( $snippet_id > 0 ) {
            $sql .= ' AND id != %d';
            $args[] = $snippet_id;
        }

        // Prepare and execute the query using argument unpacking (do not pass an array as the second parameter)
        $exists = $wpdb->get_var( $wpdb->prepare( $sql, ...$args ) );

        wp_send_json(array('exists' => $exists > 0));
    } else {
        // Handle cases where required POST data is missing
        wp_send_json(array('error' => 'Missing required data (name or type).'));
    }
    exit; // Use exit after wp_send_json
}
// Ensure the action hook matches the 'action' sent in AJAX
add_action('wp_ajax_check_snippet_uniqueness', 'check_snippet_uniqueness_handler');


/**
 * Handles the AJAX request for activating a license.
 */
function cwp_handle_activate_license_ajax() {
    // Verify the nonce
    check_ajax_referer( 'cwp_license_ajax_nonce', '_ajax_nonce' );

    // Get the license key from the POST data
    $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

    if ( empty( $license_key ) ) {
        wp_send_json_error( array( 'message' => __( 'License key cannot be empty.', 'cwp-snippets' ) ) );
        return;
    }

    // Call the reusable function to perform the license activation
    // For activation, the current DB status and expiry are less critical as inputs, but can be passed.
    $current_license_data = get_option('cwp_snippets_license_data', array());
    $current_db_status = $current_license_data['status'] ?? 'unknown';
    $current_db_expiry_date = $current_license_data['expiry_date'] ?? null;

    $result = fmcwp_perform_license_server_request( 'activate', $license_key, $current_db_status, $current_db_expiry_date );
    
    // Send the JSON response to the client based on the outcome
    if ( $result['comm_error'] === false ) {
        // Communication with the server was successful
        if ( $result['server_success_flag'] === true && isset($result['final_status']) && $result['final_status'] === 'active' ) {
            // Server reported success AND the final status is 'active'
            wp_send_json_success( [
                'new_status'  => 'active', // Ensure it's 'active'
                'message'     => $result['message'],
                'expiry_date' => $result['final_expiry'],
                'license_key' => $license_key 
            ] );
        } else {
            // Server communication was OK, but activation didn't result in an 'active' status
            // (e.g., limit reached, server reported success:false, or status is inactive/expired)
            wp_send_json_error( [
                'new_status'  => $result['final_status'], // This will be the actual status like 'inactive', 'limit_reached', etc.
                'message'     => $result['message'],
                'expiry_date' => $result['final_expiry']
            ] );
        }
    } else {
        // Communication error with the licensing server
        wp_send_json_error( [
            'new_status'  => $result['final_status'],
            'message'     => $result['message'],
            'expiry_date' => $result['final_expiry']
        ] );
    }
}
add_action( 'wp_ajax_cwp_activate_license', 'cwp_handle_activate_license_ajax' );


/**
 * Handles the AJAX request for deactivating a license.
 */
function cwp_handle_deactivate_license_ajax() {
    // Verify the nonce
    check_ajax_referer( 'cwp_license_ajax_nonce', '_ajax_nonce' );

    // Get the license key from the POST data
    $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

    if ( empty( $license_key ) ) {
        wp_send_json_error( array( 'message' => __( 'License key not provided for deactivation.', 'cwp-snippets' ) ) );
        return;
    }

    // For deactivation, the current DB status and expiry are less critical as inputs.
    // The fmcwp_perform_license_server_request function will clear the key/expiry/site_url from options on successful deactivation.
    $current_license_data = get_option('cwp_snippets_license_data', array());
    $current_db_status = $current_license_data['status'] ?? 'unknown';
    $current_db_expiry_date = $current_license_data['expiry_date'] ?? null;

    $result = fmcwp_perform_license_server_request( 'deactivate', $license_key, $current_db_status, $current_db_expiry_date );

    // Send the JSON response to the client
    if ( $result['server_success_flag'] === true && $result['comm_error'] === false ) {
        wp_send_json_success( [
            'new_status'  => $result['final_status'], // Should be 'inactive' or similar
            'message'     => $result['message']
        ] );
    } else {
        wp_send_json_error( [
            'new_status'  => $result['final_status'], // Could be the old status if deactivation failed
            'message'     => $result['message']
        ] );
    }
}
add_action( 'wp_ajax_cwp_deactivate_license', 'cwp_handle_deactivate_license_ajax' );

/**
 * AJAX handler for checking the license status.
 */
function fmcwp_check_license_ajax_handler() {
    // Verify the nonce (should match the one used in JS and other handlers)
    check_ajax_referer( 'cwp_license_ajax_nonce', 'nonce' );

    $license_key_from_post = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

    // Get current license data for fallback and to ensure we have a key if not provided in POST
    $current_license_data = get_option('cwp_snippets_license_data', array());
    if (!is_array($current_license_data)) {
        $current_license_data = array(
            'license_key' => '',
            'status' => 'unknown',
            'expiry_date' => null,
            'site_url' => home_url()
        );
    }

    $license_key_to_check = !empty($license_key_from_post) ? $license_key_from_post : ($current_license_data['license_key'] ?? '');
    $current_local_status = $current_license_data['status'] ?? 'unknown';
    $current_db_expiry_date = $current_license_data['expiry_date'] ?? null;

    if ( empty( $license_key_to_check ) ) {
        wp_send_json_error( array( 'new_status' => $current_local_status, 'message' => __( 'License key not available for verification.', 'cwp-snippets' ) ) );
        return;
    }

    $result = fmcwp_perform_license_server_request( 'verify', $license_key_to_check, $current_local_status, $current_db_expiry_date );
    // Send the JSON response to the client
    if ( $result['server_success_flag'] === true && $result['comm_error'] === false ) {
        wp_send_json_success( [
            'new_status'  => $result['final_status'],
            'message'     => $result['message'],
            'expiry_date' => $result['final_expiry'],
        ] );
    } else {
        wp_send_json_error( [
            'new_status'  => $result['final_status'], // This will be the status from server, or fallback
            'message'     => $result['message'],
            'expiry_date' => $result['final_expiry'], // Send expiry date even on error
        ] );
    }
}
add_action( 'wp_ajax_cwp_check_license', 'fmcwp_check_license_ajax_handler' );

/**
 * AJAX handler to check for existing snippet names before an import.
 */
function fmcwp_check_import_conflicts_handler() {
    // Security checks
    // Reusing the nonce from the import form for this related action.
    check_ajax_referer('import_snippets_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        return;
    }

    // Get and sanitize input - normalize and sanitize each incoming snippet entry immediately
    $raw_snippets = isset($_POST['snippets']) && is_array($_POST['snippets']) ? wp_unslash($_POST['snippets']) : [];
    $snippets_to_check = array();
    foreach ( $raw_snippets as $raw ) {
        if ( is_array( $raw ) ) {
            $snippets_to_check[] = array(
                'name' => isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '',
                'type' => isset( $raw['type'] ) ? sanitize_text_field( $raw['type'] ) : '',
            );
        }
    }

    if (empty($snippets_to_check)) {
        wp_send_json_success(['conflicts' => []]);
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'cwp_snippets';
    $where_clauses = [];
    $query_args = [];

    foreach ($snippets_to_check as $snippet) {
        if (isset($snippet['name'], $snippet['type'])) {
            $where_clauses[] = "(name = %s AND type = %s)";
            $query_args[] = sanitize_text_field($snippet['name']);
            $query_args[] = sanitize_text_field($snippet['type']);
        }
    }

    if (empty($where_clauses)) {
        wp_send_json_success(['conflicts' => []]);
        return;
    }

    // Check each snippet individually to perform a case-insensitive comparison.
    $conflicting_names = array();
    foreach ( $snippets_to_check as $snippet ) {
        if ( empty( $snippet['name'] ) || empty( $snippet['type'] ) ) {
            continue;
        }
        // Check for a match case-insensitively.
        $count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE LOWER(name) = %s AND type = %s', strtolower( $snippet['name'] ), $snippet['type'] ) );

        if ( $count > 0 ) {
            // If a conflict is found, add the original name from the import file to the list.
            $conflicting_names[] = $snippet['name'];
        }
    }

    wp_send_json_success(['conflicts' => $conflicting_names]);
}
add_action('wp_ajax_fmcwp_check_import_conflicts', 'fmcwp_check_import_conflicts_handler');

/**
 * AJAX handler to fetch the content of a bundled snippet JSON file.
 * This is used to trigger the import dialog for bundled snippet updates.
 */
function fmcwp_fetch_bundled_snippets_handler() {
    // Security checks - reusing the import nonce as this is an import-related action.
    check_ajax_referer('import_snippets_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        return;
    }

    // Get and sanitize input
    $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
    $allowed_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];

    if (empty($type) || !in_array($type, $allowed_types)) {
        wp_send_json_error(['message' => 'Invalid snippet type specified.']);
        return;
    }

    // Construct file path
    $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $type . '.json';

    if (!file_exists($file_path) || !is_readable($file_path)) {
        wp_send_json_error(['message' => 'Bundled snippet file not found or is not readable.']);
        return;
    }

    $file_content = file_get_contents($file_path);
    $snippets_data = json_decode($file_content, true);

    // Send back the raw snippet data from the JSON file
    wp_send_json_success(['snippets' => $snippets_data]);
}
add_action('wp_ajax_fmcwp_fetch_bundled_snippets', 'fmcwp_fetch_bundled_snippets_handler');

/**
 * AJAX handler to fetch all available bundled snippet updates at once.
 */
function fmcwp_fetch_all_bundled_updates_handler() {
    // Security checks
    check_ajax_referer('import_snippets_action', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied.']);
        return;
    }

    $snippet_types = ['samples', 'templates', 'functions', 'scripts', 'styles'];
    $all_snippets_to_update = [];

    foreach ($snippet_types as $type) {
        if (get_transient('cwp_update_available_' . $type)) {
            $file_path = FMCWP_PLUGIN_PATH . 'assets/snippets/' . $type . '.json';
            if (file_exists($file_path) && is_readable($file_path)) {
                $file_content = file_get_contents($file_path);
                $snippets_data = json_decode($file_content, true);
                if (is_array($snippets_data)) {
                    $all_snippets_to_update = array_merge($all_snippets_to_update, $snippets_data);
                }
            }
        }
    }

    if (empty($all_snippets_to_update)) {
        wp_send_json_error(['message' => 'No bundled snippet updates found.']);
        return;
    }

    wp_send_json_success(['snippets' => $all_snippets_to_update]);
}
add_action('wp_ajax_fmcwp_fetch_all_bundled_updates', 'fmcwp_fetch_all_bundled_updates_handler');
?>