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

	public function shortcode() {
		wp_enqueue_style( 'mcf-recipes' );
		wp_enqueue_script( 'mcf-recipes' );
		wp_localize_script(
			'mcf-recipes',
			'MCFRecipes',
			array(
				'restUrl' => esc_url_raw( rest_url( 'mcf-recipes/v1' ) ),
				'perPage' => 8,
				'i18n'    => array(
					'loading'      => __( 'Loading recipes…', 'marcham-recipe-plugin' ),
					'noResults'    => __( 'No recipes matched those choices.', 'marcham-recipe-plugin' ),
					'loadMore'     => __( 'Load more recipes', 'marcham-recipe-plugin' ),
					'viewRecipe'   => __( 'View recipe', 'marcham-recipe-plugin' ),
					'adaptRecipe'  => __( 'Modify with AI', 'marcham-recipe-plugin' ),
					'printRecipe'  => __( 'Print / save PDF', 'marcham-recipe-plugin' ),
					'adaptPrompt'  => __( 'How would you like to adapt this recipe?', 'marcham-recipe-plugin' ),
					'adaptLoading' => __( 'Adapting recipe…', 'marcham-recipe-plugin' ),
					'adaptError'   => __( 'The recipe could not be adapted right now. Please try again later.', 'marcham-recipe-plugin' ),
				),
			)
		);

		ob_start();
		?>
		<section class="mcf-recipe-library" aria-labelledby="mcf-recipe-library-title">
			<div class="mcf-recipe-library__intro">
				<p class="mcf-recipe-library__eyebrow"><?php esc_html_e( 'Waste less, share more', 'marcham-recipe-plugin' ); ?></p>
				<h2 id="mcf-recipe-library-title"><?php esc_html_e( 'Recipes & ideas for surplus food', 'marcham-recipe-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Find practical recipes for the ingredients you have available.', 'marcham-recipe-plugin' ); ?></p>
			</div>
			<form class="mcf-recipe-search" role="search">
				<label class="screen-reader-text" for="mcf-recipe-search-input"><?php esc_html_e( 'Search recipes or ingredients', 'marcham-recipe-plugin' ); ?></label>
				<input id="mcf-recipe-search-input" type="search" name="search" placeholder="<?php esc_attr_e( 'What ingredient do you have?', 'marcham-recipe-plugin' ); ?>" autocomplete="off">
				<button type="submit"><?php esc_html_e( 'Search', 'marcham-recipe-plugin' ); ?></button>
			</form>
			<div class="mcf-recipe-filters" aria-label="<?php esc_attr_e( 'Recipe filters', 'marcham-recipe-plugin' ); ?>">
				<label><?php esc_html_e( 'Cuisine', 'marcham-recipe-plugin' ); ?>
					<select data-mcf-filter="cuisine"><option value=""><?php esc_html_e( 'All cuisines', 'marcham-recipe-plugin' ); ?></option></select>
				</label>
				<label><?php esc_html_e( 'Dietary', 'marcham-recipe-plugin' ); ?>
					<select data-mcf-filter="dietary"><option value=""><?php esc_html_e( 'All dietary types', 'marcham-recipe-plugin' ); ?></option></select>
				</label>
			</div>
			<div class="mcf-recipe-status" role="status" aria-live="polite"></div>
			<div class="mcf-recipe-grid" data-mcf-recipe-grid></div>
			<button class="mcf-recipe-load-more" type="button" hidden><?php esc_html_e( 'Load more recipes', 'marcham-recipe-plugin' ); ?></button>
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
			'method'      => self::lines_to_text( get_post_meta( $post->ID, self::META_METHOD, true ) ),
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
			<p><label><?php esc_html_e( 'Search ingredients', 'marcham-recipe-plugin' ); ?><br><input class="widefat" type="text" name="mcf_recipe[search_terms]" value="<?php echo esc_attr( implode( ', ', $search ) ); ?>" placeholder="carrot, carrots"></label></p>
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
		update_post_meta( $post_id, self::META_METHOD, self::text_to_lines( $input['method'] ?? '' ) );
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
			'method'       => self::normalise_lines( get_post_meta( $post_id, self::META_METHOD, true ) ),
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
