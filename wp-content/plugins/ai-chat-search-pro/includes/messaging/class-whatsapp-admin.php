<?php
/**
 * WhatsApp Admin Settings
 *
 * Renders WhatsApp checkbox in the free plugin's Integrations section
 * and a configuration modal. All via hooks — zero modification to free plugin UI code.
 *
 * @package AI_Chat_Search_Pro
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_WhatsApp_Admin {

    public function __construct() {
        // Checkbox in Integrations section
        add_action('ai_chat_search_integrations_section', array($this, 'render_integration_checkbox'));

        // Modal rendered inside airs-admin-wrap
        add_action('ai_chat_search_admin_modals', array($this, 'render_modal'));

        // Register settings in central registry
        add_filter('ai_chat_search_settings_registry', array($this, 'register_settings'));

        // Custom sanitization for WhatsApp number
        add_filter('ai_chat_search_sanitize_setting', array($this, 'sanitize_whatsapp_number'), 10, 3);

        // Enqueue shared messaging admin JS
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Conversation badge
        add_action('ai_chat_search_conversation_id_badge', array($this, 'render_conversation_badge'));

        // AJAX handlers
        add_action('wp_ajax_listeo_ai_save_whatsapp_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_test_whatsapp_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Enqueue shared messaging admin script
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'ai-chat-search') === false) {
            return;
        }

        if (!class_exists('AI_Chat_Search_Pro_Manager') || !AI_Chat_Search_Pro_Manager::is_pro_active()) {
            return;
        }

        wp_enqueue_script(
            'airs-messaging-admin',
            AI_CHAT_SEARCH_PRO_URL . 'assets/js/messaging-admin.js',
            array('jquery'),
            AI_CHAT_SEARCH_PRO_VERSION,
            true
        );

        wp_localize_script('airs-messaging-admin', 'airsMessagingAdmin', array(
            'whatsapp_save_action' => 'listeo_ai_save_whatsapp_settings',
            'whatsapp_save_nonce'  => wp_create_nonce('whatsapp_settings'),
            'whatsapp_test_action' => 'test_whatsapp_connection',
            'whatsapp_test_nonce'  => wp_create_nonce('whatsapp_test'),
            'telegram_save_action' => 'listeo_ai_save_telegram_settings',
            'telegram_save_nonce'  => wp_create_nonce('telegram_settings'),
            'telegram_test_action' => 'test_telegram_connection',
            'telegram_test_nonce'  => wp_create_nonce('telegram_test'),
            'requestFailed'        => __('Request failed. Please try again.', 'ai-chat-search'),
            'testing'              => __('Testing...', 'ai-chat-search'),
            'testConnection'       => __('Test Connection', 'ai-chat-search'),
            'connectionFailed'     => __('Connection failed', 'ai-chat-search'),
            'copied'               => __('Copied!', 'ai-chat-search'),
            'copy'                 => __('Copy', 'ai-chat-search'),
        ));
    }

    /**
     * Render WhatsApp badge next to conversation ID
     */
    public function render_conversation_badge($conversation_id) {
        if (strpos($conversation_id, 'wa_') === 0) {
            echo '<span class="airs-badge airs-badge-whatsapp">WhatsApp</span>';
        }
    }

    /**
     * Register WhatsApp settings in the central settings registry
     */
    public function register_settings($registry) {
        $settings = array(
            'listeo_ai_whatsapp_account_sid'     => array('type' => 'text', 'section' => 'whatsapp-integration', 'sanitize' => 'sanitize_text_field', 'default' => ''),
            'listeo_ai_whatsapp_auth_token'      => array('type' => 'text', 'section' => 'whatsapp-integration', 'sanitize' => 'sanitize_text_field', 'default' => ''),
            'listeo_ai_whatsapp_from_number'     => array('type' => 'text', 'section' => 'whatsapp-integration', 'sanitize' => 'sanitize_text_field', 'default' => ''),
        );

        return array_merge($registry, $settings);
    }

    /**
     * Auto-prefix whatsapp: to phone number
     */
    public function sanitize_whatsapp_number($value, $key, $raw_value) {
        if ($key !== 'listeo_ai_whatsapp_from_number') {
            return $value;
        }
        $number = sanitize_text_field($raw_value);
        if (!empty($number) && strpos($number, 'whatsapp:') !== 0) {
            $number = 'whatsapp:' . $number;
        }
        return $number;
    }

    /**
     * Render WhatsApp checkbox in the Integrations section (follows N8N pattern)
     */
    public function render_integration_checkbox() {
        $is_pro = class_exists('AI_Chat_Search_Pro_Manager') && AI_Chat_Search_Pro_Manager::is_pro_active();
        $enabled = get_option('listeo_ai_whatsapp_enabled', 0);
        ?>
        <div class="airs-form-group" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;">
            <label class="airs-checkbox-label" style="flex: 1;">
                <input type="checkbox"
                       name="listeo_ai_whatsapp_enabled"
                       value="1"
                       <?php checked($enabled, 1); ?>
                       <?php disabled(!$is_pro); ?> />
                <span class="airs-checkbox-custom"></span>
                <span class="airs-checkbox-text">
                    <?php if (!$is_pro): ?>
                        <?php echo AI_Chat_Search_Pro_Manager::get_lock_icon(); ?>
                    <?php endif; ?>
                    <span class="airs-channel-icon airs-channel-icon-whatsapp">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </span>
                    <?php _e('WhatsApp Integration (via Twilio)', 'ai-chat-search'); ?>
                    <?php if (!$is_pro): ?>
                        <?php echo AI_Chat_Search_Pro_Manager::get_pro_badge(); ?>
                    <?php endif; ?>
                    <small><?php _e('When enabled, users can chat with your AI assistant via WhatsApp.', 'ai-chat-search'); ?>
                    <br><a href="https://purethemes.net/wordpress-chatbot-whatsapp-telegram-integration/" target="_blank" class="airs-guide-link"><?php _e('Read Guide', 'ai-chat-search'); ?> &rarr;</a></small>
                </span>
            </label>
            <?php if ($is_pro): ?>
            <button type="button" class="airs-button airs-button-secondary" data-open-modal="whatsapp-config-modal" style="white-space: nowrap; margin-top: 3px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 5px; vertical-align: middle;">
                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <?php _e('Configure', 'ai-chat-search'); ?>
            </button>
            <?php endif; ?>
        </div>
        <?php if (!$is_pro): ?>
            <p class="airs-help-text" style="margin-left: 30px;">
                <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('ai-whatsapp')); ?>" target="_blank" class="upgrade-link">
                    <?php _e('Upgrade to Pro to enable WhatsApp integration', 'ai-chat-search'); ?> →
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

        $account_sid     = get_option('listeo_ai_whatsapp_account_sid', '');
        $auth_token      = get_option('listeo_ai_whatsapp_auth_token', '');
        $from_number     = get_option('listeo_ai_whatsapp_from_number', '');
        $webhook_url     = rest_url('listeo/v1/whatsapp-webhook');
        $is_configured   = !empty($account_sid) && !empty($auth_token) && !empty($from_number);
        ?>
        <!-- WhatsApp Configuration Modal -->
        <div id="whatsapp-config-modal" class="airs-modal" style="display: none;">
            <div class="airs-modal-overlay"></div>
            <div class="airs-modal-content" style="max-width: 600px;">
                <div class="airs-modal-header" style="flex-direction: row; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background: #25D366; color: white;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0 !important;">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </span>
                        <?php esc_html_e('WhatsApp Configuration', 'ai-chat-search'); ?>
                    </h3>
                    <button type="button" class="listeo-ai-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="airs-modal-body">
                    <!-- Quick Setup Guide -->
                    <div class="airs-shortcode-builder-info" style="margin-bottom: 20px;">
                        <span><?php _e('Create a <a href="https://www.twilio.com/console" target="_blank">Twilio account</a>, enable WhatsApp Sandbox (or get a production number), copy your credentials below, and paste the Webhook URL in your Twilio console.', 'ai-chat-search'); ?></span>
                    </div>

                    <!-- Account SID -->
                    <div class="airs-form-group">
                        <label for="listeo_ai_whatsapp_account_sid" class="airs-label">
                            <?php esc_html_e('Account SID', 'ai-chat-search'); ?>
                        </label>
                        <input type="text" id="listeo_ai_whatsapp_account_sid" data-field="account_sid"
                               value="<?php echo esc_attr($account_sid); ?>" class="airs-input" placeholder="AC..." />
                        <div class="airs-help-text"><?php esc_html_e('Found on your Twilio Console dashboard.', 'ai-chat-search'); ?></div>
                    </div>

                    <!-- Auth Token -->
                    <div class="airs-form-group">
                        <label for="listeo_ai_whatsapp_auth_token" class="airs-label">
                            <?php esc_html_e('Auth Token', 'ai-chat-search'); ?>
                        </label>
                        <input type="password" id="listeo_ai_whatsapp_auth_token" data-field="auth_token"
                               value="<?php echo esc_attr($auth_token); ?>" class="airs-input" />
                        <div class="airs-help-text"><?php esc_html_e('Keep this secret - never share it publicly.', 'ai-chat-search'); ?></div>
                    </div>

                    <!-- WhatsApp Number -->
                    <div class="airs-form-group">
                        <label for="listeo_ai_whatsapp_from_number" class="airs-label">
                            <?php esc_html_e('WhatsApp Number', 'ai-chat-search'); ?>
                        </label>
                        <input type="text" id="listeo_ai_whatsapp_from_number" data-field="from_number"
                               value="<?php echo esc_attr($from_number); ?>" class="airs-input" placeholder="whatsapp:+14155238886" />
                        <div class="airs-help-text"><?php esc_html_e('Your Twilio WhatsApp number (sandbox or production).', 'ai-chat-search'); ?></div>
                    </div>

                    <!-- Webhook URL -->
                    <div class="airs-form-group" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <label class="airs-label"><?php esc_html_e('Webhook URL', 'ai-chat-search'); ?></label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" value="<?php echo esc_attr($webhook_url); ?>" class="airs-input" readonly style="flex: 1; background: #f9fafb;" />
                            <button type="button" class="airs-button airs-button-secondary" data-copy-url="<?php echo esc_attr($webhook_url); ?>">
                                <?php esc_html_e('Copy', 'ai-chat-search'); ?>
                            </button>
                        </div>
                        <div class="airs-help-text"><?php esc_html_e('Paste this URL in Twilio Console > Messaging > WhatsApp Sandbox > "When a message comes in".', 'ai-chat-search'); ?></div>
                    </div>

                    <!-- Test Connection -->
                    <?php if ($is_configured): ?>
                    <div class="airs-form-group" style="margin-top: 16px;">
                        <button type="button" class="airs-button airs-button-secondary" data-test-action="whatsapp" data-label="<?php esc_attr_e('Test Connection', 'ai-chat-search'); ?>">
                            <?php esc_html_e('Test Connection', 'ai-chat-search'); ?>
                        </button>
                        <span class="airs-test-result"></span>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="airs-result-message" style="display: none; margin: 0 20px 15px; padding: 10px; border-radius: 4px; font-size: 13px;"></div>
                <div class="airs-modal-footer">
                    <button type="button" class="airs-button airs-button-primary" data-save-action="whatsapp">
                        <span class="button-text"><?php esc_html_e('Save Settings', 'ai-chat-search'); ?></span>
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
     * AJAX: Save WhatsApp settings
     */
    public function ajax_save_settings() {
        if (!check_ajax_referer('whatsapp_settings', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
        }

        $account_sid     = isset($_POST['account_sid']) ? sanitize_text_field($_POST['account_sid']) : '';
        $auth_token      = isset($_POST['auth_token']) ? sanitize_text_field($_POST['auth_token']) : '';
        $from_number     = isset($_POST['from_number']) ? sanitize_text_field($_POST['from_number']) : '';
        // Auto-prefix whatsapp: to phone number
        if (!empty($from_number) && strpos($from_number, 'whatsapp:') !== 0) {
            $from_number = 'whatsapp:' . $from_number;
        }

        update_option('listeo_ai_whatsapp_account_sid', $account_sid);
        update_option('listeo_ai_whatsapp_auth_token', $auth_token);
        update_option('listeo_ai_whatsapp_from_number', $from_number);

        wp_send_json_success(array(
            'message' => __('Settings saved successfully!', 'ai-chat-search'),
        ));
    }

    /**
     * AJAX: Test Twilio connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('whatsapp_test', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'ai-chat-search')));
        }

        $account_sid = get_option('listeo_ai_whatsapp_account_sid', '');
        $auth_token  = get_option('listeo_ai_whatsapp_auth_token', '');

        if (empty($account_sid) || empty($auth_token)) {
            wp_send_json_error(array('message' => __('Credentials not configured. Save settings first.', 'ai-chat-search')));
        }

        $response = wp_remote_get(
            sprintf('https://api.twilio.com/2010-04-01/Accounts/%s.json', $account_sid),
            array(
                'headers' => array('Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token)),
                'timeout' => 10,
            )
        );

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Connected to Twilio account: %s', 'ai-chat-search'),
                    isset($body['friendly_name']) ? $body['friendly_name'] : __('Unknown', 'ai-chat-search')
                ),
            ));
        } elseif ($code === 401) {
            wp_send_json_error(array('message' => __('Invalid credentials. Check Account SID and Auth Token.', 'ai-chat-search')));
        } else {
            wp_send_json_error(array('message' => sprintf(__('API returned status code: %d', 'ai-chat-search'), $code)));
        }
    }
}
