<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Stores AI decisions and popularity signals for external TheMealDB meals. */
class MCF_Recipe_Learning {
	const OPTION_VERSION = 'mcf_recipe_learning_version';
	const VERSION = '2.1';
	const POLICY_VERSION = '3.3';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mcf_clear_search_learning', array( __CLASS__, 'clear' ) );
		self::maybe_upgrade();
	}

	public static function activate() { self::create_tables(); update_option( self::OPTION_VERSION, self::VERSION, false ); }
	public static function maybe_upgrade() { if ( self::VERSION !== get_option( self::OPTION_VERSION ) ) { self::create_tables(); update_option( self::OPTION_VERSION, self::VERSION, false ); } }
	public static function table() { global $wpdb; return $wpdb->prefix . 'mcf_recipe_search_learning'; }
	public static function clicks_table() { global $wpdb; return $wpdb->prefix . 'mcf_recipe_meal_clicks'; }

	private static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$learning = self::table();
		$clicks = self::clicks_table();
		dbDelta( "CREATE TABLE {$learning} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, query_key char(64) NOT NULL, query_text text NOT NULL, semantic_terms longtext NOT NULL, unmatched_terms longtext NOT NULL, strong_meal_ids longtext NOT NULL, other_meal_ids longtext NOT NULL, strong_titles longtext NOT NULL, other_titles longtext NOT NULL, candidate_fingerprint char(64) NOT NULL, model varchar(100) NOT NULL, policy_version varchar(32) NOT NULL, candidate_count int(10) unsigned NOT NULL DEFAULT 0, ai_duration_ms int(10) unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, last_used_at datetime NOT NULL, hit_count bigint(20) unsigned NOT NULL DEFAULT 0, PRIMARY KEY (id), UNIQUE KEY query_key (query_key), KEY last_used_at (last_used_at)) {$charset};" );
		dbDelta( "CREATE TABLE {$clicks} (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, query_key char(64) NOT NULL, meal_id varchar(32) NOT NULL, meal_title text NOT NULL, click_count bigint(20) unsigned NOT NULL DEFAULT 0, first_clicked_at datetime NOT NULL, last_clicked_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY query_meal (query_key, meal_id), KEY meal_id (meal_id), KEY click_count (click_count)) {$charset};" );
	}

	public static function key( $terms, $cuisine = '' ) {
		$terms = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $terms ) ) ) );
		sort( $terms, SORT_STRING );
		return hash( 'sha256', wp_json_encode( array( 'terms' => $terms, 'cuisine' => sanitize_key( $cuisine ) ) ) );
	}

	public static function find( $key, $fingerprint, $model ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE query_key = %s', $key ), ARRAY_A );
		if ( ! $row || $row['candidate_fingerprint'] !== $fingerprint || $row['model'] !== $model || $row['policy_version'] !== self::POLICY_VERSION ) { return null; }
		$now = current_time( 'mysql', true );
		$wpdb->update( self::table(), array( 'last_used_at' => $now, 'hit_count' => absint( $row['hit_count'] ) + 1 ), array( 'id' => absint( $row['id'] ) ), array( '%s', '%d' ), array( '%d' ) );
		return array( 'recipe_ids' => self::ids( $row['strong_meal_ids'] ), 'other_recipe_ids' => self::ids( $row['other_meal_ids'] ), 'normalised_terms' => self::strings( $row['semantic_terms'] ), 'unmatched_terms' => self::strings( $row['unmatched_terms'] ), 'source' => 'learned', 'ai_duration_ms' => absint( $row['ai_duration_ms'] ) );
	}

	public static function store( $key, $query_text, $fingerprint, $model, $candidate_count, $decision, $titles = array() ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$data = array( 'query_key' => $key, 'query_text' => sanitize_text_field( $query_text ), 'semantic_terms' => wp_json_encode( self::clean_strings( $decision['normalised_terms'] ?? array() ) ), 'unmatched_terms' => wp_json_encode( self::clean_strings( $decision['unmatched_terms'] ?? array() ) ), 'strong_meal_ids' => wp_json_encode( self::ids( $decision['recipe_ids'] ?? array() ) ), 'other_meal_ids' => wp_json_encode( self::ids( $decision['other_recipe_ids'] ?? array() ) ), 'strong_titles' => wp_json_encode( self::clean_strings( $titles['strong'] ?? array() ) ), 'other_titles' => wp_json_encode( self::clean_strings( $titles['other'] ?? array() ) ), 'candidate_fingerprint' => $fingerprint, 'model' => sanitize_text_field( $model ), 'policy_version' => self::POLICY_VERSION, 'candidate_count' => absint( $candidate_count ), 'ai_duration_ms' => absint( $decision['ai_duration_ms'] ?? 0 ), 'created_at' => $now, 'updated_at' => $now, 'last_used_at' => $now, 'hit_count' => 0 );
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE query_key = %s', $key ) );
		if ( $existing ) { unset( $data['created_at'] ); $wpdb->update( self::table(), $data, array( 'id' => absint( $existing ) ) ); return; }
		$wpdb->insert( self::table(), $data );
	}

	public static function record_click( $query_key, $meal_id, $meal_title ) {
		global $wpdb;
		$meal_id = preg_replace( '/[^0-9]/', '', (string) $meal_id );
		if ( '' === $meal_id ) { return; }
		$now = current_time( 'mysql', true );
		foreach ( array( substr( preg_replace( '/[^a-f0-9]/', '', (string) $query_key ), 0, 64 ), '*' ) as $scope ) {
			if ( '' === $scope ) { continue; }
			$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT id, click_count FROM ' . self::clicks_table() . ' WHERE query_key = %s AND meal_id = %s', $scope, $meal_id ), ARRAY_A );
			if ( $existing ) { $wpdb->update( self::clicks_table(), array( 'click_count' => absint( $existing['click_count'] ) + 1, 'meal_title' => sanitize_text_field( $meal_title ), 'last_clicked_at' => $now ), array( 'id' => absint( $existing['id'] ) ) ); }
			else { $wpdb->insert( self::clicks_table(), array( 'query_key' => $scope, 'meal_id' => $meal_id, 'meal_title' => sanitize_text_field( $meal_title ), 'click_count' => 1, 'first_clicked_at' => $now, 'last_clicked_at' => $now ) ); }
		}
	}

	public static function popularity( $query_key, $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( function ( $id ) { return preg_replace( '/[^0-9]/', '', (string) $id ); }, (array) $ids ) ) );
		if ( ! $ids ) { return array(); }
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
		$params = array_merge( array( $query_key, '*' ), $ids );
		$query = 'SELECT query_key, meal_id, click_count FROM ' . self::clicks_table() . ' WHERE query_key IN (%s, %s) AND meal_id IN (' . $placeholders . ')';
		$prepared = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $params ) );
		$rows = $wpdb->get_results( $prepared, ARRAY_A );
		$scores = array_fill_keys( $ids, 0 );
		foreach ( $rows as $row ) { $scores[ $row['meal_id'] ] = ( $scores[ $row['meal_id'] ] ?? 0 ) + ( '*' === $row['query_key'] ? absint( $row['click_count'] ) * 0.25 : absint( $row['click_count'] ) ); }
		return $scores;
	}

	public static function order_by_popularity( $ids, $query_key ) {
		$scores = self::popularity( $query_key, $ids );
		$position = array_flip( array_map( 'strval', $ids ) );
		usort( $ids, function ( $a, $b ) use ( $scores, $position ) { $a_score = $scores[ (string) $a ] ?? 0; $b_score = $scores[ (string) $b ] ?? 0; return $a_score === $b_score ? ( $position[ (string) $a ] <=> $position[ (string) $b ] ) : ( $a_score < $b_score ? 1 : -1 ); } );
		return $ids;
	}

	private static function ids( $items ) {
		if ( is_string( $items ) ) { $items = json_decode( $items, true ); }
		$items = array_map( function ( $id ) { return preg_replace( '/[^0-9]/', '', (string) $id ); }, (array) $items );
		return array_values( array_unique( array_filter( $items ) ) );
	}
	private static function strings( $json ) { $items = is_string( $json ) ? json_decode( $json, true ) : $json; return self::clean_strings( is_array( $items ) ? $items : array() ); }
	private static function clean_strings( $items ) { $items = array_map( 'sanitize_text_field', (array) $items ); return array_values( array_unique( array_filter( array_map( 'strtolower', $items ) ) ) ); }

	public static function menu() { add_submenu_page( 'mcf-recipe-library', 'Search learning', 'Search learning', 'manage_options', 'mcf-recipe-learning', array( __CLASS__, 'page' ) ); }
	public static function clear() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not authorised.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'mcf_clear_search_learning' );
		global $wpdb; $wpdb->query( 'DELETE FROM ' . self::table() ); $wpdb->query( 'DELETE FROM ' . self::clicks_table() );
		wp_safe_redirect( admin_url( 'admin.php?page=mcf-recipe-learning&cleared=1' ) ); exit;
	}
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY last_used_at DESC LIMIT 100', ARRAY_A );
		$popular = $wpdb->get_results( 'SELECT meal_id, meal_title, SUM(click_count) AS clicks FROM ' . self::clicks_table() . ' GROUP BY meal_id, meal_title ORDER BY clicks DESC LIMIT 20', ARRAY_A );
		echo '<div class="wrap"><h1>Recipe search learning</h1><p>AI suitability decisions reference TheMealDB meal IDs. Click counts are tracked per search and globally, then used only to order recipes that are already suitable.</p>';
		if ( isset( $_GET['cleared'] ) ) { echo '<div class="notice notice-success"><p>Learned searches and click counts cleared.</p></div>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcf_clear_search_learning">'; wp_nonce_field( 'mcf_clear_search_learning' ); submit_button( 'Clear learned searches and popularity', 'delete' ); echo '</form>';
		echo '<h2>Most-clicked meals</h2><table class="widefat striped"><thead><tr><th>Meal ID</th><th>Meal</th><th>Clicks</th></tr></thead><tbody>';
		foreach ( $popular as $row ) { echo '<tr><td>' . esc_html( $row['meal_id'] ) . '</td><td>' . esc_html( $row['meal_title'] ) . '</td><td>' . esc_html( $row['clicks'] ) . '</td></tr>'; }
		echo '</tbody></table><h2>Learned search decisions</h2>';
		if ( ! $rows ) { echo '<p>No learned searches yet.</p></div>'; return; }
		echo '<table class="widefat striped"><thead><tr><th>Search</th><th>Interpreted as</th><th>AI time</th><th>Uses</th></tr></thead><tbody>';
		foreach ( $rows as $row ) { echo '<tr><td>' . esc_html( $row['query_text'] ) . '</td><td>' . esc_html( implode( ', ', self::strings( $row['semantic_terms'] ) ) ) . '</td><td>' . esc_html( absint( $row['ai_duration_ms'] ) . ' ms' ) . '</td><td>' . esc_html( absint( $row['hit_count'] ) ) . '</td></tr>'; }
		echo '</tbody></table></div>';
	}
}
