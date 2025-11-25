<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


// *********************************************************************************************************************************
// Set FM User ID

function set_fm_user_cookie() {
	 // are headers sent
	 $areHeadersSent = headers_sent();
	 // Set cookie expiration duration
	 // $expiration_time = time() + 3600 * 24;  // this would set the cookie for one day
     $expiration_time = time() + (365 * 24 * 60 * 60 * 10);	
	
	 //Set Test Cookie / @fixed Extra validation to avoid PHP warnings
    if (!isset($_COOKIE['fm_user_cookies']) && $areHeadersSent != true) {
		setcookie('fm_user_cookies', true, $expiration_time, "/");
    }
    // Check if the 'fm_user_id' cookie is already set
    if (!isset($_COOKIE['fm_user_id'])) {
        // Attempt to set the actual 'fm_user_id' cookie and then check if it persists
        $uniqueId = wp_generate_uuid4();  // Generate a UUID v4
        	// Ensure headers are not sent to avoid PHP warnings.
        	if($areHeadersSent != true) {
				setcookie('fm_user_id', $uniqueId, $expiration_time, "/");  // 1-day expiration
			}
        // Check if the cookie is set (i.e., cookies are enabled)
        if (isset($_COOKIE['fm_user_id'])) {
            // Cookies are available, manually set the cookie for immediate use
            $_COOKIE['fm_user_id'] = $uniqueId;
        } else {
            // Cookies are not available, fallback to generating a consistent user ID using other stable data
            // 1. IP Address (check multiple headers for proxy support)
            $ipAddress = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']))
                : (isset($_SERVER['HTTP_CLIENT_IP'])
                    ? sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']))
                    : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown_ip'));
            // 2. User-Agent
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'unknown_user_agent';
            // 3. Accept-Language header (optional for further uniqueness)
            $acceptLanguage = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : 'unknown_language';
            // 4. Combine the IP address, User-Agent, and Accept-Language
            $combinedData = $ipAddress . $userAgent . $acceptLanguage;
            // Hash the combined data using a strong algorithm (SHA-256) to create a consistent unique ID
            $uniqueId = hash('sha256', $combinedData);
            // Store the generated user ID in the $_COOKIE array for session continuity (even without real cookies)
				$_COOKIE['fm_user_id'] = $uniqueId;
        }
    }
}
add_action('init', 'set_fm_user_cookie');

/**
 * Sets a unique session ID cookie for developers to use.
 * This cookie is only set if the user's browser accepts cookies.
 */
function set_cwp_session_id_cookie() {
    // Check if headers have already been sent to avoid warnings
    if (headers_sent()) {
        return;
    }

    // Define cookie name and expiration (e.g., 10 years)
    $cookie_name = 'cwp_session_id';
    $expiration_time = time() + (365 * 24 * 60 * 60 * 10); // 10 years

    // Only set if the cookie is not already present
    if (!isset($_COOKIE[$cookie_name])) {
        $unique_session_id = wp_generate_uuid4();

        // Attempt to set the cookie
        setcookie($cookie_name, $unique_session_id, $expiration_time, "/");

        // For immediate use in the current request, if setting was successful
        if (isset($_COOKIE[$cookie_name])) {
            $_COOKIE[$cookie_name] = $unique_session_id;
        }
        // No fallback if cookies are not accepted, as per requirements.
    }
}
add_action('init', 'set_cwp_session_id_cookie');