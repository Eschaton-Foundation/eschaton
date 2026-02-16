<?php
/**
 * Plugin Name: AI Chat & Search
 * Plugin URI: https://purethemes.net/ai-chat-search-pro/
 * Description: AI-powered semantic search and conversational chat with natural language queries
 * Version: 1.8.6
 * Author: PureThemes
 * Author URI: https://purethemes.net
 * License: GPL2
 * Text Domain: ai-chat-search
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Define plugin constants
define("LISTEO_AI_SEARCH_VERSION", "1.8.6");
define("LISTEO_AI_SEARCH_PLUGIN_URL", plugin_dir_url(__FILE__));
define("LISTEO_AI_SEARCH_PLUGIN_PATH", plugin_dir_path(__FILE__));

/**
 * Main plugin class
 */
class Listeo_AI_Search
{
    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Search handler instance
     *
     * @var Listeo_AI_Search_Search_Handler
     */
    private $search_handler;

    /**
     * Shortcode handler instance
     *
     * @var Listeo_AI_Search_Shortcode_Handler
     */
    private $shortcode_handler;

    /**
     * Admin interface instance
     *
     * @var Listeo_AI_Search_Admin_Interface
     */
    private $admin_interface;

    /**
     * Get instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Load dependencies first
        $this->load_dependencies();

        // Initialize AJAX handlers early (before init)
        $this->search_handler = new Listeo_AI_Search_Search_Handler();

        add_action("init", [$this, "init"]);
        add_action("wp_enqueue_scripts", [$this, "enqueue_scripts"]);
        register_activation_hook(__FILE__, [$this, "activate"]);
        register_deactivation_hook(__FILE__, [$this, "deactivate"]);
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        // Register external pages CPT (Pro feature - hidden CPT for storing scraped web pages)
        register_post_type('ai_external_page', array(
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'supports' => array('title', 'editor'),
            'can_export' => false,
            'label' => __('External Pages', 'ai-chat-search'),
        ));

        // Initialize remaining components
        $this->shortcode_handler = new Listeo_AI_Search_Shortcode_Handler();
        $this->admin_interface = new Listeo_AI_Search_Admin_Interface();

        // Initialize background processor if available
        if (class_exists("Listeo_AI_Background_Processor")) {
            Listeo_AI_Background_Processor::init();
        }

        // Auto-process listings
        add_action("save_post", [$this, "process_listing_on_save"], 10, 2);

        // WP All Import integration - auto-generate embeddings during import
        if (class_exists("PMXI_Plugin") || defined("PMXI_VERSION")) {
            add_action(
                "pmxi_saved_post",
                [$this, "process_wpallimport_post"],
                10,
                3,
            );
        }

        // Run upgrade check only on plugin settings page (handles plugin updates)
        if (
            is_admin() &&
            isset($_GET["page"]) &&
            $_GET["page"] === "ai-chat-search"
        ) {
            $this->maybe_upgrade();
        }
    }

    /**
     * Check if plugin was updated and run necessary upgrades
     * This handles cases where plugin is updated (activation hook doesn't fire)
     */
    private function maybe_upgrade()
    {
        $installed_version = get_option("listeo_ai_search_version", "0");

        // If version changed or never set, run upgrades
        if (
            version_compare($installed_version, LISTEO_AI_SEARCH_VERSION, "<")
        ) {
            // Ensure contact messages table exists (added in 1.6.0)
            if (class_exists("Listeo_AI_Search_Contact_Messages")) {
                Listeo_AI_Search_Contact_Messages::create_table();
            }

            // Ensure chat history table and columns are up to date (ip_address added in 1.6.5)
            if (class_exists("Listeo_AI_Search_Chat_History")) {
                Listeo_AI_Search_Chat_History::create_table();
            }

            // Update stored version
            update_option("listeo_ai_search_version", LISTEO_AI_SEARCH_VERSION);
        }
    }

