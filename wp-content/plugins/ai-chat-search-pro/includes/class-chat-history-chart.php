<?php
/**
 * AI Chat Search Pro - Chat History Chart
 *
 * Displays a visual graph for chat history showing conversations and messages
 * over the last 30 days.
 *
 * @package AI_Chat_Search_Pro
 * @since 1.7.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_Chat_History_Chart {

    /**
     * Cached stats to avoid duplicate database queries
     *
     * @var array|null
     */
    private $cached_stats = null;

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into the new standalone chart card location (right column)
        add_action('ai_chat_search_render_chart_card', array($this, 'render_chart_content'));

        // Legacy hook for backwards compatibility (no longer used in main plugin)
        add_action('ai_chat_search_after_chat_history_stats', array($this, 'render_chart'));

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Check if Pro license is valid
     *
     * @return bool
     */
    private function is_license_valid() {
        if (class_exists('AI_Chat_Search_Pro_Proxy_License_Manager')) {
            $license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();
            return $license_manager->is_license_valid();
        }
        return false;
    }

    /**
     * Get chat history table name
     *
     * @return string
     */
    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'listeo_ai_chat_history';
    }

    /**
     * Get contact messages table name
     *
     * @return string
     */
    private function get_contact_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'listeo_ai_contact_messages';
    }

    /**
     * Get daily stats for the last 30 days
     *
     * @return array Array with dates as keys and conversation/message counts
     */
    public function get_daily_stats() {
        // Return cached result if available
        if ($this->cached_stats !== null) {
            return $this->cached_stats;
        }

        // Verify user has permission to view statistics
        if (!current_user_can('manage_options')) {
            return array();
        }

        if (!$this->is_license_valid()) {
            return array();
        }

        global $wpdb;
        $table_name = $this->get_table_name();

        // Check if table exists using proper prepare()
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($table_name)
            )
        );
        if ($table_exists !== $table_name) {
            return array();
        }

        $date_from = wp_date('Y-m-d', strtotime('-29 days'));

        // Get daily stats with single optimized query
        $stats_query = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                COUNT(DISTINCT conversation_id) as conversation_count,
                COUNT(*) as message_count
            FROM {$table_name}
            WHERE DATE(created_at) >= %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC",
            $date_from
        ), ARRAY_A);

        // Convert to associative arrays for easy lookup
        // Each row = 1 user message, so COUNT(*) = user messages
        $conversations_by_date = array();
        $messages_by_date = array();
        foreach ($stats_query as $row) {
            $conversations_by_date[$row['date']] = intval($row['conversation_count']);
            $messages_by_date[$row['date']] = intval($row['message_count']);
        }

        // Get emails sent per day from contact messages table
        $emails_by_date = array();
        $contact_table = $this->get_contact_table_name();
        $contact_table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $wpdb->esc_like($contact_table)
            )
        );

        if ($contact_table_exists === $contact_table) {
            $emails_query = $wpdb->get_results($wpdb->prepare(
                "SELECT
                    DATE(created_at) as date,
                    COUNT(*) as email_count
                FROM {$contact_table}
                WHERE DATE(created_at) >= %s AND email_sent = 1
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                $date_from
            ), ARRAY_A);

            foreach ($emails_query as $row) {
                $emails_by_date[$row['date']] = intval($row['email_count']);
            }
        }

        // Build complete 30-day array with labels, conversations, messages, and emails
        $labels = array();
        $conversations = array();
        $messages = array();
        $emails = array();

        for ($i = 29; $i >= 0; $i--) {
            $date = wp_date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date_i18n('M j', strtotime($date));
            $conversations[] = isset($conversations_by_date[$date]) ? $conversations_by_date[$date] : 0;
            $messages[] = isset($messages_by_date[$date]) ? $messages_by_date[$date] : 0;
            $emails[] = isset($emails_by_date[$date]) ? $emails_by_date[$date] : 0;
        }

        // Cache the result
        $this->cached_stats = array(
            'labels' => $labels,
            'conversations' => $conversations,
            'messages' => $messages,
            'emails' => $emails,
        );

        return $this->cached_stats;
    }

    /**
     * Enqueue assets for the chart
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets($hook) {
        // Only load on AI Chat Search admin page
        if ($hook !== 'toplevel_page_ai-chat-search') {
            return;
        }

        // Only load on stats tab (where Chat History section is displayed)
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        if ($active_tab !== 'stats') {
            return;
        }

        // Only load if license is valid
        if (!$this->is_license_valid()) {
            return;
        }

        // Enqueue Chart.js from CDN (use unique handle to avoid conflicts)
        wp_enqueue_script(
            'ai-chat-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            array(),
            '4.4.1',
            true
        );

        // Enqueue our custom CSS
        wp_enqueue_style(
            'ai-chat-pro-history-chart',
            AI_CHAT_SEARCH_PRO_URL . 'assets/css/chat-history-chart.css',
            array(),
            AI_CHAT_SEARCH_PRO_VERSION
        );

        // Enqueue our custom JS (depends on Chart.js)
        wp_enqueue_script(
            'ai-chat-pro-history-chart',
            AI_CHAT_SEARCH_PRO_URL . 'assets/js/chat-history-chart.js',
            array('jquery', 'ai-chat-chartjs'),
            AI_CHAT_SEARCH_PRO_VERSION,
            true
        );

        // Get chart data
        $chart_data = $this->get_daily_stats();

        // Only localize if we have data
        if (empty($chart_data)) {
            return;
        }

        // Calculate if emails should be shown (only if total > 0)
        $emails_data = isset($chart_data['emails']) ? $chart_data['emails'] : array();
        $show_emails = array_sum($emails_data) > 0;

        // Localize script with chart data
        wp_localize_script('ai-chat-pro-history-chart', 'aiChatHistoryChartData', array(
            'labels' => isset($chart_data['labels']) ? $chart_data['labels'] : array(),
            'conversations' => isset($chart_data['conversations']) ? $chart_data['conversations'] : array(),
            'messages' => isset($chart_data['messages']) ? $chart_data['messages'] : array(),
            'emails' => $emails_data,
            'showEmails' => $show_emails,
            'strings' => array(
                'conversations' => __('Conversations', 'ai-chat-search-pro'),
                'messages' => __('Messages', 'ai-chat-search-pro'),
                'emails' => __('Emails Sent', 'ai-chat-search-pro'),
            ),
        ));
    }

    /**
     * Render just the chart content (for standalone card in right column)
     * Used by the new ai_chat_search_render_chart_card hook
     */
    public function render_chart_content() {
        // Verify user has permission to view statistics
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only render if license is valid
        if (!$this->is_license_valid()) {
            echo '<p style="text-align: center; color: #666; padding: 20px;">' . esc_html__('Upgrade to Pro to view activity charts.', 'ai-chat-search-pro') . '</p>';
            return;
        }

        // Get chart data to check if there's any data (uses cached result)
        $chart_data = $this->get_daily_stats();
        $has_data = !empty($chart_data['conversations']) && array_sum($chart_data['conversations']) > 0;
        $show_emails = !empty($chart_data['emails']) && array_sum($chart_data['emails']) > 0;
        ?>
        <div class="airs-chart-container" style="min-height: 200px;">
            <?php if ($has_data): ?>
            <div class="airs-chart-legend" style="display: flex; justify-content: center; gap: 20px; margin-bottom: 10px;">
                <span class="airs-legend-item airs-legend-conversations">
                    <span class="airs-legend-color"></span>
                    <?php esc_html_e('Conversations', 'ai-chat-search-pro'); ?>
                </span>
                <span class="airs-legend-item airs-legend-messages">
                    <span class="airs-legend-color"></span>
                    <?php esc_html_e('Messages', 'ai-chat-search-pro'); ?>
                </span>
                <?php if ($show_emails): ?>
                <span class="airs-legend-item airs-legend-emails">
                    <span class="airs-legend-color"></span>
                    <?php esc_html_e('Emails Sent', 'ai-chat-search-pro'); ?>
                </span>
                <?php endif; ?>
            </div>
            <canvas id="airs-chat-history-chart"></canvas>
            <?php else: ?>
            <div class="airs-chart-no-data" style="text-align: center; padding: 40px 20px; color: #666;">
                <p><?php esc_html_e('No activity data to display yet. The chart will appear once conversations are recorded.', 'ai-chat-search-pro'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render the chart HTML (legacy - full wrapper)
     */
    public function render_chart() {
        // Verify user has permission to view statistics
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only render if license is valid
        if (!$this->is_license_valid()) {
            return;
        }

        // Get chart data to check if there's any data (uses cached result)
        $chart_data = $this->get_daily_stats();
        $has_data = !empty($chart_data['conversations']) && array_sum($chart_data['conversations']) > 0;
        $show_emails = !empty($chart_data['emails']) && array_sum($chart_data['emails']) > 0;
        ?>
        <div class="airs-chat-history-chart-wrapper">
            <div class="airs-chart-header">
                <h4><?php esc_html_e('Activity (Last 30 Days)', 'ai-chat-search-pro'); ?></h4>
                <div class="airs-chart-legend">
                    <span class="airs-legend-item airs-legend-conversations">
                        <span class="airs-legend-color"></span>
                        <?php esc_html_e('Conversations', 'ai-chat-search-pro'); ?>
                    </span>
                    <span class="airs-legend-item airs-legend-messages">
                        <span class="airs-legend-color"></span>
                        <?php esc_html_e('Messages', 'ai-chat-search-pro'); ?>
                    </span>
                    <?php if ($show_emails): ?>
                    <span class="airs-legend-item airs-legend-emails">
                        <span class="airs-legend-color"></span>
                        <?php esc_html_e('Emails Sent', 'ai-chat-search-pro'); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="airs-chart-container">
                <?php if ($has_data): ?>
                <canvas id="airs-chat-history-chart"></canvas>
                <?php else: ?>
                <div class="airs-chart-no-data">
                    <p><?php esc_html_e('No activity data to display yet. The chart will appear once conversations are recorded.', 'ai-chat-search-pro'); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
