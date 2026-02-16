<?php
/**
 * Telegram Admin Settings
 *
 * Renders Telegram checkbox in the free plugin's Integrations section
 * and a configuration modal. All via hooks — zero modification to free plugin UI code.
 *
 * @package AI_Chat_Search_Pro
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_Telegram_Admin {

    public function __construct() {
        // Checkbox in Integrations section
        add_action('ai_chat_search_integrations_section', array($this, 'render_integration_checkbox'));

        // Modal rendered inside airs-admin-wrap
        add_action('ai_chat_search_admin_modals', array($this, 'render_modal'));

        // Register settings in central registry
        add_filter('ai_chat_search_settings_registry', array($this, 'register_settings'));

        // Conversation badge
        add_action('ai_chat_search_conversation_id_badge', array($this, 'render_conversation_badge'));

        // AJAX handlers
        add_action('wp_ajax_listeo_ai_save_telegram_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_test_telegram_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Render Telegram badge next to conversation ID
     */
    public function render_conversation_badge($conversation_id) {
        if (strpos($conversation_id, 'tg_') === 0) {
            echo '<span class="airs-badge airs-badge-telegram">Telegram</span>';
        }
    }

    /**
     * Register Telegram settings in the central settings registry
     */
    public function register_settings($registry) {
        $settings = array(
            'listeo_ai_telegram_bot_token' => array('type' => 'text', 'section' => 'telegram-integration', 'sanitize' => 'sanitize_text_field', 'default' => ''),
        );

        return array_merge($registry, $settings);
    }

    /**
     * Render Telegram checkbox in the Integrations section
     */
    public function render_integration_checkbox() {
        $is_pro = class_exists('AI_Chat_Search_Pro_Manager') && AI_Chat_Search_Pro_Manager::is_pro_active();
        $enabled = get_option('listeo_ai_telegram_enabled', 0);
        ?>
        <div class="airs-form-group" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
            <label class="airs-checkbox-label" style="flex: 1;">
                <input type="checkbox"
                       name="listeo_ai_telegram_enabled"
                       value="1"
                       <?php checked($enabled, 1); ?>
                       <?php disabled(!$is_pro); ?> />
                <span class="airs-checkbox-custom"></span>
                <span class="airs-checkbox-text">
                    <?php if (!$is_pro): ?>
                        <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                    <?php endif; ?>
                    <span class="airs-channel-icon airs-channel-icon-telegram">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                    </span>
                    <?php _e('Telegram Integration', 'ai-chat-search-pro'); ?>
                    <?php if (!$is_pro): ?>
                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                    <?php endif; ?>
                    <small><?php _e('When enabled, users can chat with your AI assistant via Telegram.', 'ai-chat-search-pro'); ?>
                    <br><a href="https://purethemes.net/wordpress-chatbot-whatsapp-telegram-integration/" target="_blank" class="airs-guide-link"><?php _e('Read Guide', 'ai-chat-search-pro'); ?> &rarr;</a></small>
                </span>
            </label>
            <?php if ($is_pro): ?>
            <button type="button" class="airs-button airs-button-secondary" data-open-modal="telegram-config-modal" style="white-space: nowrap; margin-top: 3px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px; vertical-align: middle;">
                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php _e('Configure', 'ai-chat-search-pro'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php if (!$is_pro): ?>
            <p class="airs-help-text" style="margin-left: 30px;">
                <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-telegram')); ?>" target="_blank" class="upgrade-link">
                    <?php _e('Upgrade to Pro to enable Telegram integration', 'ai-chat-search-pro'); ?> &rarr;
                </a>
            </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render configuration modal
     */
    public function render_modal() {
        if (!class_exists('AI_Chat_Search_Pro_Manager') || !AI_Chat_Search_Pro_Manager::is_pro_active()) {
            return;
        }

        $bot_token       = get_option('listeo_ai_telegram_bot_token', '');
        $is_configured   = !empty($bot_token) && !empty(get_option('listeo_ai_telegram_secret_token', ''));
        ?>
        <!-- Telegram Configuration Modal -->
        <div id="telegram-config-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content" style="max-width: 600px;">
                <div class="airs-modal-header" style="flex-direction: row; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #0088cc; color: white;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0 !important;">
                                <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                            </svg>
                        </span>
                        <?php esc_html_e('Telegram Configuration', 'ai-chat-search-pro'); ?>
                    </h3>
                    <button type="button" class="listeo-ai-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="airs-modal-body">
                    <!-- Quick Setup Guide -->
                    <div class="airs-shortcode-builder-info" style="margin-bottom: 20px;">
                        <span><?php _e('Open <a href="https://t.me/BotFather" target="_blank">@BotFather</a> on Telegram, use the /newbot command to create a bot, then paste the bot token below and save settings.', 'ai-chat-search-pro'); ?></span>
                    </div>

                    <!-- Bot Token -->
                    <div class="airs-form-group">
                        <label for="listeo_ai_telegram_bot_token" class="airs-label">
                            <?php esc_html_e('Bot Token', 'ai-chat-search-pro'); ?>
                        </label>
                        <input type="password" id="listeo_ai_telegram_bot_token" data-field="bot_token"
                               value="<?php echo esc_attr($bot_token); ?>" class="airs-input" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" />
                        <div class="airs-help-text"><?php esc_html_e('The token you received from @BotFather when creating your bot.', 'ai-chat-search-pro'); ?></div>
                    </div>

                    <!-- Test Connection -->
                    <?php if ($is_configured): ?>
                    <div class="airs-form-group" style="margin-top: 16px;">
                        <button type="button" class="airs-button airs-button-secondary" data-test-action="telegram" data-label="<?php esc_attr_e('Test Connection', 'ai-chat-search-pro'); ?>">
                            <?php esc_html_e('Test Connection', 'ai-chat-search-pro'); ?>
                        </button>
                        <span class="airs-test-result"></span>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="airs-result-message" style="display: none; margin: 0 20px 15px; padding: 10px; border-radius: 4px; font-size: 13px;"></div>
                <div class="airs-modal-footer">
                    <button type="button" class="airs-button airs-button-primary" data-save-action="telegram">
                        <span class="button-text"><?php esc_html_e('Save Settings', 'ai-chat-search-pro'); ?></span>
                        <span class="button-spinner" style="display: none;">
                            <span class="airs-spinner"></span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save Telegram settings
     */
    public function ajax_save_settings() {
        if (!check_ajax_referer('telegram_settings', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search-pro')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search-pro')));
        }

        $bot_token = isset($_POST['bot_token']) ? sanitize_text_field($_POST['bot_token']) : '';

        update_option('listeo_ai_telegram_bot_token', $bot_token);

        // Auto-register webhook with Telegram if bot token is provided
        if (!empty($bot_token)) {
            $secret_token = get_option('listeo_ai_telegram_secret_token', '');
            if (empty($secret_token)) {
                $secret_token = wp_generate_password(32, false);
            }

            $webhook_url = rest_url('listeo/v1/telegram-webhook');
            $response = wp_remote_post(
                'https://api.telegram.org/bot' . $bot_token . '/setWebhook',
                array(
                    'headers' => array('Content-Type' => 'application/json'),
                    'body'    => wp_json_encode(array(
                        'url'             => $webhook_url,
                        'secret_token'    => $secret_token,
                        'allowed_updates' => array('message'),
                    )),
                    'timeout' => 15,
                )
            );

            $tg_body = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);

            if (is_wp_error($response) || empty($tg_body['ok'])) {
                $error = is_wp_error($response) ? $response->get_error_message() : (isset($tg_body['description']) ? $tg_body['description'] : 'Unknown error');
                wp_send_json_error(array(
                    'message' => sprintf(
                        __('Settings saved, but webhook registration failed: %s', 'ai-chat-search-pro'),
                        $error
                    ),
                ));
            }

            update_option('listeo_ai_telegram_secret_token', $secret_token);

            wp_send_json_success(array(
                'message' => __('Settings saved and webhook registered successfully!', 'ai-chat-search-pro'),
            ));
        }

        wp_send_json_success(array(
            'message' => __('Settings saved successfully!', 'ai-chat-search-pro'),
        ));
    }

    /**
     * AJAX: Test Telegram connection (calls getMe)
     */
    public function ajax_test_connection() {
        check_ajax_referer('telegram_test', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-chat-search-pro')));
        }

        $bot_token = get_option('listeo_ai_telegram_bot_token', '');

        if (empty($bot_token)) {
            wp_send_json_error(array('message' => __('Bot token not configured. Save settings first.', 'ai-chat-search-pro')));
        }

        $response = wp_remote_get(
            'https://api.telegram.org/bot' . $bot_token . '/getMe',
            array('timeout' => 10)
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && !empty($body['ok'])) {
            $bot_name = isset($body['result']['first_name']) ? $body['result']['first_name'] : '';
            $bot_username = isset($body['result']['username']) ? '@' . $body['result']['username'] : '';
            wp_send_json_success(array(
                'message' => sprintf(
                    /* translators: %1$s: bot name, %2$s: bot username */
                    __('Connected to bot: %1$s (%2$s)', 'ai-chat-search-pro'),
                    $bot_name,
                    $bot_username
                ),
            ));
        } elseif ($code === 401) {
            wp_send_json_error(array('message' => __('Invalid bot token. Check the token from @BotFather.', 'ai-chat-search-pro')));
        } else {
            $error_desc = isset($body['description']) ? $body['description'] : sprintf(__('API returned status code: %d', 'ai-chat-search-pro'), $code);
            wp_send_json_error(array('message' => $error_desc));
        }
    }

}