    /**
     * Load all required class files
     */
    private function load_dependencies()
    {
        // PDF post type (loads early)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-pdf-post-type.php";

        // Content chunker (for long posts/pages - loads early)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-content-chunker.php";

        // Content extractors (must load before embedding manager)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-factory.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-listing.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-post.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-pdf.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-chunk.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-default.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-null.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/content-extractors/class-content-extractor-external-page.php";
        // Note: Page and Product extractors moved to Pro plugin

        // Core utility classes
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-utility-helper.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-result-formatter.php";

        // Pro features manager (loads first to provide hooks)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-pro-manager.php";

        // AI Provider abstraction layer (OpenAI/Gemini)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-ai-provider.php";

        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-embedding-manager.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-database-manager.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-analytics.php";

        // Search engines
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/search/class-fallback-engine.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/search/class-ai-engine.php";

        // Main handlers
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-search-handler.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/frontend/class-shortcode-handler.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/admin/class-admin-interface.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/admin/class-universal-settings.php";
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-listeo-field-integration.php";

        // Chat API for conversational search
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-chat-api.php";

        // Chat history tracking
        // Load from free version unless Pro has already loaded it
        if (!class_exists("Listeo_AI_Search_Chat_History")) {
            require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
                "includes/class-chat-history.php";
        }

        // Chat shortcode
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-chat-shortcode.php";

        // Floating chat widget
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-floating-chat-widget.php";

        // Contact form handler
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-contact-form.php";

        // Contact messages logger
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-contact-messages.php";

        // Listeo integration (conditional - only loads if Listeo theme/core active)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-listeo-detection.php";

        if (
            file_exists(
                LISTEO_AI_SEARCH_PLUGIN_PATH .
                    "includes/integrations/class-listeo-integration.php",
            )
        ) {
            require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
                "includes/integrations/class-listeo-integration.php";
            // Initialize Listeo integration if available
            if (Listeo_AI_Detection::is_listeo_available()) {
                new Listeo_AI_Integration();
            }
        }

        // WooCommerce integration (Pro feature - moved to Pro plugin)
        // Pro plugin hooks into 'listeo_ai_woocommerce_integration' to provide this
        add_action(
            "plugins_loaded",
            function () {
                if (class_exists("WooCommerce")) {
                    do_action("listeo_ai_woocommerce_integration");
                }
            },
            20,
        );

        // Background processor (existing)
        if (
            file_exists(
                LISTEO_AI_SEARCH_PLUGIN_PATH .
                    "includes/class-background-processor.php",
            )
        ) {
            require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
                "includes/class-background-processor.php";
        }

        // Plugin updater (self-hosted updates)
        require_once LISTEO_AI_SEARCH_PLUGIN_PATH .
            "includes/class-updater.php";
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            "ai-chat-search",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/search.js",
            ["jquery"],
            LISTEO_AI_SEARCH_VERSION,
            true,
        );

        // Get placeholder image from theme/core
        $placeholder_url = "";
        if (function_exists("get_listeo_core_placeholder_image")) {
            $placeholder = get_listeo_core_placeholder_image();
            if (is_numeric($placeholder)) {
                $placeholder_img = wp_get_attachment_image_src(
                    $placeholder,
                    "medium",
                );
                if ($placeholder_img && isset($placeholder_img[0])) {
                    $placeholder_url = $placeholder_img[0];
                }
            } else {
                $placeholder_url = $placeholder;
            }
        }

        // Fallback to theme customizer
        if (empty($placeholder_url)) {
            $placeholder_id = get_theme_mod("listeo_placeholder_id");
            if ($placeholder_id) {
                $placeholder_img = wp_get_attachment_image_src(
                    $placeholder_id,
                    "medium",
                );
                if ($placeholder_img && isset($placeholder_img[0])) {
                    $placeholder_url = $placeholder_img[0];
                }
            }
        }

        wp_localize_script("ai-chat-search", "listeoAiSearch", [
            "ajax_url" => get_admin_url(
                get_current_blog_id(),
                "admin-ajax.php",
            ),
            "nonce" => wp_create_nonce("listeo_ai_search_nonce"),
            "debugMode" => (bool) get_option(
                "listeo_ai_search_debug_mode",
                false,
            ), // Debug mode from settings
            "ai_enabled" => true, // AI search is always enabled
            "max_results" => intval(
                get_option("listeo_ai_search_max_results", 10),
            ),
            "search_url" => get_post_type_archive_link("listing") ?: home_url(), // Use proper archive URL or fallback to home
            "default_thumbnail" => $placeholder_url, // Theme placeholder image
            "strings" => [
                "searching" => __("Searching...", "ai-chat-search"),
                "no_results" => __("No results found.", "ai-chat-search"),
                "error" => __("Search error occurred.", "ai-chat-search"),
                "best_match" => __("Best Match", "ai-chat-search"),
                "type_keywords_first" => __(
                    "Type keywords first",
                    "ai-chat-search",
                ),
                "top_listing_singular" => __(
                    "Top 1 listing matching",
                    "ai-chat-search",
                ),
                "top_listings_plural" => __(
                    "Top %d listings matching",
                    "ai-chat-search",
                ),
                // Error messages for search
                "rateLimitError" => __(
                    "Too many searches. Please wait a moment and try again.",
                    "ai-chat-search",
                ),
                "apiUnavailable" => __(
                    "AI search temporarily unavailable. Using basic search instead.",
                    "ai-chat-search",
                ),
                "sessionExpired" => __(
                    "Session expired. Please refresh the page.",
                    "ai-chat-search",
                ),
                "fallbackNotice" => __(
                    "Showing regular search results instead.",
                    "ai-chat-search",
                ),
                // Stock status
                "inStock" => __("In Stock", "ai-chat-search"),
                "outOfStock" => __("Out of Stock", "ai-chat-search"),
            ],
        ]);

