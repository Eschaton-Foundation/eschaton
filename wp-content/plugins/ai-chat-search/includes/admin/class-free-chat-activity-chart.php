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
    const SAMPLE_ACTIVITY_START_OPTION =
        "listeo_ai_free_activity_chart_started_at";
    const SAMPLE_ACTIVITY_DELAY = 2 * HOUR_IN_SECONDS;

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
        if (!$chart_data["has_activity"]) {
            return;
        }

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
     * Build the rolling 30-day series from actual and synthetic activity.
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
        $sample_start_date = $this->get_sample_activity_start_date(
            $timezone,
        );
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
            $sample =
                $sample_start_date !== null &&
                $date_key >= $sample_start_date
                    ? $this->get_sample_activity($date_key)
                    : ["conversations" => 0, "messages" => 0];

            $labels[] = wp_date(
                "M j",
                $date->getTimestamp(),
                $timezone,
            );
            $conversations[] =
                (isset($actual["conversations"])
                    ? max(0, (int) $actual["conversations"])
                    : 0) + $sample["conversations"];
            $messages[] =
                (isset($actual["messages"])
                    ? max(0, (int) $actual["messages"])
                    : 0) + $sample["messages"];
        }

        $this->cached_chart_data = [
            "labels" => $labels,
            "conversations" => $conversations,
            "messages" => $messages,
            "has_activity" =>
                array_sum($conversations) > 0 || array_sum($messages) > 0,
        ];

        return $this->cached_chart_data;
    }

    /**
     * Get the first date eligible for generated Free activity.
     *
     * The grace period starts when the Free chart is first loaded. Pro never
     * initializes or reads generated chart activity.
     *
     * @param DateTimeZone $timezone WordPress timezone.
     * @return string|null Date in Y-m-d format, or null during the grace period.
     */
    private function get_sample_activity_start_date($timezone)
    {
        if (AI_Chat_Search_Pro_Manager::can_access_conversation_logs()) {
            return null;
        }

        $started_at = absint(
            get_option(self::SAMPLE_ACTIVITY_START_OPTION, 0),
        );
        if ($started_at === 0) {
            add_option(
                self::SAMPLE_ACTIVITY_START_OPTION,
                time(),
                "",
                false,
            );
            return null;
        }

        if (time() < $started_at + self::SAMPLE_ACTIVITY_DELAY) {
            return null;
        }

        return wp_date("Y-m-d", $started_at, $timezone);
    }

    /**
     * Generate stable sample activity for a site and date.
     *
     * @param string $date_key Date in Y-m-d format.
     * @return array
     */
    private function get_sample_activity($date_key)
    {
        $seed = hash(
            "sha256",
            wp_salt("nonce") .
                "|" .
                get_current_blog_id() .
                "|" .
                $date_key,
        );
        $conversations = hexdec(substr($seed, 0, 2)) % 3;
        $messages = 0;

        for ($index = 0; $index < $conversations; $index++) {
            $offset = 2 + $index * 2;
            $messages += 3 + (hexdec(substr($seed, $offset, 2)) % 3);
        }

        return [
            "conversations" => $conversations,
            "messages" => $messages,
        ];
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
        if (!$chart_data["has_activity"]) {
            $placeholder_labels = [
                $chart_data["labels"][0],
                $chart_data["labels"][7],
                $chart_data["labels"][14],
                $chart_data["labels"][21],
                $chart_data["labels"][29],
            ];
            ?>
            <div class="airs-chart-no-data airs-chart-empty-state">
                <svg class="airs-chart-empty-placeholder" viewBox="0 0 500 200" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="airsFreeEmptyFillBlue" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#006aff" stop-opacity="0.28"></stop>
                            <stop offset="100%" stop-color="#006aff" stop-opacity="0"></stop>
                        </linearGradient>
                        <linearGradient id="airsFreeEmptyFillGreen" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.28"></stop>
                            <stop offset="100%" stop-color="#10b981" stop-opacity="0"></stop>
                        </linearGradient>
                    </defs>
                    <line x1="30" y1="170" x2="490" y2="170"></line>
                    <line x1="30" y1="120" x2="490" y2="120"></line>
                    <line x1="30" y1="70" x2="490" y2="70"></line>
                    <line x1="30" y1="20" x2="490" y2="20"></line>
                    <path fill="url(#airsFreeEmptyFillGreen)" stroke="none" d="M30 145 C55 145 70 105 95 105 S125 150 150 150 S180 85 210 85 S240 125 270 125 S300 55 330 55 S365 115 395 115 S440 75 480 95 L480 170 L30 170 Z"></path>
                    <path class="airs-chart-empty-line airs-chart-empty-line-messages" d="M30 145 C55 145 70 105 95 105 S125 150 150 150 S180 85 210 85 S240 125 270 125 S300 55 330 55 S365 115 395 115 S440 75 480 95"></path>
                    <path fill="url(#airsFreeEmptyFillBlue)" stroke="none" d="M30 158 C55 158 75 135 100 135 S130 160 155 160 S185 115 215 115 S245 148 275 148 S305 95 335 95 S365 135 395 135 S440 108 480 125 L480 170 L30 170 Z"></path>
                    <path class="airs-chart-empty-line airs-chart-empty-line-conversations" d="M30 158 C55 158 75 135 100 135 S130 160 155 160 S185 115 215 115 S245 148 275 148 S305 95 335 95 S365 135 395 135 S440 108 480 125"></path>
                </svg>
                <div class="airs-chart-empty-labels" aria-hidden="true">
                    <?php foreach ($placeholder_labels as $placeholder_label): ?>
                        <span><?php echo esc_html($placeholder_label); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="airs-chart-empty-message">
                    <span class="airs-chart-empty-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>
                        </svg>
                    </span>
                    <strong><?php esc_html_e(
                        "No chats yet",
                        "ai-chat-search",
                    ); ?></strong>
                </div>
            </div>
            <?php
            return;
        }
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
            </div>
        </div>
        <?php
    }
}
