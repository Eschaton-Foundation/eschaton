<?php
/**
 * REST API for the optional backend-only agentic chat mode.
 *
 * @package Listeo_AI_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_Agent_API {

    const NAMESPACE = 'listeo/v1';
    const PROGRESS_TTL = 300;
    const MEMORY_TTL = 1800;
    const MAX_PROGRESS_MESSAGES = 24;
    const MAX_CANCEL_TOKENS_PER_IP = 64;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/agent-chat',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'agent_chat'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'messages' => array(
                        'required' => true,
                        'type' => 'array',
                    ),
                    'request_id' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/agent-progress',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'agent_progress'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'request_id' => array(
                        'required' => true,
                        'type' => 'string',
                    ),
                    'after' => array(
                        'type' => 'integer',
                        'default' => 0,
                    ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/agent-cancel',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'agent_cancel'),
                'permission_callback' => array($this, 'check_permission'),
                'args' => array(
                    'request_id' => array(
                        'required' => false,
                        'type' => 'string',
                        'default' => '',
                    ),
                ),
            )
        );
    }

    public function check_permission($request = null) {
        if (!get_option('listeo_ai_chat_enabled', 0)) {
            return new WP_Error(
                'chat_disabled',
                __('AI Chat is currently disabled.', 'ai-chat-search'),
                array('status' => 403)
            );
        }

        if (!AI_Chat_Search_Pro_Manager::is_pro_active()) {
            return new WP_Error(
                'agentic_mode_pro_required',
                __('Agentic Mode requires PurioChat Pro.', 'ai-chat-search'),
                array('status' => 403)
            );
        }

        if (!get_option('listeo_ai_chat_agentic_mode', 0)) {
            return new WP_Error(
                'agentic_mode_disabled',
                __('Agentic chat mode is currently disabled.', 'ai-chat-search'),
                array('status' => 403)
            );
        }

        if (get_option('listeo_ai_chat_require_login', 0) && !is_user_logged_in()) {
            return new WP_Error(
                'authentication_error',
                __('You must be logged in to use AI Chat.', 'ai-chat-search'),
                array('status' => 401)
            );
        }

        if (
            $request instanceof WP_REST_Request
            && $this->get_session_id($request) === ''
        ) {
            return new WP_Error(
                'invalid_chat_session',
                __('Invalid chat session.', 'ai-chat-search'),
                array('status' => 400)
            );
        }

        if (apply_filters('listeo_ai_chat_should_block_ip', false)) {
            return new WP_Error(
                'ip_blocked',
                __('Access denied.', 'ai-chat-search'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Run one complete backend agent loop.
     *
     * @param WP_REST_Request $request Current request.
     * @return WP_REST_Response
     */
    public function agent_chat($request) {
        $started_at = microtime(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $handoff_error = apply_filters(
            'listeo_ai_chat_handoff_block_ai_request',
            null,
            $request
        );
        if (is_wp_error($handoff_error)) {
            return $this->error_response($handoff_error, 409);
        }

        if (get_option('listeo_ai_chat_require_login', 0) && !is_user_logged_in()) {
            return $this->error_response(
                new WP_Error(
                    'authentication_error',
                    __('You must be logged in to use AI Chat.', 'ai-chat-search')
                ),
                401
            );
        }

        $request_id = $this->sanitize_request_id($request->get_param('request_id'));
        if ($request_id === '') {
            return $this->error_response(
                new WP_Error(
                    'invalid_request_id',
                    __('Invalid agent request identifier.', 'ai-chat-search')
                ),
                400
            );
        }

        $session_id = $this->get_session_id($request);
        $client_ip = Listeo_AI_Search_Utility_Helper::get_client_ip_secure();
        $rate_check = Listeo_AI_Search_Chat_API::check_ip_rate_limit($client_ip, 3);
        if (empty($rate_check['allowed'])) {
            $message = !empty($rate_check['error'])
                ? $rate_check['error']
                : __('Too many requests. Please try again later.', 'ai-chat-search');
            return $this->error_response(
                new WP_Error('rate_limit_error', $message),
                429,
                $request_id
            );
        }

        $provider = new Listeo_AI_Provider();
        $provider->set_managed_gateway_billing_context('agent', $request_id);
        $managed_access_error = $provider->get_no_api_key_configuration_error();
        if ($managed_access_error !== '') {
            return $this->error_response(
                new WP_Error('configuration_error', $managed_access_error),
                422,
                $request_id
            );
        }
        if ($provider->get_api_key() === '') {
            return $this->error_response(
                new WP_Error(
                    'configuration_error',
                    sprintf(
                        __('%s API key is not configured on the server.', 'ai-chat-search'),
                        $provider->get_provider_name()
                    )
                ),
                500,
                $request_id
            );
        }

        $messages = $this->sanitize_messages(
            $request->get_param('messages'),
            $provider
        );
        if (is_wp_error($messages)) {
            return $this->error_response($messages, 400, $request_id);
        }

        $user_message = $this->get_latest_user_text($messages);
        if ($user_message === '') {
            return $this->error_response(
                new WP_Error(
                    'missing_user_message',
                    __('A user message is required.', 'ai-chat-search')
                ),
                400,
                $request_id
            );
        }
        if ($request->get_param('is_transcribed')) {
            $user_message = '[' . __('Transcribed', 'ai-chat-search') . '] '
                . $user_message;
        }

        $this->reset_progress($session_id, $request_id);
        $messages = $this->prepare_agent_messages($messages, $request, $session_id);
        // Keep complete server-side schemas. The frontend filter intentionally
        // removes sensitive/dynamic parameters because browsers receive them.
        $tools = apply_filters(
            'listeo_ai_agent_tools',
            Listeo_AI_Search_Chat_API::get_listeo_tools()
        );

        $progress_callback = function ($event) use ($session_id, $request_id) {
            $this->publish_progress($session_id, $request_id, $event);
        };
        $executor = new Listeo_AI_Search_Agent_Tool_Executor();
        $runner = new Listeo_AI_Search_Agent_Runner(
            $provider,
            $executor,
            $progress_callback,
            array(
                'request' => $request,
                'request_id' => $request_id,
                'session_id' => $session_id,
                'client_ip' => $client_ip,
                'cancel_callback' => function () use ($session_id, $request_id, $client_ip) {
                    return $this->is_cancelled($session_id, $request_id, $client_ip);
                },
            )
        );

        $result = $runner->run($messages, $tools);
        if (is_wp_error($result)) {
            return $this->error_response($result, 500, $request_id);
        }

        $answer = isset($result['answer'])
            ? wp_kses_post(
                Listeo_AI_Search_Chat_API::convert_markdown_to_html(
                    (string) $result['answer']
                )
            )
            : '';
        if ($answer === '') {
            $answer = esc_html__(
                'I could not produce a complete answer. Please try rephrasing your request.',
                'ai-chat-search'
            );
        }

        $handoff_result = !empty($result['handoff']) && is_array($result['handoff'])
            ? $result['handoff']
            : null;

        // A human may have claimed the thread while the agent loop was running.
        // Skip this check for the handoff tool's own terminal response.
        if ($handoff_result === null) {
            $late_handoff_error = apply_filters(
                'listeo_ai_chat_handoff_block_ai_request',
                null,
                $request
            );
            if (is_wp_error($late_handoff_error)) {
                return $this->error_response(
                    $late_handoff_error,
                    409,
                    $request_id
                );
            }
        }

        if (!$this->is_cancelled($session_id, $request_id, $client_ip)) {
            $this->store_memory(
                $session_id,
                isset($result['memory']) ? $result['memory'] : array()
            );
            if ($this->is_cancelled($session_id, $request_id, $client_ip)) {
                delete_transient($this->memory_key($session_id));
            } else {
                $this->save_history(
                    $session_id,
                    $user_message,
                    $answer,
                    $provider,
                    $request,
                    $started_at
                );
            }
        }

        $progress_snapshot = $this->get_progress_snapshot(
            $session_id,
            $request_id
        );
        $response_data = array(
            'success' => true,
            'answer' => $answer,
            'artifacts' => isset($result['artifacts'])
                ? array_values((array) $result['artifacts'])
                : array(),
            'progress_events' => $progress_snapshot['messages'],
            'request_id' => $request_id,
        );
        if ($handoff_result !== null) {
            $response_data['purio_live_handoff'] = $handoff_result;
        } else {
            do_action(
                'listeo_ai_chat_exchange_completed',
                $request,
                $response_data
            );
        }

        $response = new WP_REST_Response($response_data, 200);

        return $this->no_cache($response);
    }

    /**
     * Mark a running request as cancelled by its originating chat session.
     *
     * @param WP_REST_Request $request Current request.
     * @return WP_REST_Response
     */
    public function agent_cancel($request) {
        $request_id = $this->sanitize_request_id($request->get_param('request_id'));
        $session_id = $this->get_session_id($request);
        if ($request_id !== '') {
            $client_ip = Listeo_AI_Search_Utility_Helper::get_client_ip_secure();
            $this->add_cancel_token($session_id, $request_id, $client_ip);

            $progress_key = $this->progress_key($session_id, $request_id);
            $state = get_transient($progress_key);
            if (is_array($state)) {
                $state['cancelled'] = true;
                set_transient($progress_key, $state, self::PROGRESS_TTL);
            }
        }
        delete_transient($this->memory_key($session_id));

        return $this->no_cache(
            new WP_REST_Response(
                array(
                    'success' => true,
                    'request_id' => $request_id,
                ),
                200
            )
        );
    }

    /**
     * Return model-authored progress messages emitted during a running request.
     *
     * @param WP_REST_Request $request Current request.
     * @return WP_REST_Response
     */
    public function agent_progress($request) {
        $request_id = $this->sanitize_request_id($request->get_param('request_id'));
        if ($request_id === '') {
            return $this->error_response(
                new WP_Error(
                    'invalid_request_id',
                    __('Invalid agent request identifier.', 'ai-chat-search')
                ),
                400
            );
        }

        $session_id = $this->get_session_id($request);
        $after = max(0, (int) $request->get_param('after'));
        $snapshot = $this->get_progress_snapshot(
            $session_id,
            $request_id,
            $after
        );

        $response = new WP_REST_Response(
            array(
                'success' => true,
                'messages' => $snapshot['messages'],
                'cursor' => $snapshot['cursor'],
                'request_id' => $request_id,
            ),
            200
        );

        return $this->no_cache($response);
    }

    private function sanitize_messages($raw_messages, $provider) {
        if (!is_array($raw_messages)) {
            return new WP_Error(
                'invalid_messages',
                __('Invalid chat message history.', 'ai-chat-search')
            );
        }

        $messages = array();
        foreach ($raw_messages as $raw_message) {
            if (!is_array($raw_message) || !isset($raw_message['role'])) {
                continue;
            }
            // Ignore progress bubbles stored by earlier Agentic Mode versions.
            if (!empty($raw_message['purio_agent_progress'])) {
                continue;
            }

            $role = sanitize_key($raw_message['role']);
            if (!in_array($role, array('user', 'assistant'), true)) {
                continue;
            }

            $content = isset($raw_message['content']) ? $raw_message['content'] : '';
            if (is_array($content)) {
                $content = $this->sanitize_content_parts($content, $provider);
            } else {
                $content = (string) $content;
                if ($role === 'user') {
                    $content = mb_substr($content, 0, 1000);
                } else {
                    $content = mb_substr($content, 0, 12000);
                }
            }

            if ($content !== '' && $content !== array()) {
                $messages[] = array(
                    'role' => $role,
                    'content' => $content,
                );
            }
        }

        if (empty($messages)) {
            return new WP_Error(
                'invalid_messages',
                __('Invalid chat message history.', 'ai-chat-search')
            );
        }

        $enabled_types = class_exists('Listeo_AI_Search_Database_Manager')
            ? Listeo_AI_Search_Database_Manager::get_enabled_post_types()
            : array();
        $base_messages = (
            in_array('listing', $enabled_types, true)
            || in_array('product', $enabled_types, true)
        ) ? 12 : 6;
        $context_length = get_option('listeo_ai_chat_context_length', 'normal');
        if (
            $provider->get_provider() === 'mistral'
            || strpos($provider->get_chat_model(), 'mistral') === 0
        ) {
            $context_length = 'short';
        }
        $multipliers = Listeo_AI_Search_Chat_API::CONTEXT_MULTIPLIERS;
        $multiplier = isset($multipliers[$context_length])
            ? $multipliers[$context_length]
            : 1;

        return array_slice($messages, -($base_messages * $multiplier));
    }

    private function sanitize_content_parts($parts, $provider) {
        $sanitized = array();
        foreach ($parts as $part) {
            if (!is_array($part) || empty($part['type'])) {
                continue;
            }

            if ($part['type'] === 'text' && isset($part['text'])) {
                $sanitized[] = array(
                    'type' => 'text',
                    'text' => mb_substr((string) $part['text'], 0, 1000),
                );
                continue;
            }

            if (
                $part['type'] === 'image_url'
                && isset($part['image_url']['url'])
                && is_string($part['image_url']['url'])
            ) {
                $url = $part['image_url']['url'];
                $valid_url = strpos($url, 'https://') === 0;

                if (strpos($url, 'data:image/') === 0 && strlen($url) <= 7 * 1024 * 1024) {
                    $valid_url = (bool) preg_match(
                        '#^data:image/(?:jpeg|jpg|png|gif|webp);base64,[A-Za-z0-9+/=\r\n]+$#',
                        $url
                    );
                }

                if ($valid_url) {
                    $detail = isset($part['image_url']['detail'])
                        ? sanitize_key($part['image_url']['detail'])
                        : 'auto';
                    $sanitized[] = $provider->format_image_content($url, $detail);
                }
            }
        }

        return $sanitized;
    }

    private function prepare_agent_messages($messages, $request, $session_id) {
        $system_prompt = Listeo_AI_Search_Chat_API::get_system_prompt(false);
        $system_prompt .= "\n\nAGENTIC EXECUTION:\n";
        $system_prompt .= "- You may call tools repeatedly until you have enough grounded information to answer the whole request.\n";
        $system_prompt .= "- You may add one plain-text status_message of at most 50 characters for the result-analysis phase. Describe what you will compare, check, or synthesize after the tools return. Use the same complete status_message in every parallel tool call. Do not use Markdown or HTML and do not repeat Thinking, Searching, or Analyzing in it.\n";
        $system_prompt .= "- Decompose compound requests into independent objectives. Never combine unrelated product, listing, or content targets into one search query.\n";
        $system_prompt .= "- Call independent tools in the same turn when possible, gather evidence for every part, and keep unrelated outcomes clearly separated in the final answer.\n";
        $system_prompt .= "- Treat PDF content as answer context only. Never mention PDF file names and never link to any PDF source, including URLs ending in .pdf or containing ?post_type=ai_pdf_document.\n";
        $system_prompt .= "- Only call tools present in the current tool catalog. Never tell the user that an available tool cannot be used; call it and report only its actual result.\n";
        $system_prompt .= "- Tools that access private data or perform actions may be called only when the current user request and the tool-specific rules authorize them. Never infer consent, invent missing identity data, or claim success before the tool confirms it.\n";
        $system_prompt .= "- Use add_to_cart only when the user explicitly asks to add or buy a product, with an exact product ID returned by the product tools. Use check_order_status when the user asks about an order and provides both the order number and billing email; ask for whichever value is missing.\n";
        $system_prompt = (string) apply_filters(
            'listeo_ai_agent_system_prompt',
            $system_prompt,
            $request
        );

        if ($this->latest_user_has_image($messages)) {
            $system_prompt .= "\nIMAGE ATTACHED: Acknowledge and describe what is visible in the image before answering. If you use tools, reference the image in the final answer.\n";
        }

        $pinned_context = $this->get_pinned_context($request);
        if ($pinned_context !== '') {
            $system_prompt .= "\n" . $pinned_context;
        }

        $memory = get_transient($this->memory_key($session_id));
        if (is_array($memory) && !empty($memory)) {
            $encoded_memory = wp_json_encode($memory);
            if (is_string($encoded_memory)) {
                $system_prompt .= "\nRECENT TOOL CONTEXT FROM THIS CONVERSATION:\n";
                $system_prompt .= mb_substr($encoded_memory, 0, 16000);
            }
        }

        $messages = array_merge(
            array(array('role' => 'system', 'content' => $system_prompt)),
            $messages
        );

        $latest_user = null;
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if (isset($messages[$index]['role']) && $messages[$index]['role'] === 'user') {
                $latest_user = $index;
                break;
            }
        }

        if ($latest_user !== null) {
            $inline = Listeo_AI_Search_Chat_API::get_language_rule_inline();
            $page_context = $this->get_page_context($request);
            $suffix = "\n\n" . $inline;
            if ($page_context !== '') {
                $suffix .= "\n" . $page_context;
            }

            if (is_array($messages[$latest_user]['content'])) {
                $messages[$latest_user]['content'][] = array(
                    'type' => 'text',
                    'text' => $suffix,
                );
            } else {
                $messages[$latest_user]['content'] .= $suffix;
            }
        }

        return apply_filters(
            'listeo_ai_agent_messages_before_request',
            $messages,
            $request
        );
    }

    private function latest_user_has_image($messages) {
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if (!isset($messages[$index]['role']) || $messages[$index]['role'] !== 'user') {
                continue;
            }
            foreach ((array) $messages[$index]['content'] as $part) {
                if (
                    is_array($part)
                    && isset($part['type'])
                    && $part['type'] === 'image_url'
                ) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    private function get_pinned_context($request) {
        $blocks = array();
        $listing_id = absint($request->get_param('listing_context_id'));
        if ($listing_id > 0) {
            $post = get_post($listing_id);
            if (
                $post
                && $post->post_status === 'publish'
                && $post->post_type === 'listing'
                && class_exists('Listeo_AI_Search_Embedding_Manager')
            ) {
                $embedding_manager = new Listeo_AI_Search_Embedding_Manager();
                $content = $embedding_manager->get_content_for_embedding($listing_id);

                if (
                    class_exists('Listeo_AI_Content_Extractor_Listing')
                    && $content !== ''
                ) {
                    $extractor = new Listeo_AI_Content_Extractor_Listing();
                    $hours = $extractor->get_formatted_hours($listing_id);
                    $extended = $extractor->get_extended_context($listing_id);
                    if ($hours !== '') {
                        $content .= "\nOPENING_HOURS: " . $hours;
                    }
                    if ($extended !== '') {
                        $content .= "\n" . $extended;
                    }
                }

                if ($content !== '') {
                    $blocks[] = "CURRENT LISTING CONTEXT:\n"
                        . mb_substr((string) $content, 0, 30000)
                        . "\nUse this information for questions about the current listing; do not search for the same listing again.";
                }
            }
        }

        $product_id = absint($request->get_param('product_context_id'));
        if ($product_id > 0) {
            $post = get_post($product_id);
            if (
                $post
                && $post->post_status === 'publish'
                && $post->post_type === 'product'
            ) {
                $content = '';
                if (
                    class_exists('Listeo_AI_WooCommerce_Integration')
                    && function_exists('wc_get_product')
                ) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $integration = new Listeo_AI_WooCommerce_Integration();
                        $content = $integration->build_product_structured_content(
                            $product,
                            $product_id
                        );
                    }
                }
                if (
                    $content === ''
                    && class_exists('Listeo_AI_Search_Embedding_Manager')
                ) {
                    $embedding_manager = new Listeo_AI_Search_Embedding_Manager();
                    $content = $embedding_manager->get_content_for_embedding($product_id);
                }

                if ($content !== '') {
                    $blocks[] = "CURRENT PRODUCT CONTEXT:\n"
                        . mb_substr((string) $content, 0, 30000)
                        . "\nUse this information for questions about the current product; do not search for the same product again.";
                }
            }
        }

        if (empty($blocks)) {
            return '';
        }

        return "PINNED PAGE CONTEXT:\n" . implode("\n\n", $blocks);
    }

    private function get_page_context($request) {
        if (!get_option('listeo_ai_chat_page_context_enabled', 0)) {
            return '';
        }

        $page_url = esc_url_raw(trim((string) $request->get_header('X-Page-URL')));
        if ($page_url === '') {
            return '';
        }

        $page_url = mb_substr(preg_replace('/[?#].*$/', '', $page_url), 0, 500);
        $post_id = url_to_postid($page_url);
        $title = $post_id > 0 ? get_the_title($post_id) : '';
        if ($title === '') {
            $title = $page_url;
        }

        $context = '[CURRENT PAGE USER IS VIEWING: '
            . mb_substr(sanitize_text_field($title), 0, 200)
            . ' | '
            . $page_url;
        $post_type = $post_id > 0 ? get_post_type($post_id) : '';
        if ($post_type === 'listing') {
            $context .= ' | LISTING ID: ' . $post_id;
        } elseif ($post_type === 'product') {
            $context .= ' | PRODUCT ID: ' . $post_id;
        }

        return $context . ']';
    }

    private function get_latest_user_text($messages) {
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            if (!isset($messages[$index]['role']) || $messages[$index]['role'] !== 'user') {
                continue;
            }

            if (is_string($messages[$index]['content'])) {
                return trim(wp_strip_all_tags($messages[$index]['content']));
            }

            $parts = array();
            foreach ((array) $messages[$index]['content'] as $part) {
                if (isset($part['type'], $part['text']) && $part['type'] === 'text') {
                    $parts[] = wp_strip_all_tags($part['text']);
                } elseif (isset($part['type']) && $part['type'] === 'image_url') {
                    $parts[] = __('Image attached', 'ai-chat-search');
                }
            }
            return trim(implode(' ', $parts));
        }

        return '';
    }

    private function get_session_id($request) {
        $session_id = sanitize_text_field((string) $request->get_header('X-Session-ID'));
        $session_id = preg_replace('/[^A-Za-z0-9_-]/', '', $session_id);
        $session_id = mb_substr($session_id, 0, 80);
        if (mb_strlen($session_id) >= 16) {
            return $session_id;
        }

        return '';
    }

    private function sanitize_request_id($request_id) {
        $request_id = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            sanitize_text_field((string) $request_id)
        );
        $request_id = mb_substr($request_id, 0, 64);
        return mb_strlen($request_id) >= 16 ? $request_id : '';
    }

    private function progress_key($session_id, $request_id) {
        return 'listeo_ai_agent_progress_' . md5($session_id . '|' . $request_id);
    }

    private function get_progress_snapshot($session_id, $request_id, $after = 0) {
        $state = get_transient($this->progress_key($session_id, $request_id));
        $messages = array();

        if (is_array($state) && !empty($state['messages'])) {
            foreach ($state['messages'] as $message) {
                if (
                    isset($message['sequence'])
                    && (int) $message['sequence'] > (int) $after
                ) {
                    $messages[] = $message;
                }
            }
        }

        return array(
            'messages' => $messages,
            'cursor' => is_array($state) && isset($state['sequence'])
                ? (int) $state['sequence']
                : 0,
        );
    }

    private function reset_progress($session_id, $request_id) {
        set_transient(
            $this->progress_key($session_id, $request_id),
            array(
                'sequence' => 0,
                'messages' => array(),
                'cancelled' => false,
            ),
            self::PROGRESS_TTL
        );
    }

    private function publish_progress($session_id, $request_id, $event) {
        if (!is_array($event)) {
            return;
        }

        $type = isset($event['type']) ? sanitize_key($event['type']) : '';
        $entry = array('type' => $type);

        if ($type !== 'status') {
            return;
        }
        $phase = isset($event['phase']) ? sanitize_key($event['phase']) : '';
        if (!in_array($phase, array('thinking', 'searching', 'analyzing'), true)) {
            return;
        }
        $entry['phase'] = $phase;
        $detail = isset($event['detail']) && is_scalar($event['detail'])
            ? mb_substr(
                sanitize_text_field((string) $event['detail']),
                0,
                Listeo_AI_Search_Agent_Runner::MAX_STATUS_DETAIL_LENGTH
            )
            : '';
        if ($detail !== '') {
            $entry['detail'] = $detail;
        }

        $key = $this->progress_key($session_id, $request_id);
        $state = get_transient($key);
        if (!is_array($state)) {
            $state = array(
                'sequence' => 0,
                'messages' => array(),
                'cancelled' => false,
            );
        }

        $sequence = isset($state['sequence']) ? (int) $state['sequence'] + 1 : 1;
        $state['sequence'] = $sequence;
        $entry['sequence'] = $sequence;
        $state['messages'][] = $entry;
        if (count($state['messages']) > self::MAX_PROGRESS_MESSAGES) {
            $state['messages'] = array_slice(
                $state['messages'],
                -self::MAX_PROGRESS_MESSAGES
            );
        }
        set_transient($key, $state, self::PROGRESS_TTL);
    }

    private function cancel_bucket_key($client_ip) {
        return 'listeo_ai_agent_cancel_bucket_' . md5((string) $client_ip);
    }

    private function cancel_token($session_id, $request_id) {
        return md5($session_id . '|' . $request_id);
    }

    private function add_cancel_token($session_id, $request_id, $client_ip) {
        $key = $this->cancel_bucket_key($client_ip);
        $tokens = get_transient($key);
        if (!is_array($tokens)) {
            $tokens = array();
        }

        $cutoff = time() - self::PROGRESS_TTL;
        $tokens = array_filter(
            $tokens,
            function ($timestamp) use ($cutoff) {
                return (int) $timestamp >= $cutoff;
            }
        );
        $tokens[$this->cancel_token($session_id, $request_id)] = time();
        if (count($tokens) > self::MAX_CANCEL_TOKENS_PER_IP) {
            $tokens = array_slice(
                $tokens,
                -self::MAX_CANCEL_TOKENS_PER_IP,
                null,
                true
            );
        }

        set_transient($key, $tokens, self::PROGRESS_TTL);
    }

    private function is_cancelled($session_id, $request_id, $client_ip) {
        $state = get_transient($this->progress_key($session_id, $request_id));
        if (is_array($state) && !empty($state['cancelled'])) {
            return true;
        }

        $tokens = get_transient($this->cancel_bucket_key($client_ip));
        return is_array($tokens)
            && isset($tokens[$this->cancel_token($session_id, $request_id)]);
    }

    private function memory_key($session_id) {
        return 'listeo_ai_agent_memory_' . md5($session_id);
    }

    private function store_memory($session_id, $memory) {
        if (!is_array($memory) || empty($memory)) {
            return;
        }
        set_transient(
            $this->memory_key($session_id),
            array_slice($memory, -Listeo_AI_Search_Agent_Runner::MAX_TOOL_CALLS),
            self::MEMORY_TTL
        );
    }

    private function save_history(
        $session_id,
        $user_message,
        $answer,
        $provider,
        $request,
        $started_at
    ) {
        if (!class_exists('Listeo_AI_Search_Chat_History')) {
            return;
        }

        Listeo_AI_Search_Chat_History::save_exchange(
            $session_id,
            $user_message,
            $answer,
            $provider->get_chat_model(),
            is_user_logged_in() ? get_current_user_id() : null,
            esc_url_raw((string) $request->get_header('X-Page-URL')),
            max(0, (int) round((microtime(true) - $started_at) * 1000))
        );
    }

    private function error_response($error, $status, $request_id = '') {
        $data = is_wp_error($error) ? $error->get_error_data() : null;
        if (is_array($data) && isset($data['status'])) {
            $status = (int) $data['status'];
        }

        $response = new WP_REST_Response(
            array(
                'success' => false,
                'error' => array(
                    'message' => is_wp_error($error)
                        ? $error->get_error_message()
                        : __('Agent request failed.', 'ai-chat-search'),
                    'type' => is_wp_error($error)
                        ? $error->get_error_code()
                        : 'agent_error',
                    'request_id' => $request_id,
                ),
            ),
            $status
        );

        return $this->no_cache($response);
    }

    private function no_cache($response) {
        $response->header(
            'Cache-Control',
            'no-cache, no-store, must-revalidate, max-age=0'
        );
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', '0');
        $response->header('CDN-Cache-Control', 'no-store');
        return $response;
    }
}

new Listeo_AI_Search_Agent_API();
