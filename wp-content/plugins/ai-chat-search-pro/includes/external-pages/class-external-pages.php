<?php
/**
 * External Pages Manager - PRO Feature
 *
 * Allows users to add external web pages to train their AI chatbot.
 * Handles URL validation, content fetching, and REST API endpoints.
 *
 * @package AI_Chat_Search_Pro
 * @since 1.8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AI_Chat_Search_Pro_External_Pages {

    /**
     * Maximum number of URLs per batch
     */
    const MAX_URLS_PER_BATCH = 20;

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Only enable features if license is valid
        if (!$this->is_license_valid()) {
            return;
        }

        // Render modal in admin footer (like PDF Documents)
        add_action('admin_footer', array($this, 'render_modal'));

        // Register REST API routes
        add_action('rest_api_init', array($this, 'register_routes'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Unlock ai_external_page post type when Pro is active
        add_filter('ai_chat_search_post_type_locked', array($this, 'unlock_external_page'), 10, 2);
    }

    /**
     * Check if Pro license is valid
     *
     * @return bool True if license is valid
     */
    private function is_license_valid() {
        if (class_exists('AI_Chat_Search_Pro_Proxy_License_Manager')) {
            $license_manager = AI_Chat_Search_Pro_Proxy_License_Manager::get_instance();
            return $license_manager->is_license_valid();
        }
        return false;
    }

    /**
     * Unlock ai_external_page post type when Pro is active
     *
     * @param bool $locked Current locked status
     * @param string $post_type Post type slug
     * @return bool False to unlock, true to keep locked
     */
    public function unlock_external_page($locked, $post_type) {
        if ($post_type === 'ai_external_page') {
            return false; // Unlock for Pro users
        }
        return $locked;
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on AI Chat Search admin page
        if ($hook !== 'toplevel_page_ai-chat-search') {
            return;
        }

        // Only load on database tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        if ($active_tab !== 'database') {
            return;
        }

        wp_enqueue_style(
            'ai-chat-pro-external-pages',
            AI_CHAT_SEARCH_PRO_URL . 'assets/css/external-pages.css',
            array(),
            AI_CHAT_SEARCH_PRO_VERSION
        );

        wp_enqueue_script(
            'ai-chat-pro-external-pages',
            AI_CHAT_SEARCH_PRO_URL . 'assets/js/external-pages.js',
            array('jquery', 'wp-api-fetch'),
            AI_CHAT_SEARCH_PRO_VERSION,
            true
        );

        wp_localize_script('ai-chat-pro-external-pages', 'airsExternalPagesConfig', array(
            'restUrl' => rest_url('listeo/v1/external-pages'),
            'nonce' => wp_create_nonce('wp_rest'),
            'maxUrlsPerBatch' => self::MAX_URLS_PER_BATCH,
            'strings' => array(
                'loading' => __('Loading...', 'ai-chat-search-pro'),
                'validating' => __('Validating URLs...', 'ai-chat-search-pro'),
                'processing' => __('Processing URL %d of %d...', 'ai-chat-search-pro'),
                'confirmDelete' => __('Delete this external page?', 'ai-chat-search-pro'),
                'noValidUrls' => __('No valid URLs to process.', 'ai-chat-search-pro'),
                'success' => __('%d page(s) added successfully.', 'ai-chat-search-pro'),
                'skipped' => __('%d skipped:', 'ai-chat-search-pro'),
                'failed' => __('%d failed:', 'ai-chat-search-pro'),
                'emptyState' => __('No external pages added yet. Click "Add Pages" to get started.', 'ai-chat-search-pro'),
                'error' => __('An error occurred', 'ai-chat-search-pro'),
            ),
        ));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get all external pages
        register_rest_route('listeo/v1', '/external-pages', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_pages'),
                'permission_callback' => array($this, 'check_admin_permission'),
            ),
        ));

        // Validate URLs before processing
        register_rest_route('listeo/v1', '/external-pages/validate', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_urls'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // Add single page (called sequentially for each URL)
        register_rest_route('listeo/v1', '/external-pages/add', array(
            'methods' => 'POST',
            'callback' => array($this, 'add_single_page'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));

        // Delete a page
        register_rest_route('listeo/v1', '/external-pages/(?P<id>\d+)', array(
            'methods' => 'DELETE',
            'callback' => array($this, 'delete_page'),
            'permission_callback' => array($this, 'check_admin_permission'),
        ));
    }

    /**
     * Check if current user has admin permissions
     *
     * @return bool True if user can manage options
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get all external pages with embedding status
     *
     * @return WP_REST_Response
     */
    public function get_pages() {
        global $wpdb;

        $posts = get_posts(array(
            'post_type' => 'ai_external_page',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish',
        ));

        // Get embedding status for all pages
        $embeddings_table = $wpdb->prefix . 'listeo_ai_embeddings';
        $post_ids = wp_list_pluck($posts, 'ID');
        $embedded_ids = array();

        if (!empty($post_ids)) {
            $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
            $embedded_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT listing_id FROM {$embeddings_table} WHERE listing_id IN ($placeholders)",
                ...$post_ids
            ));
        }

        $pages = array();
        foreach ($posts as $post) {
            $pages[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_post_meta($post->ID, '_external_url', true),
                'source_name' => get_post_meta($post->ID, '_external_source_name', true),
                'date' => $post->post_date,
                'has_embedding' => in_array($post->ID, $embedded_ids),
            );
        }

        return rest_ensure_response(array(
            'pages' => $pages,
            'count' => count($pages),
        ));
    }

    /**
     * Validate and prepare URLs for processing
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function validate_urls($request) {
        $urls = $request->get_param('urls');
        $source_name = sanitize_text_field($request->get_param('source_name'));

        if (empty($urls)) {
            return new WP_Error(
                'no_urls',
                __('Please provide at least one URL', 'ai-chat-search-pro'),
                array('status' => 400)
            );
        }

        // Parse URLs (accept array or newline-separated string)
        if (is_string($urls)) {
            $urls = array_filter(array_map('trim', explode("\n", $urls)));
        }
        $urls = array_map('esc_url_raw', $urls);
        $urls = array_filter($urls);
        $urls = array_unique($urls);

        // Limit URLs per batch
        if (count($urls) > self::MAX_URLS_PER_BATCH) {
            $urls = array_slice($urls, 0, self::MAX_URLS_PER_BATCH);
        }

        // Validate each URL
        $valid = array();
        $invalid = array();

        foreach ($urls as $url) {
            // SSRF check
            if (!$this->is_safe_url($url)) {
                $invalid[] = array(
                    'url' => $url,
                    'error' => __('URL not allowed (internal/private)', 'ai-chat-search-pro')
                );
                continue;
            }

            // Duplicate check
            $existing = get_posts(array(
                'post_type' => 'ai_external_page',
                'meta_key' => '_external_url',
                'meta_value' => $url,
                'posts_per_page' => 1,
                'fields' => 'ids',
            ));

            if (!empty($existing)) {
                $invalid[] = array(
                    'url' => $url,
                    'error' => __('Already exists', 'ai-chat-search-pro')
                );
                continue;
            }

            $valid[] = $url;
        }

        return rest_ensure_response(array(
            'valid_urls' => $valid,
            'invalid' => $invalid,
            'source_name' => $source_name,
        ));
    }

    /**
     * Fetch and add a single URL
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function add_single_page($request) {
        $url = esc_url_raw($request->get_param('url'));
        $source_name = sanitize_text_field($request->get_param('source_name'));

        if (empty($url)) {
            return new WP_Error(
                'no_url',
                __('URL required', 'ai-chat-search-pro'),
                array('status' => 400)
            );
        }

        // Re-validate URL (in case called directly)
        if (!$this->is_safe_url($url)) {
            return new WP_Error(
                'unsafe_url',
                __('URL not allowed', 'ai-chat-search-pro'),
                array('status' => 400)
            );
        }

        // Fetch page content
        $content = $this->fetch_page($url);

        if (is_wp_error($content)) {
            return new WP_Error(
                'fetch_failed',
                $content->get_error_message(),
                array('status' => 400)
            );
        }

        // Ensure ai_external_page is enabled for embeddings
        $this->ensure_post_type_enabled();

        // Sanitize content before inserting
        $post_title = !empty($content['title']) ? $content['title'] : $this->title_from_url($url);
        $post_title = sanitize_text_field($post_title);
        $post_content = wp_kses_post($content['text']);

        // Create post
        $post_id = wp_insert_post(array(
            'post_type' => 'ai_external_page',
            'post_status' => 'publish',
            'post_title' => $post_title,
            'post_content' => $post_content,
        ));

        if (!$post_id || is_wp_error($post_id)) {
            return new WP_Error(
                'insert_failed',
                __('Failed to create post', 'ai-chat-search-pro'),
                array('status' => 500)
            );
        }

        // Save meta
        update_post_meta($post_id, '_external_url', $url);
        if (!empty($source_name)) {
            update_post_meta($post_id, '_external_source_name', $source_name);
        }

        // Generate embedding immediately
        if (class_exists('Listeo_AI_Search_Database_Manager')) {
            Listeo_AI_Search_Database_Manager::generate_single_embedding($post_id);
        }

        return rest_ensure_response(array(
            'success' => true,
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'url' => $url,
        ));
    }

    /**
     * Delete an external page
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function delete_page($request) {
        $post_id = intval($request->get_param('id'));

        if ($post_id <= 0) {
            return new WP_Error(
                'invalid_id',
                __('Invalid page ID', 'ai-chat-search-pro'),
                array('status' => 400)
            );
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'ai_external_page') {
            return new WP_Error(
                'not_found',
                __('Page not found', 'ai-chat-search-pro'),
                array('status' => 404)
            );
        }

        // Delete embedding if exists
        global $wpdb;
        $table_name = $wpdb->prefix . 'listeo_ai_embeddings';
        $wpdb->delete($table_name, array('listing_id' => $post_id), array('%d'));

        // Delete the post
        wp_delete_post($post_id, true);

        return rest_ensure_response(array('success' => true));
    }

    /**
     * Fetch page content from URL
     *
     * @param string $url URL to fetch
     * @return array|WP_Error Content array with title and text, or error
     */
    private function fetch_page($url) {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 15,
            'sslverify' => true,
            'user-agent' => 'Mozilla/5.0 (compatible; WordPress/' . get_bloginfo('version') . ')',
            'limit_response_size' => 1024 * 1024, // 1MB max
        ));

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', $response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return new WP_Error(
                'http_error',
                sprintf(__('HTTP %d error', 'ai-chat-search-pro'), $status)
            );
        }

        $html = wp_remote_retrieve_body($response);

        // Process HTML to extract content
        require_once AI_CHAT_SEARCH_PRO_DIR . 'includes/external-pages/class-content-processor.php';
        $processor = new AI_Chat_Search_Pro_Content_Processor();

        return $processor->extract($html);
    }

    /**
     * SSRF protection - block internal/private URLs
     *
     * @param string $url URL to check
     * @return bool True if URL is safe, false otherwise
     */
    private function is_safe_url($url) {
        // Only allow http and https protocols
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!$scheme || !in_array(strtolower($scheme), array('http', 'https'), true)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!$host) {
            return false;
        }

        // Block localhost variants
        $blocked_hosts = array('localhost', '127.0.0.1', '0.0.0.0', '::1');
        if (in_array(strtolower($host), $blocked_hosts, true)) {
            return false;
        }

        // Resolve hostname and check IP
        $ip = gethostbyname($host);

        // If resolution failed, gethostbyname returns the hostname
        if ($ip === $host) {
            return false;
        }

        // Block private and reserved IP ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return true;
    }

    /**
     * Generate title from URL path
     *
     * @param string $url URL to extract title from
     * @return string Generated title
     */
    private function title_from_url($url) {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path || $path === '/') {
            return parse_url($url, PHP_URL_HOST);
        }
        $title = basename($path);
        $title = str_replace(array('-', '_', '.html', '.php', '.htm'), array(' ', ' ', '', '', ''), $title);
        return ucwords(trim($title));
    }

    /**
     * Get current page count
     *
     * @return int Number of external pages
     */
    private function get_page_count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'ai_external_page' AND post_status = 'publish'"
        );
    }

    /**
     * Ensure ai_external_page post type is enabled for embedding generation
     */
    private function ensure_post_type_enabled() {
        $enabled_types = get_option('listeo_ai_search_enabled_post_types', array());
        if (!is_array($enabled_types)) {
            $enabled_types = array();
        }

        if (!in_array('ai_external_page', $enabled_types)) {
            $enabled_types[] = 'ai_external_page';
            update_option('listeo_ai_search_enabled_post_types', $enabled_types);
        }
    }

    /**
     * Render External Pages modal (in admin footer, like PDF Documents)
     */
    public function render_modal() {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'toplevel_page_ai-chat-search') {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        if ($active_tab !== 'database') {
            return;
        }
        ?>
        <div id="external-pages-modal" class="listeo-ai-modal" style="display: none;">
            <div class="listeo-ai-modal-overlay"></div>
            <div class="listeo-ai-modal-content external-pages-modal-content">
                <div class="listeo-ai-modal-header">
                    <h2><?php esc_html_e('External Pages Manager', 'ai-chat-search-pro'); ?></h2>
                    <button type="button" class="listeo-ai-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>

                <div class="listeo-ai-modal-body">
                    <!-- Add URLs Section -->
                    <div class="external-pages-add-section">
                        <h3><?php esc_html_e('Add External Pages', 'ai-chat-search-pro'); ?></h3>
                        <p class="description">
                            <?php esc_html_e('Add external web pages to make their content searchable by AI. Pages will be automatically fetched and embedded.', 'ai-chat-search-pro'); ?>
                        </p>

                        <form id="airs-add-pages-form">
                            <div class="external-pages-form-row">
                                <textarea name="urls" class="widefat" rows="4" placeholder="https://docs.example.com/getting-started&#10;https://help.example.com/faq"></textarea>
                            </div>
                            <div class="external-pages-form-row">
                                <button type="submit" class="button button-primary" id="airs-add-pages-btn">
                                    <span class="dashicons dashicons-plus"></span>
                                    <?php esc_html_e('Add Pages', 'ai-chat-search-pro'); ?>
                                </button>
                            </div>
                            <p class="description">
                                <?php printf(
                                    /* translators: %d is the maximum number of URLs allowed per batch */
                                    esc_html__('One URL per line. Maximum %d URLs per batch.', 'ai-chat-search-pro'),
                                    self::MAX_URLS_PER_BATCH
                                ); ?>
                            </p>
                        </form>

                        <div id="airs-add-results" style="display:none;"></div>
                    </div>

                    <hr>

                    <!-- Existing Pages Section -->
                    <div class="external-pages-list-section">
                        <h3 style="margin-bottom: 7px;"><?php esc_html_e('Added Pages', 'ai-chat-search-pro'); ?></h3>
                        <span class="description"><?php esc_html_e('Pages are automatically embedded after adding. No need to click "Start Training".', 'ai-chat-search-pro'); ?></span>

                        <div id="external-pages-list">
                            <p class="loading-message"><?php esc_html_e('Loading...', 'ai-chat-search-pro'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="listeo-ai-modal-footer">
                    <button type="button" class="button" id="external-pages-modal-close"><?php esc_html_e('Close', 'ai-chat-search-pro'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
}
