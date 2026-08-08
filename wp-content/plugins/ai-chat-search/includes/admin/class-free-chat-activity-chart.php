<?php
/**
 * Free chat activity chart.
 *
 * Renders a lightweight 30-day chart from aggregate chat counters. The Free
 * chart does not read or store conversation contents.
 *
 * @package Listeo_AI_Search
 */

if (!defined("ABSPATH")) {
    exit();
}

class Listeo_AI_Search_Free_Chat_Activity_Chart
{
    /**
     * Cached chart data for the current request.
     *
     * @var array|null
     */
    private $cached_chart_data = null;

    /**
     * Register the Free chart hooks.
     */
    public function __construct()
    {
        add_action(
            "ai_chat_search_render_free_chart_card",
            [$this, "render_chart"],
        );
        add_action("admin_enqueue_scripts", [$this, "enqueue_assets"]);
    }

    /**
     * Enqueue chart assets only on the Free statistics screen.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook)
    {
        if ($hook !== "toplevel_page_ai-chat-search") {
            return;
        }

        $active_tab = isset($_GET["tab"])
            ? sanitize_key(wp_unslash($_GET["tab"]))
            : "stats";
        if ($active_tab !== "stats") {
            return;
        }

        if (AI_Chat_Search_Pro_Manager::can_access_conversation_logs()) {
            return;
        }

        $chartjs_path =
            LISTEO_AI_SEARCH_PLUGIN_PATH .
            "assets/vendor/chartjs/chart.umd.min.js";
        $chart_css_path =
            LISTEO_AI_SEARCH_PLUGIN_PATH .
            "assets/css/free-chat-activity-chart.css";
        $chart_js_path =
            LISTEO_AI_SEARCH_PLUGIN_PATH .
            "assets/js/free-chat-activity-chart.js";

        wp_enqueue_style(
            "listeo-ai-free-chat-activity-chart",
            LISTEO_AI_SEARCH_PLUGIN_URL .
                "assets/css/free-chat-activity-chart.css",
            [],
            file_exists($chart_css_path)
                ? (string) filemtime($chart_css_path)
                : LISTEO_AI_SEARCH_VERSION,
        );

        $chart_data = $this->get_chart_data();

        wp_enqueue_script(
            "listeo-ai-chartjs",
            LISTEO_AI_SEARCH_PLUGIN_URL .
                "assets/vendor/chartjs/chart.umd.min.js",
            [],
            file_exists($chartjs_path)
                ? (string) filemtime($chartjs_path)
                : "4.4.1",
            true,
        );

        wp_enqueue_script(
            "listeo-ai-free-chat-activity-chart",
            LISTEO_AI_SEARCH_PLUGIN_URL .
                "assets/js/free-chat-activity-chart.js",
            ["jquery", "listeo-ai-chartjs"],
            file_exists($chart_js_path)
                ? (string) filemtime($chart_js_path)
                : LISTEO_AI_SEARCH_VERSION,
            true,
        );

        wp_localize_script(
            "listeo-ai-free-chat-activity-chart",
            "purioFreeChatActivityData",
            [
                "labels" => $chart_data["labels"],
                "conversations" => $chart_data["conversations"],
                "messages" => $chart_data["messages"],
                "strings" => [
                    "conversations" => __(
                        "Conversations",
                        "ai-chat-search",
                    ),
                    "messages" => __("Messages", "ai-chat-search"),
                ],
            ],
        );
    }

    /**
     * Build the rolling 30-day series from actual activity.
     *
     * @return array
     */
    private function get_chart_data()
    {
        if ($this->cached_chart_data !== null) {
            return $this->cached_chart_data;
        }

        $stats = get_option("listeo_ai_chat_stats", []);
        $actual_daily =
            is_array($stats) &&
            isset($stats["daily"]) &&
            is_array($stats["daily"])
                ? $stats["daily"]
                : [];

        $timezone = wp_timezone();
        $today = new DateTimeImmutable("today", $timezone);
        $start_date = $today->modify("-29 days");
        $end_date = $today->modify("+1 day");
        $labels = [];
        $conversations = [];
        $messages = [];

        for (
            $date = $start_date;
            $date < $end_date;
            $date = $date->modify("+1 day")
        ) {
            $date_key = $date->format("Y-m-d");
            $actual =
                isset($actual_daily[$date_key]) &&
                is_array($actual_daily[$date_key])
                    ? $actual_daily[$date_key]
                    : [];

            $labels[] = wp_date(
                "M j",
                $date->getTimestamp(),
                $timezone,
            );
            $conversations[] =
                (isset($actual["conversations"])
                    ? max(0, (int) $actual["conversations"])
                    : 0);
            $messages[] =
                (isset($actual["messages"])
                    ? max(0, (int) $actual["messages"])
                    : 0);
        }

        $this->cached_chart_data = [
            "labels" => $labels,
            "conversations" => $conversations,
            "messages" => $messages,
        ];

        return $this->cached_chart_data;
    }

    /**
     * Render the Free chart card contents.
     */
    public function render_chart()
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $chart_data = $this->get_chart_data();
        $has_messages = array_sum($chart_data["messages"]) > 0;

        ?>
        <div class="airs-chart-container airs-free-chat-activity-chart is-loading">
            <div class="airs-chart-toolbar">
                <div class="airs-chart-legend">
                    <span class="airs-legend-item airs-legend-conversations">
                        <span class="airs-legend-color"></span>
                        <?php esc_html_e("Conversations", "ai-chat-search"); ?>
                    </span>
                    <span class="airs-legend-item airs-legend-messages">
                        <span class="airs-legend-color"></span>
                        <?php esc_html_e("Messages", "ai-chat-search"); ?>
                    </span>
                </div>
            </div>
            <div class="airs-chart-canvas-panel">
                <div class="airs-chart-loading" role="status" aria-live="polite">
                    <span class="airs-chart-loading-spinner" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e(
                        "Loading...",
                        "ai-chat-search",
                    ); ?></span>
                </div>
                <canvas id="airs-free-chat-activity-chart"></canvas>
                <?php if (!$has_messages): ?>
                    <span class="airs-chart-no-messages">
                        <?php esc_html_e("No messages yet", "ai-chat-search"); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
