<?php
/**
 * AI Search Engine Class
 * 
 * Handles all AI-powered search functionality including embeddings, filtering, and ranking
 * 
 * PERFORMANCE IMPROVEMENTS (v1.0.6):
 * - Structured embeddings with hierarchical information priority
 * - Reduced AI filtering usage (only for ultra-specific queries)  
 * - Optimized similarity thresholds for structured data
 * - ~66% reduction in OpenAI API calls per search
 * - ~60-70% faster search response times
 * 
 * @package Listeo_AI_Search
 * @since 1.0.5
 * @version 1.0.6
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Listeo_AI_Search_AI_Engine {

    /**
     * Embedding manager instance
     *
     * @var Listeo_AI_Search_Embedding_Manager
     */
    private $embedding_manager;

    /**
     * AI Provider instance
     *
     * @var Listeo_AI_Provider
     */
    private $provider;

    /**
     * API key (for backward compatibility)
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     *
     * @param string $api_key Optional API key (deprecated - use provider settings instead)
     */
    public function __construct($api_key = '') {
        // IMPORTANT: Don't assume OpenAI when API key is provided
        // Always use configured provider from settings for consistency
        $this->provider = new Listeo_AI_Provider();
        $this->api_key = $this->provider->get_api_key();

        // Backward compatibility: warn if deprecated parameter is used
        if ($api_key && get_option('listeo_ai_search_debug_mode', false)) {
            Listeo_AI_Search_Utility_Helper::debug_log(
                'AI_Engine: API key passed to constructor is deprecated. Using provider settings instead.',
                'warning'
            );
        }

        $this->embedding_manager = new Listeo_AI_Search_Embedding_Manager();
    }
    
    /**
     * Test API connection health (deprecated - kept for backward compatibility)
     *
     * @deprecated No longer used internally. Embedding generation will fail fast if API is down.
     * @return bool True if API key is configured
     */
    public function test_api_health() {
        // Simplified: just check if API key exists
        // Actual API health is tested when we try to generate embeddings
        return !empty($this->api_key);
    }
    
    /**
     * Perform AI-powered search
     *
     * @param string $query Search query
     * @param int $limit Number of results to return
     * @param int $offset Results offset for pagination
     * @param string $listing_types Comma-separated listing types or 'all'
     * @param bool $debug Enable debug logging
     * @param array $location_filtered_ids Optional pre-filtered listing IDs from SQL location search
     * @param bool $is_rag RAG mode - uses more lenient thresholds for context retrieval
     * @return array Search results
     */
    public function search($query, $limit, $offset, $listing_types, $debug = false, $location_filtered_ids = array(), $is_rag = false) {
        global $wpdb;
        
        $debug_info = array();
        $search_start = microtime(true);

        // Check if API key is configured
        if (empty($this->api_key)) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('No API key configured, falling back to traditional search', 'warning');
            }
            $provider_name = $this->provider->get_provider_name();
            throw new Exception($provider_name . ' API key not configured - using fallback search');
        }

        // Generate embedding for search query
        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('EMBEDDING: Generating embedding for query: "' . $query . '"');
        }

        // Apply query expansion if enabled
        $expanded_query = $this->expand_query_if_enabled($query, $debug);

        // Generate embedding for query
        $query_embedding = $this->embedding_manager->generate_embedding($expanded_query);

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('QUERY ANALYSIS: Original="' . $query . '", Expanded="' . $expanded_query . '"');
        }
        if (!$query_embedding) {
            if ($debug) {
                $debug_info['embedding_generation'] = 'failed';
                $debug_info['error'] = 'Could not generate embedding for query';
                Listeo_AI_Search_Utility_Helper::debug_log('EMBEDDING ERROR: Could not generate embedding for query', 'error');
            }
            $provider_name = $this->provider->get_provider_name();
            throw new Exception($provider_name . ' API failed to generate embedding - using fallback search');
        }
        
        if ($debug) {
            $debug_info['embedding_generation'] = 'success';
            $debug_info['embedding_dimensions'] = count($query_embedding);
            Listeo_AI_Search_Utility_Helper::debug_log('EMBEDDING SUCCESS: Generated embedding with ' . count($query_embedding) . ' dimensions');
        }
        
        // Get embeddings from database
        // LOCATION PRE-FILTER: Use SQL-filtered IDs if provided (from chatbot location parameter)
        $embeddings = Listeo_AI_Search_Database_Manager::get_embeddings_for_search($listing_types, array(), $location_filtered_ids);

        if ($debug) {
            $debug_info['embeddings_found'] = count($embeddings);
            $debug_info['location_pre_filtered'] = !empty($location_filtered_ids);
            $debug_info['pre_filter_count'] = count($location_filtered_ids);

            if (!empty($location_filtered_ids)) {
                Listeo_AI_Search_Utility_Helper::debug_log('DATABASE: Using SQL location pre-filter (' . count($location_filtered_ids) . ' IDs) - Found ' . count($embeddings) . ' embeddings');
            } else {
                Listeo_AI_Search_Utility_Helper::debug_log('DATABASE: Found ' . count($embeddings) . ' embeddings (no location filtering)');
            }
        }

        if (empty($embeddings)) {
            if ($debug) {
                $debug_info['error'] = 'No embeddings found in database';
                Listeo_AI_Search_Utility_Helper::debug_log('DATABASE ERROR: No embeddings found in database', 'error');
            }
            // Return friendly empty response instead of throwing exception
            return array(
                'listings' => array(),
                'total' => 0,
                'query' => $query,
                'search_type' => 'ai_semantic',
                'is_fallback' => false,
                'chunk_mapping' => array(),
                'notice' => __('No data available yet. Please train the AI search data first.', 'ai-chat-search'),
                'notice_type' => 'no_embeddings',
                'debug' => $debug ? $debug_info : null
            );
        }

        // Calculate similarities with admin-configured threshold
        $similarities = array();
        $similarity_scores = array();

        // Determine if searching listings/products (strict) or content (lenient)
        $strict_types = array('listing', 'product');
        $use_strict = false;
        if ($listing_types !== 'all') {
            $types_array = array_map('trim', explode(',', $listing_types));
            foreach ($types_array as $type) {
                if (in_array($type, $strict_types)) {
                    $use_strict = true;
                    break;
                }
            }
        } else {
            // 'all' includes listings, so use strict
            $use_strict = true;
        }

        // Use admin setting for minimum match percentage, convert to raw similarity
        // Pass $is_rag to use more lenient thresholds for RAG context retrieval
        $min_match_percentage = intval(get_option('listeo_ai_search_min_match_percentage', 50));
        $min_similarity_threshold = Listeo_AI_Search_Utility_Helper::percentage_to_similarity($min_match_percentage, $use_strict, null, $is_rag);

        if ($debug) {
            $mode = $use_strict ? 'strict (listings/products)' : ($is_rag ? 'RAG (extra lenient)' : 'lenient (content)');
            Listeo_AI_Search_Utility_Helper::debug_log('THRESHOLD: Using admin setting ' . $min_match_percentage . '% = ' . round($min_similarity_threshold, 3) . ' raw similarity [' . $mode . ']');
        }
        
        $failed_decompressions = 0;
        foreach ($embeddings as $embedding_row) {
            $stored_embedding = Listeo_AI_Search_Database_Manager::decompress_embedding_from_storage($embedding_row->embedding);
            if ($stored_embedding) {
                $similarity = Listeo_AI_Search_Utility_Helper::calculate_cosine_similarity($query_embedding, $stored_embedding);

                // Additional keyword boost for exact matches
                $keyword_boost = $this->calculate_keyword_boost($query, $embedding_row->listing_id);
                $adjusted_similarity = $similarity + $keyword_boost;

                // Only include results above minimum threshold
                if ($adjusted_similarity >= $min_similarity_threshold) {
                    $similarities[$embedding_row->listing_id] = $adjusted_similarity;
                    $similarity_scores[] = round($adjusted_similarity, 4);
                }
            } else {
                // Decompression failed - log details
                $failed_decompressions++;
                if ($debug && $failed_decompressions <= 3) {
                    // Only log first 3 failures to avoid spam
                    Listeo_AI_Search_Utility_Helper::debug_log(
                        sprintf(
                            'DECOMPRESSION FAILED for listing %d - Embedding data length: %d bytes, First 100 chars: %s',
                            $embedding_row->listing_id,
                            strlen($embedding_row->embedding),
                            substr($embedding_row->embedding, 0, 100)
                        ),
                        'error'
                    );
                }
            }
        }

        if ($debug && $failed_decompressions > 0) {
            Listeo_AI_Search_Utility_Helper::debug_log(
                "DECOMPRESSION: Failed to decompress {$failed_decompressions} out of " . count($embeddings) . " embeddings",
                'error'
            );
        }

        // CHUNKING: Map chunk IDs back to parent post IDs
        // This ensures search results show the original post, not the chunk
        // Use extended mapping to preserve chunk IDs for RAG context
        $chunk_result = Listeo_AI_Search_Database_Manager::map_chunks_to_parents($similarities, true);
        $similarities = $chunk_result['similarities'];
        $chunk_mapping = $chunk_result['chunk_mapping'];

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('CHUNKING: Mapped ' . count($similarities) . ' results (chunks consolidated to parents)');
            Listeo_AI_Search_Utility_Helper::debug_log('CHUNKING: ' . count($chunk_mapping) . ' posts have matching chunks');
        }

        if ($debug) {
            $debug_info['similarities_calculated'] = count($similarities);
            $debug_info['min_threshold'] = $min_similarity_threshold;
            $debug_info['filtered_out'] = count($embeddings) - count($similarities);
            
            Listeo_AI_Search_Utility_Helper::debug_log('SIMILARITY: Calculated similarities for ' . count($similarities) . ' listings');
            Listeo_AI_Search_Utility_Helper::debug_log('SIMILARITY: Minimum threshold: ' . $min_similarity_threshold);
            Listeo_AI_Search_Utility_Helper::debug_log('SIMILARITY: Filtered out ' . (count($embeddings) - count($similarities)) . ' results below threshold');
            
            if (!empty($similarity_scores)) {
                $debug_info['similarity_range'] = array(
                    'min' => min($similarity_scores),
                    'max' => max($similarity_scores),
                    'avg' => round(array_sum($similarity_scores) / count($similarity_scores), 4)
                );
                $debug_info['top_5_scores'] = array_slice(array_reverse($similarity_scores), 0, 5);
                
                Listeo_AI_Search_Utility_Helper::debug_log('SIMILARITY RANGE: Min=' . min($similarity_scores) . ', Max=' . max($similarity_scores) . ', Avg=' . round(array_sum($similarity_scores) / count($similarity_scores), 4));
                Listeo_AI_Search_Utility_Helper::debug_log('TOP 5 SCORES: ' . implode(', ', array_slice(array_reverse($similarity_scores), 0, 5)));
            } else {
                $debug_info['similarity_range'] = 'No results above threshold';
                Listeo_AI_Search_Utility_Helper::debug_log('SIMILARITY: No results above threshold');
            }
        }
        
        // Check if we have any relevant results
        if (empty($similarities)) {
            if ($debug) {
                $debug_info['error'] = "No results found above similarity threshold of {$min_similarity_threshold}";
                Listeo_AI_Search_Utility_Helper::debug_log('RESULTS ERROR: No results found above similarity threshold of ' . $min_similarity_threshold, 'warning');
            }
            
            // Return empty results instead of throwing exception
            return array(
                'listings' => array(),
                'total' => 0,
                'query' => $query,
                'search_type' => 'ai_semantic',
                'is_fallback' => false,
                'chunk_mapping' => array(),
                'debug' => $debug ? $debug_info : null
            );
        }
        
        // Sort by similarity and get top results
        arsort($similarities);
        $top_listing_ids = array_keys($similarities);

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('SORTING: Found ' . count($top_listing_ids) . ' results above threshold');
            Listeo_AI_Search_Utility_Helper::debug_log('TOP 10 LISTING IDS: ' . implode(', ', array_slice($top_listing_ids, 0, 10)));
        }

        // Raw similarity threshold filtering already applied above (line 212)
        // No redundant display % filtering - raw threshold is authoritative
        $filtered_listing_ids = $top_listing_ids;

        // Apply pagination
        $paginated_ids = array_slice($filtered_listing_ids, $offset, $limit);
        
        // DEBUG: Log pagination details
        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('MAIN PAGINATION: Total filtered results=' . count($filtered_listing_ids) . ', Offset=' . $offset . ', Limit=' . $limit . ', Paginated=' . count($paginated_ids));
            Listeo_AI_Search_Utility_Helper::debug_log('MAIN PAGINATION: Paginated listing IDs: [' . implode(', ', $paginated_ids) . ']');
        }
        
        // Format results - filtering already applied, so pass false for filtering in formatter
        $formatted_results = Listeo_AI_Search_Result_Formatter::format_search_results($paginated_ids, true, $similarities, false);
        
        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('RESULTS: Using direct semantic search with structured embeddings');
        }
        
        $search_time = round((microtime(true) - $search_start) * 1000, 2);
        
        if ($debug) {
            $debug_info['search_time'] = $search_time . 'ms';
            $debug_info['total_processed'] = count($top_listing_ids);
            $debug_info['returned_results'] = count($formatted_results);
            
            Listeo_AI_Search_Utility_Helper::debug_log('TIMING: Search completed in ' . $search_time . 'ms');
            Listeo_AI_Search_Utility_Helper::debug_log('RESULTS: Total processed=' . count($top_listing_ids) . ', Returned results=' . count($formatted_results));
            
            // Add details about top results for debugging
            $debug_results = array();
            foreach (array_slice($formatted_results, 0, 10) as $result) { // Top 10 for debug
                $debug_results[] = array(
                    'title' => $result['title'],
                    'score' => $result['match_percentage'] ?? $result['similarity_score'],
                    'match_type' => $result['match_type'],
                    'listing_type' => $result['listing_type'],
                    'address' => $result['address']
                );
                Listeo_AI_Search_Utility_Helper::debug_log('TOP RESULT: "' . $result['title'] . '" (Raw: ' . ($result['similarity_score'] ?? 'N/A') . ', Display: ' . ($result['match_percentage'] ?? 'N/A') . '%, Address: ' . $result['address'] . ')');
            }
            $debug_info['top_results'] = $debug_results;
            
            Listeo_AI_Search_Utility_Helper::debug_log('=== LISTEO AI SEARCH DEBUG END ===');
        }
        
        $result = array(
            'listings' => $formatted_results,
            'total_found' => count($filtered_listing_ids),
            'search_type' => 'ai',
            'query' => $query,
            'explanation' => Listeo_AI_Search_Utility_Helper::generate_search_explanation($query, count($filtered_listing_ids)),
            'has_more' => ($offset + $limit) < count($filtered_listing_ids),
            'chunk_mapping' => $chunk_mapping // For RAG: which chunks matched for each parent
        );

        if ($debug) {
            $result['debug'] = $debug_info;
        }

        return $result;
    }
    
    // Note: extract_location_from_query method is defined later in this class

    /**
     * Perform AI-powered search with batch processing for memory efficiency
     *
     * @param string $query Search query
     * @param int $limit Number of results to return
     * @param int $offset Results offset for pagination
     * @param string $listing_types Comma-separated listing types or 'all'
     * @param bool $debug Enable debug logging
     * @param int $batch_size Number of embeddings per batch (default 3000, safe for 256MB PHP)
     * @return array Search results
     */
    public function search_with_batching($query, $limit, $offset, $listing_types, $debug = false, $batch_size = 3000) {
        global $wpdb;
        
        $debug_info = array();
        $search_start = microtime(true);

        // Check if API key is configured
        if (empty($this->api_key)) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI SEARCH (BATCH): No API key configured, falling back to traditional search', 'warning');
            }
            $provider_name = $this->provider->get_provider_name();
            throw new Exception($provider_name . ' API key not configured - using fallback search');
        }

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Starting batch processing for query: "' . $query . '"');
        }
        
        // Generate embedding for search query
        $location_filtering_enabled = get_option('listeo_ai_search_ai_location_filtering_enabled', false);
        
        if ($location_filtering_enabled) {
            $query_analysis = $this->extract_location_from_query($query, $debug);
            $detected_locations = isset($query_analysis['locations']) ? $query_analysis['locations'] : array();
            $has_location_intent = isset($query_analysis['has_location_intent']) ? $query_analysis['has_location_intent'] : false;
        } else {
            $detected_locations = array();
            $has_location_intent = false;
        }
        
        // Apply query expansion if enabled
        $business_query = $query;
        $expanded_query = $this->expand_query_if_enabled($business_query, $debug);
        
        // Generate embedding for business query
        $query_embedding = $this->embedding_manager->generate_embedding($expanded_query);
        
        if (!$query_embedding) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Could not generate embedding for query', 'error');
            }
            $provider_name = $this->provider->get_provider_name();
            throw new Exception($provider_name . ' API failed to generate embedding - using fallback search');
        }
        
        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Generated embedding with ' . count($query_embedding) . ' dimensions');
        }
        
        // BATCH PROCESSING: Get total count first
        $apply_location_filtering = !empty($detected_locations) && $has_location_intent && $location_filtering_enabled;
        $total_embeddings = Listeo_AI_Search_Database_Manager::count_embeddings_for_search($listing_types, $apply_location_filtering ? $detected_locations : array());
        
        if ($debug) {
            $debug_info['total_embeddings'] = $total_embeddings;
            $debug_info['location_filtering'] = $apply_location_filtering;
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Found ' . $total_embeddings . ' total embeddings to process');
        }
        
        if ($total_embeddings === 0) {
            throw new Exception('No embeddings found in database');
        }
        
        // BATCH PROCESSING: Process in chunks to save memory
        // $batch_size is now passed as parameter (auto-detected by caller)
        $similarities = array();
        $processed_count = 0;

        // Determine if searching listings/products (strict) or content (lenient)
        $strict_types = array('listing', 'product');
        $use_strict = false;
        if ($listing_types !== 'all') {
            $types_array = array_map('trim', explode(',', $listing_types));
            foreach ($types_array as $type) {
                if (in_array($type, $strict_types)) {
                    $use_strict = true;
                    break;
                }
            }
        } else {
            $use_strict = true;
        }

        // Use admin setting for minimum match percentage, convert to raw similarity
        $min_match_percentage = intval(get_option('listeo_ai_search_min_match_percentage', 50));
        $min_similarity_threshold = Listeo_AI_Search_Utility_Helper::percentage_to_similarity($min_match_percentage, $use_strict);

        if ($debug) {
            $mode = $use_strict ? 'strict (listings/products)' : 'lenient (content)';
            $debug_info['batch_size'] = $batch_size;
            $debug_info['min_threshold'] = $min_similarity_threshold;
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Using admin setting ' . $min_match_percentage . '% = ' . round($min_similarity_threshold, 3) . ' raw similarity [' . $mode . ']');
        }
        
        // Process embeddings in batches
        $batch_offset = 0;
        while ($batch_offset < $total_embeddings) {
            $batch_embeddings = Listeo_AI_Search_Database_Manager::get_embeddings_batch(
                $listing_types, 
                $apply_location_filtering ? $detected_locations : array(), 
                $batch_size, 
                $batch_offset
            );
            
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Processing batch ' . ($batch_offset / $batch_size + 1) . ' with ' . count($batch_embeddings) . ' embeddings');
            }
            
            // Process current batch
            foreach ($batch_embeddings as $embedding_row) {
                $stored_embedding = Listeo_AI_Search_Database_Manager::decompress_embedding_from_storage($embedding_row->embedding);
                if ($stored_embedding) {
                    $similarity = Listeo_AI_Search_Utility_Helper::calculate_cosine_similarity($query_embedding, $stored_embedding);
                    
                    // Additional keyword boost for exact matches
                    $keyword_boost = $this->calculate_keyword_boost($query, $embedding_row->listing_id);
                    $adjusted_similarity = $similarity + $keyword_boost;
                    
                    // Only include results above minimum threshold
                    if ($adjusted_similarity >= $min_similarity_threshold) {
                        $similarities[$embedding_row->listing_id] = $adjusted_similarity;
                    }
                    
                    $processed_count++;
                }
            }
            
            // Clear batch from memory
            unset($batch_embeddings);
            
            $batch_offset += $batch_size;
            
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Processed ' . $processed_count . ' embeddings, found ' . count($similarities) . ' above threshold');
            }
        }

        // CHUNKING: Map chunk IDs back to parent post IDs
        // This ensures search results show the original post, not the chunk
        // Use extended mapping to preserve chunk IDs for RAG context
        $chunk_result = Listeo_AI_Search_Database_Manager::map_chunks_to_parents($similarities, true);
        $similarities = $chunk_result['similarities'];
        $chunk_mapping = $chunk_result['chunk_mapping'];

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH CHUNKING: Mapped ' . count($similarities) . ' results (chunks consolidated to parents)');
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH CHUNKING: ' . count($chunk_mapping) . ' posts have matching chunks');
        }

        if ($debug) {
            $debug_info['processed_count'] = $processed_count;
            $debug_info['similarities_found'] = count($similarities);
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Completed processing. Total similarities found: ' . count($similarities));
        }

        // Check if we have any relevant results
        if (empty($similarities)) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: No results found above similarity threshold of ' . $min_similarity_threshold, 'warning');
            }
            throw new Exception('No relevant results found');
        }

        // Sort by similarity and get results
        arsort($similarities);
        $top_listing_ids = array_keys($similarities);

        // Apply pagination
        $paginated_ids = array_slice($top_listing_ids, $offset, $limit);
        // Pass $apply_filtering = false - raw threshold filtering already done above
        $formatted_results = Listeo_AI_Search_Result_Formatter::format_search_results($paginated_ids, true, $similarities, false);

        $search_time = round((microtime(true) - $search_start) * 1000, 2);

        if ($debug) {
            $debug_info['search_time'] = $search_time . 'ms';
            $debug_info['total_results'] = count($top_listing_ids);
            $debug_info['returned_results'] = count($formatted_results);
            $final_ids = array_map(function($listing) { return $listing['id']; }, $formatted_results);
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Completed in ' . $search_time . 'ms, returning ' . count($formatted_results) . ' results');
            Listeo_AI_Search_Utility_Helper::debug_log('BATCH SEARCH: Final listing IDs: [' . implode(', ', $final_ids) . ']');
        }

        return array(
            'listings' => $formatted_results,
            'total_found' => count($top_listing_ids),
            'search_type' => 'ai_batch',
            'query' => $query,
            'explanation' => Listeo_AI_Search_Utility_Helper::generate_search_explanation($query, count($top_listing_ids)),
            'has_more' => ($offset + $limit) < count($top_listing_ids),
            'chunk_mapping' => $chunk_mapping, // For RAG: which chunks matched for each parent
            'debug' => $debug ? $debug_info : null
        );
    }

    /**
     * BROKEN METHOD - DO NOT USE
     * Use GPT-4o mini to extract location entities from search query globally
     * 
     * @param string $query Search query
     * @param bool $debug Enable debug logging
     * @return array Detected locations
     */
    private function broken_extract_location_from_query($query, $debug = false) {
        // This method is broken and should not be used
        // Returning empty array to avoid errors
        return array();
    }

    /**
     * Fallback AI filtering using inclusive approach
     * 
     * @param string $query Search query
     * @param array $results Search results to filter
     * @param bool $debug Enable debug logging
     * @param array $detected_locations Detected locations from query
     * @return array Filtered results
     */
    private function ai_filter_results_inclusive($query, $results, $debug = false, $detected_locations = array()) {
        if (empty($this->api_key) || empty($results)) {
            return $results;
        }
        
        // Prepare results summary for AI analysis
        $results_summary = array();
        foreach ($results as $index => $result) {
            $results_summary[] = array(
                'index' => $index,
                'title' => $result['title'],
                'address' => $result['address'],
                'listing_type' => $result['listing_type'],
                'excerpt' => substr(strip_tags($result['excerpt']), 0, 200),
                'match_percentage' => $result['match_percentage'] ?? 0
            );
        }

        // Build location context
        $location_context = "";
        if (!empty($detected_locations)) {
            $location_context = "\nDetected locations from query: " . implode(', ', $detected_locations) . "\n";
        }
        
        $prompt = "User search query: \"$query\"" . $location_context . "

You are analyzing search results from a business directory using INCLUSIVE filtering as a fallback.

Here are the candidates found by semantic search:
" . json_encode($results_summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

Apply INCLUSIVE filtering - keep any business that could reasonably serve the user's general need:

KEEP results that:
1. Are in a business category that could potentially help the user
2. Might offer the service or product the user needs
3. A reasonable person might consider visiting for this request
4. Are broadly related to the user's intent (even if not perfect match)

FILTER OUT only results that are:
- Completely unrelated business types
- Would never serve the user's stated need
- Are clearly in wrong category

Examples:
- For coffee/breakfast search: keep cafés, restaurants, bakeries, hotels with dining
- For pet help: keep vets, grooming, pet stores, training centers
- For fitness: keep gyms, sports centers, yoga studios, trainers

Return ONLY a JSON array of indices for broadly relevant results.

Do not include any explanation, just the JSON array of relevant indices.";

        try {
            // Use lightweight models for result filtering
            $model = ($this->provider->get_provider() === 'gemini') ? 'gemini-2.5-flash' : 'gpt-4o-mini';

            // Gemini needs higher max_tokens in OpenAI compatibility mode
            $max_tokens = ($this->provider->get_provider() === 'gemini') ? 300 : 100;

            $response = wp_remote_post($this->provider->get_endpoint('chat'), array(
                'headers' => $this->provider->get_headers(),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'max_tokens' => $max_tokens,
                    'temperature' => 0.6
                )),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('AI FILTERING FALLBACK ERROR: ' . $response->get_error_message(), 'error');
                }
                return $results;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error'])) {
                if ($debug) {
                    $provider_name = $this->provider->get_provider_name();
                    Listeo_AI_Search_Utility_Helper::debug_log($provider_name . ' AI FILTERING FALLBACK API ERROR: ' . $body['error']['message'], 'error');
                }
                return $results;
            }
            
            $ai_response = $body['choices'][0]['message']['content'] ?? '';
            $relevant_indices = json_decode(trim($ai_response), true);
            
            if (!is_array($relevant_indices)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('AI FILTERING FALLBACK: Invalid response format: ' . $ai_response, 'error');
                }
                return $results;
            }
            
            // Filter results based on AI selection
            $filtered_results = array();
            foreach ($relevant_indices as $index) {
                if (isset($results[$index])) {
                    $filtered_results[] = $results[$index];
                }
            }
            
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI FILTERING FALLBACK: Successfully applied inclusive filtering. Original: ' . count($results) . ', Filtered: ' . count($filtered_results) . ', Selected indices: [' . implode(', ', $relevant_indices) . ']');
            }
            
            return $filtered_results;
            
        } catch (Exception $e) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI FILTERING FALLBACK EXCEPTION: ' . $e->getMessage(), 'error');
            }
            return $results;
        }
    }

    /**
     * Use GPT-4o mini to detect locations in search query for filtering
     * 
     * @param string $query Search query
     * @param bool $debug Enable debug logging
     * @return array Location analysis data
     */
    private function extract_location_from_query($query, $debug = false) {
        if (empty($this->api_key)) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI PROCESSING: API key not configured, skipping location detection', 'warning');
            }
            return array();
        }

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION: Starting location detection for query: ' . $query);
        }

        $prompt = "Analyze this search query and detect location information: \"$query\"

