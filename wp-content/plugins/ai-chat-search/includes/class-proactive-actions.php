<?php
/**
 * Proactive Actions admin settings and rule matching.
 *
 * @package Listeo_AI_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Proactive_Actions {

    const OPTION_KEY = 'listeo_ai_proactive_actions';

    public function __construct() {
        $this->maybe_migrate_slashed_settings();

        add_filter('ai_chat_search_settings_registry', array($this, 'register_setting'));
        add_filter('ai_chat_search_sanitize_setting', array($this, 'sanitize_setting'), 10, 3);
        add_filter('ai_chat_search_hidden_fields_except', array($this, 'exclude_from_hidden_fields'));

        add_action('ai_chat_search_chat_sidebar_before_quick_buttons', array($this, 'render_sidebar_item'));
        add_action('ai_chat_search_chat_settings_sections', array($this, 'render_settings_section'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_listeo_ai_proactive_search_content', array($this, 'ajax_search_content'));

        add_filter('listeo_ai_floating_chat_config', array($this, 'add_frontend_config'));
    }

    /**
     * Register the single option used by this module.
     *
     * @param array $registry Settings registry.
     * @return array
     */
    public function register_setting($registry) {
        $registry[self::OPTION_KEY] = array(
            'type'        => 'array',
            'section'     => 'ai-chat-config',
            'sanitize'    => 'sanitize_proactive_actions',
            'default'     => array(
                'enabled'              => 0,
                'interaction_cooldown' => 'session',
                'display_mode'         => 'all',
                'rules'                => array(),
            ),
            'description' => 'Proactive chat actions',
        );

        return $registry;
    }

    /**
     * Keep the visible option out of the form's preservation fields.
     *
     * @param array $excluded Excluded setting keys.
     * @return array
     */
    public function exclude_from_hidden_fields($excluded) {
        $excluded[] = self::OPTION_KEY;
        return array_unique($excluded);
    }

    /**
     * Sanitize the module option.
     *
     * @param mixed  $value     Filtered value.
     * @param string $key       Setting key.
     * @param mixed  $raw_value Raw submitted value.
     * @return mixed
     */
    public function sanitize_setting($value, $key, $raw_value) {
        if ($key !== self::OPTION_KEY) {
            return $value;
        }

        $sanitized = array(
            'enabled'              => 0,
            'interaction_cooldown' => 'session',
            'display_mode'         => 'all',
            'rules'                => array(),
        );

        if (!is_array($raw_value)) {
            return $sanitized;
        }

        $raw_value = wp_unslash($raw_value);

        $sanitized['enabled'] = empty($raw_value['enabled']) ? 0 : 1;
        $interaction_cooldown = isset($raw_value['interaction_cooldown'])
            ? sanitize_key($raw_value['interaction_cooldown'])
            : 'session';
        $display_mode = isset($raw_value['display_mode'])
            ? sanitize_key($raw_value['display_mode'])
            : 'all';

        if (!in_array($interaction_cooldown, array('none', 'session', '30_minutes', '1_hour', '24_hours', '7_days'), true)) {
            $interaction_cooldown = 'session';
        }

        if (!in_array($display_mode, array('highest_priority', 'all'), true)) {
            $display_mode = 'all';
        }

        $sanitized['interaction_cooldown'] = $interaction_cooldown;
        $sanitized['display_mode'] = $display_mode;

        $rules = isset($raw_value['rules']) && is_array($raw_value['rules'])
            ? array_slice($raw_value['rules'], 0, $this->get_rule_limit())
            : array();
        $public_post_types = array_keys($this->get_public_post_types());
        $seen_rule_ids = array();

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $trigger = isset($rule['trigger']) ? sanitize_key($rule['trigger']) : 'time';
            $action = isset($rule['action']) ? sanitize_key($rule['action']) : 'show_bubble';
            $scope = isset($rule['scope']) ? sanitize_key($rule['scope']) : 'global';
            $rule_id = isset($rule['id']) ? sanitize_key($rule['id']) : '';
            $name = isset($rule['name']) ? sanitize_text_field($rule['name']) : '';

            if (function_exists('mb_substr')) {
                $name = mb_substr($name, 0, 80);
            } else {
                $name = substr($name, 0, 80);
            }

            if (!in_array($trigger, array('time', 'scroll_depth'), true)) {
                $trigger = 'time';
            }

            if (!in_array($action, array('show_bubble', 'open_chat', 'mini_chat'), true)) {
                $action = 'show_bubble';
            }

            if (!in_array($scope, array('global', 'post_type', 'specific'), true)) {
                $scope = 'global';
            }

            if ($rule_id === '' || isset($seen_rule_ids[$rule_id])) {
                $rule_id = wp_generate_uuid4();
            }
            $seen_rule_ids[$rule_id] = true;

            $post_type = isset($rule['post_type']) ? sanitize_key($rule['post_type']) : '';
            if ($scope !== 'post_type' || !in_array($post_type, $public_post_types, true)) {
                $post_type = '';
            }

            $content_ids = isset($rule['content_ids']) && is_array($rule['content_ids'])
                ? array_values(array_unique(array_filter(array_map('absint', $rule['content_ids']))))
                : array();
            if ($scope !== 'specific') {
                $content_ids = array();
            }

            $message = isset($rule['message']) ? sanitize_textarea_field($rule['message']) : '';
            if (function_exists('mb_substr')) {
                $message = mb_substr($message, 0, 500);
            } else {
                $message = substr($message, 0, 500);
            }

            $quick_actions = array();
            $raw_quick_actions = isset($rule['quick_actions']) && is_array($rule['quick_actions'])
                ? $rule['quick_actions']
                : array();

            if ($action === 'mini_chat') {
                foreach ($raw_quick_actions as $quick_action) {
                    if (!is_array($quick_action)) {
                        continue;
                    }

                    $label = isset($quick_action['label'])
                        ? sanitize_text_field($quick_action['label'])
                        : '';
                    $quick_message = isset($quick_action['message'])
                        ? sanitize_textarea_field($quick_action['message'])
                        : '';
                    $color = isset($quick_action['color']) && is_string($quick_action['color'])
                        ? sanitize_hex_color($quick_action['color'])
                        : '';
                    $color = $color ? $color : '';

                    if (function_exists('mb_substr')) {
                        $label = mb_substr($label, 0, 80);
                        $quick_message = mb_substr($quick_message, 0, 500);
                    } else {
                        $label = substr($label, 0, 80);
                        $quick_message = substr($quick_message, 0, 500);
                    }

                    if ($label === '' || $quick_message === '') {
                        continue;
                    }

                    $quick_actions[] = array(
                        'label'   => $label,
                        'message' => $quick_message,
                        'color'   => $color,
                    );
                }
            }

            $sanitized['rules'][] = array(
                'id'          => $rule_id,
                'name'        => $name,
                'priority'    => max(1, min(100, absint(isset($rule['priority']) ? $rule['priority'] : 10))),
                'trigger'     => $trigger,
                'delay'       => max(1, min(3600, absint(isset($rule['delay']) ? $rule['delay'] : 10))),
                'scroll_depth' => max(1, min(100, absint(isset($rule['scroll_depth']) ? $rule['scroll_depth'] : 50))),
                'action'      => $action,
                'message'     => $message,
                'scope'       => $scope,
                'post_type'   => $post_type,
                'content_ids' => $content_ids,
                'quick_actions' => $quick_actions,
            );
        }

        return $sanitized;
    }

    /**
     * Render the Chat Settings sidebar link.
     */
    public function render_sidebar_item() {
        ?>
        <button type="button" class="airs-chat-sidebar-item" data-target="proactive-actions" role="tab" aria-selected="false">
            <span class="airs-chat-sidebar-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"></circle><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M2 12h4"></path><path d="M18 12h4"></path><path d="M12 9v6"></path><path d="M9 12h6"></path></svg>
            </span>
            <span class="airs-chat-sidebar-label"><?php esc_html_e('Proactive Actions', 'ai-chat-search'); ?></span>
        </button>
        <?php
    }

    /**
     * Render the Proactive Actions card within the existing Chat Settings form.
     */
    public function render_settings_section() {
        $settings = $this->get_settings();
        $rules = $settings['rules'];
        $rule_limit = $this->get_rule_limit();
        $rule_limit_reached = count($rules) >= $rule_limit;
        ?>
        <style>
            .purio-post-reference-results {
                margin-top: 4px;
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
            }
            .purio-post-reference-selected-text {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border-radius: 6px;
                background: #f5f5f5;
                font-size: 13px;
            }
            .purio-proactive-remove-content:hover {
                color: #666666 !important;
            }
            .purio-proactive-rule {
                margin: 0 0 16px;
                overflow: visible;
                border: 1px solid #e0e0e0;
                border-radius: 6px;
                background: #fff;
            }
            .purio-proactive-rule .airs-label svg {
                width: 16px;
                height: 16px;
            }
            .airs-card[data-chat-section="proactive-actions"] {
                overflow: visible;
            }
            .airs-card[data-chat-section="proactive-actions"] > .airs-card-header {
                border-radius: 8px 8px 0 0;
            }
            .airs-card[data-chat-section="proactive-actions"] > .airs-card-body {
                border-radius: 0 0 8px 8px;
            }
            .purio-proactive-rule-title {
                display: flex;
                box-sizing: border-box;
                height: auto;
                padding: 10px 20px;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                border-radius: 6px 6px 0 0;
                background: #f5f5f5;
                color: #666;
                font-size: 14px;
                font-weight: 600;
                line-height: 25px;
            }
            .purio-proactive-rule-name {
                display: flex;
                min-width: 0;
                flex: 1 1 auto;
                align-items: center;
                gap: 6px;
            }
            .purio-proactive-rule-title-text {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .purio-proactive-rule-name-input {
                display: none;
                max-width: 200px !important;
                height: 25px !important;
                min-height: 25px !important;
                padding: 8px !important;
                border: none !important;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12) !important;
                font-size: 14px !important;
                font-weight: 400;
            }
            .purio-proactive-rule-name.is-editing .purio-proactive-rule-title-text {
                display: none;
            }
            .purio-proactive-rule-name.is-editing .purio-proactive-rule-name-input {
                display: block;
            }
            .purio-proactive-edit-name {
                display: inline-flex;
                flex: 0 0 28px;
                width: 28px;
                height: 28px;
                min-height: 28px;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: none !important;
                border-radius: 5px;
                background: transparent !important;
                color: #666 !important;
                cursor: pointer;
            }
            .purio-proactive-edit-name:hover,
            .purio-proactive-edit-name:focus-visible {
                background: #e5e5e5 !important;
                color: #333 !important;
            }
            .purio-proactive-rule-name.is-editing .purio-proactive-edit-name {
                display: none;
            }
            .purio-proactive-priority {
                display: none;
                align-items: center;
                gap: 7px;
                font-weight: 400;
                white-space: nowrap;
            }
            .purio-proactive-priority-enabled .purio-proactive-priority {
                display: inline-flex;
            }
            .purio-proactive-priority input {
                width: 62px;
                height: 28px;
                min-height: 28px;
                padding: 2px 6px;
            }
            .purio-proactive-rule-title-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .purio-proactive-rule-title .purio-proactive-remove-rule {
                flex: 0 0 28px;
                width: 28px;
                height: 28px;
                min-height: 28px;
                border-radius: 5px;
            }
            .purio-proactive-toggle-rule {
                display: inline-flex;
                flex: 0 0 28px;
                width: 28px;
                height: 28px;
                min-height: 28px;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: none !important;
                border-radius: 5px;
                background: transparent !important;
                color: #666 !important;
            }
            .purio-proactive-toggle-rule:hover,
            .purio-proactive-toggle-rule:focus-visible {
                background: #e5e5e5 !important;
                color: #333 !important;
            }
            .purio-proactive-toggle-rule .toggle-icon {
                transition: transform 0.18s ease;
            }
            .purio-proactive-rule.is-collapsed .purio-proactive-toggle-rule .toggle-icon {
                transform: rotate(180deg);
            }
            .purio-proactive-rule.is-collapsed > .purio-proactive-rule-body {
                display: none;
            }
            .purio-proactive-setting-help {
                display: block;
                margin-top: 6px;
                color: #777;
                font-size: 13px;
                line-height: 1.4;
            }
            .purio-proactive-rule-body {
                padding: 18px;
            }
            .purio-proactive-remove-rule {
                display: inline-flex;
                flex: 0 0 37px;
                width: 37px;
                height: 37px;
                align-items: center;
                justify-content: center;
                padding: 0;
                border: none;
                background: #fde3e3;
                color: #e31e1e;
            }
            .purio-proactive-remove-rule:hover,
            .purio-proactive-remove-rule:focus-visible {
                border: none !important;
                background: #f8caca !important;
                color: #c91818 !important;
            }
            .purio-proactive-remove-rule .remove-icon {
                flex: 0 0 16px;
                width: 16px;
                height: 16px;
            }
            .purio-proactive-quick-actions {
                margin-top: 24px;
                padding: 20px;
                border-radius: 8px;
                background: #f5f5f5;
            }
            .purio-proactive-quick-actions-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .purio-proactive-quick-actions-header small {
                font-size: 13px;
            }
            .purio-proactive-add-quick-action {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #fff !important;
            }
            .purio-proactive-add-quick-action svg {
                flex: 0 0 16px;
            }
            .purio-proactive-quick-action-row {
                display: flex;
                padding: 0;
                align-items: flex-start;
                gap: 10px;
                margin-top: 8px;
                border-radius: 8px;
                background: #f7f7f7;
            }
            .purio-proactive-quick-actions-list > .purio-proactive-quick-action-row:first-child {
                margin-top: 15px;
            }
            .purio-proactive-quick-action-color-wrap {
                position: relative;
                flex: 0 0 37px;
            }
            .purio-proactive-quick-action-color-swatch {
                width: 37px;
                height: 37px;
                padding: 0;
                border: 1px solid rgba(0, 0, 0, 0.15);
                border-radius: 8px;
                outline: none;
                box-shadow: none;
                cursor: pointer;
            }
            .purio-proactive-quick-action-label,
            .purio-proactive-quick-action-message {
                min-width: 120px;
            }
            .purio-proactive-quick-action-message {
                width: auto;
                max-width: none;
                flex: 1 1 0;
            }
            .purio-proactive-remove-quick-action {
                display: inline-flex;
                width: 37px;
                height: 37px;
                padding: 0;
                flex: 0 0 37px;
                align-items: center;
                justify-content: center;
                border: none;
                background: #fde3e3;
                color: #e31e1e;
            }
            .purio-proactive-remove-quick-action:hover,
            .purio-proactive-remove-quick-action:focus-visible {
                border: none !important;
                background: #f8caca !important;
                color: #c91818 !important;
            }
            .purio-proactive-color-dropdown {
                position: absolute;
                z-index: 100002;
                padding: 10px;
                border-radius: 6px;
                background: #fff;
                box-shadow: 0 4px 18px rgba(0, 0, 0, 0.18);
            }
            .purio-proactive-color-picker-footer {
                display: flex;
                margin-top: 8px;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .purio-proactive-color-hex {
                width: 90px;
            }
            #purio-proactive-add-rule,
            #purio-proactive-upgrade-action {
                border: none !important;
                background: linear-gradient(135deg, #0073ee 0%, #005bb5 100%) !important;
                color: white !important;
            }
            #purio-proactive-upgrade-action {
                gap: 8px;
                align-items: center;
                text-decoration: none;
            }
            #purio-proactive-upgrade-action svg {
                flex: 0 0 18px;
            }
        </style>
        <div class="airs-card<?php echo $settings['display_mode'] === 'highest_priority' ? ' purio-proactive-priority-enabled' : ''; ?>" data-chat-section="proactive-actions">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"></circle><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M2 12h4"></path><path d="M18 12h4"></path><path d="M12 9v6"></path><path d="M9 12h6"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php esc_html_e('Proactive Actions', 'ai-chat-search'); ?></h3>
                    <p><?php esc_html_e('Start a chat or show a short message after a visitor stays on a page.', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                <div class="airs-form-group purio-live-handoff__master-setting">
                    <label class="airs-checkbox-label">
                        <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="0">
                        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]" value="1" <?php checked($settings['enabled'], 1); ?>>
                        <span class="airs-checkbox-custom"></span>
                        <span class="airs-checkbox-text">
                            <?php esc_html_e('Enable proactive actions', 'ai-chat-search'); ?>
                            <small><?php esc_html_e('Actions wait for their trigger on each page view. Choose below when they can appear again after the visitor sends a message.', 'ai-chat-search'); ?></small>
                        </span>
                    </label>
                </div>

                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px;">
                    <label style="flex:1;min-width:220px;">
                        <span class="airs-label"><?php esc_html_e('After the visitor sends a message', 'ai-chat-search'); ?></span>
                        <select class="airs-input" name="<?php echo esc_attr(self::OPTION_KEY); ?>[interaction_cooldown]">
                            <option value="none" <?php selected($settings['interaction_cooldown'], 'none'); ?>><?php esc_html_e('Show actions again on the next page', 'ai-chat-search'); ?></option>
                            <option value="session" <?php selected($settings['interaction_cooldown'], 'session'); ?>><?php esc_html_e('Hide actions for the rest of this visit', 'ai-chat-search'); ?></option>
                            <option value="30_minutes" <?php selected($settings['interaction_cooldown'], '30_minutes'); ?>><?php esc_html_e('Hide actions for 30 minutes', 'ai-chat-search'); ?></option>
                            <option value="1_hour" <?php selected($settings['interaction_cooldown'], '1_hour'); ?>><?php esc_html_e('Hide actions for 1 hour', 'ai-chat-search'); ?></option>
                            <option value="24_hours" <?php selected($settings['interaction_cooldown'], '24_hours'); ?>><?php esc_html_e('Hide actions for 24 hours', 'ai-chat-search'); ?></option>
                            <option value="7_days" <?php selected($settings['interaction_cooldown'], '7_days'); ?>><?php esc_html_e('Hide actions for 7 days', 'ai-chat-search'); ?></option>
                        </select>
                        <small class="purio-proactive-setting-help"><?php esc_html_e('Opening or closing the chat without sending a message does not hide actions. “This visit” lasts until the browser tab is closed; closing Mini Chat affects only that action.', 'ai-chat-search'); ?></small>
                    </label>
                    <label style="flex:1;min-width:220px;">
                        <span class="airs-label"><?php esc_html_e('When multiple actions match', 'ai-chat-search'); ?></span>
                        <select class="airs-input purio-proactive-display-mode" name="<?php echo esc_attr(self::OPTION_KEY); ?>[display_mode]">
                            <option value="all" <?php selected($settings['display_mode'], 'all'); ?>><?php esc_html_e('Show all matching actions', 'ai-chat-search'); ?></option>
                            <option value="highest_priority" <?php selected($settings['display_mode'], 'highest_priority'); ?>><?php esc_html_e('Show highest priority only', 'ai-chat-search'); ?></option>
                        </select>
                        <small class="purio-proactive-setting-help"><?php esc_html_e('If multiple configured actions can trigger on the same page, show them all or show only one. Priority appears when you choose to show one.', 'ai-chat-search'); ?></small>
                    </label>
                </div>

                <div id="purio-proactive-rules">
                    <?php foreach ($rules as $index => $rule) : ?>
                        <?php $this->render_rule($rule, $index); ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($rule_limit === 1) : ?>
                    <p class="airs-help-text">
                        <?php esc_html_e('You can add up to 1 action in the free plugin.', 'ai-chat-search'); ?>
                    </p>
                <?php endif; ?>
                <button type="button" class="airs-button airs-button-secondary" id="purio-proactive-add-rule" <?php disabled($rule_limit_reached); ?><?php echo $rule_limit_reached ? ' style="display:none;"' : ''; ?>>
                    <?php esc_html_e('Add Action', 'ai-chat-search'); ?>
                </button>
                <?php if ($rule_limit === 1) : ?>
                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('proactive_actions')); ?>" class="airs-button airs-button-secondary" id="purio-proactive-upgrade-action" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('Upgrade to Pro to add more actions.', 'ai-chat-search'); ?>"<?php echo !$rule_limit_reached ? ' style="display:none;"' : ''; ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>
                        <?php esc_html_e('Upgrade to Add More Actions', 'ai-chat-search'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render one rule editor.
     *
     * @param array $rule  Rule data.
     * @param int   $index Rule form index.
     */
    private function render_rule($rule, $index) {
        $rule = wp_parse_args($rule, $this->get_default_rule());
        $quick_actions = is_array($rule['quick_actions'])
            ? $rule['quick_actions']
            : array();
        $post_types = $this->get_public_post_types();
        $custom_name = is_string($rule['name']) ? sanitize_text_field($rule['name']) : '';
        $default_title = sprintf(__('Action %d', 'ai-chat-search'), $index + 1);
        $display_title = $custom_name !== '' ? $custom_name : $default_title;
        ?>
        <div class="purio-proactive-rule" data-rule-index="<?php echo esc_attr($index); ?>">
            <div class="purio-proactive-rule-title">
                <div class="purio-proactive-rule-name">
                    <span class="purio-proactive-rule-title-text"><?php echo esc_html($display_title); ?></span>
                    <input type="text" class="airs-input purio-proactive-rule-name-input" maxlength="80" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($custom_name); ?>" placeholder="<?php echo esc_attr($default_title); ?>" aria-label="<?php esc_attr_e('Action name', 'ai-chat-search'); ?>">
                    <button type="button" class="purio-proactive-edit-name" title="<?php esc_attr_e('Edit action name', 'ai-chat-search'); ?>" aria-label="<?php esc_attr_e('Edit action name', 'ai-chat-search'); ?>" aria-expanded="false">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                    </button>
                </div>
                <div class="purio-proactive-rule-title-actions">
                    <label class="purio-proactive-priority" title="<?php esc_attr_e('1 is the highest priority.', 'ai-chat-search'); ?>">
                        <span><?php esc_html_e('Priority', 'ai-chat-search'); ?></span>
                        <input type="number" class="airs-input" min="1" max="100" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][priority]" value="<?php echo esc_attr($rule['priority']); ?>">
                    </label>
                    <button type="button" class="airs-button airs-button-secondary purio-proactive-toggle-rule" title="<?php esc_attr_e('Collapse action', 'ai-chat-search'); ?>" aria-expanded="true">
                        <svg class="toggle-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"></path></svg>
                    </button>
                    <button type="button" class="airs-button airs-button-secondary purio-proactive-remove-rule" title="<?php esc_attr_e('Remove rule', 'ai-chat-search'); ?>">
                        <svg class="remove-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6 6 18"></path></svg>
                    </button>
                </div>
            </div>
            <div class="purio-proactive-rule-body">
                <input type="hidden" class="purio-proactive-rule-id" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($rule['id']); ?>">
                <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
                <label style="flex:1;min-width:160px;">
                    <span class="airs-label"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><circle cx="12" cy="12" r="7"></circle><path d="M12 2v4"></path><path d="M12 18v4"></path><path d="M2 12h4"></path><path d="M18 12h4"></path><path d="M12 9v6"></path><path d="M9 12h6"></path></svg><?php esc_html_e('Trigger', 'ai-chat-search'); ?></span>
                    <select class="airs-input purio-proactive-trigger" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][trigger]">
                        <option value="time" <?php selected($rule['trigger'], 'time'); ?>><?php esc_html_e('Time', 'ai-chat-search'); ?></option>
                        <option value="scroll_depth" <?php selected($rule['trigger'], 'scroll_depth'); ?>><?php esc_html_e('Scroll Depth', 'ai-chat-search'); ?></option>
                    </select>
                </label>
                <label class="purio-proactive-trigger-time" style="flex:1;min-width:150px;">
                    <span class="airs-label"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg><?php esc_html_e('After', 'ai-chat-search'); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <input type="number" class="airs-input" min="1" max="3600" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][delay]" value="<?php echo esc_attr($rule['delay']); ?>">
                        <span><?php esc_html_e('seconds', 'ai-chat-search'); ?></span>
                    </span>
                </label>
                <label class="purio-proactive-trigger-scroll" style="display:none;flex:1;min-width:150px;">
                    <span class="airs-label"><?php esc_html_e('Scroll depth', 'ai-chat-search'); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <input type="number" class="airs-input" min="1" max="100" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][scroll_depth]" value="<?php echo esc_attr($rule['scroll_depth']); ?>">
                        <span>%</span>
                    </span>
                </label>
                <label style="flex:1;min-width:180px;">
                    <span class="airs-label"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"></path></svg><?php esc_html_e('Action', 'ai-chat-search'); ?></span>
                    <select class="airs-input purio-proactive-action" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][action]">
                        <option value="mini_chat" <?php selected($rule['action'], 'mini_chat'); ?>><?php esc_html_e('Show mini chat', 'ai-chat-search'); ?></option>
                        <option value="show_bubble" <?php selected($rule['action'], 'show_bubble'); ?>><?php esc_html_e('Show message bubble', 'ai-chat-search'); ?></option>
                        <option value="open_chat" <?php selected($rule['action'], 'open_chat'); ?>><?php esc_html_e('Start chat with message', 'ai-chat-search'); ?></option>
                    </select>
                </label>
                <label style="flex:1;min-width:180px;">
                    <span class="airs-label"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a14 14 0 0 1 0 18"></path><path d="M12 3a14 14 0 0 0 0 18"></path></svg><?php esc_html_e('Where', 'ai-chat-search'); ?></span>
                    <select class="airs-input purio-proactive-scope" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][scope]">
                        <option value="global" <?php selected($rule['scope'], 'global'); ?>><?php esc_html_e('Entire site', 'ai-chat-search'); ?></option>
                        <option value="post_type" <?php selected($rule['scope'], 'post_type'); ?>><?php esc_html_e('Content type', 'ai-chat-search'); ?></option>
                        <option value="specific" <?php selected($rule['scope'], 'specific'); ?>><?php esc_html_e('Specific page', 'ai-chat-search'); ?></option>
                    </select>
                </label>
                </div>

            <div class="purio-proactive-message-wrap" style="display:block;margin-top:14px;">
                <label class="airs-label" for="purio-proactive-message-<?php echo esc_attr($index); ?>"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"></path></svg><?php esc_html_e('Message', 'ai-chat-search'); ?></label>
                <div class="purio-emoji-field" data-purio-emoji-label="<?php esc_attr_e('Choose emoji', 'ai-chat-search'); ?>">
                    <textarea id="purio-proactive-message-<?php echo esc_attr($index); ?>" class="airs-input" rows="2" maxlength="500" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][message]"><?php echo esc_textarea($rule['message']); ?></textarea>
                </div>
            </div>

            <div class="purio-proactive-quick-actions" style="display:none;">
                <div class="purio-proactive-quick-actions-header">
                    <div>
                        <span class="airs-label"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;" aria-hidden="true"><rect x="3" y="5" width="18" height="5" rx="2"></rect><rect x="3" y="14" width="18" height="5" rx="2"></rect></svg><?php esc_html_e('Buttons', 'ai-chat-search'); ?></span>
                        <small style="display:block;color:#666;"><?php esc_html_e('Optional buttons shown below the mini chat message.', 'ai-chat-search'); ?></small>
                    </div>
                    <button type="button" class="airs-button airs-button-secondary purio-proactive-add-quick-action">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
                        <?php esc_html_e('Add Button', 'ai-chat-search'); ?>
                    </button>
                </div>
                <div class="purio-proactive-quick-actions-list">
                    <?php foreach ($quick_actions as $quick_action_index => $quick_action) : ?>
                        <?php $this->render_quick_action($quick_action, $index, $quick_action_index); ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="purio-proactive-post-type-wrap" style="display:none;margin-top:14px;">
                <label>
                    <span class="airs-label"><?php esc_html_e('Content type', 'ai-chat-search'); ?></span>
                    <select class="airs-input" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][post_type]">
                        <option value=""><?php esc_html_e('Select content type', 'ai-chat-search'); ?></option>
                        <?php foreach ($post_types as $post_type => $label) : ?>
                            <option value="<?php echo esc_attr($post_type); ?>" <?php selected($rule['post_type'], $post_type); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="purio-proactive-specific-wrap" style="display:none;margin-top:14px;">
                <label>
                    <span class="airs-label"><?php esc_html_e('Specific posts, pages, or products', 'ai-chat-search'); ?></span>
                    <input type="search" class="airs-input purio-proactive-content-search" autocomplete="off" placeholder="<?php esc_attr_e('Search by title…', 'ai-chat-search'); ?>">
                </label>
                <div class="purio-proactive-search-results purio-post-reference-results" style="display:none;"></div>
                <div class="purio-proactive-selected-content" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                    <?php foreach ($rule['content_ids'] as $content_id) : ?>
                        <?php $this->render_selected_content($content_id, $index); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render one Mini Chat quick action.
     *
     * @param array $quick_action Quick action data.
     * @param int   $rule_index Rule form index.
     * @param int   $quick_action_index Quick action form index.
     */
    private function render_quick_action($quick_action, $rule_index, $quick_action_index) {
        $quick_action = wp_parse_args($quick_action, array('label' => '', 'message' => '', 'color' => ''));
        $field_base = self::OPTION_KEY . '[rules][' . $rule_index . '][quick_actions][' . $quick_action_index . ']';
        $stored_color = is_string($quick_action['color'])
            ? sanitize_hex_color($quick_action['color'])
            : '';
        $stored_color = $stored_color ? $stored_color : '';
        $swatch_color = $stored_color ? $stored_color : '#ebebeb';
        ?>
        <div class="purio-proactive-quick-action-row" data-quick-action-index="<?php echo esc_attr($quick_action_index); ?>">
            <div class="purio-proactive-quick-action-color-wrap">
                <button type="button" class="purio-proactive-quick-action-color-swatch" style="background-color:<?php echo esc_attr($swatch_color); ?>;" title="<?php esc_attr_e('Button color', 'ai-chat-search'); ?>"></button>
                <input type="hidden" class="purio-proactive-quick-action-color" name="<?php echo esc_attr($field_base); ?>[color]" value="<?php echo esc_attr($stored_color); ?>">
            </div>
            <input type="text" class="airs-input purio-proactive-quick-action-label" maxlength="80" name="<?php echo esc_attr($field_base); ?>[label]" value="<?php echo esc_attr($quick_action['label']); ?>" placeholder="<?php esc_attr_e('Button label', 'ai-chat-search'); ?>" aria-label="<?php esc_attr_e('Quick action label', 'ai-chat-search'); ?>">
            <input type="text" class="airs-input purio-proactive-quick-action-message" maxlength="500" name="<?php echo esc_attr($field_base); ?>[message]" value="<?php echo esc_attr($quick_action['message']); ?>" placeholder="<?php esc_attr_e('Message sent to AI', 'ai-chat-search'); ?>" aria-label="<?php esc_attr_e('Message sent to AI', 'ai-chat-search'); ?>">
            <button type="button" class="airs-button airs-button-secondary purio-proactive-remove-quick-action" title="<?php esc_attr_e('Remove quick action', 'ai-chat-search'); ?>">
                <svg class="remove-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12"></path><path d="M18 6 6 18"></path></svg>
            </button>
        </div>
        <?php
    }

    /**
     * Render one selected-content chip.
     *
     * @param int $post_id Post ID.
     * @param int $index   Rule form index.
     */
    private function render_selected_content($post_id, $index) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        ?>
        <span class="purio-proactive-content-chip purio-post-reference-selected-text" data-id="<?php echo esc_attr($post_id); ?>">
            <strong><?php echo esc_html(get_the_title($post) ?: sprintf(__('Item #%d', 'ai-chat-search'), $post_id)); ?></strong>
            <span style="color:#999;">(ID: <?php echo esc_html($post_id); ?>)</span>
            <span class="post-reference-remove purio-proactive-remove-content" style="cursor:pointer;color:#999;margin-left:4px;" title="<?php esc_attr_e('Remove content', 'ai-chat-search'); ?>">×</span>
            <input type="hidden" name="<?php echo esc_attr(self::OPTION_KEY); ?>[rules][<?php echo esc_attr($index); ?>][content_ids][<?php echo esc_attr($post_id); ?>]" value="<?php echo esc_attr($post_id); ?>">
        </span>
        <?php
    }

    /**
     * Enqueue the small rule editor script on Chat Settings only.
     */
    public function enqueue_admin_assets() {
        if (
            !isset($_GET['page'], $_GET['tab']) ||
            sanitize_key(wp_unslash($_GET['page'])) !== 'ai-chat-search' ||
            sanitize_key(wp_unslash($_GET['tab'])) !== 'ai-chat'
        ) {
            return;
        }

        wp_enqueue_script(
            'purio-proactive-actions-admin',
            LISTEO_AI_SEARCH_PLUGIN_URL . 'assets/js/admin/proactive-actions-admin.js',
            array('jquery', 'wp-color-picker'),
            LISTEO_AI_SEARCH_VERSION,
            true
        );

        wp_localize_script(
            'purio-proactive-actions-admin',
            'purioProactiveActionsAdmin',
            array(
                'ajaxUrl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('listeo_ai_proactive_search_content'),
                'optionKey' => self::OPTION_KEY,
                'postTypes' => $this->get_public_post_types(),
                'defaultColor' => get_option('listeo_ai_primary_color', '#0073ee'),
                'ruleLimit' => $this->get_rule_limit(),
                'strings'   => array(
                    'after'             => __('After', 'ai-chat-search'),
                    'trigger'           => __('Trigger', 'ai-chat-search'),
                    'triggerTime'       => __('Time', 'ai-chat-search'),
                    'triggerScrollDepth' => __('Scroll Depth', 'ai-chat-search'),
                    'scrollDepth'       => __('Scroll depth', 'ai-chat-search'),
                    'actionTitle'       => __('Action %d', 'ai-chat-search'),
                    'actionName'        => __('Action name', 'ai-chat-search'),
                    'editActionName'    => __('Edit action name', 'ai-chat-search'),
                    'priority'          => __('Priority', 'ai-chat-search'),
                    'priorityHelp'      => __('1 is the highest priority.', 'ai-chat-search'),
                    'collapseAction'    => __('Collapse action', 'ai-chat-search'),
                    'expandAction'      => __('Expand action', 'ai-chat-search'),
                    'seconds'           => __('seconds', 'ai-chat-search'),
                    'action'            => __('Action', 'ai-chat-search'),
                    'showBubble'        => __('Show message bubble', 'ai-chat-search'),
                    'openChat'          => __('Start chat with message', 'ai-chat-search'),
                    'miniChat'          => __('Show mini chat', 'ai-chat-search'),
                    'where'             => __('Where', 'ai-chat-search'),
                    'entireSite'        => __('Entire site', 'ai-chat-search'),
                    'contentType'       => __('Content type', 'ai-chat-search'),
                    'specificContent'   => __('Specific page', 'ai-chat-search'),
                    'remove'            => __('Remove', 'ai-chat-search'),
                    'removeRule'        => __('Remove rule', 'ai-chat-search'),
                    'message'           => __('Message', 'ai-chat-search'),
                    'defaultMessage'    => __('Need help? Ask me anything.', 'ai-chat-search'),
                    'chooseEmoji'       => __('Choose emoji', 'ai-chat-search'),
                    'quickActions'      => __('Buttons', 'ai-chat-search'),
                    'quickActionsHelp'  => __('Optional buttons shown below the mini chat message.', 'ai-chat-search'),
                    'addQuickAction'    => __('Add Button', 'ai-chat-search'),
                    'quickActionLabel'  => __('Button label', 'ai-chat-search'),
                    'quickActionMessage' => __('Message sent to AI', 'ai-chat-search'),
                    'removeQuickAction' => __('Remove quick action', 'ai-chat-search'),
                    'quickActionColor'  => __('Button color', 'ai-chat-search'),
                    'resetColor'        => __('Reset', 'ai-chat-search'),
                    'selectContentType' => __('Select content type', 'ai-chat-search'),
                    'searchPlaceholder' => __('Search by title…', 'ai-chat-search'),
                    'noResults'         => __('No content found.', 'ai-chat-search'),
                ),
            )
        );
    }

    /**
     * Search public content for the specific-content selector.
     */
    public function ajax_search_content() {
        if (!check_ajax_referer('listeo_ai_proactive_search_content', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')), 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')), 403);
        }

        $query = isset($_GET['query']) ? sanitize_text_field(wp_unslash($_GET['query'])) : '';
        if (strlen($query) < 2) {
            wp_send_json_success(array());
        }

        $posts = get_posts(array(
            'post_type'      => array_keys($this->get_public_post_types()),
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            's'              => $query,
            'orderby'        => 'relevance',
            'order'          => 'DESC',
        ));

        $results = array();
        foreach ($posts as $post) {
            $post_type_object = get_post_type_object($post->post_type);
            $results[] = array(
                'id'    => $post->ID,
                'title' => get_the_title($post) ?: sprintf(__('Item #%d', 'ai-chat-search'), $post->ID),
                'type'  => $post_type_object ? $post_type_object->labels->singular_name : $post->post_type,
            );
        }

        wp_send_json_success($results);
    }

    /**
     * Add all matching rules to the floating widget configuration.
     *
     * @param array $config Widget configuration.
     * @return array
     */
    public function add_frontend_config($config) {
        $settings = $this->get_settings();
        if (empty($settings['enabled'])) {
            return $config;
        }

        $rules = $this->get_matching_rules();
        if (empty($rules)) {
            return $config;
        }

        $config['proactiveActions'] = array_map(
            static function ($rule) {
                $quick_actions = array();
                $stored_quick_actions = isset($rule['quick_actions']) && is_array($rule['quick_actions'])
                    ? $rule['quick_actions']
                    : array();

                foreach ($stored_quick_actions as $quick_action) {
                    if (!is_array($quick_action)) {
                        continue;
                    }

                    $quick_action_color = isset($quick_action['color']) && is_string($quick_action['color'])
                        ? sanitize_hex_color($quick_action['color'])
                        : '';

                    $quick_actions[] = array(
                        'label'   => isset($quick_action['label']) ? $quick_action['label'] : '',
                        'message' => isset($quick_action['message']) ? $quick_action['message'] : '',
                        'color'   => $quick_action_color ? $quick_action_color : '',
                    );
                }

                return array(
                    'id'      => $rule['id'],
                    'priority' => isset($rule['priority'])
                        ? max(1, min(100, absint($rule['priority'])))
                        : 10,
                    'trigger' => isset($rule['trigger']) && $rule['trigger'] === 'scroll_depth'
                        ? 'scroll_depth'
                        : 'time',
                    'delay'   => $rule['delay'],
                    'scroll_depth' => isset($rule['scroll_depth'])
                        ? max(1, min(100, absint($rule['scroll_depth'])))
                        : 50,
                    'action'  => $rule['action'],
                    'message' => $rule['message'],
                    'quick_actions' => $quick_actions,
                );
            },
            $rules
        );
        $config['proactiveSettings'] = array(
            'interaction_cooldown' => $settings['interaction_cooldown'],
            'display_mode'         => $settings['display_mode'],
        );
        $config['proactiveStrings'] = array(
            'miniPlaceholder' => __('Type a message', 'ai-chat-search'),
            'sendMessage'     => __('Send message', 'ai-chat-search'),
            'closeMiniChat'   => __('Close mini chat', 'ai-chat-search'),
        );

        return $config;
    }

    /**
     * Resolve all rules that match the current page.
     *
     * @return array
     */
    private function get_matching_rules() {
        $rules = $this->get_settings()['rules'];
        $current_post_id = is_singular() ? get_queried_object_id() : 0;
        $current_post_type = $current_post_id ? get_post_type($current_post_id) : '';
        $matching_rules = array();

        foreach ($rules as $stored_order => $rule) {
            $is_match = false;
            if (
                $rule['scope'] === 'specific' &&
                $current_post_id &&
                in_array($current_post_id, $rule['content_ids'], true)
            ) {
                $is_match = true;
            } elseif (
                $rule['scope'] === 'post_type' &&
                $current_post_type &&
                $rule['post_type'] === $current_post_type
            ) {
                $is_match = true;
            } elseif ($rule['scope'] === 'global') {
                $is_match = true;
            }

            if ($is_match) {
                $rule['_stored_order'] = $stored_order;
                $matching_rules[] = $rule;
            }
        }

        usort(
            $matching_rules,
            static function ($rule_a, $rule_b) {
                $priority_a = isset($rule_a['priority'])
                    ? max(1, min(100, absint($rule_a['priority'])))
                    : 10;
                $priority_b = isset($rule_b['priority'])
                    ? max(1, min(100, absint($rule_b['priority'])))
                    : 10;

                if ($priority_a === $priority_b) {
                    return $rule_a['_stored_order'] <=> $rule_b['_stored_order'];
                }

                return $priority_a <=> $priority_b;
            }
        );

        return $matching_rules;
    }

    /**
     * Get sanitized settings with defaults.
     *
     * @return array
     */
    private function get_settings() {
        $settings = get_option(self::OPTION_KEY, array());
        $settings = is_array($settings) ? $settings : array();

        return array(
            'enabled'              => empty($settings['enabled']) ? 0 : 1,
            'interaction_cooldown' => isset($settings['interaction_cooldown']) && in_array(
                $settings['interaction_cooldown'],
                array('none', 'session', '30_minutes', '1_hour', '24_hours', '7_days'),
                true
            )
                ? $settings['interaction_cooldown']
                : 'session',
            'display_mode'         => isset($settings['display_mode']) && in_array(
                $settings['display_mode'],
                array('highest_priority', 'all'),
                true
            )
                ? $settings['display_mode']
                : 'all',
            'rules'                => isset($settings['rules']) && is_array($settings['rules'])
                ? array_slice($settings['rules'], 0, $this->get_rule_limit())
                : array(),
        );
    }

    /**
     * Remove the WordPress request slashes stored by earlier versions.
     */
    private function maybe_migrate_slashed_settings() {
        $migration_version = 1;
        $installed_version = (int) get_option(
            'listeo_ai_proactive_actions_slashes_migration_version',
            0
        );

        if ($installed_version >= $migration_version) {
            return;
        }

        $settings = get_option(self::OPTION_KEY, array());
        if (is_array($settings) && !empty($settings)) {
            $unslashed_settings = wp_unslash($settings);
            if ($unslashed_settings !== $settings && !update_option(self::OPTION_KEY, $unslashed_settings)) {
                return;
            }
        }

        update_option(
            'listeo_ai_proactive_actions_slashes_migration_version',
            $migration_version,
            false
        );
    }

    /**
     * Get the maximum number of rules available to this installation.
     *
     * @return int
     */
    private function get_rule_limit() {
        return max(
            1,
            absint(apply_filters('listeo_ai_proactive_actions_rule_limit', 1))
        );
    }

    /**
     * Get public post type labels.
     *
     * @return array
     */
    private function get_public_post_types() {
        $objects = get_post_types(array('public' => true), 'objects');
        $post_types = array();

        foreach ($objects as $post_type => $object) {
            if ($post_type === 'attachment') {
                continue;
            }
            $post_types[$post_type] = $object->labels->singular_name;
        }

        return $post_types;
    }

    /**
     * Get a new rule's defaults.
     *
     * @return array
     */
    private function get_default_rule() {
        return array(
            'id'          => wp_generate_uuid4(),
            'name'        => '',
            'priority'    => 10,
            'trigger'     => 'time',
            'delay'       => 10,
            'scroll_depth' => 50,
            'action'      => 'show_bubble',
            'message'     => __('Need help? Ask me anything.', 'ai-chat-search'),
            'scope'       => 'global',
            'post_type'   => '',
            'content_ids' => array(),
            'quick_actions' => array(),
        );
    }
}
