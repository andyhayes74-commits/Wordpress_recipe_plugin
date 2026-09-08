<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Server-side TheMealDB client. Recipe content remains owned by TheMealDB. */
class MCF_Recipe_MealDB {
	const BASE_URL = 'https://www.themealdb.com/api/json/';
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	public static function api_key() {
		$settings = MCF_Recipe_Admin::settings();
		$key = isset( $settings['themealdb_api_key'] ) ? trim( (string) $settings['themealdb_api_key'] ) : '';
		return $key ? $key : '1';
	}

	private static function version() {
		return '1' === self::api_key() ? 'v1' : 'v2';
	}

	private static function request( $endpoint, $args = array() ) {
		$url = self::BASE_URL . self::version() . '/' . rawurlencode( self::api_key() ) . '/' . ltrim( $endpoint, '/' );
		if ( $args ) {
			$url .= '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
		}
		$cache_key = 'mcf_mealdb_' . md5( $url );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$response = wp_remote_get( $url, array( 'timeout' => 12, 'headers' => array( 'Accept' => 'application/json' ) ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'mealdb_transport_error', 'TheMealDB could not be reached.' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'mealdb_api_error', 'TheMealDB returned an invalid response.', array( 'http_status' => $status ) );
		}
		set_transient( $cache_key, $data, self::CACHE_TTL );
		return $data;
	}

	public static function meal( $id ) {
		$id = preg_replace( '/[^0-9]/', '', (string) $id );
		if ( '' === $id ) {
			return new WP_Error( 'mealdb_invalid_id', 'Invalid meal ID.' );
		}
		$data = self::request( 'lookup.php', array( 'i' => $id ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['meals'][0] ) && is_array( $data['meals'][0] ) ? $data['meals'][0] : null;
	}

	public static function ingredient_names() {
		$data = self::request( 'list.php', array( 'i' => 'list' ) );
		if ( is_wp_error( $data ) || empty( $data['meals'] ) ) {
			return array();
		}
		$names = array();
		foreach ( $data['meals'] as $item ) {
			if ( ! empty( $item['strIngredient'] ) ) {
				$name = self::term( $item['strIngredient'] );
				$names[ $name ] = sanitize_text_field( $item['strIngredient'] );
			}
		}
		return $names;
	}

	public static function interpret_search( $search ) {
		$stop = array( 'a', 'an', 'and', 'any', 'can', 'cook', 'dish', 'dishes', 'for', 'from', 'got', 'have', 'i', 'in', 'ingredient', 'ingredients', 'lots', 'make', 'me', 'my', 'need', 'of', 'please', 'recipe', 'recipes', 'some', 'the', 'there', 'to', 'up', 'use', 'using', 'want', 'what', 'with' );
		$raw = preg_split( '/[^a-z0-9]+/i', strtolower( (string) $search ), -1, PREG_SPLIT_NO_EMPTY );
		$dictionary = self::ingredient_names();
		$ingredients = array();
		$residual = array();
		foreach ( $raw as $word ) {
			if ( in_array( $word, $stop, true ) ) {
				continue;
			}
			$word = self::singular( $word );
			$match = self::match_dictionary( $word, $dictionary );
			if ( $match ) {
				$ingredients[ self::term( $match ) ] = $match;
			} else {
				$residual[] = $word;
			}
		}
		return array(
			'ingredients' => array_values( $ingredients ),
			'residual'    => array_values( array_unique( $residual ) ),
			'terms'       => array_values( array_unique( array_merge( array_keys( $ingredients ), $residual ) ) ),
		);
	}

	private static function match_dictionary( $word, $dictionary ) {
		foreach ( $dictionary as $term => $display ) {
			if ( $word === $term ) {
				return $display;
			}
		}
		if ( strlen( $word ) < 4 ) {
			return '';
		}
		foreach ( $dictionary as $term => $display ) {
			if ( levenshtein( $word, $term ) <= ( strlen( $word ) >= 5 ? 2 : 1 ) ) {
				return $display;
			}
		}
		return '';
	}

	private static function singular( $word ) {
		if ( strlen( $word ) > 4 && 'ies' === substr( $word, -3 ) ) {
			return substr( $word, 0, -3 ) . 'y';
		}
		if ( strlen( $word ) > 3 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
			return substr( $word, 0, -1 );
		}
		return $word;
	}

	private static function term( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );
		return trim( $value, '_' );
	}

