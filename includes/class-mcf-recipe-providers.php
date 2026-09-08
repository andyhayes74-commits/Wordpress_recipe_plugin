<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Chooses the enabled public recipe provider. Spoonacular is preferred when both are enabled. */
class MCF_Recipe_Providers {
	public static function active() {
		if ( MCF_Recipe_Admin::provider_enabled( 'spoonacular' ) && MCF_Recipe_Spoonacular::is_available() ) {
			return MCF_Recipe_Spoonacular::class;
		}
		if ( MCF_Recipe_Admin::provider_enabled( 'mealdb' ) ) {
			return MCF_Recipe_MealDB::class;
		}
		return new WP_Error( 'recipe_provider_disabled', 'No recipe provider is enabled. Enable TheMealDB or Spoonacular in Recipe Library settings.' );
	}

	public static function recipe( $id ) {
		$id = self::normalise_id( $id );
		if ( 0 === strpos( $id, 'spoonacular:' ) ) {
			if ( ! MCF_Recipe_Admin::provider_enabled( 'spoonacular' ) ) {
				return new WP_Error( 'recipe_provider_disabled', 'Spoonacular is disabled.' );
			}
			return MCF_Recipe_Spoonacular::recipe( $id );
		}
		if ( 0 === strpos( $id, 'mealdb:' ) ) {
			if ( ! MCF_Recipe_Admin::provider_enabled( 'mealdb' ) ) {
				return new WP_Error( 'recipe_provider_disabled', 'TheMealDB is disabled.' );
			}
			$meal = MCF_Recipe_MealDB::meal( $id );
			return is_wp_error( $meal ) ? $meal : MCF_Recipe_MealDB::to_recipe( $meal );
		}
		return new WP_Error( 'recipe_invalid_id', 'Invalid recipe ID.' );
	}

	public static function normalise_id( $id ) {
		$id = trim( (string) $id );
		if ( preg_match( '/^(?:mealdb|spoonacular):\d+$/', $id ) ) {
			return $id;
		}
		return ctype_digit( $id ) ? 'mealdb:' . $id : '';
	}
}