        wp_enqueue_style(
            "ai-chat-search",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/search.css",
            [],
            LISTEO_AI_SEARCH_VERSION,
        );
    }

    /**
     * Process listing on save
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function process_listing_on_save($post_id, $post)
    {
        Listeo_AI_Search_Database_Manager::process_listing_on_save(
            $post_id,
            $post,
        );
    }

    /**
     * Process post imported via WP All Import
     * Generates embedding immediately, bypassing throttle
     *
     * @param int $post_id Post ID
     * @param object $xml_node XML node data
     * @param bool $is_update Whether this is an update
     */
    public function process_wpallimport_post($post_id, $xml_node, $is_update)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_status !== "publish") {
            return;
        }

        // Check if post type is enabled for embeddings
        $enabled_types = Listeo_AI_Search_Database_Manager::get_enabled_post_types();
        if (!in_array($post->post_type, $enabled_types)) {
            return;
        }

        // Remove throttle to allow immediate embedding generation
        delete_transient("listeo_ai_last_embedding_" . $post_id);

        // Generate embedding
        Listeo_AI_Search_Database_Manager::generate_single_embedding($post_id);
    }

    /**
     * Custom debug logging to debug.log
     *
     * @param string $message Log message
     * @param string $level Log level (info, error, warning, debug)
     */
    public static function debug_log($message, $level = "info")
    {
        // Only log if debug mode is explicitly enabled (must be truthy value like 1 or '1')
        $debug_mode = get_option("listeo_ai_search_debug_mode", 0);
        if (empty($debug_mode) || $debug_mode === "0") {
            return;
        }

        // Use WordPress standard debug logging
        $timestamp = date("Y-m-d H:i:s");
        $formatted_message = "[{$timestamp}] [{$level}] AI Chat: {$message}";

        error_log($formatted_message);
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Initialize default settings (only if not already set)
        $this->initialize_default_settings();

        // Create database tables
        Listeo_AI_Search_Database_Manager::create_tables();

        // Create chat history table (only if feature is enabled)
        if (get_option("listeo_ai_chat_history_enabled", 0)) {
            Listeo_AI_Search_Chat_History::create_table();
        }

        // Create contact messages table
        Listeo_AI_Search_Contact_Messages::create_table();

        // Embeddings are generated:
        // 1. Manually via "Start Training" button in admin
        // 2. Automatically when individual posts are published/updated (via save_post hook)

        // Schedule weekly chat history cleanup (runs every 7 days)
        if (!wp_next_scheduled("listeo_ai_cleanup_chat_history")) {
            wp_schedule_event(
                time(),
                "weekly",
                "listeo_ai_cleanup_chat_history",
            );
        }

        // Schedule yearly contact messages cleanup
        if (!wp_next_scheduled("listeo_ai_cleanup_contact_messages")) {
            wp_schedule_event(
                time(),
                "weekly",
                "listeo_ai_cleanup_contact_messages",
            );
        }

        // Store plugin version for upgrade checks
        update_option("listeo_ai_search_version", LISTEO_AI_SEARCH_VERSION);

        // Auto-install translation on first install only
        $this->maybe_auto_install_translation();
    }

    /**
     * Automatically install translation files on first plugin install
     *
     * This method is designed to be completely safe and non-breaking:
     * - Only runs on first install (not updates)
     * - Skips English locales
     * - Silently fails if translation unavailable or download fails
     * - Checks for required WordPress functions before attempting
     *
     * @since 1.8.3
     * @return void
     */
    private function maybe_auto_install_translation()
    {
        // Only run on first install - check if we've already attempted this
        if (get_option('listeo_ai_search_translation_auto_attempted', false)) {
            return;
        }

        // Mark as attempted immediately to prevent retries
        update_option('listeo_ai_search_translation_auto_attempted', true);

        // Get WordPress locale
        $locale = get_locale();

        // Skip English locales - no translation needed
        if (empty($locale) || strpos($locale, 'en_') === 0 || $locale === 'en') {
            return;
        }

        // Check if required functions exist
        if (!function_exists('wp_remote_head') || !function_exists('wp_remote_get') || !function_exists('download_url')) {
            return;
        }

        // Check if translation file already exists
        $mo_file = WP_LANG_DIR . '/plugins/ai-chat-search-' . $locale . '.mo';
        if (file_exists($mo_file)) {
            return;
        }

        // Check if translation is available on server
        $base_url = 'https://purethemes.net/listeo-theme-translations/';
        $check_url = $base_url . 'mo/ai-chat-search-' . $locale . '.mo';

        $response = wp_remote_head($check_url, array(
            'timeout' => 5,
            'sslverify' => true,
        ));

        // If translation not available, silently exit
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return;
        }

        // Try to download and install translation
        try {
            // Load required WordPress file functions - check file exists first
            $file_php = ABSPATH . 'wp-admin/includes/file.php';
            if (!function_exists('WP_Filesystem')) {
                if (!file_exists($file_php)) {
                    return;
                }
                require_once $file_php;
            }

            // Verify required functions are now available
            if (!function_exists('WP_Filesystem') || !function_exists('request_filesystem_credentials')) {
                return;
            }

            // Initialize filesystem - if it fails or requires credentials, skip
            $creds = request_filesystem_credentials('', '', false, false, array());
            if ($creds === false || !WP_Filesystem($creds)) {
                return;
            }

            global $wp_filesystem;
            if (!$wp_filesystem) {
                return;
            }

            $dest_dir = trailingslashit(WP_LANG_DIR) . 'plugins/';

            // Create directory if it doesn't exist
            if (!$wp_filesystem->is_dir($dest_dir)) {
                if (!$wp_filesystem->mkdir($dest_dir, FS_CHMOD_DIR)) {
                    return;
                }
            }

            // Download and install .mo file (essential for translations)
            $mo_url = $base_url . 'mo/ai-chat-search-' . $locale . '.mo';
            $temp_file = download_url($mo_url, 15);

            if (is_wp_error($temp_file)) {
                return;
            }

            $dest_path = $dest_dir . 'ai-chat-search-' . $locale . '.mo';
            if (!$wp_filesystem->move($temp_file, $dest_path, true)) {
                $wp_filesystem->delete($temp_file);
                return;
            }

            // Optionally try to download .po file too (not critical)
            $po_url = $base_url . 'po/ai-chat-search-' . $locale . '.po';
            $temp_po = download_url($po_url, 10);
            if (!is_wp_error($temp_po)) {
                $po_dest = $dest_dir . 'ai-chat-search-' . $locale . '.po';
                if (!$wp_filesystem->move($temp_po, $po_dest, true)) {
                    $wp_filesystem->delete($temp_po);
                }
            }

            // Log success if debug mode is on
            self::debug_log("Auto-installed translation for locale: {$locale}");

        } catch (Exception $e) {
            // Silently fail - translation is not critical
            self::debug_log("Auto-translation install failed: " . $e->getMessage(), 'warning');
        }
    }

    /**
     * Get default settings array
     * Centralized defaults for consistency across plugin
     *
     * @return array Default settings
     */
    public static function get_default_settings()
    {
        return [
            // API Provider settings
            "listeo_ai_search_provider" => "openai",

            // Search settings
            "listeo_ai_search_min_match_percentage" => 50,
            "listeo_ai_search_best_match_threshold" => 75,
            "listeo_ai_search_max_results" => 10,
            "listeo_ai_search_rate_limit_per_hour" => 200,
            // batch_size removed - now auto-detected (5k threshold, 3k batch)
            "listeo_ai_search_embedding_delay" => 5,

            // Chat settings
            "listeo_ai_chat_enabled" => 1,
            "listeo_ai_chat_name" => __("AI Assistant", "ai-chat-search"),
            "listeo_ai_chat_welcome_message" => __(
                "Hello! How can I help you today?",
                "ai-chat-search",
            ),
            "listeo_ai_chat_system_prompt" => "",
            "listeo_ai_chat_model" => "gpt-5.1",
            "listeo_ai_chat_max_results" => 10,
            "listeo_ai_chat_rag_sources_limit" => 5,
            "listeo_ai_chat_hide_images" => 0,
            "listeo_ai_chat_require_login" => 0,
            "listeo_ai_chat_history_enabled" => 1,
            "listeo_ai_chat_retention_days" => 30,
            "listeo_ai_chat_terms_notice_enabled" => 0,
            "listeo_ai_chat_terms_notice_text" => "",
            "listeo_ai_chat_rate_limit_tier1" => 10,
            "listeo_ai_chat_rate_limit_tier2" => 30,
            "listeo_ai_chat_rate_limit_tier3" => 100,

            // Contact form tool settings
            "listeo_ai_contact_form_examples" =>
                "EXAMPLES OF WHEN TO USE:\n- \"Can you send a message to the site owner for me?\"\n- \"I want to contact support about X\"\n- \"Please send them my inquiry about Y\"\n\nEXAMPLES OF WHEN NOT TO USE:\n- \"How can I contact you?\" (just provide contact info)\n- \"What's your email?\" (just provide info, don't send)",

            // Floating widget settings
            "listeo_ai_floating_chat_enabled" => 1,
            "listeo_ai_floating_button_icon" => "default",
            "listeo_ai_floating_custom_icon" => 0,
            "listeo_ai_floating_welcome_bubble" => __(
                "Hi! How can I help you?",
                "ai-chat-search",
            ),
            "listeo_ai_floating_popup_width" => 390,
            "listeo_ai_floating_popup_height" => 600,
            "listeo_ai_floating_button_color" => "#222222",
            "listeo_ai_primary_color" => "#0073ee",
            "listeo_ai_floating_excluded_pages" => [],
            "listeo_ai_chat_quick_buttons_enabled" => 1,
            "listeo_ai_chat_quick_buttons_visibility" => "always",
            "listeo_ai_chat_quick_buttons" => [],

            // Checkbox settings
            "listeo_ai_search_ai_location_filtering_enabled" => 0,
            "listeo_ai_search_debug_mode" => 0,
            "listeo_ai_search_query_expansion" => 0,
            "listeo_ai_search_enable_analytics" => 1,
            "listeo_ai_search_suggestions_enabled" => 0,
        ];
    }

    /**
     * Initialize default settings
     * Only sets values if they don't exist in database
     */
    private function initialize_default_settings()
    {
        $defaults = self::get_default_settings();

        foreach ($defaults as $option_name => $default_value) {
            // Only add if option doesn't exist (won't overwrite existing values)
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clean up scheduled events
        wp_clear_scheduled_hook("listeo_ai_bulk_process_listings");
        wp_clear_scheduled_hook("listeo_ai_process_listing");
        wp_clear_scheduled_hook("listeo_ai_cleanup_chat_history");
        wp_clear_scheduled_hook("listeo_ai_cleanup_contact_messages");
    }
}

