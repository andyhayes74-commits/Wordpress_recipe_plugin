<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCF_Recipe_Admin {
	const OPTION = 'mcf_recipe_settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_mcf_import_csv', array( __CLASS__, 'import_csv' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	public static function defaults() {
		return array(
			'openai_api_key' => '',
			'openai_model'   => 'gpt-5-mini',
		);
	}

	public static function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function get_openai_key() {
		$settings = self::settings();
		return isset( $settings['openai_api_key'] ) ? trim( (string) $settings['openai_api_key'] ) : '';
	}

	public static function get_openai_model() {
		$settings = self::settings();
		return ! empty( $settings['openai_model'] ) ? sanitize_text_field( $settings['openai_model'] ) : self::defaults()['openai_model'];
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
			__( 'AI recipe adaptation', 'marcham-recipe-plugin' ),
			function () {
				echo '<p>' . esc_html__( 'The key is used only by the server when a visitor requests an adaptation. It is never sent to the browser.', 'marcham-recipe-plugin' ) . '</p>';
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
	}

	public static function sanitize_settings( $input ) {
		$current = self::settings();
		$input   = is_array( $input ) ? $input : array();
		$key     = isset( $input['openai_api_key'] ) ? preg_replace( '/[\r\n\t]/', '', (string) $input['openai_api_key'] ) : '';

		return array(
			'openai_api_key' => '' !== trim( $key ) ? sanitize_text_field( $key ) : $current['openai_api_key'],
			'openai_model'   => isset( $input['openai_model'] ) ? sanitize_text_field( $input['openai_model'] ) : self::defaults()['openai_model'],
		);
	}

	public static function admin_assets( $hook ) {
		if ( false !== strpos( $hook, 'mcf-recipe' ) ) {
			wp_add_inline_style(
				'wp-admin',
				'.mcf-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.mcf-admin-card{max-width:900px;background:#fff;border:1px solid #dcdcde;padding:20px}.mcf-key-status{color:#287c3c;font-weight:600}'
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
			'<input class="regular-text" type="text" name="%s[openai_model]" value="%s" placeholder="gpt-5-mini">',
			esc_attr( self::OPTION ),
			esc_attr( $settings['openai_model'] )
		);
	}

	public static function import_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$result = isset( $_GET['mcf_imported'] ) ? absint( $_GET['mcf_imported'] ) : null;
		$error  = isset( $_GET['mcf_import_error'] ) ? sanitize_text_field( wp_unslash( $_GET['mcf_import_error'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Recipes', 'marcham-recipe-plugin' ); ?></h1>
			<?php if ( null !== $result ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html( sprintf( _n( '%d recipe imported.', '%d recipes imported.', $result, 'marcham-recipe-plugin' ), $result ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<div class="mcf-admin-card">
				<p><?php esc_html_e( 'Upload a UTF-8 CSV file. Imported recipes default to draft status unless the CSV contains a status column.', 'marcham-recipe-plugin' ); ?></p>
				<p><?php esc_html_e( 'Use || between multiple ingredients or method steps. Use commas or pipes between cuisine, dietary and search-term values.', 'marcham-recipe-plugin' ); ?></p>
				<p><code>title,description,cuisine,meal_type,ingredients,method,prep_time,cook_time,servings,dietary_tags,allergens,storage_advice,search_terms,image_url,image_alt_text,status</code></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="mcf_import_csv">
					<?php wp_nonce_field( 'mcf_import_csv' ); ?>
					<p><input type="file" name="mcf_recipe_csv" accept=".csv,text/csv" required></p>
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
		$mime = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
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
		$count   = 0;

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
				continue;
			}
			$status = self::valid_status( $data['status'] ?? '' ) ? $data['status'] : 'draft';
			$post_id = wp_insert_post(
				array(
					'post_type'   => MCF_Recipe_Plugin::POST_TYPE,
					'post_status' => $status,
					'post_title'  => sanitize_text_field( $data['title'] ),
					'post_excerpt'=> sanitize_textarea_field( $data['description'] ?? '' ),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}
			self::save_imported_fields( $post_id, $data );
			if ( ! empty( $data['image_url'] ) ) {
				self::import_image( $post_id, $data['image_url'], $data['image_alt_text'] ?? '' );
			}
			$count++;
		}
		fclose( $handle );
		wp_safe_redirect( add_query_arg( array( 'page' => 'mcf-recipe-import', 'mcf_imported' => $count ), admin_url( 'admin.php' ) ) );
		exit;
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
		update_post_meta( $post_id, MCF_Recipe_Plugin::META_INGREDIENTS, MCF_Recipe_Plugin::normalise_lines( str_replace( '||', "\n", $data['ingredients'] ?? '' ) ) );
		update_post_meta( $post_id, MCF_Recipe_Plugin::META_METHOD, MCF_Recipe_Plugin::normalise_lines( str_replace( '||', "\n", $data['method'] ?? '' ) ) );
		self::set_terms( $post_id, MCF_Recipe_Plugin::TAX_CUISINE, $data['cuisine'] ?? '' );
		self::set_terms( $post_id, MCF_Recipe_Plugin::TAX_DIETARY, $data['dietary_tags'] ?? '' );
		self::set_terms( $post_id, MCF_Recipe_Plugin::TAX_INGREDIENT, $data['search_terms'] ?? '' );
	}

	private static function import_image( $post_id, $url, $alt ) {
		$url = esc_url_raw( $url );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return;
		}
		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return;
		}
		$file = array(
			'name'     => sanitize_file_name( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'recipe-image.jpg' ),
			'tmp_name' => $tmp,
		);
		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return;
		}
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
			update_post_meta( $post_id, MCF_Recipe_Plugin::META_IMAGE_ALT, sanitize_text_field( $alt ) );
		}
		set_post_thumbnail( $post_id, $attachment_id );
	}

	private static function set_terms( $post_id, $taxonomy, $value ) {
		$terms = preg_split( '/[,|\r\n]+/', (string) $value );
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
