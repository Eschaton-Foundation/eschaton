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
     * Current provider
     *
     * @var string 'openai', 'gemini', or 'mistral'
     */
    private $provider;

    /**
     * API key for the selected provider
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     *
     * @param string $provider Optional provider override (defaults to settings)
     * @param string $api_key Optional API key override (defaults to settings)
     */
    public function __construct($provider = null, $api_key = null) {
        $this->provider = $provider ?: get_option('listeo_ai_search_provider', 'openai');

        if ($api_key) {
            $this->api_key = $api_key;
        } else {
            // Get API key based on provider
            if ($this->provider === 'gemini') {
                $this->api_key = get_option('listeo_ai_search_gemini_api_key', '');
            } elseif ($this->provider === 'mistral') {
                $this->api_key = get_option('listeo_ai_search_mistral_api_key', '');
            } else {
                $this->api_key = get_option('listeo_ai_search_api_key', '');
            }
        }
    }

    /**
     * Get current provider
     *
     * @return string 'openai' or 'gemini'
     */
    public function get_provider() {
        return $this->provider;
    }

    /**
     * Get API key for current provider
     *
     * @return string
     */
    public function get_api_key() {
        return $this->api_key;
    }

    /**
     * Get API endpoint URL
     *
     * @param string $type 'embeddings' or 'chat'
     * @return string Full API endpoint URL
     */
    public function get_endpoint($type = 'embeddings') {
        if ($this->provider === 'gemini') {
            // Use OpenAI compatibility mode for Gemini
            // Base URL: https://generativelanguage.googleapis.com/v1beta/openai/
            if ($type === 'embeddings') {
                return 'https://generativelanguage.googleapis.com/v1beta/openai/embeddings';
            } elseif ($type === 'chat') {
                return 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
            }
        } elseif ($this->provider === 'mistral') {
            // Mistral uses OpenAI-compatible API format
            // Base URL: https://api.mistral.ai/v1
            if ($type === 'embeddings') {
                return 'https://api.mistral.ai/v1/embeddings';
            } elseif ($type === 'chat') {
                return 'https://api.mistral.ai/v1/chat/completions';
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
        if ($this->provider === 'gemini') {
            // Gemini uses the same Authorization header format in compatibility mode
            return array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            );
        } else {
            // OpenAI headers
            return array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            );
        }
    }

    /**
     * Get embedding model name
     *
     * @return string Model name
     */
    public function get_embedding_model() {
        if ($this->provider === 'gemini') {
            return 'gemini-embedding-001';
        } elseif ($this->provider === 'mistral') {
            return 'mistral-embed';
        } else {
            return 'text-embedding-3-small';
        }
    }

    /**
     * Get chat/completion model name
     *
     * @return string Model name
     */
    public function get_chat_model() {
        if ($this->provider === 'gemini') {
            return get_option('listeo_ai_chat_model', 'gemini-3-flash-preview');
        } elseif ($this->provider === 'mistral') {
            return get_option('listeo_ai_chat_model', 'mistral-large-latest');
        } else {
            return get_option('listeo_ai_chat_model', 'gpt-5.1');
        }
    }

    /**
     * Prepare embedding request payload
     *
     * @param string|array $input Text to embed (single string or array of strings)
     * @return array Request payload
     */
    public function prepare_embedding_payload($input) {
        if ($this->provider === 'gemini') {
            // Gemini in OpenAI compatibility mode uses the same format
            // But we explicitly set dimensions to 1536 for compatibility
            return array(
                'model' => $this->get_embedding_model(),
                'input' => $input,
                'dimensions' => 1536
            );
        } elseif ($this->provider === 'mistral') {
            // Mistral embed produces 1024 dimensions (fixed, not configurable)
            return array(
                'model' => $this->get_embedding_model(),
                'input' => $input,
            );
        } else {
            // OpenAI format
            return array(
                'model' => $this->get_embedding_model(),
                'input' => $input,
            );
        }
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

            // Only include tool_choice if tools are present
            if ($tool_choice) {
                $payload['tool_choice'] = $tool_choice;
            }
        }

        // Add thinking configuration for Gemini 3 models
        // Uses OpenAI compatibility mode with reasoning_effort parameter
        // Valid values: high, low, medium, none
        // Gemini 3 Pro: "low" for reduced latency
        // Gemini 3 Flash: "low" for minimal thinking (fastest)
        if (strpos($model, 'gemini-3-pro') !== false) {
            $payload['reasoning_effort'] = 'low';
        } elseif (strpos($model, 'gemini-3-flash') !== false) {
            $payload['reasoning_effort'] = 'low';
        }

        return $payload;
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
        if ($this->provider === 'gemini') {
            return 'Google Gemini';
        } elseif ($this->provider === 'mistral') {
            return 'Mistral AI';
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
        $key = $api_key ?: $this->api_key;

        if (empty($key)) {
            return false;
        }

        if ($this->provider === 'gemini') {
            // Gemini keys start with AIzaSy
            return strpos($key, 'AIzaSy') === 0;
        } elseif ($this->provider === 'mistral') {
            // Mistral keys are alphanumeric strings (no standard prefix)
            return strlen($key) >= 32;
        } else {
            // OpenAI keys start with sk-
            return strpos($key, 'sk-') === 0;
        }
    }

    /**
     * Get embedding dimensions for current provider
     *
     * @return int Number of dimensions
     */
    public function get_embedding_dimensions() {
        if ($this->provider === 'mistral') {
            return 1024;
        } else {
            // OpenAI and Gemini both use 1536
            return 1536;
        }
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
        return in_array($this->provider, array('openai', 'mistral'), true);
    }

    /**
     * Get transcription API endpoint URL
     *
     * @return string Endpoint URL or empty string if not supported
     */
    public function get_transcription_endpoint() {
        if ($this->provider === 'mistral') {
            return 'https://api.mistral.ai/v1/audio/transcriptions';
        } elseif ($this->provider === 'openai') {
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
        if ($this->provider === 'mistral') {
            return 'voxtral-mini-latest';
        } elseif ($this->provider === 'openai') {
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
        if ($this->provider === 'mistral') {
            return array(
                'x-api-key' => $this->api_key,
            );
        } else {
            return array(
                'Authorization' => 'Bearer ' . $this->api_key,
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
        if ($this->provider === 'mistral') {
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
