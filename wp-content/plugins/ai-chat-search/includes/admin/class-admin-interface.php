<?php
/**
 * Admin Interface Class
 * 
 * Handles WordPress admin settings and management interface
 * 
 * @package Listeo_AI_Search
 * @since 1.0.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include admin handlers
require_once plugin_dir_path(__FILE__) . 'class-admin-chat-history.php';
require_once plugin_dir_path(__FILE__) . 'class-admin-contact-messages.php';
require_once plugin_dir_path(__FILE__) . 'class-admin-search-analytics.php';

class Listeo_AI_Search_Admin_Interface {

    /**
     * Chat history admin handler instance
     * @var Admin_Chat_History
     */
    private $chat_history;

    /**
     * Contact messages admin handler instance
     * @var Admin_Contact_Messages
     */
    private $contact_messages;

    /**
     * Search analytics admin handler instance
     * @var Admin_Search_Analytics
     */
    private $search_analytics;

    /**
     * Centralized Settings Registry - Single Source of Truth
     *
     * This registry eliminates duplication across 10+ locations in the codebase.
     * All settings metadata is defined here once and derived everywhere else.
     *
     * @return array Comprehensive settings configuration
     */
    private function get_settings_registry() {
        $settings = array(
            // ============================================
            // API Configuration Settings
            // ============================================
            'listeo_ai_search_provider' => array(
                'type' => 'select',
                'section' => 'api-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'openai',
                'description' => 'AI Provider',
                'options' => array(
                    'openai' => 'OpenAI',
                    'gemini' => 'Google Gemini'
                )
            ),
            'listeo_ai_search_api_key' => array(
                'type' => 'text',
                'section' => 'api-config',
                'sanitize' => 'sanitize_text_field',
                'default' => '',
                'description' => 'OpenAI API Key'
            ),
            'listeo_ai_search_gemini_api_key' => array(
                'type' => 'text',
                'section' => 'api-config',
                'sanitize' => 'sanitize_text_field',
                'default' => '',
                'description' => 'Google Gemini API Key'
            ),
            'listeo_ai_search_mistral_api_key' => array(
                'type' => 'text',
                'section' => 'api-config',
                'sanitize' => 'sanitize_text_field',
                'default' => '',
                'description' => 'Mistral AI API Key'
            ),
            'listeo_ai_search_debug_mode' => array(
                'type' => 'checkbox',
                'section' => 'developer-debug',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable debug logging'
            ),
            'listeo_ai_chat_lazy_load' => array(
                'type' => 'checkbox',
                'section' => 'developer-debug',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Lazy load chatbot scripts'
            ),
            // ============================================
            // Quality & Threshold Settings
            // ============================================
            'listeo_ai_search_query_expansion' => array(
                'type' => 'checkbox',
                'section' => 'quality-thresholds',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable query expansion'
            ),
            'listeo_ai_search_min_match_percentage' => array(
                'type' => 'number',
                'section' => 'quality-thresholds',
                'sanitize' => 'intval',
                'default' => 50,
                'description' => 'Minimum match percentage'
            ),
            'listeo_ai_search_best_match_threshold' => array(
                'type' => 'number',
                'section' => 'quality-thresholds',
                'sanitize' => 'intval',
                'default' => 75,
                'description' => 'Best match threshold'
            ),
            'listeo_ai_search_max_results' => array(
                'type' => 'number',
                'section' => 'quality-thresholds',
                'sanitize' => 'intval',
                'default' => 10,
                'description' => 'Maximum search results'
            ),

            // ============================================
            // Search Suggestions Settings
            // ============================================
            'listeo_ai_search_suggestions_enabled' => array(
                'type' => 'checkbox',
                'section' => 'search-suggestions',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable search suggestions'
            ),
            'listeo_ai_search_suggestions_source' => array(
                'type' => 'select',
                'section' => 'search-suggestions',
                'sanitize' => 'sanitize_text_field',
                'default' => 'ai',
                'description' => 'Suggestions source'
            ),
            'listeo_ai_search_custom_suggestions' => array(
                'type' => 'textarea',
                'section' => 'search-suggestions',
                'sanitize' => 'sanitize_textarea_field',
                'default' => '',
                'description' => 'Custom suggestions (one per line)'
            ),

            // ============================================
            // Processing Settings
            // ============================================
            'listeo_ai_search_rate_limit_per_hour' => array(
                'type' => 'number',
                'section' => 'processing',
                'sanitize' => 'intval',
                'default' => 200,
                'min' => 10,
                'max' => 10000,
                'description' => 'API rate limit per hour'
            ),

            // ============================================
            // Analytics Settings
            // ============================================
            'listeo_ai_search_enable_analytics' => array(
                'type' => 'checkbox',
                'section' => 'analytics',
                'sanitize' => 'intval',
                'default' => 1,
                'description' => 'Enable analytics tracking'
            ),

            // ============================================
            // AI Chat Configuration Settings
            // ============================================
            'listeo_ai_chat_force_language' => array(
                'type' => 'text',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => '',
                'description' => 'Force AI to respond in this language. Use [English][browser_lang] for restricted choice.'
            ),
            'listeo_ai_chat_system_prompt' => array(
                'type' => 'textarea',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_textarea_field',
                'default' => '',
                'description' => 'System prompt for AI chat'
            ),
            'listeo_ai_chat_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 1,
                'description' => 'Enable AI chat'
            ),
            'listeo_ai_chat_avatar' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Chat avatar image'
            ),
            'listeo_ai_chat_name' => array(
                'type' => 'text',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => __('AI Assistant', 'ai-chat-search'),
                'description' => 'Chat assistant name'
            ),
            'listeo_ai_chat_welcome_message' => array(
                'type' => 'wysiwyg',
                'section' => 'ai-chat-config',
                'sanitize' => 'wp_kses_post',
                'default' => __('Hello! How can I help you today?', 'ai-chat-search'),
                'description' => 'Welcome message (supports HTML)'
            ),
            'listeo_ai_chat_model' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'gpt-5.3-chat-latest',
                'description' => 'OpenAI model to use'
            ),
            'listeo_ai_chat_max_results' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 10,
                'description' => 'Maximum results to include in context'
            ),
            'listeo_ai_chat_woo_cart_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable WooCommerce cart in chatbot'
            ),
            'listeo_ai_chat_rag_sources_limit' => array(
                'type' => 'number',
                'section' => 'quality-thresholds',
                'sanitize' => 'intval',
                'default' => 5,
                'min' => 2,
                'max' => 10,
                'description' => 'Maximum RAG sources to send to LLM'
            ),
            'listeo_ai_chat_hide_images' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Hide images in chat results'
            ),
            'listeo_ai_chat_loading_style' => array(
                'type' => 'radio',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'spinner',
                'description' => 'Loading animation style',
                'options' => array(
                    'spinner' => 'Spinner + Text',
                    'dots' => 'Dots Only'
                )
            ),
            'listeo_ai_chat_typing_animation' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable typing animation effect for AI responses'
            ),
            'listeo_ai_chat_require_login' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Require login to use chat'
            ),
            'listeo_ai_chat_history_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 1,
                'description' => 'Enable chat history'
            ),
            'listeo_ai_chat_retention_days' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 30,
                'description' => 'Chat history retention (days)'
            ),
            'listeo_ai_chat_terms_notice_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Show terms notice'
            ),
            'listeo_ai_chat_terms_notice_text' => array(
                'type' => 'wysiwyg',
                'section' => 'ai-chat-config',
                'sanitize' => 'wp_kses_post',
                'default' => '',
                'description' => 'Terms notice text (supports HTML)'
            ),
            'listeo_ai_chat_whitelabel_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable whitelabel (removes Powered by Purethemes badge)'
            ),
            'listeo_ai_contact_form_allow_ai_send' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Allow AI to send emails to you when explicitly asked by user when chatting with AI'
            ),
            'listeo_ai_contact_form_examples' => array(
                'type' => 'textarea',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_textarea_field',
                'default' => "EXAMPLES OF WHEN TO USE:\n- \"Can you send a message to the site owner for me?\"\n- \"I want to contact support about X\"\n- \"Please send them my inquiry about Y\"\n\nEXAMPLES OF WHEN NOT TO USE:\n- \"How can I contact you?\" (just provide contact info)\n- \"What's your email?\" (just provide info, don't send)",
                'description' => 'Examples for AI when to use or not use the contact form tool'
            ),
            'listeo_ai_whatsapp_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable WhatsApp integration via Twilio'
            ),
            'listeo_ai_telegram_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable Telegram bot integration'
            ),
            'listeo_ai_chat_rate_limit_tier1' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 10,
                'description' => 'Rate limit tier 1 (logged out users)'
            ),
            'listeo_ai_chat_rate_limit_tier2' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 30,
                'description' => 'Rate limit tier 2 (logged in users)'
            ),
            'listeo_ai_chat_rate_limit_tier3' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 100,
                'description' => 'Rate limit tier 3 (premium users)'
            ),
            'listeo_ai_chat_context_length' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'normal',
                'description' => 'Conversation context length preset'
            ),

            // ============================================
            // Floating Chat Widget Settings (now part of ai-chat-config section)
            // ============================================
            'listeo_ai_floating_chat_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable floating chat widget'
            ),
            'listeo_ai_floating_position' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'right',
                'description' => 'Floating widget position (left or right)'
            ),
            'listeo_ai_floating_button_icon' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'default',
                'description' => 'Button icon style'
            ),
            'listeo_ai_floating_custom_icon' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Custom icon attachment ID'
            ),
            'listeo_ai_floating_welcome_bubble' => array(
                'type' => 'wysiwyg',
                'section' => 'ai-chat-config',
                'sanitize' => 'wp_kses_post',
                'default' => __('Hi! How can I help you?', 'ai-chat-search'),
                'description' => 'Welcome bubble text (supports HTML)'
            ),
            'listeo_ai_floating_popup_width' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 390,
                'description' => 'Popup width (pixels)'
            ),
            'listeo_ai_floating_popup_height' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 600,
                'description' => 'Popup height (pixels)'
            ),
            'listeo_ai_floating_button_color' => array(
                'type' => 'color',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_hex_color',
                'default' => '#222222',
                'description' => 'Button background color'
            ),
            'listeo_ai_primary_color' => array(
                'type' => 'color',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_hex_color',
                'default' => '#0073ee',
                'description' => 'Primary color for UI elements'
            ),
            'listeo_ai_color_scheme' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'light',
                'description' => 'Color scheme for chat widget (light, dark, auto)'
            ),
            'listeo_ai_color_scheme_switcher' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Show color scheme switcher toggle in chat window'
            ),
            'listeo_ai_floating_header_style' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'simple',
                'description' => 'Header style for floating chat popup (simple, image, or animated)'
            ),
            'listeo_ai_floating_header_bg' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Header background image attachment ID'
            ),
            'listeo_ai_floating_header_overlay' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable overlay on header background image'
            ),
            'listeo_ai_animated_bg_color' => array(
                'type' => 'color',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_hex_color',
                'default' => '#1560d0',
                'description' => 'Base color for animated wave background'
            ),
            'listeo_ai_floating_excluded_pages' => array(
                'type' => 'array',
                'section' => 'ai-chat-config',
                'sanitize' => 'array_map_intval',
                'default' => array(),
                'description' => 'Pages where floating chat should be hidden'
            ),
            'listeo_ai_floating_offset_desktop_h' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 20,
                'description' => 'Desktop horizontal offset (px)'
            ),
            'listeo_ai_floating_offset_desktop_v' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 20,
                'description' => 'Desktop vertical offset (px)'
            ),
            'listeo_ai_floating_offset_mobile_h' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 20,
                'description' => 'Mobile horizontal offset (px)'
            ),
            'listeo_ai_floating_offset_mobile_v' => array(
                'type' => 'number',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 20,
                'description' => 'Mobile vertical offset (px)'
            ),
            'listeo_ai_chat_quick_buttons_enabled' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable quick action buttons in chat'
            ),
            'listeo_ai_chat_quick_buttons_visibility' => array(
                'type' => 'select',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_text_field',
                'default' => 'always',
                'description' => 'Quick buttons visibility mode'
            ),
            'listeo_ai_chat_quick_buttons' => array(
                'type' => 'array',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_quick_buttons',
                'default' => array(),
                'description' => 'Quick action buttons configuration'
            ),
            'listeo_ai_chat_blocked_ips' => array(
                'type' => 'array',
                'section' => 'ai-chat-config',
                'sanitize' => 'sanitize_blocked_ips',
                'default' => array(),
                'description' => 'IP addresses blocked from using the chat widget (PRO feature)'
            ),
            'listeo_ai_chat_enable_speech' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable speech-to-text input in chat (PRO feature)'
            ),
            'listeo_ai_chat_enable_image_input' => array(
                'type' => 'checkbox',
                'section' => 'ai-chat-config',
                'sanitize' => 'intval',
                'default' => 0,
                'description' => 'Enable image input in chat (vision models)'
            ),

            // ============================================
            // Special/Internal Settings (not in standard forms)
            // ============================================
            'listeo_ai_search_enabled_types' => array(
                'type' => 'array',
                'section' => 'internal',
                'sanitize' => 'sanitize_text_field',
                'default' => array('listing'),
                'description' => 'Enabled post types for AI search'
            ),
        );

        // Allow PRO to register additional settings
        return apply_filters('ai_chat_search_settings_registry', $settings);
    }

    /**
     * Get all setting keys from registry
     *
     * @return array Array of setting keys
     */
    private function get_all_setting_keys() {
        return array_keys($this->get_settings_registry());
    }

    /**
     * Get settings grouped by section
     *
     * @return array Settings organized by section
     */
    private function get_section_settings() {
        $registry = $this->get_settings_registry();
        $sections = array();

        foreach ($registry as $key => $config) {
            if ($config['section'] !== 'internal') {
                $sections[$config['section']][] = $key;
            }
        }

        return $sections;
    }

    /**
     * Get checkbox fields, optionally filtered by section
     *
     * @param string|null $section Optional section filter
     * @return array Array of checkbox field keys
     */
    private function get_checkbox_fields($section = null) {
        $registry = $this->get_settings_registry();
        $checkboxes = array();

        foreach ($registry as $key => $config) {
            if ($config['type'] === 'checkbox') {
                if ($section === null || $config['section'] === $section) {
                    $checkboxes[] = $key;
                }
            }
        }

        return $checkboxes;
    }

    /**
     * Get section checkboxes mapping
     *
     * @return array Checkboxes organized by section
     */
    private function get_section_checkboxes() {
        $registry = $this->get_settings_registry();
        $sections = array();

        foreach ($registry as $key => $config) {
            if ($config['type'] === 'checkbox' && $config['section'] !== 'internal') {
                $sections[$config['section']][] = $key;
            }
        }

        return $sections;
    }

    /**
     * Sanitize a setting value based on registry configuration
     *
     * @param string $key Setting key
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    private function sanitize_setting($key, $value) {
        $registry = $this->get_settings_registry();

        if (!isset($registry[$key])) {
            // Unknown setting, use basic sanitization
            return sanitize_text_field($value);
        }

        $config = $registry[$key];

        // Allow PRO to handle sanitization for its own settings
        $filtered_value = apply_filters('ai_chat_search_sanitize_setting', $value, $key, $value);
        if ($filtered_value !== $value) {
            return $filtered_value;
        }

        // Handle arrays
        if (is_array($value)) {
            // Special handling for page IDs (integers)
            if ($key === 'listeo_ai_floating_excluded_pages') {
                return array_map('intval', $value);
            }
            // Special handling for quick buttons
            if ($key === 'listeo_ai_chat_quick_buttons') {
                $sanitized_buttons = array();
                foreach ($value as $button) {
                    if (!empty($button['text'])) {
                        $btn_type = isset($button['type']) ? $button['type'] : 'chat';
                        $btn_color = isset($button['color']) ? sanitize_hex_color($button['color']) : '';
                        $sanitized_buttons[] = array(
                            'text'  => sanitize_text_field($button['text']),
                            'type'  => in_array($btn_type, array('chat', 'url', 'contact')) ? $btn_type : 'chat',
                            'value' => $btn_type === 'url' ? esc_url_raw($button['value']) : sanitize_text_field($button['value']),
                            'color' => $btn_color ? $btn_color : ''
                        );
                    }
                }
                return $sanitized_buttons;
            }
            // Special handling for blocked IPs (PRO feature)
            if ($key === 'listeo_ai_chat_blocked_ips') {
                $sanitized_ips = array();
                foreach ($value as $entry) {
                    if (!empty($entry['ip'])) {
                        $ip = sanitize_text_field(trim($entry['ip']));
                        // Basic validation - allow IPv4, IPv6, and CIDR notation
                        if (filter_var($ip, FILTER_VALIDATE_IP) ||
                            (strpos($ip, '/') !== false && filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP))) {
                            $sanitized_ips[] = array('ip' => $ip);
                        }
                    }
                }
                return $sanitized_ips;
            }
            // Special handling for pre-chat fields (PRO feature)
            if ($key === 'listeo_ai_chat_pre_chat_fields') {
                $sanitized_fields = array();
                foreach ($value as $entry) {
                    if (!empty($entry['label'])) {
                        $sanitized_fields[] = array(
                            'label' => sanitize_text_field(trim($entry['label'])),
                        );
                    }
                }
                return $sanitized_fields;
            }
            return array_map('sanitize_text_field', $value);
        }

        // Handle special cases with min/max bounds
        if ($key === 'listeo_ai_search_rate_limit_per_hour') {
            $value = intval($value);
            return max($config['min'], min($config['max'], $value));
        }

        // RAG sources limit - ensure minimum of 2
        if ($key === 'listeo_ai_chat_rag_sources_limit') {
            $value = intval($value);
            return max($config['min'], min($config['max'], $value));
        }

        // Context length - only allow valid presets
        if ($key === 'listeo_ai_chat_context_length') {
            return in_array($value, array('short', 'normal', 'long')) ? $value : 'normal';
        }

        if ($key === 'listeo_ai_floating_button_color' || $key === 'listeo_ai_primary_color') {
            $sanitized = sanitize_hex_color($value);
            return empty($sanitized) ? $config['default'] : $sanitized;
        }

        // Apply sanitization callback
        $sanitize_callback = $config['sanitize'];

        // Handle special sanitization for WYSIWYG fields (allow HTML)
        if ($sanitize_callback === 'wp_kses_post') {
            return wp_kses_post(wp_unslash($value));
        }

        // Handle textarea fields (strip slashes)
        if ($sanitize_callback === 'sanitize_textarea_field') {
            return sanitize_textarea_field(wp_unslash($value));
        }

        // Standard sanitization
        return call_user_func($sanitize_callback, $value);
    }

    /**
     * Get default value for a setting
     *
     * @param string $key Setting key
     * @return mixed Default value
     */
    private function get_default_value($key) {
        $registry = $this->get_settings_registry();
        return isset($registry[$key]) ? $registry[$key]['default'] : null;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize admin handlers (they register their own AJAX handlers)
        $this->chat_history = new Admin_Chat_History();
        $this->contact_messages = new Admin_Contact_Messages();
        $this->search_analytics = new Admin_Search_Analytics();

        // Ensure default settings exist (for existing installations)
        add_action('admin_init', array($this, 'ensure_default_settings'), 5);

        // Admin menu
        add_action('admin_menu', array($this, 'admin_menu'));

        // Settings
        add_action('admin_init', array($this, 'admin_init'));

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers for settings
        add_action('wp_ajax_listeo_ai_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_listeo_ai_test_api_key', array($this, 'ajax_test_api_key'));
        add_action('wp_ajax_listeo_ai_test_gemini_api_key', array($this, 'ajax_test_gemini_api_key'));
        add_action('wp_ajax_listeo_ai_test_mistral_api_key', array($this, 'ajax_test_mistral_api_key'));
        add_action('wp_ajax_listeo_ai_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_listeo_ai_regenerate_embedding', array($this, 'ajax_regenerate_embedding'));
        add_action('wp_ajax_listeo_ai_clear_embeddings_for_provider_switch', array($this, 'ajax_clear_embeddings_for_provider_switch'));
        add_action('wp_ajax_listeo_ai_create_missing_tables', array($this, 'ajax_create_missing_tables'));
        add_action('wp_ajax_listeo_ai_clear_ip_rate_limits', array($this, 'ajax_clear_ip_rate_limits'));
        add_action('wp_ajax_listeo_ai_toggle_auto_training', array($this, 'ajax_toggle_auto_training'));
        add_action('wp_ajax_listeo_ai_toggle_custom_fields', array($this, 'ajax_toggle_custom_fields'));

        // Translation importer AJAX handlers
        add_action('wp_ajax_ai_chat_search_check_translation', array($this, 'ajax_check_translation_availability'));
        add_action('wp_ajax_ai_chat_search_install_translation', array($this, 'ajax_install_translation'));
        add_action('wp_ajax_ai_chat_search_remove_translation', array($this, 'ajax_remove_translation'));

        // Embedding search handler
        add_action('wp_ajax_listeo_ai_search_embeddings', array($this, 'ajax_search_embeddings'));

        // Knowledge sources management
        add_action('wp_ajax_listeo_ai_search_posts_for_reference', array($this, 'ajax_search_posts_for_reference'));
        add_action('wp_ajax_listeo_ai_add_knowledge_source', array($this, 'ajax_add_knowledge_source'));
        add_action('wp_ajax_listeo_ai_delete_knowledge_source', array($this, 'ajax_delete_knowledge_source'));

        // Show debug mode notice if enabled
        if (get_option('listeo_ai_search_debug_mode', false)) {
            add_action('admin_notices', array($this, 'show_debug_mode_notice'));
        }

        // Show version mismatch notice if Pro is active with different version
        add_action('admin_notices', array($this, 'show_version_mismatch_notice'));
    }

    /**
     * Ensure default settings exist in database
     * This handles existing installations that were activated before defaults were added
     */
    public function ensure_default_settings() {
        // Check if defaults have been initialized (use a flag to avoid running every page load)
        $defaults_initialized = get_option('listeo_ai_defaults_initialized', false);

        if ($defaults_initialized) {
            return; // Defaults already set
        }

        // Get defaults from main plugin class (single source of truth)
        $defaults = Listeo_AI_Search::get_default_settings();

        foreach ($defaults as $option_name => $default_value) {
            // Only add if option doesn't exist
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }

        // Set flag so this doesn't run again
        update_option('listeo_ai_defaults_initialized', true);
    }
    
    /**
     * Check if Listeo theme is active
     *
     * @return bool True if Listeo or Listeo child theme is active
     */
    private function is_listeo_theme_active() {
        $current_theme = wp_get_theme();
        $theme_name = $current_theme->get('Name');
        $parent_theme = $current_theme->get('Template');

        // Check if current theme or parent theme is Listeo
        return (stripos($theme_name, 'listeo') !== false || stripos($parent_theme, 'listeo') !== false);
    }

    /**
     * Get the admin menu icon (robot head as chat bubble with tail)
     *
     * @return string Base64-encoded SVG data URI
     */
    private function get_menu_icon() {
        // Robot icon - white fill, viewBox 24x28 adds bottom whitespace to raise icon
        return 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyOCIgZmlsbD0iI0ZGRiI+PHBhdGggZD0iTTkgMTRhMS41IDEuNSAwIDEwMCAzIDEuNSAxLjUgMCAwMDAtM3oiLz48cGF0aCBkPSJNMTMuNSAxNS41YTEuNSAxLjUgMCAxMTMgMCAxLjUgMS41IDAgMDEtMyAweiIvPjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgZD0iTTEyIDFhMiAyIDAgMDAtMiAyYzAgLjc0LjQwMiAxLjM4NyAxIDEuNzMyVjdINmEzIDMgMCAwMC0zIDN2MTBhMyAzIDAgMDAzIDNoMTJhMyAzIDAgMDAzLTNWMTBhMyAzIDAgMDAtMy0zaC01VjQuNzMyQTIgMiAwIDAwMTIgMXpNNSAxMGExIDEgMCAwMTEtMWgxMmExIDEgMCAwMTEgMXYxMGExIDEgMCAwMS0xIDFINmExIDEgMCAwMS0xLTFWMTB6Ii8+PHBhdGggZD0iTTEgMTRhMSAxIDAgMDAtMSAxdjJhMSAxIDAgMTAyIDB2LTJhMSAxIDAgMDAtMS0xeiIvPjxwYXRoIGQ9Ik0yMiAxNWExIDEgMCAxMTIgMHYyYTEgMSAwIDExLTIgMHYtMnoiLz48L3N2Zz4=';
    }

    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            'AI Chat & Search',           // Page title
            'AI Chat & Search',                 // Menu title
            'manage_options',                                     // Capability
            'ai-chat-search',                                   // Menu slug
            array($this, 'admin_page'),                          // Callback function
            $this->get_menu_icon(), // Robot icon
            30                                                    // Position (after Comments)
        );
    }

    /**
     * Initialize admin settings
     *
     * REFACTORED: Now uses centralized settings registry
     */
    public function admin_init() {
        // Register settings with explicit capability for multisite compatibility
        $settings_args = array(
            'type' => 'string',
            'sanitize_callback' => null, // We handle sanitization in AJAX handler
            'default' => null,
        );

        // Register all settings from the central registry
        foreach ($this->get_all_setting_keys() as $setting_key) {
            register_setting('listeo_ai_search_settings', $setting_key, $settings_args);
        }

        // Add allowed_options filter for multisite compatibility
        add_filter('allowed_options', array($this, 'add_allowed_options'));
    }

    /**
     * Add plugin options to allowed options list for multisite compatibility
     *
     * REFACTORED: Now uses centralized settings registry
     *
     * @param array $allowed_options Array of allowed options
     * @return array Modified array of allowed options
     */
    public function add_allowed_options($allowed_options) {
        $allowed_options['listeo_ai_search_settings'] = $this->get_all_setting_keys();
        return $allowed_options;
    }

    /**
     * Add hidden fields to preserve other settings when submitting a form
     *
     * REFACTORED: Now uses centralized settings registry
     *
     * @param array $exclude_fields Array of field names to exclude from hidden fields
     */
    private function add_hidden_fields_except($exclude_fields = array()) {
        // Allow PRO to add its settings to the exclude list
        $exclude_fields = apply_filters('ai_chat_search_hidden_fields_except', $exclude_fields);

        $all_settings = $this->get_all_setting_keys();

        foreach ($all_settings as $setting) {
            if (!in_array($setting, $exclude_fields)) {
                $value = get_option($setting);
                if ($value !== false && $value !== '') {
                    // Handle different input types
                    if (is_array($value)) {
                        foreach ($value as $key => $sub_value) {
                            // Handle nested arrays (like quick buttons)
                            if (is_array($sub_value)) {
                                foreach ($sub_value as $sub_key => $sub_sub_value) {
                                    if (!is_array($sub_sub_value)) {
                                        echo '<input type="hidden" name="' . esc_attr($setting) . '[' . esc_attr($key) . '][' . esc_attr($sub_key) . ']" value="' . esc_attr($sub_sub_value) . '">';
                                    }
                                }
                            } else {
                                echo '<input type="hidden" name="' . esc_attr($setting) . '[' . esc_attr($key) . ']" value="' . esc_attr($sub_value) . '">';
                            }
                        }
                    } else {
                        echo '<input type="hidden" name="' . esc_attr($setting) . '" value="' . esc_attr($value) . '">';
                    }
                }
            }
        }
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        // Debug logging for multisite
        if (is_multisite() && get_option('listeo_ai_search_debug_mode', false)) {
            error_log('[AI Chat Multisite Debug] AJAX save settings called');
            error_log('[AI Chat Multisite Debug] Blog ID: ' . get_current_blog_id());
            error_log('[AI Chat Multisite Debug] User ID: ' . get_current_user_id());
            error_log('[AI Chat Multisite Debug] User can manage_options: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        }

        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get section being saved (to properly handle unchecked checkboxes)
        $section = isset($_POST['section']) ? sanitize_text_field($_POST['section']) : '';

        // REFACTORED: Get settings mapping from central registry
        $section_settings = $this->get_section_settings();
        $section_checkboxes = $this->get_section_checkboxes();
        $all_settings = $this->get_all_setting_keys();

        $updated_settings = array();

        // Handle unchecked checkboxes for current section
        // Special handling for combined sections
        $sections_to_check = array();
        if ($section === 'settings-config') {
            // Settings tab combines all these sections
            $sections_to_check = array('api-config', 'developer-debug', 'quality-thresholds', 'search-suggestions', 'processing', 'analytics');
        } elseif ($section && isset($section_checkboxes[$section])) {
            $sections_to_check = array($section);
        }

        foreach ($sections_to_check as $check_section) {
            if (isset($section_checkboxes[$check_section])) {
                foreach ($section_checkboxes[$check_section] as $checkbox_field) {
                    if (!isset($_POST[$checkbox_field])) {
                        // Checkbox not in POST = unchecked, set to 0
                        update_option($checkbox_field, 0);
                        $updated_settings[$checkbox_field] = 0;
                    }
                }
            }
        }

        // Handle array checkbox fields (like page exclusion lists)
        // These are type='array' but behave like checkboxes and need special handling
        if ($section === 'ai-chat-config') {
            if (!isset($_POST['listeo_ai_floating_excluded_pages'])) {
                // No checkboxes selected = empty array
                update_option('listeo_ai_floating_excluded_pages', array());
                $updated_settings['listeo_ai_floating_excluded_pages'] = array();
            }
            // Handle blocked IPs (PRO feature) - if all IPs removed, save empty array
            if (!isset($_POST['listeo_ai_chat_blocked_ips'])) {
                update_option('listeo_ai_chat_blocked_ips', array());
                $updated_settings['listeo_ai_chat_blocked_ips'] = array();
            }
            // Handle pre-chat fields (PRO feature) - if all fields removed, save empty array
            if (!isset($_POST['listeo_ai_chat_pre_chat_fields'])) {
                update_option('listeo_ai_chat_pre_chat_fields', array());
                $updated_settings['listeo_ai_chat_pre_chat_fields'] = array();
            }
        }

        // Process each setting
        foreach ($all_settings as $setting) {
            if (isset($_POST[$setting])) {
                // REFACTORED: Use centralized sanitization from registry
                $value = $this->sanitize_setting($setting, $_POST[$setting]);

                // Update the option
                $update_result = update_option($setting, $value);

                // Debug logging for multisite
                if (is_multisite() && get_option('listeo_ai_search_debug_mode', false)) {
                    error_log(sprintf(
                        '[AI Chat Multisite Debug] update_option(%s) result: %s',
                        $setting,
                        $update_result ? 'success' : 'failed'
                    ));
                }

                $updated_settings[$setting] = $value;

                // Auto-update model when provider changes
                if ($setting === 'listeo_ai_search_provider') {
                    $current_model = get_option('listeo_ai_chat_model', '');
                    $openai_models = array('gpt-4o-mini', 'gpt-4o', 'gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-5-mini', 'gpt-5-chat-latest', 'gpt-5.1', 'gpt-5.2', 'gpt-5.3-chat-latest', 'gpt-5.4', 'gpt-5.4-mini', 'gpt-5.4-nano');
                    $gemini_models = array('gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-3.1-pro-preview', 'gemini-3-flash-preview', 'gemini-3.1-flash-lite-preview');
                    $mistral_models = array('mistral-small-latest', 'mistral-medium-latest', 'mistral-large-latest');

                    // If switching to OpenAI and current model is not an OpenAI model
                    if ($value === 'openai' && !in_array($current_model, $openai_models)) {
                        update_option('listeo_ai_chat_model', 'gpt-5.3-chat-latest');
                        $updated_settings['listeo_ai_chat_model'] = 'gpt-5.3-chat-latest';
                    }
                    // If switching to Gemini and current model is not a Gemini model
                    elseif ($value === 'gemini' && !in_array($current_model, $gemini_models)) {
                        update_option('listeo_ai_chat_model', 'gemini-3-flash-preview');
                        $updated_settings['listeo_ai_chat_model'] = 'gemini-3-flash-preview';
                    }
                    // If switching to Mistral and current model is not a Mistral model
                    elseif ($value === 'mistral' && !in_array($current_model, $mistral_models)) {
                        update_option('listeo_ai_chat_model', 'mistral-large-latest');
                        $updated_settings['listeo_ai_chat_model'] = 'mistral-large-latest';
                    }
                }
            }
            // NOTE: Do NOT set missing settings to 0
            // If a setting is not in POST, it means it's from a different tab/form
            // Leave those settings unchanged in the database
        }

        // Debug logging for multisite
        if (is_multisite() && get_option('listeo_ai_search_debug_mode', false)) {
            error_log('[AI Chat Multisite Debug] Total settings updated: ' . count($updated_settings));
        }

        // Cleanup: Ensure all checkbox values are proper integers (not strings)
        $this->cleanup_checkbox_values();

        // Ensure default contact form examples are stored if not set
        if (get_option('listeo_ai_contact_form_examples') === false) {
            $default_examples = "EXAMPLES OF WHEN TO USE:\n- \"Can you send a message to the site owner for me?\"\n- \"I want to contact support about X\"\n- \"Please send them my inquiry about Y\"\n\nEXAMPLES OF WHEN NOT TO USE:\n- \"How can I contact you?\" (just provide contact info)\n- \"What's your email?\" (just provide info, don't send)";
            add_option('listeo_ai_contact_form_examples', $default_examples);
        }

        // Handle chat history table creation/deletion based on setting
        $history_enabled = get_option('listeo_ai_chat_history_enabled', 0);
        if ($history_enabled && class_exists('Listeo_AI_Search_Chat_History')) {
            // Create table if it doesn't exist
            Listeo_AI_Search_Chat_History::create_table();
        }

        wp_send_json_success(array(
            'message' => __('Settings saved successfully!', 'ai-chat-search'),
            'updated_settings' => $updated_settings
        ));
    }

    /**
     * Cleanup corrupted checkbox values (convert string '0' to integer 0)
     *
     * REFACTORED: Now uses centralized settings registry
     */
    private function cleanup_checkbox_values() {
        // Get all checkbox fields from the central registry
        $checkbox_fields = $this->get_checkbox_fields();

        foreach ($checkbox_fields as $field) {
            $value = get_option($field);
            // Convert string '0' or '1' to proper integers
            if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
                update_option($field, 0);
            } elseif ($value === '1' || $value === 1 || $value === true || $value === 'true') {
                update_option($field, 1);
            }
        }
    }

    /**
     * AJAX handler for testing API key
     */
    public function ajax_test_api_key() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get API key from POST data or current settings
        $api_key = sanitize_text_field($_POST['api_key'] ?? get_option('listeo_ai_search_api_key', ''));
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Please enter an API key first.', 'ai-chat-search')));
            return;
        }

        try {
            // Test the API key by making a simple request
            $response = wp_remote_get('https://api.openai.com/v1/models', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => __('Connection failed: ', 'ai-chat-search') . $response->get_error_message()));
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                wp_send_json_success(array(
                    'message' => __('✅ API key is valid!', 'ai-chat-search')
                ));
            } elseif ($response_code === 401) {
                wp_send_json_error(array('message' => __('❌ Invalid API key. Please check your key and try again.', 'ai-chat-search')));
            } elseif ($response_code === 429) {
                wp_send_json_error(array('message' => __('⚠️ API key valid but rate limit exceeded. Try again in a moment.', 'ai-chat-search')));
            } else {
                $error_body = json_decode($response_body, true);
                $error_message = isset($error_body['error']['message']) ? $error_body['error']['message'] : __('Unknown error', 'ai-chat-search');
                wp_send_json_error(array('message' => sprintf(__('❌ API Error (%d): %s', 'ai-chat-search'), $response_code, $error_message)));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('❌ Test failed: ', 'ai-chat-search') . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for testing Gemini API key
     */
    public function ajax_test_gemini_api_key() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get API key from POST data or current settings
        $api_key = sanitize_text_field($_POST['api_key'] ?? get_option('listeo_ai_search_gemini_api_key', ''));

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Please enter a Gemini API key first.', 'ai-chat-search')));
            return;
        }

        try {
            // Test the API key by making a simple embedding request (smallest possible test)
            $test_endpoint = 'https://generativelanguage.googleapis.com/v1beta/openai/embeddings';

            $response = wp_remote_post($test_endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'gemini-embedding-001',
                    'input' => 'test',
                    'dimensions' => 1536
                )),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => __('Connection failed: ', 'ai-chat-search') . $response->get_error_message()));
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                wp_send_json_success(array(
                    'message' => __('✅ Gemini API key is valid!', 'ai-chat-search')
                ));
            } elseif ($response_code === 401 || $response_code === 403) {
                wp_send_json_error(array('message' => __('❌ Invalid Gemini API key. Please check your key and try again.', 'ai-chat-search')));
            } elseif ($response_code === 429) {
                wp_send_json_error(array('message' => __('⚠️ API key valid but rate limit exceeded. Try again in a moment.', 'ai-chat-search')));
            } else {
                $error_body = json_decode($response_body, true);
                $error_message = isset($error_body['error']['message']) ? $error_body['error']['message'] : __('Unknown error', 'ai-chat-search');
                wp_send_json_error(array('message' => sprintf(__('❌ Gemini API Error (%d): %s', 'ai-chat-search'), $response_code, $error_message)));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('❌ Test failed: ', 'ai-chat-search') . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for testing Mistral API key
     */
    public function ajax_test_mistral_api_key() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get API key from POST data or current settings
        $api_key = sanitize_text_field($_POST['api_key'] ?? get_option('listeo_ai_search_mistral_api_key', ''));

        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('Please enter a Mistral API key first.', 'ai-chat-search')));
            return;
        }

        try {
            // Test the API key by making a simple models list request
            $test_endpoint = 'https://api.mistral.ai/v1/models';

            $response = wp_remote_get($test_endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => __('Connection failed: ', 'ai-chat-search') . $response->get_error_message()));
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if ($response_code === 200) {
                wp_send_json_success(array(
                    'message' => __('✅ Mistral API key is valid!', 'ai-chat-search')
                ));
            } elseif ($response_code === 401 || $response_code === 403) {
                wp_send_json_error(array('message' => __('❌ Invalid Mistral API key. Please check your key and try again.', 'ai-chat-search')));
            } elseif ($response_code === 429) {
                wp_send_json_error(array('message' => __('⚠️ API key valid but rate limit exceeded. Try again in a moment.', 'ai-chat-search')));
            } else {
                $error_body = json_decode($response_body, true);
                $error_message = isset($error_body['message']) ? $error_body['message'] : __('Unknown error', 'ai-chat-search');
                wp_send_json_error(array('message' => sprintf(__('❌ Mistral API Error (%d): %s', 'ai-chat-search'), $response_code, $error_message)));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('❌ Test failed: ', 'ai-chat-search') . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for clearing cache
     */
    public function ajax_clear_cache() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        try {
            $cleared_count = 0;
            $cleared_types = array();

            // Clear API health cache
            if (delete_transient('listeo_ai_api_health')) {
                $cleared_count++;
                $cleared_types[] = __('API health status', 'ai-chat-search');
            }

            // Clear rate limit cache (current hour)
            $rate_limit_key = 'listeo_ai_rate_limit_' . date('Y-m-d-H');
            if (delete_transient($rate_limit_key)) {
                $cleared_count++;
                $cleared_types[] = __('rate limiting', 'ai-chat-search');
            }

            // Clear usage tracking cache (current hour)
            $usage_key = 'listeo_ai_usage_' . date('Y-m-d-H');
            if (delete_transient($usage_key)) {
                $cleared_count++;
                $cleared_types[] = __('usage tracking', 'ai-chat-search');
            }

            // Clear Google Places cache (if any exist)
            global $wpdb;
            $google_transients = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_listeo_google_places_%'"
            );
            foreach ($google_transients as $transient_name) {
                $key = str_replace('_transient_', '', $transient_name);
                if (delete_transient($key)) {
                    $cleared_count++;
                }
            }
            if (count($google_transients) > 0) {
                $cleared_types[] = sprintf(__('Google Places data (%d)', 'ai-chat-search'), count($google_transients));
            }

            // Clear processing delay cache
            $processing_keys = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_listeo_ai_processing_delay_%'"
            );
            foreach ($processing_keys as $transient_name) {
                $key = str_replace('_transient_', '', $transient_name);
                if (delete_transient($key)) {
                    $cleared_count++;
                }
            }
            if (count($processing_keys) > 0) {
                $cleared_types[] = sprintf(__('processing delays (%d)', 'ai-chat-search'), count($processing_keys));
            }

            $message = $cleared_count > 0 
                ? sprintf(__('✅ Cleared %d cache entries: %s', 'ai-chat-search'), $cleared_count, implode(', ', $cleared_types))
                : __('ℹ️ No cache entries found to clear.', 'ai-chat-search');

            wp_send_json_success(array('message' => $message));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('❌ Clear cache failed: ', 'ai-chat-search') . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for clearing IP-based rate limit transients
     */
    public function ajax_clear_ip_rate_limits() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        try {
            global $wpdb;

            // Find and delete all IP rate limit transients (pattern: ai_chat_ip_*)
            $ip_transients = $wpdb->get_col(
                "SELECT option_name FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_ai_chat_ip_%'"
            );

            $cleared_count = 0;
            foreach ($ip_transients as $transient_name) {
                $key = str_replace('_transient_', '', $transient_name);
                if (delete_transient($key)) {
                    $cleared_count++;
                }
            }

            if ($cleared_count > 0) {
                wp_send_json_success(array(
                    'message' => sprintf(__('Cleared %d IP rate limits.', 'ai-chat-search'), $cleared_count)
                ));
            } else {
                wp_send_json_success(array(
                    'message' => __('No IP rate limits to clear.', 'ai-chat-search')
                ));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Failed to clear IP rate limits: ', 'ai-chat-search') . $e->getMessage()));
        }
    }

    /**
     * AJAX handler for toggling auto-training on save
     */
    public function ajax_toggle_auto_training() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $disabled = filter_var($_POST['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        update_option('listeo_ai_disable_auto_training', $disabled);

        wp_send_json_success();
    }

    /**
     * AJAX handler for toggling custom fields inclusion in embeddings
     */
    public function ajax_toggle_custom_fields() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $disabled = filter_var($_POST['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        update_option('listeo_ai_disable_custom_fields', $disabled);

        wp_send_json_success();
    }

    /**
     * AJAX handler for creating missing database tables and columns
     */
    public function ajax_create_missing_tables() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        global $wpdb;
        $created_tables = array();
        $failed_tables = array();
        $upgraded_columns = array();

        // Define required tables and their creation methods
        $required_tables = array(
            'listeo_ai_embeddings' => array(
                'class' => 'Listeo_AI_Search_Database_Manager',
                'method' => 'create_table'
            ),
            'listeo_ai_chat_history' => array(
                'class' => 'Listeo_AI_Search_Chat_History',
                'method' => 'create_table'
            ),
            'listeo_ai_contact_messages' => array(
                'class' => 'Listeo_AI_Search_Contact_Messages',
                'method' => 'create_table'
            ),
        );

        foreach ($required_tables as $table_suffix => $table_info) {
            $full_table_name = $wpdb->prefix . $table_suffix;

            // Check if table already exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) === $full_table_name;

            if (!$table_exists) {
                // Try to create the table
                if (class_exists($table_info['class']) && method_exists($table_info['class'], $table_info['method'])) {
                    $result = call_user_func(array($table_info['class'], $table_info['method']));

                    // Verify table was created
                    $table_now_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) === $full_table_name;

                    if ($table_now_exists) {
                        $created_tables[] = $table_suffix;
                    } else {
                        $failed_tables[] = $table_suffix . ' (' . __('creation failed', 'ai-chat-search') . ')';
                    }
                } else {
                    $failed_tables[] = $table_suffix . ' (' . __('class/method not found', 'ai-chat-search') . ')';
                }
            }
        }

        // Run column migrations for chat_history table
        $chat_history_table = $wpdb->prefix . 'listeo_ai_chat_history';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$chat_history_table}'") === $chat_history_table) {
            // Check and add ip_address column if missing
            $ip_column = $wpdb->get_results("SHOW COLUMNS FROM {$chat_history_table} LIKE 'ip_address'");
            if (empty($ip_column)) {
                $wpdb->query("ALTER TABLE {$chat_history_table} ADD COLUMN ip_address varchar(45) DEFAULT NULL AFTER user_id");
                $wpdb->query("ALTER TABLE {$chat_history_table} ADD KEY ip_address (ip_address)");
                $upgraded_columns[] = 'ip_address';
            }
        }

        // Build response message
        $messages = array();
        if (!empty($created_tables)) {
            $messages[] = sprintf(__('Created tables: %s', 'ai-chat-search'), implode(', ', $created_tables));
        }
        if (!empty($upgraded_columns)) {
            $messages[] = sprintf(__('Added columns: %s', 'ai-chat-search'), implode(', ', $upgraded_columns));
        }
        if (!empty($failed_tables)) {
            $messages[] = sprintf(__('Failed: %s', 'ai-chat-search'), implode(', ', $failed_tables));
        }

        if (!empty($failed_tables)) {
            wp_send_json_error(array(
                'message' => '❌ ' . implode('. ', $messages),
                'created' => $created_tables,
                'upgraded' => $upgraded_columns,
                'failed' => $failed_tables
            ));
        } elseif (!empty($created_tables) || !empty($upgraded_columns)) {
            wp_send_json_success(array(
                'message' => '✅ ' . implode('. ', $messages) . '. ' . __('Please refresh the page.', 'ai-chat-search'),
                'created' => $created_tables,
                'upgraded' => $upgraded_columns
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('ℹ️ Database is already up to date.', 'ai-chat-search'),
                'created' => array(),
                'upgraded' => array()
            ));
        }
    }

    /**
     * AJAX handler for regenerating single embedding
     */
    public function ajax_regenerate_embedding() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get post ID
        $post_id = absint($_POST['listing_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Please enter a valid post ID.', 'ai-chat-search')));
            return;
        }

        // Check if post exists
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => sprintf(__('Post ID %d not found.', 'ai-chat-search'), $post_id)));
            return;
        }

        // Check if post type is enabled
        $enabled_post_types = Listeo_AI_Search_Database_Manager::get_enabled_post_types();
        if (!in_array($post->post_type, $enabled_post_types)) {
            wp_send_json_error(array('message' => sprintf(__('Post type "%s" is not enabled for AI search. Enable it in Universal Settings first.', 'ai-chat-search'), $post->post_type)));
            return;
        }

        try {
            // Check if API key is configured (provider-aware for OpenAI/Gemini)
            $provider = new Listeo_AI_Provider();
            $api_key = $provider->get_api_key();
            if (empty($api_key)) {
                wp_send_json_error(array('message' => sprintf(__('%s API key is not configured. Please configure it in Settings first.', 'ai-chat-search'), $provider->get_provider_name())));
                return;
            }

            // Regenerate the embedding
            $result = Listeo_AI_Search_Database_Manager::generate_single_embedding($post_id);

            if ($result['success']) {
                $message = sprintf(
                    __('✅ Embedding regenerated successfully for "%s" (ID: %d, Type: %s). Processed %d characters.', 'ai-chat-search'),
                    esc_html($post->post_title),
                    $post_id,
                    $post->post_type,
                    $result['chars_processed'] ?? 0
                );

                wp_send_json_success(array(
                    'message' => $message,
                    'post_title' => $post->post_title,
                    'post_id' => $post_id,
                    'post_type' => $post->post_type,
                    'chars_processed' => $result['chars_processed'] ?? 0,
                    'embedding_dimensions' => $result['embedding_dimensions'] ?? 0
                ));
            } else {
                wp_send_json_error(array('message' => sprintf(
                    __('❌ Failed to regenerate embedding for "%s" (ID: %d): %s', 'ai-chat-search'),
                    esc_html($post->post_title),
                    $post_id,
                    $result['error'] ?? __('Unknown error', 'ai-chat-search')
                )));
            }

        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(
                __('❌ Error regenerating embedding for post ID %d: %s', 'ai-chat-search'),
                $post_id,
                $e->getMessage()
            )));
        }
    }

    /**
     * AJAX handler for searching embeddings
     */
    public function ajax_search_embeddings() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Get search term
        $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        if (empty($search_term)) {
            wp_send_json_error(array('message' => __('Please enter a search term.', 'ai-chat-search')));
            return;
        }

        try {
            $results = Listeo_AI_Search_Database_Manager::search_embeddings($search_term, 20);

            wp_send_json_success(array(
                'results' => $results,
                'count' => count($results),
                'search_term' => $search_term
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => sprintf(
                __('Error searching embeddings: %s', 'ai-chat-search'),
                $e->getMessage()
            )));
        }
    }

    /**
     * AJAX handler: Search posts for reference picker in system prompt
     */
    public function ajax_search_posts_for_reference() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        if (mb_strlen($search) < 2) {
            wp_send_json_success(array('results' => array()));
            return;
        }

        global $wpdb;

        // Include all enabled post types + document types, exclude listings and products (they have dedicated tools)
        $enabled_types = array();
        if (class_exists('Listeo_AI_Search_Database_Manager')) {
            $enabled_types = Listeo_AI_Search_Database_Manager::get_enabled_post_types();
        }
        $enabled_types = array_diff($enabled_types, array('listing', 'product'));
        // Always include document types
        $all_types = array_unique(array_merge($enabled_types, array('ai_pdf_document', 'ai_external_page')));

        if (empty($all_types)) {
            wp_send_json_success(array('results' => array()));
            return;
        }

        $types_placeholders = implode(',', array_fill(0, count($all_types), '%s'));
        $like = '%' . $wpdb->esc_like($search) . '%';

        $query = $wpdb->prepare(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts}
             WHERE post_status = 'publish'
             AND post_type IN ($types_placeholders)
             AND post_title LIKE %s
             ORDER BY post_title ASC
             LIMIT 10",
            array_merge($all_types, array($like))
        );

        $posts = $wpdb->get_results($query);
        $results = array();

        foreach ($posts as $post) {
            $type_label = $post->post_type;
            if ($post->post_type === 'ai_pdf_document') {
                $type_label = 'PDF';
            } elseif ($post->post_type === 'ai_external_page') {
                $type_label = 'External Page';
            } else {
                $type_obj = get_post_type_object($post->post_type);
                $type_label = ($type_obj && isset($type_obj->labels->singular_name)) ? $type_obj->labels->singular_name : ucfirst($post->post_type);
            }

            $results[] = array(
                'id' => $post->ID,
                'title' => html_entity_decode(wp_strip_all_tags($post->post_title), ENT_QUOTES, 'UTF-8'),
                'type' => $type_label,
            );
        }

        wp_send_json_success(array('results' => $results));
    }

    /**
     * AJAX handler: Add a knowledge source
     */
    public function ajax_add_knowledge_source() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post_title = isset($_POST['post_title']) ? sanitize_text_field($_POST['post_title']) : '';

        if (empty($topic) || $post_id <= 0) {
            wp_send_json_error(array('message' => __('Topic and post are required.', 'ai-chat-search')));
            return;
        }

        $sources = get_option('listeo_ai_knowledge_sources', array());
        if (!is_array($sources)) {
            $sources = array();
        }

        $sources[] = array(
            'topic' => $topic,
            'post_id' => $post_id,
            'post_title' => $post_title,
        );

        update_option('listeo_ai_knowledge_sources', $sources);

        wp_send_json_success(array('sources' => $sources));
    }

    /**
     * AJAX handler: Delete a knowledge source by index
     */
    public function ajax_delete_knowledge_source() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;

        $sources = get_option('listeo_ai_knowledge_sources', array());
        if (!is_array($sources) || !isset($sources[$index])) {
            wp_send_json_error(array('message' => __('Source not found.', 'ai-chat-search')));
            return;
        }

        array_splice($sources, $index, 1);
        update_option('listeo_ai_knowledge_sources', $sources);

        wp_send_json_success(array('sources' => $sources));
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page (updated hook for top-level menu)
        if ($hook !== 'toplevel_page_ai-chat-search') {
            return;
        }
        
        // Enqueue Outfit font
        wp_enqueue_style(
            'outfit-font',
            'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap',
            [],
            null
        );

        // Enqueue CSS
        wp_enqueue_style(
            'ai-chat-search-admin',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/css/admin-styles.css',
            ['outfit-font'],
            LISTEO_AI_SEARCH_VERSION
        );

        // Enqueue Pro features CSS (locked features and upgrade prompts)
        wp_enqueue_style(
            'ai-chat-search-admin-pro',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/css/admin-pro.css',
            ['ai-chat-search-admin'],
            LISTEO_AI_SEARCH_VERSION
        );

        // Enqueue jQuery (it should already be loaded, but just to be sure)
        wp_enqueue_script('jquery');

        // Enqueue WordPress media uploader (for custom icon upload)
        wp_enqueue_media();

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Get the admin AJAX URL (multisite compatible)
        $admin_ajax_url = get_admin_url(get_current_blog_id(), 'admin-ajax.php');

        // Get current embedding count for provider switch detection (lightweight query only)
        global $wpdb;
        $embeddings_table = Listeo_AI_Search_Database_Manager::get_embeddings_table_name();
        $total_embeddings = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$embeddings_table}'" ) === $embeddings_table ) {
            $total_embeddings = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$embeddings_table}" );
        }

        // Enqueue modular admin JavaScript files
        $js_version = LISTEO_AI_SEARCH_VERSION;
        $js_base_url = LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/js/admin/';

        // Core module (must be loaded first)
        wp_enqueue_script(
            'airs-admin-core',
            $js_base_url . 'admin-core.js',
            array('jquery'),
            $js_version,
            true
        );

        // Settings module (provider toggle, API tests, forms)
        wp_enqueue_script(
            'airs-admin-settings',
            $js_base_url . 'admin-settings.js',
            array('jquery', 'airs-admin-core'),
            $js_version,
            true
        );

        // Embeddings module (batch processing, viewer)
        wp_enqueue_script(
            'airs-admin-embeddings',
            $js_base_url . 'admin-embeddings.js',
            array('jquery', 'airs-admin-core'),
            $js_version,
            true
        );

        // Database module (status, actions, search)
        wp_enqueue_script(
            'airs-admin-database',
            $js_base_url . 'admin-database.js',
            array('jquery', 'airs-admin-core', 'airs-admin-embeddings'),
            $js_version,
            true
        );

        // Media module (WordPress media uploader)
        wp_enqueue_script(
            'airs-admin-media',
            $js_base_url . 'admin-media.js',
            array('jquery', 'airs-admin-core'),
            $js_version,
            true
        );

        // UI module (sticky footers, collapsibles, shortcode generator, etc.)
        wp_enqueue_script(
            'airs-admin-ui',
            $js_base_url . 'admin-ui.js',
            array('jquery', 'airs-admin-core'),
            $js_version,
            true
        );

        // Silk wave renderer (shared with frontend, used for admin preview)
        wp_enqueue_script(
            'listeo-silk-wave-bg',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/js/silk-wave-bg.js',
            array(),
            $js_version,
            true
        );

        // Localize AJAX settings for the core module
        $provider = new Listeo_AI_Provider();
        $has_api_key = ! empty( $provider->get_api_key() );

        wp_localize_script('airs-admin-core', 'listeo_ai_search_ajax', array(
            'ajax_url'                => $admin_ajax_url,
            'nonce'                   => wp_create_nonce('listeo_ai_search_nonce'),
            'clear_embeddings_nonce'  => wp_create_nonce('listeo_ai_clear_embeddings'),
            'translation_nonce'       => wp_create_nonce('ai_chat_search_translation_nonce'),
            'test_email_nonce'        => wp_create_nonce('listeo_ai_test_email'),
            'contact_form_nonce'      => wp_create_nonce('listeo_ai_contact_form_settings'),
            'total_embeddings'        => intval($total_embeddings),
            'has_api_key'             => $has_api_key,
            'provider_name'           => $provider->get_provider_name(),
            'settings_url'            => admin_url('admin.php?page=ai-chat-search&tab=settings'),
        ));

        // Localize translations for all modules
        wp_localize_script('airs-admin-core', 'listeo_ai_search_i18n', array(
            // General
            'success'            => __('Success!', 'ai-chat-search'),
            'error'              => __('Error!', 'ai-chat-search'),
            'loading'            => __('Loading...', 'ai-chat-search'),
            'ajaxError'          => __('AJAX error:', 'ai-chat-search'),
            'connectionFailed'   => __('Connection failed:', 'ai-chat-search'),
            'ajaxErrorOccurred'  => __('AJAX error occurred', 'ai-chat-search'),
            'ajaxConfigError'    => __('Error: AJAX configuration not loaded. Please refresh the page.', 'ai-chat-search'),
            'noResults'          => __('No results found.', 'ai-chat-search'),
            'whenUserAsksAbout'  => __('When user asks about', 'ai-chat-search'),

            // Provider settings
            'providerRetrainNotice' => __('Click Save and go to Data Training tab and start retraining after changing provider.', 'ai-chat-search'),
            'errorClearingEmbeddings' => __('Error clearing embeddings. Please try again.', 'ai-chat-search'),
            'openaiModel'        => __('OpenAI Model', 'ai-chat-search'),
            'openaiModelHelp'    => __('Select the OpenAI model for chat responses.', 'ai-chat-search'),
            'geminiModel'        => __('Gemini Model', 'ai-chat-search'),
            'geminiModelHelp'    => __('Select the Gemini model for chat responses.', 'ai-chat-search'),
            'mistralModel'       => __('Mistral Model', 'ai-chat-search'),
            'mistralModelHelp'   => __('Select the Mistral model for chat responses.', 'ai-chat-search'),

            // API testing
            'enterApiKeyFirst'   => __('Please enter an API key first.', 'ai-chat-search'),
            'testingConnection'  => __('Testing API connection...', 'ai-chat-search'),
            'testFailed'         => __('Test failed', 'ai-chat-search'),

            // Cache and rate limits
            'clearing'           => __('Clearing...', 'ai-chat-search'),
            'clearCacheFailed'   => __('Clear cache failed', 'ai-chat-search'),
            'failed'             => __('Failed', 'ai-chat-search'),
            'creating'           => __('Creating...', 'ai-chat-search'),
            'failedCreateTables' => __('Failed to create tables', 'ai-chat-search'),

            // Batch generation
            'startingGeneration' => __('Starting structured embedding generation...', 'ai-chat-search'),
            'stoppedByUser'      => __('Generation stopped by user.', 'ai-chat-search'),
            'processingBatch'    => __('Processing batch starting at offset', 'ai-chat-search'),
            'batchCompleted'     => __('Batch completed:', 'ai-chat-search'),
            'itemsProcessed'     => __('items processed.', 'ai-chat-search'),
            'progress'           => __('Progress:', 'ai-chat-search'),
            'batchHadErrors'     => __('Batch had', 'ai-chat-search'),
            'errors'             => __('errors:', 'ai-chat-search'),
            'andMore'            => __('and', 'ai-chat-search'),
            'moreErrors'         => __('more errors', 'ai-chat-search'),
            'generationComplete' => __('Generation completed successfully!', 'ai-chat-search'),
            'batchFailed'        => __('Batch failed:', 'ai-chat-search'),
            'generationFailed'   => __('Generation failed.', 'ai-chat-search'),
            'connectionError'    => __('Generation failed due to connection error.', 'ai-chat-search'),
            'apiKeyMissing'      => __('API Key Not Configured', 'ai-chat-search'),
            'apiKeyMissingDesc'  => __('Please add your API key in the Settings tab before starting training.', 'ai-chat-search'),
            'goToSettings'       => __('Go to Settings', 'ai-chat-search'),

            // Single embedding
            'enterListingId'     => __('Please enter a listing ID', 'ai-chat-search'),
            'enterValidListingId' => __('Please enter a valid listing ID', 'ai-chat-search'),
            'loadingEmbedding'   => __('Loading embedding data for listing', 'ai-chat-search'),
            'regenerationFailed' => __('Regeneration failed', 'ai-chat-search'),
            'generating'         => __('Generating...', 'ai-chat-search'),
            'done'               => __('Done', 'ai-chat-search'),
            'failedToGenerate'   => __('Failed to generate embedding:', 'ai-chat-search'),

            // Embedding viewer
            'parent'             => __('Parent', 'ai-chat-search'),
            'contentChunked'     => __('This content is chunked into', 'ai-chat-search'),
            'partsForBetter'     => __('parts for better embedding quality', 'ai-chat-search'),
            'words'              => __('Words', 'ai-chat-search'),
            'characters'         => __('Characters', 'ai-chat-search'),
            'embedding'          => __('Embedding', 'ai-chat-search'),
            'created'            => __('Created', 'ai-chat-search'),
            'yes'                => __('Yes', 'ai-chat-search'),
            'no'                 => __('No', 'ai-chat-search'),
            'clickChunkId'       => __('Click on a chunk ID to view its embedding details.', 'ai-chat-search'),
            'processedContent'   => __('Processed Content', 'ai-chat-search'),
            'embeddingVector'    => __('Embedding Vector (first 10 dimensions)', 'ai-chat-search'),
            'fullVector'         => __('Full embedding vector contains', 'ai-chat-search'),
            'dimensions'         => __('dimensions', 'ai-chat-search'),
            'deleteEmbedding'    => __('Delete Embedding', 'ai-chat-search'),
            'deleteAllChunks'    => __('Delete All Chunks', 'ai-chat-search'),
            'parentNoEmbedding'  => __('Parent post does not have its own embedding - content is stored in chunks above.', 'ai-chat-search'),
            'noEmbeddingFound'   => __('No embedding found', 'ai-chat-search'),
            'notProcessedYet'    => __('This listing has not been processed for AI search yet.', 'ai-chat-search'),
            'confirmDeleteEmbedding' => __('Are you sure you want to delete the embedding for', 'ai-chat-search'),
            'confirmDeleteChunks' => __('Are you sure you want to delete all', 'ai-chat-search'),
            'chunksFor'          => __('chunks for', 'ai-chat-search'),
            'needToRegenerate'   => __('You will need to regenerate it to use AI search for this item.', 'ai-chat-search'),
            'deletingEmbedding'  => __('Deleting embedding...', 'ai-chat-search'),
            'deletingChunks'     => __('Deleting chunks...', 'ai-chat-search'),
            'embeddingDeleted'   => __('Embedding deleted successfully.', 'ai-chat-search'),
            'chunksDeleted'      => __('All chunks deleted successfully.', 'ai-chat-search'),

            // Database status
            'processedEmbeddings' => __('Processed Embeddings', 'ai-chat-search'),
            'missingEmbeddings'  => __('Missing Embeddings', 'ai-chat-search'),
            'recentActivity'     => __('Recent Activity (24h)', 'ai-chat-search'),
            'recentEmbeddings'   => __('Recent Embeddings', 'ai-chat-search'),
            'searchPlaceholder'  => __('Search by title or ID...', 'ai-chat-search'),
            'search'             => __('Search', 'ai-chat-search'),
            'title'              => __('Title', 'ai-chat-search'),
            'type'               => __('Type', 'ai-chat-search'),
            'clickIdToView'      => __('Click on any ID to view its embedding data.', 'ai-chat-search'),
            'indicatesChunk'     => __('indicates that content was split into parts for better accuracy.', 'ai-chat-search'),
            'noRecentEmbeddings' => __('No recent embeddings found.', 'ai-chat-search'),
            'selectAll'          => __('Select All', 'ai-chat-search'),
            'deselectAll'        => __('Deselect All', 'ai-chat-search'),
            'generateSelected'   => __('Generate Selected', 'ai-chat-search'),
            'lastModified'       => __('Last Modified', 'ai-chat-search'),
            'action'             => __('Action', 'ai-chat-search'),
            'generate'           => __('Generate', 'ai-chat-search'),
            'clickGenerateOrSelect' => __('Click "Generate" for individual listings or select multiple and use "Generate Selected".', 'ai-chat-search'),
            'allMissing'         => __('All', 'ai-chat-search'),
            'listingsAreMissing' => __('listings are missing embeddings. Use the "Generate Structured Embeddings" tool above to process them in bulk.', 'ai-chat-search'),
            'errorLoadingInfo'   => __('Error loading listing information', 'ai-chat-search'),

            // Search
            'enterSearchTerm'    => __('Please enter a search term.', 'ai-chat-search'),
            'searching'          => __('Searching...', 'ai-chat-search'),
            'searchFailed'       => __('Search failed.', 'ai-chat-search'),
            'searchRequestFailed' => __('Search request failed.', 'ai-chat-search'),
            'resultsFound'       => __('result(s) found', 'ai-chat-search'),
            'noResultsFound'     => __('No results found.', 'ai-chat-search'),
            'clearSearchResults' => __('Clear search results', 'ai-chat-search'),

            // Database actions
            'confirmClearAll'    => __('Are you sure? This will delete all embeddings and cannot be undone.', 'ai-chat-search'),
            'clearingDatabase'   => __('Clearing database...', 'ai-chat-search'),
            'selectPostType'     => __('Please select a post type', 'ai-chat-search'),
            'confirmDeletePostType' => __('Are you sure you want to delete all embeddings for', 'ai-chat-search'),
            'cannotBeUndone'     => __('This cannot be undone.', 'ai-chat-search'),
            'deletingEmbeddingsFor' => __('Deleting embeddings for', 'ai-chat-search'),
            'clearingAnalytics'  => __('Clearing analytics data...', 'ai-chat-search'),
            'actionCompleted'    => __('Action completed successfully!', 'ai-chat-search'),
            'successfullyDeleted' => __('Successfully deleted', 'ai-chat-search'),
            'embeddingsForPostType' => __('embedding(s) for post type:', 'ai-chat-search'),
            'analyticsCleared'   => __('Analytics data cleared successfully!', 'ai-chat-search'),

            // Bulk generation
            'embeddings'         => __('embeddings...', 'ai-chat-search'),
            'completed'          => __('Completed:', 'ai-chat-search'),
            'processing'         => __('Processing', 'ai-chat-search'),

            // Media uploader
            'selectCustomIcon'   => __('Select Custom Icon', 'ai-chat-search'),
            'useThisIcon'        => __('Use this icon', 'ai-chat-search'),
            'selectChatAvatar'   => __('Select Chat Avatar', 'ai-chat-search'),
            'useThisImage'       => __('Use this image', 'ai-chat-search'),
            'remove'             => __('Remove', 'ai-chat-search'),
            'buttonColor'        => get_option('listeo_ai_floating_button_color', '#222222'),
            'pluginUrl'          => LISTEO_AI_SEARCH_PLUGIN_URL,

            // UI module - Shortcode generator
            'copied'             => __('Copied!', 'ai-chat-search'),

            // UI module - Translation installer
            'englishDefault'     => __('English is default, no install needed.', 'ai-chat-search'),
            'checking'           => __('Checking...', 'ai-chat-search'),
            'translationInstalled' => __('Translation already installed!', 'ai-chat-search'),
            'translationAvailable' => __('Translation available. Click Install.', 'ai-chat-search'),
            'translationNotAvailable' => __('Translation not available for this locale.', 'ai-chat-search'),
            'checkFailed'        => __('Check failed.', 'ai-chat-search'),
            'installing'         => __('Installing...', 'ai-chat-search'),
            'translationInstalledSuccess' => __('Translation installed successfully!', 'ai-chat-search'),
            'installFailed'      => __('Installation failed.', 'ai-chat-search'),
            'install'            => __('Install', 'ai-chat-search'),
            'update'             => __('Update', 'ai-chat-search'),

            // UI module - Quality slider
            'qualityVeryLow'     => __('Loose - many results, lower relevance', 'ai-chat-search'),
            'qualityLow'         => __('Broad - more results, some may be less relevant', 'ai-chat-search'),
            'qualityBalanced'    => __('Balanced - good mix of quantity and quality', 'ai-chat-search'),
            'qualityHigh'        => sprintf(__('Quality focused - pay attention because %syou might start getting little results%s', 'ai-chat-search'), '<strong>', '</strong>'),
            'qualityVeryHigh'    => sprintf(__('Very strict — %syou might get little to no results%s', 'ai-chat-search'), '<strong>', '</strong>'),

            // UI module - Quick buttons
            'placeholderUrl'     => __('https://example.com', 'ai-chat-search'),
            'placeholderMessage' => __('Message to send', 'ai-chat-search'),
            'color'              => __('Buttons Color', 'ai-chat-search'),
            'reset'              => __('Reset', 'ai-chat-search'),
            'requestFailed'      => __('Request failed. Please try again.', 'ai-chat-search'),
        ));

        // Note: Color picker and toggleable cards are now handled by admin-ui.js

        // Add inline CSS for AJAX form styling
        wp_add_inline_style('ai-chat-search-admin', '
            .airs-form-message {
                margin-top: 15px;
                padding: 10px 15px;
                border-radius: 5px;
                border-left: none;
                background: #fff;
            }
            .airs-form-message.airs-alert-success {
                background: #ecf7ed;
                color: #1e4620;
            }
            .airs-form-message.airs-alert-error {
                background: #fbeaea;
                color: #761919;
            }
            .button-spinner {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            .airs-ajax-form button[type="submit"]:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
            .airs-api-test-result {
                padding: 10px 15px;
                border-radius: 4px;
                border: 1px solid;
                font-size: 14px;
                line-height: 1.4;
            }
            .airs-api-test-result.airs-api-test-success {
                border-color: #46b450;
                background: #ecf7ed;
                color: #1e4620;
            }
            .airs-api-test-result.airs-api-test-error {
                border-color: #dc3232;
                background: #fbeaea;
                color: #761919;
            }
            .airs-button-secondary {
                background: #f7f7f7;
                color: #555;
                border: 1px solid #ccc;
            }
            .airs-button-secondary:hover {
                background: #e9e9e9;
                border-color: #999;
            }
            .airs-cache-actions {
                margin-top: 10px;
            }
            .airs-cache-actions button {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .spin {
                animation: spin 1s linear infinite;
            }
        ');
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        // Run table migrations if needed (only when admin visits this page)
        if (class_exists('Listeo_AI_Search_Contact_Messages')) {
            Listeo_AI_Search_Contact_Messages::maybe_upgrade_table();
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
        ?>
        <script>
        // Apply collapsed state immediately to prevent flash
        (function() {
            var STORAGE_KEY = "airs_collapsed_cards";
            var DEFAULTS = { "database-management": true, "semantic-search-field": true, "developer-debug": true };
            try {
                var stored = localStorage.getItem(STORAGE_KEY);
                var state = stored ? JSON.parse(stored) : {};
                for (var id in DEFAULTS) {
                    if (!(id in state)) state[id] = DEFAULTS[id];
                }
                window.airsCollapsedCards = state;
                var style = document.createElement('style');
                style.id = 'airs-early-collapse-styles';
                var css = '';
                for (var id in state) {
                    if (state[id] === true) {
                        css += '.airs-card-toggleable[data-toggle-id="' + id + '"]:not(.js-ready) .airs-card-body { display: none; }';
                        css += '.airs-card-toggleable[data-toggle-id="' + id + '"]:not(.js-ready) .airs-card-header { border-bottom: none; }';
                        css += '.airs-card-toggleable[data-toggle-id="' + id + '"]:not(.js-ready) .airs-card-toggle-icon { transform: translateY(-50%) rotate(0deg); }';
                    }
                }
                if (css) {
                    style.textContent = css;
                    document.head.appendChild(style);
                }
            } catch(e) {}
        })();
        </script>
        <div class="wrap airs-admin-wrap">
            <div class="airs-header">
                <div class="airs-header-content">
                    <div class="airs-header-icon">
                        🔍
                    </div>
                    <div class="airs-header-text">
                        <h1 style="display: none;"></h1>
                        <div class="airs-header-title">AI Chat &amp; Search by Purethemes</div>
                        <p><?php _e('An AI chatbot that searches the website database and responds like a human assistant.', 'ai-chat-search'); ?></p>
                    </div>
                </div>
            </div>

            <?php
            // Check if user has an active trial license
            $is_trial_active = false;
            $trial_expires_at = 0;
            $trial_time_remaining = 0;

            if (AI_Chat_Search_Pro_Manager::is_pro_active() && class_exists('AI_Chat_Search_Pro_Proxy_License_Manager')) {
                $license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();
                $is_trial_active = $license_manager->is_trial_license();
                $trial_expires_at = $license_manager->get_trial_expires_at();
                $trial_time_remaining = $license_manager->get_trial_time_remaining();
            }

            // Show trial countdown banner if trial is active
            if ($is_trial_active && $trial_time_remaining > 0):
                $hours_remaining = floor($trial_time_remaining / 3600);
                $minutes_remaining = floor(($trial_time_remaining % 3600) / 60);
            ?>
            <a href="https://purethemes.net/ai-chatbot-for-wordpress/?utm_source=ai-chat-plugin&utm_medium=trial_banner&utm_campaign=upgrade" target="_blank" class="airs-trial-request-banner airs-trial-countdown-banner" id="airs-trial-banner">
                <div class="airs-trial-countdown-content">
                    <span class="airs-trial-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </span>
                    <span class="airs-trial-text">
                        <strong><?php _e('Trial License Active', 'ai-chat-search'); ?></strong>
                        <span>
                            <?php
                            printf(
                                /* translators: %1$s: hours, %2$s: minutes */
                                __('Time remaining: %1$s hours, %2$s minutes', 'ai-chat-search'),
                                '<span id="airs-trial-hours">' . esc_html($hours_remaining) . '</span>',
                                '<span id="airs-trial-minutes">' . esc_html($minutes_remaining) . '</span>'
                            );
                            ?>
                            <span class="airs-trial-expires-date">
                                (<?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $trial_expires_at)); ?>)
                            </span>
                        </span>
                    </span>
                </div>
                <span class="airs-trial-upgrade-btn">
                    <?php _e('Upgrade Now', 'ai-chat-search'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </span>
            </a>
            <script>
            (function() {
                var secondsRemaining = <?php echo (int) $trial_time_remaining; ?>;
                var hoursEl = document.getElementById('airs-trial-hours');
                var minutesEl = document.getElementById('airs-trial-minutes');

                if (hoursEl && minutesEl && secondsRemaining > 0) {
                    setInterval(function() {
                        secondsRemaining--;
                        if (secondsRemaining <= 0) {
                            location.reload();
                            return;
                        }
                        var hours = Math.floor(secondsRemaining / 3600);
                        var minutes = Math.floor((secondsRemaining % 3600) / 60);
                        hoursEl.textContent = hours;
                        minutesEl.textContent = minutes;
                    }, 60000); // Update every minute
                }
            })();
            </script>
            <?php
            // Show trial expired banner
            elseif ($is_trial_active && $trial_time_remaining <= 0):
            ?>
            <a href="https://purethemes.net/ai-chatbot-for-wordpress/?utm_source=ai-chat-plugin&utm_medium=trial_expired&utm_campaign=upgrade" target="_blank" class="airs-trial-request-banner airs-trial-expired-banner" id="airs-trial-banner">
                <div class="airs-trial-countdown-content">
                    <span class="airs-trial-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    </span>
                    <span class="airs-trial-text">
                        <strong><?php _e('Trial License Expired', 'ai-chat-search'); ?></strong>
                        <span><?php _e('Upgrade now to continue using Pro features.', 'ai-chat-search'); ?></span>
                    </span>
                </div>
                <span class="airs-trial-upgrade-btn">
                    <?php _e('Upgrade Now', 'ai-chat-search'); ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </span>
            </a>
            <?php
            // Show trial request banner for non-Pro, non-Listeo users
            elseif (!AI_Chat_Search_Pro_Manager::is_pro_active() && !Listeo_AI_Detection::is_listeo_available()):
            ?>
            <div class="airs-trial-request-banner" id="airs-trial-banner">
                <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_trial_request_url()); ?>" target="_blank" class="airs-trial-request-btn">
                    <span class="airs-trial-icon">
                        <svg class="rocket-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path class="rocket-fire" d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"></path><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"></path><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"></path><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"></path></svg>
                    </span>
                    <span class="airs-trial-text">
                        <strong><?php _e('Request Free Trial for 3 Days', 'ai-chat-search'); ?></strong>
                        <span><?php _e(' Unlock everything and see for yourself. No card required.', 'ai-chat-search'); ?></span>
                    </span>
                </a>
                <button type="button" class="airs-trial-close" id="airs-trial-close" title="<?php esc_attr_e('Dismiss', 'ai-chat-search'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <script>
            (function() {
                var banner = document.getElementById('airs-trial-banner');
                var closeBtn = document.getElementById('airs-trial-close');
                if (banner && localStorage.getItem('airs_trial_banner_dismissed') === 'true') {
                    banner.style.display = 'none';
                }
                if (closeBtn) {
                    closeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        banner.style.display = 'none';
                        localStorage.setItem('airs_trial_banner_dismissed', 'true');
                    });
                }
            })();
            </script>
            <?php
            // Show discount banner for Listeo owners who never had Pro
            elseif (!AI_Chat_Search_Pro_Manager::is_pro_active() && Listeo_AI_Detection::is_listeo_available() && !AI_Chat_Search_Pro_Manager::was_pro_ever_activated()):
                $discount_data = AI_Chat_Search_Pro_Manager::get_listeo_discount_data();
                if ($discount_data):
                    $discount_url = add_query_arg([
                        'utm_source'   => 'ai-chat-plugin',
                        'utm_medium'   => 'listeo_discount_banner',
                        'utm_campaign' => 'listeo-discount',
                    ], $discount_data['url']);
            ?>
            <div class="airs-trial-request-banner airs-listeo-discount-banner" id="airs-discount-banner">
                <a href="<?php echo esc_url($discount_url); ?>" target="_blank" class="airs-trial-request-btn">
                    <span class="airs-trial-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                    </span>
                    <?php if (!empty($discount_data['coupon_code'])): ?>
                    <span class="airs-discount-coupon" id="airs-discount-coupon" title="<?php esc_attr_e('Click to copy', 'ai-chat-search'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                        <span class="airs-discount-coupon-code"><?php echo esc_html($discount_data['coupon_code']); ?></span>
                    </span>
                    <?php endif; ?>
                    <span class="airs-trial-text">
                        <strong><?php echo esc_html($discount_data['title']); ?></strong>
                        <span><?php echo esc_html($discount_data['message']); ?></span>
                    </span>
                </a>
                <button type="button" class="airs-trial-close" id="airs-discount-close" title="<?php esc_attr_e('Dismiss', 'ai-chat-search'); ?>">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <script>
            (function() {
                var banner = document.getElementById('airs-discount-banner');
                var closeBtn = document.getElementById('airs-discount-close');
                var couponBtn = document.getElementById('airs-discount-coupon');
                if (banner && localStorage.getItem('airs_listeo_discount_dismissed') === 'true') {
                    banner.style.display = 'none';
                }
                if (closeBtn) {
                    closeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        banner.style.display = 'none';
                        localStorage.setItem('airs_listeo_discount_dismissed', 'true');
                    });
                }
                if (couponBtn) {
                    couponBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var codeEl = couponBtn.querySelector('.airs-discount-coupon-code');
                        var code = codeEl.textContent;
                        var textarea = document.createElement('textarea');
                        textarea.value = code;
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                        codeEl.textContent = '<?php echo esc_js(__('Copied!', 'ai-chat-search')); ?>';
                        setTimeout(function() {
                            codeEl.textContent = code;
                        }, 1500);
                    });
                }
            })();
            </script>
            <?php
                endif; // discount_data
            endif; ?>

            <nav class="airs-nav-tab-wrapper nav-tab-wrapper">
                <a href="?page=ai-chat-search&tab=settings"
                   class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 19.951 19.951">
                        <g transform="translate(-2.025 -2.025)">
                            <path d="M7.713,5.175a1.894,1.894,0,0,0,2.542-.987,1.89,1.89,0,0,1,3.49,0,1.894,1.894,0,0,0,2.542.987,1.914,1.914,0,0,1,2.538,2.538,1.894,1.894,0,0,0,.987,2.542,1.89,1.89,0,0,1,0,3.49,1.894,1.894,0,0,0-.987,2.542,1.914,1.914,0,0,1-2.538,2.538,1.894,1.894,0,0,0-2.542.987,1.89,1.89,0,0,1-3.49,0,1.9,1.9,0,0,0-2.542-.987,1.914,1.914,0,0,1-2.538-2.538,1.894,1.894,0,0,0-.987-2.542,1.89,1.89,0,0,1,0-3.49,1.894,1.894,0,0,0,.987-2.542A1.914,1.914,0,0,1,7.713,5.175ZM12,8.75A3.25,3.25,0,1,0,15.25,12,3.25,3.25,0,0,0,12,8.75Z" fill="#6aa9ff" fill-rule="evenodd" opacity="0.1"/>
                            <path d="M10.255,4.188a1.894,1.894,0,0,1-2.542.987A1.914,1.914,0,0,0,5.175,7.713a1.894,1.894,0,0,1-.987,2.542,1.89,1.89,0,0,0,0,3.49,1.894,1.894,0,0,1,.987,2.542,1.914,1.914,0,0,0,2.538,2.538,1.9,1.9,0,0,1,2.542.987,1.89,1.89,0,0,0,3.49,0,1.894,1.894,0,0,1,2.542-.987,1.914,1.914,0,0,0,2.538-2.538,1.894,1.894,0,0,1,.987-2.542,1.89,1.89,0,0,0,0-3.49,1.9,1.9,0,0,1-.987-2.542,1.914,1.914,0,0,0-2.538-2.538,1.894,1.894,0,0,1-2.542-.987A1.89,1.89,0,0,0,10.255,4.188Z" fill="none" stroke="#006aff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <path d="M15,12a3,3,0,1,1-3-3A3,3,0,0,1,15,12Z" fill="none" stroke="#006aff" stroke-width="2"/>
                        </g>
                    </svg>
                    <?php _e('Settings', 'ai-chat-search'); ?>
                </a>
                <a href="?page=ai-chat-search&tab=ai-chat"
                   class="nav-tab <?php echo $active_tab == 'ai-chat' ? 'nav-tab-active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="15.68" viewBox="0 0 20 19.6">
                        <g transform="translate(-2 -2)">
                            <path d="M21,13V7c0-1.886,0-2.828-.586-3.414S18.886,3,17,3H7c-1.886,0-2.828,0-3.414.586S3,5.114,3,7v6c0,1.886,0,2.828.586,3.414S5.114,17,7,17h.5a.5.5,0,0,1,.5.5v2.65a.433.433,0,0,0,.688.35L13.1,17.294A1.538,1.538,0,0,1,14,17h3c1.886,0,2.828,0,3.414-.586S21,14.886,21,13Z" fill="#6aa9ff" opacity="0.1"/>
                            <path d="M8,10h.01" fill="none" stroke="#006aff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <path d="M12,10h.01" fill="none" stroke="#006aff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <path d="M16,10h.01" fill="none" stroke="#006aff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                            <path d="M21,13V7c0-1.886,0-2.828-.586-3.414S18.886,3,17,3H7c-1.886,0-2.828,0-3.414.586S3,5.114,3,7v6c0,1.886,0,2.828.586,3.414S5.114,17,7,17h.5a.5.5,0,0,1,.5.5v2.65a.433.433,0,0,0,.688.35L13.1,17.294A1.538,1.538,0,0,1,14,17h3c1.886,0,2.828,0,3.414-.586S21,14.886,21,13Z" fill="none" stroke="#006aff" stroke-linejoin="round" stroke-width="2"/>
                        </g>
                    </svg>
                    <?php _e('AI Chat', 'ai-chat-search'); ?>
                </a>
                <a href="?page=ai-chat-search&tab=database"
                   class="nav-tab <?php echo $active_tab == 'database' ? 'nav-tab-active' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14.4" height="16" viewBox="0 0 18 20">
                        <g transform="translate(-3 -2)">
                            <path d="M20,7c0,2.209-3.582,4-8,4S4,9.209,4,7s3.582-4,8-4S20,4.791,20,7Z" fill="#004dff" opacity="0.1"/>
                            <path d="M19.75,13.477a5.3,5.3,0,0,1-1.981,1.575A13.22,13.22,0,0,1,12,16.25a13.22,13.22,0,0,1-5.769-1.2A5.3,5.3,0,0,1,4.25,13.477V17c0,.959.784,1.894,2.2,2.6A12.726,12.726,0,0,0,12,20.75,12.726,12.726,0,0,0,17.545,19.6c1.421-.711,2.2-1.646,2.2-2.6Z" fill="#004dff" opacity="0.1"/>
                            <path d="M20,7c0,2.209-3.582,4-8,4S4,9.209,4,7s3.582-4,8-4S20,4.791,20,7Z" fill="none" stroke="#006aff" stroke-width="2"/>
                            <path d="M20,12c0,2.209-3.582,4-8,4s-8-1.791-8-4" fill="none" stroke="#006aff" stroke-width="2"/>
                            <path d="M4,7V17c0,2.209,3.582,4,8,4s8-1.791,8-4V7" fill="none" stroke="#006aff" stroke-width="2"/>
                        </g>
                    </svg>
                    <?php _e('Data Training', 'ai-chat-search'); ?>
                </a>
                <a href="?page=ai-chat-search&tab=stats"
                   class="nav-tab <?php echo $active_tab == 'stats' ? 'nav-tab-active' : ''; ?>">
                    <svg width="16" height="16" viewBox="2 3 19 19" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5V19C4 19.5523 4.44772 20 5 20H19" stroke="#0060ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M18 9L13 13.9999L10.5 11.4998L7 14.9998" stroke="#0060ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <?php _e('Stats & History', 'ai-chat-search'); ?>
                </a>
                <?php
                // Hook for Pro plugin to add additional tabs (e.g., License tab)
                do_action('listeo_ai_search_admin_nav_tabs', $active_tab);
                ?>
            </nav>

            <?php if ($active_tab == 'settings') : ?>
                <div class="airs-tab-content airs-settings-tab">
                    <?php $this->render_settings_tab(); ?>
                </div>
            <?php elseif ($active_tab == 'database') : ?>
                <div class="airs-tab-content airs-database-tab">
                    <?php $this->render_database_tab(); ?>
                </div>
            <?php elseif ($active_tab == 'stats') : ?>
                <div class="airs-tab-content airs-stats-tab">
                    <?php $this->render_stats_tab(); ?>
                </div>
            <?php elseif ($active_tab == 'ai-chat') : ?>
                <div class="airs-tab-content airs-ai-chat-tab">
                    <?php $this->render_ai_chat_tab(); ?>
                </div>
            <?php else : ?>
                <?php
                // Hook for Pro plugin to render custom tab content (e.g., License tab)
                do_action('listeo_ai_search_admin_tab_content', $active_tab);
                ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <!-- Single form wrapping all sections -->
        <form class="airs-ajax-form" data-section="settings-config" id="settings-form">

        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('API Configuration', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Configure your AI provider and rate limiting.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                    <!-- AI Provider Selection -->
                    <div class="airs-form-group">
                        <label for="listeo_ai_search_provider" class="airs-label">
                            <?php _e('AI Provider', 'ai-chat-search'); ?>
                        </label>

                        <!-- Hidden select for data storage -->
                        <select id="listeo_ai_search_provider" name="listeo_ai_search_provider" class="airs-input" style="display: none;">
                            <option value="openai" <?php selected(get_option('listeo_ai_search_provider', 'openai'), 'openai'); ?>><?php _e('OpenAI', 'ai-chat-search'); ?></option>
                            <option value="gemini" <?php selected(get_option('listeo_ai_search_provider', 'openai'), 'gemini'); ?>><?php _e('Google Gemini', 'ai-chat-search'); ?></option>
                            <option value="mistral" <?php selected(get_option('listeo_ai_search_provider', 'openai'), 'mistral'); ?>><?php _e('Mistral AI', 'ai-chat-search'); ?></option>
                        </select>

                        <!-- Custom Toggle Switch with Logos -->
                        <div class="ai-provider-toggle ai-provider-toggle-3" data-selected="<?php echo esc_attr(get_option('listeo_ai_search_provider', 'openai')); ?>">
                            <div class="ai-provider-option" data-value="openai">
                                <div class="ai-provider-logo">
                                    <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/icons/gpt.svg'); ?>" alt="ChatGPT">
                                </div>
                            </div>
                            <div class="ai-provider-option" data-value="gemini">
                                <div class="ai-provider-logo">
                                    <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/icons/gemini.svg'); ?>" alt="Gemini">
                                </div>
                            </div>
                            <div class="ai-provider-option" data-value="mistral">
                                <div class="ai-provider-logo">
                                    <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/icons/mistral.svg'); ?>" alt="Mistral">
                                </div>
                            </div>
                            <div class="ai-provider-slider"></div>
                        </div>

                        <div class="airs-help-text">
                            <?php _e('Choose between OpenAI, Google Gemini, or Mistral AI for AI-powered search and chat.', 'ai-chat-search'); ?>
                            <br>
                            <?php _e('<strong>Gemini and Mistral offer free tier. OpenAI requires $5 minimum balance.</strong>', 'ai-chat-search'); ?>
                        </div>
                    </div>

                    <!-- OpenAI API Key (shown when provider is OpenAI) -->
                    <div class="airs-form-group provider-field provider-openai" style="<?php echo get_option('listeo_ai_search_provider', 'openai') !== 'openai' ? 'display:none;' : ''; ?>">
                        <label for="listeo_ai_search_api_key" class="airs-label">
                            <?php _e('OpenAI API Key', 'ai-chat-search'); ?>
                        </label>
                        <input type="password" id="listeo_ai_search_api_key" name="listeo_ai_search_api_key" value="<?php echo esc_attr(get_option('listeo_ai_search_api_key')); ?>" class="airs-input" placeholder="sk-..." />

                        <!-- Test API Key Button -->
                        <div class="airs-api-test-wrapper" style="margin-top: 10px;">
                            <button type="button" id="test-api-key" class="airs-button airs-button-secondary" style="font-size: 13px; padding: 8px 16px;">
                                <span class="button-text">
                                    <?php _e('Test API Key', 'ai-chat-search'); ?>
                                </span>
                                <span class="button-spinner" style="display: none;">
                                    <span class="airs-spinner"></span>
                                    <?php _e('Testing...', 'ai-chat-search'); ?>
                                </span>
                            </button>
                            <div id="api-test-result" class="airs-api-test-result" style="margin-top: 8px; display: none;"></div>
                        </div>

                        <div class="airs-help-text">
                            <?php _e('Enter your OpenAI API key from the OpenAI Dashboard.', 'ai-chat-search'); ?>
                            <br><a href="https://docs.purethemes.net/listeo/knowledge-base/how-to-create-open-ai-api-key/" target="_blank"><?php _e('How to create Open AI API key →', 'ai-chat-search'); ?></a>
                        </div>
                    </div>

                    <!-- Gemini API Key (shown when provider is Gemini) -->
                    <div class="airs-form-group provider-field provider-gemini" style="<?php echo get_option('listeo_ai_search_provider', 'openai') !== 'gemini' ? 'display:none;' : ''; ?>">
                        <label for="listeo_ai_search_gemini_api_key" class="airs-label">
                            <?php _e('Google Gemini API Key', 'ai-chat-search'); ?>
                        </label>
                        <input type="password" id="listeo_ai_search_gemini_api_key" name="listeo_ai_search_gemini_api_key" value="<?php echo esc_attr(get_option('listeo_ai_search_gemini_api_key')); ?>" class="airs-input" placeholder="AIzaSy..." />

                        <!-- Test API Key Button for Gemini -->
                        <div class="airs-api-test-wrapper" style="margin-top: 10px;">
                            <button type="button" id="test-gemini-api-key" class="airs-button airs-button-secondary" style="font-size: 13px; padding: 8px 16px;">
                                <span class="button-text">
                                    <?php _e('Test API Key', 'ai-chat-search'); ?>
                                </span>
                                <span class="button-spinner" style="display: none;">
                                    <span class="airs-spinner"></span>
                                    <?php _e('Testing...', 'ai-chat-search'); ?>
                                </span>
                            </button>
                            <div id="gemini-api-test-result" class="airs-api-test-result" style="margin-top: 8px; display: none;"></div>
                        </div>

                        <div class="airs-help-text">
                            <?php printf(__('Enter your Google Gemini API key from %1$sGoogle AI Studio%2$s. Note: %3$sfree tier has very low rate limits%4$s — we recommend a paid plan.', 'ai-chat-search'), '<a href="https://docs.purethemes.net/listeo/knowledge-base/how-to-create-gemini-ai-api-key/" target="_blank">', '</a>', '<strong>', '</strong>'); ?>
                        </div>
                    </div>

                    <!-- Mistral API Key (shown when provider is Mistral) -->
                    <div class="airs-form-group provider-field provider-mistral" style="<?php echo get_option('listeo_ai_search_provider', 'openai') !== 'mistral' ? 'display:none;' : ''; ?>">
                        <label for="listeo_ai_search_mistral_api_key" class="airs-label">
                            <?php _e('Mistral AI API Key', 'ai-chat-search'); ?>
                        </label>
                        <input type="password" id="listeo_ai_search_mistral_api_key" name="listeo_ai_search_mistral_api_key" value="<?php echo esc_attr(get_option('listeo_ai_search_mistral_api_key')); ?>" class="airs-input" placeholder="..." />

                        <!-- Test API Key Button for Mistral -->
                        <div class="airs-api-test-wrapper" style="margin-top: 10px;">
                            <button type="button" id="test-mistral-api-key" class="airs-button airs-button-secondary" style="font-size: 13px; padding: 8px 16px;">
                                <span class="button-text">
                                    <?php _e('Test API Key', 'ai-chat-search'); ?>
                                </span>
                                <span class="button-spinner" style="display: none;">
                                    <span class="airs-spinner"></span>
                                    <?php _e('Testing...', 'ai-chat-search'); ?>
                                </span>
                            </button>
                            <div id="mistral-api-test-result" class="airs-api-test-result" style="margin-top: 8px; display: none;"></div>
                        </div>

                        <div class="airs-help-text">
                            <?php _e('Enter your Mistral AI API key from the Mistral Console. Mistral is hosted in', 'ai-chat-search'); ?> <a href="https://help.mistral.ai/en/articles/347629-where-do-you-store-my-data-or-my-organization-s-data" target="_blank"><?php _e('European Union', 'ai-chat-search'); ?></a>.
                            <br><a href="https://docs.purethemes.net/listeo/knowledge-base/how-to-create-mistral-ai-api-key/" target="_blank"><?php _e('Get Mistral API Key →', 'ai-chat-search'); ?></a>
                        </div>
                    </div>

                    <!-- Rate Limit Settings - Collapsible -->
                    <div class="airs-collapsible-section">
                        <div class="airs-collapsible-header" data-section="rate-limits">
                            <span class="airs-collapsible-title">
                                <span class="dashicons dashicons-shield"></span>
                                <?php _e('Rate Limit Settings', 'ai-chat-search'); ?>
                            </span>
                            <span class="airs-collapsible-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </span>
                        </div>
                        <div class="airs-collapsible-content">
                            <div class="airs-form-group">
                                <label for="listeo_ai_search_rate_limit_per_hour" class="airs-label">
                                    <?php _e('Global API Rate Limit (per hour)', 'ai-chat-search'); ?>
                                </label>
                                <input type="number" id="listeo_ai_search_rate_limit_per_hour" name="listeo_ai_search_rate_limit_per_hour" value="<?php echo esc_attr(get_option('listeo_ai_search_rate_limit_per_hour', 1000)); ?>" min="100" max="10000" step="1" class="airs-input airs-input-small" />
                                <span><?php _e('API calls per hour', 'ai-chat-search'); ?></span>
                                <div class="airs-help-text">
                                    <?php _e('Maximum number of API calls allowed per hour (includes chat completions, and search operations).', 'ai-chat-search'); ?>
                                    <?php
                                    // Show current usage if available
                                    $rate_limit_key = 'listeo_ai_rate_limit_' . date('Y-m-d-H');
                                    $current_calls = get_transient($rate_limit_key) ?: 0;
                                    if ($current_calls > 0) {
                                        echo '<br><span style="background-color: rgba(0, 123, 255, 0.1); padding: 3px 7px; display: inline-block; margin-top: 5px; border-radius: 3px; color: rgba(0, 123, 255, 1);">Current hour usage: <strong style="color: rgba(0, 123, 255, 1)">' . $current_calls . '</strong> calls</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- User Rate Limiting - Per IP Address -->
                            <div class="airs-form-group">
                                <label class="airs-label"><?php _e('User Rate Limit - Per IP Address', 'ai-chat-search'); ?> <a href="#" id="clear-ip-rate-limits" style="color: #dc3545; text-decoration: underline; font-weight: 400 !important;"><?php _e('Clear all IP limits', 'ai-chat-search'); ?></a></label>
                                <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="number" id="listeo_ai_chat_rate_limit_tier1" name="listeo_ai_chat_rate_limit_tier1" value="<?php echo esc_attr(get_option('listeo_ai_chat_rate_limit_tier1', 10)); ?>" min="1" max="100" class="airs-input" style="width: 80px;" />
                                        <span style="font-size: 12px; color: #666;"><?php _e('/min', 'ai-chat-search'); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="number" id="listeo_ai_chat_rate_limit_tier2" name="listeo_ai_chat_rate_limit_tier2" value="<?php echo esc_attr(get_option('listeo_ai_chat_rate_limit_tier2', 30)); ?>" min="1" max="500" class="airs-input" style="width: 80px;" />
                                        <span style="font-size: 12px; color: #666;"><?php _e('/15min', 'ai-chat-search'); ?></span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="number" id="listeo_ai_chat_rate_limit_tier3" name="listeo_ai_chat_rate_limit_tier3" value="<?php echo esc_attr(get_option('listeo_ai_chat_rate_limit_tier3', 100)); ?>" min="1" max="10000" class="airs-input" style="width: 80px;" />
                                        <span style="font-size: 12px; color: #666;"><?php _e('/day', 'ai-chat-search'); ?></span>
                                    </div>
                                </div>
                                <div class="airs-help-text"><?php _e('Max chat messages per IP address in each time window. Enforced server-side.', 'ai-chat-search'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Provider Change Confirmation Modal -->
                    <div id="provider-change-modal" class="airs-modal" style="display: none;">
                        <div class="airs-modal-overlay"></div>
                        <div class="airs-modal-content">
                            <div class="airs-modal-header">
                                <h3><?php _e('Switch AI Provider?', 'ai-chat-search'); ?></h3>
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #ff9800;">
                                    <path d="M12 2L1 21H23L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                    <path d="M12 9V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <circle cx="12" cy="17" r="1" fill="currentColor"/>
                                </svg>
                            </div>
                            <div class="airs-modal-body">
                                <p><?php _e('Switching to a different AI provider will:', 'ai-chat-search'); ?></p>
                                <ul class="airs-modal-list">
                                    <li><?php _e('Clear all existing embeddings', 'ai-chat-search'); ?></li>
                                    <li><strong><?php _e('Require retraining with the new provider', 'ai-chat-search'); ?></strong></li>
                                </ul>
                                <p style="margin-top: 16px; color: #666; font-size: 14px;">
                                    <?php _e('This action cannot be undone. Your previous training data will be permanently deleted.', 'ai-chat-search'); ?>
                                </p>
                            </div>
                            <div class="airs-modal-footer">
                                <button type="button" class="airs-button airs-button-secondary" id="modal-cancel-btn">
                                    <?php _e('Cancel', 'ai-chat-search'); ?>
                                </button>
                                <button type="button" class="airs-button airs-button-danger" id="modal-confirm-btn">
                                    <span class="button-text"><?php _e('Yes, Clear Embeddings', 'ai-chat-search'); ?></span>
                                    <span class="button-spinner" style="display: none;">
                                        <span class="airs-spinner"></span>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

            </div>
        </div>

        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path><path d="m15 5 3 3"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Search Quality & Thresholds', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Fine-tune search quality and result filtering parameters.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                    <!-- Minimum Match Percentage - Always visible -->
                    <div class="airs-form-group">
                        <label for="listeo_ai_search_min_match_percentage" class="airs-label">
                            <?php _e('Minimum Match Percentage', 'ai-chat-search'); ?>
                        </label>

                        <?php
                        $min_match_value = intval(get_option('listeo_ai_search_min_match_percentage', 50));
                        // Determine initial color class and color based on value
                        if ($min_match_value < 20) {
                            $quality_class = 'quality-very-low';
                            $quality_color = '#3498db';
                            $quality_label = __('Loose - many results, lower relevance', 'ai-chat-search');
                        } elseif ($min_match_value < 40) {
                            $quality_class = 'quality-low';
                            $quality_color = '#2980b9';
                            $quality_label = __('Broad - more results, some may be less relevant', 'ai-chat-search');
                        } elseif ($min_match_value < 60) {
                            $quality_class = 'quality-balanced';
                            $quality_color = '#27ae60';
                            $quality_label = __('Balanced - good mix of quantity and quality', 'ai-chat-search');
                        } elseif ($min_match_value < 80) {
                            $quality_class = 'quality-high';
                            $quality_color = '#73a916';
                            $quality_label = sprintf(__('Quality focused - pay attention because %syou might start getting little results%s', 'ai-chat-search'), '<strong>', '</strong>');
                        } else {
                            $quality_class = 'quality-very-high';
                            $quality_color = '#c0392b';
                            $quality_label = sprintf(__('Very strict — %syou might get little to no results%s', 'ai-chat-search'), '<strong>', '</strong>');
                        }
                        ?>
                        <div class="airs-quality-slider-container">
                            <div class="airs-quality-value-display <?php echo esc_attr($quality_class); ?>" id="min-match-display">
                                <span class="airs-quality-value-badge <?php echo esc_attr($quality_class); ?>" id="min-match-badge"><?php echo esc_html($min_match_value); ?>%</span>
                                <span class="airs-quality-value-label" id="min-match-label"><?php echo wp_kses($quality_label, array('strong' => array())); ?></span>
                            </div>
                            <div class="airs-quality-slider-wrapper">
                                <div class="airs-quality-slider-track <?php echo esc_attr($quality_class); ?>" id="min-match-track" style="--slider-glow-position: <?php echo esc_attr($min_match_value); ?>%;"></div>
                                <input type="range"
                                       id="listeo_ai_search_min_match_percentage"
                                       name="listeo_ai_search_min_match_percentage"
                                       value="<?php echo esc_attr($min_match_value); ?>"
                                       min="0"
                                       max="100"
                                       step="5"
                                       class="airs-quality-slider <?php echo esc_attr($quality_class); ?>" />
                            </div>
                        </div>

                        <div class="airs-help-text" style="margin-top: 12px;">
                            <?php echo sprintf(
                                __('Acts as a <strong>quality filter</strong> for search results and AI answers. Only <strong><span title="%s" style="cursor: help; border-bottom: 1px dotted currentColor;">sources</span> scoring above this level</strong> will be shown or used by the AI to answer questions.', 'ai-chat-search'),
                                esc_attr__('pages, documents, posts, products, listings, etc.', 'ai-chat-search')
                            ); ?>
                        </div>
                    </div>

                    <!-- Additional Thresholds - Collapsible -->
                    <div class="airs-collapsible-section">
                        <div class="airs-collapsible-header" data-section="search-thresholds">
                            <span class="airs-collapsible-title">
                                <?php _e('Additional Threshold Settings', 'ai-chat-search'); ?>
                            </span>
                            <span class="airs-collapsible-toggle">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </span>
                        </div>
                        <div class="airs-collapsible-content">
                            <div class="airs-form-row">
                                <!-- RAG Sources Limit - Show for Pro OR when Listeo is not available (standalone use) -->
                                <?php if (AI_Chat_Search_Pro_Manager::is_pro_active() || !Listeo_AI_Detection::is_listeo_available()): ?>
                                <div class="airs-form-group">
                                    <label for="listeo_ai_chat_rag_sources_limit" class="airs-label">
                                        <?php _e('Number of Content Sources to Send to the AI', 'ai-chat-search'); ?>
                                    </label>
                                    <input type="number" id="listeo_ai_chat_rag_sources_limit" name="listeo_ai_chat_rag_sources_limit" value="<?php echo esc_attr(get_option('listeo_ai_chat_rag_sources_limit', 5)); ?>" min="2" max="10" step="1" class="airs-input airs-input-small" />
                                    <span><?php _e('sources', 'ai-chat-search'); ?></span>
                                    <div class="airs-help-text">
                                        <?php _e('For RAG responses <strong>(when searching pages, posts etc.)</strong>. Not related to WooCommerce products!', 'ai-chat-search'); ?>
                                        <br>
                                        <span style="color: #27ae60;">5</span> = <?php _e('Balanced (recommended)', 'ai-chat-search'); ?>,
                                        <span style="color: #2980b9;">3</span> = <?php _e('Faster/cheaper', 'ai-chat-search'); ?>,
                                        <span style="color: #f39c12;">10</span> = <?php _e('More context (not recommended)', 'ai-chat-search'); ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <div class="airs-form-group">
                                    <label class="airs-label">
                                        <?php _e('Conversation Context Length', 'ai-chat-search'); ?>
                                    </label>
                                    <?php $context_length = get_option('listeo_ai_chat_context_length', 'normal'); ?>
                                    <div class="airs-theme-toggle airs-theme-toggle--text">
                                        <button type="button" class="airs-theme-btn<?php echo $context_length === 'short' ? ' active' : ''; ?>" data-value="short" title="<?php esc_attr_e('Short, ~3 user messages, lowest cost', 'ai-chat-search'); ?>">
                                            <?php _e('Short', 'ai-chat-search'); ?>
                                        </button>
                                        <button type="button" class="airs-theme-btn<?php echo $context_length === 'normal' ? ' active' : ''; ?>" data-value="normal" title="<?php esc_attr_e('Normal, ~6 user messages', 'ai-chat-search'); ?>">
                                            <?php _e('Normal', 'ai-chat-search'); ?>
                                        </button>
                                        <button type="button" class="airs-theme-btn<?php echo $context_length === 'long' ? ' active' : ''; ?>" data-value="long" title="<?php esc_attr_e('Long, ~18 user messages', 'ai-chat-search'); ?>">
                                            <?php _e('Long', 'ai-chat-search'); ?>
                                        </button>
                                        <input type="hidden" name="listeo_ai_chat_context_length" id="listeo_ai_chat_context_length" value="<?php echo esc_attr($context_length); ?>">
                                    </div>
                                    <div class="airs-help-text">
                                        <strong><?php _e('How many previous user messages are included as context for the AI.', 'ai-chat-search'); ?></strong> <?php _e('Short prevents context pollution and saves tokens. Longer gives the AI more memory of previous user questions.', 'ai-chat-search'); ?>
                                        <br>
                                        <span style="color: #27ae60;"><?php _e('Short', 'ai-chat-search'); ?></span> = <?php _e('~3 user messages', 'ai-chat-search'); ?>,
                                        <span style="color: #2980b9;"><?php _e('Normal', 'ai-chat-search'); ?></span> = <?php _e('~6 user messages', 'ai-chat-search'); ?>,
                                        <span style="color: #f39c12;"><?php _e('Long', 'ai-chat-search'); ?></span> = <?php _e('~18 user messages', 'ai-chat-search'); ?>
                                    </div>
                                </div>

                                <?php if ((class_exists('Listeo_AI_Detection') && Listeo_AI_Detection::is_listeo_available()) || class_exists('WooCommerce')): ?>
                                <div class="airs-form-group">
                                    <label for="listeo_ai_search_best_match_threshold" class="airs-label">
                                        <?php _e('Best Match Badge Threshold', 'ai-chat-search'); ?>
                                    </label>
                                    <input type="number" id="listeo_ai_search_best_match_threshold" name="listeo_ai_search_best_match_threshold" value="<?php echo esc_attr(get_option('listeo_ai_search_best_match_threshold', 75)); ?>" min="50" max="95" step="1" class="airs-input airs-input-small" />
                                    <span>%</span>
                                    <div class="airs-help-text">
                                        <?php _e('Show <strong>"Best Match" badge</strong> for search results above this similarity percentage. Higher values make the badge more exclusive.', 'ai-chat-search'); ?>
                                        <br>
                                        <span style="color: #27ae60;">75%</span> = Balanced,
                                        <span style="color: #2980b9;">85%</span> = More exclusive,
                                        <span style="color: #f39c12;">65%</span> = More badges
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (class_exists('Listeo_AI_Detection') && Listeo_AI_Detection::is_listeo_available()): ?>
                                <div class="airs-form-group">
                                    <label for="listeo_ai_search_max_results" class="airs-label">
                                        <?php _e('Maximum AI Top Picks Results', 'ai-chat-search'); ?>
                                    </label>
                                    <input type="number" id="listeo_ai_search_max_results" name="listeo_ai_search_max_results" value="<?php echo esc_attr(get_option('listeo_ai_search_max_results', 10)); ?>" min="3" max="50" step="1" class="airs-input airs-input-small" />
                                    <span><?php _e('results', 'ai-chat-search'); ?></span>
                                    <div class="airs-help-text">
                                        <?php _e('Maximum number of <strong>"Best Match" badge</strong> results to display in <strong>search field shortcode</strong> dropdown.', 'ai-chat-search'); ?>
                                        <br>
                                        <span style="color: #27ae60;">5</span> = Balanced (recommended),
                                        <span style="color: #2980b9;">3</span> = Compact,
                                        <span style="color: #f39c12;">20</span> = Comprehensive
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="airs-form-group">
                        <label class="airs-checkbox-label">
                            <input type="checkbox" name="listeo_ai_search_enable_analytics" value="1" <?php checked(get_option('listeo_ai_search_enable_analytics'), 1); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php _e('Search Analytics', 'ai-chat-search'); ?>
                                <small><?php _e('Enable search analytics tracking', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                        <div class="airs-help-text">
                            <?php _e('When enabled, tracks search queries, response times, and usage patterns to help improve search performance. Data is anonymized and stored locally.', 'ai-chat-search'); ?>
                        </div>
                        <?php if (get_option('listeo_ai_search_enable_analytics')): ?>
                            <div style="padding: 8px 10px;
    background: #ecfdf5;
    border-radius: 4px;
    border-left: 3px solid #059669;
    margin-top: 8px;color: #047857">
                                <?php
                                if (class_exists('Listeo_AI_Search_Analytics')) {
                                    $analytics = Listeo_AI_Search_Analytics::get_analytics(7);
                                    echo '<strong>' . esc_html__('Last 7 days:', 'ai-chat-search') . '</strong> ' . $analytics['total_searches'] . ' ' . esc_html__('searches', 'ai-chat-search') . ', ';
                                    echo $analytics['avg_response_time'] . 'ms ' . esc_html__('avg response', 'ai-chat-search');
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>

            </div>
        </div>

        <?php if ($this->is_listeo_theme_active()) : ?>
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Search Suggestions', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Help users discover what they can search for with intelligent suggestions.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                    <div class="airs-form-group">
                        <label class="airs-checkbox-label">
                            <input type="checkbox" name="listeo_ai_search_suggestions_enabled" value="1" <?php checked(get_option('listeo_ai_search_suggestions_enabled'), 1); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php _e('Enable search suggestions', 'ai-chat-search'); ?>
                                <small><?php _e('Show helpful search suggestions below the search input to guide users.', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                    </div>
                    
                    <div class="airs-form-group">
                        <label class="airs-label" style="font-weight: 600; margin-bottom: 8px; display: block;">
                            <?php _e('Suggestion Source:', 'ai-chat-search'); ?>
                        </label>
                        <label class="airs-checkbox-label">
                            <input type="radio" name="listeo_ai_search_suggestions_source" value="top_searches" <?php checked(get_option('listeo_ai_search_suggestions_source', 'top_searches'), 'top_searches'); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text"><?php _e('Show top 5 most popular searches', 'ai-chat-search'); ?></span>
                        </label>
                        <label class="airs-checkbox-label">
                            <input type="radio" name="listeo_ai_search_suggestions_source" value="top_searches_10" <?php checked(get_option('listeo_ai_search_suggestions_source', 'top_searches'), 'top_searches_10'); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text"><?php _e('Show top 10 most popular searches', 'ai-chat-search'); ?></span>
                        </label>
                        <label class="airs-checkbox-label">
                            <input type="radio" name="listeo_ai_search_suggestions_source" value="custom" <?php checked(get_option('listeo_ai_search_suggestions_source', 'top_searches'), 'custom'); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text"><?php _e('Use custom suggestions (comma-separated)', 'ai-chat-search'); ?></span>
                        </label>
                    </div>
                    
                    <div class="airs-form-group">
                        <label for="listeo_ai_search_custom_suggestions" class="airs-label">
                            <?php _e('Custom Suggestions (comma-separated):', 'ai-chat-search'); ?>
                        </label>
                        <textarea id="listeo_ai_search_custom_suggestions" name="listeo_ai_search_custom_suggestions" rows="3" class="airs-input" placeholder="pet-friendly apartments, cozy cafes"><?php echo esc_textarea(get_option('listeo_ai_search_custom_suggestions', '')); ?></textarea>
                        <div class="airs-help-text">
                            <?php _e('Enter search suggestions separated by commas. These will be displayed when "custom suggestions" is selected above.', 'ai-chat-search'); ?>
                            <br><strong><?php _e('Examples:', 'ai-chat-search'); ?></strong>
                            pet-friendly apartments, cozy cafes with wifi, outdoor wedding venues, 24/7 gyms downtown
                        </div>
                    </div>

            </div>
        </div>

        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M7 12h10"></path><path d="M10 18h4"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Search Refining', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Extra AI-powered search features and optimizations.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                    <div class="airs-form-group"  style="background: #fff;
    padding: 20px;
    border-radius: 5px;
    border: 1px solid #e0e0e0;">
                        <label class="airs-checkbox-label">
                            <input type="checkbox" name="listeo_ai_search_query_expansion" value="1" <?php checked(get_option('listeo_ai_search_query_expansion', false), 1); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php _e('AI Query Expansion', 'ai-chat-search'); ?>
                            </span>
                        </label>
                        <div class="airs-help-text">
                        <span style="color: #f00;">It’s an experimental feature. </span> <br>

                            <?php _e('Expands queries with related keywords to find more relevant results, but may return broader matches.', 'ai-chat-search'); ?>
<br>
                            <strong><?php _e('Examples:', 'ai-chat-search'); ?></strong><br>
                            ➡️ "car broken down" → auto repair, mechanic, garage<br>
                            ➡️ "need somewhere to sleep" → hotels, hostels, apartments<br>
                            ➡️ "romantic evening" → restaurants, theaters, spas<br>
                           <small style="color: #666; display: block; margin-top: 10px; font-size: 13px;"><?php _e('⚠️ Adds ~1s latency per search due to additional AI processing', 'ai-chat-search'); ?></small>
                        </div>

                    </div>

            </div>
        </div>
        <?php endif; ?>

        <?php
        // AI Semantic Search Field - hide when FREE version is active and Listeo theme is active
        // (Listeo theme already has integrated search; this shortcode is for standalone/Pro usage)
        if (AI_Chat_Search_Pro_Manager::is_pro_active() || !Listeo_AI_Detection::is_listeo_available()) :
        ?>
        <!-- AI Semantic Search Field - Shortcode Builder -->
        <div class="airs-card airs-card-toggleable" data-toggle-id="semantic-search-field">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path><path d="M11 8v6"></path><path d="M8 11h6"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('AI Semantic Search Field', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Add AI-powered semantic search to any page using a shortcode.', 'ai-chat-search'); ?></p>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2 airs-card-toggle-icon"></span>
            </div>
            <div class="airs-card-body">
                <?php $this->render_search_field_shortcode_builder(); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="airs-card airs-card-toggleable" data-toggle-id="developer-debug">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 16 4-4-4-4"></path><path d="m6 8-4 4 4 4"></path><path d="m14.5 4-5 16"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Developer & Debug Options', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Advanced options for development and troubleshooting.', 'ai-chat-search'); ?></p>
                </div>
                <span class="dashicons dashicons-arrow-down-alt2 airs-card-toggle-icon"></span>
            </div>
            <div class="airs-card-body">
                    <div class="airs-form-group">
                        <label class="airs-checkbox-label">
                            <input type="checkbox" name="listeo_ai_search_debug_mode" value="1" <?php checked(get_option('listeo_ai_search_debug_mode'), 1); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php _e('Debug Mode', 'ai-chat-search'); ?>
                                <small><?php _e('Enable debug logging to wp-content/debug.log', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                        <div class="airs-help-text"><?php _e('When enabled, detailed search information will be logged to help troubleshoot issues. Make sure WP_DEBUG_LOG is enabled in wp-config.php.', 'ai-chat-search'); ?></div>
                    </div>

                    <div class="airs-form-group">
                        <label class="airs-checkbox-label">
                            <input type="checkbox" name="listeo_ai_chat_lazy_load" value="1" <?php checked(get_option('listeo_ai_chat_lazy_load'), 1); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                Lazy Load Chatbot
                                <small><?php _e('Load chatbot scripts only when the floating widget is opened', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                        <div class="airs-help-text"><?php _e('Defers loading chatbot JavaScript until the user opens the chat. Improves page load performance. Only applies to the floating widget, not the shortcode.', 'ai-chat-search'); ?></div>
                    </div>

                    <!-- Database Tables Status -->
                    <div class="airs-form-group">
                        <label class="airs-label"><?php _e('Database Tables Status', 'ai-chat-search'); ?></label>
                        <?php
                        global $wpdb;
                        $tables = array(
                            $wpdb->prefix . 'listeo_ai_embeddings' => __('Embeddings', 'ai-chat-search'),
                            $wpdb->prefix . 'listeo_ai_chat_history' => __('Chat History', 'ai-chat-search'),
                            $wpdb->prefix . 'listeo_ai_contact_messages' => __('Contact Messages', 'ai-chat-search'),
                        );
                        $missing_tables = array();
                        $missing_columns = array();

                        foreach ($tables as $table_name => $label) {
                            if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
                                $missing_tables[] = $label;
                            }
                        }

                        // Check for ip_address column in chat_history table
                        $chat_history_table = $wpdb->prefix . 'listeo_ai_chat_history';
                        if ($wpdb->get_var("SHOW TABLES LIKE '{$chat_history_table}'") === $chat_history_table) {
                            $ip_column = $wpdb->get_results("SHOW COLUMNS FROM {$chat_history_table} LIKE 'ip_address'");
                            if (empty($ip_column)) {
                                $missing_columns[] = __('Chat History: ip_address column', 'ai-chat-search');
                            }
                        }

                        $has_issues = !empty($missing_tables) || !empty($missing_columns);
                        ?>
                        <?php if (!$has_issues): ?>
                        <div style="padding: 8px 10px; background: #ecfdf5; border-radius: 4px; border-left: 3px solid #059669; margin-top: 8px;">
                            <span style="color: #047857;">
                                <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                <?php _e('All database tables are properly configured.', 'ai-chat-search'); ?>
                            </span>
                        </div>
                        <?php else: ?>
                        <div style="padding: 8px 10px; background: #fef2f2; border-radius: 4px; border-left: 3px solid #ef4444; margin-top: 8px;">
                            <span style="color: #dc2626;">
                                <span class="dashicons dashicons-warning" style="vertical-align: middle;"></span>
                                <?php
                                $issues = array();
                                if (!empty($missing_tables)) {
                                    $issues[] = sprintf(__('Missing tables: %s', 'ai-chat-search'), implode(', ', $missing_tables));
                                }
                                if (!empty($missing_columns)) {
                                    $issues[] = sprintf(__('Missing columns: %s', 'ai-chat-search'), implode(', ', $missing_columns));
                                }
                                echo implode('<br>', $issues);
                                ?>
                            </span>
                            <div style="margin-top: 8px; font-size: 13px; color: #dc2626; font-weight: 500;">
                                <?php _e('To fix: Deactivate and reactivate the plugin.', 'ai-chat-search'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="airs-form-group">
                        <label class="airs-label"><?php _e('Server Info', 'ai-chat-search'); ?></label>
                        <?php
                        $server_memory = ini_get('memory_limit') ?: __('Unknown', 'ai-chat-search');
                        $server_memory_int = wp_convert_hr_to_bytes($server_memory);
                        $memory_low = $server_memory_int > 0 && $server_memory_int < 256 * 1024 * 1024;
                        $border_color = $memory_low ? '#ef4444' : '#94a3b8';
                        ?>
                        <div style="padding: 8px 10px; background: #f8fafc; border-radius: 4px; border-left: 3px solid <?php echo $border_color; ?>; margin-top: 8px; display: flex; gap: 20px; flex-wrap: wrap;">
                            <span><?php _e('WP Memory Limit:', 'ai-chat-search'); ?> <code><?php echo esc_html(defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '40M'); ?></code></span>
                            <span><?php _e('PHP Memory Limit:', 'ai-chat-search'); ?> <code><?php echo esc_html($server_memory); ?></code></span>
                            <span><?php _e('Max Execution Time:', 'ai-chat-search'); ?> <code><?php echo esc_html(ini_get('max_execution_time') ?: '0'); ?>s</code></span>
                        </div>
                        <?php if ($memory_low): ?>
                            <div class="airs-help-text" style="margin-top: 6px; color: #ef4444;"><?php _e('PHP memory limit is below 256 MB. This may cause issues with embedding generation and AI search. Please increase it in your hosting panel or php.ini.', 'ai-chat-search'); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="airs-form-group">
                        <label style="font-weight: 500;"><?php _e('Cache Management', 'ai-chat-search'); ?></label>
                        <div class="airs-help-text"><?php _e('Clear rate limiting and usage tracking data. Useful for testing or troubleshooting.', 'ai-chat-search'); ?></div>
                        <div class="airs-cache-actions" style="margin-top: 10px;">
                            <button type="button" id="listeo-clear-cache-btn" class="button button-secondary">
                                <span class="dashicons dashicons-trash" style="vertical-align: middle; margin-right: 5px;"></span>
                                <?php _e('Clear Cache', 'ai-chat-search'); ?>
                            </button>
                            <span id="listeo-clear-cache-status" style="margin-left: 10px; font-weight: bold;"></span>
                        </div>
                    </div>

            </div>
        </div>

        <!-- Sticky Footer with Save Button -->
        <div class="airs-sticky-footer">
            <div class="airs-sticky-footer-inner">
                <div class="airs-form-message airs-footer-message" style="display: none;"></div>
                <button type="submit" class="airs-button airs-button-primary">
                    <span class="button-text"><?php _e('Save Settings', 'ai-chat-search'); ?></span>
                    <span class="button-spinner" style="display: none;">
                        <span class="airs-spinner"></span>
                    </span>
                </button>
            </div>
        </div>

        </form>
        <?php
    }

    /**
     * Render database management tab
     */
    private function render_database_tab() {
        ?>
        <!-- Generate Embeddings Section -->
        <div class="airs-card airs-card-full-width">
            <div class="airs-card-header">
                <h3><?php _e('Select Content for Training', 'ai-chat-search'); ?></h3>
                <p>
                    <?php
                    printf(
                        /* translators: %s: "New content is automatically trained" (bold text) */
                        __('Disabling a post type will exclude it from training and search results. %s after adding/editing, no need to run this in future.', 'ai-chat-search'),
                        '<strong style="font-weight: 500; color: #111;">' . __('New content is automatically trained', 'ai-chat-search') . '</strong>'
                    );
                    ?>
                </p>
            </div>
            <div class="airs-card-body">

                <!-- STEP 1: Content Sources Selection -->
                <?php
                $universal_settings = new Listeo_AI_Search_Universal_Settings();
                $universal_settings->render_content_sources_cards();
                ?>

                <!-- STEP 2: Generation Controls -->

                <!-- Simple Training Interface -->
                <?php $this->render_simple_batch_interface(); ?>
            </div>
        </div>

        <!-- Database Management Section (Actions + Status) -->
        <div class="airs-card airs-card-toggleable airs-card-full-width" data-toggle-id="database-management">
            <div class="airs-card-header">
                <h3><?php _e('Database Management', 'ai-chat-search'); ?></h3>
                <p><?php _e('Manage your AI search database and monitor embedding statistics.', 'ai-chat-search'); ?></p>
                <span class="dashicons dashicons-arrow-down-alt2 airs-card-toggle-icon"></span>
            </div>
            <div class="airs-card-body">
                <div class="airs-db-management-grid">
                    <!-- Left Column: Database Actions -->
                    <div class="airs-db-actions-column">
                        <h4><?php _e('Database Actions', 'ai-chat-search'); ?></h4>

                        <div class="airs-form-group" style="margin-bottom:5px">
                            <label class="airs-checkbox-label">
                                <input type="checkbox" id="disable-auto-training" value="1" <?php checked(get_option('listeo_ai_disable_auto_training', false)); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text" style="font-weight:500;"><?php _e('Disable auto-training for new/edited content', 'ai-chat-search'); ?></span>
                            </label>
                            <script>
                            jQuery(function($){
                                $('#disable-auto-training').on('change', function(){
                                    var $cb = $(this),
                                        $custom = $cb.next('.airs-checkbox-custom'),
                                        $spinner = $('<span class="airs-spinner airs-spinner--small" style="margin-left:0;top:4px"></span>');
                                    $custom.hide().after($spinner);
                                    AIRS.ajax({
                                        action: 'listeo_ai_toggle_auto_training',
                                        data: { disabled: $cb.is(':checked') },
                                        success: function(r){ if(!r.success) $cb.prop('checked', !$cb.is(':checked')); },
                                        error: function(){ $cb.prop('checked', !$cb.is(':checked')); },
                                        complete: function(){ $spinner.remove(); $custom.show(); }
                                    });
                                });
                            });
                            </script>
                        </div>

                        <div class="airs-form-group">
                            <label class="airs-checkbox-label">
                                <input type="checkbox" id="disable-custom-fields" value="1" <?php checked(get_option('listeo_ai_disable_custom_fields', false)); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text" style="font-weight:500;"><?php _e('Disable custom fields in embeddings', 'ai-chat-search'); ?>
                                    <span class="airs-info-icon" tabindex="0" style="top:3px">
                                        <span class="dashicons dashicons-info-outline"></span>
                                        <span class="airs-info-tooltip"><?php _e('When enabled, auto-detected custom fields will be excluded from embedding content. Requires re-training after changing.', 'ai-chat-search'); ?></span>
                                    </span>
                                </span>
                            </label>
                            <script>
                            jQuery(function($){
                                $('#disable-custom-fields').on('change', function(){
                                    var $cb = $(this),
                                        $custom = $cb.next('.airs-checkbox-custom'),
                                        $spinner = $('<span class="airs-spinner airs-spinner--small" style="margin-left:0;top:4px"></span>');
                                    $custom.hide().after($spinner);
                                    AIRS.ajax({
                                        action: 'listeo_ai_toggle_custom_fields',
                                        data: { disabled: $cb.is(':checked') },
                                        success: function(r){ if(!r.success) $cb.prop('checked', !$cb.is(':checked')); },
                                        error: function(){ $cb.prop('checked', !$cb.is(':checked')); },
                                        complete: function(){ $spinner.remove(); $custom.show(); }
                                    });
                                });
                            });
                            </script>
                        </div>

                        <div class="airs-form-group">
                            <label for="listing-id-input" class="airs-label">
                                <?php _e('Check Embedding', 'ai-chat-search'); ?>
                            </label>
                            <div class="db-action-btns">
                                <input type="number" id="listing-id-input" placeholder="Enter Item ID" class="airs-input airs-input-small" />
                                <button type="button" id="check-embedding" class="airs-button airs-button-secondary"><?php _e('Check Embedding', 'ai-chat-search'); ?></button>
                            </div>
                            <div class="airs-help-text"><?php _e('Enter an item ID to view its embedding data and processed content.', 'ai-chat-search'); ?></div>
                        </div>

                        <div class="airs-form-group">
                            <label for="regenerate-listing-id-input" class="airs-label">
                                <?php _e('Regenerate Embedding', 'ai-chat-search'); ?>
                            </label>
                            <div class="db-action-btns">
                                <input type="number" id="regenerate-listing-id-input" placeholder="Enter Item ID" class="airs-input airs-input-small" />
                                <button type="button" id="regenerate-embedding" class="airs-button airs-button-primary">
                                    <span class="button-text"><?php _e('Regenerate Embedding', 'ai-chat-search'); ?></span>
                                    <span class="button-spinner" style="display: none;">
                                        <span class="airs-spinner"></span>
                                        <?php _e('Processing...', 'ai-chat-search'); ?>
                                    </span>
                                </button>
                            </div>
                            <div class="airs-help-text"><?php _e('Enter an item ID to regenerate its embedding data. This will fetch fresh content and create a new embedding.', 'ai-chat-search'); ?></div>
                            <div id="regenerate-embedding-result" style="margin-top: 10px; display: none;"></div>
                        </div>

                        <div class="airs-form-group">
                            <label for="delete-post-type-select" class="airs-label">
                                <?php _e('Delete Embeddings by Post Type', 'ai-chat-search'); ?>
                            </label>
                            <div class="db-action-btns">
                                <select id="delete-post-type-select" class="airs-input airs-input-small" style="max-width: 200px;">
                                    <option value=""><?php _e('Select post type...', 'ai-chat-search'); ?></option>
                                    <?php
                                    // Get enabled post types
                                    $enabled_post_types = Listeo_AI_Search_Database_Manager::get_enabled_post_types();
                                    foreach ($enabled_post_types as $post_type) {
                                        $post_type_obj = get_post_type_object($post_type);
                                        if ($post_type_obj) {
                                            echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type_obj->label) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <button type="button" id="delete-by-post-type" class="airs-button airs-button-danger"><?php _e('Delete Embeddings', 'ai-chat-search'); ?></button>
                            </div>
                            <div class="airs-help-text"><?php _e('Delete all embeddings for a specific post type. This will not delete the posts themselves, only their embeddings.', 'ai-chat-search'); ?></div>
                        </div>

                        <div class="airs-form-group">
                            <button type="button" id="clear-database" class="airs-button airs-button-danger"><?php _e('Clear All Embeddings', 'ai-chat-search'); ?></button>
                            <div class="airs-help-text"><?php _e('Delete all embeddings. You will need to regenerate them after clearing.', 'ai-chat-search'); ?></div>
                        </div>
                    </div>

                    <!-- Right Column: Database Status -->
                    <div class="airs-db-status-column">
                        <h4><?php _e('Database Status', 'ai-chat-search'); ?></h4>
                        <div id="database-status">
                            <div id="status-content">
                                <p><span class="airs-spinner" style="margin-right: 6px;"></span><?php _e('Loading database status...', 'ai-chat-search'); ?></p>
                            </div>
                            <div class="airs-form-actions">
                                <button type="button" id="refresh-status" class="airs-button airs-button-secondary"><?php _e('Refresh Status', 'ai-chat-search'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Embedding Viewer Section -->
        <div id="embedding-viewer" class="airs-card airs-card-full-width" style="display: none;">
            <div class="airs-card-header">
                <h3><?php _e('Embedding Data', 'ai-chat-search'); ?></h3>
            </div>
            <div class="airs-card-body">
                <div id="embedding-content"></div>
                <div class="airs-form-actions">
                    <button type="button" id="close-embedding" class="airs-button airs-button-secondary"><?php _e('Close', 'ai-chat-search'); ?></button>
                </div>
            </div>
        </div>

        <div id="action-result" class="airs-card" style="display: none;">
            <div class="airs-card-body">
                <div id="result-message-content" class="airs-alert"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI Semantic Search Field shortcode builder
     * Shown for all sites - excludes listing post type (Listeo has separate integration)
     */
    private function render_search_field_shortcode_builder() {
        // Get enabled post types from the training settings
        $enabled_post_types = get_option('listeo_ai_search_enabled_post_types', array());

        // Filter out attachment/media, PDF document, and listing post types
        // Listing post type has dedicated Listeo integration, so exclude it from this universal shortcode
        $enabled_post_types = array_filter($enabled_post_types, function($type) {
            // Always exclude these - listing has separate Listeo integration
            if (in_array($type, array('attachment', 'ai_pdf_document', 'listing'))) {
                return false;
            }
            return true;
        });

        // Get embedding counts per post type
        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_ai_embeddings';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

        $post_type_counts = array();
        if ($table_exists && !empty($enabled_post_types)) {
            foreach ($enabled_post_types as $post_type) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} e
                     INNER JOIN {$wpdb->posts} p ON e.listing_id = p.ID
                     WHERE p.post_type = %s AND p.post_status = 'publish'",
                    $post_type
                ));
                $post_type_counts[$post_type] = intval($count);
            }
        }

        // Get human-readable post type labels
        $post_type_labels = array();
        foreach ($enabled_post_types as $post_type) {
            $post_type_obj = get_post_type_object($post_type);
            if ($post_type_obj) {
                $post_type_labels[$post_type] = $post_type_obj->labels->name;
            } else {
                $post_type_labels[$post_type] = ucfirst($post_type);
            }
        }
        ?>
        <div class="airs-shortcode-builder" id="ai-search-field-builder">
            <?php if (empty($enabled_post_types)) : ?>
                <div class="airs-notice airs-notice-warning">
                    <strong><?php _e('No content types trained yet!', 'ai-chat-search'); ?></strong>
                    <p><?php _e('Please go to the "Data Training" tab first and train at least one content type before using this shortcode.', 'ai-chat-search'); ?></p>
                </div>
            <?php else : ?>
                <?php if ($this->is_listeo_theme_active()) : ?>
                <div style="padding: 12px 15px; background: #fff3e0; border-left: 4px solid #f57c00; margin-bottom: 15px; border-radius: 4px;">
                    <span style="font-size: 14px; color: #e65100;"><strong><?php _e('Using Listeo Theme?', 'ai-chat-search'); ?></strong> <?php printf(__('To use Semantic Search field with listings in Listeo please check <a href="%s" target="_blank" style="color: #e65100; font-weight: 600;">this article in docs</a>.', 'ai-chat-search'), 'https://docs.purethemes.net/listeo/knowledge-base/listeo-ai-smart-search/'); ?></span>
                </div>
                <?php endif; ?>

                <p class="airs-help-text">
                    <?php _e('<strong>Do not need chatbot? No problems - use this shortcode </strong> builder to create an AI-powered semantic search field. Select which content types to include in search results.', 'ai-chat-search'); ?>
                </p>

                <!-- Shortcode Preview Info -->
                <div class="airs-shortcode-info-box">
                    <strong><?php _e('How it works:', 'ai-chat-search'); ?></strong>
                    <ol>
                        <li><?php _e('Users type their search query in natural language', 'ai-chat-search'); ?></li>
                        <li><?php _e('AI finds semantically related content (not just keyword matches)', 'ai-chat-search'); ?></li>
                        <li><?php _e('Results are displayed in a dropdown according to Minimum Match Percentage setting', 'ai-chat-search'); ?></li>
                                </ol>
                </div>

                <!-- Post Types Selection -->
                <div class="airs-form-group">
                    <label class="airs-label">
                        <?php _e('Select Content Types to Search:', 'ai-chat-search'); ?>
                    </label>
                    <div class="airs-post-type-grid">
                        <?php foreach ($enabled_post_types as $post_type) :
                            $count = isset($post_type_counts[$post_type]) ? $post_type_counts[$post_type] : 0;
                            $label = isset($post_type_labels[$post_type]) ? $post_type_labels[$post_type] : ucfirst($post_type);
                        ?>
                        <label class="airs-checkbox-label airs-post-type-checkbox <?php echo $count === 0 ? 'disabled' : ''; ?>">
                            <input type="checkbox"
                                   class="shortcode-post-type"
                                   value="<?php echo esc_attr($post_type); ?>"
                                   data-label="<?php echo esc_attr($label); ?>"
                                   <?php echo $count === 0 ? 'disabled' : ''; ?>>
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php echo esc_html($label); ?>
                                <small class="<?php echo $count > 0 ? 'trained' : 'not-trained'; ?>">
                                    <?php
                                    if ($count > 0) {
                                        printf(_n('%d item trained', '%d items trained', $count, 'ai-chat-search'), $count);
                                    } else {
                                        _e('Not trained yet', 'ai-chat-search');
                                    }
                                    ?>
                                </small>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="airs-shortcode-builder-info">
                    <span><?php _e('You can index/train content to search in the', 'ai-chat-search'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=ai-chat-search&tab=database')); ?>"><?php _e('Data Training', 'ai-chat-search'); ?></a> <?php _e('tab.', 'ai-chat-search'); ?></span>
                </div>

                <!-- Additional Options -->
                <div class="airs-shortcode-form-row">
                    <div class="airs-form-group">
                        <label for="shortcode-placeholder" class="airs-label"><?php _e('Placeholder Text:', 'ai-chat-search'); ?></label>
                        <input type="text"
                               id="shortcode-placeholder"
                               class="airs-input"
                               value="<?php echo esc_attr__('Search anything...', 'ai-chat-search'); ?>"
                               placeholder="<?php esc_attr_e('Enter placeholder text', 'ai-chat-search'); ?>">
                    </div>
                    <div class="airs-form-group">
                        <label for="shortcode-limit" class="airs-label"><?php _e('Max Results:', 'ai-chat-search'); ?></label>
                        <input type="number"
                               id="shortcode-limit"
                               class="airs-input airs-input-small"
                               value="<?php echo esc_attr(get_option('listeo_ai_search_max_results', 10)); ?>"
                               min="1"
                               max="50">
                    </div>
                </div>

                <!-- Generated Shortcode -->
                <div class="airs-form-group airs-generated-shortcode">
                    <label class="airs-label">
                        <?php _e('Generated Shortcode:', 'ai-chat-search'); ?>
                    </label>
                    <div class="airs-generated-shortcode-row">
                        <input type="text"
                               id="generated-shortcode"
                               class="airs-input"
                               readonly
                               value='[ai_search_field]'>
                        <button type="button"
                                id="copy-shortcode-btn"
                                class="airs-button airs-button-primary">
                            <?php _e('Copy Shortcode', 'ai-chat-search'); ?>
                        </button>
                    </div>
                    <div class="airs-help-text">
                        <?php _e('Copy this shortcode and paste it into any page, post, or widget to display the AI semantic search field.', 'ai-chat-search'); ?>
                    </div>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render simple batch interface
     */
    private function render_simple_batch_interface() {
        ?>
        <div id="listing-detection-info" class="listing-detection-info">
            <div id="listing-count-text" class="listing-count-text">
                <span class="airs-spinner" style="margin-right: 6px;"></span><?php _e('Loading content information...', 'ai-chat-search'); ?>
            </div>
        </div>
        
        <div id="regeneration-controls">
            <div class="airs-form-actions">
                <button type="button" id="start-regeneration" class="airs-button airs-button-primary"><svg class="rocket-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path class="rocket-fire" d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg> <?php _e('Start Training', 'ai-chat-search'); ?></button>
                <button type="button" id="stop-regeneration" class="airs-button airs-button-secondary" style="display: none;"><?php _e('Stop', 'ai-chat-search'); ?></button>
            </div>
        </div>
        
        <div id="regeneration-progress" style="display: none; margin-top: 20px; padding: 20px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 6px; text-align: center;">
            <div class="rpa-icon-wrapper" style="display: inline-block; padding: 8px; padding-bottom: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="4 4 40 40" class="rpa-animated" style="overflow: visible;">
                    <!-- Robot body (bounces) -->
                    <g class="rpa-body">
                        <!-- Main body -->
                        <path fill="#DBE8FF" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M22.323,8.063h3.354c4.986,0,9.027,4.042,9.027,9.027v14.127c0,2.074-1.681,3.755-3.755,3.755h-13.9c-2.074,0-3.755-1.681-3.755-3.755V17.09C13.295,12.105,17.337,8.063,22.323,8.063z"/>
                        <!-- Ears -->
                        <path fill="#DBE8FF" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M35.786,19.973h-1.081v-4.273h1.081c1.18,0,2.136,0.956,2.136,2.136C37.923,19.016,36.966,19.973,35.786,19.973z"/>
                        <path fill="#DBE8FF" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M12.214,19.973h1.081v-4.273h-1.081c-1.18,0-2.136,0.956-2.136,2.136C10.077,19.016,11.034,19.973,12.214,19.973z"/>
                        <!-- Face visor -->
                        <path fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M28.373,22.115h-8.745c-2.099,0-3.8-1.701-3.8-3.8v-0.503c0-2.099,1.701-3.8,3.8-3.8h8.745c2.099,0,3.8,1.701,3.8,3.8v0.503C32.173,20.414,30.471,22.115,28.373,22.115z"/>
                        <!-- Eyes (dots) -->
                        <circle fill="#0060FF" cx="20.337" cy="18.063" r="0.75"/>
                        <circle fill="#0060FF" cx="27.545" cy="18.063" r="0.75"/>
                        <!-- Antenna line -->
                        <line fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" x1="37.923" y1="17.518" x2="37.923" y2="9.293"/>
                        <!-- Antenna light -->
                        <circle class="rpa-light" fill="#DBE8FF" stroke="#0060FF" stroke-width="1.5" cx="37.923" cy="8.063" r="1.229"/>
                        <!-- Arms/connectors -->
                        <path fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M34.705,24.234h0.984c1.64,0,2.969,1.329,2.969,2.969v7.769"/>
                        <path fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M13.295,24.234h-0.984c-1.64,0-2.969,1.329-2.969,2.969v7.769"/>
                        <!-- Feet -->
                        <path fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M36.348,37.282c0-1.276,1.034-2.31,2.31-2.31s2.31,1.034,2.31,2.31"/>
                        <path fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M11.652,37.282c0-1.276-1.034-2.31-2.31-2.31s-2.31,1.034-2.31,2.31"/>
                    </g>
                    <!-- Gear (on top, rotates) -->
                    <g class="rpa-gear">
                        <path fill="#DBE8FF" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" d="M30.672,37.089l0.231-0.494c0.348-0.747,0.025-1.635-0.722-1.983l-1.088-0.508c0.012-0.143,0.043-0.281,0.043-0.427c0-0.378-0.047-0.744-0.124-1.099l0.995-0.634c0.695-0.443,0.9-1.365,0.457-2.061l-0.293-0.46c-0.443-0.695-1.365-0.9-2.061-0.457l-1.008,0.642c-0.404-0.309-0.85-0.557-1.336-0.736v-1.192c0-0.824-0.668-1.492-1.492-1.492h-0.546c-0.824,0-1.492,0.668-1.492,1.492v1.192c-0.702,0.258-1.339,0.649-1.858,1.167l-1.065-0.497c-0.747-0.348-1.635-0.025-1.983,0.722l-0.231,0.494c-0.348,0.747-0.025,1.635,0.722,1.983l1.088,0.508c-0.012,0.143-0.043,0.281-0.043,0.427c0,0.378,0.047,0.744,0.124,1.099l-0.995,0.634c-0.695,0.443-0.9,1.365-0.457,2.061l0.293,0.46c0.443,0.695,1.365,0.9,2.061,0.457l1.008-0.642c0.404,0.309,0.85,0.557,1.336,0.736v1.192c0,0.824,0.668,1.492,1.492,1.492h0.546c0.824,0,1.492-0.668,1.492-1.492v-1.192c0.702-0.258,1.339-0.649,1.858-1.167l1.065,0.497C29.435,38.16,30.323,37.836,30.672,37.089z"/>
                        <circle fill="none" stroke="#0060FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" cx="24" cy="33.677" r="2.114"/>
                    </g>
                </svg>
            </div>
            <p style="margin: 0; font-size: 15px; color: #222; font-weight: 500;"><?php _e('Training in progress...', 'ai-chat-search'); ?></p>
            <p style="margin: 5px 0 0 0; font-size: 13px; color: #666;"><?php _e('This may take a while depending on the number of items selected', 'ai-chat-search'); ?></p>
        </div>
        <style>
        .rpa-icon-wrapper {
            overflow: visible;
        }
        .rpa-animated .rpa-gear {
            animation: gear-rotate 2s linear infinite;
            transform-origin: 24px 33.7px;
        }
        .rpa-animated .rpa-body {
            animation: body-bounce 2s ease-in-out infinite;
        }
        .rpa-animated .rpa-light {
            animation: light-pulse 1s ease-in-out infinite;
            transform-origin: 37.923px 8.063px;
        }
        @keyframes gear-rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(180deg); }
        }
        @keyframes body-bounce {
            0%, 100% { transform: translateY(0); }
            25%, 45% { transform: translateY(-4px); }
        }
        @keyframes light-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.5); }
        }
        </style>
        
        <div id="regeneration-log" style="display: none; margin-top: 15px;">
            <h4><?php _e('Progress Log:', 'ai-chat-search'); ?></h4>
            <div id="log-content" class="airs-log"></div>
        </div>

        <!-- Training Confirmation Modal -->
        <div id="training-confirm-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content">
                <div class="airs-modal-header">
                    <h3><?php _e('Start Training?', 'ai-chat-search'); ?></h3>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #0073aa;">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 7V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="16.5" r="1" fill="currentColor"/>
                    </svg>
                </div>
                <div class="airs-modal-body">
                    <p><?php _e('This will generate embeddings for all selected post types and consume API credits. You can stop anytime.', 'ai-chat-search'); ?></p>
                </div>
                <div class="airs-modal-footer">
                    <button type="button" class="airs-button airs-button-secondary" id="training-cancel-btn">
                        <?php _e('Cancel', 'ai-chat-search'); ?>
                    </button>
                    <button type="button" class="airs-button airs-button-primary" id="training-confirm-btn">
                        <?php _e('Yes, Start Training', 'ai-chat-search'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- API Key Missing Modal -->
        <div id="api-key-missing-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content">
                <div class="airs-modal-header">
                    <h3><?php _e('API Key Not Configured', 'ai-chat-search'); ?></h3>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #dc3545;">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                        <path d="M12 7V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="12" cy="16.5" r="1" fill="currentColor"/>
                    </svg>
                </div>
                <div class="airs-modal-body">
                    <p><?php
                        $provider = new Listeo_AI_Provider();
                        printf(
                            /* translators: %s: AI provider name (e.g. OpenAI, Gemini, Mistral) */
                            esc_html__('Please add your %s API key in the Settings tab before starting training.', 'ai-chat-search'),
                            esc_html($provider->get_provider_name())
                        );
                    ?></p>
                </div>
                <div class="airs-modal-footer">
                    <button type="button" class="airs-button airs-button-secondary" id="api-key-missing-close-btn">
                        <?php _e('Cancel', 'ai-chat-search'); ?>
                    </button>
                    <button type="button" class="airs-button airs-button-primary" id="api-key-missing-settings-btn">
                        <?php _e('Go to Settings', 'ai-chat-search'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    

    /**
     * Render stats tab
     */
    private function render_stats_tab() {
        ?>
        <!-- AI Chatbot Stats Section -->
        <?php
        $chat_stats = get_option('listeo_ai_chat_stats', array(
            'total_sessions' => 0,
            'user_messages' => 0
        ));

        // Chat History Section - delegated to Admin_Chat_History class
        $history_enabled = get_option('listeo_ai_chat_history_enabled', 0);
        $this->chat_history->render_section($history_enabled);
        ?>

        <!-- Right Column: Chart + Contact Messages (stacked) -->
        <div class="airs-stats-right-column">
            <!-- Activity Chart Card -->
            <div class="airs-card">
                <div class="airs-card-header airs-card-header-with-icon">
                    <div class="airs-card-icon airs-card-icon-indigo">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path></svg>
                    </div>
                    <div class="airs-card-header-text">
                        <h3><?php _e('Activity (Last 30 Days)', 'ai-chat-search'); ?></h3>
                        <p><?php _e('Chat activity trends over time', 'ai-chat-search'); ?></p>
                    </div>
                </div>
                <div class="airs-card-body">
                    <?php
                    // Hook for chart rendering (Pro feature)
                    if (has_action('ai_chat_search_render_chart_card')) {
                        do_action('ai_chat_search_render_chart_card');
                    } else {
                        // Fallback for free version - show locked preview with dummy chart
                        ?>
                        <div class="ai-chat-pro-feature-locked" style="min-height: auto;">
                            <!-- Dummy chart background (visible behind overlay) -->
                            <div class="preview-container" style="padding: 0;">
                                <!-- Dummy Chart Legend -->
                                <div style="display: flex; justify-content: center; gap: 20px; margin-bottom: 15px;">
                                    <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #666;">
                                        <span style="width: 12px; height: 12px; background: #22c55e; border-radius: 2px;"></span>
                                        <?php _e('Conversations', 'ai-chat-search'); ?>
                                    </span>
                                    <span style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #666;">
                                        <span style="width: 12px; height: 12px; background: #3b82f6; border-radius: 2px;"></span>
                                        <?php _e('Messages', 'ai-chat-search'); ?>
                                    </span>
                                </div>
                                <!-- Dummy Chart SVG -->
                                <svg viewBox="0 0 500 200" preserveAspectRatio="xMidYMid meet" style="width: 100%; aspect-ratio: 5/2; display: block;">
                                    <!-- Grid lines -->
                                    <line x1="30" y1="10" x2="30" y2="170" stroke="#e5e7eb" stroke-width="1"/>
                                    <line x1="30" y1="170" x2="490" y2="170" stroke="#e5e7eb" stroke-width="1"/>
                                    <line x1="30" y1="120" x2="490" y2="120" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
                                    <line x1="30" y1="70" x2="490" y2="70" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
                                    <line x1="30" y1="20" x2="490" y2="20" stroke="#e5e7eb" stroke-width="1" stroke-dasharray="4"/>
                                    <!-- Conversations line (green) -->
                                    <polyline fill="none" stroke="#22c55e" stroke-width="2.5" points="30,140 75,120 120,130 165,100 210,115 255,80 300,95 345,60 390,75 435,50 480,65"/>
                                    <!-- Conversations area fill -->
                                    <polygon fill="rgba(34,197,94,0.15)" points="30,140 75,120 120,130 165,100 210,115 255,80 300,95 345,60 390,75 435,50 480,65 480,170 30,170"/>
                                    <!-- Messages line (blue) -->
                                    <polyline fill="none" stroke="#3b82f6" stroke-width="2.5" points="30,155 75,140 120,148 165,125 210,135 255,105 300,115 345,85 390,95 435,70 480,80"/>
                                    <!-- Messages area fill -->
                                    <polygon fill="rgba(59,130,246,0.15)" points="30,155 75,140 120,148 165,125 210,135 255,105 300,115 345,85 390,95 435,70 480,80 480,170 30,170"/>
                                    <!-- X-axis labels -->
                                    <text x="50" y="188" font-size="11" fill="#9ca3af">Jan 1</text>
                                    <text x="140" y="188" font-size="11" fill="#9ca3af">Jan 8</text>
                                    <text x="230" y="188" font-size="11" fill="#9ca3af">Jan 15</text>
                                    <text x="320" y="188" font-size="11" fill="#9ca3af">Jan 22</text>
                                    <text x="420" y="188" font-size="11" fill="#9ca3af">Jan 29</text>
                                </svg>
                            </div>

                            <!-- Overlay -->
                            <div class="lock-overlay" style="background: rgba(255, 255, 255, 0.55); backdrop-filter: blur(3px);">
                                <div class="lock-content">
                                    <h3><?php _e('Activity Analytics', 'ai-chat-search'); ?></h3>

                                    <ul class="benefits-list">
                                        <li><?php _e('Visual conversation trends', 'ai-chat-search'); ?></li>
                                    </ul>

                                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('activity_chart')); ?>"
                                       class="button button-primary button-hero" target="_blank">
                                        <?php _e('Upgrade to Pro', 'ai-chat-search'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Contact Messages Section -->
            <?php $this->contact_messages->render_section(); ?>
        </div>

        <?php
        // Popular Search Queries - always rendered after Chat History and Activity
        $this->search_analytics->render_section();
        ?>

        <?php
    }

    /**
     * Render locked conversation logs (Pro feature)
     */
    private function render_conversation_logs_locked() {
        ?>
        <div class="ai-chat-pro-feature-locked" style="margin-top: 30px;">
            <!-- Blurred preview using actual conversation design -->
            <div class="preview-container preview-blurred">
                <div style="margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0;"><?php _e('Recent Conversations', 'ai-chat-search'); ?></h3>

                    <?php
                    // Generate 3 dummy conversations
                    $dummy_conversations = array(
                        array('id' => 'a1b2c3d4e5f6g7h8', 'messages' => 8, 'user' => 'Guest User', 'ip' => '192.168.1.45', 'country' => 'us', 'started' => 3, 'last_msg' => 1),
                        array('id' => 'x9y8z7w6v5u4t3s2', 'messages' => 5, 'user' => 'john.doe@example.com', 'ip' => '85.214.132.117', 'country' => 'de', 'started' => 6, 'last_msg' => 4),
                        array('id' => 'm5n4o3p2q1r0s9t8', 'messages' => 12, 'user' => 'Guest User', 'ip' => '46.125.70.146', 'country' => 'pl', 'started' => 9, 'last_msg' => 7),
                    );

                    foreach ($dummy_conversations as $idx => $conv):
                    ?>
                    <div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong><?php _e('Conversation ID:', 'ai-chat-search'); ?></strong>
                                <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($conv['id']); ?></code>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 12px; color: #666;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo esc_html($conv['user']); ?>
                                    <img src="https://flagcdn.com/16x12/<?php echo esc_attr($conv['country']); ?>.png" alt="<?php echo esc_attr(strtoupper($conv['country'])); ?>" style="vertical-align: middle; margin-left: 5px;" />
                                    <span style="color: #999; margin-left: 3px;"><?php echo esc_html($conv['ip']); ?></span>
                                </div>
                                <div style="font-size: 12px; color: #999;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg><?php printf(__('Started: %d hours ago', 'ai-chat-search'), $conv['started']); ?>
                                </div>
                            </div>
                        </div>

                        <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><?php echo $conv['messages']; ?> <?php _e('messages', 'ai-chat-search'); ?>
                            • <?php printf(__('last %d hours ago', 'ai-chat-search'), $conv['last_msg']); ?>
                        </div>

                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; padding: 8px; background: #f9f9f9; border-radius: 3px; font-weight: 500;">
                                <?php _e('View Messages', 'ai-chat-search'); ?> (<?php echo $conv['messages']; ?>)
                            </summary>
                            <div style="margin-top: 10px; padding: 10px; background: #fafafa; border-radius: 3px; max-height: 400px; overflow-y: auto;">
                                <!-- User Message -->
                                <div style="margin-bottom: 15px; padding: 10px; background: #e8f4ff; border-radius: 4px;">
                                    <div style="font-weight: bold; color: #1976d2; margin-bottom: 5px; font-size: 12px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php _e('User', 'ai-chat-search'); ?>
                                        <span style="color: #999; font-weight: normal; margin-left: 10px;">
                                            <?php echo date_i18n('M j, ' . get_option('time_format'), strtotime('-' . $conv['started'] . ' hours')); ?>
                                        </span>
                                    </div>
                                    <div style="color: #333; word-break: break-word;">
                                        <?php _e('Looking for a restaurant near downtown...', 'ai-chat-search'); ?>
                                    </div>
                                </div>

                                <!-- AI Response -->
                                <div style="margin-bottom: 15px; padding: 10px; background: #ffffff; border-radius: 4px;">
                                    <div style="font-weight: bold; color: #666; margin-bottom: 5px; font-size: 12px;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg><?php _e('AI Assistant', 'ai-chat-search'); ?>
                                        <span style="color: #999; font-weight: normal; margin-left: 10px;">
                                            gpt-4o-mini
                                        </span>
                                    </div>
                                    <div style="color: #333; word-break: break-word;">
                                        <p><?php _e('I found several great restaurants downtown. Would you like to see Italian, Asian, or American cuisine options?', 'ai-chat-search'); ?></p>
                                    </div>
                                </div>

                                <!-- More messages indicator -->
                                <div style="text-align: center; padding: 10px; color: #999; font-size: 12px;">
                                    ... <?php echo ($conv['messages'] - 2); ?> <?php _e('more messages', 'ai-chat-search'); ?> ...
                                </div>
                            </div>
                        </details>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Overlay -->
            <div class="lock-overlay">
                <div class="lock-content">
                    <h3><?php _e('Chat History & Analytics', 'ai-chat-search'); ?></h3>

                    <ul class="benefits-list">
                        <li><?php _e('Conversation statistics and metrics', 'ai-chat-search'); ?></li>
                        <li><?php _e('Complete message history', 'ai-chat-search'); ?></li>
                    </ul>

                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('conversation_logs')); ?>"
                       class="button button-primary button-hero" target="_blank">
                        <?php _e('Upgrade to Pro', 'ai-chat-search'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render AI Chat tab
     */
    private function render_ai_chat_tab() {
        global $wpdb;

        // Get current settings
        $system_prompt = get_option('listeo_ai_chat_system_prompt', '');
        $chat_enabled = get_option('listeo_ai_chat_enabled', 0);
        $chat_name = get_option('listeo_ai_chat_name', 'Assistant');
        $welcome_message = get_option('listeo_ai_chat_welcome_message', 'Hello! I can help you find restaurants, hotels, and services. What would you like to search for?');
        $model = get_option('listeo_ai_chat_model', 'gpt-5.3-chat-latest');
        $current_provider = get_option('listeo_ai_search_provider', 'openai');
        $is_pro = AI_Chat_Search_Pro_Manager::is_pro_active();

        ?>

        <!-- Single form wrapping all sections -->
        <form class="airs-ajax-form" data-section="ai-chat-config" id="ai-chat-settings-form">

        <!-- ========================================== -->
        <!-- SECTION 1: GENERAL -->
        <!-- ========================================== -->
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('General', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Core settings for the AI chat functionality.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                <!-- Enable Chat -->
                <div class="airs-form-group">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" name="listeo_ai_chat_enabled" value="1" <?php checked($chat_enabled, 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Enable AI Chat', 'ai-chat-search'); ?>
                            <small><?php _e('Enable conversational AI chat interface for searches.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <!-- Shortcode & Elementor Usage -->
                <div class="airs-form-group">
                    <div class="airs-form-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div class="airs-form-col" style="flex: 1;">
                            <label class="airs-label"><?php _e('Shortcode Usage', 'ai-chat-search'); ?></label>
                            <code style="background: #f7f7f8; padding: 12px; border-radius: 4px; display: block; margin-bottom: 8px; font-size: 14px;">
                                [ai_chat]
                            </code>
                            <p class="airs-help-text"><?php _e('Available parameters:', 'ai-chat-search'); ?> <code>height="600px"</code> <code>style="1"</code> <?php _e('or', 'ai-chat-search'); ?> <code>style="2"</code></p>
                        </div>
                        <?php if (class_exists('Listeo_AI_Detection') && Listeo_AI_Detection::is_listeo_available()): ?>
                        <div class="airs-form-col" style="flex: 1;">
                            <label class="airs-label"><?php _e('Elementor Usage', 'ai-chat-search'); ?></label>
                            <p class="airs-help-text"><?php _e('Edit any page with Elementor and add <mark style="background: #ffba003b; color: #b48507; padding: 2px 5px; border-radius: 4px;">AI Chat</mark> widget', 'ai-chat-search'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Install Translation -->
                <div class="airs-form-group">
                    <label for="ai_translation_locale" class="airs-label">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="display: inline-block; vertical-align: -4px; margin-right: 5px;"><path d="M12.87 15.07l-2.54-2.51.03-.03A17.52 17.52 0 0014.07 6H17V4h-7V2H8v2H1v2h11.17C11.5 7.92 10.44 9.75 9 11.35 8.07 10.32 7.3 9.19 6.69 8h-2c.73 1.63 1.73 3.17 2.98 4.56l-5.09 5.02L4 19l5-5 3.11 3.11.76-2.04zM18.5 10h-2L12 22h2l1.12-3h4.75L21 22h2l-4.5-12zm-2.62 7l1.62-4.33L19.12 17h-3.24z"/></svg><?php _e('Install Translation', 'ai-chat-search'); ?>
                    </label>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <select id="ai_translation_locale" class="airs-input" style="max-width: 280px;">
                            <option value=""><?php esc_html_e('-- Select Language --', 'ai-chat-search'); ?></option>
                            <?php
                            $trans_languages = $this->get_translation_languages();
                            $active_locale = get_locale();
                            foreach ($trans_languages as $loc => $lang_name) {
                                $is_active = ($loc === $active_locale);
                                $label = $lang_name . ' (' . $loc . ')';
                                if ($is_active) {
                                    $label .= ' ✓';
                                }
                                echo '<option value="' . esc_attr($loc) . '"' . selected($is_active, true, false) . '>' . esc_html($label) . '</option>';
                            }
                            ?>
                        </select>
                        <?php
                        $btn_disabled = true;
                        if ($active_locale && strpos($active_locale, 'en_') !== 0 && $active_locale !== 'en') {
                            $btn_disabled = false;
                        }
                        ?>
                        <button type="button" id="ai_install_translation" class="airs-button airs-button-secondary"<?php echo $btn_disabled ? ' disabled' : ''; ?> style="white-space: nowrap;">
                            <?php _e('Update', 'ai-chat-search'); ?>
                        </button>
                        <span id="ai_translation_status" style="font-size: 13px;"></span>
                        <?php
                        $current_locale = get_locale();
                        $has_translation = false;
                        if ($current_locale && strpos($current_locale, 'en_') !== 0 && $current_locale !== 'en') {
                            $mo_file = trailingslashit(WP_LANG_DIR) . 'plugins/ai-chat-search-' . $current_locale . '.mo';
                            if (file_exists($mo_file)) {
                                $has_translation = true;
                            }
                        }
                        ?>
                        <button type="button" id="ai_remove_translation" data-locale="<?php echo esc_attr($current_locale); ?>" class="airs-button" style="background: #fcecec; border-color: #dc3232; color: #dc3232; margin-left: -10px; white-space: nowrap;<?php echo $has_translation ? '' : ' display: none;'; ?>">
                            <?php _e('Switch to English', 'ai-chat-search'); ?>
                        </button>
                    </div>
                    <p class="airs-help-text"><?php _e('Translates all plugin settings and chatbot states like "Thinking..." or "Searching products..."', 'ai-chat-search'); ?></p>
                </div>

                <!-- Model Selection -->
                <div class="airs-form-group">
                    <label for="listeo_ai_chat_model" class="airs-label">
                        <span id="model-label-text">
                            <?php
                            if ($current_provider === 'gemini') {
                                _e('Gemini Model', 'ai-chat-search');
                            } elseif ($current_provider === 'mistral') {
                                _e('Mistral Model', 'ai-chat-search');
                            } else {
                                _e('OpenAI Model', 'ai-chat-search');
                            }
                            ?>
                        </span>
                    </label>
                    <select id="listeo_ai_chat_model" name="listeo_ai_chat_model" class="airs-input">
                        <!-- OpenAI Models -->
                        <optgroup label="OpenAI Models" class="model-group model-group-openai" style="<?php echo $current_provider !== 'openai' ? 'display:none;' : ''; ?>">
                            <option value="gpt-4.1-mini" <?php selected($model, 'gpt-4.1-mini'); ?>>GPT-4.1 Mini (Fast & Good)</option>
                            <option value="gpt-4.1" <?php selected($model, 'gpt-4.1'); ?>>GPT-4.1 (Smart, non-reasoning)</option>
                            <option value="gpt-5-mini" <?php selected($model, 'gpt-5-mini'); ?>>GPT-5 Mini (Fast & Good)</option>
                            <option value="gpt-5-chat-latest" <?php selected($model, 'gpt-5-chat-latest'); ?>>GPT-5 (Smart)</option>
                            <option value="gpt-5.1" <?php selected($model, 'gpt-5.1'); ?>>GPT-5.1 (High Intelligence)</option>
                            <option value="gpt-5.2" <?php selected($model, 'gpt-5.2'); ?>>GPT-5.2 (High Intelligence)</option>
                            <option value="gpt-5.3-chat-latest" <?php selected($model, 'gpt-5.3-chat-latest'); ?>>GPT-5.3 (High Intelligence)</option>
                            <option value="gpt-5.4" <?php selected($model, 'gpt-5.4'); ?>>GPT-5.4 (High Intelligence - Latest)</option>
                            <option value="gpt-5.4-mini" <?php selected($model, 'gpt-5.4-mini'); ?>>GPT-5.4 Mini (Fast & Smart)</option>
                            <option value="gpt-5.4-nano" <?php selected($model, 'gpt-5.4-nano'); ?>>GPT-5.4 Nano (Fastest & Cheapest)</option>
                        </optgroup>
                        <!-- Gemini Models -->
                        <optgroup label="Gemini Models" class="model-group model-group-gemini" style="<?php echo $current_provider !== 'gemini' ? 'display:none;' : ''; ?>">
                            <option value="gemini-2.5-flash" <?php selected($model, 'gemini-2.5-flash'); ?>>Gemini 2.5 Flash (Fast)</option>
                            <option value="gemini-2.5-pro" <?php selected($model, 'gemini-2.5-pro'); ?>>Gemini 2.5 Pro (High Intellgence & Slow)</option>
                            <option value="gemini-3.1-pro-preview" <?php selected($model, 'gemini-3.1-pro-preview'); ?>>Gemini 3.1 Pro (High Intelligence & Slow)</option>
                            <option value="gemini-3-flash-preview" <?php selected($model, 'gemini-3-flash-preview'); ?>>Gemini 3 Flash (Intelligent & Fast - Recommended)</option>
                            <option value="gemini-3.1-flash-lite-preview" <?php selected($model, 'gemini-3.1-flash-lite-preview'); ?>>Gemini 3.1 Flash Lite (Fastest)</option>
                        </optgroup>
                        <!-- Mistral Models -->
                        <optgroup label="Mistral Models" class="model-group model-group-mistral" style="<?php echo $current_provider !== 'mistral' ? 'display:none;' : ''; ?>">
                            <option value="mistral-small-latest" <?php selected($model, 'mistral-small-latest'); ?>>Mistral Small 4 (Fast)</option>
                            <option value="mistral-medium-latest" <?php selected($model, 'mistral-medium-latest'); ?>>Mistral Medium 3.1 (Balanced - Recommended)</option>
                            <option value="mistral-large-latest" <?php selected($model, 'mistral-large-latest'); ?>>Mistral Large 3 (High Intelligence)</option>
                        </optgroup>
                    </select>
                    <p class="airs-help-text" id="model-help-text">
                        <?php
                        if ($current_provider === 'gemini') {
                            _e('Select the Gemini model for chat responses. Better models provide more accurate and context-aware responses.', 'ai-chat-search');
                        } elseif ($current_provider === 'mistral') {
                            _e('Select the Mistral model for chat responses. Better models provide more accurate and context-aware responses.', 'ai-chat-search');
                        } else {
                            _e('Select the OpenAI model for chat responses. The better the model, the more accurate and context-aware the responses at a cost of time response.', 'ai-chat-search');
                        }
                        ?>
                    </p>
                    <p class="airs-warning-text" id="gemini-3-warning" style="color: #dc3545; font-size: 13px; margin-top: -10px; display: <?php echo ($current_provider === 'gemini' && $model === 'gemini-3.1-pro-preview') ? 'block' : 'none'; ?>;">
                        ⚠️ <?php _e('To use Gemini 3 you need to enable billing in Google. It doesn\'t work within Free Tier.', 'ai-chat-search'); ?>
                    </p>
                </div>

                <!-- WooCommerce Settings (only show when listing or product post types exist) -->
                <?php
                $has_listings_or_products = post_type_exists('listing') || post_type_exists('product');
                ?>
                <div class="airs-form-group" style="<?php echo !$has_listings_or_products ? 'display: none;' : ''; ?>">
                    <label class="airs-label">
<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -1px; margin-right: 5px;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg> WooCommerce<?php if (post_type_exists('listing')): ?> &amp; Listeo<?php endif; ?>
                    </label>
                    <div class="airs-form-row" style="display: flex; flex-wrap: wrap; gap: 0 20px; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; padding-bottom: 15px;">
                        <div class="airs-form-col" style="flex: 1;">
                            <label for="listeo_ai_chat_max_results" class="airs-label">
                                <?php _e('Maximum Products/Listings Cards Displayed', 'ai-chat-search'); ?>
                            </label>
                            <input type="number" id="listeo_ai_chat_max_results" name="listeo_ai_chat_max_results" value="<?php echo esc_attr(get_option('listeo_ai_chat_max_results', 10)); ?>" class="airs-input" min="1" max="25" step="1" />
                            <p class="airs-help-text"><?php _e('Maximum number of WooCommerce products to display in chat results (1-25). Default: 10', 'ai-chat-search'); ?></p>
                        </div>
                        <?php if (class_exists('WooCommerce')): ?>
                        <div class="airs-form-col" style="flex: 1;">
                            <?php $woo_cart_enabled = $is_pro && get_option('listeo_ai_chat_woo_cart_enabled', 0); ?>
                            <label class="airs-checkbox-label <?php echo !$is_pro ? 'pro-locked' : ''; ?>">
                                <input type="checkbox"
                                       name="listeo_ai_chat_woo_cart_enabled"
                                       id="listeo_ai_chat_woo_cart_enabled"
                                       value="1"
                                       <?php checked($woo_cart_enabled, 1); ?>
                                       <?php disabled(!$is_pro); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text">
                                    <?php if (!$is_pro): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                    <?php endif; ?>
                                    <?php _e('Enable WooCommerce Cart in Chatbot', 'ai-chat-search'); ?>
                                    <?php if (!$is_pro): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php if (!$is_pro): ?>
                                <p class="airs-help-text" style="margin-left: 30px;">
                                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('woo-cart')); ?>" target="_blank" class="upgrade-link">
                                        <?php _e('Upgrade to Pro to enable WooCommerce cart', 'ai-chat-search'); ?> →
                                    </a>
                                </p>
                            <?php else: ?>
                                <p class="airs-help-text"><?php _e('Show "Add to Cart" buttons on product cards and a cart icon in the chat header.', 'ai-chat-search'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Speech-to-Text & Image Input (PRO Features) -->
                <div class="airs-form-group">
                    <div class="airs-form-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div class="airs-form-col" style="flex: 1;">
                            <?php $speech_enabled = $is_pro && get_option('listeo_ai_chat_enable_speech', 0); ?>
                            <label class="airs-checkbox-label <?php echo !$is_pro ? 'pro-locked' : ''; ?>">
                                <input type="checkbox"
                                       name="listeo_ai_chat_enable_speech"
                                       id="listeo_ai_chat_enable_speech"
                                       value="1"
                                       <?php checked($speech_enabled, 1); ?>
                                       <?php disabled(!$is_pro); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text">
                                    <?php if (!$is_pro): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                    <?php endif; ?>
                                    <?php _e('Enable Speech-to-Text', 'ai-chat-search'); ?>
                                    <?php if (!$is_pro): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                    <?php endif; ?>
                                    <small><?php _e('Show a microphone button that allows users to send voice messages. Audio is sent directly to AI for transcription and is', 'ai-chat-search'); ?> <strong><?php _e('not stored on your server.', 'ai-chat-search'); ?></strong></small>
                                </span>
                            </label>
                            <?php if (!$is_pro): ?>
                                <p class="airs-help-text" style="margin-left: 30px;">
                                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('speech')); ?>" target="_blank" class="upgrade-link">
                                        <?php _e('Upgrade to Pro to enable speech-to-text', 'ai-chat-search'); ?> →
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="airs-form-col" style="flex: 1;">
                            <?php $image_input_enabled = $is_pro && get_option('listeo_ai_chat_enable_image_input', 0); ?>
                            <label class="airs-checkbox-label <?php echo !$is_pro ? 'pro-locked' : ''; ?>">
                                <input type="checkbox"
                                       name="listeo_ai_chat_enable_image_input"
                                       id="listeo_ai_chat_enable_image_input"
                                       value="1"
                                       <?php checked($image_input_enabled, 1); ?>
                                       <?php disabled(!$is_pro); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text">
                                    <?php if (!$is_pro): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                    <?php endif; ?>
                                    <?php _e('Enable Image Input', 'ai-chat-search'); ?>
                                    <?php if (!$is_pro): ?>
                                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                    <?php endif; ?>
                                    <small><?php _e('Show a button that allows users to attach an image for the AI to analyze. Images are sent directly to AI and are', 'ai-chat-search'); ?> <strong><?php _e('not stored on your server.', 'ai-chat-search'); ?></strong></small>
                                </span>
                            </label>
                            <?php if (!$is_pro): ?>
                                <p class="airs-help-text" style="margin-left: 30px;">
                                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('image_input')); ?>" target="_blank" class="upgrade-link">
                                        <?php _e('Upgrade to Pro to enable image input', 'ai-chat-search'); ?> →
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <?php $is_pro_quick_buttons = AI_Chat_Search_Pro_Manager::is_pro_active(); ?>
                <div class="airs-form-group">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" name="listeo_ai_chat_quick_buttons_enabled" id="listeo_ai_chat_quick_buttons_enabled" value="1" <?php checked(get_option('listeo_ai_chat_quick_buttons_enabled', 1), 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Enable Quick Action Buttons', 'ai-chat-search'); ?>
                            <small><?php _e('Show quick action buttons above the chat input field.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <div id="listeo_ai_chat_quick_buttons_wrapper" class="<?php echo $is_pro_quick_buttons ? 'pro-active' : 'pro-locked'; ?>" style="display: <?php echo get_option('listeo_ai_chat_quick_buttons_enabled', 1) ? 'block' : 'none'; ?>;">
                    <?php if ($is_pro_quick_buttons): ?>
                        <!-- PRO: Full Quick Buttons Configuration -->
                        <label class="airs-label"><?php _e('Quick Buttons', 'ai-chat-search'); ?></label>
                        <p class="airs-help-text" style="margin-bottom: 15px;">
                            <?php _e('Add buttons that appear above the chat input. "<strong>Chat Message</strong>" buttons send a message to the AI, "<strong>Link</strong>" buttons open a link and "<strong>Contact Form</strong>" buttons open a contact form.', 'ai-chat-search'); ?>
                        </p>

                        <div id="listeo-quick-buttons-container">
                            <?php
                            $quick_buttons = get_option('listeo_ai_chat_quick_buttons', array());
                            if (empty($quick_buttons)) {
                                $quick_buttons = array(array('text' => '', 'type' => 'chat', 'value' => ''));
                            }
                            $default_primary_color = get_option('listeo_ai_primary_color', '#0073ee');
                            foreach ($quick_buttons as $index => $button) :
                                $button_text = isset($button['text']) ? $button['text'] : '';
                                $button_type = isset($button['type']) ? $button['type'] : 'chat';
                                $button_value = isset($button['value']) ? $button['value'] : '';
                                $button_color = isset($button['color']) ? $button['color'] : '';
                            ?>
                            <div class="listeo-quick-button-row">
                                <div class="listeo-quick-button-color-wrap">
                                    <button type="button" class="listeo-quick-btn-color-swatch" style="background-color: <?php echo esc_attr($button_color ? $button_color : '#ebebeb'); ?>;"></button>
                                    <input type="hidden"
                                           name="listeo_ai_chat_quick_buttons[<?php echo $index; ?>][color]"
                                           value="<?php echo esc_attr($button_color); ?>"
                                           class="listeo-quick-button-color-value"
                                           data-default-color="<?php echo esc_attr($default_primary_color); ?>" />
                                </div>
                                <input type="text"
                                       name="listeo_ai_chat_quick_buttons[<?php echo $index; ?>][text]"
                                       value="<?php echo esc_attr($button_text); ?>"
                                       placeholder="<?php esc_attr_e('Button Text', 'ai-chat-search'); ?>"
                                       class="airs-input listeo-quick-button-text" />
                                <select name="listeo_ai_chat_quick_buttons[<?php echo $index; ?>][type]"
                                        class="airs-input listeo-quick-button-type">
                                    <option value="chat" <?php selected($button_type, 'chat'); ?>><?php _e('Chat Message', 'ai-chat-search'); ?></option>
                                    <option value="url" <?php selected($button_type, 'url'); ?>><?php _e('Link', 'ai-chat-search'); ?></option>
                                    <option value="contact" <?php selected($button_type, 'contact'); ?>><?php _e('Contact Form', 'ai-chat-search'); ?></option>
                                </select>
                                <input type="text"
                                       name="listeo_ai_chat_quick_buttons[<?php echo $index; ?>][value]"
                                       value="<?php echo esc_attr($button_value); ?>"
                                       placeholder="<?php echo $button_type === 'url' ? esc_attr__('https://example.com', 'ai-chat-search') : esc_attr__('Message to send', 'ai-chat-search'); ?>"
                                       class="airs-input listeo-quick-button-value"
                                       <?php echo $button_type === 'contact' ? 'style="display: none;"' : ''; ?> />
                                <button type="button" class="airs-button airs-button-primary listeo-configure-contact-form" <?php echo $button_type !== 'contact' ? 'style="display: none;"' : ''; ?>>
                                    <?php _e('Configure', 'ai-chat-search'); ?>
                                </button>
                                <button type="button" class="airs-button airs-button-secondary listeo-remove-quick-button" title="<?php esc_attr_e('Remove', 'ai-chat-search'); ?>">
                                    <span class="remove-icon">×</span>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" id="listeo-add-quick-button" class="airs-button airs-button-secondary">
                            <?php _e('+ Add Button', 'ai-chat-search'); ?>
                        </button>

                        <!-- PRO: Quick Buttons Visibility -->
                        <div class="airs-form-group" style="margin: 15px 0 0 0;">
                            <label class="airs-label" for="listeo_ai_chat_quick_buttons_visibility"><?php _e('Visibility', 'ai-chat-search'); ?></label>
                            <select name="listeo_ai_chat_quick_buttons_visibility" id="listeo_ai_chat_quick_buttons_visibility" class="airs-input" style="max-width: 250px;">
                                <option value="always" <?php selected(get_option('listeo_ai_chat_quick_buttons_visibility', 'always'), 'always'); ?>><?php _e('Always show', 'ai-chat-search'); ?></option>
                                <option value="hide_after_first" <?php selected(get_option('listeo_ai_chat_quick_buttons_visibility', 'always'), 'hide_after_first'); ?>><?php _e('Hide after 1st message', 'ai-chat-search'); ?></option>
                            </select>
                        </div>
                    <?php else: ?>
                        <!-- FREE: Quick Buttons Teaser -->
                        <div class="quick-buttons-pro-teaser">
                            <div class="teaser-preview">
                                <p class="airs-help-text"><?php _e('Add <strong>custom buttons</strong> above the chat input to guide user interactions.', 'ai-chat-search'); ?></p>

                                <!-- Preview of what buttons look like -->
                                <div class="quick-buttons-preview">
                                    <div class="preview-button"><?php _e('Who are you?', 'ai-chat-search'); ?></div>
                                    <div class="preview-button"><?php _e('Human Contact Form', 'ai-chat-search'); ?></div>
                                    <div class="preview-button"><?php _e('View Pricing', 'ai-chat-search'); ?></div>
                                </div>

                                <ul class="teaser-features">
                                    <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('<strong>Chat Message</strong> buttons - send predefined messages to AI', 'ai-chat-search'); ?></li>
                                    <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('<strong>Link buttons</strong> - direct users to any URL', 'ai-chat-search'); ?></li>
                                    <li><span class="dashicons dashicons-yes-alt"></span> <?php _e('<strong>Contact Form</strong> - built-in contact form overlay', 'ai-chat-search'); ?></li>
                                </ul>

                                <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('quick-buttons')); ?>" target="_blank" class="airs-button airs-button-primary">
                                    <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                    <?php _e('Unlock Quick Buttons', 'ai-chat-search'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 2: APPEARANCE -->
        <!-- ========================================== -->
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.555C21.965 6.012 17.461 2 12 2z"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Appearance', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Customize how the chat looks and feels to your users.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                <!-- Chatbot Name & Avatar -->
                <div class="airs-form-group">
                    <div class="airs-form-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div class="airs-form-col" style="flex: 1;">
                            <label for="listeo_ai_chat_name" class="airs-label">
                                <?php _e('Chatbot Name', 'ai-chat-search'); ?>
                            </label>
                            <input type="text" id="listeo_ai_chat_name" name="listeo_ai_chat_name" value="<?php echo esc_attr($chat_name); ?>" class="airs-input" placeholder="Assistant" />
                            <p class="airs-help-text"><?php _e('Displayed in chat header. Default: "Assistant"', 'ai-chat-search'); ?></p>

                            <label for="listeo_ai_chat_welcome_message" class="airs-label" style="margin-top: 15px;">
                                <?php _e('Welcome Message', 'ai-chat-search'); ?>
                            </label>
                            <textarea id="listeo_ai_chat_welcome_message" name="listeo_ai_chat_welcome_message" class="airs-input" rows="2" placeholder="Hello! I can help you find restaurants, hotels, and services. What would you like to search for?"><?php echo esc_textarea($welcome_message); ?></textarea>
                            <p class="airs-help-text"><?php _e('The initial greeting message displayed when chat loads. HTML tags allowed (e.g., &lt;b&gt;, &lt;i&gt;, &lt;a&gt;, &lt;br&gt;).', 'ai-chat-search'); ?></p>
                        </div>
                        <div class="airs-form-col" style="flex: 0 0 auto; align-self: flex-start; margin-top: 10px; padding: 20px 20px 5px 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
                            <label for="listeo_ai_chat_avatar" class="airs-label">
                                <?php _e('Chatbot Avatar', 'ai-chat-search'); ?>
                            </label>
                            <?php
                            $chat_avatar_id = get_option('listeo_ai_chat_avatar', 0);
                            $chat_avatar_url = $chat_avatar_id ? wp_get_attachment_image_url($chat_avatar_id, 'thumbnail') : '';
                            ?>
                            <div class="airs-media-upload">
                                <input type="hidden" id="listeo_ai_chat_avatar" name="listeo_ai_chat_avatar" value="<?php echo esc_attr($chat_avatar_id); ?>" />
                                <div class="airs-media-preview" id="listeo-chat-avatar-preview">
                                    <?php if ($chat_avatar_url) : ?>
                                        <img src="<?php echo esc_url($chat_avatar_url); ?>" alt="Chat avatar" style="width: 38px; height: 38px; border-radius: 100px; object-fit: cover;" />
                                    <?php else : ?>
                                        <div class="airs-media-placeholder" style="width: 38px; height: 38px; border: 2px dashed #ddd; border-radius: 100px; display: flex; align-items: center; justify-content: center; color: #999; box-sizing: content-box;">
                                            <i class="sl sl-icon-user" style="font-size: 14px;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="airs-media-buttons" style="margin-top: 10px;">
                                    <button type="button" class="airs-button airs-button-secondary" id="listeo-upload-chat-avatar">
                                        <?php _e('Upload', 'ai-chat-search'); ?>
                                    </button>
                                    <?php if ($chat_avatar_id) : ?>
                                        <button type="button" class="airs-button airs-button-secondary" id="listeo-remove-chat-avatar" style="margin-left: 5px;">
                                            <?php _e('Remove', 'ai-chat-search'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="airs-help-text"><?php _e('100x100px recommended.', 'ai-chat-search'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Color Settings -->
                <div class="airs-form-group">
                    <label class="airs-label"><?php _e('Color Settings', 'ai-chat-search'); ?></label>
                    <?php $color_scheme = get_option('listeo_ai_color_scheme', 'light'); ?>
                    <div class="airs-form-row" style="display: flex; flex-wrap: wrap; gap: 0 30px; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; padding-bottom: 15px;">
                        <div class="airs-form-col" style="flex: 1;">
                            <label for="listeo_ai_floating_button_color" class="airs-label" style="font-weight: 500; font-size: 13px; margin-bottom: 6px;">
                                <?php _e('Buttons Color', 'ai-chat-search'); ?>
                            </label>
                            <input type="text" id="listeo_ai_floating_button_color" name="listeo_ai_floating_button_color" value="<?php echo esc_attr(get_option('listeo_ai_floating_button_color', '#222222')); ?>" class="airs-input airs-color-picker" data-default-color="#222222" />
                            <p class="airs-help-text"><?php _e('Floating button, send button, context button.', 'ai-chat-search'); ?></p>
                        </div>
                        <div class="airs-form-col" style="flex: 1;">
                            <label for="listeo_ai_primary_color" class="airs-label" style="font-weight: 500; font-size: 13px; margin-bottom: 6px;">
                                <?php _e('Primary Color', 'ai-chat-search'); ?>
                            </label>
                            <input type="text" id="listeo_ai_primary_color" name="listeo_ai_primary_color" value="<?php echo esc_attr(get_option('listeo_ai_primary_color', '#0073ee')); ?>" class="airs-input airs-color-picker" data-default-color="#0073ee" />
                            <p class="airs-help-text"><?php _e('Links, user messages, and UI elements.', 'ai-chat-search'); ?></p>
                        </div>
                        <div class="airs-form-col" style="flex: 1;">
                            <label class="airs-label" style="font-weight: 500; font-size: 13px; margin-bottom: 6px;">
                                <?php _e('Color Scheme', 'ai-chat-search'); ?>
                            </label>
                            <div class="airs-theme-toggle">
                                <button type="button" class="airs-theme-btn<?php echo $color_scheme === 'light' ? ' active' : ''; ?>" data-value="light" title="<?php esc_attr_e('Light', 'ai-chat-search'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                    </svg>
                                    <!-- Hover preview -->
                                    <div class="airs-theme-preview">
                                        <div class="color-scheme-preview color-scheme-light">
                                            <div class="preview-header"></div>
                                            <div class="preview-message preview-message-ai"></div>
                                            <div class="preview-message preview-message-user"></div>
                                            <div class="preview-input"></div>
                                        </div>
                                    </div>
                                </button>
                                <button type="button" class="airs-theme-btn<?php echo $color_scheme === 'auto' ? ' active' : ''; ?>" data-value="auto" title="<?php esc_attr_e('System', 'ai-chat-search'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                    </svg>
                                    <!-- Hover preview -->
                                    <div class="airs-theme-preview">
                                        <div class="color-scheme-preview color-scheme-auto">
                                            <div class="preview-split">
                                                <div class="preview-half preview-half-light">
                                                    <div class="preview-header"></div>
                                                    <div class="preview-message"></div>
                                                </div>
                                                <div class="preview-half preview-half-dark">
                                                    <div class="preview-header"></div>
                                                    <div class="preview-message"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                                <button type="button" class="airs-theme-btn<?php echo $color_scheme === 'dark' ? ' active' : ''; ?>" data-value="dark" title="<?php esc_attr_e('Dark', 'ai-chat-search'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                                    </svg>
                                    <!-- Hover preview -->
                                    <div class="airs-theme-preview">
                                        <div class="color-scheme-preview color-scheme-dark">
                                            <div class="preview-header"></div>
                                            <div class="preview-message preview-message-ai"></div>
                                            <div class="preview-message preview-message-user"></div>
                                            <div class="preview-input"></div>
                                        </div>
                                    </div>
                                </button>
                                <input type="hidden" name="listeo_ai_color_scheme" id="listeo_ai_color_scheme" value="<?php echo esc_attr($color_scheme); ?>" />
                            </div>
                            <p class="airs-help-text"><?php _e('Light, system preference (auto), or dark mode.', 'ai-chat-search'); ?></p>
                        </div>
                        <div style="flex-basis: 100%;">
                            <label class="airs-checkbox-label">
                                <input type="checkbox" name="listeo_ai_color_scheme_switcher" value="1" <?php checked(get_option('listeo_ai_color_scheme_switcher'), 1); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text">
                                    <?php _e('Show color scheme switcher in chat window', 'ai-chat-search'); ?>
                                    <small><?php _e('Adds a sun/moon toggle icon letting visitors switch between light and dark mode.', 'ai-chat-search'); ?></small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Loading Animation Style -->
                <div class="airs-form-group">
                    <label class="airs-label"><?php _e('Loading Animation Style', 'ai-chat-search'); ?></label>
                    <p class="airs-help-text" style="margin-top: 0; margin-bottom: 12px;"><?php _e('Choose how the loading indicator appears while the AI is processing your request.', 'ai-chat-search'); ?></p>
                    <?php $loading_style = get_option('listeo_ai_chat_loading_style', 'spinner'); ?>
                    <div class="airs-visual-radio-group">
                        <!-- Spinner + Text Option -->
                        <label class="airs-visual-radio-card <?php echo $loading_style === 'spinner' ? 'selected' : ''; ?>">
                            <input type="radio" name="listeo_ai_chat_loading_style" value="spinner" <?php checked($loading_style, 'spinner'); ?> />
                            <div class="airs-visual-radio-preview">
                                <div class="loading-preview-spinner">
                                    <span class="preview-spinner-icon"></span>
                                    <span class="preview-shimmer-text"><?php _e('Thinking...', 'ai-chat-search'); ?></span>
                                </div>
                            </div>
                            <div class="airs-visual-radio-label"><?php _e('Icon + Text', 'ai-chat-search'); ?></div>
                        </label>
                        <!-- Dots Only Option -->
                        <label class="airs-visual-radio-card <?php echo $loading_style === 'dots' ? 'selected' : ''; ?>">
                            <input type="radio" name="listeo_ai_chat_loading_style" value="dots" <?php checked($loading_style, 'dots'); ?> />
                            <div class="airs-visual-radio-preview">
                                <div class="loading-preview-dots">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                            <div class="airs-visual-radio-label"><?php _e('Dots Only', 'ai-chat-search'); ?></div>
                        </label>
                    </div>
                </div>

                <!-- Typing Animation -->
                <div class="airs-form-group">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" name="listeo_ai_chat_typing_animation" value="1" <?php checked(get_option('listeo_ai_chat_typing_animation', 1), 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Enable Typing Animation', 'ai-chat-search'); ?>
                            <small><?php _e('Show AI responses with a smooth word-by-word typing effect, similar to ChatGPT streaming.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <!-- Hide Images in Results (only show when listing or product post types exist) -->
                <?php
                // Recheck in case this is rendered in different context
                if (!isset($has_listings_or_products)) {
                    $has_listings_or_products = post_type_exists('listing') || post_type_exists('product');
                }
                ?>
                <div class="airs-form-group" style="<?php echo !$has_listings_or_products ? 'display: none;' : ''; ?>">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" name="listeo_ai_chat_hide_images" value="1" <?php checked(get_option('listeo_ai_chat_hide_images', 1), 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Hide Images in Chat Results', 'ai-chat-search'); ?>
                            <small><?php _e('Remove listing/product thumbnails from search results in chat for a cleaner, text-only interface.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <!-- Whitelabel Option (PRO Feature) -->
                <div class="airs-form-group">
                    <?php $whitelabel_enabled = $is_pro && get_option('listeo_ai_chat_whitelabel_enabled', 0); ?>
                    <label class="airs-checkbox-label <?php echo !$is_pro ? 'pro-locked' : ''; ?>">
                        <input type="checkbox"
                               id="listeo_ai_chat_whitelabel_enabled"
                               name="listeo_ai_chat_whitelabel_enabled"
                               value="1"
                               <?php checked($whitelabel_enabled, 1); ?>
                               <?php disabled(!$is_pro); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php if (!$is_pro): ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                            <?php endif; ?>
                            <?php _e('Enable Whitelabel', 'ai-chat-search'); ?>
                            <?php if (!$is_pro): ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                            <?php endif; ?>
                            <small><?php _e('Remove "Powered by Purethemes" badge from chat interface.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                    <?php if (!$is_pro): ?>
                        <p class="airs-help-text" style="margin-left: 30px;">
                            <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('whitelabel')); ?>" target="_blank" class="upgrade-link">
                                <?php _e('Upgrade to Pro to enable whitelabel', 'ai-chat-search'); ?> →
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 3: FLOATING WIDGET -->
        <!-- ========================================== -->
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Floating Widget', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Configure the floating chat bubble that appears on your site.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                <!-- Enable Floating Widget & Custom Icon -->
                <div class="airs-form-group">
                    <div class="airs-form-row" style="display: flex; gap: 20px; flex-wrap: wrap;">
                        <div class="airs-form-col" style="flex: 1;">
                            <label class="airs-checkbox-label">
                                <input type="checkbox" name="listeo_ai_floating_chat_enabled" value="1" <?php checked(get_option('listeo_ai_floating_chat_enabled', 0), 1); ?> />
                                <span class="airs-checkbox-custom"></span>
                                <span class="airs-checkbox-text">
                                    <?php _e('Enable Floating Chat Widget', 'ai-chat-search'); ?>
                                    <small><?php _e('Show a floating chat button on all pages.', 'ai-chat-search'); ?></small>
                                    <?php $widget_position = get_option('listeo_ai_floating_position', 'right'); ?>
                                    <span class="airs-position-toggle" style="margin-top: 6px;" onclick="event.preventDefault(); event.stopPropagation();">
                                        <button type="button" class="airs-position-btn<?php echo $widget_position === 'left' ? ' active' : ''; ?>" data-value="left">
                                            <?php _e('Left', 'ai-chat-search'); ?>
                                        </button>
                                        <button type="button" class="airs-position-btn<?php echo $widget_position === 'right' ? ' active' : ''; ?>" data-value="right">
                                            <?php _e('Right', 'ai-chat-search'); ?>
                                        </button>
                                        <input type="hidden" name="listeo_ai_floating_position" id="listeo_ai_floating_position" value="<?php echo esc_attr($widget_position); ?>" />
                                    </span>
                                </span>
                            </label>

                            <label for="listeo_ai_floating_welcome_bubble" class="airs-label" style="margin-top: 25px;">
                                <?php _e('Welcome Bubble Message', 'ai-chat-search'); ?>
                            </label>
                            <input type="text" id="listeo_ai_floating_welcome_bubble" name="listeo_ai_floating_welcome_bubble" value="<?php echo esc_attr(get_option('listeo_ai_floating_welcome_bubble', __('Hi! How can I help you?', 'ai-chat-search'))); ?>" class="airs-input" placeholder="<?php esc_attr_e('Hi! How can I help you?', 'ai-chat-search'); ?>" />
                            <p class="airs-help-text"><?php _e('Short message displayed above the button on first visit.', 'ai-chat-search'); ?> <strong><?php _e('Leave empty to disable.', 'ai-chat-search'); ?></strong></p>
                        </div>
                        <div class="airs-form-col" style="flex: 0 0 auto; align-self: flex-start; margin-top: 10px; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; max-width: 250px;">
                            <label for="listeo_ai_floating_custom_icon" class="airs-label">
                                <?php _e('Button Icon', 'ai-chat-search'); ?>
                            </label>
                            <?php
                            $custom_icon_id = get_option('listeo_ai_floating_custom_icon', 0);
                            $custom_icon_url = $custom_icon_id ? wp_get_attachment_image_url($custom_icon_id, 'thumbnail') : '';
                            $button_color = get_option('listeo_ai_floating_button_color', '#222222');
                            ?>
                            <div class="airs-media-upload">
                                <input type="hidden" id="listeo_ai_floating_custom_icon" name="listeo_ai_floating_custom_icon" value="<?php echo esc_attr($custom_icon_id); ?>" />
                                <div class="airs-media-preview" id="listeo-custom-icon-preview">
                                    <?php if ($custom_icon_url) : ?>
                                        <div class="airs-media-placeholder" style="width: 60px; height: 60px; background-color: <?php echo esc_attr($button_color); ?>; border-radius: 100px; display: flex; align-items: center; justify-content: center;">
                                            <img src="<?php echo esc_url($custom_icon_url); ?>" alt="Custom icon" width="28" height="28" />
                                        </div>
                                    <?php else : ?>
                                        <div class="airs-media-placeholder" style="width: 60px; height: 60px; background-color: <?php echo esc_attr($button_color); ?>; border-radius: 100px; display: flex; align-items: center; justify-content: center;">
                                            <img src="<?php echo esc_url(LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/icons/chat.svg'); ?>" alt="Default icon" width="28" height="28" />
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="airs-media-buttons" style="margin-top: 10px;">
                                    <button type="button" class="airs-button airs-button-secondary" id="listeo-upload-custom-icon">
                                        <?php _e('Change Icon', 'ai-chat-search'); ?>
                                    </button>
                                    <?php if ($custom_icon_id) : ?>
                                        <button type="button" class="airs-button airs-button-secondary" id="listeo-remove-custom-icon" style="margin-left: 5px;">
                                            <?php _e('Remove', 'ai-chat-search'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Popup Dimensions & Widget Offset -->
                <div class="airs-form-group">
                    <div style="display: flex; gap: 30px; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
                        <div style="flex: 1;">
                            <div style="display: flex; gap: 12px;">
                                <div style="flex: 1;">
                                    <label for="listeo_ai_floating_popup_width" class="airs-label">
                                        <?php _e('Popup Width (px)', 'ai-chat-search'); ?>
                                    </label>
                                    <input type="number" id="listeo_ai_floating_popup_width" name="listeo_ai_floating_popup_width" value="<?php echo esc_attr(get_option('listeo_ai_floating_popup_width', 390)); ?>" class="airs-input" min="320" max="800" step="10" />
                                    <p class="airs-help-text"><?php _e('Default: 390px. Range: 320-800px.', 'ai-chat-search'); ?></p>
                                </div>
                                <div style="flex: 1;">
                                    <label for="listeo_ai_floating_popup_height" class="airs-label">
                                        <?php _e('Popup Height (px)', 'ai-chat-search'); ?>
                                    </label>
                                    <input type="number" id="listeo_ai_floating_popup_height" name="listeo_ai_floating_popup_height" value="<?php echo esc_attr(get_option('listeo_ai_floating_popup_height', 600)); ?>" class="airs-input" min="400" max="900" step="10" />
                                    <p class="airs-help-text"><?php _e('Default: 600px. Range: 400-900px.', 'ai-chat-search'); ?></p>
                                </div>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <?php
                            $offset_desktop_h = get_option('listeo_ai_floating_offset_desktop_h', 20);
                            $offset_desktop_v = get_option('listeo_ai_floating_offset_desktop_v', 20);
                            $offset_mobile_h = get_option('listeo_ai_floating_offset_mobile_h', 20);
                            $offset_mobile_v = get_option('listeo_ai_floating_offset_mobile_v', 20);
                            ?>
                            <label class="airs-label"><?php _e('Widget Offset', 'ai-chat-search'); ?></label>
                            <div>
                                <div class="airs-position-toggle airs-offset-device-toggle" style="margin-bottom: 10px;">
                                    <button type="button" class="airs-position-btn active" data-target="airs-offset-desktop"><?php _e('Desktop', 'ai-chat-search'); ?></button>
                                    <button type="button" class="airs-position-btn" data-target="airs-offset-mobile"><?php _e('Mobile', 'ai-chat-search'); ?></button>
                                </div>
                                <div class="airs-offset-panels">
                                    <div class="airs-offset-panel" id="airs-offset-desktop">
                                        <div style="display: flex; gap: 12px; align-items: center;">
                                            <div>
                                                <label class="airs-label" style="font-size: 12px; margin-bottom: 4px;"><?php _e('Horizontal', 'ai-chat-search'); ?></label>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <input type="number" name="listeo_ai_floating_offset_desktop_h" value="<?php echo esc_attr($offset_desktop_h); ?>" class="airs-input" style="width: 70px;" min="0" max="200" />
                                                    <span style="color: #9ca3af; font-size: 12px;">px</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="airs-label" style="font-size: 12px; margin-bottom: 4px;"><?php _e('Vertical', 'ai-chat-search'); ?></label>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <input type="number" name="listeo_ai_floating_offset_desktop_v" value="<?php echo esc_attr($offset_desktop_v); ?>" class="airs-input" style="width: 70px;" min="0" max="200" />
                                                    <span style="color: #9ca3af; font-size: 12px;">px</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="airs-offset-panel" id="airs-offset-mobile" style="display: none;">
                                        <div style="display: flex; gap: 12px; align-items: center;">
                                            <div>
                                                <label class="airs-label" style="font-size: 12px; margin-bottom: 4px;"><?php _e('Horizontal', 'ai-chat-search'); ?></label>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <input type="number" name="listeo_ai_floating_offset_mobile_h" value="<?php echo esc_attr($offset_mobile_h); ?>" class="airs-input" style="width: 70px;" min="0" max="200" />
                                                    <span style="color: #9ca3af; font-size: 12px;">px</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="airs-label" style="font-size: 12px; margin-bottom: 4px;"><?php _e('Vertical', 'ai-chat-search'); ?></label>
                                                <div style="display: flex; align-items: center; gap: 4px;">
                                                    <input type="number" name="listeo_ai_floating_offset_mobile_v" value="<?php echo esc_attr($offset_mobile_v); ?>" class="airs-input" style="width: 70px;" min="0" max="200" />
                                                    <span style="color: #9ca3af; font-size: 12px;">px</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Initial Header Style -->
                <div class="airs-form-group">
                    <label class="airs-label">
                        <?php _e('Initial Header Style', 'ai-chat-search'); ?>
                    </label>
                    <?php $header_style = get_option('listeo_ai_floating_header_style', 'simple'); ?>
                    <div class="airs-header-style-toggle">
                        <button type="button" class="airs-header-style-btn<?php echo $header_style === 'simple' ? ' active' : ''; ?>" data-value="simple" title="<?php esc_attr_e('Simple', 'ai-chat-search'); ?>">
                            <span class="airs-header-style-text"><?php _e('Simple', 'ai-chat-search'); ?></span>
                            <!-- Hover preview -->
                            <div class="airs-header-style-preview">
                                <div class="header-style-preview header-style-simple">
                                    <div class="preview-header-bar"></div>
                                    <div class="preview-chat-body">
                                        <div class="preview-message"></div>
                                    </div>
                                </div>
                            </div>
                        </button>
                        <button type="button" class="airs-header-style-btn<?php echo $header_style === 'image' ? ' active' : ''; ?>" data-value="image" title="<?php esc_attr_e('Image', 'ai-chat-search'); ?>">
                            <span class="airs-header-style-text"><?php _e('Image', 'ai-chat-search'); ?></span>
                            <!-- Hover preview -->
                            <div class="airs-header-style-preview">
                                <div class="header-style-preview header-style-image">
                                    <div class="preview-header-bar preview-header-image preview-header-pixelated"></div>
                                    <div class="preview-chat-body">
                                        <div class="preview-message"></div>
                                    </div>
                                </div>
                            </div>
                        </button>
                        <button type="button" class="airs-header-style-btn<?php echo $header_style === 'animated' ? ' active' : ''; ?>" data-value="animated" title="<?php esc_attr_e('Animated', 'ai-chat-search'); ?>">
                            <span class="airs-header-style-text"><?php _e('Animated', 'ai-chat-search'); ?></span>
                            <!-- Hover preview -->
                            <div class="airs-header-style-preview">
                                <div class="header-style-preview header-style-image">
                                    <div class="preview-header-bar preview-header-image preview-header-animated"></div>
                                    <div class="preview-chat-body">
                                        <div class="preview-message"></div>
                                    </div>
                                </div>
                            </div>
                        </button>
                        <input type="hidden" name="listeo_ai_floating_header_style" id="listeo_ai_floating_header_style" value="<?php echo esc_attr($header_style); ?>" />
                    </div>

                    <?php
                    $header_bg_id = get_option('listeo_ai_floating_header_bg', 0);
                    $header_bg_url = $header_bg_id ? wp_get_attachment_image_url($header_bg_id, 'medium') : '';
                    $animated_bg_color = get_option('listeo_ai_animated_bg_color', '#1560d0');
                    ?>

                    <!-- Panel: Background Image (shown when "Image" is selected) -->
                    <div id="airs-header-bg-panel" style="margin-top: 15px;<?php echo $header_style !== 'image' ? ' display: none;' : ''; ?>">
                        <div style="display: flex; gap: 30px; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
                            <div style="flex: 1;">
                                <label class="airs-label">
                                    <?php _e('Header Background Image', 'ai-chat-search'); ?>
                                </label>
                                <div class="airs-media-upload">
                                    <input type="hidden" id="listeo_ai_floating_header_bg" name="listeo_ai_floating_header_bg" value="<?php echo esc_attr($header_bg_id); ?>" />
                                    <div class="airs-media-preview" id="listeo-header-bg-preview">
                                        <?php if ($header_bg_url) : ?>
                                            <div class="airs-header-bg-preview-frame">
                                                <img src="<?php echo esc_url($header_bg_url); ?>" alt="Header background" />
                                            </div>
                                        <?php else : ?>
                                            <div class="airs-header-bg-preview-frame airs-header-bg-placeholder">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="airs-media-buttons" style="margin-top: 10px;">
                                        <button type="button" class="airs-button airs-button-secondary" id="listeo-upload-header-bg">
                                            <?php _e('Upload', 'ai-chat-search'); ?>
                                        </button>
                                        <?php if ($header_bg_id) : ?>
                                            <button type="button" class="airs-button airs-button-secondary" id="listeo-remove-header-bg" style="margin-left: 5px;">
                                                <?php _e('Remove', 'ai-chat-search'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="airs-help-text"><?php _e('Recommended size: 400x120px or larger. Image will be cropped to fit.', 'ai-chat-search'); ?></p>
                            </div>
                            <div style="flex: 1;">
                                <label class="airs-checkbox-label" style="margin-top: 0;">
                                    <input type="checkbox" name="listeo_ai_floating_header_overlay" value="1" <?php checked(get_option('listeo_ai_floating_header_overlay', 0), 1); ?> />
                                    <span class="airs-checkbox-custom"></span>
                                    <span class="airs-checkbox-text">
                                        <?php _e('Enable overlay', 'ai-chat-search'); ?>
                                        <small><?php _e('Blends image with chat background.', 'ai-chat-search'); ?></small>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Panel: Animated BG (shown when "Animated" is selected) -->
                    <div id="airs-header-animated-panel" style="margin-top: 15px;<?php echo $header_style !== 'animated' ? ' display: none;' : ''; ?>">
                        <div style="display: flex; gap: 30px; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0; align-items: flex-start;">
                            <div style="flex: 0 0 auto;">
                                <label for="listeo_ai_animated_bg_color" class="airs-label" style="font-weight: 500; font-size: 13px; margin-bottom: 6px;">
                                    <?php _e('Wave Color', 'ai-chat-search'); ?>
                                </label>
                                <input type="text"
                                       id="listeo_ai_animated_bg_color"
                                       name="listeo_ai_animated_bg_color"
                                       value="<?php echo esc_attr($animated_bg_color); ?>"
                                       class="airs-input airs-color-picker"
                                       data-default-color="#1560d0" />
                                <p class="airs-help-text"><?php _e('Base color for the animated wave effect.', 'ai-chat-search'); ?></p>
                            </div>
                            <div style="flex: 1;">
                                <label class="airs-label" style="font-weight: 500; font-size: 13px; margin-bottom: 6px;">
                                    <?php _e('Preview', 'ai-chat-search'); ?>
                                </label>
                                <div class="airs-animated-bg-preview" id="airs-animated-bg-preview"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hide Chat on Selected Pages - Collapsible -->
                <div class="airs-collapsible-section">
                    <div class="airs-collapsible-header" data-section="hide-pages">
                        <span class="airs-collapsible-title">
                            <span class="dashicons dashicons-hidden"></span>
                            <?php _e('Hide Chat on Selected Pages', 'ai-chat-search'); ?>
                        </span>
                        <span class="airs-collapsible-toggle">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </span>
                    </div>
                    <div class="airs-collapsible-content">
                        <div class="airs-form-group">
                            <?php
                            $excluded_pages = get_option('listeo_ai_floating_excluded_pages', array());
                            if (!is_array($excluded_pages)) {
                                $excluded_pages = array();
                            }
                            $all_pages = get_pages(array(
                                'sort_column' => 'post_title',
                                'sort_order' => 'ASC'
                            ));
                            ?>
                            <div class="airs-page-exclusion-list">
                                <?php if (empty($all_pages)) : ?>
                                    <p><?php _e('No pages found.', 'ai-chat-search'); ?></p>
                                <?php else : ?>
                                    <?php foreach ($all_pages as $page) : ?>
                                        <label class="airs-page-exclusion-item">
                                            <input
                                                type="checkbox"
                                                name="listeo_ai_floating_excluded_pages[]"
                                                value="<?php echo esc_attr($page->ID); ?>"
                                                <?php checked(in_array($page->ID, $excluded_pages)); ?>
                                            />
                                            <?php echo esc_html($page->post_title); ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <p class="airs-help-text"><?php _e('Select pages where the floating chat widget should be hidden.', 'ai-chat-search'); ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 4: AI TOOLS -->
        <!-- ========================================== -->
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('AI Tools', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Configure AI behavior, prompts, and interactive features.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                <!-- Force Language -->
                <div class="airs-form-group">
                    <label for="listeo_ai_chat_force_language" class="airs-label">
                        <?php _e('Force Response Language', 'ai-chat-search'); ?>
                    </label>
                    <input type="text"
                           id="listeo_ai_chat_force_language"
                           name="listeo_ai_chat_force_language"
                           class="airs-input"
                           style="max-width: 300px;"
                           value="<?php echo esc_attr(get_option('listeo_ai_chat_force_language', '')); ?>" />
                    <p class="airs-help-text">
                        <strong><?php _e('Leave empty to auto-detect language from messages and user browser.', 'ai-chat-search'); ?></strong>
                        <?php _e('Set a language (e.g., "French") to force AI to always respond in that language.', 'ai-chat-search'); ?>
                    </p>
                </div>

                <!-- System Prompt -->
                <div class="airs-form-group">
                    <?php $max_prompt_length = AI_Chat_Search_Pro_Manager::get_max_system_prompt_length(); ?>
                    <label for="listeo_ai_chat_system_prompt" class="airs-label">
                        <?php if ($max_prompt_length === 0): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                        <?php endif; ?>
                        <?php _e('Custom System Prompt (Additional Instructions)', 'ai-chat-search'); ?>
                        <?php if ($max_prompt_length === 0): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                        <?php else: ?>
                            &nbsp;<a href="https://purethemes.net/ai-chatbot-for-wordpress/#faq"><?php _e('See the FAQ for tips', 'ai-chat-search'); ?> &rarr;</a>
                        <?php endif; ?>
                    </label>
                    <?php if ($max_prompt_length === 0): ?>
                        <div style="margin-bottom: 5px;">
                            <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('system_prompt')); ?>"
                               class="upgrade-link"
                               target="_blank">
                                <?php _e('Upgrade to Pro to add custom instructions', 'ai-chat-search'); ?> &rarr;
                            </a>
                        </div>
                        <textarea id="listeo_ai_chat_system_prompt" rows="4" class="airs-input" disabled placeholder="<?php esc_attr_e('Custom system instructions require Pro version.', 'ai-chat-search'); ?>" style="opacity: 0.6;"></textarea>
                    <?php else: ?>
                        <textarea id="listeo_ai_chat_system_prompt" name="listeo_ai_chat_system_prompt" rows="8" class="airs-input" maxlength="<?php echo esc_attr($max_prompt_length); ?>" data-max-length="<?php echo esc_attr($max_prompt_length); ?>" placeholder="<?php _e('Add custom instructions about your website, business focus, special features...', 'ai-chat-search'); ?>"><?php echo esc_textarea($system_prompt); ?></textarea>
                        <div style="display: flex;align-items: baseline;gap: 0;margin-top: 5px;flex-direction: column;">
                            <span id="system-prompt-counter" style="color: #666;"></span>
                            <?php if (!$is_pro): ?>
                                <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('system_prompt')); ?>"
                                   class="upgrade-link"
                                   target="_blank">
                                    <?php _e('Upgrade to Pro and Increase limit to 6000 characters', 'ai-chat-search'); ?> →
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <p class="airs-help-text">
                        <?php _e('Use this to describe your website, special features, or guide the AI behavior.', 'ai-chat-search'); ?>
                        <?php if ($is_pro): ?>
                            <br><strong><?php _e('Instructions should be short and concise. Long prompts can mislead the LLM - stay under 3000 characters.', 'ai-chat-search'); ?></strong>
                        <?php endif; ?>
                    </p>
                    <?php if ($max_prompt_length > 0): ?>
                    <button type="button" id="insert-post-reference-btn" class="airs-button airs-button-secondary" style="margin-top: 8px; font-size: 13px;<?php echo !$is_pro ? ' opacity: 1; cursor: default;' : ''; ?>" <?php echo !$is_pro ? 'disabled' : ''; ?>>
                        <?php if ($is_pro): ?>
                            <span class="dashicons dashicons-plus-alt2" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
                        <?php else: ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                        <?php endif; ?>
                        <?php _e('Hints for AI', 'ai-chat-search'); ?>
                        <?php if (!$is_pro): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                        <?php endif; ?>
                    </button>
                    <p class="airs-help-text" style="margin-top: 4px;">
                        <?php _e('Direct the AI to use a specific page or document when answering certain questions.', 'ai-chat-search'); ?>
                        <br><strong><?php _e('Optional. Use only if needed for sensitive topics where accuracy matters most.', 'ai-chat-search'); ?></strong>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Hints for AI Modal -->
                <div id="post-reference-modal" class="airs-modal" style="display: none;">
                    <div class="airs-modal-overlay"></div>
                    <div class="airs-modal-content" style="max-width: 560px;">
                        <div class="airs-modal-header" style="flex-direction: row; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0;"><?php esc_html_e('Hints for AI', 'ai-chat-search'); ?></h3>
                            <button type="button" class="listeo-ai-modal-close" id="post-reference-modal-close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="airs-modal-body" style="padding: 20px 24px;">
                            <!-- Add new source form -->
                            <div class="airs-form-group" style="margin-bottom: 12px;">
                                <label class="airs-label"><?php esc_html_e('When user asks about', 'ai-chat-search'); ?></label>
                                <input type="text" id="post-reference-topic" class="airs-input" style="max-width: 100%;" placeholder="<?php esc_attr_e('e.g. shipping, delivery, returns', 'ai-chat-search'); ?>" autocomplete="off" />
                            </div>

                            <div class="airs-form-group" style="margin-bottom: 12px;">
                                <label class="airs-label"><?php esc_html_e('Search in', 'ai-chat-search'); ?></label>
                                <input type="text" id="post-reference-search" class="airs-input" style="max-width: 100%;" placeholder="<?php esc_attr_e('Search page or document by title...', 'ai-chat-search'); ?>" autocomplete="off" />
                                <div id="post-reference-results" style="display: none;"></div>
                                <div id="post-reference-selected" style="margin-top: 8px; display: none;">
                                    <span id="post-reference-selected-text"></span>
                                </div>
                            </div>

                            <button type="button" id="post-reference-add" class="airs-button airs-button-primary" disabled>
                                <span class="dashicons dashicons-plus-alt2" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
                                <?php esc_html_e('Add', 'ai-chat-search'); ?>
                            </button>

                            <!-- Existing sources list -->
                            <div id="knowledge-sources-separator">
                                <div id="knowledge-sources-list">
                                    <?php
                                    $knowledge_sources = get_option('listeo_ai_knowledge_sources', array());
                                    if (!empty($knowledge_sources) && is_array($knowledge_sources)):
                                        foreach ($knowledge_sources as $idx => $ks): ?>
                                        <div class="knowledge-source-item" data-index="<?php echo esc_attr($idx); ?>">
                                            <div style="flex: 1; min-width: 0;">
                                                <div class="ks-label"><?php esc_html_e('When user asks about', 'ai-chat-search'); ?>:</div>
                                                <div class="ks-topic"><?php echo esc_html($ks['topic']); ?></div>
                                                <div class="ks-post"><?php echo esc_html($ks['post_title']); ?> (ID: <?php echo intval($ks['post_id']); ?>)</div>
                                            </div>
                                            <button type="button" class="knowledge-source-delete" data-index="<?php echo esc_attr($idx); ?>" title="<?php esc_attr_e('Delete', 'ai-chat-search'); ?>">&times;</button>
                                        </div>
                                        <?php endforeach;
                                    endif; ?>
                                </div>
                                <div id="knowledge-sources-empty" <?php echo (!empty($knowledge_sources) ? 'style="display: none;"' : ''); ?>>
                                    <?php esc_html_e('No hints added yet.', 'ai-chat-search'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Allow AI to Send Emails (PRO Feature) -->
                <div class="airs-form-group" style="margin-top: 20px;">
                    <?php
                    $is_pro_contact = AI_Chat_Search_Pro_Manager::is_pro_active();
                    $ai_contact_enabled = $is_pro_contact && get_option('listeo_ai_contact_form_allow_ai_send', 0);
                    ?>
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <label class="airs-checkbox-label <?php echo !$is_pro_contact ? 'pro-locked' : ''; ?>" style="flex: 1;">
                            <input type="checkbox"
                                   id="listeo_ai_contact_form_allow_ai_send"
                                   name="listeo_ai_contact_form_allow_ai_send"
                                   value="1"
                                   <?php checked($ai_contact_enabled, 1); ?>
                                   <?php disabled(!$is_pro_contact); ?> />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php if (!$is_pro_contact): ?>
                                    <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                <?php endif; ?>
                                <?php _e('Allow AI to Send Emails to You', 'ai-chat-search'); ?>
                                <?php if (!$is_pro_contact): ?>
                                    <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                <?php endif; ?>
                                <small><?php _e('When enabled, AI can send emails to you on behalf of users who explicitly request it when chatting with AI.', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                        <?php if ($is_pro_contact): ?>
                        <button type="button" class="airs-button airs-button-secondary listeo-configure-contact-form" style="white-space: nowrap; margin-top: 3px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px; vertical-align: middle;">
                                <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <?php _e('Configure', 'ai-chat-search'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_pro_contact): ?>
                        <p class="airs-help-text" style="margin-left: 30px;">
                            <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-email')); ?>" target="_blank" class="upgrade-link">
                                <?php _e('Upgrade to Pro to allow AI to send emails', 'ai-chat-search'); ?> →
                            </a>
                        </p>
                    <?php endif; ?>

                    <!-- Contact Form Examples (Conditional - shown when AI send emails is enabled) -->
                    <?php
                    $default_examples = "EXAMPLES OF WHEN TO USE:\n- \"Can you send a message to the site owner for me?\"\n- \"I want to contact support about X\"\n- \"Please send them my inquiry about Y\"\n\nEXAMPLES OF WHEN NOT TO USE:\n- \"How can I contact you?\" (just provide contact info)\n- \"What's your email?\" (just provide info, don't send)";
                    ?>
                    <div id="listeo_ai_contact_form_examples_wrapper" style="display: <?php echo $ai_contact_enabled ? 'block' : 'none'; ?>; margin-left: 30px; margin-top: 10px;">
                        <label for="listeo_ai_contact_form_examples" class="airs-label">
                            <?php _e('Contact Tool Instructions for AI', 'ai-chat-search'); ?>
                        </label>
                        <textarea id="listeo_ai_contact_form_examples" name="listeo_ai_contact_form_examples" class="airs-input" rows="8" maxlength="1000" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea(get_option('listeo_ai_contact_form_examples', $default_examples)); ?></textarea>
                        <p class="airs-help-text"><?php _e('Customize when AI should or should not use the contact form tool. This helps AI understand your specific use cases.', 'ai-chat-search'); ?></p>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 5: ACCESS & PRIVACY -->
        <!-- ========================================== -->
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Access & Privacy', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Control who can use the chat and how data is handled.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                <!-- Require Login -->
                <div class="airs-form-group">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" name="listeo_ai_chat_require_login" value="1" <?php checked(get_option('listeo_ai_chat_require_login', 0), 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Require Login to Use Chat', 'ai-chat-search'); ?>
                            <small><?php _e('Only logged-in WordPress users can access the AI chat. Shortcode will show a login message, floating widget will be hidden.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <!-- Terms of Use Notice -->
                <div class="airs-form-group">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" id="listeo_ai_chat_terms_notice_enabled" name="listeo_ai_chat_terms_notice_enabled" value="1" <?php checked(get_option('listeo_ai_chat_terms_notice_enabled', 0), 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Show Terms of Use Notice', 'ai-chat-search'); ?>
                            <small><?php _e('Display a terms notice below the chat input for privacy compliance.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <!-- Terms Notice Text (Conditional - shown when checkbox is checked) -->
                <div id="listeo_ai_chat_terms_notice_text_wrapper" style="display: <?php echo get_option('listeo_ai_chat_terms_notice_enabled', 0) ? 'block' : 'none'; ?>; margin-left: 30px;">
                    <label for="listeo_ai_chat_terms_notice_text" class="airs-label">
                        <?php _e('Terms Notice Text', 'ai-chat-search'); ?>
                    </label>
                    <textarea id="listeo_ai_chat_terms_notice_text" name="listeo_ai_chat_terms_notice_text" class="airs-input" rows="2" placeholder="By using this chat, you agree to our Terms of Use and Privacy Policy"><?php echo esc_textarea(get_option('listeo_ai_chat_terms_notice_text', 'By using this chat, you agree to our <a href="/terms-of-use" target="_blank">Terms of Use</a> and <a href="/privacy-policy" target="_blank">Privacy Policy</a>')); ?></textarea>
                    <p class="airs-help-text"><?php _e('Text displayed below the chat input. HTML tags allowed (e.g., &lt;a href="/terms"&gt;Terms&lt;/a&gt;).', 'ai-chat-search'); ?></p>
                </div>

                <!-- Chat History Tracking -->
                <div class="airs-form-group">
                    <label class="airs-checkbox-label">
                        <input type="checkbox" name="listeo_ai_chat_history_enabled" value="1" <?php checked(get_option('listeo_ai_chat_history_enabled', 0), 1); ?> />
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php _e('Enable Chat History Tracking', 'ai-chat-search'); ?>
                            <small><?php _e('Save user questions and AI responses for analytics. Chat history is automatically deleted after 30 days.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <?php if (AI_Chat_Search_Pro_Manager::is_pro_active()): ?>
                    <?php
                    // PRO: Full Pre-Chat Form Configuration - rendered by PRO plugin
                    do_action('listeo_ai_chat_access_privacy_settings');
                    ?>
                <?php else: ?>
                    <!-- FREE: Pre-Chat Form Teaser -->
                    <div class="airs-form-group">
                        <label class="airs-checkbox-label pro-locked">
                            <input type="checkbox" disabled />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                <?php _e('Enable Pre-Chat Form', 'ai-chat-search'); ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                <small><?php _e('Collect visitor information before the chat starts. The submitted data will be visible in chat history.', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                    </div>
                <?php endif; ?>

                <!-- Block IP Addresses (PRO Feature) -->
                <?php
                $is_pro_ip_blocking = AI_Chat_Search_Pro_Manager::is_pro_active();
                ?>
                <div class="airs-form-group" style="margin-top: 20px; border: 1px solid #e0e0e0; border-radius: 5px; padding: 20px;">
                    <label class="airs-label">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px; vertical-align: text-bottom; margin-right: 4px; display: inline-block;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A8.966 8.966 0 0 1 3 12c0-1.264.26-2.467.732-3.558" /></svg><?php _e('Block IP Addresses', 'ai-chat-search'); ?>
                        <?php if (!$is_pro_ip_blocking): ?>
                            <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                        <?php endif; ?>
                    </label>
                    <p class="airs-help-text" style="margin-bottom: 15px;">
                        <?php _e('The chat widget will be completely hidden for visitors from these IP addresses. Supports individual IPs and CIDR ranges (e.g., 192.168.1.0/24).', 'ai-chat-search'); ?>
                    </p>

                    <?php if ($is_pro_ip_blocking): ?>
                        <?php
                        // PRO: Full IP Blocking Configuration - rendered by PRO plugin
                        do_action('listeo_ai_chat_blocked_ips_fields');
                        ?>
                    <?php else: ?>
                        <!-- FREE: IP Blocking Teaser -->
                        <div class="blocked-ips-pro-teaser">
                            <div class="teaser-preview" style="opacity: 0.6; pointer-events: none;">
                                <div style="display: flex; gap: 10px; margin-bottom: 10px; align-items: center;">
                                    <input type="text"
                                           disabled
                                           placeholder="<?php esc_attr_e('e.g., 192.168.1.100 or 10.0.0.0/8', 'ai-chat-search'); ?>"
                                           class="airs-input"
                                           style="flex: 1; max-width: 300px;" />
                                    <button type="button" class="airs-button airs-button-secondary" disabled>
                                        <span class="remove-icon">&times;</span>
                                    </button>
                                </div>
                            </div>

                            <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ip-blocking')); ?>" target="_blank" class="upgrade-link">
                                <?php _e('Upgrade to Pro to block IP addresses', 'ai-chat-search'); ?> →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- SECTION 6: INTEGRATIONS -->
        <!-- ========================================== -->
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Integrations', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Connect AI chat to external services and automation platforms.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">

                <?php if (!AI_Chat_Search_Pro_Manager::is_pro_active()): ?>

                <!-- Webhook Automation (PRO Feature — locked state) -->
                <div class="airs-form-group">
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <label class="airs-checkbox-label pro-locked" style="flex: 1;">
                            <input type="checkbox" disabled />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                <?php _e('Webhook Automation (e.g. N8N, Zapier, Make)', 'ai-chat-search'); ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                <small><?php _e('When enabled, AI can send structured data to external systems (N8N, Zapier, Make) when users explicitly request actions.', 'ai-chat-search'); ?>
                                <br><a href="https://purethemes.net/wordpress-chatbot-n8n-make-zapier-integration/" target="_blank" class="airs-guide-link"><?php _e('Read Guide', 'ai-chat-search'); ?> &rarr;</a></small>
                            </span>
                        </label>
                    </div>
                    <p class="airs-help-text" style="margin-left: 30px;">
                        <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-webhook')); ?>" target="_blank" class="upgrade-link">
                            <?php _e('Upgrade to Pro to enable webhook automations', 'ai-chat-search'); ?> &rarr;
                        </a>
                    </p>
                </div>

                <!-- WhatsApp Integration (PRO Feature — locked state) -->
                <div class="airs-form-group">
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <label class="airs-checkbox-label pro-locked" style="flex: 1;">
                            <input type="checkbox" disabled />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                <span class="airs-channel-icon airs-channel-icon-whatsapp">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                </span>
                                <?php _e('WhatsApp Integration (via Twilio)', 'ai-chat-search'); ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                <small><?php _e('When enabled, users can chat with your AI assistant via WhatsApp.', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                    </div>
                    <p class="airs-help-text" style="margin-left: 30px;">
                        <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-whatsapp')); ?>" target="_blank" class="upgrade-link">
                            <?php _e('Upgrade to Pro to enable WhatsApp integration', 'ai-chat-search'); ?> &rarr;
                        </a>
                    </p>
                </div>

                <!-- Telegram Integration (PRO Feature — locked state) -->
                <div class="airs-form-group">
                    <div style="display: flex; align-items: flex-start; gap: 15px;">
                        <label class="airs-checkbox-label pro-locked" style="flex: 1;">
                            <input type="checkbox" disabled />
                            <span class="airs-checkbox-custom"></span>
                            <span class="airs-checkbox-text">
                                <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                                <span class="airs-channel-icon airs-channel-icon-telegram">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                    </svg>
                                </span>
                                <?php _e('Telegram Integration', 'ai-chat-search'); ?>
                                <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                                <small><?php _e('When enabled, users can chat with your AI assistant via Telegram.', 'ai-chat-search'); ?></small>
                            </span>
                        </label>
                    </div>
                    <p class="airs-help-text" style="margin-left: 30px;">
                        <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-telegram')); ?>" target="_blank" class="upgrade-link">
                            <?php _e('Upgrade to Pro to enable Telegram integration', 'ai-chat-search'); ?> &rarr;
                        </a>
                    </p>
                </div>

                <?php endif; ?>

                <?php do_action('ai_chat_search_integrations_section'); ?>

            </div>
        </div>

        <!-- Hidden fields for remaining settings -->
        <?php $this->add_hidden_fields_except(array(
            'listeo_ai_chat_force_language', 'listeo_ai_chat_system_prompt', 'listeo_ai_chat_enabled', 'listeo_ai_chat_avatar',
            'listeo_ai_chat_model',
            'listeo_ai_chat_name', 'listeo_ai_chat_welcome_message', 'listeo_ai_chat_max_results', 'listeo_ai_chat_woo_cart_enabled',
            'listeo_ai_chat_hide_images', 'listeo_ai_chat_loading_style', 'listeo_ai_chat_typing_animation', 'listeo_ai_chat_require_login', 'listeo_ai_chat_history_enabled',
            'listeo_ai_chat_retention_days', 'listeo_ai_chat_terms_notice_enabled', 'listeo_ai_chat_terms_notice_text',
            'listeo_ai_chat_whitelabel_enabled', 'listeo_ai_contact_form_allow_ai_send', 'listeo_ai_contact_form_examples',
            'listeo_ai_whatsapp_enabled', 'listeo_ai_telegram_enabled',
            'listeo_ai_chat_rate_limit_tier1', 'listeo_ai_chat_rate_limit_tier2', 'listeo_ai_chat_rate_limit_tier3', // Now visible in UI
            'listeo_ai_floating_chat_enabled', 'listeo_ai_floating_position', 'listeo_ai_floating_custom_icon', 'listeo_ai_floating_welcome_bubble',
            'listeo_ai_floating_popup_width', 'listeo_ai_floating_popup_height', 'listeo_ai_floating_button_color',
            'listeo_ai_floating_offset_desktop_h', 'listeo_ai_floating_offset_desktop_v', 'listeo_ai_floating_offset_mobile_h', 'listeo_ai_floating_offset_mobile_v',
            'listeo_ai_primary_color', 'listeo_ai_color_scheme', 'listeo_ai_color_scheme_switcher', 'listeo_ai_floating_header_style', 'listeo_ai_floating_header_bg', 'listeo_ai_floating_header_overlay', 'listeo_ai_animated_bg_color', 'listeo_ai_floating_excluded_pages', 'listeo_ai_chat_quick_buttons_enabled',
            'listeo_ai_chat_quick_buttons_visibility', 'listeo_ai_chat_blocked_ips', 'listeo_ai_chat_enable_speech', 'listeo_ai_chat_enable_image_input',
            'listeo_ai_chat_quick_buttons'
        )); ?>

        <!-- Sticky Footer with Save Button -->
        <div class="airs-sticky-footer">
            <div class="airs-sticky-footer-inner">
                <div class="airs-form-message airs-footer-message" style="display: none;"></div>
                <button type="submit" class="airs-button airs-button-primary">
                    <span class="button-text"><?php _e('Save Settings', 'ai-chat-search'); ?></span>
                    <span class="button-spinner" style="display: none;">
                        <span class="airs-spinner"></span>
                    </span>
                </button>
            </div>
        </div>

        </form>

        <!-- JavaScript for this tab (common UI handlers are in admin-ui.js, only PHP-dependent code remains here) -->
        <script>
        jQuery(document).ready(function($) {
            // Add new quick button row (needs PHP for translations)
            var quickBtnDefaultColor = '<?php echo esc_js(get_option('listeo_ai_primary_color', '#0073ee')); ?>';
            $('#listeo-add-quick-button').on('click', function() {
                var container = $('#listeo-quick-buttons-container');
                var index = container.find('.listeo-quick-button-row').length;
                var newRow = `
                    <div class="listeo-quick-button-row">
                        <div class="listeo-quick-button-color-wrap">
                            <button type="button" class="listeo-quick-btn-color-swatch" style="background-color: #ebebeb;"></button>
                            <input type="hidden"
                                   name="listeo_ai_chat_quick_buttons[${index}][color]"
                                   value=""
                                   class="listeo-quick-button-color-value"
                                   data-default-color="${quickBtnDefaultColor}" />
                        </div>
                        <input type="text"
                               name="listeo_ai_chat_quick_buttons[${index}][text]"
                               value=""
                               placeholder="<?php esc_attr_e('Button Text', 'ai-chat-search'); ?>"
                               class="airs-input listeo-quick-button-text" />
                        <select name="listeo_ai_chat_quick_buttons[${index}][type]"
                                class="airs-input listeo-quick-button-type">
                            <option value="chat"><?php _e('Chat Message', 'ai-chat-search'); ?></option>
                            <option value="url"><?php _e('Link', 'ai-chat-search'); ?></option>
                            <option value="contact"><?php _e('Contact Form', 'ai-chat-search'); ?></option>
                        </select>
                        <input type="text"
                               name="listeo_ai_chat_quick_buttons[${index}][value]"
                               value=""
                               placeholder="<?php esc_attr_e('Message to send', 'ai-chat-search'); ?>"
                               class="airs-input listeo-quick-button-value" />
                        <button type="button" class="airs-button airs-button-primary listeo-configure-contact-form" style="display: none;">
                            <?php _e('Configure', 'ai-chat-search'); ?>
                        </button>
                        <button type="button" class="airs-button airs-button-secondary listeo-remove-quick-button" title="<?php esc_attr_e('Remove', 'ai-chat-search'); ?>">
                            <span class="remove-icon">×</span>
                        </button>
                    </div>
                `;
                container.append(newRow);
            });
        });
        </script>

        <!-- Contact Form Configuration Modal -->
        <div id="contact-form-config-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content" style="max-width: 550px;">
                <div class="airs-modal-header" style="flex-direction: row; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0;"><?php _e('Contact Form Settings', 'ai-chat-search'); ?></h3>
                    <button type="button" id="contact-form-modal-close" class="listeo-ai-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="airs-modal-body">
                    <?php
                    // SMTP Detection
                    $smtp_plugins = array(
                        'wp-mail-smtp/wp_mail_smtp.php' => 'WP Mail SMTP',
                        'fluent-smtp/fluent-smtp.php' => 'FluentSMTP',
                        'post-smtp/postman-smtp.php' => 'Post SMTP',
                        'easy-wp-smtp/easy-wp-smtp.php' => 'Easy WP SMTP',
                        'smtp-mailer/main.php' => 'SMTP Mailer',
                        'wp-smtp/wp-smtp.php' => 'WP SMTP',
                        'mailgun/mailgun.php' => 'Mailgun',
                        'sendgrid-email-delivery-simplified/wpsendgrid.php' => 'SendGrid',
                    );
                    $smtp_detected = false;
                    $smtp_plugin_name = '';
                    foreach ($smtp_plugins as $plugin_file => $plugin_name) {
                        if (is_plugin_active($plugin_file)) {
                            $smtp_detected = true;
                            $smtp_plugin_name = $plugin_name;
                            break;
                        }
                    }
                    ?>

                    <!-- SMTP Status Notice -->
                    <div class="airs-notice <?php echo $smtp_detected ? 'airs-notice-success' : 'airs-notice-warning'; ?>" style="margin-bottom: 20px; padding: 12px 15px; border-radius: 6px; display: flex; align-items: flex-start; gap: 10px;">
                        <?php if ($smtp_detected): ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0; color: #28a745;">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div>
                                <strong><?php _e('SMTP Configured', 'ai-chat-search'); ?></strong><br>
                                <span style="font-size: 13px; color: #666;">
                                    <?php printf(__('%s detected. Emails should be delivered reliably.', 'ai-chat-search'), '<strong>' . esc_html($smtp_plugin_name) . '</strong>'); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink: 0; color: #ffc107;">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div>
                                <strong><?php _e('No SMTP Plugin Detected', 'ai-chat-search'); ?></strong><br>
                                <span style="font-size: 13px; color: #666;">
                                    <?php _e('Using PHP mail() which has poor deliverability. We recommend installing an SMTP plugin like WP Mail SMTP for reliable email delivery.', 'ai-chat-search'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Test Email Section -->
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 15px;">
                            <div>
                                <strong><?php _e('Test Email Delivery', 'ai-chat-search'); ?></strong><br>
                                <span style="font-size: 13px; color: #666;"><?php _e('Send a test email to verify your configuration.', 'ai-chat-search'); ?></span>
                            </div>
                            <button type="button" id="contact-form-test-email-btn" class="airs-button airs-button-secondary" style="white-space: nowrap;">
                                <span class="button-text"><?php _e('Send Test', 'ai-chat-search'); ?></span>
                                <span class="button-spinner" style="display: none;">
                                    <span class="airs-spinner"></span>
                                </span>
                            </button>
                        </div>
                        <div id="contact-form-test-result" class="airs-result-message" style="display: none; margin-top: 10px; padding: 10px; border-radius: 4px; font-size: 13px;"></div>
                    </div>

                    <!-- Form Settings -->
                    <div class="airs-form-group" style="margin-bottom: 15px;">
                        <label for="listeo_ai_contact_form_recipient" class="airs-label"><?php _e('Recipient Email', 'ai-chat-search'); ?></label>
                        <input type="email" id="listeo_ai_contact_form_recipient" class="airs-input" value="<?php echo esc_attr(get_option('listeo_ai_contact_form_recipient', get_option('admin_email'))); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" />
                        <p class="airs-help-text"><?php _e('Email address where contact form messages will be sent.', 'ai-chat-search'); ?></p>
                    </div>

                    <div class="airs-form-group" style="margin-bottom: 15px;">
                        <label for="listeo_ai_contact_form_from_name" class="airs-label"><?php _e('From Name', 'ai-chat-search'); ?></label>
                        <input type="text" id="listeo_ai_contact_form_from_name" class="airs-input" value="<?php echo esc_attr(get_option('listeo_ai_contact_form_from_name', get_bloginfo('name'))); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                    </div>

                    <div class="airs-form-group" style="margin-bottom: 15px;">
                        <label for="listeo_ai_contact_form_from_email" class="airs-label"><?php _e('From Email', 'ai-chat-search'); ?></label>
                        <?php
                        // Try to detect From Email from popular SMTP plugins
                        $smtp_from_email = '';
                        $smtp_source = '';

                        // WP Mail SMTP
                        if (empty($smtp_from_email)) {
                            $wp_mail_smtp = get_option('wp_mail_smtp', array());
                            if (!empty($wp_mail_smtp['mail']['from_email'])) {
                                $smtp_from_email = $wp_mail_smtp['mail']['from_email'];
                                $smtp_source = 'WP Mail SMTP';
                            }
                        }

                        // FluentSMTP
                        if (empty($smtp_from_email)) {
                            $fluent_settings = get_option('fluentmail-settings', array());
                            if (!empty($fluent_settings['connections'])) {
                                $connections = $fluent_settings['connections'];
                                $first_connection = reset($connections);
                                if (!empty($first_connection['sender_email'])) {
                                    $smtp_from_email = $first_connection['sender_email'];
                                    $smtp_source = 'FluentSMTP';
                                }
                            }
                        }

                        // Post SMTP
                        if (empty($smtp_from_email)) {
                            $postman_options = get_option('postman_options', array());
                            if (!empty($postman_options['sender_email'])) {
                                $smtp_from_email = $postman_options['sender_email'];
                                $smtp_source = 'Post SMTP';
                            }
                        }

                        // Easy WP SMTP
                        if (empty($smtp_from_email)) {
                            $easy_smtp = get_option('swpsmtp_options', array());
                            if (!empty($easy_smtp['from_email_field'])) {
                                $smtp_from_email = $easy_smtp['from_email_field'];
                                $smtp_source = 'Easy WP SMTP';
                            }
                        }

                        $current_from_email = get_option('listeo_ai_contact_form_from_email', '');
                        $default_from_email = !empty($smtp_from_email) ? $smtp_from_email : get_option('admin_email');
                        $display_value = !empty($current_from_email) ? $current_from_email : $default_from_email;

                        // Check for mismatch
                        $has_mismatch = !empty($smtp_from_email) && !empty($current_from_email) && $current_from_email !== $smtp_from_email;
                        ?>

                        <?php if (!empty($smtp_from_email)): ?>
                            <div class="airs-notice airs-notice-info" style="margin-bottom: 10px; padding: 10px 12px; border-radius: 4px; font-size: 13px; background: #e8f4fc; border-left: 3px solid #0073ee;">
                                <?php printf(
                                    __('Detected from %s: %s', 'ai-chat-search'),
                                    '<strong>' . esc_html($smtp_source) . '</strong>',
                                    '<code>' . esc_html($smtp_from_email) . '</code>'
                                ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($has_mismatch): ?>
                            <div class="airs-notice airs-notice-error" style="margin-bottom: 10px; padding: 10px 12px; border-radius: 4px; font-size: 13px; background: #fef2f2; border-left: 3px solid #dc3545; color: #991b1b;">
                                <strong><?php _e('⚠️ Mismatch detected!', 'ai-chat-search'); ?></strong><br>
                                <?php _e('Your From Email doesn\'t match your SMTP plugin settings. Emails may fail to deliver.', 'ai-chat-search'); ?>
                            </div>
                        <?php endif; ?>

                        <input type="email" id="listeo_ai_contact_form_from_email" class="airs-input <?php echo $has_mismatch ? 'input-error' : ''; ?>" value="<?php echo esc_attr($display_value); ?>" placeholder="<?php echo esc_attr($default_from_email); ?>" />
                        <p class="airs-help-text">
                            <?php if (!empty($smtp_from_email)): ?>
                                <?php _e('Must match your SMTP plugin\'s verified sender address for reliable delivery.', 'ai-chat-search'); ?>
                            <?php else: ?>
                                <?php _e('The "From" address for outgoing emails. If using an SMTP plugin, this should match your verified sender.', 'ai-chat-search'); ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="airs-form-group" style="margin-bottom: 15px;">
                        <label for="listeo_ai_contact_form_subject" class="airs-label"><?php _e('Email Subject', 'ai-chat-search'); ?></label>
                        <input type="text" id="listeo_ai_contact_form_subject" class="airs-input" value="<?php echo esc_attr(get_option('listeo_ai_contact_form_subject', '[{site_name}] New message from {name}')); ?>" placeholder="[{site_name}] New message from {name}" />
                        <p class="airs-help-text"><?php _e('Available placeholders: {site_name}, {name}, {email}', 'ai-chat-search'); ?></p>
                    </div>

                    <div class="airs-form-group" style="margin-bottom: 15px;">
                        <label for="listeo_ai_contact_form_success_message" class="airs-label"><?php _e('Success Message', 'ai-chat-search'); ?></label>
                        <input type="text" id="listeo_ai_contact_form_success_message" class="airs-input" value="<?php echo esc_attr(get_option('listeo_ai_contact_form_success_message', __('Your message has been sent successfully!', 'ai-chat-search'))); ?>" />
                        <p class="airs-help-text"><?php _e('Message shown to users after successful form submission.', 'ai-chat-search'); ?></p>
                    </div>

                    <div id="contact-form-save-result" class="airs-result-message" style="display: none; margin-bottom: 15px; padding: 10px; border-radius: 4px; font-size: 13px;"></div>
                </div>
                <div class="airs-modal-footer">
                    <button type="button" id="contact-form-save-settings-btn" class="airs-button airs-button-primary">
                        <span class="button-text"><?php _e('Save Settings', 'ai-chat-search'); ?></span>
                        <span class="button-spinner" style="display: none;">
                            <span class="airs-spinner"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <style>
            .airs-notice-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
            .airs-notice-warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
            .airs-result-message.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .airs-result-message.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        </style>

        <?php
        // Hook for Pro plugin modals (WhatsApp, Telegram, etc.) — renders inside airs-admin-wrap
        do_action('ai_chat_search_admin_modals');
        ?>

        <?php
    }

    /**
     * Get list of available languages for translation importer
     *
     * @return array
     */
    private function get_translation_languages() {
        return array(
            'af' => 'Afrikaans', 'ar' => 'العربية', 'bg_BG' => 'Български', 'bn_BD' => 'বাংলা',
            'ca' => 'Català', 'cs_CZ' => 'Čeština‎', 'cy' => 'Cymraeg', 'da_DK' => 'Dansk',
            'de_AT' => 'Deutsch (Österreich)', 'de_CH' => 'Deutsch (Schweiz)', 'de_DE' => 'Deutsch',
            'de_DE_formal' => 'Deutsch (Sie)', 'el' => 'Ελληνικά', 'en_AU' => 'English (Australia)',
            'en_CA' => 'English (Canada)', 'en_GB' => 'English (UK)', 'en_NZ' => 'English (New Zealand)',
            'es_AR' => 'Español de Argentina', 'es_CL' => 'Español de Chile', 'es_CO' => 'Español de Colombia',
            'es_ES' => 'Español', 'es_MX' => 'Español de México', 'et' => 'Eesti', 'eu' => 'Euskara',
            'fa_IR' => 'فارسی', 'fi' => 'Suomi', 'fr_BE' => 'Français de Belgique',
            'fr_CA' => 'Français du Canada', 'fr_FR' => 'Français', 'gl_ES' => 'Galego',
            'he_IL' => 'עִבְרִית', 'hi_IN' => 'हिन्दी', 'hr' => 'Hrvatski', 'hu_HU' => 'Magyar',
            'id_ID' => 'Bahasa Indonesia', 'is_IS' => 'Íslenska', 'it_IT' => 'Italiano', 'ja' => '日本語',
            'ko_KR' => '한국어', 'lt_LT' => 'Lietuvių kalba', 'lv' => 'Latviešu valoda',
            'mk_MK' => 'Македонски јазик', 'ms_MY' => 'Bahasa Melayu', 'nb_NO' => 'Norsk bokmål',
            'nl_BE' => 'Nederlands (België)', 'nl_NL' => 'Nederlands', 'nn_NO' => 'Norsk nynorsk',
            'pl_PL' => 'Polski', 'pt_BR' => 'Português do Brasil', 'pt_PT' => 'Português',
            'ro_RO' => 'Română', 'ru_RU' => 'Русский', 'sk_SK' => 'Slovenčina', 'sl_SI' => 'Slovenščina',
            'sq' => 'Shqip', 'sr_RS' => 'Српски језик', 'sv_SE' => 'Svenska', 'th' => 'ไทย',
            'tl' => 'Tagalog', 'tr_TR' => 'Türkçe', 'uk' => 'Українська', 'vi' => 'Tiếng Việt',
            'zh_CN' => '简体中文', 'zh_HK' => '香港中文版', 'zh_TW' => '繁體中文'
        );
    }

    /**
     * AJAX handler to check translation availability
     */
    public function ajax_check_translation_availability() {
        check_ajax_referer('ai_chat_search_translation_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-chat-search')));
        }

        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : '';
        if (empty($locale)) {
            wp_send_json_error(array('message' => __('Invalid locale.', 'ai-chat-search')));
        }

        // Check if translation file exists on server (using Listeo translations endpoint)
        $check_url = 'https://purethemes.net/listeo-theme-translations/mo/ai-chat-search-' . $locale . '.mo';
        $response = wp_remote_head($check_url, array('timeout' => 10));

        if (200 === wp_remote_retrieve_response_code($response)) {
            wp_send_json_success(array('available' => true));
        } else {
            wp_send_json_success(array('available' => false));
        }
    }

    /**
     * AJAX handler to install translation
     */
    public function ajax_install_translation() {
        check_ajax_referer('ai_chat_search_translation_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-chat-search')));
        }

        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : '';
        if (empty($locale)) {
            wp_send_json_error(array('message' => __('Invalid locale.', 'ai-chat-search')));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;

        $dest_dir = trailingslashit(WP_LANG_DIR) . 'plugins/';

        // Create directory if it doesn't exist
        if (!$wp_filesystem->is_dir($dest_dir)) {
            if (!$wp_filesystem->mkdir($dest_dir, FS_CHMOD_DIR)) {
                wp_send_json_error(array('message' => __('Could not create languages directory.', 'ai-chat-search')));
            }
        }

        $base_url = 'https://purethemes.net/listeo-theme-translations/';
        $success_count = 0;
        $errors = array();

        // Download both .mo and .po files
        foreach (array('mo', 'po') as $ext) {
            $filename = "ai-chat-search-{$locale}.{$ext}";
            $remote_url = $base_url . $ext . '/' . $filename;
            $temp_file = download_url($remote_url, 15);

            if (is_wp_error($temp_file)) {
                continue; // File might not exist, skip
            }

            $dest_path = $dest_dir . $filename;
            if ($wp_filesystem->move($temp_file, $dest_path, true)) {
                $success_count++;
            } else {
                $errors[] = $filename;
                $wp_filesystem->delete($temp_file);
            }
        }

        if ($success_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('Installed %d file(s)', 'ai-chat-search'), $success_count)
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Could not install translation files.', 'ai-chat-search')
            ));
        }
    }

    /**
     * AJAX handler to remove installed translation (switch to English)
     */
    public function ajax_remove_translation() {
        check_ajax_referer('ai_chat_search_translation_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-chat-search')));
        }

        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : '';
        if (empty($locale)) {
            wp_send_json_error(array('message' => __('Invalid locale.', 'ai-chat-search')));
        }

        $dest_dir = trailingslashit(WP_LANG_DIR) . 'plugins/';
        $removed = 0;

        foreach (array('mo', 'po') as $ext) {
            $file_path = $dest_dir . "ai-chat-search-{$locale}.{$ext}";
            if (file_exists($file_path)) {
                wp_delete_file($file_path);
                if (!file_exists($file_path)) {
                    $removed++;
                }
            }
        }

        if ($removed > 0) {
            wp_send_json_success(array(
                'message' => __('Translation removed. Plugin will now use English.', 'ai-chat-search')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('No translation files found to remove.', 'ai-chat-search')
            ));
        }
    }

    /**
     * AJAX handler for clearing all embeddings when switching AI provider
     */
    public function ajax_clear_embeddings_for_provider_switch() {
        // Verify nonce
        if (!check_ajax_referer('listeo_ai_clear_embeddings', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        // Clear all embeddings using Database Manager
        if (!class_exists('Listeo_AI_Search_Database_Manager')) {
            wp_send_json_error(array('message' => __('Database manager class not found.', 'ai-chat-search')));
            return;
        }

        $result = Listeo_AI_Search_Database_Manager::clear_all_embeddings();

        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to clear embeddings.', 'ai-chat-search')));
            return;
        }

        wp_send_json_success(array(
            'message' => __('All embeddings have been successfully cleared.', 'ai-chat-search')
        ));
    }

    // Note: Admin JavaScript has been moved to external files in assets/js/admin/
    // - admin-core.js: AJAX helpers and utilities
    // - admin-settings.js: Provider toggle, API tests, form handling
    // - admin-embeddings.js: Batch processing, embedding viewer
    // - admin-database.js: Database status, actions, search
    // - admin-media.js: WordPress media uploader
    // Dead Safe Mode code has been removed as it was never implemented.

    /**
     * Show version mismatch notice between free and pro plugins
     */
    public function show_version_mismatch_notice() {
        $screen = get_current_screen();
        if ( ! $screen || $screen->id !== 'toplevel_page_ai-chat-search' ) {
            return;
        }

        if ( ! defined( 'AI_CHAT_SEARCH_PRO_VERSION' ) || ! defined( 'LISTEO_AI_SEARCH_VERSION' ) ) {
            return;
        }

        $free_version = LISTEO_AI_SEARCH_VERSION;
        $pro_version  = AI_CHAT_SEARCH_PRO_VERSION;

        if ( version_compare( $free_version, $pro_version, '==' ) ) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p>%s <strong>%s</strong> | %s <strong>%s</strong></p></div>',
            esc_html__( 'Version Mismatch:', 'ai-chat-search' ),
            esc_html__( 'AI Chat & Search and AI Chat & Search Pro are running different versions. Please update both plugins to the same version to avoid compatibility issues.', 'ai-chat-search' ),
            esc_html__( 'Base:', 'ai-chat-search' ),
            esc_html( $free_version ),
            esc_html__( 'Pro:', 'ai-chat-search' ),
            esc_html( $pro_version )
        );
    }

    /**
     * Show debug mode notice
     */
    public function show_debug_mode_notice() {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'toplevel_page_ai-chat-search') {
            $debug_file = WP_CONTENT_DIR . '/debug.log';
            echo '<div class="notice notice-info">';
            echo '<p><strong>Debug Mode Active:</strong> Detailed logs are being written to <code>' . esc_html($debug_file) . '</code></p>';

            echo '</div>';
        }
    }
}
