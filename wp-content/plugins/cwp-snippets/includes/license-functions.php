<?php
/**
 * CWP Snippets License Helper Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Performs a request to the license server and updates the local license option.
 *
 * @param string $action_on_server The action to perform on the license server (e.g., 'verify', 'activate', 'deactivate').
 * @param string $license_key The license key.
 * @param string $current_db_status The current status stored in the DB, for fallback.
 * @param string|null $current_db_expiry_date The current expiry date stored in the DB, for fallback.
 * @return array An array containing the result:
 *               'final_status' => string, The status to be used/stored.
 *               'final_expiry' => string|null, The expiry date to be used/stored.
 *               'message'      => string, The message from the server or an error message.
 *               'server_success_flag' => bool, The 'success' flag from the server's JSON response.
 *               'comm_error'   => bool, True if a communication error occurred before parsing server data.
 */
function fmcwp_perform_license_server_request( $action_on_server, $license_key, $current_db_status = 'unknown', $current_db_expiry_date = null ) {
	$site_url = wp_parse_url( home_url(), PHP_URL_HOST );
	$api_url = 'https://cwpsnippets.com/license';
	$product_name = 'CWP Snippets';

	$return_data = array(
		'final_status'        => $current_db_status,
		'final_expiry'        => $current_db_expiry_date,
		'message'             => __( 'Could not connect to the licensing server.', 'cwp-snippets' ),
		'server_success_flag' => false,
		'comm_error'          => true,
	);

	$request_args = array(
		'method'  => 'POST',
		'timeout' => 45,
		'body'    => array(
			'action'       => $action_on_server,
			'license_key'  => $license_key,
			'site_url'     => $site_url,
			'product_name' => $product_name,
		),
	);

	$response = wp_remote_post( $api_url, $request_args );

	if ( is_wp_error( $response ) ) {
		$return_data['message'] = __( 'Failed to connect to licensing server: ', 'cwp-snippets' ) . $response->get_error_message();
		return $return_data;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$response_body = wp_remote_retrieve_body( $response );

	if ( empty( $response_body ) ) {
		$return_data['message'] = __( 'Received empty response from licensing server.', 'cwp-snippets' );
		return $return_data;
	}

	$server_data = json_decode( $response_body, true );

	if ( json_last_error() !== JSON_ERROR_NONE ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CWP Snippets License Request: Invalid JSON response from server. Action: ' . $action_on_server . '. Body: ' . $response_body );
		}
		$return_data['message'] = __( 'Invalid response from licensing server (not valid JSON).', 'cwp-snippets' );
		return $return_data;
	}

	// If we reached here, communication was successful in some form.
	$return_data['comm_error'] = false;

	// Extract core data from server response, with defaults
	$return_data['server_success_flag'] = $server_data['success'] ?? false;
	$server_message_from_json = isset($server_data['message']) && !empty($server_data['message'])
	                            ? sanitize_text_field($server_data['message'])
	                            : null; // Keep null if server sent no specific message
	$server_status_from_json = isset($server_data['status'])
	                           ? sanitize_text_field($server_data['status'])
	                           : null;
	$server_expiry_date_from_json = isset($server_data['expiry_date'])
	                                ? sanitize_text_field($server_data['expiry_date'])
	                                : null;

	// Determine the final status for the plugin's internal use and storage.
	// $return_data['final_status'] is already initialized to $current_db_status.
	// It should only be updated if the server provides an explicit 'status' field in its response.
	if ($server_status_from_json !== null) {
		$return_data['final_status'] = $server_status_from_json;
	} else {
		// Server response was received and successfully parsed, but it's missing the 'status' field.
		// $return_data['final_status'] (already $current_db_status) remains unchanged.
		// Log this, as the server should ideally always provide a status if the communication was successful.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log('CWP Snippets License Request: Server response parsed but missing "status" field. Local status (' . $return_data['final_status'] . ') remains unchanged. Action: ' . $action_on_server . '. Server data: ' . esc_html( print_r( $server_data, true ) ) );
		}
	}

	// Determine final expiry date
	$return_data['final_expiry'] = $server_expiry_date_from_json ?? $current_db_expiry_date;

	// Determine the final message to display to the client
	$return_data['message'] = $server_message_from_json ?? __('License status processed.', 'cwp-snippets');
	if ($server_status_from_json === null && $return_data['server_success_flag'] === true && $server_message_from_json === null) {
		// If status was missing, success was true, and no message, then use the "missing essential data" message.
		$return_data['message'] = __('Server response missing essential data (status), but reported success.', 'cwp-snippets');
	}

	// Always update the WordPress option and transient with the determined status and expiry
	$license_data_to_store = array(
		'license_key' => $license_key,
		'status'      => $return_data['final_status'],
		'expiry_date' => $return_data['final_expiry'],
		'site_url'    => $site_url,
	);
	if ( $action_on_server === 'deactivate' && $return_data['server_success_flag'] === true ) {
		$license_data_to_store['license_key'] = '';
		$license_data_to_store['expiry_date'] = null;
		$license_data_to_store['site_url']    = '';
	}
	update_option( 'cwp_snippets_license_data', $license_data_to_store );
	wp_cache_delete( 'cwp_snippets_license_data', 'options' );

	$transient_key = 'cwp_license_status_cache';
	$transient_data = array(
		'status'      => $return_data['final_status'],
		'expiry_date' => $return_data['final_expiry'],
	);
	set_transient( $transient_key, $transient_data, 12 * HOUR_IN_SECONDS );
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'CWP Snippets License Request - Option and Transient updated. Action: ' . $action_on_server . '. Data: ' . esc_html( print_r( $license_data_to_store, true ) ) );
	}

	return $return_data;
}

/**
 * Performs the scheduled license check.
 * This function is hooked to the WP-Cron event.
 */
function cwp_snippets_perform_scheduled_license_check() {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'CWP Snippets: Scheduled license check started.' );
	}

	$license_data = get_option( 'cwp_snippets_license_data', array() );
	$license_key = isset( $license_data['license_key'] ) ? $license_data['license_key'] : '';

	if ( ! empty( $license_key ) ) {
		$current_db_status = isset( $license_data['status'] ) ? $license_data['status'] : 'unknown';
		$current_db_expiry_date = isset( $license_data['expiry_date'] ) ? $license_data['expiry_date'] : null;

		// Perform the license verification
		$result = fmcwp_perform_license_server_request( 'verify', $license_key, $current_db_status, $current_db_expiry_date );

		if ( $result['comm_error'] ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CWP Snippets: Scheduled license check - Communication error: ' . $result['message'] );
			}
		} else {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'CWP Snippets: Scheduled license check - Success. New status: ' . $result['final_status'] . '. Message: ' . $result['message'] );
			}
		}
	} else {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CWP Snippets: Scheduled license check - No license key found to check.' );
		}
	}
}