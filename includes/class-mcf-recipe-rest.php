<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCF_Recipe_Rest {
	const NAMESPACE = 'mcf-recipes/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/recipes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_recipes' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'search'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'cuisine'  => array( 'sanitize_callback' => 'sanitize_title' ),
					'dietary'  => array( 'sanitize_callback' => 'sanitize_title' ),
					'page'     => array( 'default' => 1, 'sanitize_callback' => 'absint' ),
					'per_page' => array( 'default' => 8, 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/recipes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_recipe' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/recipes/(?P<id>\d+)/adapt',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'adapt_recipe' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function list_recipes( WP_REST_Request $request ) {
		$search   = sanitize_text_field( $request->get_param( 'search' ) );
		$cuisine  = sanitize_title( $request->get_param( 'cuisine' ) );
		$dietary  = sanitize_title( $request->get_param( 'dietary' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = min( 24, max( 1, absint( $request->get_param( 'per_page' ) ) ) );
		$tax_query = array( 'relation' => 'AND' );

		if ( $cuisine ) {
			$tax_query[] = array(
				'taxonomy' => MCF_Recipe_Plugin::TAX_CUISINE,
				'field'    => 'slug',
				'terms'    => $cuisine,
			);
		}
		if ( $dietary ) {
			$tax_query[] = array(
				'taxonomy' => MCF_Recipe_Plugin::TAX_DIETARY,
				'field'    => 'slug',
				'terms'    => $dietary,
			);
		}

		$args = array(
			'post_type'      => MCF_Recipe_Plugin::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( count( $tax_query ) > 1 ) {
			$args['tax_query'] = $tax_query;
		}

		$ids = null;
		if ( $search ) {
			$title_query = new WP_Query(
				array_merge(
					$args,
					array(
						's'              => $search,
						'posts_per_page' => -1,
						'paged'          => 1,
						'fields'         => 'ids',
					)
				)
			);
			$term_ids = get_terms(
				array(
					'taxonomy'   => MCF_Recipe_Plugin::TAX_INGREDIENT,
					'search'     => $search,
					'hide_empty' => true,
					'fields'     => 'ids',
				)
			);
			$ingredient_ids = array();
			if ( ! is_wp_error( $term_ids ) && $term_ids ) {
				$ingredient_query = new WP_Query(
					array_merge(
						$args,
						array(
							'posts_per_page' => -1,
							'paged'          => 1,
							'fields'         => 'ids',
							'tax_query'      => array_merge(
								count( $tax_query ) > 1 ? array_slice( $tax_query, 1 ) : array(),
								array(
									array(
										'taxonomy' => MCF_Recipe_Plugin::TAX_INGREDIENT,
										'field'    => 'term_id',
										'terms'    => $term_ids,
									),
								)
							),
						)
					)
				);
				$ingredient_ids = $ingredient_query->posts;
			}
			$ids = array_values( array_unique( array_merge( $title_query->posts, $ingredient_ids ) ) );
			$args['post__in'] = $ids ? $ids : array( 0 );
		}

		$query = new WP_Query( $args );
		$recipes = array();
		foreach ( $query->posts as $post ) {
			$recipes[] = MCF_Recipe_Plugin::get_recipe( $post->ID );
		}

		return rest_ensure_response(
			array(
				'recipes' => $recipes,
				'page'    => $page,
				'pages'   => (int) $query->max_num_pages,
				'total'   => (int) $query->found_posts,
				'filters' => array(
					'cuisines' => self::term_options( MCF_Recipe_Plugin::TAX_CUISINE ),
					'dietary'  => self::term_options( MCF_Recipe_Plugin::TAX_DIETARY ),
				),
			)
		);
	}

	public static function get_recipe( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		$post = get_post( $id );
		if ( ! $post || MCF_Recipe_Plugin::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'mcf_recipe_not_found', __( 'Recipe not found.', 'marcham-recipe-plugin' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( MCF_Recipe_Plugin::get_recipe( $id ) );
	}

	public static function adapt_recipe( WP_REST_Request $request ) {
		$id = absint( $request['id'] );
		$post = get_post( $id );
		if ( ! $post || MCF_Recipe_Plugin::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'mcf_recipe_not_found', __( 'Recipe not found.', 'marcham-recipe-plugin' ), array( 'status' => 404 ) );
		}
		if ( self::rate_limited() ) {
			return new WP_Error( 'mcf_rate_limited', __( 'Please wait before requesting another adaptation.', 'marcham-recipe-plugin' ), array( 'status' => 429 ) );
		}
		$api_key = MCF_Recipe_Admin::get_openai_key();
		if ( ! $api_key ) {
			return new WP_Error( 'mcf_ai_not_configured', __( 'AI adaptation has not been configured yet.', 'marcham-recipe-plugin' ), array( 'status' => 503 ) );
		}
		$body = $request->get_json_params();
		$instruction = isset( $body['instruction'] ) ? sanitize_textarea_field( $body['instruction'] ) : '';
		if ( '' === trim( $instruction ) || strlen( $instruction ) > 800 ) {
			return new WP_Error( 'mcf_invalid_instruction', __( 'Please enter a short adaptation request.', 'marcham-recipe-plugin' ), array( 'status' => 400 ) );
		}
		$recipe = MCF_Recipe_Plugin::get_recipe( $id );
		$result = self::call_openai( $api_key, MCF_Recipe_Admin::get_openai_model(), $recipe, $instruction );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['source_recipe_id'] = $id;
		$result['ai_adapted'] = true;
		return rest_ensure_response( $result );
	}

	private static function call_openai( $api_key, $model, $recipe, $instruction ) {
		$prompt = wp_json_encode(
			array(
				'original_recipe' => $recipe,
				'user_request'    => $instruction,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$payload = array(
			'model'        => $model,
			'store'        => false,
			'instructions' => 'You adapt approved recipes. Return JSON only with keys title, description, ingredients (array of strings), method (array of strings), warnings. Preserve allergen and food-safety warnings. Do not claim that food is safe if its condition is unknown. Do not invent medical or nutritional claims.',
			'input'        => 'Adapt this recipe according to the user request. Keep the result practical and concise.' . "\n\n" . $prompt,
		);
		$response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mcf_ai_request_failed', __( 'The AI service could not be reached.', 'marcham-recipe-plugin' ), array( 'status' => 502 ) );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $body ) ) {
			return new WP_Error( 'mcf_ai_request_failed', __( 'The AI service returned an error.', 'marcham-recipe-plugin' ), array( 'status' => 502 ) );
		}
		$text = isset( $body['output_text'] ) ? $body['output_text'] : self::extract_output_text( $body );
		$data = self::decode_json( $text );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'mcf_ai_invalid_response', __( 'The AI response was not in the expected format.', 'marcham-recipe-plugin' ), array( 'status' => 502 ) );
		}
		return array(
			'title'       => sanitize_text_field( isset( $data['title'] ) ? $data['title'] : $recipe['title'] ),
			'description' => sanitize_textarea_field( isset( $data['description'] ) ? $data['description'] : $recipe['description'] ),
			'ingredients' => MCF_Recipe_Plugin::normalise_lines( isset( $data['ingredients'] ) ? $data['ingredients'] : $recipe['ingredients'] ),
			'method'      => MCF_Recipe_Plugin::normalise_lines( isset( $data['method'] ) ? $data['method'] : $recipe['method'] ),
			'warnings'    => MCF_Recipe_Plugin::normalise_lines( isset( $data['warnings'] ) ? $data['warnings'] : array() ),
			'cuisine'     => $recipe['cuisine'],
			'dietary'     => $recipe['dietary'],
			'prep_time'   => $recipe['prep_time'],
			'cook_time'   => $recipe['cook_time'],
			'servings'    => $recipe['servings'],
			'allergens'   => $recipe['allergens'],
			'storage'     => $recipe['storage'],
			'image'       => $recipe['image'],
			'image_alt'   => $recipe['image_alt'],
		);
	}

	private static function extract_output_text( $body ) {
		$text = '';
		$output = isset( $body['output'] ) && is_array( $body['output'] ) ? $body['output'] : array();
		foreach ( $output as $item ) {
			$content_items = isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : array();
			foreach ( $content_items as $content ) {
				if ( isset( $content['text'] ) && ( 'output_text' === ( isset( $content['type'] ) ? $content['type'] : '' ) || ! $text ) ) {
					$text .= (string) $content['text'];
				}
			}
		}
		return $text;
	}

	private static function decode_json( $text ) {
		$text = trim( (string) $text );
		$fence = str_repeat( chr( 96 ), 3 );
		$text = preg_replace( '/^' . preg_quote( $fence, '/' ) . '(?:json)?\s*/i', '', $text );
		$text = preg_replace( '/\s*' . preg_quote( $fence, '/' ) . '$/', '', $text );
		$data = json_decode( $text, true );
		if ( is_array( $data ) ) {
			return $data;
		}
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			return json_decode( substr( $text, $start, $end - $start + 1 ), true );
		}
		return null;
	}

	private static function term_options( $taxonomy ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		return array_map(
			function ( $term ) {
				return array( 'name' => $term->name, 'slug' => $term->slug );
			},
			$terms
		);
	}

	private static function rate_limited() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'mcf_ai_' . md5( $ip );
		if ( get_transient( $key ) ) {
			return true;
		}
		set_transient( $key, 1, MINUTE_IN_SECONDS );
		return false;
	}
}
