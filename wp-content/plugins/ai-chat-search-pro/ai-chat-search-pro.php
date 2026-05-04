<?php
/**
 * Plugin Name: AI Chat & Search Pro
 * Plugin URI: https://purethemes.net/ai-chatbot-for-wordpress/
 * Description: Premium features for AI Chat & Search plugin - Unlimited post types, full conversation logs, and priority support
 * Version: 2.0.7
 * Author: PureThemes
 * Author URI: https://purethemes.net
 * License: GPL2
 * Text Domain: ai-chat-search
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define Pro constants early
define('AI_CHAT_SEARCH_PRO_VERSION', '2.0.7');
define('AI_CHAT_SEARCH_PRO_FILE', __FILE__);
define('AI_CHAT_SEARCH_PRO_DIR', plugin_dir_path(__FILE__));
define('AI_CHAT_SEARCH_PRO_URL', plugin_dir_url(__FILE__));

/**
 * Check if free version is active
 * Use plugins_loaded hook to ensure free version has loaded first
 */
function ai_chat_search_pro_check_dependencies() {
    // Check if free version is active by looking for its main class
    if (!class_exists('Listeo_AI_Search')) {
        add_action('admin_notices', 'ai_chat_search_pro_missing_free_notice');
        return false;
    }
    return true;
}

/**
 * Show admin notice when free version is missing
 */
function ai_chat_search_pro_missing_free_notice() {
    $install_url = wp_nonce_url(
        admin_url('admin-post.php?action=ai_chat_search_pro_install_base'),
        'ai_chat_search_pro_install_base'
    );
    ?>
    <div class="notice" style="padding:0;border:none;background:transparent;box-shadow:none;">
        <div style="background:#fff;border-radius:6px;box-shadow:0 3px 8px rgba(0,0,0,0.08);border-left:4px solid #dc2626;padding:22px 25px;font-family:'Outfit',BlinkMacSystemFont,'Segoe UI',sans-serif;">
            <div style="display:flex;align-items:flex-start;gap:14px;">
                <div style="width:40px;height:40px;background:#fef2f2;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <div style="flex:1;">
                    <h3 style="margin:0 0 6px 0;font-size:17px;font-weight:600;color:#111;"><?php _e('AI Chat Pro Requires Base (Free) Plugin', 'ai-chat-search'); ?></h3>
                    <p style="margin:0 0 16px 0;font-size:14px;color:#666;line-height:1.5;"><?php _e('AI Chat & Search Pro requires the free base plugin to be installed and activated.', 'ai-chat-search'); ?></p>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php if (current_user_can('install_plugins')) : ?>
                            <a href="<?php echo esc_url($install_url); ?>" class="button" style="background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%) !important;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                                <?php _e('Install & Activate Base Plugin', 'ai-chat-search'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="https://purethemes.net/license/plugins/ai-chat-search.zip" target="_blank" class="button" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;padding:8px 18px;border-radius:6px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                            <?php _e('Download Manually', 'ai-chat-search'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Handle one-click install of the free base plugin
 */
function ai_chat_search_pro_install_base_handler() {
    if (!current_user_can('install_plugins')) {
        wp_die(esc_html__('You do not have permission to install plugins.', 'ai-chat-search'));
    }

    check_admin_referer('ai_chat_search_pro_install_base');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    $plugin_slug = 'ai-chat-search';
    $plugin_file = $plugin_slug . '/ai-chat-search.php';
    $download_url = 'https://purethemes.net/license/plugins/ai-chat-search.zip';

    // If already installed, just activate
    if (file_exists(WP_PLUGIN_DIR . '/' . $plugin_file)) {
        $result = activate_plugin($plugin_file);
        if (is_wp_error($result)) {
            wp_redirect(admin_url('plugins.php?aics_error=' . urlencode($result->get_error_message())));
            exit;
        }
        wp_redirect(admin_url('plugins.php?aics_activated=1'));
        exit;
    }

    // Download the zip
    $temp_file = download_url($download_url, 60);
    if (is_wp_error($temp_file)) {
        wp_redirect(admin_url('plugins.php?aics_error=' . urlencode($temp_file->get_error_message())));
        exit;
    }

    // Unzip
    global $wp_filesystem;
    if (!WP_Filesystem()) {
        wp_redirect(admin_url('plugins.php?aics_error=' . urlencode(__('Unable to initialize filesystem.', 'ai-chat-search'))));
        exit;
    }

    $result = unzip_file($temp_file, WP_PLUGIN_DIR);
    @unlink($temp_file);

    if (is_wp_error($result)) {
        wp_redirect(admin_url('plugins.php?aics_error=' . urlencode($result->get_error_message())));
        exit;
    }

    // Clear plugin cache so get_plugins() sees the newly extracted plugin
    wp_clean_plugins_cache(true);

    // Find the actual plugin file (handles any folder name)
    $plugins = get_plugins();
    $plugin_to_activate = '';
    foreach ($plugins as $plugin_path => $plugin_data) {
        if (strpos($plugin_path, 'ai-chat-search/') === 0 && $plugin_path !== 'ai-chat-search-pro/ai-chat-search-pro.php') {
            $plugin_to_activate = $plugin_path;
            break;
        }
    }

    if ($plugin_to_activate) {
        $activate_result = activate_plugin($plugin_to_activate);
        if (is_wp_error($activate_result)) {
            wp_redirect(admin_url('plugins.php?aics_error=' . urlencode($activate_result->get_error_message())));
            exit;
        }
    } else {
        wp_redirect(admin_url('plugins.php?aics_error=' . urlencode(__('Plugin extracted but could not be found in the plugin list.', 'ai-chat-search'))));
        exit;
    }

    wp_redirect(admin_url('plugins.php?aics_installed=1'));
    exit;
}
add_action('admin_post_ai_chat_search_pro_install_base', 'ai_chat_search_pro_install_base_handler');

/**
 * Show feedback notice after install/activate attempt
 */
function ai_chat_search_pro_install_feedback_notice() {
    if (!empty($_GET['aics_installed'])) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Base plugin installed and activated successfully.', 'ai-chat-search'); ?></p>
        </div>
        <?php
    }
    if (!empty($_GET['aics_activated'])) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Base plugin activated successfully.', 'ai-chat-search'); ?></p>
        </div>
        <?php
    }
    if (!empty($_GET['aics_error'])) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html(urldecode(sanitize_text_field(wp_unslash($_GET['aics_error'])))); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'ai_chat_search_pro_install_feedback_notice');

