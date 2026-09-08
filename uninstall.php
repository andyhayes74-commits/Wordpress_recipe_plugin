<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'mcf_recipe_settings' );
delete_transient( 'mcf_recipe_search_debug' );
delete_option( 'mcf_recipe_learning_version' );

global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mcf_recipe_search_learning' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mcf_recipe_meal_clicks' );