/**
 * Get chat strings for localization (shared by shortcode and floating widget)
 * Centralized in one place to avoid duplication
 *
 * @param string $welcome_message Custom welcome message
 * @return array Localized strings
 */
function listeo_ai_get_chat_strings($welcome_message = "")
{
    if (empty($welcome_message)) {
        $welcome_message = __(
            "Hello! How can I help you today?",
            "ai-chat-search",
        );
    }

    return [
        "placeholder" => __("Ask about listings...", "ai-chat-search"),
        "sendButton" => __("Send", "ai-chat-search"),
        "welcomeMessage" => $welcome_message,
        "loading" => __("Thinking...", "ai-chat-search"),
        "loadingConfig" => __(
            "Please wait, loading configuration...",
            "ai-chat-search",
        ),
        "gettingDetails" => __("Getting listing details...", "ai-chat-search"),
        "searchingDatabase" => __("Thinking...", "ai-chat-search"),
        "analyzingResults" => __("Analyzing...", "ai-chat-search"),
        "generatingAnswer" => __("Thinking...", "ai-chat-search"),
        "analyzingListing" => __("Analyzing listing...", "ai-chat-search"),
        "loadingButton" => __("Loading...", "ai-chat-search"),
        "talkAboutListing" => __("Talk about this listing", "ai-chat-search"),
        "listingNotFound" => __(
            'Sorry, I couldn\'t find details for that listing.',
            "ai-chat-search",
        ),
        "errorGettingDetails" => __(
            "Error getting listing details.",
            "ai-chat-search",
        ),
        "errorLoadingListing" => __("Error loading listing.", "ai-chat-search"),
        "failedLoadDetails" => __(
            "Failed to load listing details.",
            "ai-chat-search",
        ),
        "errorApiKey" => __(
            "Please add API key in plugin settings.",
            "ai-chat-search",
        ),
        "apiNotConfigured" => __(
            "⚠️ Hey, to start using the chatbot <strong>please add OpenAI or Gemini API key</strong> in plugin settings!",
            "ai-chat-search",
        ),
        "errorConfig" => __(
            "Failed to load chat configuration. Please try again later.",
            "ai-chat-search",
        ),
        "errorGeneral" => __(
            "Sorry, an error occurred. Please try again.",
            "ai-chat-search",
        ),
        "chatDisabled" => __(
            "AI Chat is currently disabled.",
            "ai-chat-search",
        ),
        "bestMatch" => __("Best Match", "ai-chat-search"),
        "showMore" => __("Show more (%d)", "ai-chat-search"),
        "listingContextLoaded" => __(
            "Listing context loaded! You can now ask me anything about",
            "ai-chat-search",
        ),
        // Product context strings (WooCommerce)
        "talkAboutProduct" => __("Talk about this product", "ai-chat-search"),
        "productContextLoaded" => __(
            "Product context loaded! You can now ask me anything about",
            "ai-chat-search",
        ),
        "errorLoadingProduct" => __("Error loading product.", "ai-chat-search"),
        "failedLoadProductDetails" => __(
            "Failed to load product details.",
            "ai-chat-search",
        ),
        // Chat loading states
        "searchingListings" => __("Searching listings...", "ai-chat-search"),
        "searchingProducts" => __("Searching products...", "ai-chat-search"),
        "searchingSiteContent" => __(
            "Searching site content...",
            "ai-chat-search",
        ),
        "analyzingProducts" => __("Analyzing products...", "ai-chat-search"),
        "gettingProductDetails" => __(
            "Getting product details...",
            "ai-chat-search",
        ),
        "analyzingProduct" => __("Analyzing product...", "ai-chat-search"),
        "comparingListings" => __("Comparing listings...", "ai-chat-search"),
        "comparingProducts" => __("Comparing products...", "ai-chat-search"),
        "analyzingContent" => __("Analyzing content...", "ai-chat-search"),
        // Order status messages
        "checkingOrderStatus" => __(
            "Checking order status...",
            "ai-chat-search",
        ),
        "analyzingOrderDetails" => __(
            "Analyzing order details...",
            "ai-chat-search",
        ),
        // Contact form messages
        "sendingMessage" => __("Sending message...", "ai-chat-search"),
        "orderNotFound" => __(
            "Order not found. Please check the order number and try again.",
            "ai-chat-search",
        ),
        "orderVerificationRequired" => __(
            "Please provide your billing email to verify the order.",
            "ai-chat-search",
        ),
        "errorGettingOrder" => __(
            "Unable to retrieve order status. Please try again later.",
            "ai-chat-search",
        ),
        // Error messages
        "searchFailed" => __(
            "Search failed. Please try again.",
            "ai-chat-search",
        ),
        "productSearchFailed" => __(
            "Product search failed. Please try again.",
            "ai-chat-search",
        ),
        "unknownFunction" => __(
            "Unknown function requested.",
            "ai-chat-search",
        ),
        "contentNotFound" => __(
            "Content not found or not published.",
            "ai-chat-search",
        ),
        "errorGettingContent" => __(
            "Error getting content details.",
            "ai-chat-search",
        ),
        "productNotFound" => __(
            "Product not found or not published.",
            "ai-chat-search",
        ),
        "errorGettingProduct" => __(
            "Error getting product details.",
            "ai-chat-search",
        ),
        // Detailed error messages for diagnostics
        "errorNetwork" => __(
            "Network error - request could not be sent. Please check your connection.",
            "ai-chat-search",
        ),
        "errorConnection" => __(
            "Connection was interrupted. Please try again.",
            "ai-chat-search",
        ),
        "errorTimeout" => __(
            "Request timed out. Please try again.",
            "ai-chat-search",
        ),
        "errorRateLimit" => __(
            "Too many requests. Please wait a moment and try again.",
            "ai-chat-search",
        ),
        "errorServer" => __(
            "Server error occurred. Please try again.",
            "ai-chat-search",
        ),
        // No results messages
        "thinkingAboutQuery" => __("Thinking...", "ai-chat-search"),
        "noResultsGeneric" => __(
            'I couldn\'t find results matching your search. Try different keywords or be more specific about what you\'re looking for.',
            "ai-chat-search",
        ),
        // Rate limit messages
        "rateLimitPrefix" => __(
            'You\'ve reached the limit of',
            "ai-chat-search",
        ),
        "rateLimitSuffix" => __("messages per", "ai-chat-search"),
        "rateLimitWait" => __("Please wait", "ai-chat-search"),
        "rateLimitBeforeTrying" => __("before trying again.", "ai-chat-search"),
        "minute" => __("minute", "ai-chat-search"),
        "minutes" => __("minutes", "ai-chat-search"),
        "hour" => __("hour", "ai-chat-search"),
        "hours" => __("hours", "ai-chat-search"),
        "second" => __("second", "ai-chat-search"),
        "seconds" => __("seconds", "ai-chat-search"),
        // Stock status
        "inStock" => __("In Stock", "ai-chat-search"),
        "outOfStock" => __("Out of Stock", "ai-chat-search"),
        // Contact form
        "contactFormFillAll" => __(
            "Please fill in all fields.",
            "ai-chat-search",
        ),
        "contactFormSent" => __("Message sent successfully!", "ai-chat-search"),
        "contactFormError" => __(
            "Failed to send message. Please try again.",
            "ai-chat-search",
        ),
        // Speech-to-text (PRO feature)
        "micStartRecording" => __("Recording...", "ai-chat-search"),
        "micStopRecording" => __("Processing...", "ai-chat-search"),
        "micAccessDenied" => __(
            "Microphone access denied. Please allow microphone access in your browser settings.",
            "ai-chat-search",
        ),
        "micNotSupported" => __(
            "Speech-to-text is not supported in your browser.",
            "ai-chat-search",
        ),
        "micNoSSL" => __("Not available without SSL", "ai-chat-search"),
        "audioTooLarge" => __(
            "Recording is too long. Please try a shorter message.",
            "ai-chat-search",
        ),
        "transcriptionFailed" => __(
            "Could not transcribe audio. Please try again.",
            "ai-chat-search",
        ),
        "speechNotAvailable" => __(
            "Speech-to-text requires PRO version.",
            "ai-chat-search",
        ),
        // Image input
        "imageTooLarge" => __(
            "Image is too large. Maximum size is 4MB.",
            "ai-chat-search",
        ),
        "imageResolutionTooLarge" => __(
            "Image resolution is too large. Maximum is 3000x3000 pixels.",
            "ai-chat-search",
        ),
        "imageInvalidFormat" => __(
            "Invalid image format. Allowed: JPEG, PNG, GIF, WebP.",
            "ai-chat-search",
        ),
        "imageAttached" => __("[Image attached]", "ai-chat-search"),
        "analyzingImage" => __("Analyzing image...", "ai-chat-search"),
    ];
}

