<?php
/**
 * Listeo AI Floating Chat Widget
 *
 * Floating chat button and popup that appears on all pages
 *
 * @package Listeo_AI_Search
 * @since 1.0.0
 */

if (!defined("ABSPATH")) {
    exit();
}

class Listeo_AI_Search_Floating_Chat_Widget
{
    /**
     * Constructor
     */
    public function __construct()
    {
        add_action("wp_footer", [$this, "render_floating_widget"]);
        add_action("wp_enqueue_scripts", [$this, "enqueue_widget_assets"]);
    }

    /**
     * Enqueue widget assets
     */
    public function enqueue_widget_assets()
    {
        // Only load if widget is enabled
        if (!get_option("listeo_ai_floating_chat_enabled", 0)) {
            return;
        }

        // Check if login is required and user is not logged in
        if (
            get_option("listeo_ai_chat_require_login", 0) &&
            !is_user_logged_in()
        ) {
            return;
        }

        // Check if current page is in the exclusion list
        $excluded_pages = get_option("listeo_ai_floating_excluded_pages", []);
        if (!empty($excluded_pages) && is_array($excluded_pages) && is_page()) {
            $current_page_id = get_the_ID();
            if (in_array($current_page_id, $excluded_pages)) {
                return;
            }
        }

        // Check if current IP is blocked (PRO feature)
        if (apply_filters('listeo_ai_chat_should_block_ip', false)) {
            return;
        }

        // Enqueue chat styles (reuse from shortcode)
        wp_enqueue_style(
            "listeo-ai-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/chatbot.css",
            [],
            LISTEO_AI_SEARCH_VERSION,
        );

        // Enqueue dark mode styles
        wp_enqueue_style(
            "listeo-ai-chat-dark-mode",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/chatbot-dark-mode.css",
            ["listeo-ai-chat"],
            LISTEO_AI_SEARCH_VERSION,
        );

        // Enqueue dark mode JS only for auto mode
        if (get_option('listeo_ai_color_scheme', 'light') === 'auto') {
            wp_enqueue_script(
                "listeo-ai-chat-dark-mode",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/chatbot-dark-mode.js",
                [],
                LISTEO_AI_SEARCH_VERSION,
                false // Load in head for immediate execution
            );
        }

        // Enqueue theme switcher JS when enabled
        if (get_option('listeo_ai_color_scheme_switcher')) {
            wp_enqueue_script(
                "listeo-ai-chat-theme-switcher",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/chatbot-theme-switcher.js",
                ["jquery"],
                LISTEO_AI_SEARCH_VERSION,
                true
            );
        }

        // Enqueue floating widget styles
        wp_enqueue_style(
            "listeo-ai-floating-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/css/floating-chat.css",
            ["listeo-ai-chat"],
            LISTEO_AI_SEARCH_VERSION,
        );

        // Enqueue chat script (reuse from shortcode)
        wp_enqueue_script(
            "listeo-ai-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/chatbot-core.js",
            ["jquery"],
            LISTEO_AI_SEARCH_VERSION,
            true,
        );

        // Enqueue silk wave animated background (only when needed)
        if (get_option('listeo_ai_floating_header_style', 'simple') === 'animated') {
            wp_enqueue_script(
                "listeo-silk-wave-bg",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/silk-wave-bg.js",
                [],
                LISTEO_AI_SEARCH_VERSION,
                true,
            );
        }

        // Enqueue floating widget script
        wp_enqueue_script(
            "listeo-ai-floating-chat",
            LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/floating-chat.js",
            ["jquery", "listeo-ai-chat"],
            LISTEO_AI_SEARCH_VERSION,
            true,
        );

        // Load UI utilities only when badge is visible (whitelabel not enabled)
        $is_pro = AI_Chat_Search_Pro_Manager::is_pro_active();
        $whitelabel_enabled = $is_pro && get_option('listeo_ai_chat_whitelabel_enabled', 0);
        if (!$whitelabel_enabled) {
            wp_enqueue_script(
                "listeo-ai-chat-ui-utils",
                LISTEO_AI_SEARCH_PLUGIN_URL . "assets/js/chat-ui-utils.js",
                ["jquery", "listeo-ai-chat"],
                LISTEO_AI_SEARCH_VERSION,
                true,
            );
        }

        // Use shared function for chat config (eliminates duplication with shortcode)
        // This also includes chatConfig inline, eliminating the /chat-config API call
        wp_localize_script("listeo-ai-chat", "listeoAiChatConfig", listeo_ai_get_chat_js_config());

        // Get welcome bubble message for floating widget
        $welcome_bubble_message = get_option(
            "listeo_ai_floating_welcome_bubble",
            __("Hi! How can I help you?", "ai-chat-search"),
        );

        // Localize script for floating widget
        wp_localize_script(
            "listeo-ai-floating-chat",
            "listeoAiFloatingChatConfig",
            [
                "welcomeBubbleMessage" => $welcome_bubble_message,
                "buttonIcon" => get_option(
                    "listeo_ai_floating_button_icon",
                    "fa-robot",
                ),
                "strings" => [
                    "openChat" => __("Open chat", "ai-chat-search"),
                    "closeChat" => __("Close chat", "ai-chat-search"),
                ],
            ],
        );

        // Speech-to-text assets hook (PRO feature)
        if (AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_speech', 0)) {
            do_action('listeo_ai_chat_enqueue_speech_assets');
        }
    }