// Check dependencies after all plugins have loaded
add_action('plugins_loaded', function() {
    if (!ai_chat_search_pro_check_dependencies()) {
        return; // Stop initialization if free version is missing
    }

    // Initialize Pro plugin if dependency check passed
    AI_Chat_Search_Pro::get_instance();
}, 5); // Priority 5 to run before free version's init at priority 20

/**
 * Main Pro Plugin Class
 */
class AI_Chat_Search_Pro {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Activate Pro features via hooks
        $this->activate_pro_features();

        // Load Pro components
        $this->load_dependencies();

        // Initialize
        add_action('plugins_loaded', array($this, 'init'), 20);
    }

    /**
     * Activate Pro features through free version's hooks
     */
    private function activate_pro_features() {
        // Tell free version that Pro is active ONLY if license is valid
        add_filter('ai_chat_search_pro_active', array($this, 'check_license_valid'));

        // Check license before unlocking features
        add_filter('ai_chat_search_pro_license_valid', array($this, 'check_license_valid'));

        // Unlock all post types (only if license is valid)
        add_filter('ai_chat_search_post_type_locked', array($this, 'unlock_post_types'), 10, 2);

        // Enable conversation logs access (only if license is valid)
        add_filter('ai_chat_search_can_access_conversation_logs', array($this, 'allow_conversation_logs'));

        // Override upgrade URL to account page
        add_filter('ai_chat_search_upgrade_url', array($this, 'get_account_url'));
        add_filter('ai_chat_search_learn_more_url', array($this, 'get_account_url'));
    }

    /**
     * Check if license is valid
     *
     * @return bool True if license is valid
     */
    public function check_license_valid() {
        // Use proxy-based license manager
        $license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();
        return $license_manager->is_license_valid();
    }

    /**
     * Unlock post types if license is valid
     *
     * @param bool $locked Current locked status
     * @param string $post_type Post type slug
     * @return bool False to unlock, true to keep locked
     */
    public function unlock_post_types($locked, $post_type) {
        if ($this->check_license_valid()) {
            return false; // Unlock
        }
        return $locked; // Keep locked if license invalid
    }

    /**
     * Allow conversation logs if license is valid
     *
     * @return bool True if allowed
     */
    public function allow_conversation_logs() {
        return $this->check_license_valid();
    }

    /**
     * Load Pro dependencies
     */
    private function load_dependencies() {
        // Sync handler must load BEFORE license manager (license manager uses it for validation)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-sync.php';

        // License Manager - Proxy-based (recommended for production)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-proxy-license-manager.php';

        // Old direct DodoPayments integration (keep as backup)
        // require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-license-manager.php';

        // Admin License Tab
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-admin-license-tab.php';

        // Pro-specific features: Conversation Logs
        // Class is provided by free version - Pro only unlocks UI access via filters

        // PDF Management (Pro feature)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-pdf-manager.php';
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-pdf-admin-ui.php';

        // Quick Action Buttons (Pro feature - code moved from free plugin)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-quick-buttons.php';
        new AI_Chat_Search_Pro_Quick_Buttons();

        // IP Address Blocking (Pro feature)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-blocked-ips.php';
        new AI_Chat_Search_Pro_Blocked_IPs();

        // Pre-Chat Required Fields (Pro feature)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-pre-chat-fields.php';
        new AI_Chat_Search_Pro_Pre_Chat_Fields();

        // Contact Tool for AI (Pro feature - code moved from free plugin)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-contact-tool.php';
        new AI_Chat_Search_Pro_Contact_Tool();

        // Webhook Tool for AI (Pro feature - triggers webhooks to external systems)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-webhook-tool.php';
        new AI_Chat_Search_Pro_Webhook_Tool();

        // Webhook Admin UI (checkbox, modal, settings — injected via hooks)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-webhook-admin.php';

        // Chat History Data Provider (Pro feature - data retrieval moved from free plugin)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-chat-history-data.php';
        new AI_Chat_Search_Pro_Chat_History_Data();

        // Chat History Chart (Pro feature - visual graph for 30-day activity)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-chat-history-chart.php';
        new AI_Chat_Search_Pro_Chat_History_Chart();

        // Conversation Auditor (Pro feature - AI-driven analysis of chat history)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-conversation-auditor.php';
        AI_Chat_Search_Pro_Conversation_Auditor::get_instance();

        // Content Extractors for Page/Product (Pro feature - moved from free plugin)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-content-extractors.php';
        new AI_Chat_Search_Pro_Content_Extractors();

        // Speech-to-Text (Pro feature - voice input for chat)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-speech-to-text.php';
        new AI_Chat_Search_Pro_Speech_To_Text();

        // External Pages (Pro feature - add external web pages for AI training)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/external-pages/class-external-pages.php';
        AI_Chat_Search_Pro_External_Pages::get_instance();

        // WooCommerce Integration (Pro feature - moved from free plugin)
        add_action('listeo_ai_woocommerce_integration', array($this, 'init_woocommerce_integration'));

        // Cart popup overlay (Pro feature — shared template for shortcode & floating widget)
        add_action('listeo_ai_chat_cart_popup', function () {
            include AI_CHAT_SEARCH_PRO_DIR . 'includes/cart-popup-template.php';
        });

        // Messaging Channels (WhatsApp, Telegram)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/messaging/class-messaging-migrations.php';
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/messaging/class-messaging-channel.php';
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/messaging/class-whatsapp-handler.php';
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/messaging/class-whatsapp-admin.php';
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/messaging/class-telegram-handler.php';
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/messaging/class-telegram-admin.php';
        AI_Chat_Search_Pro_Messaging_Migrations::init();
        new AI_Chat_Search_Pro_WhatsApp_Handler();
        new AI_Chat_Search_Pro_Telegram_Handler();

        // Plugin updater (self-hosted updates with license validation)
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/class-updater.php';

        // Initialize sync handler (class already loaded at top of method)
        AI_Chat_Search_Pro_Sync_Handler::get_instance();
    }

    /**
     * Initialize Pro version
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('ai-chat-search', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize Proxy License Manager
        AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();

        // Initialize Admin License Tab (adds License tab to admin UI)
        if (is_admin()) {
            AI_Chat_Search_Pro_Admin_License_Tab::get_instance();
            new AI_Chat_Search_Pro_Webhook_Admin();
            new AI_Chat_Search_Pro_WhatsApp_Admin();
            new AI_Chat_Search_Pro_Telegram_Admin();
        }

        // Show Pro activation notice
        if (is_admin() && get_transient('ai_chat_search_pro_activated')) {
            add_action('admin_notices', array($this, 'show_activation_notice'));
            delete_transient('ai_chat_search_pro_activated');
        }
    }

    /**
     * Get account URL
     */
    public function get_account_url($url) {
        return 'https://purethemes.net/ai-chat-search-pro/';
    }

    /**
     * Show Pro activation notice
     */
    public function show_activation_notice() {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php _e('AI Chat & Search Pro activated!', 'ai-chat-search'); ?></strong>
                <?php _e('All premium features are now unlocked.', 'ai-chat-search'); ?>
                <a href="<?php echo admin_url('admin.php?page=ai-chat-search'); ?>">
                    <?php _e('Go to Settings', 'ai-chat-search'); ?> →
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Initialize WooCommerce integration (Pro feature)
     * Called via 'listeo_ai_woocommerce_integration' action
     */
    public function init_woocommerce_integration() {
        // Only load if license is valid
        if (!$this->check_license_valid()) {
            return;
        }

        // Load and initialize WooCommerce integration
        if (file_exists(AI_CHAT_SEARCH_PRO_DIR . 'includes/integrations/class-woocommerce-integration.php')) {
            require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/integrations/class-woocommerce-integration.php';
            new Listeo_AI_WooCommerce_Integration();
        }
    }
}

// Activation hook
register_activation_hook(__FILE__, function() {
    set_transient('ai_chat_search_pro_activated', true, 30);
    // Enable whitelabel option automatically
    update_option('listeo_ai_chat_whitelabel_enabled', 1);
});

// Deactivation hook - clean up scheduled events
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('listeo_ai_aggregate_monthly_stats');
});