/**
 * Get chat JavaScript configuration (shared by shortcode and floating widget)
 * Centralizes all config to avoid duplication and eliminate /chat-config API call
 *
 * @return array Configuration array for wp_localize_script
 */
function listeo_ai_get_chat_js_config()
{
    // Get placeholder image from Listeo Core or theme customizer
    $placeholder_url = "";
    if (function_exists("get_listeo_core_placeholder_image")) {
        $placeholder = get_listeo_core_placeholder_image();
        // Function returns either attachment ID (numeric) or URL (string)
        if (is_numeric($placeholder)) {
            $placeholder_img = wp_get_attachment_image_src($placeholder, "medium");
            if ($placeholder_img && isset($placeholder_img[0])) {
                $placeholder_url = $placeholder_img[0];
            }
        } else {
            $placeholder_url = $placeholder;
        }
    }
    // Fallback to theme customizer if Listeo Core not available or returned empty
    if (empty($placeholder_url)) {
        $placeholder_id = get_theme_mod("listeo_placeholder_id");
        if ($placeholder_id) {
            $placeholder_img = wp_get_attachment_image_src($placeholder_id, "medium");
            if ($placeholder_img && isset($placeholder_img[0])) {
                $placeholder_url = $placeholder_img[0];
            }
        }
    }

    // Get widget settings
    $chat_name = get_option(
        "listeo_ai_chat_name",
        __("AI Assistant", "ai-chat-search")
    );
    $welcome_message = get_option(
        "listeo_ai_chat_welcome_message",
        __("Hello! How can I help you today?", "ai-chat-search")
    );

    // Get chat avatar URL
    $chat_avatar_id = intval(get_option("listeo_ai_chat_avatar", 0));
    $chat_avatar_url = $chat_avatar_id
        ? wp_get_attachment_image_url($chat_avatar_id, "thumbnail")
        : "";

    // Get chat config from Chat_API (includes enabled, model, listeo_available, tools, api_configured)
    $chat_config = [];
    if (class_exists("Listeo_AI_Search_Chat_API")) {
        $chat_config = Listeo_AI_Search_Chat_API::get_chat_config();
        // Remove system_prompt - it's sensitive and handled server-side
        unset($chat_config["system_prompt"]);
    }

    return [
        // API settings
        "apiBase" => esc_url(rest_url("listeo/v1")),
        "nonce" => wp_create_nonce("wp_rest"),
        "isLoggedIn" => is_user_logged_in(),

        // Debug mode
        "debugMode" => (bool) get_option("listeo_ai_search_debug_mode", false),

        // UI settings
        "placeholderImage" => esc_url($placeholder_url),
        "chatName" => esc_html($chat_name),
        "chatAvatarUrl" => esc_url($chat_avatar_url),
        "hideImages" => get_option("listeo_ai_chat_hide_images", 1),
        "loadingStyle" => get_option("listeo_ai_chat_loading_style", "spinner"),
        "typingAnimation" => (bool) get_option("listeo_ai_chat_typing_animation", 1),
        "colorScheme" => get_option("listeo_ai_color_scheme", "light"),
        "ragSourcesLimit" => max(2, intval(get_option("listeo_ai_chat_rag_sources_limit", 5))),
        "imageInputEnabled" => class_exists("AI_Chat_Search_Pro_Manager")
            && AI_Chat_Search_Pro_Manager::is_pro_active()
            && (bool) get_option("listeo_ai_chat_enable_image_input", 0),
        "hasImageHeader" => in_array(get_option("listeo_ai_floating_header_style", "simple"), ["image", "animated"]),
        "hasImageHeaderOverlay" => (get_option("listeo_ai_floating_header_style", "simple") === "animated")
            || (get_option("listeo_ai_floating_header_style", "simple") === "image"
            && (bool) get_option("listeo_ai_floating_header_overlay", 0)),
        "hasAnimatedHeader" => (get_option("listeo_ai_floating_header_style", "simple") === "animated"),
        "animatedBgColor" => sanitize_hex_color(get_option("listeo_ai_animated_bg_color", "#1560d0")) ?: "#1560d0",

        // Quick buttons visibility
        "quickButtonsVisibility" => get_option("listeo_ai_chat_quick_buttons_visibility", "always"),

        // Rate limits
        "rateLimits" => [
            "tier1" => intval(get_option("listeo_ai_chat_rate_limit_tier1", 10)),
            "tier2" => intval(get_option("listeo_ai_chat_rate_limit_tier2", 30)),
            "tier3" => intval(get_option("listeo_ai_chat_rate_limit_tier3", 100)),
        ],

        // Localized strings
        "strings" => listeo_ai_get_chat_strings($welcome_message),

        // Chat config (previously fetched via /chat-config API)
        // Now included inline to eliminate extra HTTP request
        "chatConfig" => $chat_config,
    ];
}

