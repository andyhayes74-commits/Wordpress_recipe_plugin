<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Server-side Spoonacular client. The API key is never exposed to the browser. */
class MCF_Recipe_Spoonacular {
	const PROVIDER = 'spoonacular';
	const BASE_URL = 'https://api.spoonacular.com/';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	public static function api_key() {
		return MCF_Recipe_Admin::get_spoonacular_key();
	}

	public static function is_available() {
		return '' !== self::api_key();
	}

	private static function request( $endpoint, $args = array() ) {
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'spoonacular_not_configured', 'Spoonacular is enabled but no API key has been saved.' );
		}
		$args['apiKey'] = $key;
		// Spoonacular can return both US and metric measures. Always request metric
		// values for this UK-facing recipe library.
		$args['units'] = 'metric';
		$url = self::BASE_URL . ltrim( $endpoint, '/' ) . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
		// Version the cache key so existing imperial API responses are not reused.
		$cache_key = 'mcf_spoonacular_metric_v2_' . md5( $url );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get( $url, array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'spoonacular_transport_error', 'Spoonacular could not be reached.' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'spoonacular_api_error', 'Spoonacular returned an invalid response.', array( 'http_status' => $status ) );
		}
		set_transient( $cache_key, $data, self::CACHE_TTL );
		return $data;
	}

	public static function recipe_id( $id ) {
		$id = absint( $id );
		return $id ? self::PROVIDER . ':' . $id : '';
	}

	public static function external_id( $id ) {
		$id = (string) $id;
		if ( 0 === strpos( $id, self::PROVIDER . ':' ) ) {
			$id = substr( $id, strlen( self::PROVIDER ) + 1 );
		}
		return absint( $id );
	}

	public static function recipe( $id ) {
		$id = self::external_id( $id );
		if ( ! $id ) {
			return new WP_Error( 'spoonacular_invalid_id', 'Invalid Spoonacular recipe ID.' );
		}
		$data = self::request( 'recipes/' . $id . '/information', array( 'includeNutrition' => 'false' ) );
		return is_wp_error( $data ) ? $data : self::to_recipe( $data );
	}

	public static function find_candidates( $search, $cuisine = '' ) {
		$search = sanitize_text_field( $search );
		$terms = self::terms( $search );
		$args = array(
			'query'                => $search,
			'number'               => 30,
			'addRecipeInformation' => 'true',
			'fillIngredients'      => 'true',
			'instructionsRequired' => 'true',
		);
		if ( $cuisine ) {
			$args['cuisine'] = str_replace( '-', ' ', sanitize_title( $cuisine ) );
		}
		$data = self::request( 'recipes/complexSearch', $args );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$candidates = array();
		foreach ( isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array() as $recipe ) {
			$candidate = self::candidate( $recipe, $terms );
			if ( 'incidental' !== $candidate['match_band'] ) {
				$candidates[] = $candidate;
			}
		}
		usort( $candidates, function ( $a, $b ) {
			if ( $a['match_score'] === $b['match_score'] ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
			return $a['match_score'] < $b['match_score'] ? 1 : -1;
		} );
		return array(
			'interpretation' => array( 'ingredients' => $terms, 'residual' => array(), 'terms' => $terms ),
			'ids'            => wp_list_pluck( $candidates, 'id' ),
			'candidates'     => array_slice( $candidates, 0, 30 ),
		);
	}

	public static function browse_candidates( $preferred_ids = array(), $limit = 24 ) {
		$limit = min( 24, max( 1, absint( $limit ) ) );
		$recipes = array();
		foreach ( array_slice( array_filter( array_map( array( __CLASS__, 'external_id' ), (array) $preferred_ids ) ), 0, $limit ) as $id ) {
			$recipe = self::recipe( $id );
			if ( is_array( $recipe ) ) {
				$recipes[ $recipe['id'] ] = $recipe;
			}
		}
		if ( count( $recipes ) < $limit ) {
			$data = self::request( 'recipes/random', array( 'number' => $limit - count( $recipes ) ) );
			if ( ! is_wp_error( $data ) ) {
				foreach ( isset( $data['recipes'] ) && is_array( $data['recipes'] ) ? $data['recipes'] : array() as $recipe ) {
					$normalised = self::to_recipe( $recipe );
					if ( ! empty( $normalised['id'] ) ) {
						$recipes[ $normalised['id'] ] = $normalised;
					}
				}
			}
		}
		return array_slice( array_values( $recipes ), 0, $limit );
	}

	public static function area_options() {
		$names = array( 'African', 'American', 'British', 'Cajun', 'Caribbean', 'Chinese', 'European', 'French', 'Greek', 'Indian', 'Italian', 'Japanese', 'Korean', 'Mediterranean', 'Mexican', 'Middle Eastern', 'Spanish', 'Thai', 'Vietnamese' );
		return array_map( function ( $name ) { return array( 'name' => $name, 'slug' => sanitize_title( $name ), 'count' => 0 ); }, $names );
	}

	private static function candidate( $recipe, $terms ) {
		$normalised = self::to_recipe( $recipe );
		$title = strtolower( $normalised['title'] );
		$ingredients = implode( ' ', $normalised['ingredients'] );
		$method = implode( ' ', $normalised['method'] );
		$description = $normalised['description'];
		$primary = array();
		$secondary = array();
		$title_led = array();
		$scores = array();
		foreach ( $terms as $term ) {
			$pattern = '/(?<![a-z])' . preg_quote( $term, '/' ) . '(?:es|s)?(?![a-z])/i';
			$title_match = (bool) preg_match( $pattern, $title );
			$ingredient_match = (bool) preg_match( $pattern, $ingredients );
			$other_match = (bool) preg_match( $pattern, $method . ' ' . $description );
			$scores[ $term ] = $title_match ? 100 : ( $ingredient_match ? 70 : ( $other_match ? 35 : 0 ) );
			if ( $title_match || $ingredient_match ) {
				$primary[] = $term;
			} elseif ( $other_match ) {
				$secondary[] = $term;
			}
			if ( $title_match ) {
				$title_led[] = $term;
			}
		}
		$band = ! $terms || count( $primary ) === count( $terms ) ? 'primary' : ( count( $primary ) + count( $secondary ) === count( $terms ) ? 'secondary' : 'incidental' );
		return array(
			'id' => $normalised['id'], 'title' => $normalised['title'], 'description' => $normalised['description'], 'ingredients' => $normalised['ingredients'], 'method' => array_slice( $normalised['method'], 0, 8 ),
			'category' => implode( ', ', (array) ( $recipe['dishTypes'] ?? array() ) ), 'area' => implode( ', ', (array) ( $recipe['cuisines'] ?? array() ) ),
			'match_score' => $scores ? min( $scores ) : 0, 'match_band' => $band, 'matched_terms' => array_values( array_unique( array_merge( $primary, $secondary ) ) ),
			'primary_terms' => array_values( array_unique( $primary ) ), 'secondary_terms' => array_values( array_unique( $secondary ) ), 'title_led_terms' => array_values( array_unique( $title_led ) ), 'term_scores' => $scores,
		);
	}

	public static function to_recipe( $recipe ) {
		$id = absint( $recipe['id'] ?? 0 );
		$steps = array();
		foreach ( isset( $recipe['analyzedInstructions'] ) && is_array( $recipe['analyzedInstructions'] ) ? $recipe['analyzedInstructions'] : array() as $instruction_group ) {
			foreach ( isset( $instruction_group['steps'] ) && is_array( $instruction_group['steps'] ) ? $instruction_group['steps'] : array() as $step ) {
				if ( ! empty( $step['step'] ) ) { $steps[] = sanitize_textarea_field( $step['step'] ); }
			}
		}
		if ( ! $steps && ! empty( $recipe['instructions'] ) ) {
			$steps = MCF_Recipe_Plugin::normalise_method_lines( wp_strip_all_tags( $recipe['instructions'] ) );
		}
		$ingredients = array();
		foreach ( isset( $recipe['extendedIngredients'] ) && is_array( $recipe['extendedIngredients'] ) ? $recipe['extendedIngredients'] : array() as $ingredient ) {
			$line = self::metric_ingredient_line( $ingredient );
			if ( $line ) { $ingredients[] = sanitize_text_field( $line ); }
		}
		$summary = ! empty( $recipe['summary'] ) ? sanitize_textarea_field( wp_strip_all_tags( $recipe['summary'] ) ) : '';
		$source = esc_url_raw( $recipe['sourceUrl'] ?? '' );
		$fallback = esc_url_raw( $recipe['spoonacularSourceUrl'] ?? '' );
		return array(
			'id' => self::recipe_id( $id ), 'title' => sanitize_text_field( $recipe['title'] ?? '' ), 'description' => $summary, 'ingredients' => $ingredients, 'method' => $steps,
			'prep_time' => ! empty( $recipe['preparationMinutes'] ) ? absint( $recipe['preparationMinutes'] ) . ' minutes' : '',
			'cook_time' => ! empty( $recipe['cookingMinutes'] ) ? absint( $recipe['cookingMinutes'] ) . ' minutes' : ( ! empty( $recipe['readyInMinutes'] ) ? absint( $recipe['readyInMinutes'] ) . ' minutes total' : '' ),
			'servings' => ! empty( $recipe['servings'] ) ? absint( $recipe['servings'] ) : '', 'allergens' => '', 'storage' => '',
			'meal_type' => implode( ', ', (array) ( $recipe['dishTypes'] ?? array() ) ), 'cuisine' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $recipe['cuisines'] ?? array() ) ) ) ),
			'dietary' => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $recipe['diets'] ?? array() ) ) ) ), 'search_terms' => array(),
			'image' => esc_url_raw( $recipe['image'] ?? '' ), 'image_alt' => sanitize_text_field( $recipe['title'] ?? '' ), 'permalink' => '',
			'source_url' => $source ? $source : ( $fallback ? $fallback : 'https://spoonacular.com/' ), 'source_is_original' => (bool) $source, 'provider' => self::PROVIDER,
		);
	}

	private static function metric_ingredient_line( $ingredient ) {
		$metric = isset( $ingredient['measures']['metric'] ) && is_array( $ingredient['measures']['metric'] ) ? $ingredient['measures']['metric'] : array();
		$amount = isset( $metric['amount'] ) ? sanitize_text_field( (string) $metric['amount'] ) : '';
		$unit = isset( $metric['unitShort'] ) ? sanitize_text_field( $metric['unitShort'] ) : ( isset( $metric['unitLong'] ) ? sanitize_text_field( $metric['unitLong'] ) : '' );
		$name = sanitize_text_field( $ingredient['nameClean'] ?? ( $ingredient['name'] ?? '' ) );
		$notes = isset( $ingredient['meta'] ) && is_array( $ingredient['meta'] ) ? implode( ', ', array_filter( array_map( 'sanitize_text_field', $ingredient['meta'] ) ) ) : '';
		if ( $metric && ( '' !== $amount || '' !== $unit || '' !== $name ) ) {
			return trim( implode( ' ', array_filter( array( $amount, $unit, $name ) ) ) . ( $notes ? ', ' . $notes : '' ) );
		}
		return $ingredient['original'] ?? ( $ingredient['originalString'] ?? '' );
	}

	private static function terms( $search ) {
		$stop = array( 'a', 'an', 'and', 'for', 'from', 'have', 'i', 'ingredients', 'make', 'of', 'recipe', 'recipes', 'some', 'the', 'to', 'with' );
		$terms = preg_split( '/[^a-z0-9]+/i', strtolower( (string) $search ), -1, PREG_SPLIT_NO_EMPTY );
		$terms = array_values( array_filter( $terms, function ( $term ) use ( $stop ) { return strlen( $term ) > 1 && ! in_array( $term, $stop, true ); } ) );
		return array_slice( array_values( array_unique( $terms ) ), 0, 5 );
	}
}
