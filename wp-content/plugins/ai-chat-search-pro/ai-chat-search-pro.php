<?php
/**
 * Plugin Name: AI Chat & Search Pro
 * Plugin URI: https://purethemes.net/ai-chat-search-pro/
 * Description: Premium features for AI Chat & Search plugin - Unlimited post types, full conversation logs, and priority support
 * Version: 1.9.7
 * Author: PureThemes
 * Author URI: https://purethemes.net
 * Requires Plugins: ai-chat-search
 * License: GPL2
 * Text Domain: ai-chat-search-pro
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define Pro constants early
define('AI_CHAT_SEARCH_PRO_VERSION', '1.9.7');
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
    ?>
    <div class="notice notice-error">
        <p>
            <?php _e('AI Chat & Search Pro requires the free AI Chat & Search plugin to be installed and activated.', 'ai-chat-search-pro'); ?>
            <a href="<?php echo admin_url('plugin-install.php?s=ai+chat+search&tab=search'); ?>">
                <?php _e('Install AI Chat & Search', 'ai-chat-search-pro'); ?>
            </a>
        </p>
    </div>
    <?php
}

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
        load_plugin_textdomain('ai-chat-search-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');

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
                <strong><?php _e('AI Chat & Search Pro activated!', 'ai-chat-search-pro'); ?></strong>
                <?php _e('All premium features are now unlocked.', 'ai-chat-search-pro'); ?>
                <a href="<?php echo admin_url('admin.php?page=ai-chat-search'); ?>">
                    <?php _e('Go to Settings', 'ai-chat-search-pro'); ?> →
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
