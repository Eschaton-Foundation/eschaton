<?php
/**
 * Pro Plugin Updater Class
 *
 * Handles self-hosted plugin updates for AI Chat & Search Pro
 * Checks for updates every 24 hours from purethemes.net
 *
 * @package AI_Chat_Search_Pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_Updater {

    /**
     * Update server URL (JSON manifest)
     */
    private $update_url = 'https://purethemes.net/license/plugins/ai-chat-search-pro-updates.json';

    /**
     * Protected update check endpoint.
     */
    private $protected_update_check_url = 'https://purethemes.net/wp-json/purethemes-license-proxy/v1/check-plugin-update';

    /**
     * Plugin slug
     */
    private $plugin_slug = 'ai-chat-search';

    /**
     * Plugin file (relative path)
     */
    private $plugin_file = 'ai-chat-search-pro/ai-chat-search-pro.php';

    /**
     * Protected update package slug
     */
    private $protected_package_slug = 'ai-chat-search-pro';

    /**
     * Cache key for update check
     */
    private $cache_key = 'ai_chat_search_pro_update_data';

    /**
     * Cache key for update errors
     */
    private $error_cache_key = 'ai_chat_search_pro_update_error';

    /**
     * Cache interval (24 hours)
     */
    private $cache_interval = DAY_IN_SECONDS;

    /**
     * Current plugin version
     */
    private $current_version;

    /**
     * License manager instance
     */
    private $license_manager;

    /**
     * Constructor
     */
    public function __construct() {
        $this->current_version = AI_CHAT_SEARCH_PRO_VERSION;
        $this->license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();

        // Allow filtering the update URL (for testing)
        $this->update_url = apply_filters('ai_chat_search_pro_update_url', $this->update_url);
        $this->protected_update_check_url = apply_filters('ai_chat_search_pro_protected_update_check_url', $this->protected_update_check_url);

        // Allow filtering the check interval
        $this->cache_interval = apply_filters('ai_chat_search_pro_update_check_interval', $this->cache_interval);

        // Hook into WordPress update system
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'download_protected_package'), 10, 4);

        // Manual update check (bypass cache)
        add_action('wp_ajax_ai_chat_search_pro_check_update_now', array($this, 'manual_update_check'));

        // Show license warning if invalid
        add_action('after_plugin_row_' . $this->plugin_file, array($this, 'show_license_warning'), 10, 2);
    }

    /**
     * Check for plugin updates
     *
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Force clear cache when WordPress does manual update check
        if (isset($_GET['force-check'])) {
            delete_transient($this->cache_key);
            $this->clear_update_error();
        }

        // Check cache first
        $update_data = get_transient($this->cache_key);

        if ($update_data === false) {
            // Cache expired or doesn't exist - fetch fresh data
            $update_data = $this->fetch_update_info();

            if ($update_data !== false) {
                // Cache the result for 24 hours
                set_transient($this->cache_key, $update_data, $this->cache_interval);
            }
        }

        // If update data exists and version is newer
        if (
            $update_data
            && isset($update_data->new_version)
            && version_compare($this->current_version, $update_data->new_version, '<')
            && !empty($update_data->package)
        ) {
            $transient->response[$this->plugin_file] = $update_data;
        } else {
            // Explicitly mark as no update available
            $transient->no_update[$this->plugin_file] = $update_data ?: $this->get_no_update_object();
        }

        return $transient;
    }

    /**
     * Fetch update information from server
     *
     * @return object|false Update data object or false on failure
     */
    private function fetch_update_info() {
        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || !isset($data->version)) {
            return false;
        }

        $package_url = isset($data->download_url) ? $data->download_url : '';
        $update_available = version_compare($this->current_version, $data->version, '<');

        if ($update_available && $this->is_protected_download_url($package_url)) {
            $package_url = $this->resolve_protected_download_url($package_url, $data->version);
        } else {
            $this->clear_update_error();
        }

        // Build update object
        $update_data = (object) array(
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_file,
            'new_version' => $data->version,
            'url' => isset($data->homepage) ? $data->homepage : 'https://purethemes.net/ai-chatbot-for-wordpress/',
            'package' => $package_url,
            'tested' => isset($data->tested) ? $data->tested : '',
            'requires_php' => isset($data->requires_php) ? $data->requires_php : '7.4',
            'requires' => isset($data->requires) ? $data->requires : '5.0',
            'last_updated' => isset($data->last_updated) ? $data->last_updated : '',
            'sections' => array(
                'description' => isset($data->sections->description) ? $data->sections->description : '',
                'changelog' => isset($data->sections->changelog) ? $data->sections->changelog : ''
            ),
            'banners' => array(
                'low' => isset($data->banners->low) ? $data->banners->low : '',
                'high' => isset($data->banners->high) ? $data->banners->high : ''
            ),
            'icons' => array(
                '1x' => isset($data->icons->{'1x'}) ? $data->icons->{'1x'} : '',
                '2x' => isset($data->icons->{'2x'}) ? $data->icons->{'2x'} : ''
            )
        );

        return $update_data;
    }

    /**
     * Check whether a manifest download URL points to the protected licenser endpoint.
     *
     * @param string $url Download URL from manifest.
     * @return bool
     */
    private function is_protected_download_url($url) {
        return is_array($this->parse_protected_download_url($url));
    }

    /**
     * Parse protected download URL into package/version.
     *
     * @param string $url Download URL.
     * @return array|null
     */
    private function parse_protected_download_url($url) {
        $paths = array();
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (!empty($path)) {
            $paths[] = $path;
        }

        $query = wp_parse_url($url, PHP_URL_QUERY);
        if (!empty($query)) {
            parse_str($query, $query_args);
            if (!empty($query_args['rest_route'])) {
                $paths[] = $query_args['rest_route'];
            }
        }

        foreach ($paths as $candidate_path) {
            if (preg_match('~/purethemes-license-proxy/v1/download-package/([a-z0-9-]+)/([^/?#]+)~', $candidate_path, $matches)) {
                return array(
                    'package' => sanitize_key(rawurldecode($matches[1])),
                    'version' => sanitize_text_field(rawurldecode($matches[2])),
                );
            }
        }

        return null;
    }

    /**
     * Resolve protected manifest URL to a signed package URL.
     *
     * @param string $manifest_download_url Protected URL from manifest.
     * @param string $version Version from manifest.
     * @return string Signed package URL or empty string.
     */
    private function resolve_protected_download_url($manifest_download_url, $version) {
        $license_key = $this->license_manager->get_license_key();
        $package = $this->parse_protected_download_url($manifest_download_url);

        if (empty($license_key)) {
            $this->set_update_error('license_missing', $version);
            return '';
        }

        if (empty($package['package']) || $package['version'] !== $version) {
            $this->set_update_error('invalid_manifest_download_url', $version);
            return '';
        }

        $response = wp_remote_post($this->protected_update_check_url, array(
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'package' => $package['package'],
                'version' => $version,
                'manifest_download_url' => $manifest_download_url,
                'license_key' => $license_key,
            )),
            'timeout' => 10,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            $this->set_update_error('update_check_failed', $version);
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (wp_remote_retrieve_response_code($response) !== 200) {
            $this->set_update_error(isset($body['code']) ? $body['code'] : 'update_check_failed', $version);
            return '';
        }

        if (
            !is_array($body)
            || empty($body['remote_updates_allowed'])
            || empty($body['download_url'])
        ) {
            $this->set_update_error(isset($body['code']) ? $body['code'] : 'update_package_unavailable', $version);
            return '';
        }

        $this->clear_update_error();

        return esc_url_raw($body['download_url']);
    }

    /**
     * Store a safe user-facing update error.
     *
     * @param string $code Error code.
     * @param string $version Update version.
     */
    private function set_update_error($code, $version = '') {
        $code = is_scalar($code) ? sanitize_key((string) $code) : 'update_package_unavailable';

        set_transient($this->error_cache_key, array(
            'code' => $code,
            'message' => $this->get_public_update_error_message($code),
            'version' => sanitize_text_field($version),
        ), $this->cache_interval);
    }

    /**
     * Clear stored update error.
     */
    private function clear_update_error() {
        delete_transient($this->error_cache_key);
    }

    /**
     * Get stored update error.
     *
     * @return array|null
     */
    private function get_update_error() {
        $error = get_transient($this->error_cache_key);

        return is_array($error) ? $error : null;
    }

    /**
     * Map server error codes to safe user-facing messages.
     *
     * @param string $code Error code.
     * @return string
     */
    private function get_public_update_error_message($code) {
        $code = is_scalar($code) ? sanitize_key((string) $code) : '';

        switch ($code) {
            case 'license_missing':
                return __('Please activate your PurioChat Pro license to install this update.', 'ai-chat-search');

            case 'license_not_found':
                return __('Your license could not be verified. Please check your license key or contact PureThemes support.', 'ai-chat-search');

            case 'license_inactive':
                return __('Your license is not active. Please reactivate your license or contact PureThemes support.', 'ai-chat-search');

            case 'product_mismatch':
                return __('This license is not valid for PurioChat Pro updates. Please use the correct license key or contact PureThemes support.', 'ai-chat-search');

            case 'rate_limit_exceeded':
                return __('Too many update checks. Please wait about one hour and try again.', 'ai-chat-search');

            case 'invalid_download_token':
            case 'download_token_expired':
            case 'rest_missing_callback_param':
                return __('The secure download link expired. Please click Check again and retry the update.', 'ai-chat-search');

            default:
                return __('The update package is temporarily unavailable. Please contact PureThemes support.', 'ai-chat-search');
        }
    }

    /**
     * Download protected package with mapped error messages.
     *
     * @param false|WP_Error|string $reply Previous filter result.
     * @param string                $package Package URL.
     * @param WP_Upgrader           $upgrader Upgrader instance.
     * @param array                 $hook_extra Upgrader context.
     * @return false|WP_Error|string
     */
    public function download_protected_package($reply, $package, $upgrader, $hook_extra) {
        if (false !== $reply || empty($package)) {
            return $reply;
        }

        $protected_package = $this->parse_protected_download_url($package);
        if (
            empty($protected_package['package'])
            || $protected_package['package'] !== $this->protected_package_slug
            || (!empty($hook_extra['plugin']) && $hook_extra['plugin'] !== $this->plugin_file)
        ) {
            return $reply;
        }

        $download_file = download_url($package, 300);
        if (!is_wp_error($download_file)) {
            $this->clear_update_error();
            return $download_file;
        }

        $code = $this->get_download_error_code($download_file);
        $this->set_update_error($code, !empty($protected_package['version']) ? $protected_package['version'] : '');

        return new WP_Error('download_failed', $this->get_public_update_error_message($code));
    }

    /**
     * Extract REST error code from a failed package download.
     *
     * @param WP_Error $error Download error.
     * @return string
     */
    private function get_download_error_code($error) {
        $data = $error->get_error_data();
        if (is_array($data) && !empty($data['body'])) {
            $body = json_decode($data['body'], true);
            if (is_array($body) && !empty($body['code'])) {
                return $body['code'];
            }
        }

        if ($error->get_error_code() === 'http_no_url') {
            return 'update_package_unavailable';
        }

        return 'update_check_failed';
    }

    /**
     * Get "no update" object for current version
     *
     * @return object No update object
     */
    private function get_no_update_object() {
        return (object) array(
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_file,
            'new_version' => $this->current_version,
            'url' => 'https://purethemes.net/ai-chatbot-for-wordpress/',
            'package' => '',
            'tested' => '',
            'requires_php' => '7.4',
            'requires' => '5.0'
        );
    }

    /**
     * Provide plugin information for "View Details" link
     *
     * @param false|object|array $result The result object or array
     * @param string $action The type of information being requested
     * @param object $args Plugin API arguments
     * @return false|object Modified result
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Get cached update data
        $update_data = get_transient($this->cache_key);

        if ($update_data === false) {
            $update_data = $this->fetch_update_info();
        }

        if ($update_data) {
            return (object) array(
                'slug' => $this->plugin_slug,
                'name' => 'PurioChat Pro',
                'version' => $update_data->new_version,
                'author' => '<a href="https://purethemes.net">PureThemes</a>',
                'homepage' => $update_data->url,
                'requires' => $update_data->requires,
                'tested' => $update_data->tested,
                'requires_php' => $update_data->requires_php,
                'download_link' => $update_data->package,
                'sections' => $update_data->sections,
                'banners' => $update_data->banners,
                'icons' => $update_data->icons,
                'last_updated' => $update_data->last_updated
            );
        }

        return $result;
    }

    /**
     * Show license warning in plugins list if invalid
     *
     * @param string $plugin_file Plugin file
     * @param array $plugin_data Plugin data
     */
    public function show_license_warning($plugin_file, $plugin_data) {
        $update_error = $this->get_update_error();
        if (!empty($update_error['message'])) {
            echo '<tr class="plugin-update-tr active"><td colspan="4" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-error notice-alt"><p>';
            echo '<strong>' . esc_html__('Automatic update unavailable:', 'ai-chat-search') . '</strong> ';
            echo esc_html($update_error['message']);
            echo '</p></div></td></tr>';
        }

        if (!$this->license_manager->is_license_valid()) {
            echo '<tr class="plugin-update-tr active"><td colspan="4" class="plugin-update colspanchange">';
            echo '<div class="update-message notice inline notice-warning notice-alt"><p>';
            echo '<strong>' . esc_html__('License Required:', 'ai-chat-search') . '</strong> ';
            echo wp_kses_post(sprintf(
                __('Please <a href="%s">activate your license</a> to receive automatic updates and support.', 'ai-chat-search'),
                esc_url(admin_url('admin.php?page=ai-chat-search&tab=license'))
            ));
            echo '</p></div></td></tr>';
        }
    }

    /**
     * Manual update check (AJAX handler)
     * Bypasses cache and forces fresh check
     */
    public function manual_update_check() {
        check_ajax_referer('listeo_ai_search_nonce', 'nonce');

        if (!current_user_can('update_plugins')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'ai-chat-search')));
        }

        // Delete cache to force fresh check
        delete_transient($this->cache_key);

        // Clear WordPress plugin cache
        wp_clean_plugins_cache();

        // Fetch fresh update info
        $update_data = $this->fetch_update_info();

        if ($update_data === false) {
            wp_send_json_error(array('message' => __('Failed to check for updates. Please try again later.', 'ai-chat-search')));
        }

        // Cache the fresh data
        set_transient($this->cache_key, $update_data, $this->cache_interval);

        // Check if update is available
        $update_available = version_compare($this->current_version, $update_data->new_version, '<');
        $update_error = $this->get_update_error();

        if ($update_available && !empty($update_error['message'])) {
            wp_send_json_error(array('message' => $update_error['message']));
        }

        wp_send_json_success(array(
            'update_available' => $update_available,
            'current_version' => $this->current_version,
            'latest_version' => $update_data->new_version,
            'message' => $update_available
                ? sprintf(__('Update available: %s', 'ai-chat-search'), $update_data->new_version)
                : __('You have the latest version!', 'ai-chat-search')
        ));
    }

    /**
     * Force update check (for external use)
     *
     * @return array Update status
     */
    public static function force_check() {
        $instance = new self();

        delete_transient('ai_chat_search_pro_update_data');
        wp_clean_plugins_cache();

        $update_data = $instance->fetch_update_info();

        if ($update_data) {
            set_transient('ai_chat_search_pro_update_data', $update_data, DAY_IN_SECONDS);
            $update_error = $instance->get_update_error();

            return array(
                'success' => true,
                'update_available' => version_compare(AI_CHAT_SEARCH_PRO_VERSION, $update_data->new_version, '<'),
                'latest_version' => $update_data->new_version,
                'error' => $update_error,
            );
        }

        return array('success' => false, 'message' => 'Update check failed');
    }
}

// Initialize updater
new AI_Chat_Search_Pro_Updater();
