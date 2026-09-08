<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCF_Recipe_Plugin {
	const POST_TYPE = 'mcf_recipe';
	const TAX_INGREDIENT = 'mcf_ingredient';
	const TAX_CUISINE = 'mcf_cuisine';
	const TAX_DIETARY = 'mcf_dietary';
	const META_DESCRIPTION = '_mcf_description';
	const META_INGREDIENTS = '_mcf_ingredients';
	const META_METHOD = '_mcf_method';
	const META_PREP_TIME = '_mcf_prep_time';
	const META_COOK_TIME = '_mcf_cook_time';
	const META_SERVINGS = '_mcf_servings';
	const META_ALLERGENS = '_mcf_allergens';
	const META_STORAGE = '_mcf_storage';
	const META_MEAL_TYPE = '_mcf_meal_type';
	const META_IMAGE_ALT = '_mcf_image_alt';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function activate() {
		self::register_content_types();
		MCF_Recipe_Learning::activate();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_content_types' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_recipe' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'script_loader_tag', array( __CLASS__, 'script_loader_tag' ), 10, 3 );
		add_filter( 'litespeed_optimize_js_excludes', array( __CLASS__, 'litespeed_script_excludes' ) );
		add_filter( 'litespeed_optm_js_defer_exc', array( __CLASS__, 'litespeed_script_excludes' ) );
		add_filter( 'litespeed_optm_gm_js_exc', array( __CLASS__, 'litespeed_script_excludes' ) );
		add_shortcode( 'mcf_recipes', array( $this, 'shortcode' ) );

		MCF_Recipe_Admin::init();
		MCF_Recipe_Rest::init();
	}

	public static function register_content_types() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'          => __( 'Recipes', 'marcham-recipe-plugin' ),
					'singular_name' => __( 'Recipe', 'marcham-recipe-plugin' ),
					'add_new_item'  => __( 'Add New Recipe', 'marcham-recipe-plugin' ),
					'edit_item'     => __( 'Edit Recipe', 'marcham-recipe-plugin' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'mcf-recipe-library',
				'show_in_rest'    => true,
				'menu_icon'       => 'dashicons-carrot',
				'supports'        => array( 'title', 'thumbnail', 'excerpt', 'revisions' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
			)
		);

		$taxonomies = array(
			self::TAX_INGREDIENT => __( 'Ingredients', 'marcham-recipe-plugin' ),
			self::TAX_CUISINE    => __( 'Cuisines', 'marcham-recipe-plugin' ),
			self::TAX_DIETARY    => __( 'Dietary labels', 'marcham-recipe-plugin' ),
		);

		foreach ( $taxonomies as $taxonomy => $label ) {
			register_taxonomy(
				$taxonomy,
				self::POST_TYPE,
				array(
					'labels'       => array( 'name' => $label ),
					'public'       => false,
					'show_ui'      => false,
					'show_in_rest' => true,
					'hierarchical' => false,
				)
			);
		}
	}

	public function register_assets() {
		wp_register_style(
			'mcf-recipes',
			MCF_RECIPE_URL . 'assets/css/mcf-recipes.css',
			array(),
			MCF_RECIPE_VERSION
		);
		wp_register_script(
			'mcf-recipes',
			MCF_RECIPE_URL . 'assets/js/mcf-recipes.js',
			array(),
			MCF_RECIPE_VERSION,
			true
		);
	}

	public static function script_loader_tag( $tag, $handle, $src ) {
		if ( 'mcf-recipes' !== $handle ) {
			return $tag;
		}
		return str_replace( '<script ', '<script data-no-defer="1" ', $tag );
	}

	public static function litespeed_script_excludes( $excludes ) {
		$excludes = is_array( $excludes ) ? $excludes : array_filter( array( $excludes ) );
		$excludes[] = 'mcf-recipes.js';
		return array_values( array_unique( $excludes ) );
	}

	public function shortcode() {
		$text = MCF_Recipe_Admin::display_text();
		wp_enqueue_style( 'mcf-recipes' );
		wp_enqueue_script( 'mcf-recipes' );
		$config = array(
			// Use a relative URL so the request follows the page's HTTP/HTTPS scheme.
			'restUrl' => wp_make_link_relative( rest_url( 'mcf-recipes/v1' ) ),
			'perPage' => 8,
			'i18n'    => array(
				'loading'                => $text['loading'],
				'noResults'              => $text['no_results'],
				'noFocusedResults'       => $text['no_focused_results'],
				'otherMatches'           => $text['other_matches'],
				'searchFallback'         => $text['search_fallback'],
				'searchError'            => $text['search_error'],
				'aiSearching'            => $text['ai_searching'],
				'aiSearchingDetail'      => $text['ai_searching_detail'],
				'aiSearchingSlow'        => $text['ai_searching_slow'],
				'aiSearchError'          => $text['ai_search_error'],
				'aiNotConfigured'         => $text['ai_not_configured'],
				'mealdbError'            => $text['mealdb_error'],
				'localSearchNotice'      => $text['local_search_notice'],
				'resultsSingular'        => $text['results_singular'],
				'resultsPlural'          => $text['results_plural'],
				'showMoreFilters'        => $text['show_more_filters'],
				'showLessFilters'        => $text['show_less_filters'],
				'loadMore'               => $text['load_more'],
				'viewRecipe'             => $text['view_recipe'],
				'sourceRecipe'           => $text['source_recipe'],
				'mealdbRecipe'           => $text['mealdb_recipe'],
				'spoonacularRecipe'      => $text['spoonacular_recipe'],
				'printRecipe'            => $text['print_recipe'],
				'backToRecipes'          => $text['back_to_recipes'],
				'recipeBadge'            => $text['recipe_badge'],
				'aiAdaptedBadge'         => $text['ai_adapted_badge'],
				'prepLabel'             => $text['prep_label'],
				'cookLabel'             => $text['cook_label'],
				'servingsLabel'          => $text['servings_label'],
				'ingredientsHeading'     => $text['ingredients_heading'],
				'methodHeading'          => $text['method_heading'],
				'allergenHeading'        => $text['allergen_heading'],
				'storageHeading'         => $text['storage_heading'],
				'adaptationNotesHeading' => $text['adaptation_notes_heading'],
			),
		);

		ob_start();
		?>
		<section class="mcf-recipe-library" style="<?php echo esc_attr( MCF_Recipe_Admin::display_style() ); ?>" data-mcf-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" aria-labelledby="mcf-recipe-library-title">
			<div class="mcf-recipe-library__intro">
				<div class="mcf-recipe-library__leaf-mark" aria-hidden="true"><i></i><i></i><i></i></div>
				<p class="mcf-recipe-library__eyebrow"><?php echo esc_html( $text['eyebrow'] ); ?></p>
				<h2 id="mcf-recipe-library-title"><?php echo esc_html( $text['intro_heading'] ); ?></h2>
				<p><?php echo esc_html( $text['intro_text'] ); ?></p>
			</div>
			<form class="mcf-recipe-search" role="search">
				<label class="screen-reader-text" for="mcf-recipe-search-input"><?php echo esc_html( $text['search_label'] ); ?></label>
				<span class="mcf-recipe-search__icon" aria-hidden="true"></span>
				<input id="mcf-recipe-search-input" type="search" name="search" placeholder="<?php echo esc_attr( $text['search_placeholder'] ); ?>" autocomplete="off">
				<button type="submit"><span class="mcf-recipe-search__button-icon" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html( $text['search_button'] ); ?></span></button>
			</form>
			<div class="mcf-recipe-filters" aria-label="<?php echo esc_attr( $text['filters_label'] ); ?>">
				<div class="mcf-recipe-filter-group">
					<strong><?php echo esc_html( $text['cuisine_label'] ); ?></strong>
					<div class="mcf-recipe-filter-chips" data-mcf-filter="cuisine">
						<button type="button" class="is-active" data-mcf-filter-option="" aria-pressed="true"><?php echo esc_html( $text['all_cuisines'] ); ?></button>
					</div>
					<button type="button" class="mcf-recipe-filter-toggle" data-mcf-filter-toggle hidden aria-expanded="false"><?php echo esc_html( $text['show_more_filters'] ); ?></button>
				</div>
			</div>
			<div class="mcf-recipe-status" role="status" aria-live="polite"></div>
			<div class="mcf-recipe-search-progress" data-mcf-search-progress role="status" aria-live="polite" hidden>
				<span class="mcf-recipe-search-progress__spinner" aria-hidden="true"></span>
				<span><strong data-mcf-search-progress-text></strong><small data-mcf-search-progress-detail></small></span>
			</div>
			<div class="mcf-recipe-grid" data-mcf-recipe-grid></div>
			<button class="mcf-recipe-load-more" type="button" hidden><?php echo esc_html( $text['load_more'] ); ?></button>
			<div class="mcf-recipe-detail" data-mcf-recipe-detail hidden tabindex="-1" aria-live="polite"></div>
		</section>
		<?php
		return ob_get_clean();
	}

	public function add_meta_boxes() {
		add_meta_box(
			'mcf_recipe_details',
			__( 'Recipe details', 'marcham-recipe-plugin' ),
			array( $this, 'render_recipe_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	public function render_recipe_metabox( $post ) {
		wp_nonce_field( 'mcf_save_recipe', 'mcf_recipe_nonce' );
		$fields = array(
			'description' => get_post_meta( $post->ID, self::META_DESCRIPTION, true ),
			'ingredients' => self::lines_to_text( get_post_meta( $post->ID, self::META_INGREDIENTS, true ) ),
			'method'      => implode( "\n", self::normalise_method_lines( get_post_meta( $post->ID, self::META_METHOD, true ) ) ),
			'prep_time'   => get_post_meta( $post->ID, self::META_PREP_TIME, true ),
			'cook_time'   => get_post_meta( $post->ID, self::META_COOK_TIME, true ),
			'servings'    => get_post_meta( $post->ID, self::META_SERVINGS, true ),
			'allergens'   => get_post_meta( $post->ID, self::META_ALLERGENS, true ),
			'storage'     => get_post_meta( $post->ID, self::META_STORAGE, true ),
			'meal_type'   => get_post_meta( $post->ID, self::META_MEAL_TYPE, true ),
			'image_alt'   => get_post_meta( $post->ID, self::META_IMAGE_ALT, true ),
		);
		$cuisine = wp_get_post_terms( $post->ID, self::TAX_CUISINE, array( 'fields' => 'names' ) );
		$dietary = wp_get_post_terms( $post->ID, self::TAX_DIETARY, array( 'fields' => 'names' ) );
		$search  = wp_get_post_terms( $post->ID, self::TAX_INGREDIENT, array( 'fields' => 'names' ) );
		?>
		<p><label><strong><?php esc_html_e( 'Short description', 'marcham-recipe-plugin' ); ?></strong><br><textarea class="widefat" rows="3" name="mcf_recipe[description]"><?php echo esc_textarea( $fields['description'] ); ?></textarea></label></p>
		<div class="mcf-admin-grid">
			<p><label><?php esc_html_e( 'Cuisine', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[cuisine]" value="<?php echo esc_attr( implode( ', ', $cuisine ) ); ?>"></label></p>
			<p><label><?php esc_html_e( 'Meal type', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[meal_type]" value="<?php echo esc_attr( $fields['meal_type'] ); ?>"></label></p>
			<p><label><?php esc_html_e( 'Dietary labels', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[dietary]" value="<?php echo esc_attr( implode( ', ', $dietary ) ); ?>" placeholder="Vegetarian, Vegan"></label></p>
			<p><label><?php esc_html_e( 'Main search terms', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[search_terms]" value="<?php echo esc_attr( implode( ', ', $search ) ); ?>" placeholder="carrot, beef, carrot soup"></label><span class="description"><?php esc_html_e( 'Add the ingredients or dish types that define the recipe. Do not add every incidental ingredient or garnish.', 'marcham-recipe-plugin' ); ?></span></p>
			<p><label><?php esc_html_e( 'Preparation time', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[prep_time]" value="<?php echo esc_attr( $fields['prep_time'] ); ?>" placeholder="15 mins"></label></p>
			<p><label><?php esc_html_e( 'Cooking time', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[cook_time]" value="<?php echo esc_attr( $fields['cook_time'] ); ?>" placeholder="30 mins"></label></p>
			<p><label><?php esc_html_e( 'Servings', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[servings]" value="<?php echo esc_attr( $fields['servings'] ); ?>"></label></p>
		</div>
		<p><label><strong><?php esc_html_e( 'Ingredients', 'marcham-recipe-plugin' ); ?></strong><br><textarea class="widefat" rows="8" name="mcf_recipe[ingredients]" placeholder="One ingredient per line. Include quantity and unit."><?php echo esc_textarea( $fields['ingredients'] ); ?></textarea></label></p>
		<p><label><strong><?php esc_html_e( 'Method', 'marcham-recipe-plugin' ); ?></strong><br><textarea class="widefat" rows="8" name="mcf_recipe[method]" placeholder="One method step per line."><?php echo esc_textarea( $fields['method'] ); ?></textarea></label></p>
		<p><label><?php esc_html_e( 'Allergens', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[allergens]" value="<?php echo esc_attr( $fields['allergens'] ); ?>" placeholder="Check ingredients carefully"></label></p>
		<p><label><?php esc_html_e( 'Storage and reheating advice', 'marcham-recipe-plugin' ); ?><br><textarea class="widefat" rows="3" name="mcf_recipe[storage]"><?php echo esc_textarea( $fields['storage'] ); ?></textarea></label></p>
		<p><label><?php esc_html_e( 'Image alt text', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[image_alt]" value="<?php echo esc_attr( $fields['image_alt'] ); ?>"></label></p>
		<p class="description"><?php esc_html_e( 'Use the Featured Image box for the recipe image. Keep recipes as drafts or pending review until ingredients, allergens, storage and cooking instructions have been checked.', 'marcham-recipe-plugin' ); ?></p>
		<?php
	}

	public function save_recipe( $post_id, $post ) {
		if ( ! isset( $_POST['mcf_recipe_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mcf_recipe_nonce'] ) ), 'mcf_save_recipe' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$input = isset( $_POST['mcf_recipe'] ) && is_array( $_POST['mcf_recipe'] ) ? wp_unslash( $_POST['mcf_recipe'] ) : array();
		$map   = array(
			'description' => self::META_DESCRIPTION,
			'prep_time'   => self::META_PREP_TIME,
			'cook_time'   => self::META_COOK_TIME,
			'servings'    => self::META_SERVINGS,
			'allergens'   => self::META_ALLERGENS,
			'storage'     => self::META_STORAGE,
			'meal_type'   => self::META_MEAL_TYPE,
			'image_alt'   => self::META_IMAGE_ALT,
		);
		foreach ( $map as $key => $meta_key ) {
			$value = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
			update_post_meta( $post_id, $meta_key, $value );
		}
		update_post_meta( $post_id, self::META_INGREDIENTS, self::text_to_lines( $input['ingredients'] ?? '' ) );
		update_post_meta( $post_id, self::META_METHOD, self::normalise_method_lines( $input['method'] ?? '' ) );
		self::set_terms( $post_id, self::TAX_CUISINE, $input['cuisine'] ?? '' );
		self::set_terms( $post_id, self::TAX_DIETARY, $input['dietary'] ?? '' );
		self::set_terms( $post_id, self::TAX_INGREDIENT, $input['search_terms'] ?? '' );
	}

	public static function get_recipe( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$terms = static function ( $taxonomy ) use ( $post_id ) {
			$names = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
			return is_wp_error( $names ) ? array() : array_values( $names );
		};
		$image_id  = get_post_thumbnail_id( $post_id );
		$image     = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';
		$image_alt = get_post_meta( $post_id, self::META_IMAGE_ALT, true );
		if ( ! $image_alt && $image_id ) {
			$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
		}

		return array(
			'id'           => $post_id,
			'title'        => get_the_title( $post_id ),
			'description'  => get_post_meta( $post_id, self::META_DESCRIPTION, true ),
			'ingredients'  => self::normalise_lines( get_post_meta( $post_id, self::META_INGREDIENTS, true ) ),
			'method'       => self::normalise_method_lines( get_post_meta( $post_id, self::META_METHOD, true ) ),
			'prep_time'    => get_post_meta( $post_id, self::META_PREP_TIME, true ),
			'cook_time'    => get_post_meta( $post_id, self::META_COOK_TIME, true ),
			'servings'     => get_post_meta( $post_id, self::META_SERVINGS, true ),
			'allergens'    => get_post_meta( $post_id, self::META_ALLERGENS, true ),
			'storage'      => get_post_meta( $post_id, self::META_STORAGE, true ),
			'meal_type'    => get_post_meta( $post_id, self::META_MEAL_TYPE, true ),
			'cuisine'      => $terms( self::TAX_CUISINE ),
			'dietary'      => $terms( self::TAX_DIETARY ),
			'search_terms' => $terms( self::TAX_INGREDIENT ),
			'image'        => $image,
			'image_alt'    => $image_alt,
			'permalink'    => get_permalink( $post_id ),
		);
	}

	public static function normalise_lines( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', $value ) ) ) );
	}

	public static function normalise_method_lines( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( "\n", $value );
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return array();
		}
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = preg_replace( '/[ \t]+(?=\d+\.\s+)/', "\n", trim( $value ) );
		$lines = preg_split( '/\n+/', $value );
		$lines = array_map(
			static function ( $line ) {
				$line = preg_replace( '/^\d+\.\s*/', '', trim( $line ) );
				return sanitize_text_field( $line );
			},
			$lines
		);
		return array_values( array_filter( $lines ) );
	}

	public static function lines_to_text( $value ) {
		return implode( "\n", self::normalise_lines( $value ) );
	}

	public static function text_to_lines( $value ) {
		return self::normalise_lines( $value );
	}

	private static function set_terms( $post_id, $taxonomy, $value ) {
		$terms = preg_split( '/[,|\r\n]+/', (string) $value );
		$terms = array_values( array_filter( array_map( 'sanitize_text_field', $terms ) ) );
		wp_set_post_terms( $post_id, $terms, $taxonomy, false );
	}
}
