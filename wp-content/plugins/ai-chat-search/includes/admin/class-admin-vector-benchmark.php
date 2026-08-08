<?php
/**
 * Synthetic vector benchmark for the admin Developer & Debug section.
 *
 * @package Listeo_AI_Search
 */

if (!defined("ABSPATH")) {
    exit();
}

class Listeo_AI_Search_Admin_Vector_Benchmark
{
    /**
     * Register benchmark hooks.
     */
    public function __construct()
    {
        add_action("wp_ajax_listeo_ai_run_vector_benchmark", [
            $this,
            "ajax_run",
        ]);
        add_action(
            "admin_enqueue_scripts",
            [$this, "enqueue_assets"],
            20,
        );
    }

    /**
     * Load the benchmark's isolated assets on the PurioChat admin page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets($hook)
    {
        if ($hook !== "toplevel_page_ai-chat-search") {
            return;
        }

        $css_path =
            LISTEO_AI_SEARCH_PLUGIN_PATH .
            "assets/css/vector-benchmark.css";
        $js_path =
            LISTEO_AI_SEARCH_PLUGIN_PATH .
            "assets/js/admin/vector-benchmark.js";

        wp_enqueue_style(
            "airs-vector-benchmark",
            LISTEO_AI_SEARCH_PLUGIN_URL .
                "assets/css/vector-benchmark.css",
            ["ai-chat-search-admin"],
            file_exists($css_path)
                ? (string) filemtime($css_path)
                : LISTEO_AI_SEARCH_VERSION,
        );
        wp_enqueue_script(
            "airs-vector-benchmark",
            LISTEO_AI_SEARCH_PLUGIN_URL .
                "assets/js/admin/vector-benchmark.js",
            ["jquery", "airs-admin-core"],
            file_exists($js_path)
                ? (string) filemtime($js_path)
                : LISTEO_AI_SEARCH_VERSION,
            true,
        );
        wp_localize_script(
            "airs-vector-benchmark",
            "listeo_ai_vector_benchmark_i18n",
            [
                "running" => __(
                    "Testing %s embeddings...",
                    "ai-chat-search",
                ),
                "complete" => __("Benchmark complete.", "ai-chat-search"),
                "failed" => __("Benchmark failed.", "ai-chat-search"),
                "rowComplete" => __("Complete", "ai-chat-search"),
                "connectionFailed" => __(
                    "Connection failed:",
                    "ai-chat-search",
                ),
            ],
        );
    }

    /**
     * Render the benchmark card.
     */
    public function render()
    {
        ?>
        <div class="airs-form-group airs-vector-benchmark-wrap">
            <section class="airs-vector-benchmark" aria-labelledby="airs-vector-benchmark-title">
                <h4 id="airs-vector-benchmark-title" class="airs-vector-benchmark__title">
                    <?php esc_html_e(
                        "Synthetic Similarity Benchmark",
                        "ai-chat-search",
                    ); ?>
                </h4>
                <p class="airs-vector-benchmark__description">
                    <?php esc_html_e(
                        "Tests how long your server takes to load and compare sample embeddings, similar to a real search by AI.",
                        "ai-chat-search",
                    ); ?>
                </p>

                <div class="airs-vector-benchmark__table-wrap">
                    <table class="airs-vector-benchmark__table">
                        <thead>
                            <tr>
                                <th scope="col">
                                    <?php esc_html_e(
                                        "Embeddings",
                                        "ai-chat-search",
                                    ); ?>
                                    <span class="airs-vector-benchmark__column-help">
                                        <?php esc_html_e(
                                            "Usually 1 embedding = 1 page, post, or product",
                                            "ai-chat-search",
                                        ); ?>
                                    </span>
                                </th>
                                <th scope="col"><?php esc_html_e(
                                    "Time",
                                    "ai-chat-search",
                                ); ?></th>
                                <th scope="col"><?php esc_html_e(
                                    "Status",
                                    "ai-chat-search",
                                ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ([1000, 5000, 10000] as $embedding_count): ?>
                                <tr data-benchmark-count="<?php echo esc_attr(
                                    $embedding_count,
                                ); ?>">
                                    <th scope="row" class="airs-vector-benchmark__count">
                                        <?php echo esc_html(
                                            number_format_i18n(
                                                $embedding_count,
                                            ),
                                        ); ?>
                                    </th>
                                    <td>
                                        <div class="airs-vector-benchmark__time">
                                            <span class="airs-vector-benchmark__bar" data-benchmark-bar aria-hidden="true"></span>
                                            <span class="airs-vector-benchmark__value" data-benchmark-field="duration">—</span>
                                        </div>
                                    </td>
                                    <td class="airs-vector-benchmark__status-cell">
                                        <span class="airs-vector-benchmark__status" data-benchmark-field="status">
                                            <svg width="14" height="14" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                                <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.8"></circle>
                                                <path d="M6 10.2l2.6 2.6L14 7.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                            <span data-benchmark-status-text>—</span>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="airs-vector-benchmark__actions">
                    <button type="button" id="listeo-run-vector-benchmark" class="airs-button airs-button-secondary">
                        <?php esc_html_e(
                            "Run Vector Benchmark",
                            "ai-chat-search",
                        ); ?>
                    </button>
                    <span id="listeo-vector-benchmark-status" aria-live="polite"></span>
                </div>
            </section>
        </div>
        <?php
    }

