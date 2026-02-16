<?php
/**
 * Admin Chat History Handler
 *
 * Handles chat history rendering and AJAX operations for the admin dashboard.
 *
 * @package AI_Chat_Search
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin_Chat_History
 *
 * Manages chat history display and operations in the admin area.
 */
class Admin_Chat_History {

    /**
     * Items per page for pagination
     */
    const PER_PAGE = 4;

    /**
     * Constructor - Register AJAX handlers
     */
    public function __construct() {
        add_action('wp_ajax_listeo_ai_load_chat_history', array($this, 'ajax_load'));
        add_action('wp_ajax_listeo_ai_clear_chat_history', array($this, 'ajax_clear'));
        add_action('wp_ajax_listeo_ai_delete_conversation', array($this, 'ajax_delete_conversation'));
        add_action('wp_ajax_listeo_ai_export_chat_history_csv', array($this, 'ajax_export_csv'));
    }

    /**
     * Render the complete chat history section
     *
     * @param bool $history_enabled Whether chat history is enabled
     */
    public function render_section($history_enabled) {
        if ($history_enabled) {
            $this->render_enabled_section();
        } else {
            $this->render_disabled_section();
        }
    }

    /**
     * Render section when chat history is enabled
     */
    private function render_enabled_section() {
        // Pagination
        $page = isset($_GET['history_page']) ? max(1, intval($_GET['history_page'])) : 1;
        $offset = ($page - 1) * self::PER_PAGE;

        // Get stats and conversations
        $history_stats_30d = null;
        $history_stats_today = null;
        $recent_conversations = array();
        $total_pages = 1;

        if (class_exists('Listeo_AI_Search_Chat_History')) {
            $history_stats_30d = Listeo_AI_Search_Chat_History::get_stats(30);
            $history_stats_today = Listeo_AI_Search_Chat_History::get_stats_today();
            $recent_conversations = Listeo_AI_Search_Chat_History::get_recent_conversations(self::PER_PAGE, $offset);

            // Get total count for pagination
            global $wpdb;
            $table_name = Listeo_AI_Search_Chat_History::get_table_name();
            $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$table_name}");
            $total_pages = ceil($total_conversations / self::PER_PAGE);
        }
        ?>
        <div class="airs-card">
            <div class="airs-card-header airs-card-header-with-icon">
                <div class="airs-card-icon airs-card-icon-indigo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path><path d="M12 7v5l4 2"></path></svg>
                </div>
                <div class="airs-card-header-text">
                    <h3><?php _e('Chat History (Last 30 Days)', 'ai-chat-search'); ?></h3>
                    <p><?php _e('Detailed conversation tracking', 'ai-chat-search'); ?></p>
                </div>
            </div>
            <div class="airs-card-body">
                <?php if (!AI_Chat_Search_Pro_Manager::can_access_conversation_logs()): ?>
                    <?php $this->render_locked_preview(); ?>
                <?php else: ?>
                    <?php $this->render_stats_boxes($history_stats_30d, $history_stats_today); ?>
                    <?php $this->render_conversations_list($recent_conversations, $page, $total_pages); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render section when chat history is disabled
     */
    private function render_disabled_section() {
        ?>
        <div class="airs-card">
            <div class="airs-card-header">
                <h3><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: text-bottom; margin-right: 6px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><?php _e('Chat History', 'ai-chat-search'); ?></h3>
                <p><?php _e('Chat history tracking is currently disabled.', 'ai-chat-search'); ?></p>
            </div>
            <div class="airs-card-body">
                <p><?php _e('Enable "Chat History Tracking" in the AI Chat tab to start collecting conversation data for analytics.', 'ai-chat-search'); ?></p>
                <p style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                    <strong><?php _e('Benefits:', 'ai-chat-search'); ?></strong><br>
                    <?php _e('Track popular questions and user needs', 'ai-chat-search'); ?><br>
                    <?php _e('Monitor conversation quality and patterns', 'ai-chat-search'); ?><br>
                    <?php _e('Identify most requested information', 'ai-chat-search'); ?><br>
                    <?php _e('Improve chatbot responses over time', 'ai-chat-search'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render locked preview for free users
     */
    public function render_locked_preview() {
        $dummy_conversations = array(
            array('id' => 'a1b2c3d4e5f6g7h8', 'messages' => 8, 'user' => 'Guest User', 'ip' => '192.168.1.45', 'country' => 'us', 'country_name' => 'United States', 'city' => 'New York', 'region' => 'New York', 'continent' => 'Americas', 'started' => 3, 'last_msg' => 1),
            array('id' => 'x9y8z7w6v5u4t3s2', 'messages' => 5, 'user' => 'john.doe@example.com', 'ip' => '85.214.132.117', 'country' => 'de', 'country_name' => 'Germany', 'city' => 'Berlin', 'region' => 'Berlin', 'continent' => 'Europe', 'started' => 6, 'last_msg' => 4),
            array('id' => 'm3n4o5p6q7r8s9t0', 'messages' => 12, 'user' => 'Guest User', 'ip' => '46.125.70.146', 'country' => 'pl', 'country_name' => 'Poland', 'city' => 'Warsaw', 'region' => 'Masovia', 'continent' => 'Europe', 'started' => 9, 'last_msg' => 7),
            array('id' => 'k5l6m7n8o9p0q1r2', 'messages' => 3, 'user' => 'jane.smith@email.com', 'ip' => '78.90.123.45', 'country' => 'gb', 'country_name' => 'United Kingdom', 'city' => 'London', 'region' => 'England', 'continent' => 'Europe', 'started' => 12, 'last_msg' => 10),
        );
        ?>
        <div class="ai-chat-pro-feature-locked">
            <div class="preview-container preview-blurred">
                <div class="airs-stats-boxes">
                    <div class="airs-stat-box airs-stat-box-green">
                        <div class="airs-stat-number airs-stat-number-green">42</div>
                        <div class="airs-stat-label airs-stat-label-green"><?php _e('Conversations', 'ai-chat-search'); ?></div>
                    </div>
                    <div class="airs-stat-box airs-stat-box-blue">
                        <div class="airs-stat-number airs-stat-number-blue">287</div>
                        <div class="airs-stat-label airs-stat-label-blue"><?php _e('Messages', 'ai-chat-search'); ?></div>
                    </div>
                    <div class="airs-stat-box airs-stat-box-orange">
                        <div class="airs-stat-number airs-stat-number-orange">6.8</div>
                        <div class="airs-stat-label airs-stat-label-orange"><?php _e('Avg per Conversation', 'ai-chat-search'); ?></div>
                    </div>
                </div>

                <div style="margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0;"><?php _e('Recent Conversations', 'ai-chat-search'); ?></h3>

                    <?php foreach ($dummy_conversations as $conv): ?>
                    <div class="airs-conversation-card">
                        <div class="airs-conversation-header">
                            <div class="airs-conversation-id">
                                <strong><?php _e('Conversation ID:', 'ai-chat-search'); ?></strong>
                                <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($conv['id']); ?></code>
                            </div>
                            <div class="airs-conversation-meta">
                                <div style="font-size: 12px; color: #666;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo esc_html($conv['user']); ?>
                                    <?php
                                    $dummy_tip = array();
                                    if (!empty($conv['country_name'])) $dummy_tip[] = esc_attr($conv['country_name']);
                                    if (!empty($conv['city'])) $dummy_tip[] = esc_attr($conv['city']);
                                    if (!empty($conv['region'])) $dummy_tip[] = esc_attr($conv['region']);
                                    if (!empty($conv['continent'])) $dummy_tip[] = esc_attr($conv['continent']);
                                    ?>
                                    <span class="airs-ip-geo" data-geo-tooltip="<?php echo implode('|', $dummy_tip); ?>">
                                        <img src="https://flagcdn.com/16x12/<?php echo esc_attr($conv['country']); ?>.png" alt="<?php echo esc_attr(strtoupper($conv['country'])); ?>" style="vertical-align: middle;" />
                                        <span style="color: #999;"><?php echo esc_html($conv['ip']); ?></span>
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: #999;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg><?php printf(__('Started: %d hours ago', 'ai-chat-search'), $conv['started']); ?>
                                </div>
                            </div>
                        </div>
                        <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><?php echo $conv['messages']; ?> <?php _e('messages', 'ai-chat-search'); ?>
                            &bull; <?php printf(__('last %d hours ago', 'ai-chat-search'), $conv['last_msg']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                        <span class="airs-button airs-button-secondary" style="font-size: 13px; padding: 8px 16px; text-decoration: none; white-space: nowrap; opacity: 0.7; pointer-events: none;">
                            <span class="dashicons dashicons-download" style="margin-top: 3px; margin-right: 3px;"></span>
                            <?php _e('Export Chat History CSV', 'ai-chat-search'); ?>
                        </span>
                        <span class="airs-button airs-button-secondary airs-button-danger" style="font-size: 13px; padding: 8px 16px; text-decoration: none; white-space: nowrap; opacity: 0.7; pointer-events: none;">
                            <?php _e('Clear History', 'ai-chat-search'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="lock-overlay" style="background: rgba(255, 255, 255, 0.55); backdrop-filter: blur(3px);">
                <div class="lock-content">
                    <h3><?php _e('Chat History & Analytics', 'ai-chat-search'); ?></h3>
                    <ul class="benefits-list">
                        <li><?php _e('Conversation statistics and metrics', 'ai-chat-search'); ?></li>
                        <li><?php _e('Complete message history', 'ai-chat-search'); ?></li>
                    </ul>
                    <a href="<?php echo esc_url(AI_Chat_Search_Pro_Manager::get_upgrade_url('chat_history')); ?>" class="button button-primary button-hero" target="_blank">
                        <?php _e('Upgrade to Pro', 'ai-chat-search'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render stats boxes
     *
     * @param array|null $stats_30d Stats for last 30 days
     * @param array|null $stats_today Stats for today
     */
    private function render_stats_boxes($stats_30d, $stats_today) {
        if (!is_array($stats_30d) || empty($stats_30d)) {
            echo '<p>' . __('No chat history data available yet. Start using the AI chat to see statistics here.', 'ai-chat-search') . '</p>';
            return;
        }
        ?>
        <div class="airs-stats-boxes">
            <div class="airs-stat-box airs-stat-box-green">
                <div class="airs-stat-number airs-stat-number-green">
                    <?php echo number_format(isset($stats_30d['total_conversations']) ? intval($stats_30d['total_conversations']) : 0); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-green"><?php _e('Conversations', 'ai-chat-search'); ?></div>
                <div class="airs-stat-today airs-stat-today-green">
                    <?php printf(__('Today: %s', 'ai-chat-search'), number_format(isset($stats_today['total_conversations']) ? intval($stats_today['total_conversations']) : 0)); ?>
                </div>
            </div>

            <div class="airs-stat-box airs-stat-box-blue">
                <div class="airs-stat-number airs-stat-number-blue">
                    <?php echo number_format(isset($stats_30d['total_messages']) ? intval($stats_30d['total_messages']) : 0); ?>
                </div>
                <div class="airs-stat-label airs-stat-label-blue"><?php _e('Messages', 'ai-chat-search'); ?></div>
                <div class="airs-stat-today airs-stat-today-blue">
                    <?php printf(__('Today: %s', 'ai-chat-search'), number_format(isset($stats_today['total_messages']) ? intval($stats_today['total_messages']) : 0)); ?>
                </div>
            </div>

            <div class="airs-stat-box airs-stat-box-orange">
                <div class="airs-stat-number airs-stat-number-orange">
                    <?php echo isset($stats_30d['avg_per_conversation']) ? floatval($stats_30d['avg_per_conversation']) : 0; ?>
                </div>
                <div class="airs-stat-label airs-stat-label-orange"><?php _e('Avg per Conversation', 'ai-chat-search'); ?></div>
                <div class="airs-stat-today airs-stat-today-orange">
                    <?php printf(__('Today: %s', 'ai-chat-search'), isset($stats_today['avg_per_conversation']) ? floatval($stats_today['avg_per_conversation']) : 0); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render conversations list with pagination
     *
     * @param array $conversations Recent conversations
     * @param int $page Current page
     * @param int $total_pages Total pages
     */
    private function render_conversations_list($conversations, $page, $total_pages) {
        if (empty($conversations)) {
            echo '<p style="padding: 20px; text-align: center; color: #666;">' . __('No conversations yet. Start using the AI chat to see history here.', 'ai-chat-search') . '</p>';
            return;
        }
        ?>
        <div style="margin: 20px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div>
                        <h3 style="margin: 0;"><?php _e('Recent Conversations', 'ai-chat-search'); ?></h3>
                        <p style="color: #666; margin: 5px 0 0 0;"><?php _e('Click on a conversation to view the full chat history', 'ai-chat-search'); ?></p>
                    </div>
                </div>
                <div class="conversation-search-actions">
                    <input type="text" id="conversation-search-input" placeholder="<?php esc_attr_e('Search by ID or IP address', 'ai-chat-search'); ?>" style="width: 200px; padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <button type="button" id="conversation-search-btn" class="button button-small conversation-search-btn"><?php _e('Search', 'ai-chat-search'); ?></button>
                    <button type="button" id="conversation-search-clear" class="button button-small conversation-search-clear" style="display: none;"><?php _e('Clear', 'ai-chat-search'); ?></button>
                </div>
            </div>

            <div id="listeo-history-conversations">
            <?php foreach ($conversations as $conv): ?>
                <?php
                $messages = Listeo_AI_Search_Chat_History::get_conversation($conv['conversation_id']);
                $user_info = $conv['user_id'] ? get_userdata($conv['user_id']) : null;
                $this->render_conversation_card($conv, $messages, $user_info);
                ?>
            <?php endforeach; ?>
            </div>

            <div id="listeo-history-pagination">
                <?php $this->render_pagination($page, $total_pages); ?>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=listeo_ai_export_chat_history_csv&nonce=' . wp_create_nonce('listeo_ai_search_nonce'))); ?>" class="airs-button airs-button-secondary" style="font-size: 13px; padding: 8px 16px; text-decoration: none; white-space: nowrap;">
                    <span class="dashicons dashicons-download" style="margin-top: 3px; margin-right: 3px;"></span>
                    <?php _e('Export Chat History CSV', 'ai-chat-search'); ?>
                </a>
                <a href="#" id="clear-chat-history" class="airs-button airs-button-secondary airs-button-danger" style="font-size: 13px; padding: 8px 16px; text-decoration: none; white-space: nowrap;">
                    <?php _e('Clear History', 'ai-chat-search'); ?>
                </a>
            </div>

            <?php $this->render_javascript(); ?>
        </div>
        <?php
    }

    /**
     * Render pagination controls
     *
     * @param int $page Current page
     * @param int $total_pages Total pages
     */
    public function render_pagination($page, $total_pages) {
        if ($total_pages <= 1) {
            return;
        }

        $range = 2;
        $start = max(1, $page - $range);
        $end = min($total_pages, $page + $range);
        ?>
        <div class="airs-pagination-nav">
            <?php if ($page > 1): ?>
                <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $page - 1; ?>">
                    <?php _e('Previous', 'ai-chat-search'); ?>
                </button>
            <?php endif; ?>

            <div class="airs-page-numbers">
                <?php if ($start > 1): ?>
                    <button class="airs-pagination-btn listeo-history-page" data-page="1">1</button>
                    <?php if ($start > 2): ?>
                        <span class="airs-pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="airs-pagination-btn is-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1): ?>
                        <span class="airs-pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $total_pages; ?>"><?php echo $total_pages; ?></button>
                <?php endif; ?>
            </div>

            <?php if ($page < $total_pages): ?>
                <button class="airs-pagination-btn listeo-history-page" data-page="<?php echo $page + 1; ?>">
                    <?php _e('Next', 'ai-chat-search'); ?>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a single conversation card
     *
     * @param array $conv Conversation data
     * @param array $messages Messages in the conversation
     * @param WP_User|null $user_info User info or null for guest
     */
    public function render_conversation_card($conv, $messages, $user_info) {
        ?>
        <div class="airs-conversation-card" data-conversation-id="<?php echo esc_attr($conv['conversation_id']); ?>">
            <div class="airs-conversation-header">
                <div class="airs-conversation-id">
                    <strong><?php _e('Conversation ID:', 'ai-chat-search'); ?></strong>
                    <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($conv['conversation_id']); ?></code>
                    <?php do_action('ai_chat_search_conversation_id_badge', $conv['conversation_id']); ?>
                    <button type="button" class="delete-conversation-btn" data-id="<?php echo esc_attr($conv['conversation_id']); ?>" title="<?php esc_attr_e('Delete this conversation', 'ai-chat-search'); ?>" style="background: none; border: none; cursor: pointer; padding: 2px 6px; border-radius: 3px; color: #b32d2e; opacity: 0.6; transition: opacity 0.2s; margin-left: -5px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                    </button>
                </div>
                <div class="airs-conversation-meta">
                    <div style="font-size: 12px; color: #666;">
                        <?php if ($user_info): ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php echo esc_html($user_info->display_name); ?> (<?php echo esc_html($user_info->user_email); ?>)
                        <?php else: ?>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php _e('Guest User', 'ai-chat-search'); ?>
                        <?php endif; ?>
                        <?php if (!empty($conv['ip_address'])): ?>
                            <?php
                            $geo = Listeo_AI_Search_Chat_History::get_country_from_ip($conv['ip_address']);
                            $tip_parts = array();
                            if ($geo) {
                                if (!empty($geo['country_name'])) $tip_parts[] = esc_attr($geo['country_name']);
                                if (!empty($geo['city'])) $tip_parts[] = esc_attr($geo['city']);
                                if (!empty($geo['region'])) $tip_parts[] = esc_attr($geo['region']);
                                if (!empty($geo['continent'])) $tip_parts[] = esc_attr($geo['continent']);
                            }
                            $tip_data = !empty($tip_parts) ? implode('|', $tip_parts) : '';
                            ?>
                            <span class="airs-ip-geo" <?php if ($tip_data): ?>data-geo-tooltip="<?php echo $tip_data; ?>"<?php endif; ?>>
                                <?php if ($geo): ?>
                                    <img src="https://flagcdn.com/16x12/<?php echo esc_attr($geo['country_code']); ?>.png" alt="<?php echo esc_attr(strtoupper($geo['country_code'])); ?>" style="vertical-align: middle;" />
                                <?php endif; ?>
                                <span style="color: #999;"><?php echo esc_html($conv['ip_address']); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 12px; color: #999;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg><?php printf(__('Started: %s ago', 'ai-chat-search'), human_time_diff(strtotime($conv['first_message_at']), current_time('timestamp'))); ?>
                    </div>
                </div>
            </div>

            <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 3px;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg><?php echo $conv['message_count']; ?> <?php _e('messages', 'ai-chat-search'); ?>
                <?php if ($conv['first_message_at'] !== $conv['last_message_at']): ?>
                    &bull; <?php printf(__('last %s ago', 'ai-chat-search'), human_time_diff(strtotime($conv['last_message_at']), current_time('timestamp'))); ?>
                <?php endif; ?>
            </div>

            <details class="chat-history-details" style="margin-top: 10px;">
                <summary style="cursor: pointer; padding: 8px; background: #f9f9f9; border-radius: 3px; font-weight: 500;">
                    <?php _e('View Messages', 'ai-chat-search'); ?> (<?php echo count($messages); ?>)
                </summary>
                <div class="chat-history-messages" style="margin-top: 10px; padding: 10px; background: #fafafa; border-radius: 3px; max-height: 400px; overflow-y: auto;">
                    <?php foreach ($messages as $msg): ?>
                        <div style="margin-bottom: 15px; padding: 10px; background: #e8f4ff; border-radius: 4px;">
                            <div style="font-weight: bold; color: #1976d2; margin-bottom: 5px; font-size: 12px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg><?php _e('User', 'ai-chat-search'); ?>
                                <span style="color: #999; font-weight: normal; margin-left: 10px;">
                                    <?php echo date_i18n('M j, ' . get_option('time_format'), strtotime($msg['created_at'])); ?>
                                </span>
                                <?php if (!empty($msg['page_url'])): ?>
                                    <span style="color: #999; margin: 0 5px;">•</span>
                                    <a href="<?php echo esc_url($msg['page_url']); ?>"
                                       target="_blank"
                                       title="<?php echo esc_attr($msg['page_url']); ?>"
                                       class="airs-chat-page-link">
                                        <span class="airs-chat-page-link-text"><?php echo esc_html($this->get_page_title_from_url($msg['page_url'])); ?></span>
                                        <svg class="airs-chat-page-link-icon" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div style="color: #333; word-break: break-word;">
                                <?php echo esc_html(trim($msg['user_message'])); ?>
                            </div>
                        </div>

                        <div style="margin-bottom: 15px; padding: 10px; background: #ffffff; border-radius: 4px;">
                            <div style="font-weight: bold; color: #666; margin-bottom: 5px; font-size: 12px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg><?php _e('AI Assistant', 'ai-chat-search'); ?>
                                <span style="color: #999; font-weight: normal; margin-left: 10px;">
                                    <?php echo esc_html($msg['model_used']); ?>
                                </span>
                            </div>
                            <div style="color: #333; word-break: break-word;">
                                <?php echo nl2br(wp_kses($msg['assistant_message'], array('a' => array('href' => array(), 'title' => array(), 'target' => array(), 'rel' => array())))); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </details>
        </div>
        <?php
    }

    /**
     * Render JavaScript for chat history interactions
     */
    private function render_javascript() {
        $nonce = wp_create_nonce('listeo_ai_search_nonce');
        ?>
        <script>
        jQuery(document).ready(function($) {

            // Build geo tooltips from data attributes
            function initGeoTooltips() {
                $('.airs-ip-geo[data-geo-tooltip]').not(':has(.airs-geo-tooltip)').each(function() {
                    var $el = $(this);
                    var parts = $el.attr('data-geo-tooltip').split('|');
                    var labels = ['Country', 'City', 'Region', 'Continent'];
                    var rows = '';
                    for (var i = 0; i < parts.length; i++) {
                        if (parts[i]) {
                            rows += '<div class="airs-geo-row"><span class="airs-geo-label">' + labels[i] + ':</span> <span>' + $('<span>').text(parts[i]).html() + '</span></div>';
                        }
                    }
                    if (rows) {
                        $el.append('<div class="airs-geo-tooltip">' + rows + '</div>');
                    }
                });
            }
            initGeoTooltips();
            // Scroll to last message when chat history details is opened
            $(document).on('click', '.chat-history-details > summary', function() {
                var $details = $(this).parent();
                if (!$details.attr('open')) {
                    var $messagesContainer = $details.find('.chat-history-messages');
                    if ($messagesContainer.length) {
                        setTimeout(function() {
                            $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
                        }, 100);
                    }
                }
            });

            // Pagination click handler
            $(document).on('click', '.listeo-history-page', function(e) {
                e.preventDefault();
                var page = $(this).data('page');
                var $container = $('#listeo-history-conversations');
                var $pagination = $('#listeo-history-pagination');

                $container.html('<p style="text-align: center; padding: 40px;"><span class="airs-spinner"></span> Loading...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_load_chat_history',
                        nonce: '<?php echo $nonce; ?>',
                        page: page
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.html(response.data.conversations);
                            $pagination.html(response.data.pagination);
                            initGeoTooltips();
                            $('html, body').animate({
                                scrollTop: $container.offset().top - 100
                            }, 300);
                        } else {
                            $container.html('<p style="color: #d63638; text-align: center; padding: 20px;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        $container.html('<p style="color: #d63638; text-align: center; padding: 20px;"><?php _e('Failed to load conversations. Please try again.', 'ai-chat-search'); ?></p>');
                    }
                });
            });

            // Search conversations
            function searchConversations(searchTerm) {
                var $container = $('#listeo-history-conversations');
                var $pagination = $('#listeo-history-pagination');
                var $clearBtn = $('#conversation-search-clear');

                $container.html('<p style="text-align: center; padding: 40px;"><span class="airs-spinner"></span> <?php _e('Searching...', 'ai-chat-search'); ?></p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_load_chat_history',
                        nonce: '<?php echo $nonce; ?>',
                        page: 1,
                        search: searchTerm
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.html(response.data.conversations);
                            $pagination.html(response.data.pagination);
                            initGeoTooltips();
                            if (searchTerm) {
                                $clearBtn.show();
                            }
                        } else {
                            $container.html('<p style="color: #d63638; text-align: center; padding: 20px;">' + response.data.message + '</p>');
                        }
                    },
                    error: function() {
                        $container.html('<p style="color: #d63638; text-align: center; padding: 20px;"><?php _e('Failed to search. Please try again.', 'ai-chat-search'); ?></p>');
                    }
                });
            }

            // Search button click
            $(document).on('click', '#conversation-search-btn', function(e) {
                e.preventDefault();
                var searchTerm = $('#conversation-search-input').val().trim();
                if (searchTerm) {
                    searchConversations(searchTerm);
                }
            });

            // Enter key in search input
            $(document).on('keypress', '#conversation-search-input', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    var searchTerm = $(this).val().trim();
                    if (searchTerm) {
                        searchConversations(searchTerm);
                    }
                }
            });

            // Clear search
            $(document).on('click', '#conversation-search-clear', function(e) {
                e.preventDefault();
                $('#conversation-search-input').val('');
                $(this).hide();
                searchConversations('');
            });

            // Clear History button handler
            $(document).on('click', '#clear-chat-history', function(e) {
                e.preventDefault();

                if (!confirm('<?php _e('Are you sure you want to delete all chat history? This action cannot be undone.', 'ai-chat-search'); ?>')) {
                    return;
                }

                var $button = $(this);
                var originalHtml = $button.html();

                $button.prop('disabled', true).html('<span class="airs-spinner" style="margin-right: 6px;"></span> <?php _e('Clearing...', 'ai-chat-search'); ?>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_clear_chat_history',
                        nonce: '<?php echo $nonce; ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data.message);
                            $button.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to clear chat history. Please try again.', 'ai-chat-search'); ?>');
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            // Delete single conversation button handler
            $(document).on('click', '.delete-conversation-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $button = $(this);
                var conversationId = $button.data('id');
                var $card = $button.closest('[data-conversation-id]');

                if (!confirm('<?php _e('Delete this conversation?', 'ai-chat-search'); ?>')) {
                    return;
                }

                $button.prop('disabled', true).css('opacity', '0.3');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'listeo_ai_delete_conversation',
                        conversation_id: conversationId,
                        nonce: '<?php echo $nonce; ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $card.slideUp(200, function() {
                                $(this).remove();
                                if ($('#listeo-history-conversations').children().length === 0) {
                                    $('#listeo-history-conversations').html('<p style="text-align: center; padding: 40px; color: #666;"><?php _e('No conversations found.', 'ai-chat-search'); ?></p>');
                                }
                            });
                        } else {
                            alert(response.data.message || '<?php _e('Failed to delete conversation.', 'ai-chat-search'); ?>');
                            $button.prop('disabled', false).css('opacity', '0.6');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Failed to delete conversation. Please try again.', 'ai-chat-search'); ?>');
                        $button.prop('disabled', false).css('opacity', '0.6');
                    }
                });
            });

            // Hover effect for delete button
            $(document).on('mouseenter', '.delete-conversation-btn', function() {
                $(this).css('opacity', '1');
            }).on('mouseleave', '.delete-conversation-btn', function() {
                $(this).css('opacity', '0.6');
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for loading chat history with pagination
     */
    public function ajax_load() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        if (!AI_Chat_Search_Pro_Manager::can_access_conversation_logs()) {
            wp_send_json_error(array(
                'message' => __('Conversation logs are a Pro feature. Please upgrade to access full chat history.', 'ai-chat-search'),
                'upgrade_url' => AI_Chat_Search_Pro_Manager::get_upgrade_url('conversation_logs')
            ));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $offset = ($page - 1) * self::PER_PAGE;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        global $wpdb;
        $table_name = Listeo_AI_Search_Chat_History::get_table_name();

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $recent_conversations = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    conversation_id,
                    MIN(created_at) as first_message_at,
                    MAX(created_at) as last_message_at,
                    COUNT(*) as message_count,
                    user_id,
                    MAX(ip_address) as ip_address
                FROM {$table_name}
                WHERE conversation_id LIKE %s OR ip_address LIKE %s
                GROUP BY conversation_id
                ORDER BY last_message_at DESC
                LIMIT %d OFFSET %d",
                $search_like,
                $search_like,
                self::PER_PAGE,
                $offset
            ), ARRAY_A);

            $total_conversations = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT conversation_id) FROM {$table_name} WHERE conversation_id LIKE %s OR ip_address LIKE %s",
                $search_like,
                $search_like
            ));
        } else {
            $recent_conversations = Listeo_AI_Search_Chat_History::get_recent_conversations(self::PER_PAGE, $offset);
            $total_conversations = $wpdb->get_var("SELECT COUNT(DISTINCT conversation_id) FROM {$table_name}");
        }

        $total_pages = ceil($total_conversations / self::PER_PAGE);

        // Build conversations HTML
        ob_start();
        if (empty($recent_conversations)) {
            if (!empty($search)) {
                echo '<p style="text-align: center; padding: 40px; color: #666;">' . sprintf(__('No conversations found matching "%s"', 'ai-chat-search'), esc_html($search)) . '</p>';
            } else {
                echo '<p style="text-align: center; padding: 40px; color: #666;">' . __('No conversations found.', 'ai-chat-search') . '</p>';
            }
        }
        foreach ($recent_conversations as $conv) {
            $messages = Listeo_AI_Search_Chat_History::get_conversation($conv['conversation_id']);
            $user_info = $conv['user_id'] ? get_userdata($conv['user_id']) : null;
            $this->render_conversation_card($conv, $messages, $user_info);
        }
        $conversations_html = ob_get_clean();

        // Build pagination HTML
        ob_start();
        $this->render_pagination($page, $total_pages);
        $pagination_html = ob_get_clean();

        wp_send_json_success(array(
            'conversations' => $conversations_html,
            'pagination' => $pagination_html,
            'page' => $page,
            'total_pages' => $total_pages
        ));
    }

    /**
     * AJAX handler for clearing all chat history
     */
    public function ajax_clear() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        global $wpdb;
        $table_name = Listeo_AI_Search_Chat_History::get_table_name();

        $deleted = $wpdb->query("DELETE FROM {$table_name}");

        if ($deleted === false) {
            wp_send_json_error(array('message' => __('Failed to clear chat history.', 'ai-chat-search')));
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Successfully deleted %d chat records.', 'ai-chat-search'), $deleted),
            'deleted' => $deleted
        ));
    }

    /**
     * AJAX handler for deleting a single conversation
     */
    public function ajax_delete_conversation() {
        if (!check_ajax_referer('listeo_ai_search_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'ai-chat-search')));
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'ai-chat-search')));
            return;
        }

        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';

        if (empty($conversation_id)) {
            wp_send_json_error(array('message' => __('Conversation ID is required.', 'ai-chat-search')));
            return;
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_send_json_error(array('message' => __('Chat history class not found.', 'ai-chat-search')));
            return;
        }

        global $wpdb;
        $table_name = Listeo_AI_Search_Chat_History::get_table_name();

        $deleted = $wpdb->delete(
            $table_name,
            array('conversation_id' => $conversation_id),
            array('%s')
        );

        if ($deleted === false) {
            wp_send_json_error(array('message' => __('Failed to delete conversation.', 'ai-chat-search')));
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(__('Deleted %d message(s) from conversation.', 'ai-chat-search'), $deleted),
            'deleted' => $deleted
        ));
    }

    /**
     * AJAX handler for exporting chat history as CSV
     */
    public function ajax_export_csv() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'listeo_ai_search_nonce')) {
            wp_die(__('Security check failed.', 'ai-chat-search'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'ai-chat-search'));
        }

        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            wp_die(__('Chat history class not found.', 'ai-chat-search'));
        }

        $days = isset($_GET['days']) ? intval($_GET['days']) : null;

        Listeo_AI_Search_Chat_History::export_csv($days);
        exit;
    }

    /**
     * Get a display-friendly page title from URL
     * Attempts to resolve the URL to a post/page title, falls back to URL path
     *
     * @param string $url The page URL
     * @return string Display-friendly page name
     */
    private function get_page_title_from_url($url) {
        if (empty($url)) {
            return '';
        }

        // Parse the URL
        $parsed = wp_parse_url($url);
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';

        // Check for homepage
        if (empty($path) || $path === '/') {
            return __('Homepage', 'ai-chat-search');
        }

        // Try to get post ID from URL (works for listings, posts, pages, products)
        $post_id = url_to_postid($url);
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post) {
                return $post->post_title;
            }
        }

        // Fallback: extract last segment of URL path and clean it up
        $segments = explode('/', $path);
        $last_segment = end($segments);

        // Remove common URL patterns
        $last_segment = preg_replace('/\.(html?|php)$/i', '', $last_segment);

        // Convert hyphens/underscores to spaces and capitalize
        $title = str_replace(array('-', '_'), ' ', $last_segment);
        $title = ucwords($title);

        // If still empty, use a shortened URL
        if (empty($title)) {
            return '/' . $path;
        }

        return $title;
    }
}
