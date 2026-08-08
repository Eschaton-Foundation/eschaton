<?php
/**
 * Backend tool executor for the optional agentic chat runner.
 *
 * This class deliberately contains no provider or loop logic. It accepts a
 * normalized tool name/argument array and delegates to the existing plugin
 * integrations without making HTTP requests back to WordPress.
 *
 * @package Listeo_AI_Search
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Listeo_AI_Search_Agent_Tool_Executor {

	/**
	 * Maximum number of search candidates included in the LLM-facing result.
	 *
	 * Full, public-safe result arrays remain available in the UI artifact.
	 */
	const SEARCH_TOOL_TOP_K = 5;

	/**
	 * Maximum content returned by a details tool.
	 */
	const DETAILS_CONTENT_LIMIT = 30000;

	/**
	 * Maximum combined content returned by universal retrieval.
	 */
	const UNIVERSAL_CONTENT_LIMIT = 48000;

	/**
	 * Execute a normalized tool call.
	 *
	 * @param string $name    Tool function name.
	 * @param array  $args    Decoded tool arguments.
	 * @param array  $context Runtime context. May contain request and session_id.
	 * @return array {
	 *     @type mixed      $llm_data    Result sent back to the model.
	 *     @type array|null $artifact    Optional UI artifact with type and items.
	 *     @type bool       $side_effect Whether the tool changes external state.
	 *     @type bool       $terminal    Whether the agent loop should stop.
	 * }
	 */
	public function execute( $name, array $args, array $context = array() ) {
		$name = sanitize_key( $name );

		try {
			switch ( $name ) {
				case 'search_listings':
					return $this->search_listings( $args );

				case 'get_listing_details':
					return $this->get_listing_details( $args );

				case 'search_universal_content':
					return $this->search_universal_content( $args );

				case 'search_products':
					return $this->search_products( $args );

				case 'get_product_details':
					return $this->get_product_details( $args );

				case 'check_order_status':
					return $this->check_order_status( $args );

				case 'add_to_cart':
					return $this->add_to_cart( $args );

				case 'send_contact_message':
					return $this->send_contact_message( $args, $context );

				default:
					return $this->execute_extension_tool( $name, $args, $context );
			}
		} catch ( Throwable $exception ) {
			return $this->error_result( $exception->getMessage(), $this->is_side_effect_tool( $name ) );
		}
	}

	/**
	 * Search Listeo listings through the existing integration.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function search_listings( array $args ) {
		if ( ! class_exists( 'Listeo_AI_Integration' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return $this->error_result( __( 'Listeo integration is not available.', 'ai-chat-search' ) );
		}

		$request = new WP_REST_Request( 'POST', '/listeo/v1/listeo-hybrid-search' );
		$query   = $this->sanitize_limited_text( $this->scalar_arg( $args, 'query' ), 1000 );

		if ( '' !== $query ) {
			$request->set_param( 'query', $query );
		}

		foreach ( array( 'location', 'category', 'listing_type', 'date_start', 'date_end' ) as $param ) {
			$value = $this->sanitize_limited_text( $this->scalar_arg( $args, $param ), 200 );
			if ( '' !== $value ) {
				$request->set_param( $param, $value );
			}
		}

		if ( isset( $args['features'] ) && is_array( $args['features'] ) ) {
			$features = array();
			foreach ( array_slice( $args['features'], 0, 20 ) as $feature ) {
				if ( is_scalar( $feature ) ) {
					$feature = sanitize_text_field( (string) $feature );
					if ( '' !== $feature ) {
						$features[] = $feature;
					}
				}
			}
			if ( ! empty( $features ) ) {
				$request->set_param( 'features', $features );
			}
		}

		foreach ( array( 'radius', 'price_min', 'price_max', 'rating' ) as $param ) {
			if ( isset( $args[ $param ] ) && is_numeric( $args[ $param ] ) && (float) $args[ $param ] > 0 ) {
				$request->set_param( $param, (float) $args[ $param ] );
			}
		}

		if ( array_key_exists( 'open_now', $args ) ) {
			$open_now = $this->normalize_boolean( $args['open_now'] );
			if ( null !== $open_now ) {
				$request->set_param( 'open_now', $open_now );
			}
		}

		$request->set_param( 'per_page', 10 );
		$request->set_param( 'source', 'chatbot' );

		$data = $this->response_data( ( new Listeo_AI_Integration() )->hybrid_search( $request ) );
		if ( isset( $data['error'] ) && empty( $data['results'] ) ) {
			return $this->result( $data );
		}

		$results  = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
		$artifact = array(
			'type'  => 'listings',
			'items' => $this->safe_artifact_value( $results ),
		);
		$condensed = array();

		foreach ( array_slice( $results, 0, self::SEARCH_TOOL_TOP_K ) as $listing ) {
			if ( ! is_array( $listing ) ) {
				continue;
			}

			$categories = array();
			if ( ! empty( $listing['llm_categories'] ) && is_array( $listing['llm_categories'] ) ) {
				$categories = $listing['llm_categories'];
			} elseif ( ! empty( $listing['categories'] ) && is_array( $listing['categories'] ) ) {
				$categories = $listing['categories'];
			}

			$features = array();
			if ( ! empty( $listing['llm_features'] ) && is_array( $listing['llm_features'] ) ) {
				$features = $listing['llm_features'];
			} elseif ( ! empty( $listing['features'] ) && is_array( $listing['features'] ) ) {
				$features = $listing['features'];
			}

			$condensed_listing = array(
				'id'         => isset( $listing['id'] ) ? (int) $listing['id'] : 0,
				'title'      => isset( $listing['title'] ) ? $this->clean_text( $listing['title'] ) : '',
				'url'        => isset( $listing['url'] ) ? esc_url_raw( $listing['url'] ) : '',
				'excerpt'    => $this->trim_words( isset( $listing['content'] ) ? $listing['content'] : ( isset( $listing['excerpt'] ) ? $listing['excerpt'] : '' ), 100 ),
				'address'    => isset( $listing['location']['address'] ) ? $this->clean_text( $listing['location']['address'] ) : '',
				'rating'     => isset( $listing['rating']['average'] ) ? (float) $listing['rating']['average'] : 0,
				'categories' => $this->clean_string_list( $categories, 12 ),
				'features'   => $this->clean_string_list( $features, 20 ),
			);

			if ( ! empty( $listing['event_dates'] ) && is_scalar( $listing['event_dates'] ) ) {
				$condensed_listing['event_dates'] = $this->clean_text( $listing['event_dates'] );
			}

			$condensed[] = $condensed_listing;
		}

		return $this->result(
			array(
				'success'  => ! isset( $data['success'] ) || (bool) $data['success'],
				'total'    => isset( $data['total'] ) ? (int) $data['total'] : count( $results ),
				'listings' => $condensed,
				'notice'   => isset( $data['notice'] ) ? $this->clean_text( $data['notice'] ) : '',
			),
			$artifact
		);
	}

	/**
	 * Retrieve details for up to three listings.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function get_listing_details( array $args ) {
		if ( ! class_exists( 'Listeo_AI_Integration' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return $this->error_result( __( 'Listeo integration is not available.', 'ai-chat-search' ) );
		}

		$ids = $this->normalize_ids( $args, 'listing_id', 'listing_ids' );
		if ( empty( $ids ) ) {
			return $this->error_result( __( 'A listing ID is required.', 'ai-chat-search' ) );
		}

		$request = new WP_REST_Request( 'POST', '/listeo/v1/listeo-listing-details' );
		if ( 1 === count( $ids ) ) {
			$request->set_param( 'listing_id', $ids[0] );
		} else {
			$request->set_param( 'listing_ids', $ids );
		}

		$data = $this->response_data( ( new Listeo_AI_Integration() )->get_listing_details( $request ) );
		return $this->result( $this->condense_details_response( $data, 'listings' ) );
	}

	/**
	 * Retrieve site content without making a nested LLM request.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function search_universal_content( array $args ) {
		$query = $this->sanitize_limited_text( $this->scalar_arg( $args, 'query' ), 1000 );
		if ( '' === $query ) {
			return $this->error_result( __( 'A search query is required.', 'ai-chat-search' ) );
		}

		if (
			! class_exists( 'Listeo_AI_Provider' ) ||
			! class_exists( 'Listeo_AI_Search_AI_Engine' ) ||
			! class_exists( 'Listeo_AI_Search_Embedding_Manager' )
		) {
			return $this->error_result( __( 'Universal AI search is not available.', 'ai-chat-search' ) );
		}

		$provider = new Listeo_AI_Provider();
		$api_key  = $provider->get_api_key();
		if ( empty( $api_key ) ) {
			return $this->error_result( __( 'The AI provider API key is not configured.', 'ai-chat-search' ) );
		}

		$default_limit = max( 2, (int) get_option( 'listeo_ai_chat_rag_sources_limit', 5 ) );
		$top_results   = isset( $args['top_results'] ) && is_numeric( $args['top_results'] )
			? (int) $args['top_results']
			: $default_limit;
		$top_results   = max( 2, min( 10, $top_results ) );
		$direct_ids    = $this->normalize_ids( $args, 'post_id', 'post_ids', 2 );
		$post_types    = $this->get_universal_post_types();
		$sources       = array();
		$context       = '';
		$source_index  = 0;

		foreach ( $direct_ids as $post_id ) {
			$post = get_post( $post_id );
			if (
				! $post
				|| 'publish' !== $post->post_status
				|| ! in_array( $post->post_type, $post_types, true )
			) {
				continue;
			}

			$content = $this->get_pinned_post_content( $post );
			if ( '' === $content ) {
				continue;
			}

			++$source_index;
			$source  = $this->build_source( $post, false );
			$sources[] = $source;
			$context = $this->append_source_context( $context, $source_index, $source, $content, 'PINNED' );

			if ( $this->text_length( $context ) >= self::UNIVERSAL_CONTENT_LIMIT ) {
				break;
			}
		}

		$engine         = new Listeo_AI_Search_AI_Engine( $api_key );
		$search_results = $engine->search(
			$query,
			$top_results,
			0,
			implode( ',', $post_types ),
			(bool) get_option( 'listeo_ai_search_debug_mode', false ),
			array(),
			true
		);

		$listings     = isset( $search_results['listings'] ) && is_array( $search_results['listings'] ) ? $search_results['listings'] : array();
		$chunk_map    = isset( $search_results['chunk_mapping'] ) && is_array( $search_results['chunk_mapping'] ) ? $search_results['chunk_mapping'] : array();
		$items        = array();
		$direct_lookup = array_fill_keys( $direct_ids, true );

		foreach ( $listings as $search_result ) {
			if ( ! is_array( $search_result ) || empty( $search_result['id'] ) ) {
				continue;
			}

			$post_id = (int) $search_result['id'];
			if ( isset( $direct_lookup[ $post_id ] ) ) {
				continue;
			}

			if ( ! empty( $chunk_map[ $post_id ] ) && is_array( $chunk_map[ $post_id ] ) ) {
				foreach ( $chunk_map[ $post_id ] as $chunk ) {
					if ( empty( $chunk['chunk_id'] ) ) {
						continue;
					}
					$items[] = array(
						'type'       => 'chunk',
						'post_id'    => $post_id,
						'chunk_id'   => (int) $chunk['chunk_id'],
						'similarity' => isset( $chunk['similarity'] ) ? (float) $chunk['similarity'] : 0,
					);
				}
			} else {
				$items[] = array(
					'type'       => 'post',
					'post_id'    => $post_id,
					'similarity' => isset( $search_result['similarity_score'] ) ? (float) $search_result['similarity_score'] : 0,
				);
			}
		}

		usort(
			$items,
			static function ( $left, $right ) {
				return $right['similarity'] <=> $left['similarity'];
			}
		);
		$items = array_slice( $items, 0, $top_results );

		$grouped = array();
		foreach ( $items as $item ) {
			$post_id = $item['post_id'];
			if ( 'chunk' === $item['type'] ) {
				if ( ! isset( $grouped[ $post_id ] ) ) {
					$grouped[ $post_id ] = array(
						'type'   => 'chunks',
						'chunks' => array(),
					);
				}
				$grouped[ $post_id ]['chunks'][] = $item['chunk_id'];
			} else {
				$grouped[ $post_id ] = array( 'type' => 'post' );
			}
		}

		$embedding_manager = new Listeo_AI_Search_Embedding_Manager( $api_key );

		foreach ( $grouped as $post_id => $group ) {
			if ( $this->text_length( $context ) >= self::UNIVERSAL_CONTENT_LIMIT ) {
				break;
			}

			$post = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}

			$content    = '';
			$is_chunked = 'chunks' === $group['type'];
			if ( $is_chunked ) {
				$chunks = array();
				foreach ( $group['chunks'] as $chunk_id ) {
					$chunk = get_post( $chunk_id );
					if ( ! $chunk ) {
						continue;
					}

					$chunks[] = sprintf(
						"[Chunk %d/%d]\n%s",
						(int) get_post_meta( $chunk_id, '_chunk_number', true ),
						(int) get_post_meta( $chunk_id, '_chunk_total', true ),
						$this->clean_content( $chunk->post_content )
					);
				}
				$content = implode( "\n\n---\n\n", $chunks );
			} else {
				$content = $embedding_manager->get_content_for_embedding( $post_id );
				$content = $this->clean_content( $content );
			}

			if ( '' === trim( $content ) ) {
				continue;
			}

			++$source_index;
			$source    = $this->build_source( $post, $is_chunked );
			$sources[] = $source;
			$context   = $this->append_source_context( $context, $source_index, $source, $content );
		}

		$context = $this->truncate_text( $context, self::UNIVERSAL_CONTENT_LIMIT );

		return $this->result(
			array(
				'success' => true,
				'total'   => count( $sources ),
				'sources' => $sources,
				'content' => $context,
			)
		);
	}

	/**
	 * Search WooCommerce products through the Pro integration.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function search_products( array $args ) {
		if (
			! class_exists( 'WooCommerce' ) ||
			! class_exists( 'Listeo_AI_WooCommerce_Integration' ) ||
			! class_exists( 'WP_REST_Request' )
		) {
			return $this->error_result( __( 'WooCommerce product search is not available.', 'ai-chat-search' ) );
		}

		$request = new WP_REST_Request( 'POST', '/listeo/v1/woocommerce-product-search' );
		$query   = $this->sanitize_limited_text( $this->scalar_arg( $args, 'query' ), 1000 );
		$sku     = $this->sanitize_limited_text( $this->scalar_arg( $args, 'sku' ), 100 );

		if ( '' !== $query ) {
			$request->set_param( 'query', $query );
		}
		if ( '' !== $sku ) {
			$request->set_param( 'sku', $sku );
		}

		foreach ( array( 'category' ) as $param ) {
			$value = $this->sanitize_limited_text( $this->scalar_arg( $args, $param ), 200 );
			if ( '' !== $value ) {
				$request->set_param( $param, $value );
			}
		}

		foreach ( array( 'price_min', 'price_max', 'rating' ) as $param ) {
			if ( isset( $args[ $param ] ) && is_numeric( $args[ $param ] ) && (float) $args[ $param ] > 0 ) {
				$request->set_param( $param, (float) $args[ $param ] );
			}
		}

		foreach ( array( 'in_stock', 'on_sale' ) as $param ) {
			if ( array_key_exists( $param, $args ) ) {
				$value = $this->normalize_boolean( $args[ $param ] );
				if ( null !== $value ) {
					$request->set_param( $param, $value );
				}
			}
		}

		$request->set_param( 'per_page', 10 );
		$request->set_param( 'source', 'chatbot' );

		$data = $this->response_data( ( new Listeo_AI_WooCommerce_Integration() )->search_products( $request ) );
		if ( isset( $data['error'] ) && empty( $data['results'] ) ) {
			return $this->result( $data );
		}

		$results  = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
		$artifact = array(
			'type'  => 'products',
			'items' => $this->safe_artifact_value( $results ),
		);
		$condensed = array();

		foreach ( array_slice( $results, 0, self::SEARCH_TOOL_TOP_K ) as $product ) {
			if ( ! is_array( $product ) ) {
				continue;
			}

			$item = array(
				'id'           => isset( $product['id'] ) ? (int) $product['id'] : 0,
				'title'        => isset( $product['title'] ) ? $this->clean_text( $product['title'] ) : '',
				'url'          => isset( $product['url'] ) ? esc_url_raw( $product['url'] ) : '',
				'excerpt'      => $this->trim_words( isset( $product['llm_excerpt'] ) ? $product['llm_excerpt'] : ( isset( $product['excerpt'] ) ? $product['excerpt'] : '' ), 100 ),
				'price'        => isset( $product['price']['formatted'] ) ? $this->clean_text( $product['price']['formatted'] ) : '',
				'stock_status' => isset( $product['stock_status'] ) ? sanitize_key( $product['stock_status'] ) : '',
				'on_sale'      => ! empty( $product['on_sale'] ),
				'rating'       => isset( $product['rating']['average'] ) ? (float) $product['rating']['average'] : 0,
				'categories'   => isset( $product['categories'] ) && is_array( $product['categories'] ) ? $this->clean_string_list( $product['categories'], 12 ) : array(),
				'tags'         => isset( $product['tags'] ) && is_array( $product['tags'] ) ? $this->clean_string_list( $product['tags'], 12 ) : array(),
			);

			foreach ( array( 'sku', 'product_type' ) as $key ) {
				if ( isset( $product[ $key ] ) && is_scalar( $product[ $key ] ) && '' !== (string) $product[ $key ] ) {
					$item[ $key ] = $this->clean_text( $product[ $key ] );
				}
			}

			foreach ( array( 'attributes', 'variations', 'extra_pricing' ) as $key ) {
				if ( ! empty( $product[ $key ] ) && is_array( $product[ $key ] ) ) {
					$item[ $key ] = $this->safe_artifact_value( $product[ $key ] );
				}
			}

			$condensed[] = $item;
		}

		return $this->result(
			array(
				'success'  => ! isset( $data['success'] ) || (bool) $data['success'],
				'total'    => isset( $data['total'] ) ? (int) $data['total'] : count( $results ),
				'products' => $condensed,
				'notice'   => isset( $data['notice'] ) ? $this->clean_text( $data['notice'] ) : '',
			),
			$artifact
		);
	}

	/**
	 * Retrieve details for up to three WooCommerce products.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function get_product_details( array $args ) {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return $this->error_result( __( 'WooCommerce is not available.', 'ai-chat-search' ) );
		}

		$ids = $this->normalize_ids( $args, 'product_id', 'product_ids' );
		if ( empty( $ids ) ) {
			return $this->error_result( __( 'A product ID is required.', 'ai-chat-search' ) );
		}

		$products = array();
		$errors   = array();

		if ( class_exists( 'Listeo_AI_WooCommerce_Integration' ) ) {
			$integration = new Listeo_AI_WooCommerce_Integration();

			foreach ( $ids as $product_id ) {
				$request = new WP_REST_Request( 'POST', '/listeo/v1/woocommerce-product-details' );
				$request->set_param( 'product_id', $product_id );
				$data = $this->response_data( $integration->get_product_details( $request ) );

				if ( ! empty( $data['success'] ) ) {
					$products[] = array(
						'product_id'        => $product_id,
						'title'             => isset( $data['title'] ) ? $data['title'] : '',
						'url'               => isset( $data['url'] ) ? $data['url'] : '',
						'structured_content' => isset( $data['structured_content'] ) ? $data['structured_content'] : '',
					);
				} else {
					$errors[] = isset( $data['error'] ) ? $data['error'] : __( 'Product details could not be loaded.', 'ai-chat-search' );
				}
			}
		} elseif ( class_exists( 'Listeo_AI_Search_Chat_API' ) ) {
			$request = new WP_REST_Request( 'POST', '/listeo/v1/woocommerce-product-details' );
			if ( 1 === count( $ids ) ) {
				$request->set_param( 'product_id', $ids[0] );
			} else {
				$request->set_param( 'product_ids', $ids );
			}

			$data = $this->response_data( ( new Listeo_AI_Search_Chat_API() )->get_product_details( $request ) );
			if ( ! empty( $data['products'] ) && is_array( $data['products'] ) ) {
				$products = $data['products'];
			} elseif ( ! empty( $data['success'] ) ) {
				$products[] = array(
					'product_id'        => isset( $data['product_id'] ) ? (int) $data['product_id'] : $ids[0],
					'title'             => isset( $data['title'] ) ? $data['title'] : '',
					'url'               => isset( $data['url'] ) ? $data['url'] : '',
					'structured_content' => isset( $data['structured_content'] ) ? $data['structured_content'] : '',
				);
			} else {
				$errors[] = isset( $data['error'] ) ? $data['error'] : __( 'Product details could not be loaded.', 'ai-chat-search' );
			}
		} else {
			return $this->error_result( __( 'The product details handler is not available.', 'ai-chat-search' ) );
		}

		if ( empty( $products ) ) {
			return $this->error_result( implode( ' ', array_map( array( $this, 'clean_text' ), $errors ) ) );
		}

		$data = 1 === count( $products )
			? array_merge( array( 'success' => true ), $products[0] )
			: array(
				'success'  => true,
				'count'    => count( $products ),
				'products' => $products,
				'errors'   => $errors,
			);

		return $this->result( $this->condense_details_response( $data, 'products' ) );
	}

	/**
	 * Check WooCommerce order status.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function check_order_status( array $args ) {
		if (
			! class_exists( 'WooCommerce' ) ||
			! class_exists( 'Listeo_AI_WooCommerce_Integration' ) ||
			! class_exists( 'WP_REST_Request' )
		) {
			return $this->error_result( __( 'WooCommerce order lookup is not available.', 'ai-chat-search' ) );
		}

		$order_number = $this->sanitize_limited_text( $this->scalar_arg( $args, 'order_number' ), 100 );
		if ( '' === $order_number ) {
			return $this->error_result( __( 'An order number is required.', 'ai-chat-search' ) );
		}

		$request = new WP_REST_Request( 'POST', '/listeo/v1/woocommerce-order-status' );
		$request->set_param( 'order_number', $order_number );

		$email = sanitize_email( $this->scalar_arg( $args, 'billing_email' ) );
		if ( '' !== $email ) {
			$request->set_param( 'billing_email', $email );
		}

		$data = $this->response_data( ( new Listeo_AI_WooCommerce_Integration() )->get_order_status( $request ) );
		if ( isset( $data['structured_content'] ) ) {
			$data['structured_content'] = $this->truncate_text(
				$this->clean_content( $data['structured_content'] ),
				self::DETAILS_CONTENT_LIMIT
			);
		}

		return $this->result( $data );
	}

	/**
	 * Add a product to the active WooCommerce cart.
	 *
	 * @param array $args Tool arguments.
	 * @return array
	 */
	private function add_to_cart( array $args ) {
		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_product' ) ) {
			return $this->error_result( __( 'WooCommerce is not available.', 'ai-chat-search' ), true );
		}

		$product_id = isset( $args['product_id'] ) ? absint( $args['product_id'] ) : 0;
		$quantity   = isset( $args['quantity'] ) ? (int) $args['quantity'] : 1;
		$quantity   = max( 1, min( 100, $quantity ) );

		if ( $product_id <= 0 ) {
			return $this->error_result( __( 'A valid product ID is required.', 'ai-chat-search' ), true );
		}

		$woocommerce = WC();
		if ( ! $woocommerce ) {
			return $this->error_result( __( 'WooCommerce is not available.', 'ai-chat-search' ), true );
		}

		if ( ! $woocommerce->session && method_exists( $woocommerce, 'initialize_session' ) ) {
			$woocommerce->initialize_session();
		}
		if ( ! $woocommerce->cart && method_exists( $woocommerce, 'initialize_cart' ) ) {
			$woocommerce->initialize_cart();
		}
		if ( ! $woocommerce->cart ) {
			return $this->error_result( __( 'The WooCommerce cart session is not available.', 'ai-chat-search' ), true );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return $this->error_result( __( 'Product not found.', 'ai-chat-search' ), true );
		}

		if ( function_exists( 'wc_clear_notices' ) ) {
			wc_clear_notices();
		}

		$cart_item_key = $woocommerce->cart->add_to_cart( $product_id, $quantity );
		if ( ! $cart_item_key ) {
			$message = __( 'Could not add the product to the cart.', 'ai-chat-search' );
			if ( function_exists( 'wc_get_notices' ) ) {
				$notices = wc_get_notices( 'error' );
				if ( ! empty( $notices[0] ) ) {
					$notice  = is_array( $notices[0] ) && isset( $notices[0]['notice'] ) ? $notices[0]['notice'] : $notices[0];
					$message = $this->clean_text( $notice );
				}
			}
			if ( function_exists( 'wc_clear_notices' ) ) {
				wc_clear_notices();
			}
			return $this->error_result( $message, true );
		}

		$data = array(
			'success'       => true,
			'message'       => __( 'Product added to the cart.', 'ai-chat-search' ),
			'product_id'    => $product_id,
			'quantity'      => $quantity,
			'cart_count'    => (int) $woocommerce->cart->get_cart_contents_count(),
			'cart_subtotal' => wp_strip_all_tags( $woocommerce->cart->get_cart_subtotal() ),
		);

		return $this->result(
			$data,
			array(
				'type'  => 'cart',
				'items' => array( $data ),
			),
			true
		);
	}

	/**
	 * Send a contact message through the existing contact handler.
	 *
	 * @param array $args    Tool arguments.
	 * @param array $context Runtime context.
	 * @return array
	 */
	private function send_contact_message( array $args, array $context ) {
		if ( ! class_exists( 'Listeo_AI_Search_Contact_Form' ) || ! class_exists( 'WP_REST_Request' ) ) {
			return $this->error_result( __( 'The contact form handler is not available.', 'ai-chat-search' ), true );
		}

		$name    = $this->sanitize_limited_text( $this->scalar_arg( $args, 'name' ), 200 );
		$email   = sanitize_email( $this->scalar_arg( $args, 'email' ) );
		$message = sanitize_textarea_field( $this->scalar_arg( $args, 'message' ) );
		$message = $this->truncate_text( $message, 5000 );

		if ( '' === $name || '' === $email || '' === $message ) {
			return $this->error_result( __( 'Name, email, and message are required.', 'ai-chat-search' ), true );
		}
		if ( ! is_email( $email ) ) {
			return $this->error_result( __( 'A valid email address is required.', 'ai-chat-search' ), true );
		}

		$request = new WP_REST_Request( 'POST', '/listeo/v1/contact-form' );
		$request->set_param( 'name', $name );
		$request->set_param( 'email', $email );
		$request->set_param( 'message', $message );
		$request->set_param( 'source', 'llm' );
		$request->set_param(
			'conversation_id',
			isset( $context['session_id'] ) ? sanitize_text_field( $context['session_id'] ) : ''
		);

		$data = $this->response_data(
			( new Listeo_AI_Search_Contact_Form() )->handle_submission( $request )
		);

		return $this->result( $data, null, true );
	}

	/**
	 * Delegate Pro and third-party tools through backend-only filters.
	 *
	 * @param string $name    Tool name.
	 * @param array  $args    Tool arguments.
	 * @param array  $context Runtime context.
	 * @return array
	 */
	private function execute_extension_tool( $name, array $args, array $context ) {
		$filter_context = $context;

		if ( empty( $filter_context['session_id'] ) && ! empty( $filter_context['request'] ) && $filter_context['request'] instanceof WP_REST_Request ) {
			$filter_context['session_id'] = sanitize_text_field( $filter_context['request']->get_header( 'X-Session-ID' ) );
		}

		$result = apply_filters(
			'ai_chat_search_proxy_execute_tool',
			null,
			$name,
			$args,
			$filter_context
		);

		if ( null === $result ) {
			$result = apply_filters(
				'ai_chat_search_agent_execute_tool',
				null,
				$name,
				$args,
				$filter_context
			);
		}

		if ( null === $result ) {
			return $this->error_result(
				sprintf(
					/* translators: %s: tool function name. */
					__( 'Unknown tool: %s', 'ai-chat-search' ),
					$name
				),
				$this->is_side_effect_tool( $name )
			);
		}

		if ( is_wp_error( $result ) ) {
			$result = array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		if ( is_array( $result ) && array_key_exists( 'llm_data', $result ) ) {
			return array(
				'llm_data'    => $result['llm_data'],
				'artifact'    => isset( $result['artifact'] ) && is_array( $result['artifact'] ) ? $result['artifact'] : null,
				'side_effect' => isset( $result['side_effect'] ) ? (bool) $result['side_effect'] : $this->is_side_effect_tool( $name ),
				'terminal'    => isset( $result['terminal'] ) ? (bool) $result['terminal'] : $this->is_terminal_result( $name, $result['llm_data'] ),
			);
		}

		$terminal = $this->is_terminal_result( $name, $result );
		$artifact = null;
		if ( 'request_human_handoff' === $name && is_array( $result ) ) {
			$artifact = array(
				'type'  => 'handoff',
				'items' => array( $this->safe_artifact_value( $result ) ),
			);
		}

		return $this->result(
			$result,
			$artifact,
			$this->is_side_effect_tool( $name ),
			$terminal
		);
	}

	/**
	 * Normalize a handler response to an array.
	 *
	 * @param mixed $response Handler response.
	 * @return array
	 */
	private function response_data( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		if ( $response instanceof WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : array( 'data' => $data );
		}

		return is_array( $response ) ? $response : array( 'data' => $response );
	}

	/**
	 * Build a standard executor result.
	 *
	 * @param mixed      $llm_data    LLM-facing data.
	 * @param array|null $artifact    Optional artifact.
	 * @param bool       $side_effect Whether state was changed.
	 * @param bool       $terminal    Whether the loop should stop.
	 * @return array
	 */
	private function result( $llm_data, $artifact = null, $side_effect = false, $terminal = false ) {
		return array(
			'llm_data'    => $llm_data,
			'artifact'    => $artifact,
			'side_effect' => (bool) $side_effect,
			'terminal'    => (bool) $terminal,
		);
	}

	/**
	 * Build a standard error result.
	 *
	 * @param string $message     Error message.
	 * @param bool   $side_effect Whether this was a side-effect tool attempt.
	 * @return array
	 */
	private function error_result( $message, $side_effect = false ) {
		$message = $this->clean_text( $message );
		if ( '' === $message ) {
			$message = __( 'The tool could not complete the request.', 'ai-chat-search' );
		}

		return $this->result(
			array(
				'success' => false,
				'error'   => $message,
			),
			null,
			$side_effect
		);
	}

	/**
	 * Condense a single or comparison details response.
	 *
	 * @param array  $data           Handler response.
	 * @param string $collection_key Multiple-result collection key.
	 * @return array
	 */
	private function condense_details_response( array $data, $collection_key ) {
		if ( isset( $data['structured_content'] ) ) {
			$data['structured_content'] = $this->truncate_text(
				$this->clean_content( $data['structured_content'] ),
				self::DETAILS_CONTENT_LIMIT
			);
		}

		if ( ! empty( $data[ $collection_key ] ) && is_array( $data[ $collection_key ] ) ) {
			$remaining = self::DETAILS_CONTENT_LIMIT;
			foreach ( $data[ $collection_key ] as &$item ) {
				if ( ! is_array( $item ) || ! isset( $item['structured_content'] ) ) {
					continue;
				}
				$item['structured_content'] = $this->truncate_text(
					$this->clean_content( $item['structured_content'] ),
					max( 0, $remaining )
				);
				$remaining -= $this->text_length( $item['structured_content'] );
			}
			unset( $item );
		}

		return $data;
	}

	/**
	 * Get universal post types with a safe fallback.
	 *
	 * @return array
	 */
	private function get_universal_post_types() {
		if ( class_exists( 'Listeo_AI_Search_Chat_API' ) ) {
			return Listeo_AI_Search_Chat_API::get_universal_search_post_types();
		}

		$post_types = array( 'post', 'page' );
		if ( class_exists( 'Listeo_AI_Search_Database_Manager' ) ) {
			$post_types = Listeo_AI_Search_Database_Manager::get_enabled_post_types();
		}

		$post_types = array_diff( array_map( 'sanitize_key', (array) $post_types ), array( 'listing', 'product' ) );
		return ! empty( $post_types ) ? array_values( $post_types ) : array( 'post', 'page' );
	}

	/**
	 * Get direct content for a pinned post.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function get_pinned_post_content( $post ) {
		if (
			class_exists( 'Listeo_AI_Content_Extractor_Factory' ) &&
			method_exists( 'Listeo_AI_Content_Extractor_Factory', 'preserve_links_and_strip_tags' )
		) {
			$content = Listeo_AI_Content_Extractor_Factory::preserve_links_and_strip_tags( $post->post_content );
		} else {
			$content = $this->clean_content( $post->post_content );
		}

		return $this->truncate_text( $content, 20000 );
	}

	/**
	 * Build a public source record.
	 *
	 * @param WP_Post $post       Source post.
	 * @param bool    $is_chunked Whether chunk content was used.
	 * @return array
	 */
	private function build_source( $post, $is_chunked ) {
		$url = 'ai_external_page' === $post->post_type
			? get_post_meta( $post->ID, '_external_url', true )
			: get_permalink( $post->ID );

		return array(
			'id'         => (int) $post->ID,
			'title'      => $this->clean_text( get_the_title( $post->ID ) ),
			'url'        => esc_url_raw( $url ),
			'type'       => sanitize_key( $post->post_type ),
			'excerpt'    => $this->clean_text( get_the_excerpt( $post->ID ) ),
			'is_chunked' => (bool) $is_chunked,
		);
	}

	/**
	 * Append one source to universal retrieval context within the global cap.
	 *
	 * @param string $context Current context.
	 * @param int    $index   Source index.
	 * @param array  $source  Source metadata.
	 * @param string $content Source content.
	 * @param string $label   Optional label.
	 * @return string
	 */
	private function append_source_context( $context, $index, array $source, $content, $label = '' ) {
		$remaining = self::UNIVERSAL_CONTENT_LIMIT - $this->text_length( $context );
		if ( $remaining <= 0 ) {
			return $context;
		}

		$suffix = '' !== $label ? ' (' . $label . ')' : '';
		$block  = sprintf(
			"\n\n=== SOURCE %d%s: %s ===\nURL: %s\nType: %s\n\nCONTENT:\n%s\n=== END SOURCE %d ===\n",
			(int) $index,
			$suffix,
			$source['title'],
			$source['url'],
			$source['type'],
			$this->clean_content( $content ),
			(int) $index
		);

		return $context . $this->truncate_text( $block, $remaining );
	}

	/**
	 * Normalize one or many positive IDs.
	 *
	 * @param array  $args       Arguments.
	 * @param string $single_key Single ID key.
	 * @param string $many_key   ID array key.
	 * @param int    $limit      Maximum count.
	 * @return array
	 */
	private function normalize_ids( array $args, $single_key, $many_key, $limit = 3 ) {
		$values = array();
		if ( ! empty( $args[ $many_key ] ) && is_array( $args[ $many_key ] ) ) {
			$values = $args[ $many_key ];
		} elseif ( isset( $args[ $single_key ] ) ) {
			$values = array( $args[ $single_key ] );
		}

		$ids = array();
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) || ! is_numeric( $value ) ) {
				continue;
			}
			$value = absint( $value );
			if ( $value > 0 ) {
				$ids[] = $value;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		return array_slice( $ids, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Return a scalar argument as text.
	 *
	 * @param array  $args Arguments.
	 * @param string $key  Argument key.
	 * @return string
	 */
	private function scalar_arg( array $args, $key ) {
		return isset( $args[ $key ] ) && is_scalar( $args[ $key ] ) ? (string) $args[ $key ] : '';
	}

	/**
	 * Normalize loose provider boolean values.
	 *
	 * @param mixed $value Value.
	 * @return bool|null
	 */
	private function normalize_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( ! is_scalar( $value ) ) {
			return null;
		}
		if ( 1 === $value || '1' === $value || 'true' === strtolower( (string) $value ) ) {
			return true;
		}
		if ( 0 === $value || '0' === $value || 'false' === strtolower( (string) $value ) ) {
			return false;
		}
		return null;
	}

	/**
	 * Sanitize and truncate one-line text.
	 *
	 * @param string $text  Text.
	 * @param int    $limit Character limit.
	 * @return string
	 */
	private function sanitize_limited_text( $text, $limit ) {
		return $this->truncate_text( sanitize_text_field( (string) $text ), $limit );
	}

	/**
	 * Strip markup and decode entities while preserving line breaks.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	private function clean_content( $text ) {
		if ( ! is_scalar( $text ) ) {
			return '';
		}
		return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Clean scalar text for compact result fields.
	 *
	 * @param mixed $text Text.
	 * @return string
	 */
	private function clean_text( $text ) {
		if ( ! is_scalar( $text ) ) {
			return '';
		}
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}

	/**
	 * Clean a string list.
	 *
	 * @param array $values Values.
	 * @param int   $limit  Maximum items.
	 * @return array
	 */
	private function clean_string_list( array $values, $limit ) {
		$result = array();
		foreach ( array_slice( $values, 0, $limit ) as $value ) {
			$value = $this->clean_text( $value );
			if ( '' !== $value ) {
				$result[] = $value;
			}
		}
		return $result;
	}

	/**
	 * Trim text by words.
	 *
	 * @param mixed $text  Text.
	 * @param int   $limit Word limit.
	 * @return string
	 */
	private function trim_words( $text, $limit ) {
		return wp_trim_words( $this->clean_text( $text ), (int) $limit, '' );
	}

	/**
	 * Recursively retain only JSON-safe public artifact values.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Parent key.
	 * @return mixed
	 */
	private function safe_artifact_value( $value, $key = '' ) {
		if ( is_array( $value ) ) {
			$safe = array();
			foreach ( $value as $item_key => $item_value ) {
				$safe[ $item_key ] = $this->safe_artifact_value( $item_value, (string) $item_key );
			}
			return $safe;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			if ( in_array( $key, array( 'url', 'featured_image', 'thumbnail', 'medium', 'large', 'website' ), true ) ) {
				return esc_url_raw( (string) $value );
			}
			// Agentic cards reuse legacy HTML formatters which interpolate these
			// fields directly. Store text as HTML entities to keep it inert.
			return esc_html( $this->clean_content( $value ) );
		}

		return null;
	}

	/**
	 * Truncate a UTF-8 string safely.
	 *
	 * @param mixed $text  Text.
	 * @param int   $limit Character limit.
	 * @return string
	 */
	private function truncate_text( $text, $limit ) {
		$text  = (string) $text;
		$limit = max( 0, (int) $limit );
		if ( $this->text_length( $text ) <= $limit ) {
			return $text;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}

	/**
	 * Get a UTF-8-aware string length.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private function text_length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text ) : strlen( (string) $text );
	}

	/**
	 * Determine whether a tool changes state.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	private function is_side_effect_tool( $name ) {
		return in_array(
			$name,
			array(
				'add_to_cart',
				'send_contact_message',
				'trigger_webhook_action',
				'request_human_handoff',
			),
			true
		);
	}

	/**
	 * Detect terminal handoff outcomes.
	 *
	 * @param string $name Tool name.
	 * @param mixed  $data Tool result.
	 * @return bool
	 */
	private function is_terminal_result( $name, $data ) {
		return 'request_human_handoff' === $name &&
			is_array( $data ) &&
			( ! empty( $data['handoff_started'] ) || ! empty( $data['handoff_requires_identity'] ) );
	}
}
