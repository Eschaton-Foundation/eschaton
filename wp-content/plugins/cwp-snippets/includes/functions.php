<?php

// =================================================================================
// Universal Utility Functions for CWP Snippets Admin Toggle / Admin AJAX Options
// =================================================================================
// These functions are reusable across all CWP Snippets admin pages

// --- 3a. Universal Master AJAX Handler for Toggle Settings ---
// This handler is used by ALL snippet admin pages for toggle switches
// Register once globally with: if (!function_exists()) guard
if (!function_exists('cwp_universal_toggle_setting_callback')) {
    function cwp_universal_toggle_setting_callback() {
        check_ajax_referer('cwp_universal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $option_name = isset($_POST['option_name']) ? sanitize_text_field($_POST['option_name']) : '';
        $setting_key = isset($_POST['setting_key']) ? sanitize_text_field($_POST['setting_key']) : '';
        $setting_value = isset($_POST['setting_value']) ? sanitize_text_field($_POST['setting_value']) : '';
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : 'Setting updated';
        
        if (empty($option_name) || empty($setting_key)) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
		

        // Get existing options and update the setting
        $options = get_option($option_name);
		
        if (!is_array($options)) {
            $options = [];
        }
        
        $options[$setting_key] = $setting_value;
        
        // Save the updated options
        $success = update_option($option_name, $options);
        
		// Verify the value was actually saved
		$saved_options = get_option($option_name);	
		
		if (!empty($saved_options) && isset($saved_options[$setting_key]) && $saved_options[$setting_key] === $setting_value) {
   			 wp_send_json_success(['message' => $message]);
		} else {
    		wp_send_json_error(['message' => 'Failed to update setting']);
		}
    }
    add_action('wp_ajax_cwp_universal_toggle_setting', 'cwp_universal_toggle_setting_callback');
}

// AJAX: Clear All CWP Transients (admin only)
if (!function_exists('cwp_ajax_clear_all_transients_callback')) {
    function cwp_ajax_clear_all_transients_callback() {
        check_ajax_referer('cwp_universal_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Require an explicit confirmation flag to avoid accidental clears
        $confirm = isset($_POST['confirm']) ? sanitize_text_field($_POST['confirm']) : '';
        if ($confirm !== '1') {
            wp_send_json_error(['message' => 'Missing confirmation (send confirm=1)']);
        }

        if (!function_exists('cwpClearAllTransients')) {
            wp_send_json_error(['message' => 'Clear-all helper not available']);
        }

        $deleted = cwpClearAllTransients();
        wp_send_json_success(['deleted' => intval($deleted), 'message' => "Deleted $deleted transients."]); 
    }
    add_action('wp_ajax_cwp_clear_all_transients', 'cwp_ajax_clear_all_transients_callback');
}


// =================================================================================
// Universal Data Caching Functions (FileMaker, API, etc.)
// =================================================================================
// Use these helpers to store, retrieve, and clear any data in WordPress transients.
// Example: cwpCacheData('my_key', $data, 3600); // Set
//          cwpCacheData('my_key');              // Get
//          cwpClearCache('my_key');             // Clear

// Prefix for all custom CWP Utility Functions transients
if (!defined('CWP_TRANSIENT_PREFIX')) {
    define('CWP_TRANSIENT_PREFIX', 'cwpUF_');
}

if (!function_exists('cwpCacheData')) {
    /**
     * Set or get cached data using WordPress transients (always prefixed with CWP_TRANSIENT_PREFIX)
     *
     * @param string $key    Unique cache key (will be prefixed)
     * @param mixed  $data   Data to cache (if null, function returns cached value)
     * @param int    $expire Expiry in seconds (default: 1 hour)
     * @return mixed         Cached value or true/false for set
     */
    function cwpCacheData($key, $data = null, $expire = 3600) {
        $prefixed_key = CWP_TRANSIENT_PREFIX . $key;
        if ($data === null) {
            // Get cache
            return get_transient($prefixed_key);
        } else {
            // Set cache
            return set_transient($prefixed_key, $data, $expire);
        }
    }
}

if (!function_exists('cwpClearCache')) {
    /**
     * Clear cached data by key (uses CWP_TRANSIENT_PREFIX)
     *
     * @param string $key Cache key to clear (will be prefixed)
     * @return bool       True if deleted, false otherwise
     */
    function cwpClearCache($key) {
        $prefixed_key = CWP_TRANSIENT_PREFIX . $key;
        return delete_transient($prefixed_key);
    }
}

/**
 * Validate a URL (generic helper)
 * Purpose: Check if a FileMaker Streaming_SSL image URL is valid/accessible to repopulate cached data
 * @param string|array $data string or array
 * @return boolean true : false
 */
if (!function_exists('cwpIsURLValid')) {
    /**
     * Simple check for FileMaker Streaming_SSL image availability
     *
     * - Accepts a FileMaker response/array or a URL string
     * - Searches the data for the first value containing "Streaming_SSL" (case-insensitive)
     * - If none found, returns true (no expired streaming images found)
     * - If found, tests that single URL (HEAD, then GET fallback) and returns true on 2xx, false otherwise
     *
     * Purposefully simple for beginner devs: returns only a boolean.
     *
     * @param array|string $data FileMaker response array or URL string
     * @return bool True if images are OK (or none found), false if the first Streaming_SSL URL is invalid
     */
    function cwpIsURLValid($data, $logging = false) {
        $foundUrl = '';

        // Helper: recursively search array values for first Streaming_SSL occurrence
        $search = function($input) use (&$search, &$foundUrl) {
            if (!is_array($input)) {
                return;
            }

            foreach ($input as $value) {
                if (!empty($foundUrl)) {
                    return; // stop once found
                }

                if (is_array($value)) {
                    $search($value);
                    continue;
                }

                if (!is_string($value)) {
                    continue;
                }

                if (stripos($value, 'Streaming_SSL') !== false) {
                    $foundUrl = trim($value);
                    return;
                }
            }
        };

        // If input is a string, check directly
        if (is_string($data)) {
            $maybe = trim($data);
            if (stripos($maybe, 'Streaming_SSL') !== false) {
                $foundUrl = $maybe;
            }
        } elseif (is_array($data)) {
            $search($data);
        }

        // If no Streaming_SSL URL found, consider images OK
        if (empty($foundUrl)) {
            return true;
        }

        // Ensure it looks like a URL (simple check)
        if (stripos($foundUrl, 'http') !== 0) {
            return true; // not a standard URL; treat as OK to avoid false negatives
        }

        // Request options
        $args = [
            'timeout'   => 3,
            'sslverify' => false,
            'redirection' => 3,
        ];

        // Try HEAD first
        $response = wp_remote_head($foundUrl, $args);

        // Helper to evaluate response success (2xx)
        $is_success = function($resp) use (&$foundUrl, $logging) {
            if (is_wp_error($resp)) {
                // Misc. connection error
                if ($logging) {
                    $logMessage = 'Streaming_SSL URL invalid: ' . $foundUrl;
                    cwpLog('cwp_image_invalid', $logMessage, '', 0, __LINE__);
                }                
                return false;
            }
            $code = intval( wp_remote_retrieve_response_code( $resp ) );

            if ( $code >= 200 && $code < 300 ) {
                $isValid = true;
            } else {
                $isValid = false;
                if ( $logging ) {
                    $err = is_wp_error( $resp ) ? $resp->get_error_message() : '';
                    $logMessage = 'Streaming_SSL URL invalid: ' . $foundUrl . ' (HTTP ' . $code . ')' . ( $err ? ' - ' . $err : '' );
                    cwpLog( 'cwp_image_invalid', $logMessage, '', 0, __LINE__ );
                }
            }
            // return our is_success result
            return $isValid;
        };

        if ($is_success($response)) {
            return true;
        }

        // HEAD failed - retry once with GET (some servers block HEAD)
        $response = wp_remote_get($foundUrl, $args);
        if ($is_success($response)) {
            return true;
        }

        // Still failed - consider images invalid
        if ($logging) {
            $logMessage = 'Streaming_SSL URL invalid: ' . $foundUrl;
            cwpLog('cwp_image_invalid', $logMessage, '', 0, __LINE__);
        }
        return false;
    }
}

// =================================================================================
// REST API Endpoint: Clear Cache by Key
// =================================================================================
// Allows external apps (e.g., FileMaker) to clear a cache key via REST API.
// Usage: Send a POST request to /wp-json/cwp/v1/clear-cache
//        with a JSON body: { "cache_key": "your_key", "token": "your_token" }
//        and header: Content-Type: application/json
// WordPress will automatically parse JSON and pass parameters to the endpoint.
// Add your own token/auth logic for security.
// See README.md for more details and examples.

add_action('rest_api_init', function() {
    register_rest_route('cwp/v1', '/clear-cache', [
        'methods' => 'POST',
        'callback' => 'cwp_cache_endpoint',
        'permission_callback' => function () {
            // NOTE: This endpoint is left intentionally permissive for now.
            // It's recommended to restrict this in production (e.g., require auth or a token).
            return true;
        },
        'args' => [
            'cache_key' => [
                'required' => true,
                'type' => 'string',
            ],
            'clear_all' => [
                'required' => false,
                'type' => 'boolean',
            ],
            'token' => [
                'required' => false,
                'type' => 'string',
            ],
        ],
    ]);
}); // end add_action


if (!function_exists('cwp_cache_endpoint')) { // suggested: wrap to avoid errors in case of multiple includes
    /**
     * REST API handler to clear cache by key
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    function cwp_cache_endpoint($request) {
        $cache_key = $request->get_param('cache_key');
        $clear_all = $request->get_param('clear_all');
        /* TODO: Add your own token/auth logic here
        *  for example ->
        *  ********************************************** */
        // $token = $request->get_param('token');
        // if($token && $token !== 'your_expected_token') {
        //     // Invalid token, deny access and exit script
        //     return new WP_REST_Response(['success' => false, 'message' => 'Invalid token'], 403);
        // }

        // no cache key submitted to clear
        // Support clearing all CWP transients via flag or special cache_key
        if (!empty($clear_all) || $cache_key === '__all__') {
            // Note: This clear-all action is intentionally permissive — implementers
            // may add their own permission/token logic in the `permission_callback`.
            if (!function_exists('cwpClearAllTransients')) {
                return new WP_REST_Response(['success' => false, 'message' => 'Clear-all helper not available'], 500);
            }
            $deleted = cwpClearAllTransients();
            // Log the action if logging helper exists
            if (function_exists('cwpLog')) {
                cwpLog('cache_cleared', 'REST API clear-all triggered, deleted ' . intval($deleted) . ' transients');
            }
            return new WP_REST_Response(['success' => true, 'deleted' => intval($deleted), 'message' => "Deleted $deleted transients."], 200);
        }

        // no cache key submitted to clear
        if (empty($cache_key)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing cache key'], 400);
        }

        // We have a key, they have passed auth (if any), clear the cache
        $result = cwpClearCache($cache_key);
        if ($result) {
            return new WP_REST_Response(['success' => true, 'message' => "Cache '$cache_key' cleared."], 200);
        } else {
            return new WP_REST_Response(['success' => false, 'message' => "Cache '$cache_key' not found or could not be cleared."], 404);
        }

    } // end function cwp_cache_endpoint
}

// =================================================================================
// Universal FileMaker Data Sender
// =================================================================================
// Use this helper to send any array/object as JSON to FileMaker, with optional script trigger.
// Example:
//   $result = cwpSendToFM($data, 'FM_Layout', 'FM_Field', 'ScriptName');
//   if (!$result['success']) cwpShow($result, 'FileMaker Error');

if (!function_exists('cwpSendToFM')) {
    /**
     * Send data to FileMaker as a JSON string, optionally run a script, and return a standardized response.
     *
     * @param array|object $data      Data to send (will be JSON-encoded)
     * @param string       $layout    FileMaker layout to use
     * @param string       $field     FileMaker field to store JSON
     * @param string|null  $script    Optional FileMaker script to run
     * @param array        $fm_opts   Optional: override FM connection constants (host, db, layout, user, pass)
     * @return array                  [success, message, fm_response]
     */
    function cwpSendToFM($data, $layout, $field, $script = null, $fm_opts = []) {
        // Use global FM constants unless overridden
        $host     = $fm_opts['host']     ?? (defined('FM_HOST')     ? FM_HOST     : null);
        $db       = $fm_opts['db']       ?? (defined('FM_DATABASE') ? FM_DATABASE : null);
        $user     = $fm_opts['user']     ?? (defined('FM_USER')     ? FM_USER     : null);
        $pass     = $fm_opts['pass']     ?? (defined('FM_PASSWORD') ? FM_PASSWORD : null);
        
        // Always use fmCWP class for FileMaker connection 
        // overwrite at will, but all methods and functions will have to be altered accordingly
        $fm_class = 'fmCWP';

        if (!$host || !$db || !$user || !$pass) {
            return [
                'success' => false,
                'message' => 'Missing FileMaker connection constants or override.',
                'response' => null
            ];
        }

        // Instantiate FM class
        if (!class_exists($fm_class)) {
            return [
                'success' => false,
                'message' => "FileMaker class 'fmCWP' not found.",
                'response' => null
            ];
        }
        $fm = new $fm_class($host, $db, $layout, $user, $pass);
        $fm->setFilemakerLayout($layout);

        // Prepare parameters && set script (optional: if provided)
        $params = [
            'fieldData' => [ $field => json_encode($data) ]
        ];
        if (!empty($script)) {
            $params['script'] = $script;
        }

        // Create record
        $result   = $fm->createRecord($params);
        $response = $fm->getResponse($result);

        // Check for Data API error
        if ($fm->isError($result)) {
            return [
                'success' => false,
                'message' => 'FileMaker Data API Error: ' . ($response['messages'][0]['message'] ?? 'Unknown API Error'),
                'response' => $response
            ];
        }

        // Check script result if present
        $script_result_json = $response['response']['scriptResult'] ?? null;
        $script_result_data = $script_result_json ? json_decode($script_result_json, true) : null;
        if ($script && isset($script_result_data['success']) && $script_result_data['success'] == '1') {
            return [
                'success' => true,
                'message' => 'FileMaker script ran successfully.',
                'response' => $script_result_data
            ];
        } elseif ($script && $script_result_data) {
            return [
                'success' => false,
                'message' => 'FileMaker Script Error: ' . ($script_result_data['result'] ?? 'No error message provided by script.'),
                'response' => $script_result_data
            ];
        }

        // No script or no script result, but record created
        return [
            'success' => true,
            'message' => 'FileMaker record created.',
            'response' => $response
        ];
    }
}

// =================================================================================
// Transient Expiry Viewer
// =================================================================================
// Use this helper to display all current transients and their expiry times for debugging.
// Example: cwpShowTransients();

if (!function_exists('cwpShowTransients')) {
    /**
     * Display all current CWP transients and their expiry times using cwpShow.
     * Only visible to admins.
     */
    function cwpShowTransients() {
        if (!current_user_can('manage_options')) {
            return;
        }
        global $wpdb;
        $transients = [];
        // Only get CWP Utility Functions-prefixed transients (site and normal)
        $prefix = '_transient_' . CWP_TRANSIENT_PREFIX;
        $site_prefix = '_site_transient_' . CWP_TRANSIENT_PREFIX;
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name ASC",
                $prefix . '%', $site_prefix . '%'
            )
        );
        foreach ($results as $row) {
            $name = $row->option_name;
            $value = maybe_unserialize($row->option_value);
            $expiry = null;
            // Try to find expiry (timeout) for this transient
            if (strpos($name, '_transient_') === 0) {
                $timeout_name = str_replace('_transient_', '_transient_timeout_', $name);
            } elseif (strpos($name, '_site_transient_') === 0) {
                $timeout_name = str_replace('_site_transient_', '_site_transient_timeout_', $name);
            } else {
                $timeout_name = null;
            }
            $timeout = $timeout_name ? get_option($timeout_name) : false;
            if ($timeout) {
                $expiry = date('Y-m-d H:i:s', $timeout);
            }
            $transients[] = [
                'name'   => $name,
                'value'  => $value,
                'expires'=> $expiry ?: 'no expiry',
            ];
        }
        fmcwpShowResponse($transients, 'CWP Custom Transients & Expiry');
    }
}