Please respond with ONLY a JSON object in this exact format:
{
    \"locations\": [\"city1\", \"city2\"],
    \"has_location_intent\": true/false,
    \"confidence\": 0.8
}

Rules:
1. LOCATION DETECTION:
   - locations: array of detected cities, countries, regions, neighborhoods, landmarks, addresses
   - IMPORTANT: Return location names in their canonical/standard form (nominative case)
   - Examples: \"warszawie\" → \"warszawa\", \"krakowie\" → \"kraków\", \"münchens\" → \"münchen\", \"parisien\" → \"paris\"
   - has_location_intent: true if user is looking for location-specific results
   - confidence: 0-1 how confident you are about location detection
   - If no clear location mentioned, return empty locations array
   - Distinguish between business names containing locations vs actual location searches
   - Examples: \"Hotel Paris\" (business name) vs \"hotel in Paris\" (location search)
   - Detect location patterns in ANY language (prepositions like \"in\", \"at\", \"near\", etc.)

Do not include any explanation, just the JSON object.";

        try {
            // Use lightweight models for location extraction
            $model = ($this->provider->get_provider() === 'gemini') ? 'gemini-2.5-flash' : 'gpt-4o-mini';

            // Gemini needs higher max_tokens in OpenAI compatibility mode
            $max_tokens = ($this->provider->get_provider() === 'gemini') ? 500 : 150;

            $response = wp_remote_post($this->provider->get_endpoint('chat'), array(
                'headers' => $this->provider->get_headers(),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'max_tokens' => $max_tokens,
                    'temperature' => 0.6
                )),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION ERROR: ' . $response->get_error_message(), 'error');
                }
                return array(); // Return empty array on error
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error'])) {
                if ($debug) {
                    $provider_name = $this->provider->get_provider_name();
                    Listeo_AI_Search_Utility_Helper::debug_log($provider_name . ' AI LOCATION API ERROR: ' . $body['error']['message'], 'error');
                }
                return array();
            }

            $ai_response = $body['choices'][0]['message']['content'] ?? '';
            $location_data = json_decode(trim($ai_response), true);

            if (!is_array($location_data)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION: Invalid response format: ' . $ai_response, 'error');
                }
                return $this->fallback_query_analysis($query);
            }

            // Extract location components from simplified response
            $locations = $location_data['locations'] ?? array();
            $has_intent = $location_data['has_location_intent'] ?? false;
            $confidence = $location_data['confidence'] ?? 0;

            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION: Detected locations: ' . implode(', ', $locations));
                Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION: Has location intent: ' . ($has_intent ? 'yes' : 'no'));
                Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION: Confidence: ' . $confidence);
            }

            // Return simplified analysis object (no business keywords)
            return array(
                'locations' => $locations,
                'has_location_intent' => $has_intent,
                'confidence' => $confidence,
                'original_query' => $query
            );

            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION: Filtering out results due to low confidence or no location intent', 'warning');
            }

            return array();

        } catch (Exception $e) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('AI LOCATION EXCEPTION: ' . $e->getMessage(), 'error');
            }
            return $this->fallback_query_analysis($query);
        }
    }
    
    /**
     * Expand query with related keywords if enabled
     * 
     * @param string $query Original business query (without location)
     * @param bool $debug Enable debug logging
     * @return string Expanded query or original query
     */
    private function expand_query_if_enabled($query, $debug = false) {
        // Check if query expansion is enabled
        if (!get_option('listeo_ai_search_query_expansion')) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Disabled - returning original query');
            }
            return $query;
        }
        
        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Enabled - expanding query: "' . $query . '"');
        }
        
        try {
            $prompt = "Expand this search query with 3-5 related keywords only. Focus on business types and services, NOT locations.

Original query: \"{$query}\"

CRITICAL: Expand keywords in the SAME LANGUAGE as the original query. If the query is in Polish, respond in Polish. If in English, respond in English. In French respond in French etc.

Rules:
1. Return ONLY keywords separated by commas
2. Maximum 5 additional keywords
3. NO quotes, NO explanations
4. NO location names
5. Focus on business categories and synonyms
6. MAINTAIN THE ORIGINAL LANGUAGE

Examples:
- \"car broken down\" → \"auto repair, mechanic, garage, vehicle service\"
- \"place to sleep\" → \"hotel, accommodation, lodging, hostel\"
- \"kanapki\" → \"delikatesy, sklep mięsny, catering, fast food\"
- \"hotel\" → \"nocleg, zakwaterowanie, pensjonat, hostel\" (if query was Polish)

Keywords:";

            // Use lightweight models for query expansion
            $model = ($this->provider->get_provider() === 'gemini') ? 'gemini-2.5-flash' : 'gpt-4o-mini';

            // Gemini needs higher max_tokens in OpenAI compatibility mode
            $max_tokens = ($this->provider->get_provider() === 'gemini') ? 500 : 150;

            $response = wp_remote_post($this->provider->get_endpoint('chat'), array(
                'headers' => $this->provider->get_headers(),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'max_tokens' => $max_tokens,
                    'temperature' => 0.6
                )),
                'timeout' => 10,
            ));

            if (is_wp_error($response)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION ERROR: ' . $response->get_error_message(), 'error');
                }
                return $query; // Return original query on error
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Response HTTP Code: ' . wp_remote_retrieve_response_code($response));
                Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Raw response body: ' . substr(wp_remote_retrieve_body($response), 0, 500));
            }

            if (isset($body['error'])) {
                if ($debug) {
                    $provider_name = $this->provider->get_provider_name();
                    Listeo_AI_Search_Utility_Helper::debug_log($provider_name . ' QUERY EXPANSION API ERROR: ' . $body['error']['message'], 'error');
                }
                return $query;
            }

            $expanded_keywords = $body['choices'][0]['message']['content'] ?? '';
            $expanded_keywords = trim($expanded_keywords);

            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Extracted content: "' . $expanded_keywords . '"');
            }

            // Clean up the response - remove quotes and extra formatting
            $expanded_keywords = str_replace(array('"', "'", '  '), array('', '', ' '), $expanded_keywords);

            if (empty($expanded_keywords)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Empty response - returning original query', 'warning');
                    Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION: Full response body: ' . print_r($body, true));
                }
                return $query;
            }
            
            // Combine original query with expanded keywords (space-separated for better embedding)
            $final_query = $query . ' ' . $expanded_keywords;
            
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION SUCCESS: "' . $query . '" → "' . $final_query . '"');
            }
            
            return $final_query;

        } catch (Exception $e) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('QUERY EXPANSION EXCEPTION: ' . $e->getMessage(), 'error');
            }
            return $query; // Return original query on exception
        }
    }
    
    /**
     * Fallback query analysis using regex patterns
     * 
     * @param string $query Original search query
     * @return array Fallback analysis
     */
    private function fallback_query_analysis($query) {
        // Simple regex-based fallback for location detection
        $location_patterns = array(
            '/\bw\s+(\w+)/iu',      // Polish: "w Warszawie"
            '/\bwe\s+(\w+)/iu',     // Polish: "we Włocławku"  
            '/\bin\s+(\w+)/iu',     // English: "in London"
            '/\bnear\s+(\w+)/iu',   // English: "near Berlin"
            '/\bat\s+(\w+)/iu',     // English: "at Times Square"
        );
        
        $detected_locations = array();
        $clean_query = $query;
        
        foreach ($location_patterns as $pattern) {
            if (preg_match_all($pattern, $query, $matches)) {
                foreach ($matches[1] as $location) {
                    $detected_locations[] = ucfirst(strtolower($location));
                }
                $clean_query = preg_replace($pattern, '', $clean_query);
            }
        }
        
        // Clean up extra whitespace
        $clean_query = trim(preg_replace('/\s+/', ' ', $clean_query));
        
        return array(
            'locations' => array_unique($detected_locations),
            'has_location_intent' => !empty($detected_locations),
            'confidence' => !empty($detected_locations) ? 0.6 : 0.0,
            'original_query' => $query
        );
    }
    
    /**
     * Calculate keyword boost for exact matches using semantic analysis
     * 
     * @param string $query Search query
     * @param int $listing_id Listing ID
     * @return float Boost value (0.0 to 0.2)
     */
    private function calculate_keyword_boost($query, $listing_id) {
        $boost = 0.0;
        $query_lower = strtolower($query);
        
        // Get listing content for keyword matching
        $post = get_post($listing_id);
        if (!$post) {
            return $boost;
        }
        
        // Combine title, content, and meta for keyword matching
        $content_lower = strtolower($post->post_title . ' ' . $post->post_content);
        
        // Get listing features/tags
        $features = wp_get_post_terms($listing_id, 'listing_feature', array('fields' => 'names'));
        if (!is_wp_error($features) && !empty($features)) {
            $content_lower .= ' ' . strtolower(implode(' ', $features));
        }
        
        // Get custom meta fields
        $meta_fields = array('_keywords', '_listing_description', '_tagline');
        foreach ($meta_fields as $field) {
            $meta_value = get_post_meta($listing_id, $field, true);
            if ($meta_value) {
                $content_lower .= ' ' . strtolower($meta_value);
            }
        }
        
        // Extract meaningful words from query (3+ characters, not common words)
        $query_words = preg_split('/\s+/', $query_lower);
        $meaningful_words = array();
        $common_words = array('the', 'and', 'for', 'with', 'that', 'this', 'from', 'they', 'have', 'was', 'are', 'but', 'not', 'all', 'can', 'had', 'her', 'you', 'one', 'our', 'out', 'day', 'get', 'use', 'man', 'new', 'now', 'way', 'may', 'say');
        
        foreach ($query_words as $word) {
            $word = trim($word, '.,!?;:"()[]{}');
            if (strlen($word) >= 3 && !in_array($word, $common_words)) {
                $meaningful_words[] = $word;
            }
        }
        
        // Check for exact matches of meaningful words
        $matches = 0;
        foreach ($meaningful_words as $word) {
            if (strpos($content_lower, $word) !== false) {
                $matches++;
            }
        }
        
        // Calculate boost based on match ratio (stronger boost for keyword matches)
        if (!empty($meaningful_words)) {
            $match_ratio = $matches / count($meaningful_words);
            if ($match_ratio >= 0.7) { // 70% of words match
                $boost = 0.25;
            } elseif ($match_ratio >= 0.5) { // 50% of words match
                $boost = 0.18;
            } elseif ($match_ratio >= 0.3) { // 30% of words match
                $boost = 0.10;
            }
        }

        // Cap the total boost
        return min($boost, 0.30);
    }
    
    /**
     * Determine if query is ultra-specific using AI analysis
     * 
     * @param string $query Search query
     * @param array $intent_analysis Intent analysis data
     * @param bool $debug Enable debug logging
     * @return bool True if ultra-specific query
     */
    private function is_ultra_specific_query($query, $intent_analysis, $debug = false) {
        // If we already have high confidence and specificity from intent analysis, use that
        if ($intent_analysis['specificity_level'] === 'high' && $intent_analysis['intent_confidence'] >= 0.85) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: Detected as ultra-specific based on intent analysis (confidence: ' . $intent_analysis['intent_confidence'] . ')');
            }
            return true;
        }
        
        // For lower confidence, do additional AI analysis
        if (empty($this->api_key)) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: API key not configured, using fallback detection', 'warning');
            }
            return false; // Conservative fallback
        }

        if ($debug) {
            Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: Performing AI analysis for ultra-specificity: ' . $query);
        }

        $prompt = "Analyze this search query to determine if it represents an ULTRA-SPECIFIC niche requirement: \"$query\"

