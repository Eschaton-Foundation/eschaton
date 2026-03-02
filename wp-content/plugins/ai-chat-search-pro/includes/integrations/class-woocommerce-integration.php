<?php
/**
 * WooCommerce Integration
 *
 * Provides WooCommerce-specific product search functionality
 * Only active when WooCommerce plugin is detected
 *
 * @package AI_Chat_By_Purethemes
 * @since 1.5.0
 */

if (!defined('ABSPATH')) exit;

class Listeo_AI_WooCommerce_Integration {

    /**
     * Constructor - Registers REST API routes
     */
    public function __construct() {
        // Only register routes if WooCommerce is available
        if (class_exists('WooCommerce')) {
            add_action('rest_api_init', array($this, 'register_woocommerce_routes'));
        }
    }

    /**
     * Register WooCommerce-specific REST API routes
     */
    public function register_woocommerce_routes() {

        // Product search endpoint (AI semantic search + WooCommerce filters)
        register_rest_route('listeo/v1', '/woocommerce-product-search', array(
            'methods' => 'POST',
            'callback' => array($this, 'search_products'),
            'permission_callback' => '__return_true',
            'args' => array(
                'query' => array(
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Natural language search query'
                ),
                'price_min' => array(
                    'type' => 'number',
                    'description' => 'Minimum price filter'
                ),
                'price_max' => array(
                    'type' => 'number',
                    'description' => 'Maximum price filter'
                ),
                'in_stock' => array(
                    'type' => 'boolean',
                    'description' => 'Only show in-stock products'
                ),
                'on_sale' => array(
                    'type' => 'boolean',
                    'description' => 'Only show products on sale'
                ),
                'category' => array(
                    'type' => 'string',
                    'description' => 'Product category slug or ID'
                ),
                'rating' => array(
                    'type' => 'number',
                    'description' => 'Minimum rating (1-5)'
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'default' => 10,
                    'description' => 'Results per page'
                )
            )
        ));

        // Product details endpoint - get comprehensive information about a specific product
        register_rest_route('listeo/v1', '/woocommerce-product-details', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_product_details'),
            'permission_callback' => '__return_true',
            'args' => array(
                'product_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'description' => 'Product post ID'
                )
            )
        ));

        // Order status endpoint - check WooCommerce order status
        register_rest_route('listeo/v1', '/woocommerce-order-status', array(
            'methods' => 'POST',
            'callback' => array($this, 'get_order_status'),
            'permission_callback' => '__return_true',
            'args' => array(
                'order_number' => array(
                    'required' => true,
                    'description' => 'Order number or order ID'
                ),
                'billing_email' => array(
                    'type' => 'string',
                    'description' => 'Billing email for verification (required if user not logged in)'
                )
            )
        ));
    }

    /**
     * Product search endpoint - combines AI search with WooCommerce filters
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function search_products($request) {
        // Rate limit check - prevent API abuse
        if (!Listeo_AI_Search_Embedding_Manager::check_rate_limit()) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'type' => 'rate_limit_error'
            ), 429);
        }

        $start_time = microtime(true);

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'WooCommerce is required but not active.',
                'results' => array()
            ), 503);
        }

        $query = $request->get_param('query');
        $has_ai = class_exists('Listeo_AI_Search_AI_Engine');

        $debug = get_option('listeo_ai_search_debug_mode', false);

        if ($debug) {
            error_log('=== WOOCOMMERCE PRODUCT SEARCH ===');
            error_log('Query: ' . ($query ?: 'NOT SET'));
            error_log('AI Available: ' . ($has_ai ? 'YES' : 'NO'));
        }

        // Build initial query args
        $query_args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => $request->get_param('per_page') ?: 10,
            'paged' => 1,
            'ignore_sticky_posts' => 1,
        );

        // Get filters from request
        $price_min = $request->get_param('price_min');
        $price_max = $request->get_param('price_max');
        $in_stock = $request->get_param('in_stock');
        $on_sale = $request->get_param('on_sale');
        $category = $request->get_param('category');
        $rating = $request->get_param('rating');

        // Execute search
        $results = array();

        // Track notice from AI engine (e.g., no embeddings)
        $ai_notice = null;
        $ai_notice_type = null;

        if ($has_ai && !empty($query)) {
            // AI search path - use embeddings
            if ($debug) {
                error_log('Using AI search for products');
            }

            $ai_engine = new Listeo_AI_Search_AI_Engine();

            // Search using AI with 'product' post type
            $ai_results = $ai_engine->search($query, 50, 0, 'product', $debug);

            // Capture notice from AI engine
            if (!empty($ai_results['notice'])) {
                $ai_notice = $ai_results['notice'];
                $ai_notice_type = $ai_results['notice_type'] ?? 'info';
            }

            if ($debug) {
                error_log('AI search returned: ' . (is_array($ai_results['listings']) ? count($ai_results['listings']) : 0) . ' results');
            }

            if (!empty($ai_results['listings'])) {
                // Get product IDs from AI results
                $product_ids = array();
                foreach ($ai_results['listings'] as $result) {
                    $product_ids[] = isset($result['id']) ? $result['id'] : $result;
                }

                // Query products with AI ordering
                $custom_query_args = array(
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => count($product_ids),
                    'paged' => 1,
                    'post__in' => $product_ids,
                    'orderby' => 'post__in',
                    'ignore_sticky_posts' => 1,
                );

                $products = new WP_Query($custom_query_args);

                if ($products->have_posts()) {
                    while ($products->have_posts()) {
                        $products->the_post();
                        $product_data = $this->format_product_data(get_the_ID());

                        // Apply filters
                        if ($this->passes_filters($product_data, $price_min, $price_max, $in_stock, $on_sale, $category, $rating)) {
                            $results[] = $product_data;
                        }
                    }
                    wp_reset_postdata();
                }
            }
        } else {
            // Traditional search path (no AI)
            if ($debug) {
                error_log('Using traditional search for products');
            }

            // Add text search to query args
            if (!empty($query)) {
                $query_args['s'] = $query;
            }

            // Add category filter
            if (!empty($category)) {
                if (is_numeric($category)) {
                    $query_args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'term_id',
                            'terms' => intval($category)
                        )
                    );
                } else {
                    $query_args['tax_query'] = array(
                        array(
                            'taxonomy' => 'product_cat',
                            'field' => 'slug',
                            'terms' => $category
                        )
                    );
                }
            }

            $products = new WP_Query($query_args);

            if ($products->have_posts()) {
                while ($products->have_posts()) {
                    $products->the_post();
                    $product_data = $this->format_product_data(get_the_ID());

                    // Apply filters
                    if ($this->passes_filters($product_data, $price_min, $price_max, $in_stock, $on_sale, $category, $rating)) {
                        $results[] = $product_data;
                    }
                }
                wp_reset_postdata();
            }
        }

        // Limit results to display limit
        $display_limit = intval(get_option('listeo_ai_chat_max_results', 10));
        $actual_total = count($results);
        $displayed_results = array_slice($results, 0, $display_limit);

        if ($debug) {
            error_log('Product search complete: ' . $actual_total . ' results found, ' . count($displayed_results) . ' displayed');
        }

        // Track analytics
        if (class_exists('Listeo_AI_Search_Analytics')) {
            $processing_time = round((microtime(true) - $start_time) * 1000, 2);
            $search_type = ($has_ai && !empty($query)) ? 'ai' : 'traditional';
            Listeo_AI_Search_Analytics::log_search($query, $actual_total, $search_type, $processing_time, 'rest_api_products');
        }

        return new WP_REST_Response(array(
            'success' => true,
            'search_type' => ($has_ai && !empty($query)) ? 'ai_semantic' : 'traditional',
            'total' => $actual_total,
            'total_displayed' => count($displayed_results),
            'display_limit' => $display_limit,
            'results' => $displayed_results
        ), 200);
    }

    /**
     * Get product details endpoint - returns comprehensive product information
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_product_details($request) {
        $product_id = intval($request->get_param('product_id'));

        // Verify product exists
        $post = get_post($product_id);
        if (!$post || $post->post_type !== 'product' || $post->post_status !== 'publish') {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Product not found or not published.'
            ), 404);
        }

        // Get WooCommerce product object
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Could not load product data.'
            ), 500);
        }

        $debug = get_option('listeo_ai_search_debug_mode', false);

        if ($debug) {
            error_log('=== PRODUCT DETAILS REQUEST ===');
            error_log('Product ID: ' . $product_id);
            error_log('Product Type: ' . $product->get_type());
        }

        // Build comprehensive structured content
        $structured_content = $this->build_product_structured_content($product, $product_id);

        return new WP_REST_Response(array(
            'success' => true,
            'product_id' => $product_id,
            'title' => $product->get_name(),
            'url' => get_permalink($product_id),
            'structured_content' => $structured_content
        ), 200);
    }

    /**
     * Build comprehensive structured content for a product
     *
     * Used by get_product_details() tool and "Talk about this product" feature.
     * Includes attributes which are intentionally NOT in embeddings.
     *
     * @param WC_Product $product WooCommerce product object
     * @param int $product_id Product post ID
     * @return string Structured content for AI
     */
    public function build_product_structured_content($product, $product_id) {
        $content = "";

        // === BASIC INFORMATION ===
        $content .= "PRODUCT NAME: " . $product->get_name() . "\n";
        $content .= "SKU: " . ($product->get_sku() ?: 'N/A') . "\n";
        $content .= "URL: " . get_permalink($product_id) . "\n\n";

        // === DESCRIPTIONS ===
        $short_desc = $product->get_short_description();
        if (!empty($short_desc)) {
            $content .= "SHORT DESCRIPTION:\n" . wp_strip_all_tags($short_desc) . "\n\n";
        }

        $long_desc = $product->get_description();
        if (!empty($long_desc)) {
            $content .= "FULL DESCRIPTION:\n" . wp_strip_all_tags($long_desc) . "\n\n";
        }

        // === PRICING (tax-aware using WooCommerce display settings) ===
        $currency_symbol = get_woocommerce_currency_symbol();
        $display_price = wc_get_price_to_display($product);
        $display_regular = wc_get_price_to_display($product, array('price' => $product->get_regular_price()));
        $display_sale = $product->get_sale_price() ? wc_get_price_to_display($product, array('price' => $product->get_sale_price())) : 0;

        $content .= "PRICING:\n";
        if ($product->is_on_sale() && $display_sale) {
            $savings = $display_regular - $display_sale;
            $savings_percent = $display_regular > 0 ? round(($savings / $display_regular) * 100) : 0;
            $content .= "- Regular Price: {$currency_symbol}" . number_format($display_regular, 2) . "\n";
            $content .= "- Sale Price: {$currency_symbol}" . number_format($display_sale, 2) . " (SAVE {$savings_percent}%)\n";
            $content .= "- Current Price: {$currency_symbol}" . number_format($display_price, 2) . " - ON SALE!\n";
        } else {
            $content .= "- Price: {$currency_symbol}" . number_format($display_price, 2) . "\n";
        }
        $content .= "\n";

        // === STOCK STATUS ===
        $content .= "AVAILABILITY:\n";
        $stock_status = $product->get_stock_status();
        if ($stock_status === 'instock') {
            $stock_quantity = $product->get_stock_quantity();
            if ($stock_quantity !== null) {
                $content .= "- Status: IN STOCK ({$stock_quantity} available)\n";
            } else {
                $content .= "- Status: IN STOCK\n";
            }
        } elseif ($stock_status === 'outofstock') {
            $content .= "- Status: OUT OF STOCK\n";
        } elseif ($stock_status === 'onbackorder') {
            $content .= "- Status: AVAILABLE ON BACKORDER\n";
        }
        $content .= "\n";

        // === CATEGORIES & TAGS ===
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        if (!is_wp_error($categories) && !empty($categories)) {
            $content .= "CATEGORIES: " . implode(', ', $categories) . "\n";
        }

        $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'));
        if (!is_wp_error($tags) && !empty($tags)) {
            $content .= "TAGS: " . implode(', ', $tags) . "\n";
        }
        $content .= "\n";

        // === RATINGS & REVIEWS ===
        $average_rating = $product->get_average_rating();
        $rating_count = $product->get_rating_count();
        $review_count = $product->get_review_count();

        if ($rating_count > 0) {
            $content .= "CUSTOMER RATINGS:\n";
            $content .= "- Average Rating: " . number_format($average_rating, 1) . " out of 5 stars\n";
            $content .= "- Total Ratings: {$rating_count}\n";
            $content .= "- Total Reviews: {$review_count}\n\n";
        }

        // === ATTRIBUTES (Size, Color, etc.) ===
        $attributes = $product->get_attributes();
        if (!empty($attributes)) {
            $content .= "PRODUCT ATTRIBUTES:\n";
            foreach ($attributes as $attribute) {
                $name = wc_attribute_label($attribute->get_name());
                if ($attribute->is_taxonomy()) {
                    $terms = wp_get_post_terms($product_id, $attribute->get_name(), array('fields' => 'names'));
                    if (!is_wp_error($terms) && !empty($terms)) {
                        $content .= "- {$name}: " . implode(', ', $terms) . "\n";
                    }
                } else {
                    $options = $attribute->get_options();
                    if (!empty($options)) {
                        $content .= "- {$name}: " . implode(', ', $options) . "\n";
                    }
                }
            }
            $content .= "\n";
        }

        // === VARIATIONS (for variable products) ===
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            if (!empty($variations)) {
                $content .= "AVAILABLE VARIATIONS:\n";
                foreach ($variations as $variation_data) {
                    $variation = wc_get_product($variation_data['variation_id']);
                    if ($variation) {
                        $var_desc = array();
                        foreach ($variation_data['attributes'] as $attr_name => $attr_value) {
                            $clean_name = str_replace('attribute_', '', $attr_name);
                            $clean_name = wc_attribute_label($clean_name);
                            $var_desc[] = "{$clean_name}: {$attr_value}";
                        }
                        $var_price = wc_get_price_to_display($variation);
                        $content .= "- " . implode(', ', $var_desc) . " - {$currency_symbol}" . number_format($var_price, 2);
                        if ($variation->is_in_stock()) {
                            $content .= " (In Stock)";
                        } else {
                            $content .= " (Out of Stock)";
                        }
                        $content .= "\n";
                    }
                }
                $content .= "\n";
            }
        }

        // === SHIPPING & DIMENSIONS ===
        $weight = $product->get_weight();
        $length = $product->get_length();
        $width = $product->get_width();
        $height = $product->get_height();

        if ($weight || $length || $width || $height) {
            $content .= "SHIPPING INFORMATION:\n";
            if ($weight) {
                $content .= "- Weight: {$weight} " . get_option('woocommerce_weight_unit') . "\n";
            }
            if ($length && $width && $height) {
                $dim_unit = get_option('woocommerce_dimension_unit');
                $content .= "- Dimensions: {$length} × {$width} × {$height} {$dim_unit}\n";
            }
            $content .= "\n";
        }

        // === ADDITIONAL INFORMATION ===
        if ($product->is_virtual()) {
            $content .= "PRODUCT TYPE: Digital/Virtual Product (No shipping required)\n\n";
        }
        if ($product->is_downloadable()) {
            $content .= "DOWNLOADABLE: Yes (Digital download available after purchase)\n\n";
        }

        return $content;
    }

    /**
     * Format product data for API response
     *
     * @param int $product_id Product ID
     * @return array Formatted product data
     */
    private function format_product_data($product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
            return array();
        }

        // Get price information (tax-aware using WooCommerce display settings)
        $currency_symbol = get_woocommerce_currency_symbol();
        $display_price = wc_get_price_to_display($product);
        $display_regular = wc_get_price_to_display($product, array('price' => $product->get_regular_price()));
        $display_sale = $product->get_sale_price() ? wc_get_price_to_display($product, array('price' => $product->get_sale_price())) : 0;

        // Format price with currency
        $formatted_price = $display_price ? $currency_symbol . number_format($display_price, 2) : '';
        $formatted_regular_price = $display_regular ? $currency_symbol . number_format($display_regular, 2) : '';

        // Get product image (use WooCommerce placeholder if no image)
        $featured_image = get_the_post_thumbnail_url($product_id, 'medium');
        if (!$featured_image) {
            $featured_image = wc_placeholder_img_src('medium');
        }

        // Basic product data
        $data = array(
            'id' => $product_id,
            'title' => $product->get_name(),
            'url' => get_permalink($product_id),
            // Use product description stripped and trimmed (short_description often contains styled marketing HTML)
            'excerpt' => wp_trim_words(wp_strip_all_tags($product->get_description()), 25),
            'featured_image' => $featured_image,
        );

        // Price data
        $data['price'] = array(
            'regular' => $formatted_regular_price,
            'sale' => $display_sale ? $currency_symbol . number_format($display_sale, 2) : null,
            'formatted' => $formatted_price,
            'currency' => get_woocommerce_currency(),
            'raw' => $display_price,
            'raw_regular' => $display_regular
        );

        // Stock status
        $data['stock_status'] = $product->get_stock_status();
        $data['on_sale'] = $product->is_on_sale();

        // Rating
        $average_rating = $product->get_average_rating();
        $rating_count = $product->get_rating_count();
        $data['rating'] = array(
            'average' => $average_rating ? floatval($average_rating) : 0,
            'count' => intval($rating_count)
        );

        // Categories
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
        $data['categories'] = !is_wp_error($categories) ? $categories : array();

        // Tags
        $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names'));
        $data['tags'] = !is_wp_error($tags) ? $tags : array();

        return $data;
    }

    /**
     * Check if product passes filters
     *
     * @param array $product Product data
     * @param float|null $price_min Minimum price
     * @param float|null $price_max Maximum price
     * @param bool|null $in_stock Stock filter
     * @param bool|null $on_sale Sale filter
     * @param string|null $category Category filter
     * @param float|null $rating Minimum rating
     * @return bool True if product passes all filters
     */
    private function passes_filters($product, $price_min, $price_max, $in_stock, $on_sale, $category, $rating) {
        if (empty($product)) {
            return false;
        }

        // Price filter
        if ($price_min !== null || $price_max !== null) {
            $product_price = isset($product['price']['raw']) ? $product['price']['raw'] : 0;

            if ($price_min !== null && $product_price < floatval($price_min)) {
                return false;
            }

            if ($price_max !== null && $product_price > floatval($price_max)) {
                return false;
            }
        }

        // Stock status filter
        if ($in_stock === true) {
            if (!isset($product['stock_status']) || $product['stock_status'] !== 'instock') {
                return false;
            }
        }

        // On sale filter
        if ($on_sale === true) {
            if (!isset($product['on_sale']) || $product['on_sale'] !== true) {
                return false;
            }
        }

        // Category filter (only for AI search - traditional search handles this in query)
        if (!empty($category) && isset($product['categories'])) {
            $category_match = false;

            foreach ($product['categories'] as $product_category) {
                if (stripos($product_category, $category) !== false) {
                    $category_match = true;
                    break;
                }
            }

            if (!$category_match) {
                return false;
            }
        }

        // Rating filter
        if ($rating !== null) {
            $product_rating = isset($product['rating']['average']) ? $product['rating']['average'] : 0;

            if ($product_rating < floatval($rating)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get order status endpoint - returns comprehensive order information
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_order_status($request) {
        // Rate limiting - 5 per minute, 100 per day per IP
        $client_ip = Listeo_AI_Search_Utility_Helper::get_client_ip_secure();
        $ip_hash = md5($client_ip . 'order_status_salt');

        // Per-minute limit (5 requests)
        $minute_key = 'order_status_min_' . $ip_hash;
        $minute_count = (int) get_transient($minute_key);
        if ($minute_count >= 5) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('Too many requests. Please wait a minute before trying again.', 'ai-chat-search'),
                'type' => 'rate_limit_error'
            ), 429);
        }

        // Per-day limit (100 requests)
        $day_key = 'order_status_day_' . $ip_hash . '_' . date('Y-m-d');
        $day_count = (int) get_transient($day_key);
        if ($day_count >= 100) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('Daily limit exceeded. Please try again tomorrow.', 'ai-chat-search'),
                'type' => 'rate_limit_error'
            ), 429);
        }

        // Increment counters
        set_transient($minute_key, $minute_count + 1, MINUTE_IN_SECONDS);
        set_transient($day_key, $day_count + 1, DAY_IN_SECONDS);

        $order_number = sanitize_text_field($request->get_param('order_number'));
        $billing_email = sanitize_email($request->get_param('billing_email'));

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('WooCommerce is required but not active.', 'ai-chat-search')
            ), 503);
        }

        $debug = get_option('listeo_ai_search_debug_mode', false);

        if ($debug) {
            error_log('=== ORDER STATUS REQUEST ===');
            error_log('Order Number: ' . $order_number);
            error_log('User Logged In: ' . (is_user_logged_in() ? 'Yes' : 'No'));
        }

        // Try to get order by order number or order ID
        $order = wc_get_order($order_number);

        // If not found by ID, try to search by order number
        if (!$order) {
            $orders = wc_get_orders(array(
                'limit' => 1,
                'order_number' => $order_number,
                'return' => 'ids'
            ));

            if (!empty($orders)) {
                $order = wc_get_order($orders[0]);
            }
        }

        // Security: Verify user has permission to view this order
        // Use uniform error response to prevent order ID enumeration
        $has_permission = false;
        $denial_reason = 'not_found';

        if ($order) {
            $current_user_id = get_current_user_id();
            $order_user_id = $order->get_user_id();
            $order_billing_email = $order->get_billing_email();

            // Check if user is logged in and owns the order
            if ($current_user_id > 0 && $current_user_id === $order_user_id) {
                $has_permission = true;
                if ($debug) {
                    error_log('Permission granted: User owns order');
                }
            }
            // Check if user is admin
            elseif (current_user_can('manage_woocommerce')) {
                $has_permission = true;
                if ($debug) {
                    error_log('Permission granted: User is admin');
                }
            }
            // Check if billing email matches (for guest orders or verification)
            elseif (!empty($billing_email) && strtolower($billing_email) === strtolower($order_billing_email)) {
                $has_permission = true;
                if ($debug) {
                    error_log('Permission granted: Email verification successful');
                }
            } else {
                $denial_reason = 'permission_denied';
            }
        }

        // Uniform error response - prevents order enumeration attacks
        // Attacker cannot distinguish between "order doesn't exist" and "no permission"
        if (!$has_permission) {
            if ($debug) {
                error_log('Order access denied: ' . $order_number . ' (reason: ' . $denial_reason . ')');
            }

            return new WP_REST_Response(array(
                'success' => false,
                'error' => __('Order not found or access denied. Please verify your order number and billing email.', 'ai-chat-search')
            ), 404);
        }

        // Build comprehensive structured content for the order
        $structured_content = $this->build_order_structured_content($order);

        return new WP_REST_Response(array(
            'success' => true,
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'structured_content' => $structured_content,
            'view_order_url' => $order->get_view_order_url()
        ), 200);
    }

    /**
     * Build SANITIZED order content for AI (NO PII)
     *
     * SECURITY: Strips all personally identifiable information before sending to OpenAI.
     *
     * REMOVED (NOT sent to OpenAI):
     * - Customer names
     * - Addresses (street, city, state, zip, country)
     * - Email addresses
     * - Phone numbers
     * - Payment method details
     * - Customer notes
     *
     * KEPT (Safe to send):
     * - Order number & status
     * - Product names, quantities, prices
     * - Shipping method (not address)
     * - Tracking number
     * - Dates
     *
     * @param WC_Order $order WooCommerce order object
     * @return string Sanitized content for AI (no PII)
     */
    private function build_order_structured_content($order) {
        $content = "";

        // === ORDER INFORMATION ===
        $content .= "ORDER NUMBER: #" . $order->get_order_number() . "\n";

        // Order status with user-friendly labels
        $status = $order->get_status();
        $status_labels = array(
            'pending' => __('PENDING PAYMENT', 'ai-chat-search'),
            'processing' => __('PROCESSING', 'ai-chat-search'),
            'on-hold' => __('ON HOLD', 'ai-chat-search'),
            'completed' => __('COMPLETED', 'ai-chat-search'),
            'cancelled' => __('CANCELLED', 'ai-chat-search'),
            'refunded' => __('REFUNDED', 'ai-chat-search'),
            'failed' => __('FAILED', 'ai-chat-search'),
            'shipped' => __('SHIPPED', 'ai-chat-search')
        );
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : strtoupper($status);
        $content .= __('STATUS', 'ai-chat-search') . ": " . $status_label . "\n";

        $content .= __('ORDER DATE', 'ai-chat-search') . ": " . $order->get_date_created()->date_i18n('F j, Y') . "\n";

        // Payment status (generic - no method details)
        if ($order->is_paid()) {
            $content .= __('PAYMENT STATUS', 'ai-chat-search') . ": " . __('PAID', 'ai-chat-search') . "\n";
        } else {
            $content .= __('PAYMENT STATUS', 'ai-chat-search') . ": " . __('NOT PAID', 'ai-chat-search') . "\n";
        }

        $content .= "\n";

        // === ORDER ITEMS ===
        $items = $order->get_items();
        if (!empty($items)) {
            $item_count = count($items);
            /* translators: %d: number of items */
            $content .= sprintf(__('ORDER ITEMS (%d item)', 'ai-chat-search'), $item_count) . ($item_count > 1 ? 's' : '') . ":\n";

            foreach ($items as $item) {
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $total = $order->get_formatted_line_subtotal($item);

                $content .= "- {$product_name} × {$quantity} - {$total}\n";

                // Add variation details (e.g., Size: Large, Color: Blue)
                $metadata = $item->get_formatted_meta_data();
                if (!empty($metadata)) {
                    foreach ($metadata as $meta) {
                        $content .= "  • {$meta->display_key}: {$meta->display_value}\n";
                    }
                }
            }
            $content .= "\n";
        }

        // === ORDER TOTALS ===
        $content .= __('ORDER TOTALS', 'ai-chat-search') . ":\n";
        $content .= "- " . __('Subtotal', 'ai-chat-search') . ": " . $order->get_subtotal_to_display() . "\n";

        // Shipping method only (no address)
        if ($order->get_shipping_total() > 0) {
            $shipping_method = $order->get_shipping_method();
            $content .= "- " . __('Shipping', 'ai-chat-search') . ": {$shipping_method} - " . wc_price($order->get_shipping_total()) . "\n";
        }

        // Tax
        if ($order->get_total_tax() > 0) {
            $content .= "- " . __('Tax', 'ai-chat-search') . ": " . wc_price($order->get_total_tax()) . "\n";
        }

        // Discounts
        if ($order->get_total_discount() > 0) {
            $content .= "- " . __('Discount', 'ai-chat-search') . ": -" . wc_price($order->get_total_discount()) . "\n";
        }

        $content .= "- " . __('TOTAL', 'ai-chat-search') . ": " . $order->get_formatted_order_total() . "\n\n";

        // === TRACKING INFORMATION ===
        // Agnostic: check multiple common tracking meta keys used by various plugins
        // First non-empty value wins for each field; if none found, section is skipped
        $tracking_number_keys = array(
            '_tracking_number',
            '_shipping_tracking_number',
        );
        $tracking_provider_keys = array(
            '_tracking_provider',
            '_webexpert_order_tracking_carrier',
        );
        $tracking_url_keys = array(
            '_tracking_url',
        );
        $parcel_id_keys = array(
            '_boxnow_parcel_ids',
        );

        $tracking_number = '';
        foreach ($tracking_number_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if (!empty($value)) {
                $tracking_number = sanitize_text_field($value);
                break;
            }
        }

        $tracking_provider = '';
        foreach ($tracking_provider_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if (!empty($value)) {
                $tracking_provider = sanitize_text_field($value);
                break;
            }
        }

        $tracking_url = '';
        foreach ($tracking_url_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if (!empty($value)) {
                $tracking_url = esc_url_raw($value);
                break;
            }
        }

        // Parcel IDs (may be serialized arrays)
        $parcel_ids = '';
        foreach ($parcel_id_keys as $meta_key) {
            $value = $order->get_meta($meta_key);
            if (!empty($value)) {
                if (is_array($value)) {
                    $parcel_ids = implode(', ', array_map('sanitize_text_field', $value));
                } else {
                    $parcel_ids = sanitize_text_field($value);
                }
                break;
            }
        }

        /**
         * Filter tracking meta keys to support additional plugins.
         *
         * Return associative array with resolved tracking data:
         * 'tracking_number', 'tracking_provider', 'tracking_url', 'parcel_ids'
         *
         * @param array    $tracking_data Current resolved tracking data.
         * @param WC_Order $order         The WooCommerce order object.
         */
        $tracking_data = apply_filters('listeo_ai_order_tracking_data', array(
            'tracking_number'   => $tracking_number,
            'tracking_provider' => $tracking_provider,
            'tracking_url'      => $tracking_url,
            'parcel_ids'        => $parcel_ids,
        ), $order);

        $tracking_number   = $tracking_data['tracking_number'];
        $tracking_provider = $tracking_data['tracking_provider'];
        $tracking_url      = $tracking_data['tracking_url'];
        $parcel_ids        = $tracking_data['parcel_ids'];

        if ($tracking_number || $tracking_url || $parcel_ids) {
            $content .= __('TRACKING INFORMATION', 'ai-chat-search') . ":\n";
            if ($tracking_provider) {
                $content .= "- " . __('Carrier', 'ai-chat-search') . ": {$tracking_provider}\n";
            }
            if ($tracking_number) {
                $content .= "- " . __('Tracking Number', 'ai-chat-search') . ": {$tracking_number}\n";
            }
            if ($parcel_ids) {
                $content .= "- " . __('Parcel ID', 'ai-chat-search') . ": {$parcel_ids}\n";
            }
            if ($tracking_url) {
                $content .= "- " . __('Track Package', 'ai-chat-search') . ": {$tracking_url}\n";
            }
            $content .= "\n";
        }

        // === DELIVERY ESTIMATE ===
        $estimated_delivery = $order->get_meta('_estimated_delivery_date');
        if ($estimated_delivery) {
            $content .= __('ESTIMATED DELIVERY', 'ai-chat-search') . ": " . $estimated_delivery . "\n\n";
        }

        // === ORDER STATUS DETAILS ===
        if ($status === 'completed') {
            $content .= "✅ " . __('Order completed and delivered.', 'ai-chat-search') . "\n";
            if ($order->get_date_completed()) {
                $content .= __('Completed on', 'ai-chat-search') . ": " . $order->get_date_completed()->date_i18n('F j, Y') . "\n";
            }
        } elseif ($status === 'processing') {
            $content .= "🔄 " . __('Order is being processed and will be shipped soon.', 'ai-chat-search') . "\n";
        } elseif ($status === 'shipped') {
            $content .= "📦 " . __('Order has been shipped and is on its way.', 'ai-chat-search') . "\n";
        } elseif ($status === 'pending') {
            $content .= "⏳ " . __('Order is pending payment.', 'ai-chat-search') . "\n";
        } elseif ($status === 'on-hold') {
            $content .= "⏸️ " . __('Order is on hold.', 'ai-chat-search') . "\n";
        } elseif ($status === 'cancelled') {
            $content .= "❌ " . __('Order has been cancelled.', 'ai-chat-search') . "\n";
        } elseif ($status === 'refunded') {
            $content .= "💰 " . __('Order has been refunded.', 'ai-chat-search') . "\n";
        }

        return $content;
    }
}