// =================================================================================
// Clear All CWP Transients
// =================================================================================
// Delete all transients (and site transients) that use the CWP_TRANSIENT_PREFIX.
if (!function_exists('cwpClearAllTransients')) {
    /**
     * Clear all CWP-prefixed transients.
     *
     * @param string $prefix Optional prefix (default: CWP_TRANSIENT_PREFIX)
     * @return int Number of transients deleted
     */
    function cwpClearAllTransients($prefix = CWP_TRANSIENT_PREFIX) {
        global $wpdb;
        $deleted = 0;

        // Build LIKE patterns for option_name
        $like1 = '_transient_' . $prefix . '%';
        $like2 = '_site_transient_' . $prefix . '%';

        $sql = $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like1,
            $like2
        );

        $results = $wpdb->get_col($sql);
        if (empty($results)) {
            return 0;
        }

        foreach ($results as $name) {
            if (strpos($name, '_transient_') === 0) {
                $key = substr($name, strlen('_transient_'));
                if (delete_transient($key)) {
                    $deleted++;
                }
            } elseif (strpos($name, '_site_transient_') === 0) {
                $key = substr($name, strlen('_site_transient_'));
                if (is_multisite()) {
                    if (delete_site_transient($key)) {
                        $deleted++;
                    }
                } else {
                    // On single-site installs, fall back to delete_transient
                    if (delete_transient($key)) {
                        $deleted++;
                    }
                }
            }
        }

        return $deleted;
    }
}

