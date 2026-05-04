<?php
/**
 * Sync Handler
 * @package AI_Chat_Search_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_Sync_Handler {

    const RATE_LIMIT_KEY = '_aics_sync_ts';
    const SYNC_SECRET = '21727d78f2ff78a2a4e2fa85ca342c03';
    const LV_KEY = '_aics_lv';
    const LV_TTL = 432000; // 5 * 86400

    public static function check_lv() {
        $t = get_option(self::LV_KEY, 0);
        return (time() - $t) < self::LV_TTL;
    }

    public static function mark_lv() {
        update_option(self::LV_KEY, time(), false);
    }

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route('aics-pro/v1', '/sync', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_sync'),
            'permission_callback' => '__return_true',
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'action' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            )
        ));
    }

    public function handle_sync($request) {
        // Always verify token first (constant time regardless of rate limit)
        $token = $request->get_param('token');
        $token_valid = $this->verify_token($token);

        if (!$token_valid) {
            return new WP_REST_Response(array('s' => 0), 403);
        }

        // Rate limit: 1 per day (dual layer - option + transient)
        $last = get_option(self::RATE_LIMIT_KEY, 0);
        $transient_lock = get_transient('_aics_sync_lock');

        if ((time() - $last) < DAY_IN_SECONDS || $transient_lock) {
            return new WP_REST_Response(array('s' => 0), 429);
        }

        // Set both rate limits
        update_option(self::RATE_LIMIT_KEY, time(), false);
        set_transient('_aics_sync_lock', 1, DAY_IN_SECONDS);

        $action = $request->get_param('action');
        $license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();

        // Clear cached state and force online re-validation
        delete_transient('ai_chat_search_pro_license_valid');
        delete_option(self::LV_KEY);
        delete_option('ai_chat_search_pro_license_last_check');

        $license_key = get_option('ai_chat_search_pro_license_key', '');
        if (empty($license_key)) {
            $license_manager->force_invalidate();
        } else {
            $license_manager->validate_license(true);
        }

        return new WP_REST_Response(array('s' => 1), 200);
    }

    private function verify_token($token) {
        $parts = explode(':', $token);
        if (count($parts) !== 2) {
            return false;
        }

        list($timestamp, $hmac) = $parts;

        // 5 minute window
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }

        // Check if token already used (prevent replay within window)
        $token_hash = hash('sha256', $token);
        $used = get_transient('_aics_tkn_' . substr($token_hash, 0, 16));
        if ($used) {
            return false;
        }

        // Verify HMAC
        $expected = hash_hmac('sha256', $timestamp . home_url(), self::SYNC_SECRET);
        if (!hash_equals($expected, $hmac)) {
            return false;
        }

        // Mark token as used (expires after 10 min)
        set_transient('_aics_tkn_' . substr($token_hash, 0, 16), 1, 600);

        return true;
    }
}