	public static function find_candidates( $search, $cuisine = '' ) {
		$interpretation = self::interpret_search( $search );
		$ids = array();
		if ( count( $interpretation['ingredients'] ) > 1 && 'v2' === self::version() ) {
			$data = self::request( 'filter.php', array( 'i' => implode( ',', array_map( array( __CLASS__, 'term' ), $interpretation['ingredients'] ) ) ) );
			$ids = self::meal_ids( $data );
		} elseif ( $interpretation['ingredients'] ) {
			foreach ( $interpretation['ingredients'] as $ingredient ) {
				$data = self::request( 'filter.php', array( 'i' => self::term( $ingredient ) ) );
				$current = self::meal_ids( $data );
				$ids = $ids ? array_values( array_intersect( $ids, $current ) ) : $current;
			}
		}
		if ( ! $ids && $interpretation['residual'] ) {
			$data = self::request( 'search.php', array( 's' => implode( ' ', $interpretation['residual'] ) ) );
			$ids = self::meal_ids( $data );
		}
		if ( $cuisine && $ids ) {
			$data = self::request( 'filter.php', array( 'a' => self::area_name( $cuisine ) ) );
			$ids = array_values( array_intersect( $ids, self::meal_ids( $data ) ) );
		}
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, 60 );
		$candidates = array();
		foreach ( $ids as $id ) {
			$meal = self::meal( $id );
			if ( is_array( $meal ) ) {
				$candidate = self::score_candidate( self::candidate( $meal ), $interpretation['terms'] );
				// A recipe that only contains the term incidentally is not a
				// useful AI candidate. Keep primary and plausible secondary
				// matches so the model can distinguish them, but remove noise.
				if ( 'incidental' !== $candidate['match_band'] ) {
					$candidates[] = $candidate;
				}
			}
		}
		usort( $candidates, function ( $a, $b ) {
			if ( $a['match_score'] === $b['match_score'] ) {
				return strcasecmp( $a['title'], $b['title'] );
			}
			return $a['match_score'] < $b['match_score'] ? 1 : -1;
		} );
		$candidates = array_slice( $candidates, 0, 30 );
		$ids = array_values( array_map( 'absint', wp_list_pluck( $candidates, 'id' ) ) );
		return array( 'interpretation' => $interpretation, 'ids' => $ids, 'candidates' => $candidates );
	}

	public static function area_options() {
		$data = self::request( 'list.php', array( 'a' => 'list' ) );
		if ( is_wp_error( $data ) || empty( $data['meals'] ) ) { return array(); }
		$options = array();
		foreach ( $data['meals'] as $item ) {
			if ( ! empty( $item['strArea'] ) ) { $options[] = array( 'name' => sanitize_text_field( $item['strArea'] ), 'slug' => sanitize_title( $item['strArea'] ), 'count' => 0 ); }
		}
		return $options;
	}

	private static function area_name( $slug ) {
		foreach ( self::area_options() as $area ) { if ( $area['slug'] === sanitize_title( $slug ) ) { return $area['name']; } }
		return sanitize_text_field( $slug );
	}

	public static function browse_candidates() {
		$ids = array();
		foreach ( array( 'Vegetarian', 'Beef', 'Chicken' ) as $category ) {
			$data = self::request( 'filter.php', array( 'c' => $category ) );
			$ids = array_merge( $ids, self::meal_ids( $data ) );
		}
		$ids = array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, 24 );
		$recipes = array();
		foreach ( $ids as $id ) {
			$meal = self::meal( $id );
			if ( is_array( $meal ) ) {
				$recipes[] = self::to_recipe( $meal );
			}
		}
		return $recipes;
	}

	private static function meal_ids( $data ) {
		if ( is_wp_error( $data ) || empty( $data['meals'] ) || ! is_array( $data['meals'] ) ) {
			return array();
		}
		return array_values( array_filter( array_map( function ( $meal ) { return isset( $meal['idMeal'] ) ? absint( $meal['idMeal'] ) : 0; }, $data['meals'] ) ) );
	}

	public static function candidate( $meal ) {
		$method = MCF_Recipe_Plugin::normalise_method_lines( $meal['strInstructions'] ?? '' );
		return array(
			'id'          => absint( $meal['idMeal'] ),
			'title'       => sanitize_text_field( $meal['strMeal'] ?? '' ),
			'description' => sanitize_textarea_field( implode( ' ', array_slice( $method, 0, 2 ) ) ),
			'ingredients' => self::ingredients( $meal ),
			'method'      => array_slice( $method, 0, 8 ),
			'category'    => sanitize_text_field( $meal['strCategory'] ?? '' ),
			'area'        => sanitize_text_field( $meal['strArea'] ?? '' ),
		);
	}

	private static function score_candidate( $candidate, $terms ) {
		$terms = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'term' ), (array) $terms ) ) ) );
		$term_scores = array();
		$matched_terms = array();
		$primary_terms = array();
		$secondary_terms = array();
		foreach ( $terms as $term ) {
			$score = self::score_term( $candidate, $term );
			$term_scores[ $term ] = $score['score'];
			if ( $score['matched'] ) {
				$matched_terms[] = $term;
			}
			if ( 'primary' === $score['band'] ) {
				$primary_terms[] = $term;
			} elseif ( 'secondary' === $score['band'] ) {
				$secondary_terms[] = $term;
			}
		}
		$score_values = array_values( $term_scores );
		$overall_score = $score_values ? min( $score_values ) : 0;
		$band = 'incidental';
		if ( $terms && count( $primary_terms ) === count( $terms ) ) {
			$band = 'primary';
		} elseif ( $terms && count( $primary_terms ) + count( $secondary_terms ) === count( $terms ) ) {
			$band = 'secondary';
		}
		$candidate['match_score'] = absint( $overall_score );
		$candidate['match_band'] = $band;
		$candidate['matched_terms'] = array_values( array_unique( $matched_terms ) );
		$candidate['primary_terms'] = array_values( array_unique( $primary_terms ) );
		$candidate['secondary_terms'] = array_values( array_unique( $secondary_terms ) );
		$candidate['term_scores'] = $term_scores;
		return $candidate;
	}

	private static function score_term( $candidate, $term ) {
		$title_match = self::has_term( $candidate['title'], $term );
		$ingredients = isset( $candidate['ingredients'] ) && is_array( $candidate['ingredients'] ) ? $candidate['ingredients'] : array();
		$method = isset( $candidate['method'] ) && is_array( $candidate['method'] ) ? $candidate['method'] : array();
		$matching_lines = array();
		foreach ( $ingredients as $line ) {
			if ( self::has_term( $line, $term ) ) {
				$matching_lines[] = (string) $line;
			}
		}
		$method_mentions = 0;
		foreach ( $method as $step ) {
			$method_mentions += self::term_count( $step, $term );
		}
		$ingredient_count = count( $ingredients );
		$ratio = $ingredient_count ? count( $matching_lines ) / $ingredient_count : 0;
		$score = $title_match ? 80 : 0;
		$incidental_only = true;
		foreach ( $matching_lines as $line ) {
			$incidental = (bool) preg_match( '/\b(?:garnish|to taste|as needed|sprinkle|pinch|optional)\b/i', $line );
			if ( ! $incidental ) {
				$incidental_only = false;
			}
			$line_has_quantity = (bool) preg_match( '/\b\d+(?:[.,]\d+)?\b|\b(?:small|medium|large|handful|bunch|can|tin|packet|pack|kg|g|ml|litre|liter|tbsp|tsp)\b/i', $line );
			$score += $incidental ? 4 : ( $line_has_quantity ? 24 : 14 );
		}
		if ( $ratio >= 0.20 ) {
			$score += 25;
		} elseif ( $ratio >= 0.15 ) {
			$score += 10;
		} elseif ( $ratio >= 0.10 ) {
			$score += 5;
		}
		$score += min( 15, $method_mentions * 5 );
		if ( $incidental_only && ! $title_match ) {
			$score = min( $score, 12 );
		}
		$primary = $title_match || $ratio >= 0.15 || ( $ratio >= 0.10 && $method_mentions >= 3 );
		$secondary = $primary || $ratio >= 0.10 || $method_mentions >= 2;
		return array(
			'score'   => $score,
			'matched' => $title_match || (bool) $matching_lines,
			'band'    => $primary ? 'primary' : ( $secondary ? 'secondary' : 'incidental' ),
		);
	}

	private static function has_term( $text, $term ) {
		$words = preg_split( '/[^a-z0-9]+/i', strtolower( (string) $term ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $words ) {
			return false;
		}
		$last = array_pop( $words );
		$words[] = preg_quote( $last, '/' ) . 's?';
		$pattern = '/(?<![a-z])' . implode( '[\\s_-]+', $words ) . '(?![a-z])/i';
		return (bool) preg_match( $pattern, (string) $text );
	}

	private static function term_count( $text, $term ) {
		$words = preg_split( '/[^a-z0-9]+/i', strtolower( (string) $term ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $words ) {
			return 0;
		}
		$last = array_pop( $words );
		$words[] = preg_quote( $last, '/' ) . 's?';
		$pattern = '/(?<![a-z])' . implode( '[\\s_-]+', $words ) . '(?![a-z])/i';
		return preg_match_all( $pattern, (string) $text, $matches );
	}

	private static function ingredients( $meal ) {
		$items = array();
		for ( $i = 1; $i <= 20; $i++ ) {
			$name = isset( $meal[ 'strIngredient' . $i ] ) ? trim( (string) $meal[ 'strIngredient' . $i ] ) : '';
			$measure = isset( $meal[ 'strMeasure' . $i ] ) ? trim( (string) $meal[ 'strMeasure' . $i ] ) : '';
			if ( $name ) {
				$items[] = sanitize_text_field( trim( $measure . ' ' . $name ) );
			}
		}
		return $items;
	}

	public static function to_recipe( $meal ) {
		$method = MCF_Recipe_Plugin::normalise_method_lines( $meal['strInstructions'] ?? '' );
		$description = sanitize_textarea_field( implode( ' ', array_slice( $method, 0, 2 ) ) );
		if ( strlen( $description ) > 320 ) {
			$description = substr( $description, 0, 317 ) . '…';
		}
		$recipe = array(
			'id'          => absint( $meal['idMeal'] ),
			'title'       => sanitize_text_field( $meal['strMeal'] ?? '' ),
			'description' => $description ? $description : sanitize_textarea_field( $meal['strCategory'] ?? '' ) . ( ! empty( $meal['strArea'] ) ? ' · ' . sanitize_textarea_field( $meal['strArea'] ) : '' ),
			'ingredients' => self::ingredients( $meal ),
			'method'      => $method,
			'prep_time'   => '',
			'cook_time'   => '',
			'servings'    => '',
			'allergens'    => '',
			'storage'     => '',
			'meal_type'   => sanitize_text_field( $meal['strCategory'] ?? '' ),
			'cuisine'     => ! empty( $meal['strArea'] ) ? array( sanitize_text_field( $meal['strArea'] ) ) : array(),
			'dietary'     => array(),
			'search_terms' => array(),
			'image'       => esc_url_raw( $meal['strMealThumb'] ?? '' ),
			'image_alt'   => sanitize_text_field( $meal['strMeal'] ?? '' ),
			'permalink'   => '',
		);
		return $recipe;
	}
}