    /**
     * Run the read-only synthetic benchmark.
     */
    public function ajax_run()
    {
        if (!check_ajax_referer("listeo_ai_search_nonce", "nonce", false)) {
            wp_send_json_error([
                "message" => __("Security check failed.", "ai-chat-search"),
            ]);
            return;
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error([
                "message" => __("Insufficient permissions.", "ai-chat-search"),
            ]);
            return;
        }

        $embedding_count =
            isset($_POST["embedding_count"]) &&
            is_scalar($_POST["embedding_count"])
            ? absint(wp_unslash($_POST["embedding_count"]))
            : 0;

        if (!in_array($embedding_count, [1000, 5000, 10000], true)) {
            wp_send_json_error([
                "message" => __(
                    "Invalid benchmark size.",
                    "ai-chat-search",
                ),
            ]);
            return;
        }

        try {
            global $wpdb;

            $dimensions = 1536;
            $build_vector = static function ($seed) use ($dimensions) {
                $vector = [];
                $magnitude_squared = 0.0;

                for ($i = 0; $i < $dimensions; $i++) {
                    $raw =
                        sin(($i + 1) * 12.9898 + $seed * 78.233) *
                        43758.5453;
                    $value = 2 * ($raw - floor($raw)) - 1;
                    $vector[] = $value;
                    $magnitude_squared += $value * $value;
                }

                $magnitude = sqrt($magnitude_squared);
                foreach ($vector as $index => $value) {
                    $vector[$index] = $value / $magnitude;
                }

                return $vector;
            };

            $query_vector = $build_vector(17);
            $compressed_vectors = [];

            for ($pool_index = 0; $pool_index < 4; $pool_index++) {
                $compressed_vector = Listeo_AI_Search_Database_Manager::compress_embedding_for_storage(
                    $build_vector(43 + $pool_index * 19),
                );

                if (empty($compressed_vector)) {
                    throw new Exception(
                        __(
                            "Could not create the synthetic vector.",
                            "ai-chat-search",
                        ),
                    );
                }

                $compressed_vectors[] = $compressed_vector;
            }

            foreach ($compressed_vectors as $compressed_vector) {
                Listeo_AI_Search_Utility_Helper::dot_product_packed(
                    $query_vector,
                    $compressed_vector,
                );
            }

            // Derived SELECT rows are connection results only. Nothing is written,
            // inserted, or left behind in the user's database.
            $digits_sql =
                "(SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9)";
            $groups_sql =
                "(SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4)";
            $synthetic_id_sql =
                "(synthetic_group.n * 1000 + hundreds.n * 100 + tens.n * 10 + ones.n)";
            $synthetic_rows_sql = "
                SELECT
                    {$synthetic_id_sql} AS listing_id,
                    CASE MOD({$synthetic_id_sql}, 4)
                        WHEN 0 THEN %s
                        WHEN 1 THEN %s
                        WHEN 2 THEN %s
                        ELSE %s
                    END AS embedding,
                    'Synthetic benchmark' AS post_title,
                    'publish' AS post_status,
                    'listing' AS post_type
                FROM {$digits_sql} AS ones
                CROSS JOIN {$digits_sql} AS tens
                CROSS JOIN {$digits_sql} AS hundreds
                CROSS JOIN {$groups_sql} AS synthetic_group
                LIMIT %d
            ";

            $started_at = microtime(true);
            $similarities = [];
            $batch_size =
                $embedding_count > 5000 ? 3000 : $embedding_count;
            $processed_count = 0;

            while ($processed_count < $embedding_count) {
                $current_batch_size = min(
                    $batch_size,
                    $embedding_count - $processed_count,
                );
                $prepared_query = $wpdb->prepare(
                    $synthetic_rows_sql,
                    $compressed_vectors[0],
                    $compressed_vectors[1],
                    $compressed_vectors[2],
                    $compressed_vectors[3],
                    $current_batch_size,
                );
                $embedding_rows = $wpdb->get_results($prepared_query);

                if (
                    !is_array($embedding_rows) ||
                    count($embedding_rows) !== $current_batch_size
                ) {
                    throw new Exception(
                        __(
                            "Could not load sample embeddings from the database.",
                            "ai-chat-search",
                        ),
                    );
                }

                foreach ($embedding_rows as $embedding_row) {
                    $similarity = Listeo_AI_Search_Utility_Helper::dot_product_packed(
                        $query_vector,
                        $embedding_row->embedding,
                    );

                    if ($similarity === false) {
                        throw new Exception(
                            __(
                                "Synthetic vector scoring failed.",
                                "ai-chat-search",
                            ),
                        );
                    }

                    $similarities[] = $similarity;
                }

                $processed_count += $current_batch_size;
                unset($embedding_rows);
            }

            arsort($similarities);

            wp_send_json_success([
                "duration_ms" => round(
                    (microtime(true) - $started_at) * 1000,
                    2,
                ),
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                "message" => sprintf(
                    __("Vector benchmark failed: %s", "ai-chat-search"),
                    $e->getMessage(),
                ),
            ]);
        }
    }
}
