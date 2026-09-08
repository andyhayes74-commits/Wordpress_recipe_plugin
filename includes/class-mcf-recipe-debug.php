<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bounded, opt-in search diagnostics. Never store HTTP bodies or credentials. */
class MCF_Recipe_Debug {
	const LOG_KEY = 'mcf_recipe_search_debug';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_mcf_clear_search_debug', array( __CLASS__, 'clear' ) );
	}

	public static function entries() {
		$entries = get_transient( self::LOG_KEY );
		return is_array( $entries ) ? array_values( array_filter( $entries, function ( $entry ) {
			return isset( $entry['time'] ) && $entry['time'] > time() - DAY_IN_SECONDS;
		} ) ) : array();
	}

	public static function record( $search, $details ) {
		if ( empty( MCF_Recipe_Admin::settings()['debug_search'] ) ) {
			return;
		}
		// Redact key-like strings even if a visitor pastes one into the search box.
		$search = preg_replace( '/\bsk-[A-Za-z0-9_-]+/', '[redacted]', (string) $search );
		$key = MCF_Recipe_Admin::get_openai_key();
		if ( $key ) {
			$search = str_replace( $key, '[redacted]', $search );
		}
		$entry = array( 'time' => time(), 'search' => substr( sanitize_text_field( $search ), 0, 200 ) );
		$model = MCF_Recipe_Admin::get_openai_model();
		$entry['model'] = preg_match( '/^(?:gpt|o[1-9])[-a-z0-9.]*$/i', $model ) ? $model : 'custom_model';
		foreach ( array( 'source', 'reason' ) as $field ) {
			$entry[ $field ] = isset( $details[ $field ] ) ? sanitize_key( $details[ $field ] ) : '';
		}
		foreach ( array( 'duration_ms', 'ai_duration_ms', 'http_status', 'page', 'candidate_count' ) as $field ) {
			$entry[ $field ] = isset( $details[ $field ] ) ? absint( $details[ $field ] ) : 0;
		}
		foreach ( array( 'candidate_ids', 'result_ids', 'other_ids' ) as $field ) {
			$entry[ $field ] = array_slice( array_map( 'absint', isset( $details[ $field ] ) ? $details[ $field ] : array() ), 0, 30 );
		}
		foreach ( array( 'candidate_titles', 'result_titles', 'other_titles' ) as $field ) {
			$values = isset( $details[ $field ] ) && is_array( $details[ $field ] ) ? $details[ $field ] : array();
			$entry[ $field ] = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $values ) ) ) ), 0, 30 );
		}
		$signals = isset( $details['candidate_signals'] ) && is_array( $details['candidate_signals'] ) ? $details['candidate_signals'] : array();
		$entry['candidate_signals'] = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $signals ) ) ) ), 0, 30 );
		foreach ( array( 'normalised_terms', 'unmatched_terms' ) as $field ) {
			$terms = isset( $details[ $field ] ) && is_array( $details[ $field ] ) ? $details[ $field ] : array();
			$entry[ $field ] = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $terms ) ) ) ), 0, 20 );
		}
		$entries = self::entries();
		array_unshift( $entries, $entry );
		set_transient( self::LOG_KEY, array_slice( $entries, 0, 50 ), DAY_IN_SECONDS );
	}

	public static function menu() {
		add_submenu_page( 'mcf-recipe-library', 'Search diagnostics', 'Search diagnostics', 'manage_options', 'mcf-recipe-debug', array( __CLASS__, 'page' ) );
	}

	public static function clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not authorised.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'mcf_clear_search_debug' );
		delete_transient( self::LOG_KEY );
		wp_safe_redirect( admin_url( 'admin.php?page=mcf-recipe-debug' ) );
		exit;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>Recipe search diagnostics</h1><p>Logging is <strong>' . ( empty( MCF_Recipe_Admin::settings()['debug_search'] ) ? 'OFF' : 'ON' ) . '</strong>. Enable it in Recipe Library → Settings, run searches on the recipe page, then refresh this page.</p>';
		echo '<p>Latest 50 searches, retained for up to 24 hours. Search text is recorded while enabled; avoid personal information. No API keys, IP addresses or raw AI responses are recorded. Logs are best-effort and may omit simultaneous requests.</p>';
		echo '<p><strong>Sources:</strong> ai = fresh OpenAI response; learned = saved AI decision (including zero matches); local = title/curated-term search when no OpenAI key is configured; ai_error = an AI search that could not complete. Diagnostics also show the safe, interpreted terms and unmatched terms. A successful zero-match decision never falls back. Candidate IDs show what AI could choose; they are capped at 30.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="mcf_clear_search_debug">';
		wp_nonce_field( 'mcf_clear_search_debug' );
		submit_button( 'Clear debug log', 'secondary' );
		echo '</form>';
		$entries = self::entries();
		if ( ! $entries ) {
			echo '<p>No searches recorded yet.</p>';
		}
		foreach ( $entries as $entry ) {
			echo '<details style="background:#fff;padding:12px;margin-bottom:8px"><summary>' . esc_html( gmdate( 'Y-m-d H:i:s', $entry['time'] ) . ' UTC — ' . $entry['search'] . ' — ' . $entry['source'] . ' / ' . $entry['reason'] . ' — ' . $entry['duration_ms'] . ' ms' ) . '</summary>';
			echo '<pre style="white-space:pre-wrap">' . esc_html( wp_json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre>';
			foreach ( array( 'candidate_ids' => 'Candidates', 'result_ids' => 'Strong matches', 'other_ids' => 'Other matches' ) as $field => $label ) {
				echo '<p><strong>' . esc_html( $label ) . '</strong>: ';
				$names = array();
				foreach ( $entry[ $field ] as $id ) {
					$names[] = (string) $id;
				}
				echo esc_html( implode( '; ', $names ) ) . '</p>';
			}
			foreach ( array( 'candidate_titles' => 'Candidate titles', 'result_titles' => 'Strong-match titles', 'other_titles' => 'Other-match titles' ) as $field => $label ) {
				if ( empty( $entry[ $field ] ) ) {
					continue;
				}
				echo '<p><strong>' . esc_html( $label ) . '</strong>: ' . esc_html( implode( '; ', $entry[ $field ] ) ) . '</p>';
			}
			if ( ! empty( $entry['candidate_signals'] ) ) {
				echo '<p><strong>Candidate match signals</strong>: ' . esc_html( implode( '; ', $entry['candidate_signals'] ) ) . '</p>';
			}
			echo '</details>';
		}
		echo '</div>';
	}
}