// =================================================================================
// Universal Error Logging Helper
// =================================================================================
// Use this helper to log errors, warnings, or info to the CWP error log table.
// Only 'error_issue' (category/slug) and 'error' (message/details) are required.
// All other fields are optional. This is designed for simplicity and ease of use.
//
// Example usage:
//   cwpLog('api_error', 'Failed to connect to FileMaker API');
//   cwpLog('validation', 'Missing required field', 'My Snippet', 123, 45);
//
// If you need more complex logging, you can extend this function or write your own.

if (!function_exists('cwpLog')) {
    /**
     * Log an error, warning, or info to the CWP error log table.
     *
     * @param string $error_issue  Short category/slug for the error (required)
     * @param string $error        Main error message/details (required)
     * @param string $snippet_name Name of the snippet (optional)
     * @param int    $snippet_id   ID of the snippet (optional)
     * @param int    $error_line   Line number (optional)
     * @return bool                True if logged, false otherwise
     */
    function cwpLog($error_issue, $error, $snippet_name = '', $snippet_id = 0, $error_line = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cwp_error_log';
        // If no error message is provided, do not log and return false
        if (empty($error)) {
            return false;
        }
        // If no error_issue/category is provided, use a default
        if (empty($error_issue)) {
            $error_issue = 'Error:';
        }

        // check for pro licensing
        $custom_log_enabled = get_option('fmcwp_enable_custom_log');
        $is_pro = function_exists('cwp_is_pro_active') && cwp_is_pro_active();
        if ($is_pro && $custom_log_enabled) {

            // Prepare the data array for the log entry
            $data = array(
                'timestamp'      => current_time('mysql'), // Current WP time
                'error_issue'    => $error_issue,          // Short category/slug for the error
                'snippet_name'   => $snippet_name,         // (Optional) Name of the snippet
                'snippet_id'     => intval($snippet_id),   // (Optional) Snippet ID
                'error'          => $error,                // Main error message/details
                'error_line'     => intval($error_line)    // (Optional) Line number
            );
            $format = array('%s', '%s', '%s', '%d', '%s', '%d');
        try {
            // Attempt to insert the log entry into the custom DB table
            $result = $wpdb->insert($table_name, $data, $format);
            if ($result === 1) {
                // Success: entry logged to DB
                return true;
            } else {
                // Fallback: log to PHP error log if DB insert fails
                $msg = "CWP Snippets - DB log failed: [$error_issue] $error";
                if ($snippet_name) { $msg .= " | Snippet: $snippet_name"; }
                if ($snippet_id) { $msg .= " | ID: $snippet_id"; }
                if ($error_line) { $msg .= " | Line: $error_line"; }
                error_log($msg);
                return false;
            }
        } catch (Exception $e) {
            // Fallback: log to PHP error log if an exception occurs
            $msg = "CWP Snippets - Exception: [" . $error_issue . "] $error | Exception: " . $e->getMessage();
            if ($snippet_name) { $msg .= " | Snippet: $snippet_name"; }
            if ($snippet_id) { $msg .= " | ID: $snippet_id"; }
            if ($error_line) { $msg .= " | Line: $error_line"; }
            error_log($msg);
            return false;
        }
    } else {
        // Pro not active or custom logging disabled
        // Fallback: log to PHP error log if an exception occurs
            $msg = "CWP Snippets - Exception: [" . $error_issue . "] $error | Exception: " . $e->getMessage();
            if ($snippet_name) { $msg .= " | Snippet: $snippet_name"; }
            if ($snippet_id) { $msg .= " | ID: $snippet_id"; }
            if ($error_line) { $msg .= " | Line: $error_line"; }
            error_log($msg);
            return false;
    } // end if is_pro && conditional logging enabled


    } // end function
} // end function exists

