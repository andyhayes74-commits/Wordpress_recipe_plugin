<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCF_Recipe_Admin {
	const OPTION = 'mcf_recipe_settings';
	private static $recipe_title_index = null;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_mcf_import_csv', array( __CLASS__, 'import_csv' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	public static function defaults() {
		return array(
			'openai_api_key' => '',
			'openai_model'   => 'gpt-4o-mini',
			'themealdb_api_key' => '',
			'providers'      => self::provider_defaults(),
			'debug_search'   => false,
			'appearance'     => self::appearance_defaults(),
			'text'           => self::text_defaults(),
		);
	}

	public static function provider_defaults() {
		return array(
			'mealdb_enabled'      => true,
			'spoonacular_enabled' => false,
			'spoonacular_api_key' => '',
		);
	}

	public static function appearance_defaults() {
		return array(
			'font_family'        => 'system',
			'base_font_size'     => '16px',
			'heading_font_size'  => '42px',
			'card_title_size'    => '20px',
			'detail_title_size'  => '34px',
			'line_height'        => '1.5',
			'body_weight'        => '400',
			'heading_weight'     => '800',
			'text_align'         => 'left',
			'body_color'         => '#173d27',
			'heading_color'      => '#246b36',
			'accent_color'       => '#f68f39',
			'olive_color'        => '#879b38',
			'page_background'    => '#fffaf0',
			'card_background'    => '#ffffff',
			'detail_background'  => '#fffaf0',
			'border_color'       => '#dce3d6',
			'corner_radius'      => '18px',
		);
	}

	public static function text_defaults() {
		return array(
			'eyebrow'                 => 'Waste less, share more',
			'intro_heading'           => 'Recipes & ideas for surplus food',
			'intro_text'              => 'Find practical recipes for the ingredients you have available.',
			'search_label'            => 'Search recipes or ingredients',
			'search_placeholder'      => 'What ingredient do you have?',
			'search_button'           => 'Search',
			'filters_label'           => 'Recipe filters',
			'cuisine_label'           => 'Browse by cuisine',
			'all_cuisines'            => 'All cuisines',
			'dietary_label'           => 'Browse by dietary',
			'all_dietary'             => 'All dietary types',
			'show_more_filters'       => 'Show more',
			'show_less_filters'       => 'Show less',
			'loading'                 => 'Loading recipes…',
			'no_results'              => 'No recipes matched those choices.',
			'no_focused_results'      => 'No recipes focused on your search ingredients were found.',
			'other_matches'           => 'Other recipes containing your ingredients',
			'search_fallback'         => 'AI search is temporarily unavailable. Showing title and main-ingredient matches.',
			'search_error'            => 'Recipes could not be loaded. Please try again.',
			'ai_searching'            => 'Finding recipes that use your ingredients…',
			'ai_searching_detail'     => 'Checking which dishes make the best use of them…',
			'ai_searching_slow'       => 'Still matching your ingredients — nearly there…',
			'ai_search_error'         => 'We could not complete the AI recipe search. Please try again.',
			'local_search_notice'     => 'AI search is not configured. Showing title and main-ingredient matches.',
			'ai_not_configured'       => 'AI recipe matching is not configured yet. Please ask the site administrator to add the OpenAI key.',
			'mealdb_error'            => 'The recipe service could not be reached. Please try again.',
			'results_singular'        => 'recipe found',
			'results_plural'          => 'recipes found',
			'load_more'               => 'Load more recipes',
			'view_recipe'             => 'View recipe',
			'back_to_recipes'         => 'Back to recipes',
			'recipe_badge'            => 'Recipe',
			'ai_adapted_badge'        => 'AI-adapted',
			'prep_label'              => 'Prep:',
			'cook_label'              => 'Cook:',
			'servings_label'          => 'Servings:',
			'ingredients_heading'     => 'Ingredients',
			'method_heading'          => 'Method',
			'allergen_heading'        => 'Allergen information',
			'storage_heading'         => 'Storage and reheating',
			'adaptation_notes_heading'=> 'AI adaptation notes',
			'source_recipe'           => 'View original recipe',
			'mealdb_recipe'           => 'View on TheMealDB',
			'spoonacular_recipe'      => 'View on Spoonacular',
			'print_recipe'            => 'Print / save PDF',
			'download_pdf'            => 'Download recipe PDF',
		);
	}

	public static function settings() {
		$settings = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		$settings['appearance'] = wp_parse_args( isset( $settings['appearance'] ) && is_array( $settings['appearance'] ) ? $settings['appearance'] : array(), self::appearance_defaults() );
		$settings['text']       = wp_parse_args( isset( $settings['text'] ) && is_array( $settings['text'] ) ? $settings['text'] : array(), self::text_defaults() );
		$settings['providers']  = wp_parse_args( isset( $settings['providers'] ) && is_array( $settings['providers'] ) ? $settings['providers'] : array(), self::provider_defaults() );
		return $settings;
	}

	public static function display_settings() {
		$settings = self::settings();
		return array(
			'appearance' => $settings['appearance'],
			'text'       => $settings['text'],
		);
	}

	public static function display_text() {
		return self::settings()['text'];
	}

	public static function display_style() {
		$appearance = self::settings()['appearance'];
		$fonts      = array(
			'system'    => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
			'arial'     => 'Arial, Helvetica, sans-serif',
			'georgia'   => 'Georgia, "Times New Roman", serif',
			'trebuchet' => '"Trebuchet MS", Arial, sans-serif',
			'verdana'   => 'Verdana, Arial, sans-serif',
			'courier'   => '"Courier New", Courier, monospace',
		);
		$variables = array(
			'--mcf-font-family'       => isset( $fonts[ $appearance['font_family'] ] ) ? $fonts[ $appearance['font_family'] ] : $fonts['system'],
			'--mcf-base-font-size'    => $appearance['base_font_size'],
			'--mcf-heading-font-size' => $appearance['heading_font_size'],
			'--mcf-card-title-size'   => $appearance['card_title_size'],
			'--mcf-detail-title-size' => $appearance['detail_title_size'],
			'--mcf-line-height'       => $appearance['line_height'],
			'--mcf-body-weight'       => $appearance['body_weight'],
			'--mcf-heading-weight'    => $appearance['heading_weight'],
			'--mcf-text-align'        => $appearance['text_align'],
			'--mcf-body-color'        => $appearance['body_color'],
			'--mcf-heading-color'     => $appearance['heading_color'],
			'--mcf-accent'            => $appearance['accent_color'],
			'--mcf-olive'             => $appearance['olive_color'],
			'--mcf-page-background'   => $appearance['page_background'],
			'--mcf-card-background'   => $appearance['card_background'],
			'--mcf-detail-background' => $appearance['detail_background'],
			'--mcf-border-color'      => $appearance['border_color'],
			'--mcf-corner-radius'     => $appearance['corner_radius'],
		);
		$output = array();
		foreach ( $variables as $property => $value ) {
			$output[] = $property . ':' . sanitize_text_field( $value );
		}
		return implode( ';', $output );
	}

	public static function debug_field() {
		echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION ) . '[debug_search]" value="1" ' . checked( ! empty( self::settings()['debug_search'] ), true, false ) . '> Enable search debug logging</label>';
		echo '<p class="description">Records search text and technical outcomes for the latest 50 searches, for up to 24 hours. Administrator access only; no API keys or raw API responses. Turn off after testing.</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=mcf-recipe-debug' ) ) . '">View search diagnostics</a></p>';
	}

	public static function get_openai_key() {
		$settings = self::settings();
		return isset( $settings['openai_api_key'] ) ? trim( (string) $settings['openai_api_key'] ) : '';
	}

	public static function get_openai_model() {
		$settings = self::settings();
		return ! empty( $settings['openai_model'] ) ? sanitize_text_field( $settings['openai_model'] ) : self::defaults()['openai_model'];
	}

	public static function get_mealdb_key() {
		$settings = self::settings();
		return isset( $settings['themealdb_api_key'] ) ? trim( (string) $settings['themealdb_api_key'] ) : '';
	}

	public static function get_spoonacular_key() {
		$settings = self::settings();
		return isset( $settings['providers']['spoonacular_api_key'] ) ? trim( (string) $settings['providers']['spoonacular_api_key'] ) : '';
	}

	public static function provider_enabled( $provider ) {
		$provider = sanitize_key( $provider );
		$settings = self::settings();
		return ! empty( $settings['providers'][ $provider . '_enabled' ] );
	}

	public static function maybe_upgrade() {
		$settings = get_option( self::OPTION, array() );
		if ( is_array( $settings ) && isset( $settings['openai_model'] ) && 'gpt-5-mini' === $settings['openai_model'] ) {
			$settings['openai_model'] = 'gpt-4o-mini';
			update_option( self::OPTION, $settings, false );
		}
	}

	public static function menu() {
		add_menu_page(
			__( 'Recipe Library', 'marcham-recipe-plugin' ),
			__( 'Recipe Library', 'marcham-recipe-plugin' ),
			'manage_options',
			'mcf-recipe-library',
			array( __CLASS__, 'library_page' ),
			'dashicons-carrot',
			26
		);
		add_submenu_page(
			'mcf-recipe-library',
			__( 'Recipe Library Settings', 'marcham-recipe-plugin' ),
			__( 'Settings', 'marcham-recipe-plugin' ),
			'manage_options',
			'mcf-recipe-settings',
			array( __CLASS__, 'settings_page' )
		);
		add_submenu_page(
			'mcf-recipe-library',
			__( 'Import Recipes', 'marcham-recipe-plugin' ),
			__( 'Import CSV', 'marcham-recipe-plugin' ),
			'manage_options',
			'mcf-recipe-import',
			array( __CLASS__, 'import_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			'mcf_recipe_settings_group',
			self::OPTION,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);
		add_settings_section(
			'mcf_recipe_ai_section',
			__( 'AI recipe search', 'marcham-recipe-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'OpenAI assesses whether the recipes returned by your enabled provider are genuinely useful for the surplus ingredients being searched. The OpenAI key is used only by the server and is never sent to the browser.', 'marcham-recipe-plugin' ) . '</p>';
			},
			'mcf-recipe-settings'
		);
		add_settings_field(
			'openai_api_key',
			__( 'OpenAI API key', 'marcham-recipe-plugin' ),
			array( __CLASS__, 'api_key_field' ),
			'mcf-recipe-settings',
			'mcf_recipe_ai_section'
		);
		add_settings_field(
			'openai_model',
			__( 'OpenAI model', 'marcham-recipe-plugin' ),
			array( __CLASS__, 'model_field' ),
			'mcf-recipe-settings',
			'mcf_recipe_ai_section'
		);
		add_settings_field( 'debug_search', 'Search debug logging', array( __CLASS__, 'debug_field' ), 'mcf-recipe-settings', 'mcf_recipe_ai_section' );
		add_settings_section(
			'mcf_recipe_provider_section',
			__( 'Recipe providers', 'marcham-recipe-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'Choose which recipe services the public library may use. Spoonacular is preferred when both providers are enabled and its key has been saved. If it is switched on without a key, TheMealDB remains the fallback when enabled.', 'marcham-recipe-plugin' ) . '</p>';
			},
			'mcf-recipe-settings'
		);
		add_settings_field( 'providers', __( 'Provider connections', 'marcham-recipe-plugin' ), array( __CLASS__, 'provider_fields' ), 'mcf-recipe-settings', 'mcf_recipe_provider_section' );
		add_settings_field( 'themealdb_api_key', 'TheMealDB API key', array( __CLASS__, 'mealdb_key_field' ), 'mcf-recipe-settings', 'mcf_recipe_provider_section' );
		add_settings_section(
			'mcf_recipe_appearance_section',
			__( 'Recipe library appearance', 'marcham-recipe-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'Control the typography, colours and spacing without editing CSS. These settings apply to every [mcf_recipes] library on the site.', 'marcham-recipe-plugin' ) . '</p>';
			},
			'mcf-recipe-settings'
		);
		add_settings_field(
			'appearance',
			__( 'Formatting options', 'marcham-recipe-plugin' ),
			array( __CLASS__, 'appearance_fields' ),
			'mcf-recipe-settings',
			'mcf_recipe_appearance_section'
		);
		add_settings_section(
			'mcf_recipe_text_section',
			__( 'Visitor-facing text', 'marcham-recipe-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'Every label and instruction shown by the recipe library can be changed here. Leave a field blank if you want that item hidden where supported.', 'marcham-recipe-plugin' ) . '</p>';
			},
			'mcf-recipe-settings'
		);
		add_settings_field(
			'text',
			__( 'Text labels and messages', 'marcham-recipe-plugin' ),
			array( __CLASS__, 'text_fields' ),
			'mcf-recipe-settings',
			'mcf_recipe_text_section'
		);
	}

	public static function sanitize_settings( $input ) {
		$current = self::settings();
		$input   = is_array( $input ) ? $input : array();
		$key     = isset( $input['openai_api_key'] ) ? preg_replace( '/[\r\n\t]/', '', (string) $input['openai_api_key'] ) : '';
		$mealdb_key = isset( $input['themealdb_api_key'] ) ? preg_replace( '/[\r\n\t]/', '', (string) $input['themealdb_api_key'] ) : '';
		$providers_input = isset( $input['providers'] ) && is_array( $input['providers'] ) ? $input['providers'] : array();
		$spoonacular_key = isset( $providers_input['spoonacular_api_key'] ) ? preg_replace( '/[\r\n\t]/', '', (string) $providers_input['spoonacular_api_key'] ) : '';
		$appearance_input = isset( $input['appearance'] ) && is_array( $input['appearance'] ) ? $input['appearance'] : array();
		$text_input       = isset( $input['text'] ) && is_array( $input['text'] ) ? $input['text'] : array();
		$appearance       = self::sanitize_appearance( $appearance_input, $current['appearance'] );
		$text             = self::sanitize_text_settings( $text_input, $current['text'] );

		return array(
			'openai_api_key' => '' !== trim( $key ) ? sanitize_text_field( $key ) : $current['openai_api_key'],
			'debug_search'   => ! empty( $input['debug_search'] ),
			'openai_model'   => isset( $input['openai_model'] ) ? sanitize_text_field( $input['openai_model'] ) : self::defaults()['openai_model'],
			'themealdb_api_key' => '' !== trim( $mealdb_key ) ? sanitize_text_field( $mealdb_key ) : $current['themealdb_api_key'],
			'providers'      => array(
				'mealdb_enabled'      => ! empty( $providers_input['mealdb_enabled'] ),
				'spoonacular_enabled' => ! empty( $providers_input['spoonacular_enabled'] ),
				'spoonacular_api_key' => '' !== trim( $spoonacular_key ) ? sanitize_text_field( $spoonacular_key ) : $current['providers']['spoonacular_api_key'],
			),
			'appearance'     => $appearance,
			'text'           => $text,
		);
	}

	public static function appearance_fields() {
		$appearance = self::settings()['appearance'];
		$font_options = array(
			'system'    => 'System sans-serif',
			'arial'     => 'Arial',
			'georgia'   => 'Georgia',
			'trebuchet' => 'Trebuchet MS',
			'verdana'   => 'Verdana',
			'courier'   => 'Courier New',
		);
		$weight_options = array( '400' => 'Normal', '500' => 'Medium', '600' => 'Semi-bold', '700' => 'Bold', '800' => 'Extra-bold' );
		$select = static function ( $name, $value, $options ) {
			$html = '<select name="' . esc_attr( self::OPTION . '[appearance][' . $name . ']' ) . '">';
			foreach ( $options as $key => $label ) {
				$html .= '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			return $html . '</select>';
		};
		$input = static function ( $name, $value, $placeholder = '' ) {
			return '<input class="regular-text" type="text" name="' . esc_attr( self::OPTION . '[appearance][' . $name . ']' ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '">';
		};
		?>
		<div class="mcf-settings-fields">
			<p><label><strong><?php esc_html_e( 'Font family', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $select( 'font_family', $appearance['font_family'], $font_options ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Base text size', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'base_font_size', $appearance['base_font_size'], '16px' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Main heading size', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'heading_font_size', $appearance['heading_font_size'], '42px' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Recipe card title size', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'card_title_size', $appearance['card_title_size'], '20px' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Recipe detail title size', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'detail_title_size', $appearance['detail_title_size'], '34px' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Line height', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'line_height', $appearance['line_height'], '1.5' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Body weight', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $select( 'body_weight', $appearance['body_weight'], $weight_options ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Heading weight', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $select( 'heading_weight', $appearance['heading_weight'], $weight_options ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Text alignment', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $select( 'text_align', $appearance['text_align'], array( 'left' => 'Left', 'center' => 'Centre', 'right' => 'Right' ) ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Body colour', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'body_color', $appearance['body_color'], '#173d27' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Heading colour', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'heading_color', $appearance['heading_color'], '#246b36' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Accent colour', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'accent_color', $appearance['accent_color'], '#f68f39' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Secondary/olive colour', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'olive_color', $appearance['olive_color'], '#879b38' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Page background', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'page_background', $appearance['page_background'], '#fffaf0' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Recipe card background', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'card_background', $appearance['card_background'], '#ffffff' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Recipe detail background', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'detail_background', $appearance['detail_background'], '#fffaf0' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Border colour', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'border_color', $appearance['border_color'], '#dce3d6' ); ?></label></p>
			<p><label><strong><?php esc_html_e( 'Corner radius', 'marcham-recipe-plugin' ); ?></strong><br><?php echo $input( 'corner_radius', $appearance['corner_radius'], '18px' ); ?></label></p>
		</div>
		<p class="description"><?php esc_html_e( 'Sizes accept px, rem, em, %, vw or vh. Colours must be hex values such as #f68f39.', 'marcham-recipe-plugin' ); ?></p>
		<?php
	}

	public static function text_fields() {
		$text = self::settings()['text'];
		$groups = array(
			'Search relevance messages' => array( 'no_focused_results', 'other_matches', 'search_fallback', 'search_error', 'ai_searching', 'ai_searching_detail', 'ai_searching_slow', 'ai_search_error', 'local_search_notice', 'ai_not_configured', 'mealdb_error' ),
			'Library introduction' => array( 'eyebrow', 'intro_heading', 'intro_text' ),
			'Search and filters' => array( 'search_label', 'search_placeholder', 'search_button', 'filters_label', 'cuisine_label', 'all_cuisines', 'dietary_label', 'all_dietary', 'show_more_filters', 'show_less_filters' ),
			'Cards and recipe details' => array( 'loading', 'no_results', 'results_singular', 'results_plural', 'load_more', 'view_recipe', 'back_to_recipes', 'recipe_badge', 'ai_adapted_badge', 'prep_label', 'cook_label', 'servings_label', 'ingredients_heading', 'method_heading', 'allergen_heading', 'storage_heading', 'adaptation_notes_heading' ),
			'Source and PDF actions' => array( 'source_recipe', 'mealdb_recipe', 'spoonacular_recipe', 'download_pdf' ),
		);
		$labels = array(
			'no_focused_results' => 'No focused recipes', 'other_matches' => 'Other matches heading', 'search_fallback' => 'Legacy AI unavailable notice', 'search_error' => 'Search request error', 'ai_searching' => 'AI search message', 'ai_searching_detail' => 'AI search detail', 'ai_searching_slow' => 'AI search slow message', 'ai_search_error' => 'AI search error', 'local_search_notice' => 'Local-search notice', 'ai_not_configured' => 'AI not configured message', 'mealdb_error' => 'Recipe source error message',
			'eyebrow' => 'Eyebrow', 'intro_heading' => 'Main heading', 'intro_text' => 'Introduction', 'search_label' => 'Search accessibility label', 'search_placeholder' => 'Search placeholder', 'search_button' => 'Search button', 'filters_label' => 'Filters accessibility label', 'cuisine_label' => 'Cuisine label', 'all_cuisines' => 'All cuisines option', 'dietary_label' => 'Dietary label', 'all_dietary' => 'All dietary option', 'show_more_filters' => 'Show more filters button', 'show_less_filters' => 'Show less filters button', 'loading' => 'Loading message', 'no_results' => 'No results message', 'results_singular' => 'Single-result message', 'results_plural' => 'Multiple-results message', 'load_more' => 'Load more button', 'view_recipe' => 'View recipe button', 'back_to_recipes' => 'Back button', 'recipe_badge' => 'Recipe badge', 'ai_adapted_badge' => 'AI-adapted badge', 'prep_label' => 'Preparation label', 'cook_label' => 'Cooking label', 'servings_label' => 'Servings label', 'ingredients_heading' => 'Ingredients heading', 'method_heading' => 'Method heading', 'allergen_heading' => 'Allergen heading', 'storage_heading' => 'Storage heading', 'adaptation_notes_heading' => 'AI adaptation notes heading', 'source_recipe' => 'Original-source button', 'mealdb_recipe' => 'TheMealDB-source button', 'spoonacular_recipe' => 'Spoonacular-source button', 'download_pdf' => 'Download-PDF button',
		);
		foreach ( $groups as $group => $keys ) {
			echo '<h3>' . esc_html( $group ) . '</h3><div class="mcf-settings-fields">';
			foreach ( $keys as $key ) {
				$type = 'intro_text' === $key ? 'textarea' : 'text';
				if ( 'textarea' === $type ) {
					printf( '<p><label><strong>%s</strong><br><textarea class="large-text" rows="3" name="%s">%s</textarea></label></p>', esc_html( $labels[ $key ] ), esc_attr( self::OPTION . '[text][' . $key . ']' ), esc_textarea( $text[ $key ] ) );
				} else {
					printf( '<p><label><strong>%s</strong><br><input class="regular-text" type="text" name="%s" value="%s"></label></p>', esc_html( $labels[ $key ] ), esc_attr( self::OPTION . '[text][' . $key . ']' ), esc_attr( $text[ $key ] ) );
				}
			}
			echo '</div>';
		}
	}

	private static function sanitize_appearance( $input, $current ) {
		$defaults = self::appearance_defaults();
		$input    = is_array( $input ) ? $input : array();
		$current  = is_array( $current ) ? wp_parse_args( $current, $defaults ) : $defaults;
		$fonts    = array( 'system', 'arial', 'georgia', 'trebuchet', 'verdana', 'courier' );
		$weights  = array( '400', '500', '600', '700', '800' );
		$align    = array( 'left', 'center', 'right' );
		$font     = isset( $input['font_family'] ) ? sanitize_key( $input['font_family'] ) : $current['font_family'];
		$body_weight = isset( $input['body_weight'] ) ? (string) $input['body_weight'] : (string) $current['body_weight'];
		$heading_weight = isset( $input['heading_weight'] ) ? (string) $input['heading_weight'] : (string) $current['heading_weight'];
		$text_align = isset( $input['text_align'] ) ? sanitize_key( $input['text_align'] ) : $current['text_align'];

		$appearance = array(
			'font_family'       => in_array( $font, $fonts, true ) ? $font : $current['font_family'],
			'base_font_size'    => self::sanitize_css_length( isset( $input['base_font_size'] ) ? $input['base_font_size'] : $current['base_font_size'], $current['base_font_size'] ),
			'heading_font_size' => self::sanitize_css_length( isset( $input['heading_font_size'] ) ? $input['heading_font_size'] : $current['heading_font_size'], $current['heading_font_size'] ),
			'card_title_size'   => self::sanitize_css_length( isset( $input['card_title_size'] ) ? $input['card_title_size'] : $current['card_title_size'], $current['card_title_size'] ),
			'detail_title_size' => self::sanitize_css_length( isset( $input['detail_title_size'] ) ? $input['detail_title_size'] : $current['detail_title_size'], $current['detail_title_size'] ),
			'line_height'       => self::sanitize_line_height( isset( $input['line_height'] ) ? $input['line_height'] : $current['line_height'], $current['line_height'] ),
			'body_weight'       => in_array( $body_weight, $weights, true ) ? $body_weight : $current['body_weight'],
			'heading_weight'    => in_array( $heading_weight, $weights, true ) ? $heading_weight : $current['heading_weight'],
			'text_align'        => in_array( $text_align, $align, true ) ? $text_align : $current['text_align'],
		);
		$colours = array( 'body_color', 'heading_color', 'accent_color', 'olive_color', 'page_background', 'card_background', 'detail_background', 'border_color' );
		foreach ( $colours as $colour ) {
			$value = isset( $input[ $colour ] ) ? sanitize_hex_color( $input[ $colour ] ) : sanitize_hex_color( $current[ $colour ] );
			$appearance[ $colour ] = $value ?: $current[ $colour ];
		}
		$appearance['corner_radius'] = self::sanitize_css_length( isset( $input['corner_radius'] ) ? $input['corner_radius'] : $current['corner_radius'], $current['corner_radius'] );

		return $appearance;
	}

	private static function sanitize_text_settings( $input, $current ) {
		$defaults = self::text_defaults();
		$input    = is_array( $input ) ? $input : array();
		$current  = is_array( $current ) ? wp_parse_args( $current, $defaults ) : $defaults;
		$text     = array();
		foreach ( $defaults as $key => $default ) {
			$text[ $key ] = isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : sanitize_textarea_field( $current[ $key ] );
		}
		return $text;
	}

	private static function sanitize_css_length( $value, $fallback = '' ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( preg_match( '/^(?:0|\d+(?:\.\d+)?)(?:px|rem|em|%|vw|vh|pt)?$/i', $value ) ) {
			return $value;
		}
		return $fallback;
	}

	private static function sanitize_line_height( $value, $fallback = '1.5' ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		return preg_match( '/^(?:0|[1-9]\d*)(?:\.\d+)?$/', $value ) ? $value : $fallback;
	}

	public static function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'mcf-recipe' ) ) {
			wp_add_inline_style(
				'wp-admin',
				'.mcf-admin-grid,.mcf-settings-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.mcf-admin-grid p,.mcf-settings-fields p{margin:0 0 8px}.mcf-admin-card{max-width:900px;background:#fff;border:1px solid #dcdcde;padding:20px}.mcf-key-status{color:#287c3c;font-weight:600}'
			);
		}
	}

	public static function library_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$count = wp_count_posts( MCF_Recipe_Plugin::POST_TYPE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Recipe Library', 'marcham-recipe-plugin' ); ?></h1>
			<div class="mcf-admin-card">
				<h2><?php esc_html_e( 'Build the library', 'marcham-recipe-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Add individual recipes from the Recipes menu or import a prepared CSV collection. Keep imported recipes as drafts until ingredients, allergens and cooking instructions have been reviewed.', 'marcham-recipe-plugin' ); ?></p>
				<p>
					<strong><?php esc_html_e( 'Published:', 'marcham-recipe-plugin' ); ?></strong> <?php echo esc_html( absint( $count->publish ?? 0 ) ); ?>
					&nbsp;&nbsp;
					<strong><?php esc_html_e( 'Drafts:', 'marcham-recipe-plugin' ); ?></strong> <?php echo esc_html( absint( $count->draft ?? 0 ) ); ?>
				</p>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . MCF_Recipe_Plugin::POST_TYPE ) ); ?>"><?php esc_html_e( 'Add recipe', 'marcham-recipe-plugin' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mcf-recipe-import' ) ); ?>"><?php esc_html_e( 'Import CSV', 'marcham-recipe-plugin' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=mcf-recipe-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'marcham-recipe-plugin' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	public static function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Recipe Library Settings', 'marcham-recipe-plugin' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'mcf_recipe_settings_group' );
				do_settings_sections( 'mcf-recipe-settings' );
				submit_button();
				?>
			</form>
			<h2><?php esc_html_e( 'Elementor shortcode', 'marcham-recipe-plugin' ); ?></h2>
			<p><?php esc_html_e( 'Add this shortcode to an Elementor Shortcode widget:', 'marcham-recipe-plugin' ); ?></p>
			<code>[mcf_recipes]</code>
		</div>
		<?php
	}

	public static function api_key_field() {
		$settings = self::settings();
		?>
		<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION ); ?>[openai_api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $settings['openai_api_key'] ) ? __( 'Key saved — leave blank to keep it', 'marcham-recipe-plugin' ) : __( 'Paste the API key here', 'marcham-recipe-plugin' ) ); ?>">
		<?php if ( ! empty( $settings['openai_api_key'] ) ) : ?>
			<p class="description mcf-key-status"><?php esc_html_e( 'An API key is saved. Leave the field blank to keep it, or enter a replacement.', 'marcham-recipe-plugin' ); ?></p>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Never add this key to an Elementor page, JavaScript file or public repository.', 'marcham-recipe-plugin' ); ?></p>
		<?php
	}

	public static function model_field() {
		$settings = self::settings();
		printf(
			'<input class="regular-text" type="text" name="%s[openai_model]" value="%s" placeholder="gpt-4o-mini">',
			esc_attr( self::OPTION ),
			esc_attr( $settings['openai_model'] )
		);
	}

	public static function mealdb_key_field() {
		$settings = self::settings();
		$value = $settings['themealdb_api_key'] ?? '';
		printf( '<input class="regular-text" type="password" name="%s[themealdb_api_key]" value="" autocomplete="new-password" placeholder="%s">', esc_attr( self::OPTION ), esc_attr( $value ? 'Key saved — leave blank to keep it' : '1 for development or your supporter key' ) );
		echo '<p class="description">TheMealDB key. Key 1 is for development; a supporter key enables its multi-ingredient API access. Leave blank to use development key 1.</p>';
	}

	public static function provider_fields() {
		$providers = self::settings()['providers'];
		$key_saved = ! empty( $providers['spoonacular_api_key'] );
		?>
		<p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[providers][mealdb_enabled]" value="1" <?php checked( ! empty( $providers['mealdb_enabled'] ) ); ?>> <strong><?php esc_html_e( 'Enable TheMealDB', 'marcham-recipe-plugin' ); ?></strong></label><br><span class="description"><?php esc_html_e( 'Current provider. Keep this enabled for a no-key fallback and a quick rollback.', 'marcham-recipe-plugin' ); ?></span></p>
		<p><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[providers][spoonacular_enabled]" value="1" <?php checked( ! empty( $providers['spoonacular_enabled'] ) ); ?>> <strong><?php esc_html_e( 'Enable Spoonacular', 'marcham-recipe-plugin' ); ?></strong></label><br><span class="description"><?php esc_html_e( 'Preferred over TheMealDB whenever enabled and an API key is saved. It supplies richer descriptions, ingredients and instructions where available.', 'marcham-recipe-plugin' ); ?></span></p>
		<p><label><strong><?php esc_html_e( 'Spoonacular API key', 'marcham-recipe-plugin' ); ?></strong><br><input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION ); ?>[providers][spoonacular_api_key]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( $key_saved ? __( 'Key saved — leave blank to keep it', 'marcham-recipe-plugin' ) : __( 'Paste the Spoonacular API key here', 'marcham-recipe-plugin' ) ); ?>"></label></p>
		<?php if ( $key_saved ) : ?><p class="description mcf-key-status"><?php esc_html_e( 'A Spoonacular API key is saved. Leave the field blank to keep it, or enter a replacement.', 'marcham-recipe-plugin' ); ?></p><?php endif; ?>
		<p class="description"><?php esc_html_e( 'At least one provider must be enabled. Provider keys stay on the server and are never sent to visitors.', 'marcham-recipe-plugin' ); ?></p>
		<?php
	}

	public static function import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = array(
			'created'        => isset( $_GET['mcf_import_created'] ) ? absint( $_GET['mcf_import_created'] ) : null,
			'updated'        => isset( $_GET['mcf_import_updated'] ) ? absint( $_GET['mcf_import_updated'] ) : null,
			'skipped'        => isset( $_GET['mcf_import_skipped'] ) ? absint( $_GET['mcf_import_skipped'] ) : null,
			'failed'         => isset( $_GET['mcf_import_failed'] ) ? absint( $_GET['mcf_import_failed'] ) : null,
			'image_failures' => isset( $_GET['mcf_import_image_failures'] ) ? absint( $_GET['mcf_import_image_failures'] ) : null,
			'warnings'       => isset( $_GET['mcf_import_warnings'] ) ? absint( $_GET['mcf_import_warnings'] ) : null,
		);
		$has_report = null !== $report['created'];
		if ( ! $has_report && isset( $_GET['mcf_imported'] ) ) {
			$report['created'] = absint( $_GET['mcf_imported'] );
			$report['updated'] = 0;
			$report['skipped'] = 0;
			$report['failed']  = 0;
			$report['image_failures'] = 0;
			$report['warnings'] = 0;
			$has_report = true;
		}
		$error  = isset( $_GET['mcf_import_error'] ) ? sanitize_text_field( wp_unslash( $_GET['mcf_import_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Recipes', 'marcham-recipe-plugin' ); ?></h1>
			<?php if ( $has_report ) : ?>
				<div class="notice notice-success">
					<p><strong><?php esc_html_e( 'Import complete.', 'marcham-recipe-plugin' ); ?></strong></p>
					<p><?php echo esc_html( sprintf( __( 'Created: %1$d · Updated: %2$d · Skipped: %3$d · Failed: %4$d', 'marcham-recipe-plugin' ), $report['created'], $report['updated'], $report['skipped'], $report['failed'] ) ); ?></p>
					<?php if ( $report['image_failures'] || $report['warnings'] ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Image downloads failed: %1$d · Rows with warnings: %2$d. Check the affected recipes before publishing.', 'marcham-recipe-plugin' ), $report['image_failures'], $report['warnings'] ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<div class="mcf-admin-card">
				<p><?php esc_html_e( 'Upload a UTF-8 CSV file. New recipes default to draft status unless the CSV contains a valid status column.', 'marcham-recipe-plugin' ); ?></p>
				<p><?php esc_html_e( 'Use || between multiple ingredients or method steps. Use commas or pipes between cuisine, dietary and search-term values.', 'marcham-recipe-plugin' ); ?></p>
				<p><code>title,description,cuisine,meal_type,ingredients,method,prep_time,cook_time,servings,dietary_tags,allergens,storage_advice,search_terms,image_url,image_alt_text,status</code></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="mcf_import_csv">
					<?php wp_nonce_field( 'mcf_import_csv' ); ?>
					<p><input type="file" name="mcf_recipe_csv" accept=".csv,text/csv" required></p>
					<p><label><input type="checkbox" name="mcf_import_update_existing" value="1" checked> <?php esc_html_e( 'Update an existing recipe when the CSV title matches (recommended). Uncheck this only if you intentionally want duplicate recipes.', 'marcham-recipe-plugin' ); ?></label></p>
					<?php submit_button( __( 'Import CSV', 'marcham-recipe-plugin' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public static function import_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to import recipes.', 'marcham-recipe-plugin' ) );
		}
		check_admin_referer( 'mcf_import_csv' );
		if ( empty( $_FILES['mcf_recipe_csv']['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $_FILES['mcf_recipe_csv']['error'] ) {
			self::redirect_import_error( __( 'Please choose a valid CSV file.', 'marcham-recipe-plugin' ) );
		}

		$file = $_FILES['mcf_recipe_csv'];
		if ( ! in_array( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ), array( 'csv' ), true ) ) {
			self::redirect_import_error( __( 'Only CSV files can be imported.', 'marcham-recipe-plugin' ) );
		}

		$handle = fopen( $file['tmp_name'], 'rb' );
		if ( ! $handle ) {
			self::redirect_import_error( __( 'The CSV file could not be read.', 'marcham-recipe-plugin' ) );
		}
		$headers = fgetcsv( $handle );
		if ( ! is_array( $headers ) ) {
			fclose( $handle );
			self::redirect_import_error( __( 'The CSV file has no header row.', 'marcham-recipe-plugin' ) );
		}
		$headers = array_map( array( __CLASS__, 'normalise_header' ), $headers );
		if ( ! in_array( 'title', $headers, true ) ) {
			fclose( $handle );
			self::redirect_import_error( __( 'The CSV must contain a title column.', 'marcham-recipe-plugin' ) );
		}
		$report = array(
			'created'        => 0,
			'updated'        => 0,
			'skipped'        => 0,
			'failed'         => 0,
			'image_failures' => 0,
			'warnings'       => 0,
		);
		$update_existing = ! empty( $_POST['mcf_import_update_existing'] );
		$seen_titles     = array();

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			if ( ! array_filter( $row, 'strlen' ) ) {
				continue;
			}
			$data = array();
			foreach ( $headers as $index => $header ) {
				$data[ $header ] = isset( $row[ $index ] ) ? trim( $row[ $index ] ) : '';
			}
			if ( empty( $data['title'] ) ) {
				$report['skipped']++;
				continue;
			}
			$title = sanitize_text_field( $data['title'] );
			$title_key = strtolower( $title );
			if ( isset( $seen_titles[ $title_key ] ) ) {
				$report['skipped']++;
				$report['warnings']++;
				continue;
			}
			$seen_titles[ $title_key ] = true;
			$status_value = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : '';
			$status_valid = self::valid_status( $status_value );
			if ( '' !== $status_value && ! $status_valid ) {
				$report['warnings']++;
			}
			foreach ( array( 'ingredients', 'method', 'search_terms' ) as $recommended_field ) {
				if ( empty( $data[ $recommended_field ] ) ) {
					$report['warnings']++;
					break;
				}
			}

			$existing_id = $update_existing ? self::find_existing_recipe_by_title( $title ) : 0;
			$post_args   = array(
				'post_type'    => MCF_Recipe_Plugin::POST_TYPE,
				'post_title'   => $title,
				'post_excerpt' => sanitize_textarea_field( $data['description'] ?? '' ),
			);
			if ( $existing_id ) {
				$post_args['ID'] = $existing_id;
				if ( $status_valid ) {
					$post_args['post_status'] = $status_value;
				}
				$post_id = wp_update_post( $post_args, true );
				$action  = 'updated';
			} else {
				$post_args['post_status'] = $status_valid ? $status_value : 'draft';
				$post_id = wp_insert_post( $post_args, true );
				$action  = 'created';
			}
			if ( is_wp_error( $post_id ) ) {
				$report['failed']++;
				continue;
			}
			self::save_imported_fields( $post_id, $data );
			if ( 'created' === $action ) {
				$report['created']++;
			} else {
				$report['updated']++;
			}
			if ( ! empty( $data['image_url'] ) && ( 'created' === $action || ! get_post_thumbnail_id( $post_id ) ) ) {
				if ( ! self::import_image( $post_id, $data['image_url'], $data['image_alt_text'] ?? '' ) ) {
					$report['image_failures']++;
				}
			}
		}
		fclose( $handle );
		wp_safe_redirect( add_query_arg( array(
			'page'                      => 'mcf-recipe-import',
			'mcf_imported'              => $report['created'] + $report['updated'],
			'mcf_import_created'        => $report['created'],
			'mcf_import_updated'        => $report['updated'],
			'mcf_import_skipped'        => $report['skipped'],
			'mcf_import_failed'         => $report['failed'],
			'mcf_import_image_failures' => $report['image_failures'],
			'mcf_import_warnings'       => $report['warnings'],
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function find_existing_recipe_by_title( $title ) {
		if ( null === self::$recipe_title_index ) {
			self::$recipe_title_index = array();
			$posts = get_posts(
				array(
					'post_type'      => MCF_Recipe_Plugin::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $posts as $post_id ) {
				self::$recipe_title_index[ strtolower( trim( get_the_title( $post_id ) ) ) ] = absint( $post_id );
			}
		}
		$key = strtolower( trim( $title ) );
		return isset( self::$recipe_title_index[ $key ] ) ? self::$recipe_title_index[ $key ] : 0;
	}

	private static function save_imported_fields( $post_id, $data ) {
		$meta = array(
			'description' => MCF_Recipe_Plugin::META_DESCRIPTION,
			'prep_time'   => MCF_Recipe_Plugin::META_PREP_TIME,
			'cook_time'   => MCF_Recipe_Plugin::META_COOK_TIME,
			'servings'    => MCF_Recipe_Plugin::META_SERVINGS,
			'allergens'   => MCF_Recipe_Plugin::META_ALLERGENS,
			'storage_advice' => MCF_Recipe_Plugin::META_STORAGE,
			'meal_type'   => MCF_Recipe_Plugin::META_MEAL_TYPE,
			'image_alt_text' => MCF_Recipe_Plugin::META_IMAGE_ALT,
		);
		foreach ( $meta as $key => $meta_key ) {
			update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $data[ $key ] ?? '' ) );
		}
		$ingredients = str_replace( array( '||', ';' ), "\n", $data['ingredients'] ?? '' );
		$method      = str_replace( array( '||', ';' ), "\n", $data['method'] ?? '' );
		update_post_meta( $post_id, MCF_Recipe_Plugin::META_INGREDIENTS, MCF_Recipe_Plugin::normalise_lines( $ingredients ) );
		update_post_meta( $post_id, MCF_Recipe_Plugin::META_METHOD, MCF_Recipe_Plugin::normalise_method_lines( $method ) );
		self::set_terms( $post_id, MCF_Recipe_Plugin::TAX_CUISINE, $data['cuisine'] ?? '' );
		self::set_terms( $post_id, MCF_Recipe_Plugin::TAX_DIETARY, $data['dietary_tags'] ?? '' );
		self::set_terms( $post_id, MCF_Recipe_Plugin::TAX_INGREDIENT, $data['search_terms'] ?? '' );
	}

	private static function import_image( $post_id, $url, $alt ) {
		$url = esc_url_raw( $url );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return false;
		}
		$file = array(
			'name'     => sanitize_file_name( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'recipe-image.jpg' ),
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return false;
		}
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
			update_post_meta( $post_id, MCF_Recipe_Plugin::META_IMAGE_ALT, sanitize_text_field( $alt ) );
		}
		set_post_thumbnail( $post_id, $attachment_id );
		return true;
	}

	private static function set_terms( $post_id, $taxonomy, $value ) {
		$terms = preg_split( '/[,|;\r\n]+/', (string) $value );
		$terms = array_values( array_filter( array_map( 'sanitize_text_field', $terms ) ) );
		wp_set_post_terms( $post_id, $terms, $taxonomy, false );
	}

	private static function valid_status( $status ) {
		return in_array( $status, array( 'draft', 'pending', 'publish' ), true );
	}

	private static function normalise_header( $header ) {
		$header = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header );
		$header = strtolower( trim( $header ) );
		$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
		return trim( $header, '_' );
	}

	private static function redirect_import_error( $message ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'mcf-recipe-import', 'mcf_import_error' => rawurlencode( $message ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
