<?php
/**
 * Pro Features Manager
 *
 * Handles detection and limitations for Pro features
 * Provides hooks for Pro version to override
 *
 * ============================================
 * 🔗 CENTRALIZED PRO URLS - CHANGE HERE ONLY!
 * ============================================
 * All Pro upgrade links throughout the plugin use these constants.
 * Change the URLs below to update all links across the plugin.
 *
 * @package AI_Chat_Search
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_Manager {
    const GATEWAY_ROLLOUT_CONFIG_URL = 'https://purethemes.net/gateway-config.json';
    const GATEWAY_ROLLOUT_OPTION = 'listeo_ai_gateway_rollout_config';
    const GATEWAY_ROLLOUT_TTL = 86400;


    /**
     * Number of blog posts available for training in Free.
     */
    const FREE_POST_TRAINING_LIMIT = 25;

    /**
     * Number of pages available for training in Free.
     *
     * The static homepage is selected automatically.
     */
    const FREE_PAGE_TRAINING_LIMIT = 1;

    /**
     * 🔗 Pro Upgrade URL
     *
     * Change this URL to update ALL "Upgrade to Pro" links throughout the plugin.
     * This URL is used everywhere a locked feature shows an upgrade link.
     */
    const PRO_UPGRADE_URL = 'https://purethemes.net/ai-chatbot-for-wordpress/';

    /**
     * 🔗 Pro Features URL
     *
     * Change this URL to update ALL "Learn More" and feature documentation links.
     * Currently not used in UI but available for future use.
     */
    const PRO_FEATURES_URL = 'https://purethemes.net/ai-chatbot-for-wordpress/';

    /**
     * Check if Pro version is active
     *
     * @return bool True if Pro plugin is active
     */
    public static function is_pro_active() {
        return apply_filters('ai_chat_search_pro_active', false);
    }

    /**
     * Check whether managed No API Key access is available.
     *
     * Free users and eligible licensed users may use the managed gateway.
     * Licensed eligibility is supplied by the Pro extension.
     *
     * @return bool True when No API Key access is available.
     */
    public static function can_use_no_api_key_access() {
        if (!self::is_pro_active()) {
            return self::is_gateway_rollout_enabled_for('free');
        }

        return self::can_use_license_managed_gateway();
    }

    /**
     * Refresh the remote Purio Cloud rollout configuration when its cache expires.
     * Failed requests and invalid responses are cached as disabled.
     *
     * @return array Normalized rollout configuration.
     */
    public static function refresh_gateway_rollout_config() {
        $cached = self::get_gateway_rollout_config();
        if (!empty($cached['checked_at'])) {
            return $cached;
        }

        $disabled = self::get_disabled_gateway_rollout_config(time());
        $response = wp_remote_get(self::GATEWAY_ROLLOUT_CONFIG_URL, array(
            'timeout' => 3,
            'sslverify' => true,
            'limit_response_size' => 16384,
        ));

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            update_option(self::GATEWAY_ROLLOUT_OPTION, $disabled, false);
            return $disabled;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $config = self::normalize_gateway_rollout_config($decoded, time());
        if (empty($config['checked_at'])) {
            update_option(self::GATEWAY_ROLLOUT_OPTION, $disabled, false);
            return $disabled;
        }

        update_option(self::GATEWAY_ROLLOUT_OPTION, $config, false);
        return $config;
    }

    /**
     * Get the fresh cached rollout configuration without making a remote request.
     *
     * @return array Normalized rollout configuration, disabled when stale or invalid.
     */
    public static function get_gateway_rollout_config() {
        $cached = get_option(self::GATEWAY_ROLLOUT_OPTION, array());
        $checked_at = is_array($cached) && isset($cached['checked_at'])
            ? (int) $cached['checked_at']
            : 0;
        if (
            $checked_at <= 0
            || $checked_at > time() + 300
            || time() - $checked_at >= self::GATEWAY_ROLLOUT_TTL
        ) {
            return self::get_disabled_gateway_rollout_config();
        }

        return self::normalize_gateway_rollout_config($cached, $checked_at);
    }

    /**
     * Check whether the cached rollout enables a specific access tier.
     *
     * @param string $tier One of: free, trial, pro.
     * @return bool
     */
    public static function is_gateway_rollout_enabled_for($tier) {
        if (!in_array($tier, array('free', 'trial', 'pro'), true)) {
            return false;
        }

        $config = self::get_gateway_rollout_config();
        return !empty($config['purio_cloud']['enabled'])
            && !empty($config['purio_cloud'][$tier . '_enabled']);
    }

    /**
     * Validate and normalize a remote or cached rollout configuration.
     *
     * @param mixed $config Raw configuration.
     * @param int   $checked_at Successful check timestamp.
     * @return array
     */
    private static function normalize_gateway_rollout_config($config, $checked_at) {
        if (
            !is_array($config)
            || !isset($config['schema_version'])
            || (int) $config['schema_version'] !== 1
            || !isset($config['purio_cloud'])
            || !is_array($config['purio_cloud'])
        ) {
            return self::get_disabled_gateway_rollout_config();
        }

        $cloud = $config['purio_cloud'];
        foreach (array('enabled', 'free_enabled', 'trial_enabled', 'pro_enabled') as $key) {
            if (!array_key_exists($key, $cloud) || !is_bool($cloud[$key])) {
                return self::get_disabled_gateway_rollout_config();
            }
        }

        return array(
            'schema_version' => 1,
            'purio_cloud' => array(
                'enabled' => $cloud['enabled'],
                'free_enabled' => $cloud['free_enabled'],
                'trial_enabled' => $cloud['trial_enabled'],
                'pro_enabled' => $cloud['pro_enabled'],
            ),
            'checked_at' => (int) $checked_at,
        );
    }

    /**
     * Get a fail-closed rollout configuration.
     *
     * @param int $checked_at Optional failed check timestamp.
     * @return array
     */
    private static function get_disabled_gateway_rollout_config($checked_at = 0) {
        return array(
            'schema_version' => 1,
            'purio_cloud' => array(
                'enabled' => false,
                'free_enabled' => false,
                'trial_enabled' => false,
                'pro_enabled' => false,
            ),
            'checked_at' => (int) $checked_at,
        );
    }

    /**
     * Check whether the installed Pro extension exposes signed managed access.
     *
     * @return bool
     */
    public static function can_use_license_managed_gateway() {
        return (bool) apply_filters('ai_chat_search_license_gateway_available', false);
    }

    /**
     * Check if post type is locked in free version
     *
     * @param string $post_type Post type slug
     * @return bool True if locked, false if available
     */
    public static function is_post_type_locked($post_type) {
        // Pro version can override to unlock all post types
        $is_locked = self::get_default_locked_status($post_type);

        return apply_filters('ai_chat_search_post_type_locked', $is_locked, $post_type);
    }

    /**
     * Can user access full conversation logs?
     *
     * @return bool True if allowed, false if locked
     */
    public static function can_access_conversation_logs() {
        // Pro can override to return true
        return apply_filters('ai_chat_search_can_access_conversation_logs', false);
    }

    /**
     * Get upgrade URL for specific feature
     *
     * @param string $feature Feature slug for tracking
     * @return string Upgrade URL
     */
    public static function get_upgrade_url($feature = '') {
        $url = apply_filters('ai_chat_search_upgrade_url', self::PRO_UPGRADE_URL);

        if ($feature) {
            $url = add_query_arg(array(
                'utm_source'   => 'ai-chat-plugin',
                'utm_medium'   => 'admin',
                'utm_campaign' => $feature
            ), $url);
        }

        return $url;
    }

    /**
     * Get "Learn More" URL for Pro features
     *
     * @return string Learn more URL
     */
    public static function get_learn_more_url() {
        return apply_filters('ai_chat_search_learn_more_url', self::PRO_FEATURES_URL);
    }

    /**
     * Get trial request URL with UTM tracking
     *
     * @return string Trial request URL with UTM parameters
     */
    public static function get_trial_request_url() {
        $base_url = 'https://purethemes.net/request-trial/';

        return add_query_arg(array(
            'utm_source'   => 'trial-request',
            'utm_medium'   => 'admin-banner',
            'utm_campaign' => 'trial-request'
        ), $base_url);
    }

    /**
     * Get default locked status for post type in free version
     *
     * FREE VERSION RULES:
     * - Listeo environment: listing = unlimited | everything else = locked
     * - Generic theme: post and page = limited | products and custom = locked
     *
     * @param string $post_type Post type slug
     * @return bool True if locked, false if available
     */
    private static function get_default_locked_status($post_type) {
        $is_listeo = self::is_listeo_available();

        // Listeo Free supports listings only.
        if ($is_listeo) {
            return $post_type !== 'listing';
        }

        // Generic themes can train a limited number of posts and pages.
        if (in_array($post_type, array('post', 'page'), true)) {
            return false;
        }

        // Lock products and all custom post types in generic themes.
        return true;
    }

    /**
     * Get available post types for free version
     *
     * @return array Array of available post type slugs
     */
    public static function get_free_available_post_types() {
        $is_listeo = self::is_listeo_available();

        if ($is_listeo) {
            return ['listing'];
        }

        return ['post', 'page'];
    }

    /**
     * Check whether a post type uses the Free training limit.
     *
     * @param string $post_type Post type slug.
     * @return bool
     */
    public static function is_free_training_limited($post_type) {
        return !self::is_pro_active()
            && !self::is_listeo_available()
            && in_array($post_type, array('post', 'page'), true);
    }

    /**
     * Get the adjustable Free training limit for posts/pages.
     *
     * @param string $post_type Post type slug.
     * @return int
     */
    public static function get_free_training_limit($post_type) {
        $default_limit = $post_type === 'page'
            ? self::FREE_PAGE_TRAINING_LIMIT
            : self::FREE_POST_TRAINING_LIMIT;

        $limit = (int) apply_filters(
            'listeo_ai_free_content_training_limit',
            $default_limit,
            $post_type
        );

        return max(0, $limit);
    }

    /**
     * Get locked post types in free version
     *
     * @return array Array of locked post type slugs
     */
    public static function get_locked_post_types() {
        $locked = ['product', 'ai_pdf_document', 'ai_external_page'];

        if (self::is_listeo_available()) {
            $locked[] = 'post';
            $locked[] = 'page';
        }

        // Add all detected custom post types
        $custom_types = AI_Chat_Search_Database_Manager::get_detected_custom_post_types();
        foreach ($custom_types as $type_data) {
            $locked[] = $type_data['name'];
        }

        return apply_filters('ai_chat_search_locked_post_types', $locked);
    }

    /**
     * Detect whether the Listeo theme or Listeo Core is active.
     *
     * @return bool
     */
    private static function is_listeo_available() {
        if (class_exists('Listeo_AI_Detection')) {
            return Listeo_AI_Detection::is_listeo_available();
        }

        if (class_exists('Listeo_Core') || class_exists('Listeo_Core_Listing')) {
            return true;
        }

        // Fallback: Check the active theme.
        $current_theme = wp_get_theme();
        $theme_name = $current_theme->get('Name');
        $parent_theme = $current_theme->get('Template');

        return (stripos($theme_name, 'listeo') !== false ||
                stripos($parent_theme, 'listeo') !== false);
    }

    /**
     * Get Pro badge HTML
     *
     * @return string HTML for Pro badge
     */
    public static function get_pro_badge() {
        return '<span class="ai-chat-pro-badge" title="' .
               esc_attr__('Available in Pro version', 'ai-chat-search') .
               '">PRO</span>';
    }

    /**
     * Get lock icon HTML
     *
     * @return string HTML for lock icon
     */
    public static function get_lock_icon() {
        return '<span class="dashicons dashicons-lock"></span>';
    }

    /**
     * Get maximum system prompt length based on version
     *
     * @return int Maximum character length
     */
    public static function get_max_system_prompt_length() {
        if (self::is_pro_active()) {
            return 6000;
        }

        if (self::is_listeo_available()) {
            return 0;
        }

        return apply_filters('ai_chat_search_free_prompt_limit', 500);
    }

    /**
     * Check if feature should show upgrade prompt
     *
     * @param string $feature Feature identifier
     * @return bool True if should show upgrade prompt
     */
    public static function should_show_upgrade_prompt($feature) {
        if (self::is_pro_active()) {
            return false;
        }

        // Don't show if user dismissed this feature's prompt
        $dismissed = get_user_meta(get_current_user_id(), 'ai_chat_dismissed_upgrade_prompts', true);
        if (!is_array($dismissed)) {
            $dismissed = [];
        }

        return !in_array($feature, $dismissed);
    }

    /**
     * Check if Pro was ever activated on this site
     *
     * Returns true if any evidence of past Pro activation exists:
     * - Pro plugin file is present
     * - License key option was set
     * - License validation transient exists
     * - Trial flag was set
     *
     * @return bool True if Pro was ever activated
     */
    public static function was_pro_ever_activated() {
        // Pro plugin file exists
        if (file_exists(WP_PLUGIN_DIR . '/ai-chat-search-pro/ai-chat-search-pro.php')) {
            return true;
        }

        // License key was ever set
        if (get_option('ai_chat_search_pro_license_key', '')) {
            return true;
        }

        // License validation transient exists
        if (get_transient('ai_chat_search_pro_license_valid')) {
            return true;
        }

        // Trial was ever activated
        if (get_option('ai_chat_search_pro_is_trial', false)) {
            return true;
        }

        return false;
    }

    /**
     * Get Listeo discount data from external JSON
     *
     * Fetches discount info from purethemes.net and caches it.
     * Returns false if fetch fails, JSON is invalid, or discount is inactive.
     *
     * @return array|false Discount data array or false
     */
    public static function get_listeo_discount_data() {
        $cached = get_transient('airs_listeo_discount_data');
        if ($cached !== false) {
            if (isset($cached['active']) && !$cached['active']) {
                return false;
            }
            return $cached;
        }

        $response = wp_remote_get('https://purethemes.net/listeo-discount.json', [
            'timeout' => 5,
            'sslverify' => true,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache failure for 1 hour to avoid hammering the server
            set_transient('airs_listeo_discount_data', ['active' => false], HOUR_IN_SECONDS);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['active'])) {
            set_transient('airs_listeo_discount_data', ['active' => false], HOUR_IN_SECONDS);
            return false;
        }

        // Sanitize the data
        $discount_data = [
            'active'           => true,
            'discount_percent' => isset($data['discount_percent']) ? absint($data['discount_percent']) : 0,
            'title'            => isset($data['title']) ? sanitize_text_field($data['title']) : '',
            'message'          => isset($data['message']) ? sanitize_text_field($data['message']) : '',
            'url'              => isset($data['url']) ? esc_url_raw($data['url']) : self::PRO_UPGRADE_URL,
            'coupon_code'      => isset($data['coupon_code']) ? sanitize_text_field($data['coupon_code']) : '',
        ];

        set_transient('airs_listeo_discount_data', $discount_data, 12 * HOUR_IN_SECONDS);

        return $discount_data;
    }

    /**
     * Render upgrade prompt box
     *
     * @param array $args Arguments for rendering prompt
     */
    public static function render_upgrade_prompt($args = []) {
        $defaults = [
            'title' => __('Upgrade to Pro', 'ai-chat-search'),
            'description' => '',
            'features' => [],
            'feature_id' => '',
            'show_learn_more' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $upgrade_url = self::get_upgrade_url($args['feature_id']);
        $learn_more_url = self::get_learn_more_url();

        ?>
        <div class="ai-chat-upgrade-prompt">
            <div class="upgrade-prompt-icon">
                <?php echo self::get_lock_icon(); ?>
            </div>

            <div class="upgrade-prompt-content">
                <h3><?php echo esc_html($args['title']); ?></h3>

                <?php if ($args['description']): ?>
                    <p class="description"><?php echo esc_html($args['description']); ?></p>
                <?php endif; ?>

                <?php if (!empty($args['features'])): ?>
                    <ul class="upgrade-features-list">
                        <?php foreach ($args['features'] as $feature): ?>
                            <li>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php echo esc_html($feature); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="upgrade-prompt-actions">
                    <a href="<?php echo esc_url($upgrade_url); ?>"
                       class="button button-primary button-large"
                       target="_blank">
                        <?php _e('Upgrade to Pro', 'ai-chat-search'); ?>
                    </a>

                    <?php if ($args['show_learn_more']): ?>
                        <a href="<?php echo esc_url($learn_more_url); ?>"
                           class="button button-secondary"
                           target="_blank">
                            <?php _e('Learn More', 'ai-chat-search'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