// =================================================================================
// Universal Cache Busting Helper
// =================================================================================
// Use this helper to append a version or timestamp to asset URLs for cache busting.
// Example: cwp_cache_bust(get_stylesheet_directory_uri() . '/style.css');
if (!function_exists('cwp_cache_bust')) {
    /**
     * Append a cache-busting query string to a file URL.
     * @param string $url  The asset URL.
     * @param string|int $version  (Optional) Version or timestamp. Default: filemtime.
     * @return string|false        The cache-busted URL, or false if input is invalid.
     */
    function cwp_cache_bust($url, $version = null) {
        if (empty($url)) {
            return false;
        }
        if ($version === null) {
            $file = ABSPATH . str_replace(site_url('/'), '', $url);
            $version = @file_exists($file) ? @filemtime($file) : time();
        }
        return add_query_arg('ver', $version, $url);
    }
}

// =================================================================================
// Universal Safe Redirect Helper
// =================================================================================
// Use this helper to safely redirect, even if headers are already sent.
// Example: cwp_safe_redirect(home_url('/thank-you'));
if (!function_exists('cwp_safe_redirect')) {
    /**
     * Safely redirect to a URL, even if headers are already sent.
     * @param string $url
     * @param int $status
     * @return bool False if input is invalid, otherwise does not return.
     */
    function cwp_safe_redirect($url, $status = 302) {
        if (empty($url)) {
            return false;
        }
        if (!headers_sent()) {
            wp_redirect($url, $status);
            exit;
        } else {
            echo '<meta http-equiv="refresh" content="0;url=' . esc_url($url) . '">';
            echo '<script>window.location.href="' . esc_js($url) . '";</script>';
            exit;
        }
    }
}