    /**
     * Get placeholder image URL
     */
    private function get_placeholder_image()
    {
        $placeholder_url = "";

        // Try listeo-core function
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

        return $placeholder_url;
    }

    /**
     * Render floating widget HTML
     */
    public function render_floating_widget()
    {
        // Only render if widget is enabled
        if (!get_option("listeo_ai_floating_chat_enabled", 0)) {
            return;
        }

        // Check if chat is enabled
        if (!get_option("listeo_ai_chat_enabled", 0)) {
            return;
        }

        // Check if login is required and user is not logged in
        if (
            get_option("listeo_ai_chat_require_login", 0) &&
            !is_user_logged_in()
        ) {
            return;
        }

        // Check if current page is in the exclusion list
        $excluded_pages = get_option("listeo_ai_floating_excluded_pages", []);
        if (!empty($excluded_pages) && is_array($excluded_pages) && is_page()) {
            $current_page_id = get_the_ID();
            if (in_array($current_page_id, $excluded_pages)) {
                return;
            }
        }

        // Check if current IP is blocked (PRO feature)
        if (apply_filters('listeo_ai_chat_should_block_ip', false)) {
            return;
        }

        // Get settings
        $chat_title = get_option(
            "listeo_ai_chat_name",
            __("AI Assistant", "ai-chat-search"),
        );
        $placeholder = __("Type a message", "ai-chat-search");
        $custom_icon_id = intval(
            get_option("listeo_ai_floating_custom_icon", 0),
        );

        // Get chat avatar
        $chat_avatar_id = intval(get_option("listeo_ai_chat_avatar", 0));
        $chat_avatar_url = $chat_avatar_id
            ? wp_get_attachment_image_url($chat_avatar_id, "thumbnail")
            : "";
        $welcome_bubble = get_option(
            "listeo_ai_floating_welcome_bubble",
            __("Hi! How can I help you?", "ai-chat-search"),
        );
        $popup_width = intval(
            get_option("listeo_ai_floating_popup_width", 390),
        );
        $popup_height = intval(
            get_option("listeo_ai_floating_popup_height", 600),
        );
        $hide_images = intval(get_option("listeo_ai_chat_hide_images", 1));
        $button_color = sanitize_hex_color(
            get_option("listeo_ai_floating_button_color", "#222222"),
        );
        if (empty($button_color)) {
            $button_color = "#222222"; // Fallback
        }

        // Validate dimensions
        $popup_width = max(320, min(800, $popup_width));
        $popup_height = max(400, min(900, $popup_height));

        // Get color scheme for dark mode
        $color_scheme = get_option('listeo_ai_color_scheme', 'light');

        // Get widget position
        $widget_position = get_option('listeo_ai_floating_position', 'right');

        // Get header style settings
        $header_style = get_option('listeo_ai_floating_header_style', 'simple');
        $header_bg_id = intval(get_option('listeo_ai_floating_header_bg', 0));
        $header_bg_url = $header_bg_id ? wp_get_attachment_image_url($header_bg_id, 'medium') : '';
        $use_image_header = ($header_style === 'image' || $header_style === 'animated'); // Both use expanded header
        $use_animated_header = ($header_style === 'animated');
        $has_header_bg_image = !empty($header_bg_url) && !$use_animated_header;
        $use_header_overlay = $use_animated_header || ($header_style === 'image' && get_option('listeo_ai_floating_header_overlay', 0));
        $animated_bg_color = sanitize_hex_color(get_option('listeo_ai_animated_bg_color', '#1560d0'));
        if (empty($animated_bg_color)) $animated_bg_color = '#1560d0';

        // Get custom icon URL if set
        $custom_icon_url = $custom_icon_id
            ? wp_get_attachment_image_url($custom_icon_id, "full")
            : "";
        $use_custom_icon = !empty($custom_icon_url);

        // Get primary color from settings
        $primary_color = sanitize_hex_color(
            get_option("listeo_ai_primary_color", "#0073ee"),
        );
        if (empty($primary_color)) {
            $primary_color = "#0073ee"; // Fallback
        }

        // Convert hex to RGB for light variant
        $primary_rgb = sscanf($primary_color, "#%02x%02x%02x");
        $primary_color_light = sprintf(
            "rgba(%d, %d, %d, 0.1)",
            $primary_rgb[0],
            $primary_rgb[1],
            $primary_rgb[2],
        );
        ?>
        <!-- Custom Button Color Styles -->
        <style>
            .listeo-floating-chat-button,
            .listeo-ai-chat-send-btn,
            .listeo-ai-load-listing-btn {
                background: <?php echo esc_attr($button_color); ?> !important;
            }

            /* AI Chat Primary Color Variables */
            :root {
                --ai-chat-primary-color: <?php echo esc_attr(
                    $primary_color,
                ); ?>;
                --ai-chat-primary-color-light: <?php echo esc_attr(
                    $primary_color_light,
                ); ?>;
            }
        </style>

        <!-- Floating Chat Widget -->
        <div class="listeo-floating-chat-widget<?php echo $color_scheme === 'dark' ? ' dark-mode' : ''; ?><?php echo $widget_position === 'left' ? ' position-left' : ''; ?>" id="listeo-floating-chat-widget">
        <?php if ($color_scheme === 'auto'): ?>
        <script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches){document.getElementById('listeo-floating-chat-widget').classList.add('dark-mode');}</script>
        <?php endif; ?>
        <?php if (get_option('listeo_ai_color_scheme_switcher')): ?>
        <script>(function(){var s=localStorage.getItem('listeo_ai_chat_dark_mode'),e=document.getElementById('listeo-floating-chat-widget');if(s==='dark')e.classList.add('dark-mode');else if(s==='light')e.classList.remove('dark-mode');})();</script>
        <?php endif; ?>