// Initialize plugin
Listeo_AI_Search::get_instance();

/**
 * Chat history cleanup cron job
 * Runs weekly to delete old chat records based on retention setting
 */
add_action("listeo_ai_cleanup_chat_history", function () {
    if (!class_exists("Listeo_AI_Search_Chat_History")) {
        return;
    }

    // Get retention days from settings (default: 30 days)
    $retention_days = get_option("listeo_ai_chat_retention_days", 30);

    // Run cleanup
    $deleted = Listeo_AI_Search_Chat_History::cleanup_old_records(
        $retention_days,
    );

    // Log if debug mode enabled
    if (
        get_option("listeo_ai_search_debug_mode", false) &&
        $deleted !== false
    ) {
        Listeo_AI_Search::debug_log(
            "Chat history cleanup: Deleted {$deleted} records older than {$retention_days} days",
            "info",
        );
    }
});

/**
 * Contact messages cleanup cron job
 * Runs weekly to delete old contact messages (older than 1 year)
 */
add_action("listeo_ai_cleanup_contact_messages", function () {
    if (!class_exists("Listeo_AI_Search_Contact_Messages")) {
        return;
    }

    // Delete messages older than 365 days
    $deleted = Listeo_AI_Search_Contact_Messages::cleanup_old_messages(365);

    // Log if debug mode enabled
    if (
        get_option("listeo_ai_search_debug_mode", false) &&
        $deleted !== false
    ) {
        Listeo_AI_Search::debug_log(
            "Contact messages cleanup: Deleted {$deleted} records older than 365 days",
            "info",
        );
    }
});
