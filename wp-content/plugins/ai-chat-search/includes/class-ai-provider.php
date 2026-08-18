<?php
/**
 * AI Provider Abstraction Layer
 *
 * Handles differences between OpenAI and Google Gemini APIs
 * Provides unified interface for API calls
 *
 * @package Listeo_AI_Search
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Provider {

    /**
     * Stable managed gateway endpoint. Public rollout is controlled separately
     * by gateway-config.json in AI_Chat_Search_Pro_Manager.
     */
    const MANAGED_GATEWAY_ENDPOINT = 'https://purethe.me/purio-gateway';

    /**
     * Current provider
     *
     * @var string 'no_api_key', 'openai', 'gemini', 'mistral', or 'openrouter'
     */
    private $provider;

    /**
     * API key for the selected provider
     *
     * @var string
     */
    private $api_key;

    /**
     * Request-local managed gateway auth context supplied by Pro.
     *
     * @var array|null
     */
    private $managed_gateway_auth_context = null;

    /**
     * Request-local billing metadata for paid Purio Cloud chat calls.
     *
     * @var array
     */
    private $managed_gateway_billing_context = array();

    /**
     * Constructor
     *
     * @param string $provider Optional provider override (defaults to settings)
     * @param string $api_key Optional API key override (defaults to settings)
     */
    public function __construct($provider = null, $api_key = null) {
        $this->provider = $provider ?: get_option('listeo_ai_search_provider', 'openai');

        if (
            $this->provider === 'no_api_key'
            && class_exists('AI_Chat_Search_Pro_Manager')
            && !AI_Chat_Search_Pro_Manager::can_use_no_api_key_access()
        ) {
            $this->provider = 'openai';
        }

        if ($api_key) {
            $this->api_key = $api_key;
        } else {
            // Get API key based on provider
            if ($this->provider === 'no_api_key') {
                $this->api_key = '';
            } elseif ($this->provider === 'gemini') {
                $this->api_key = get_option('listeo_ai_search_gemini_api_key', '');
            } elseif ($this->provider === 'mistral') {
                $this->api_key = get_option('listeo_ai_search_mistral_api_key', '');
            } elseif ($this->provider === 'openrouter') {
                $this->api_key = get_option('listeo_ai_search_openrouter_api_key', '');
            } else {
                $this->api_key = get_option('listeo_ai_search_api_key', '');
            }
        }
    }

    /**
     * Check if user has configured their own API key.
     *
     * @return bool True if any provider API key is set.
     */
    public function has_own_api_key() {
        return (
            get_option('listeo_ai_search_api_key', '') !== ''
            || get_option('listeo_ai_search_gemini_api_key', '') !== ''
            || get_option('listeo_ai_search_mistral_api_key', '') !== ''
            || get_option('listeo_ai_search_openrouter_api_key', '') !== ''
        );
    }

    /**
     * Check whether the managed no-key provider is selected.
     *
     * @return bool
     */
    public function is_no_api_key_provider() {
        return $this->provider === 'no_api_key';
    }

    /**
     * Get the optional signed managed-gateway context supplied by Pro.
     *
     * Free does not know about license credentials. Pro hooks this filter and
     * exchanges its trial/paid license for a short-lived gateway token. The result is
     * memoized for this provider instance so one logical WordPress request does
     * not contact the licenser more than once.
     *
     * @return array
     */
    public function get_managed_gateway_auth_context() {
        if (!$this->is_no_api_key_provider()) {
            return array();
        }

        if ($this->managed_gateway_auth_context !== null) {
            return $this->managed_gateway_auth_context;
        }

        $context = apply_filters('listeo_ai_managed_gateway_auth_context', array(), $this);
        $this->managed_gateway_auth_context = is_array($context) ? $context : array();

        return $this->managed_gateway_auth_context;
    }

    /**
     * Whether Pro claimed this request for signed trial/paid managed access.
     *
     * This stays true when token minting failed, preventing a licensed site from
     * silently falling through to the domain/email based Free quota.
     *
     * @return bool
     */
    public function is_signed_managed_gateway() {
        $context = $this->get_managed_gateway_auth_context();
        return isset($context['tier']) && in_array($context['tier'], array('trial', 'pro'), true);
    }

    /**
     * Group one or more provider calls into one user-visible Purio Cloud turn.
     * Pricing remains server-authoritative; the plugin only reports intent.
     *
     * @param string $operation Stable operation name.
     * @param string $turn_id   Request-local turn identifier.
     * @return void
     */
    public function set_managed_gateway_billing_context($operation, $turn_id) {
        $operation = sanitize_key((string) $operation);
        $allowed = array('chat', 'agent', 'insights', 'translation', 'auto_config', 'messaging', 'admin_assist');
        if (!in_array($operation, $allowed, true)) {
            $operation = 'chat';
        }

        $turn_id = trim((string) $turn_id);
        if ($turn_id === '') {
            $this->managed_gateway_billing_context = array();
            return;
        }

        $this->managed_gateway_billing_context = array(
            'operation' => $operation,
            'turn_id' => substr(hash('sha256', home_url() . '|' . $operation . '|' . $turn_id), 0, 48),
        );
    }

    /**
     * Get the email required for the managed no-key provider.
     *
     * @return string Valid email address or an empty string.
     */
    public function get_no_api_key_email() {
        $email = sanitize_email(get_option('listeo_ai_free_gateway_email', ''));
        return is_email($email) ? $email : '';
    }

    /**
     * Check whether the administrator explicitly consented to sending the
     * site domain and email address to the managed gateway.
     *
     * @return bool
     */
    public function has_no_api_key_consent() {
        return (bool) get_option('listeo_ai_free_gateway_consent', 0);
    }

    /**
     * Get a user-facing configuration error for managed access.
     *
     * @return string Empty when managed access is configured.
     */
    public function get_no_api_key_configuration_error() {
        if (!$this->is_no_api_key_provider()) {
            return '';
        }
        if ($this->get_no_api_key_email() === '') {
            return __('Enter email address in API Configuration.', 'ai-chat-search');
        }
        if (!$this->has_no_api_key_consent()) {
            return __('Confirm consent in API Configuration.', 'ai-chat-search');
        }
        if ($this->is_signed_managed_gateway()) {
            $context = $this->get_managed_gateway_auth_context();
            if (!empty($context['token'])) {
                return '';
            }
            return !empty($context['error'])
                ? sanitize_text_field($context['error'])
                : __('Purio Cloud authorization is temporarily unavailable.', 'ai-chat-search');
        }
        return '';
    }

    /**
     * Get the normalized site domain used to authenticate and meter free requests.
     *
     * @return string
     */
    public function get_no_api_key_domain() {
        $domain = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $domain = strtolower(rtrim($domain, '.'));
        return preg_replace('/^www\./i', '', $domain);
    }

    /**
     * Get current provider.
     *
     * @return string 'openai', 'gemini', 'mistral', or 'openrouter'.
     */
    public function get_provider() {
        if ($this->is_no_api_key_provider()) {
            return 'openrouter';
        }
        return $this->provider;
    }

    /**
     * Get API key for current provider
     *
     * @return string
     */
    public function get_api_key() {
        if ($this->is_no_api_key_provider()) {
            $context = $this->get_managed_gateway_auth_context();
            if ($this->is_signed_managed_gateway()) {
                return !empty($context['token']) ? (string) $context['token'] : '';
            }
            return $this->get_no_api_key_domain();
        }
        return $this->api_key;
    }

    /**
     * Get API endpoint URL
     *
     * @param string $type 'embeddings' or 'chat'
     * @return string Full API endpoint URL
     */
    public function get_endpoint($type = 'embeddings') {
        if ($this->is_no_api_key_provider()) {
            $base = self::MANAGED_GATEWAY_ENDPOINT;
            if ($type === 'embeddings') {
                return $base . '/embeddings/';
            } elseif ($type === 'chat') {
                return $base . '/chat/completions/';
            }
            return '';
        }

        if ($this->get_provider() === 'gemini') {
            // Use OpenAI compatibility mode for Gemini
            // Base URL: https://generativelanguage.googleapis.com/v1beta/openai/
            if ($type === 'embeddings') {
                return 'https://generativelanguage.googleapis.com/v1beta/openai/embeddings';
            } elseif ($type === 'chat') {
                return 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
            }
        } elseif ($this->get_provider() === 'mistral') {
            // Mistral uses OpenAI-compatible API format
            // Base URL: https://api.mistral.ai/v1
            if ($type === 'embeddings') {
                return 'https://api.mistral.ai/v1/embeddings';
            } elseif ($type === 'chat') {
                return 'https://api.mistral.ai/v1/chat/completions';
            }
        } elseif ($this->get_provider() === 'openrouter') {
            // OpenRouter uses OpenAI-compatible API format
            // Base URL: https://openrouter.ai/api/v1
            if ($type === 'embeddings') {
                return 'https://openrouter.ai/api/v1/embeddings';
            } elseif ($type === 'chat') {
                return 'https://openrouter.ai/api/v1/chat/completions';
            }
        } else {
            // OpenAI endpoints
            if ($type === 'embeddings') {
                return 'https://api.openai.com/v1/embeddings';
            } elseif ($type === 'chat') {
                return 'https://api.openai.com/v1/chat/completions';
            }
        }

        return '';
    }

    /**
     * Get HTTP headers for API requests
     *
     * @return array Headers array
     */
    public function get_headers() {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->get_api_key(),
            'Content-Type' => 'application/json',
        );

        if ($this->is_no_api_key_provider()) {
            $headers['X-Site-URL'] = home_url();
            if ($this->is_signed_managed_gateway()) {
                $headers['Idempotency-Key'] = function_exists('wp_generate_uuid4')
                    ? wp_generate_uuid4()
                    : uniqid('purio_', true);
                if (!empty($this->managed_gateway_billing_context['turn_id'])) {
                    $headers['X-Purio-Turn-ID'] = $this->managed_gateway_billing_context['turn_id'];
                    $headers['X-Purio-Operation'] = $this->managed_gateway_billing_context['operation'];
                }
            } else {
                $headers['X-User-Email'] = $this->get_no_api_key_email();
                $headers['X-User-Consent'] = $this->has_no_api_key_consent() ? '1' : '0';
            }
        }

        return $headers;
    }

    /**
     * Parse the stored embedding option into model slug and optional dimensions.
     *
     * Composite values use a colon suffix: text-embedding-3-large:1024
     *
     * @return array { 'model' => string, 'dimensions' => int|null }
     */
    private function parse_embedding_option() {
        $stored = get_option('listeo_ai_embedding_model', '');
        if (empty($stored)) {
            return array('model' => '', 'dimensions' => null);
        }
        $parts  = explode(':', $stored, 2);
        $model  = $parts[0];
        $dims   = isset($parts[1]) ? intval($parts[1]) : null;
        return array('model' => $model, 'dimensions' => $dims);
    }

    /**
     * Get the default embedding model for the current provider.
     *
     * @return string Model name
     */
    public function get_default_embedding_model() {
        if ($this->get_provider() === 'gemini') {
            return 'gemini-embedding-001';
        } elseif ($this->get_provider() === 'mistral') {
            return 'mistral-embed';
        } elseif ($this->get_provider() === 'openrouter') {
            return 'openai/text-embedding-3-small';
        } else {
            return 'text-embedding-3-small';
        }
    }

    /**
     * Check if an embedding model belongs to the given provider.
     *
     * @param string      $model    Model ID to check.
     * @param string|null $provider Provider name, or current provider when null.
     * @return bool
     */
    public function embedding_model_matches_provider($model, $provider = null) {
        if (empty($model)) {
            return false;
        }

        $provider = $provider ?: $this->get_provider();
        $model = explode(':', $model, 2)[0];

        if ($provider === 'openrouter') {
            return strpos($model, '/') !== false;
        }
        if ($provider === 'openai') {
            return strpos($model, '/') === false && strpos($model, 'text-embedding-') === 0;
        }
        if ($provider === 'gemini') {
            return strpos($model, 'gemini-embedding') === 0;
        }
        if ($provider === 'mistral') {
            return strpos($model, 'mistral-') === 0;
        }

        return false;
    }

    /**
     * Get embedding model name
     *
     * @return string Model name
     */
    public function get_embedding_model() {
        $parsed = $this->parse_embedding_option();
        if (!empty($parsed['model']) && $this->embedding_model_matches_provider($parsed['model'])) {
            return $parsed['model'];
        }
        return $this->get_default_embedding_model();
    }

    /**
     * Get chat/completion model name
     *
     * @return string Model name
     */
    public function get_chat_model() {
        $stored = $this->normalize_model(get_option('listeo_ai_chat_model', ''));
        if ($this->is_no_api_key_provider()) {
            if (!$this->is_signed_managed_gateway()) {
                return 'openai/gpt-5.4-mini';
            }
            $models = self::get_managed_gateway_chat_models();
            return isset($models[$stored]) ? $stored : 'openai/gpt-5.4-mini';
        }
        if ($this->get_provider() === 'gemini') {
            return $this->model_matches_provider($stored, 'gemini') ? $stored : 'gemini-3.7-flash';
        } elseif ($this->get_provider() === 'mistral') {
            return $this->model_matches_provider($stored, 'mistral') ? $stored : 'mistral-large-latest';
        } elseif ($this->get_provider() === 'openrouter') {
            return $this->model_matches_provider($stored, 'openrouter') ? $stored : 'openai/gpt-5.4-mini';
        } else {
            return $this->model_matches_provider($stored, 'openai') ? $stored : 'gpt-5.4-mini';
        }
    }

    /**
     * Curated model choices for license-backed Purio Cloud access.
     *
     * The gateway remains authoritative for both this allowlist and pricing;
     * these values only constrain the owner-facing selector and payload slug.
     *
     * @return array<string,array{name:string,credits:int}>
     */
    public static function get_managed_gateway_chat_models() {
        return array(
            'google/gemini-3.5-flash-lite' => array('name' => 'Gemini 3.5 Flash Lite', 'credits' => 1),
            'google/gemini-3-flash-preview' => array('name' => 'Gemini 3 Flash', 'credits' => 1),
            'openai/gpt-5.4-mini' => array('name' => 'GPT-5.4 Mini', 'credits' => 1),
            'openai/gpt-5.6-luna' => array('name' => 'GPT-5.6 Luna', 'credits' => 1),
            'anthropic/claude-haiku-4.5' => array('name' => 'Claude Haiku 4.5', 'credits' => 1),
            'google/gemini-3.6-flash' => array('name' => 'Gemini 3.6 Flash', 'credits' => 2),
            'openai/gpt-5.6-terra' => array('name' => 'GPT-5.6 Terra', 'credits' => 2),
            'anthropic/claude-sonnet-5' => array('name' => 'Claude Sonnet 5', 'credits' => 2),
        );
    }

    /**
     * Check if a model ID belongs to the given provider.
     *
     * @param string $model Model ID to check.
     * @param string $provider Provider name.
     * @return bool
     */
    private function model_matches_provider($model, $provider) {
        if (empty($model)) {
            return false;
        }
        if ($provider === 'openrouter') {
            return strpos($model, '/') !== false;
        }
        if ($provider === 'openai') {
            return strpos($model, '/') === false && strpos($model, 'gpt-') === 0;
        }
        if ($provider === 'gemini') {
            return strpos($model, 'gemini') === 0;
        }
        if ($provider === 'mistral') {
            return strpos($model, 'mistral') === 0;
        }
        return false;
    }

    /**
     * Prepare embedding request payload
     *
     * @param string|array $input Text to embed (single string or array of strings)
     * @return array Request payload
     */
    public function prepare_embedding_payload($input) {
        $parsed = $this->parse_embedding_option();
        if (!empty($parsed['model']) && !$this->embedding_model_matches_provider($parsed['model'])) {
            $parsed = array('model' => '', 'dimensions' => null);
        }
        $model  = $this->get_embedding_model();
        $dims   = $parsed['dimensions'];

        $payload = array(
            'model' => $model,
            'input' => $input,
        );

        if ($this->get_provider() === 'openrouter') {
            $payload['encoding_format'] = 'float';
        }

        // Add dimensions when explicitly configured via composite value
        if ($dims !== null && $dims > 0) {
            $payload['dimensions'] = $dims;
        } elseif ($this->get_provider() === 'gemini' && empty($parsed['model'])) {
            // Legacy fallback: gemini direct without stored option got hardcoded 1536
            $payload['dimensions'] = 1536;
        }

        return $payload;
    }

    /**
     * Prepare chat completion request payload
     *
     * @param array $messages Array of message objects
     * @param array $tools Optional tools for function calling
     * @param string $tool_choice Optional tool choice strategy
     * @return array Request payload
     */
    public function prepare_chat_payload($messages, $tools = null, $tool_choice = null) {
        $model = $this->get_chat_model();

        $payload = array(
            'model' => $model,
            'messages' => $messages,
        );

        // Only include tools if array is not empty
        // Empty tools array causes API errors in both OpenAI and Gemini
        if ($tools && is_array($tools) && count($tools) > 0) {
            $payload['tools'] = $tools;

            // The frontend executes one tool call at a time. Keep compatible
            // providers from returning multiple parallel tool calls in one turn.
            if (in_array($this->get_provider(), array('openai', 'openrouter'), true)) {
                $payload['parallel_tool_calls'] = false;
            }

            // Only include tool_choice if tools are present
            if ($tool_choice) {
                $payload['tool_choice'] = $tool_choice;
            }
        }

        return $payload;
    }

    /**
     * Strip OpenRouter namespace prefix from a model slug.
     *
     * OpenRouter uses namespaced slugs like 'openai/gpt-5.6-terra', 'google/gemini-3-flash-preview'.
     * This helper returns the bare model ID without the vendor prefix.
     *
     * @param string $model Full model slug.
     * @return string Bare model ID (e.g. 'gpt-5.6-terra').
     */
    public function get_bare_model( $model ) {
        if ( ! is_string( $model ) || $model === '' ) {
            return '';
        }
        return strpos( $model, 'openai/' ) === 0 ? substr( $model, 7 ) : $model;
    }

    /**
     * Check if a model belongs to the GPT-5 family.
     *
     * @param string $model Full or bare model slug.
     * @return bool
     */
    public function is_gpt5( $model ) {
        $bare = $this->get_bare_model( $model );
        return strpos( $bare, 'gpt-5' ) === 0;
    }

    /**
     * Check whether this request uses the native OpenAI GPT-5.6 family.
     *
     * OpenRouter and the trial gateway keep their existing compatibility path.
     *
     * @param string|null $model Optional model slug. Defaults to the chat model.
     * @return bool
     */
    public function is_native_openai_gpt56( $model = null ) {
        if ( $this->get_provider() !== 'openai' ) {
            return false;
        }

        $model = $model !== null ? $model : $this->get_chat_model();
        $bare  = $this->get_bare_model( $model );

        return strpos( $bare, 'gpt-5.6-' ) === 0;
    }

    /**
     * Return the supported replacement for a retired chat model.
     *
     * @param string $model Full or bare model slug.
     * @return string Replacement slug, or the original slug when still supported.
     */
    public static function get_retired_model_replacement( $model ) {
        if ( ! is_string( $model ) || $model === '' ) {
            return $model;
        }

        $replacements = array(
            'gpt-5-mini'                       => 'gpt-5.4-mini',
            'gpt-5-chat-latest'                => 'gpt-5.6-terra',
            'gpt-5.1'                          => 'gpt-5.6-terra',
            'gpt-5.2'                          => 'gpt-5.6-terra',
            'gpt-5.3-chat-latest'              => 'gpt-5.6-terra',
            'gpt-5.4'                          => 'gpt-5.6-terra',
            'gpt-4o'                           => 'gpt-5.6-luna',
            'gpt-4o-mini'                      => 'gpt-5.6-luna',
            'openai/gpt-5-mini'                => 'openai/gpt-5.4-mini',
            'openai/gpt-5-chat-latest'         => 'openai/gpt-5.6-terra',
            'openai/gpt-5.1'                   => 'openai/gpt-5.6-terra',
            'openai/gpt-5.2'                   => 'openai/gpt-5.6-terra',
            'openai/gpt-5.3-chat-latest'       => 'openai/gpt-5.6-terra',
            'openai/gpt-5.4'                   => 'openai/gpt-5.6-terra',
            'openai/gpt-4o'                    => 'openai/gpt-5.6-luna',
            'openai/gpt-4o-mini'               => 'openai/gpt-5.6-luna',
        );

        return isset( $replacements[ $model ] )
            ? $replacements[ $model ]
            : $model;
    }

    /**
     * Apply model ID mappings for renamed and retired models.
     *
     * @param string $model Full model slug (may include openai/ prefix).
     * @return string Mapped model slug.
     */
    public function normalize_model( $model ) {
        if ( ! is_string( $model ) || $model === '' ) {
            return $model;
        }
        if ( $model === 'gemini-3.1-flash-lite-preview' ) {
            return 'gemini-3.1-flash-lite';
        }

        if ( $model === 'google/gemini-3.1-flash-lite-preview' ) {
            return 'google/gemini-3.1-flash-lite';
        }

        return self::get_retired_model_replacement( $model );
    }

    /**
     * Normalize a chat completion payload for the current provider and model.
     *
     * Centralizes all model-specific parameter differences into one method:
     *   - max_tokens vs max_completion_tokens (GPT-5 vs others)
     *   - unsupported sampling parameters for GPT-5 and direct Gemini 3.7
     *   - reasoning_effort per model (GPT-5.x, Gemini 3.x)
     *   - OpenAI Fast mode for native GPT-5.6 models
     *   - OpenRouter reasoning override (object form: reasoning: {effort: ...})
     *   - Model ID remaps for renamed and retired models
     *
     * @param array $payload Base payload with at minimum 'model' and 'messages'.
     * @param array $options {
     *     Optional. Normalization overrides.
     *     @type int    $max_tokens   Max tokens for the response. Default 5000.
     *     @type float  $temperature  Temperature for non-GPT-5 models. Default 0.6.
     *     @type string|null $reasoning Force a reasoning level ('none','low','medium','high').
     *                                   null = auto per model. Default null.
     * }
     * @return array Normalized payload ready for wp_remote_post.
     */
    public function normalize_chat_payload( array $payload, array $options = array() ) {
        $max_tokens   = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 5000;
        $temperature  = isset( $options['temperature'] ) ? (float) $options['temperature'] : 0.6;
        $force_reasoning = isset( $options['reasoning'] ) ? $options['reasoning'] : null;

        // Every managed chat path uses the fixed Free model or the curated
        // signed trial/Pro selection. Callers cannot smuggle their own slug.
        if ( $this->is_no_api_key_provider() ) {
            $payload['model'] = $this->get_chat_model();
        }

        // Step 1: Apply model ID remaps
        $model = isset( $payload['model'] ) ? $payload['model'] : '';
        $model = $this->normalize_model( $model );
        $payload['model'] = $model;
        $bare = $this->get_bare_model( $model );

        // Step 2: max_tokens key - GPT-5 uses max_completion_tokens, others use max_tokens
        if ( $this->is_gpt5( $model ) ) {
            $payload['max_completion_tokens'] = $max_tokens;
            unset( $payload['max_tokens'] );
        } else {
            $payload['max_tokens'] = $max_tokens;
            unset( $payload['max_completion_tokens'] );
        }

        // Step 3: Sampling - GPT-5 and direct Gemini 3.7 don't support temperature.
        $is_direct_gemini_37 = $this->get_provider() === 'gemini' && $model === 'gemini-3.7-flash';
        if ( $this->is_gpt5( $model ) || $is_direct_gemini_37 ) {
            unset( $payload['temperature'] );
            if ( $is_direct_gemini_37 ) {
                unset( $payload['top_p'], $payload['top_k'] );
            }
        } else {
            $payload['temperature'] = $temperature;
        }

        // Step 4: Reasoning - native providers (not OpenRouter)
        // OpenRouter is handled separately in step 5
        // Mistral only accepts 'none' or 'high' — map 'low'/'medium' to 'none'.
        if ( $this->get_provider() !== 'openrouter' ) {
            if ( $force_reasoning !== null ) {
                $effective_reasoning = $force_reasoning;
                if ( $this->get_provider() === 'mistral' && in_array( $effective_reasoning, array( 'low', 'medium' ), true ) ) {
                    $effective_reasoning = 'none';
                }
                $payload['reasoning_effort'] = $effective_reasoning;
            } elseif ( $this->is_native_openai_gpt56( $model ) ) {
                $payload['reasoning_effort'] = get_option( 'listeo_ai_gpt56_reasoning', 0 )
                    ? 'low'
                    : 'none';
            } elseif ( $bare === 'gpt-5.1' ) {
                $payload['reasoning_effort'] = 'none';
            } elseif ( $bare === 'gpt-5-mini' ) {
                $payload['reasoning_effort'] = 'low';
            } elseif ( strpos( $model, 'gemini-3.1-pro' ) !== false || strpos( $model, 'gemini-3-pro' ) !== false ) {
                $payload['reasoning_effort'] = 'low';
            } elseif ( strpos( $model, 'gemini-3.7-flash' ) !== false || strpos( $model, 'gemini-3.6-flash' ) !== false || strpos( $model, 'gemini-3.5-flash' ) !== false || strpos( $model, 'gemini-3-flash' ) !== false ) {
                $payload['reasoning_effort'] = 'low';
            }
        }

        // Step 5: OpenAI Fast mode
        if (
            $this->is_native_openai_gpt56( $model )
            && get_option( 'listeo_ai_gpt56_fast_mode', 0 )
        ) {
            $payload['service_tier'] = 'fast';
        } else {
            unset( $payload['service_tier'] );
        }

        // Step 6: OpenRouter reasoning override
        // Uses object form `reasoning: {effort: ...}` per OpenRouter docs.
        // Applied last so it replaces any native-provider reasoning_effort.
        if ( $this->get_provider() === 'openrouter' && isset( $payload['model'] ) ) {
            unset( $payload['reasoning_effort'] );

            if ( $force_reasoning !== null ) {
                // Explicit reasoning override takes precedence over toggle logic.
                $payload['reasoning'] = array( 'effort' => $force_reasoning );
            } elseif ( ! get_option( 'listeo_ai_openrouter_reasoning', 0 ) ) {
                // Some models reject 'none' with HTTP 400 (openai/*, select google/gemini-3*)
                $is_gemini_37 = strpos( $payload['model'], 'google/gemini-3.7-flash' ) !== false;
                $reasoning_mandatory = ( strpos( $payload['model'], 'openai/' ) === 0 )
                    || ( strpos( $payload['model'], 'google/gemini-3.1-pro' ) !== false )
                    || $is_gemini_37
                    || ( strpos( $payload['model'], 'google/gemini-3.6-flash' ) !== false )
                    || ( strpos( $payload['model'], 'google/gemini-3.5-flash' ) !== false );
                $effort = $is_gemini_37 ? 'low' : ( $reasoning_mandatory ? 'minimal' : 'none' );
                $payload['reasoning'] = array( 'effort' => $effort );
            } else {
                // Reasoning toggle ON - let model use its default
                unset( $payload['reasoning'] );
            }
        }

        return $payload;
    }

    /**
     * Send a chat request through the provider's appropriate API.
     *
     * Direct OpenAI GPT-5.6 requests use the Responses API. The rest of the
     * plugin keeps its existing Chat Completions payload and response shape.
     *
     * @param array $payload Normalized Chat Completions payload.
     * @param int   $timeout Request timeout in seconds.
     * @param bool  $preserve_agent_state Include native replay state for the
     *                                    private backend agent loop.
     * @return array|WP_Error WordPress HTTP response.
     */
    public function request_chat( array $payload, $timeout = 60, $preserve_agent_state = false ) {
        if ( ! $this->is_native_openai_gpt56( isset( $payload['model'] ) ? $payload['model'] : null ) ) {
            return wp_remote_post( $this->get_endpoint( 'chat' ), array(
                'headers'     => $this->get_headers(),
                'body'        => wp_json_encode( $payload ),
                'timeout'     => $timeout,
                'data_format' => 'body',
            ) );
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/responses', array(
            'headers'     => $this->get_headers(),
            'body'        => wp_json_encode( $this->convert_chat_payload_to_responses( $payload ) ),
            'timeout'     => $timeout,
            'data_format' => 'body',
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $response;
        }

        $response_data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( is_array( $response_data ) ) {
            $response['body'] = wp_json_encode(
                $this->convert_responses_to_chat_response(
                    $response_data,
                    (bool) $preserve_agent_state
                )
            );
        }

        return $response;
    }

    /**
     * Get conservative function-calling capabilities for the active provider.
     *
     * The common agent loop only requires basic tool support. Optional features
     * stay disabled unless the active transport is known to support them.
     *
     * @return array {
     *     Provider capabilities.
     *
     *     @type bool $tools     Whether function tools are supported.
     *     @type bool $parallel  Whether parallel tool calls may be requested.
     *     @type bool $forced    Whether a specific function may be forced.
     *     @type bool $reasoning Whether provider reasoning state can be replayed.
     * }
     */
    public function get_agent_capabilities() {
        $provider = $this->get_provider();
        $known_providers = array( 'openai', 'gemini', 'mistral', 'openrouter' );

        return array(
            'tools'     => in_array( $provider, $known_providers, true ),
            'parallel'  => in_array( $provider, array( 'openai', 'openrouter' ), true ),
            'forced'    => $provider === 'openai',
            'reasoning' => $this->is_native_openai_gpt56(),
        );
    }

    /**
     * Request one provider-neutral agent turn.
     *
     * This method is additive to the legacy chat flow. It accepts canonical
     * Chat Completions messages and tools, then returns one stable shape for all
     * supported providers and for native OpenAI Responses API models.
     *
     * @param array $messages Canonical chat messages.
     * @param array $tools Canonical function tool definitions.
     * @param array $options {
     *     Optional agent request options.
     *
     *     @type string|array $tool_choice Tool selection strategy. Default 'auto'.
     *     @type bool         $require_tool Force one of the supplied tools.
     *     @type bool         $parallel    Request parallel calls when supported. Default false.
     *     @type int          $max_tokens  Maximum response tokens. Default 5000.
     *     @type int          $timeout     HTTP request timeout in seconds. Default 60.
     * }
     * @return array|WP_Error Canonical agent turn or an error.
     */
    public function request_agent_turn( $messages, $tools, $options = array() ) {
        if ( ! is_array( $messages ) || ! is_array( $tools ) || ! is_array( $options ) ) {
            return new WP_Error(
                'listeo_ai_agent_invalid_request',
                __( 'Invalid agent request data.', 'ai-chat-search' )
            );
        }

        $capabilities = $this->get_agent_capabilities();
        if ( ! empty( $tools ) && ! $capabilities['tools'] ) {
            return new WP_Error(
                'listeo_ai_agent_tools_unsupported',
                __( 'The selected AI provider does not support agent tools.', 'ai-chat-search' )
            );
        }

        $tool_choice = array_key_exists( 'tool_choice', $options )
            ? $options['tool_choice']
            : 'auto';

        if ( ! empty( $options['require_tool'] ) && ! empty( $tools ) ) {
            // All supported chat transports use their OpenAI-compatible
            // function-calling endpoint, where forcing any tool is "required".
            $tool_choice = 'required';
        } elseif ( is_array( $tool_choice ) && ! $capabilities['forced'] ) {
            $tool_choice = 'auto';
        }

        $payload = $this->prepare_chat_payload( $messages, $tools, $tool_choice );
        if ( ! empty( $tools ) && ! empty( $options['parallel'] ) && $capabilities['parallel'] ) {
            $payload['parallel_tool_calls'] = true;
        }

        $max_tokens = isset( $options['max_tokens'] ) ? (int) $options['max_tokens'] : 5000;
        if ( $max_tokens <= 0 ) {
            $max_tokens = 5000;
        }

        $normalization_options = array(
            'max_tokens' => $max_tokens,
        );
        if ( array_key_exists( 'temperature', $options ) ) {
            $normalization_options['temperature'] = $options['temperature'];
        }
        if ( array_key_exists( 'reasoning', $options ) ) {
            $normalization_options['reasoning'] = $options['reasoning'];
        }

        $payload = $this->normalize_chat_payload( $payload, $normalization_options );
        $timeout = isset( $options['timeout'] ) ? (int) $options['timeout'] : 60;
        if ( $timeout <= 0 ) {
            $timeout = 60;
        }

        $response = $this->request_chat( $payload, $timeout, true );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = (int) wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $response_data = json_decode( $response_body, true );

        $choice_error = is_array( $response_data )
            && isset( $response_data['choices'][0]['error'] )
            ? $response_data['choices'][0]['error']
            : null;
        $finish_error = is_array( $response_data )
            && isset( $response_data['choices'][0]['finish_reason'] )
            && $response_data['choices'][0]['finish_reason'] === 'error';
        $top_level_error = is_array( $response_data ) && ! empty( $response_data['error'] )
            ? $response_data['error']
            : null;

        if ( $response_code !== 200 || $top_level_error || $choice_error || $finish_error ) {
            $error_message = '';
            foreach ( array( $top_level_error, $choice_error ) as $provider_error ) {
                if ( is_array( $provider_error ) && isset( $provider_error['message'] ) && is_string( $provider_error['message'] ) ) {
                    $error_message = $provider_error['message'];
                    break;
                }
                if ( is_string( $provider_error ) && $provider_error !== '' ) {
                    $error_message = $provider_error;
                    break;
                }
            }
            if ( $error_message === '' ) {
                $error_message = __( 'The AI provider returned an error.', 'ai-chat-search' );
            }

            return new WP_Error(
                'listeo_ai_agent_provider_error',
                $error_message,
                array( 'status' => $response_code )
            );
        }

        if (
            ! is_array( $response_data )
            || empty( $response_data['choices'] )
            || ! isset( $response_data['choices'][0]['message'] )
            || ! is_array( $response_data['choices'][0]['message'] )
        ) {
            return new WP_Error(
                'listeo_ai_agent_invalid_response',
                __( 'The AI provider returned an invalid response.', 'ai-chat-search' )
            );
        }

        return $this->normalize_agent_turn( $response_data );
    }

    /**
     * Normalize a Chat Completions-compatible response into one agent turn.
     *
     * @param array $response_data Decoded provider response.
     * @return array Canonical agent turn.
     */
    private function normalize_agent_turn( array $response_data ) {
        $choice  = $response_data['choices'][0];
        $message = $choice['message'];
        $text    = $this->normalize_agent_message_text(
            isset( $message['content'] ) ? $message['content'] : ''
        );

        $canonical_calls = array();
        $replay_calls    = array();
        $raw_calls       = isset( $message['tool_calls'] ) && is_array( $message['tool_calls'] )
            ? $message['tool_calls']
            : array();
        $response_seed   = isset( $response_data['id'] ) && is_string( $response_data['id'] )
            ? $response_data['id']
            : wp_json_encode( $message );

        foreach ( $raw_calls as $index => $raw_call ) {
            if ( ! is_array( $raw_call ) ) {
                $raw_call = array();
            }

            $function = isset( $raw_call['function'] ) && is_array( $raw_call['function'] )
                ? $raw_call['function']
                : array();
            $name = isset( $function['name'] ) && is_string( $function['name'] )
                ? $function['name']
                : '';
            $id = isset( $raw_call['id'] ) && is_string( $raw_call['id'] )
                ? trim( $raw_call['id'] )
                : '';
            if ( $id === '' ) {
                $id = 'call_agent_' . substr( hash( 'sha256', $response_seed . ':' . $index ), 0, 16 );
            }

            $normalized_arguments = $this->normalize_agent_tool_arguments(
                array_key_exists( 'arguments', $function ) ? $function['arguments'] : null
            );

            $canonical_calls[] = array(
                'id'              => $id,
                'name'            => $name,
                'arguments'       => $normalized_arguments['arguments'],
                'arguments_valid' => $normalized_arguments['valid'],
                'arguments_raw'   => $normalized_arguments['raw'],
            );
            $replay_call = array(
                'id'       => $id,
                'type'     => 'function',
                'function' => array(
                    'name'      => $name,
                    'arguments' => $normalized_arguments['raw'],
                ),
            );
            if (
                isset( $raw_call['extra_content']['google']['thought_signature'] )
                && is_string( $raw_call['extra_content']['google']['thought_signature'] )
                && strlen( $raw_call['extra_content']['google']['thought_signature'] ) <= 65536
            ) {
                // Gemini 3 (including Gemini routed through OpenRouter) requires
                // the exact thought signature on every replayed function call.
                $replay_call['extra_content'] = array(
                    'google' => array(
                        'thought_signature' => $raw_call['extra_content']['google']['thought_signature'],
                    ),
                );
            }
            $replay_calls[] = $replay_call;
        }

        $replay_message = array(
            'role'    => 'assistant',
            'content' => $text !== '' ? $text : null,
        );
        if ( ! empty( $replay_calls ) ) {
            $replay_message['tool_calls'] = $replay_calls;
        }
        if ( ! empty( $message['responses_reasoning'] ) ) {
            $reasoning_items = $this->normalize_responses_reasoning_items( $message['responses_reasoning'] );
            if ( ! empty( $reasoning_items ) ) {
                $replay_message['responses_reasoning'] = $reasoning_items;
            }
        }
        if ( ! empty( $message['responses_output_items'] ) ) {
            $output_items = $this->normalize_responses_output_items( $message['responses_output_items'] );
            if ( ! empty( $output_items ) ) {
                $replay_message['responses_output_items'] = $output_items;
            }
        }
        if ( isset( $message['reasoning_details'] ) && is_array( $message['reasoning_details'] ) ) {
            $reasoning_details = $this->normalize_openrouter_reasoning_details( $message['reasoning_details'] );
            if ( ! empty( $reasoning_details ) ) {
                $replay_message['reasoning_details'] = $reasoning_details;
            }
        }
        foreach ( array( 'reasoning', 'reasoning_content' ) as $reasoning_field ) {
            if (
                isset( $message[ $reasoning_field ] )
                && is_string( $message[ $reasoning_field ] )
                && strlen( $message[ $reasoning_field ] ) <= 262144
            ) {
                $replay_message[ $reasoning_field ] = $message[ $reasoning_field ];
            }
        }

        return array(
            'type'           => ! empty( $canonical_calls ) ? 'tool_calls' : 'final',
            'text'           => $text,
            'tool_calls'     => $canonical_calls,
            'replay_message' => $replay_message,
            'usage'          => isset( $response_data['usage'] ) && is_array( $response_data['usage'] )
                ? $response_data['usage']
                : array(),
            'finish_reason'  => isset( $choice['finish_reason'] ) ? $choice['finish_reason'] : null,
        );
    }

    /**
     * Normalize an assistant message's text content.
     *
     * @param mixed $content Provider message content.
     * @return string Plain assistant text.
     */
    private function normalize_agent_message_text( $content ) {
        if ( is_string( $content ) ) {
            return $content;
        }
        if ( ! is_array( $content ) ) {
            return '';
        }

        $text = '';
        foreach ( $content as $part ) {
            if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    /**
     * Normalize provider tool arguments without repairing malformed input.
     *
     * @param mixed $raw_arguments Provider function arguments.
     * @return array {
     *     @type array  $arguments Decoded argument object.
     *     @type bool   $valid     Whether arguments were a valid object.
     *     @type string $raw       JSON representation used for replay.
     * }
     */
    private function normalize_agent_tool_arguments( $raw_arguments ) {
        if ( is_object( $raw_arguments ) ) {
            $encoded = wp_json_encode( $raw_arguments );
            return array(
                'arguments' => (array) $raw_arguments,
                'valid'     => is_string( $encoded ),
                'raw'       => is_string( $encoded ) ? $encoded : '',
            );
        }

        if ( is_array( $raw_arguments ) ) {
            $encoded = wp_json_encode( $raw_arguments );
            $is_list = ! empty( $raw_arguments )
                && array_keys( $raw_arguments ) === range( 0, count( $raw_arguments ) - 1 );
            return array(
                'arguments' => $is_list ? array() : $raw_arguments,
                'valid'     => ! $is_list && is_string( $encoded ),
                'raw'       => empty( $raw_arguments ) ? '{}' : ( is_string( $encoded ) ? $encoded : '' ),
            );
        }

        if ( ! is_string( $raw_arguments ) || trim( $raw_arguments ) === '' ) {
            return array(
                'arguments' => array(),
                'valid'     => false,
                'raw'       => is_string( $raw_arguments ) ? $raw_arguments : '',
            );
        }

        $decoded = json_decode( $raw_arguments, true );
        if (
            json_last_error() !== JSON_ERROR_NONE
            || ! is_array( $decoded )
            || strpos( ltrim( $raw_arguments ), '{' ) !== 0
        ) {
            return array(
                'arguments' => array(),
                'valid'     => false,
                'raw'       => $raw_arguments,
            );
        }

        return array(
            'arguments' => $decoded,
            'valid'     => true,
            'raw'       => $raw_arguments,
        );
    }

    /**
     * Convert the plugin's Chat Completions payload to a Responses API payload.
     *
     * @param array $payload Chat Completions payload.
     * @return array Responses API payload.
     */
    private function convert_chat_payload_to_responses( array $payload ) {
        $responses_payload = array(
            'model' => $payload['model'],
            'input' => array(),
            'store' => false,
        );
        $instructions = array();

        foreach ( $payload['messages'] as $message ) {
            $role    = isset( $message['role'] ) ? $message['role'] : '';
            $content = isset( $message['content'] ) ? $message['content'] : '';

            if ( $role === 'system' ) {
                if ( is_string( $content ) && $content !== '' ) {
                    $instructions[] = $content;
                }
                continue;
            }

            if ( $role === 'tool' && ! empty( $message['tool_call_id'] ) ) {
                $responses_payload['input'][] = array(
                    'type'    => 'function_call_output',
                    'call_id' => $message['tool_call_id'],
                    'output'  => is_string( $content ) ? $content : wp_json_encode( $content ),
                );
                continue;
            }

            if ( $role === 'assistant' && ! empty( $message['responses_output_items'] ) ) {
                $output_items = $this->normalize_responses_output_items( $message['responses_output_items'] );
                foreach ( $output_items as $output_item ) {
                    $responses_payload['input'][] = $output_item;
                }
                if ( ! empty( $output_items ) ) {
                    // These are the original Responses output items in their
                    // original order, so do not reconstruct duplicate items.
                    continue;
                }
            }

            if ( in_array( $role, array( 'user', 'assistant' ), true ) && $content !== '' && $content !== null ) {
                $responses_payload['input'][] = array(
                    'role'    => $role,
                    'content' => $this->convert_message_content_to_responses( $content, $role ),
                );
            }

            if (
                $role === 'assistant' &&
                isset( $payload['reasoning_effort'] ) &&
                $payload['reasoning_effort'] !== 'none' &&
                ! empty( $message['responses_reasoning'] )
            ) {
                foreach ( $this->normalize_responses_reasoning_items( $message['responses_reasoning'] ) as $reasoning_item ) {
                    $responses_payload['input'][] = $reasoning_item;
                }
            }

            if ( $role === 'assistant' && ! empty( $message['tool_calls'] ) ) {
                foreach ( $message['tool_calls'] as $tool_call ) {
                    if ( empty( $tool_call['id'] ) || empty( $tool_call['function']['name'] ) ) {
                        continue;
                    }
                    $responses_payload['input'][] = array(
                        'type'      => 'function_call',
                        'call_id'   => $tool_call['id'],
                        'name'      => $tool_call['function']['name'],
                        'arguments' => isset( $tool_call['function']['arguments'] ) ? $tool_call['function']['arguments'] : '{}',
                    );
                }
            }
        }

        if ( ! empty( $instructions ) ) {
            $responses_payload['instructions'] = implode( "\n\n", $instructions );
        }

        if ( ! empty( $payload['tools'] ) ) {
            $responses_payload['tools'] = array();
            foreach ( $payload['tools'] as $tool ) {
                if ( empty( $tool['function']['name'] ) ) {
                    continue;
                }
                $function = $tool['function'];
                $responses_payload['tools'][] = array(
                    'type'        => 'function',
                    'name'        => $function['name'],
                    'description' => isset( $function['description'] ) ? $function['description'] : '',
                    'parameters'  => isset( $function['parameters'] ) ? $function['parameters'] : array( 'type' => 'object' ),
                    'strict'      => false,
                );
            }
        }

        if ( isset( $payload['tool_choice'] ) ) {
            $tool_choice = $payload['tool_choice'];
            if ( is_array( $tool_choice ) && ! empty( $tool_choice['function']['name'] ) ) {
                $tool_choice = array(
                    'type' => 'function',
                    'name' => $tool_choice['function']['name'],
                );
            }
            $responses_payload['tool_choice'] = $tool_choice;
        }

        if ( isset( $payload['parallel_tool_calls'] ) ) {
            $responses_payload['parallel_tool_calls'] = (bool) $payload['parallel_tool_calls'];
        }
        if ( isset( $payload['max_completion_tokens'] ) ) {
            $responses_payload['max_output_tokens'] = (int) $payload['max_completion_tokens'];
        }
        if ( isset( $payload['reasoning_effort'] ) ) {
            $responses_payload['reasoning'] = array( 'effort' => $payload['reasoning_effort'] );
            if ( $payload['reasoning_effort'] !== 'none' ) {
                $responses_payload['include'] = array( 'reasoning.encrypted_content' );
            }
        }
        if ( isset( $payload['service_tier'] ) ) {
            $responses_payload['service_tier'] = $payload['service_tier'];
        }
        if ( isset( $payload['response_format'] ) ) {
            $responses_payload['text'] = array( 'format' => $payload['response_format'] );
        }

        return $responses_payload;
    }

    /**
     * Convert multimodal Chat Completions content to Responses content parts.
     *
     * @param string|array $content Message content.
     * @param string       $role Message role.
     * @return string|array Responses message content.
     */
    private function convert_message_content_to_responses( $content, $role ) {
        if ( ! is_array( $content ) ) {
            return $content;
        }

        $converted = array();
        foreach ( $content as $part ) {
            if ( ! is_array( $part ) || empty( $part['type'] ) ) {
                continue;
            }
            if ( $part['type'] === 'text' && isset( $part['text'] ) ) {
                $converted[] = array(
                    'type' => $role === 'assistant' ? 'output_text' : 'input_text',
                    'text' => $part['text'],
                );
            } elseif ( $part['type'] === 'image_url' && isset( $part['image_url']['url'] ) ) {
                $image = array(
                    'type'      => 'input_image',
                    'image_url' => $part['image_url']['url'],
                );
                if ( isset( $part['image_url']['detail'] ) ) {
                    $image['detail'] = $part['image_url']['detail'];
                }
                $converted[] = $image;
            }
        }

        return $converted;
    }

    /**
     * Validate reasoning items before replaying them to the Responses API.
     *
     * @param mixed $items Candidate Responses reasoning items.
     * @return array Validated reasoning items.
     */
    public function normalize_responses_reasoning_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized          = array();
        $remaining_text      = 32768;
        $remaining_encrypted = 2097152;
        foreach ( $items as $item ) {
            if ( count( $normalized ) >= 8 ) {
                break;
            }
            if ( ! is_array( $item ) || ! isset( $item['type'] ) || $item['type'] !== 'reasoning' ) {
                continue;
            }
            if ( empty( $item['id'] ) || ! is_string( $item['id'] ) || strlen( $item['id'] ) > 200 ) {
                continue;
            }

            $item_id = sanitize_text_field( $item['id'] );
            if ( $item_id === '' ) {
                continue;
            }

            $reasoning_item = array(
                'type' => 'reasoning',
                'id'   => $item_id,
            );

            foreach ( array( 'summary' => 'summary_text', 'content' => 'reasoning_text' ) as $field => $part_type ) {
                if ( ! isset( $item[ $field ] ) || ! is_array( $item[ $field ] ) ) {
                    continue;
                }
                $reasoning_item[ $field ] = array();
                foreach ( $item[ $field ] as $part ) {
                    if ( $remaining_text <= 0 ) {
                        break;
                    }
                    if ( ! is_array( $part ) || ! isset( $part['type'], $part['text'] ) || $part['type'] !== $part_type || ! is_string( $part['text'] ) ) {
                        continue;
                    }
                    $text = substr( $part['text'], 0, $remaining_text );
                    $reasoning_item[ $field ][] = array(
                        'type' => $part_type,
                        'text' => sanitize_textarea_field( $text ),
                    );
                    $remaining_text -= strlen( $text );
                    if ( $remaining_text <= 0 ) {
                        break;
                    }
                }
            }

            if ( isset( $item['encrypted_content'] ) && is_string( $item['encrypted_content'] ) ) {
                $encrypted_length = strlen( $item['encrypted_content'] );
                if ( $encrypted_length <= $remaining_encrypted ) {
                    $reasoning_item['encrypted_content'] = $item['encrypted_content'];
                    $remaining_encrypted -= $encrypted_length;
                }
            }
            if ( isset( $item['status'] ) && in_array( $item['status'], array( 'completed', 'in_progress', 'incomplete' ), true ) ) {
                $reasoning_item['status'] = $item['status'];
            }

            $normalized[] = $reasoning_item;
        }

        return $normalized;
    }

    /**
     * Retain bounded native Responses output items for stateless replay.
     *
     * GPT-5.6 with store:false requires the original response output items,
     * including encrypted reasoning and function-call metadata, in their
     * original order on the next request.
     *
     * @param mixed $items Candidate Responses output items.
     * @return array Bounded output items.
     */
    private function normalize_responses_output_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();
        $remaining_bytes = 4 * 1024 * 1024;
        foreach ( $items as $item ) {
            if ( count( $normalized ) >= 32 || $remaining_bytes <= 0 ) {
                break;
            }
            if (
                ! is_array( $item )
                || empty( $item['type'] )
                || ! is_string( $item['type'] )
                || ! in_array( $item['type'], array( 'reasoning', 'function_call', 'message' ), true )
            ) {
                continue;
            }

            $encoded = wp_json_encode( $item );
            if ( ! is_string( $encoded ) ) {
                continue;
            }
            $item_bytes = strlen( $encoded );
            if ( $item_bytes > $remaining_bytes ) {
                continue;
            }

            $normalized[] = $item;
            $remaining_bytes -= $item_bytes;
        }

        return $normalized;
    }

    /**
     * Retain bounded OpenRouter reasoning detail blocks for exact replay.
     *
     * @param mixed $items Candidate reasoning details.
     * @return array Bounded reasoning details.
     */
    private function normalize_openrouter_reasoning_details( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        $normalized = array();
        $remaining_bytes = 2 * 1024 * 1024;
        foreach ( $items as $item ) {
            if ( count( $normalized ) >= 32 || $remaining_bytes <= 0 || ! is_array( $item ) ) {
                break;
            }
            $encoded = wp_json_encode( $item );
            if ( ! is_string( $encoded ) || strlen( $encoded ) > $remaining_bytes ) {
                continue;
            }
            $normalized[] = $item;
            $remaining_bytes -= strlen( $encoded );
        }

        return $normalized;
    }

    /**
     * Convert a Responses API result to the existing Chat Completions shape.
     *
     * @param array $response Responses API response.
     * @param bool  $preserve_agent_state Include raw output items for private replay.
     * @return array Chat Completions-compatible response.
     */
    private function convert_responses_to_chat_response( array $response, $preserve_agent_state = false ) {
        $content         = '';
        $tool_calls      = array();
        $reasoning_items = array();

        foreach ( isset( $response['output'] ) ? $response['output'] : array() as $item ) {
            if ( isset( $item['type'] ) && $item['type'] === 'reasoning' ) {
                $reasoning_items[] = $item;
                continue;
            }
            if ( isset( $item['type'] ) && $item['type'] === 'function_call' ) {
                $tool_calls[] = array(
                    'id'       => ! empty( $item['call_id'] ) ? $item['call_id'] : ( isset( $item['id'] ) ? $item['id'] : '' ),
                    'type'     => 'function',
                    'function' => array(
                        'name'      => $item['name'],
                        'arguments' => isset( $item['arguments'] ) ? $item['arguments'] : '{}',
                    ),
                );
                continue;
            }

            if ( ! isset( $item['type'] ) || $item['type'] !== 'message' || empty( $item['content'] ) ) {
                continue;
            }
            foreach ( $item['content'] as $part ) {
                if ( isset( $part['type'], $part['text'] ) && $part['type'] === 'output_text' ) {
                    $content .= $part['text'];
                } elseif ( isset( $part['type'], $part['refusal'] ) && $part['type'] === 'refusal' ) {
                    $content .= $part['refusal'];
                }
            }
        }

        $message = array(
            'role'    => 'assistant',
            'content' => $content !== '' ? $content : null,
        );
        if ( ! empty( $tool_calls ) ) {
            $message['tool_calls'] = $tool_calls;
            $reasoning_items = $this->normalize_responses_reasoning_items( $reasoning_items );
            if ( ! empty( $reasoning_items ) ) {
                $message['responses_reasoning'] = $reasoning_items;
            }
            if ( $preserve_agent_state ) {
                $output_items = $this->normalize_responses_output_items(
                    isset( $response['output'] ) ? $response['output'] : array()
                );
                if ( ! empty( $output_items ) ) {
                    $message['responses_output_items'] = $output_items;
                }
            }
        }

        $usage = isset( $response['usage'] ) ? $response['usage'] : array();
        return array(
            'id'      => isset( $response['id'] ) ? $response['id'] : '',
            'object'  => 'chat.completion',
            'created' => isset( $response['created_at'] ) ? (int) $response['created_at'] : time(),
            'model'   => isset( $response['model'] ) ? $response['model'] : '',
            'choices' => array(array(
                'index'         => 0,
                'message'       => $message,
                'finish_reason' => ! empty( $tool_calls ) ? 'tool_calls' : ( isset( $response['status'] ) && $response['status'] === 'incomplete' ? 'length' : 'stop' ),
            )),
            'usage'   => array(
                'prompt_tokens'     => isset( $usage['input_tokens'] ) ? $usage['input_tokens'] : 0,
                'completion_tokens' => isset( $usage['output_tokens'] ) ? $usage['output_tokens'] : 0,
                'total_tokens'      => isset( $usage['total_tokens'] ) ? $usage['total_tokens'] : 0,
            ),
        );
    }

    /**
     * Parse embedding response
     *
     * @param array $response_data Decoded JSON response
     * @return array|false Embedding array or false on failure
     */
    public function parse_embedding_response($response_data) {
        // Both OpenAI and Gemini (in compatibility mode) use the same response format
        return $response_data['data'][0]['embedding'] ?? false;
    }

    /**
     * Parse chat response
     *
     * @param array $response_data Decoded JSON response
     * @return array|false Response data or false on failure
     */
    public function parse_chat_response($response_data) {
        // Both providers use the same response format in compatibility mode
        return $response_data;
    }

    /**
     * Get provider display name
     *
     * @return string
     */
    public function get_provider_name() {
        if ($this->is_no_api_key_provider()) {
            return 'Purio Cloud';
        } elseif ($this->get_provider() === 'gemini') {
            return 'Google Gemini';
        } elseif ($this->get_provider() === 'mistral') {
            return 'Mistral AI';
        } elseif ($this->get_provider() === 'openrouter') {
            return 'OpenRouter';
        } else {
            return 'OpenAI';
        }
    }

    /**
     * Validate API key format
     *
     * @param string $api_key API key to validate
     * @return bool True if format appears valid
     */
    public function validate_api_key_format($api_key = null) {
        $key = $api_key ?: $this->get_api_key();

        if (empty($key)) {
            return false;
        }

        if ($this->is_no_api_key_provider()) {
            if ($this->is_signed_managed_gateway()) {
                $context = $this->get_managed_gateway_auth_context();
                return !empty($context['token']) && hash_equals((string) $context['token'], (string) $key);
            }
            return $key === $this->get_no_api_key_domain();
        } elseif ($this->get_provider() === 'gemini') {
            // Gemini keys start with AIzaSy
            return strpos($key, 'AIzaSy') === 0;
        } elseif ($this->get_provider() === 'mistral') {
            // Mistral keys are alphanumeric strings (no standard prefix)
            return strlen($key) >= 32;
        } elseif ($this->get_provider() === 'openrouter') {
            // OpenRouter keys start with sk-or-
            return strpos($key, 'sk-or-') === 0;
        } else {
            // OpenAI keys start with sk- but NOT sk-or- (that's OpenRouter)
            return strpos($key, 'sk-') === 0 && strpos($key, 'sk-or-') !== 0;
        }
    }

    /**
     * Get embedding dimensions for current provider
     *
     * @return int Number of dimensions
     */
    public function get_embedding_dimensions() {
        $parsed = $this->parse_embedding_option();
        if ($parsed['dimensions'] !== null && $parsed['dimensions'] > 0) {
            return $parsed['dimensions'];
        }

        $model = $this->get_embedding_model();

        // Known defaults for specific models
        if ($model === 'mistral-embed') {
            return 1024;
        }

        if ($model === 'gemini-embedding-001') {
            return 1536;
        }

        if ($model === 'text-embedding-3-small' || $model === 'openai/text-embedding-3-small') {
            return 1536;
        }

        if ($model === 'text-embedding-3-large' || $model === 'openai/text-embedding-3-large') {
            return 1536;
        }

        if ($model === 'google/gemini-embedding-2-preview') {
            return 1536;
        }

        // Fallback
        return 1536;
    }

    /**
     * Check if current provider supports vision/image input
     *
     * @return bool True if vision is supported
     */
    public function supports_vision() {
        return true;
    }

    /**
     * Check if current provider supports speech-to-text transcription
     *
     * @return bool True if transcription is supported
     */
    public function supports_transcription() {
        return in_array($this->get_provider(), array('openai', 'mistral', 'openrouter'), true);
    }

    /**
     * Get transcription API endpoint URL
     *
     * @return string Endpoint URL or empty string if not supported
     */
    public function get_transcription_endpoint() {
        if ($this->get_provider() === 'mistral') {
            return 'https://api.mistral.ai/v1/audio/transcriptions';
        } elseif ($this->get_provider() === 'openai') {
            return 'https://api.openai.com/v1/audio/transcriptions';
        }
        return '';
    }

    /**
     * Get transcription model name
     *
     * @return string Model name or empty string if not supported
     */
    public function get_transcription_model() {
        if ($this->get_provider() === 'mistral') {
            return 'voxtral-mini-latest';
        } elseif ($this->get_provider() === 'openai') {
            return 'whisper-1';
        }
        return '';
    }

    /**
     * Get HTTP headers for transcription/audio API requests
     *
     * @return array Headers array for audio transcription requests
     */
    public function get_transcription_headers() {
        if ($this->get_provider() === 'mistral') {
            return array(
                'x-api-key' => $this->get_api_key(),
            );
        } else {
            return array(
                'Authorization' => 'Bearer ' . $this->get_api_key(),
            );
        }
    }

    /**
     * Format image_url content for the current provider
     *
     * @param string $url The image URL (data: URI or https:// URL)
     * @param string $detail Detail level for OpenAI ('auto', 'low', 'high')
     * @return array Formatted image_url content block
     */
    public function format_image_content($url, $detail = 'auto') {
        if ($this->get_provider() === 'mistral') {
            return array(
                'type' => 'image_url',
                'image_url' => $url,
            );
        } else {
            return array(
                'type' => 'image_url',
                'image_url' => array(
                    'url' => $url,
                    'detail' => $detail,
                ),
            );
        }
    }
}
