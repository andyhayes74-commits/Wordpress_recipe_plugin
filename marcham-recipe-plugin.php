<?php
/**
 * Plugin Name: Marcham Community Fridge Recipe Library
 * Description: Searchable recipe library with CSV import, in-page recipe viewing and optional AI adaptation.
 * Version: 0.2.3
 * Author: Marcham Community Fridge
 * License: GPL-2.0-or-later
 * Text Domain: marcham-recipe-plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCF_RECIPE_VERSION', '0.2.3' );
define( 'MCF_RECIPE_FILE', __FILE__ );
define( 'MCF_RECIPE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MCF_RECIPE_URL', plugin_dir_url( __FILE__ ) );

require_once MCF_RECIPE_PATH . 'includes/class-mcf-recipe-plugin.php';
require_once MCF_RECIPE_PATH . 'includes/class-mcf-recipe-admin.php';
require_once MCF_RECIPE_PATH . 'includes/class-mcf-recipe-rest.php';

register_activation_hook( __FILE__, array( 'MCF_Recipe_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MCF_Recipe_Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		MCF_Recipe_Plugin::instance();
	}
);