            <?php if (!empty(trim($welcome_bubble))) : ?>
            <!-- Welcome Bubble (shows on first visit only) -->
            <div class="listeo-floating-welcome-bubble hidden" id="listeo-floating-welcome-bubble">
                <div class="listeo-floating-welcome-bubble-content">
                    <?php echo wp_kses_post($welcome_bubble); ?>
                </div>
                <div class="listeo-floating-welcome-bubble-arrow"></div>
            </div>
            <!-- Check localStorage immediately to prevent flash -->
            <script>
                (function() {
                    var bubble = document.getElementById('listeo-floating-welcome-bubble');
                    var dismissed = localStorage.getItem('listeo_floating_chat_bubble_dismissed');
                    if (dismissed !== 'true' && bubble) {
                        bubble.classList.remove('hidden');
                    }
                })();
            </script>
            <?php endif; ?>

            <!-- Floating Button -->
            <button
                class="listeo-floating-chat-button <?php echo $use_custom_icon
                    ? "has-custom-icon"
                    : ""; ?>"
                id="listeo-floating-chat-button"
                aria-label="<?php esc_attr_e("Open chat", "ai-chat-search"); ?>"
            >
                <?php if ($use_custom_icon): ?>
                    <img src="<?php echo esc_url(
                        $custom_icon_url,
                    ); ?>" alt="Chat" class="listeo-floating-custom-icon listeo-floating-icon-open" />
                <?php else: ?>
                    <img src="<?php echo esc_url(
                        LISTEO_AI_SEARCH_PLUGIN_URL . "assets/icons/chat.svg",
                    ); ?>" alt="Chat" class="listeo-floating-icon-open" width="28" height="28" />
                <?php endif; ?>
                <img src="<?php echo esc_url(
                    LISTEO_AI_SEARCH_PLUGIN_URL . "assets/icons/close.svg",
                ); ?>" alt="Close" class="listeo-floating-icon-close" style="display: none;" width="18" height="18" />
            </button>

            <!-- Chat Popup (reuses exact shortcode HTML structure) -->
            <div class="listeo-floating-chat-popup<?php echo $use_image_header ? ' chat-image-header' : ''; ?><?php echo $use_animated_header ? ' chat-animated-header' : ''; ?><?php echo $use_header_overlay ? ' chat-image-header-overlay' : ''; ?>" id="listeo-floating-chat-popup" style="display: none; width: <?php echo esc_attr(
                $popup_width,
            ); ?>px; height: <?php echo esc_attr($popup_height); ?>px;<?php if ($use_image_header && !$use_animated_header) { echo $has_header_bg_image ? ' --header-bg-image: url(' . esc_url($header_bg_url) . ');' : ' --header-bg-color: ' . esc_attr($primary_color) . ';'; } ?>">
                <div class="listeo-ai-chat-wrapper<?php echo $color_scheme === 'dark' ? ' dark-mode' : ''; ?>" id="listeo-floating-chat-instance" data-hide-images="<?php echo esc_attr(
                    $hide_images,
                ); ?>"><?php if ($color_scheme === 'auto'): ?><script>if(window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches){document.getElementById('listeo-floating-chat-instance').classList.add('dark-mode');}</script><?php endif; ?><?php if (get_option('listeo_ai_color_scheme_switcher')): ?><script>(function(){var s=localStorage.getItem('listeo_ai_chat_dark_mode'),e=document.getElementById('listeo-floating-chat-instance');if(s==='dark')e.classList.add('dark-mode');else if(s==='light')e.classList.remove('dark-mode');})();</script><?php endif; ?>
                    <div class="listeo-ai-chat-container">
                        <div class="listeo-ai-chat-header">
                            <div class="listeo-ai-chat-header-left">
                                <?php if ($chat_avatar_url): ?>
                                    <div class="listeo-ai-chat-avatar-wrapper">
                                        <img src="<?php echo esc_url(
                                            $chat_avatar_url,
                                        ); ?>" alt="<?php echo esc_attr(
    $chat_title,
); ?>" class="listeo-ai-chat-avatar" />
                                        <span class="listeo-ai-chat-status-dot"></span>
                                    </div>
                                <?php endif; ?>
                                <div class="listeo-ai-chat-title"><?php echo esc_html(
                                    $chat_title,
                                ); ?></div>
                            </div>
                            <div class="listeo-ai-chat-menu">
                                <?php if (get_option('listeo_ai_color_scheme_switcher')): ?>
                                <div class="listeo-ai-chat-darkmode-toggle" role="button" tabindex="0" aria-label="<?php esc_attr_e('Toggle dark mode', 'ai-chat-search'); ?>">
                                    <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                                    <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                                </div>
                                <?php endif; ?>
                                <div class="listeo-ai-chat-menu-trigger" role="button" tabindex="0" aria-haspopup="menu" aria-expanded="false">
                                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M3 6.5C2.17 6.5 1.5 7.17 1.5 8C1.5 8.83 2.17 9.5 3 9.5C3.83 9.5 4.5 8.83 4.5 8C4.5 7.17 3.83 6.5 3 6.5ZM8 6.5C7.17 6.5 6.5 7.17 6.5 8C6.5 8.83 7.17 9.5 8 9.5C8.83 9.5 9.5 8.83 9.5 8C9.5 7.17 8.83 6.5 8 6.5ZM13 6.5C12.17 6.5 11.5 7.17 11.5 8C11.5 8.83 12.17 9.5 13 9.5C13.83 9.5 14.5 8.83 14.5 8C14.5 7.17 13.83 6.5 13 6.5Z" fill="currentColor"/>
                                    </svg>
                                </div>
                                <div class="listeo-ai-chat-menu-dropdown" role="menu" data-state="closed">
                                    <div class="listeo-ai-chat-menu-item listeo-ai-chat-expand-btn" role="menuitem" tabindex="-1">
                                        <svg class="icon-expand" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <polyline points="9 21 3 21 3 15"></polyline>
                                            <line x1="21" y1="3" x2="14" y2="10"></line>
                                            <line x1="3" y1="21" x2="10" y2="14"></line>
                                        </svg>
                                        <svg class="icon-collapse" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="4 14 10 14 10 20"></polyline>
                                            <polyline points="20 10 14 10 14 4"></polyline>
                                            <line x1="14" y1="10" x2="21" y2="3"></line>
                                            <line x1="3" y1="21" x2="10" y2="14"></line>
                                        </svg>
                                        <span class="text-expand"><?php esc_html_e("Expand chat", "ai-chat-search"); ?></span>
                                        <span class="text-collapse"><?php esc_html_e("Collapse chat", "ai-chat-search"); ?></span>
                                    </div>
                                    <div class="listeo-ai-chat-menu-item listeo-ai-chat-clear-btn" role="menuitem" tabindex="-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                            <line x1="12" y1="7" x2="12" y2="13"></line>
                                            <line x1="9" y1="10" x2="15" y2="10"></line>
                                        </svg>
                                        <?php esc_html_e("Start a new chat", "ai-chat-search"); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="listeo-ai-chat-messages" id="listeo-floating-chat-instance-messages">
                            <!-- Welcome message added by JavaScript -->
                        </div>

                        <?php
                        // Quick Action Buttons (PRO feature - code in Pro plugin)
                        do_action('listeo_ai_chat_quick_buttons');
                        ?>

                        <?php $image_input_enabled = AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_image_input', 0); ?>
                        <div class="listeo-ai-chat-input-wrapper">
                            <?php if ($image_input_enabled): ?>
                            <div
                                class="listeo-ai-chat-image-btn"
                                data-chat-tooltip="<?php esc_attr_e('Attach Image', 'ai-chat-search'); ?>"
                                role="button"
                                tabindex="0"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span class="image-count-badge">1</span>
                            </div>
                            <input type="file" class="listeo-ai-chat-image-input" accept="image/jpeg,image/jpg,.jpg,.jpeg,image/png,image/gif,image/webp" style="display: none;" />
                            <?php endif; ?>
                            <textarea
                                id="listeo-floating-chat-instance-input"
                                class="listeo-ai-chat-input<?php echo $image_input_enabled ? ' has-image-input' : ''; ?>"
                                placeholder="<?php echo esc_attr(
                                    $placeholder,
                                ); ?>"
                                rows="2"
                                maxlength="1000"
                            ></textarea>
                            <?php
                            // Speech-to-text mic button (PRO feature)
                            if (AI_Chat_Search_Pro_Manager::is_pro_active() && get_option('listeo_ai_chat_enable_speech', 0)) {
                                do_action('listeo_ai_chat_mic_button');
                            }
                            ?>
                            <button
                                id="listeo-floating-chat-instance-send"
                                class="listeo-ai-chat-send-btn"
                            >
                                <img src="<?php echo esc_url(
                                    LISTEO_AI_SEARCH_PLUGIN_URL .
                                        "assets/icons/arrow-up.svg",
                                ); ?>" alt="Send" width="16" height="16" />
                            </button>
                        </div>

                        <?php if (
                            get_option("listeo_ai_chat_terms_notice_enabled", 0)
                        ): ?>
                            <div class="listeo-ai-chat-terms-notice">
                                <?php echo wp_kses_post(
                                    get_option(
                                        "listeo_ai_chat_terms_notice_text",
                                        'By using this chat, you agree to our <a href="/terms-of-use" target="_blank">Terms of Use</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>',
                                    ),
                                ); ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        // Show "Powered by Purethemes" badge in FREE version (unless whitelabel is enabled in PRO)
                        $is_pro_widget =
                            class_exists("AI_Chat_Search_Pro_Manager") &&
                            AI_Chat_Search_Pro_Manager::is_pro_active();
                        $whitelabel_enabled_widget =
                            $is_pro_widget &&
                            get_option("listeo_ai_chat_whitelabel_enabled", 0);
                        if (!$whitelabel_enabled_widget): ?>
                            <div class="listeo-ai-chat-powered-by" id="listeo-ai-chat-powered-by-floating" data-required="true">
                                Powered by <a href="https://purethemes.net/ai-chatbot-for-wordpress/?utm_source=chatbot-widget&utm_medium=powered-by&utm_campaign=branding" target="_blank" rel="noopener">Purethemes</a>
                            </div>
                        <?php endif;
                        ?>

<?php
                        // Contact Form Overlay (PRO feature - rendered by Pro plugin when quick buttons enabled)
                        do_action('listeo_ai_chat_contact_form_overlay');
                        ?>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
}

// Initialize floating widget
new Listeo_AI_Search_Floating_Chat_Widget();