Respond with ONLY a JSON object:
{
    \"is_ultra_specific\": true/false,
    \"specificity_reason\": \"brief explanation\",
    \"confidence\": 0.8
}

ULTRA-SPECIFIC queries are those that:
- Require very particular features, amenities, or characteristics
- Have specialized requirements that most businesses don't offer
- Need exact matching rather than general category matching
- Represent niche markets or special accommodations

Examples of ULTRA-SPECIFIC:
- Businesses with specific dietary restrictions (vegan-only, gluten-free)
- Accommodations with special accessibility features
- Services with unusual hours (24/7, overnight)
- Establishments with unique amenities (pet-friendly, co-working spaces)
- Specialty services (organic-only, premium/luxury requirements)
- Niche business types (cat cafes, escape rooms, specialty workshops)

Examples of GENERAL/NORMAL:
- \"restaurant\" (general dining)
- \"hotel\" (general accommodation)
- \"coffee shop\" (general cafe)
- \"gym\" (general fitness)
- \"vet\" (general veterinary)

Do not include explanation, just the JSON object.";

        try {
            // Use lightweight models for specificity detection
            $model = ($this->provider->get_provider() === 'gemini') ? 'gemini-2.5-flash' : 'gpt-4o-mini';

            // Gemini needs higher max_tokens in OpenAI compatibility mode
            $max_tokens = ($this->provider->get_provider() === 'gemini') ? 300 : 100;

            $response = wp_remote_post($this->provider->get_endpoint('chat'), array(
                'headers' => $this->provider->get_headers(),
                'body' => json_encode(array(
                    'model' => $model,
                    'messages' => array(
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'max_tokens' => $max_tokens,
                    'temperature' => 0.6
                )),
                'timeout' => 15, // Shorter timeout for quick decision
            ));

            if (is_wp_error($response)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY ERROR: ' . $response->get_error_message(), 'error');
                }
                return false; // Conservative fallback
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error'])) {
                if ($debug) {
                    $provider_name = $this->provider->get_provider_name();
                    Listeo_AI_Search_Utility_Helper::debug_log($provider_name . ' NICHE QUERY API ERROR: ' . $body['error']['message'], 'error');
                }
                return false;
            }

            $ai_response = $body['choices'][0]['message']['content'] ?? '';
            $specificity_data = json_decode(trim($ai_response), true);

            if (!is_array($specificity_data)) {
                if ($debug) {
                    Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: Invalid response format: ' . $ai_response, 'error');
                }
                return false;
            }

            $is_ultra_specific = $specificity_data['is_ultra_specific'] ?? false;
            $reason = $specificity_data['specificity_reason'] ?? '';
            $confidence = $specificity_data['confidence'] ?? 0;

            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: Ultra-specific: ' . ($is_ultra_specific ? 'yes' : 'no'));
                Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: Reason: ' . $reason);
                Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY: Confidence: ' . $confidence);
            }

            // Only return true if AI is confident about ultra-specificity
            return $is_ultra_specific && $confidence >= 0.7;

        } catch (Exception $e) {
            if ($debug) {
                Listeo_AI_Search_Utility_Helper::debug_log('NICHE QUERY EXCEPTION: ' . $e->getMessage(), 'error');
            }
            return false; // Conservative fallback
        }
    }
}
