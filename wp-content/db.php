<?php
/**
 * Plugin Name: SQLite integration (Drop-in)
 * Version: 1.0.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 *
 * This file is auto-generated and copied from the sqlite plugin.
 * Please don't edit this file directly.
 *
 * @package wp-sqlite-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SQLITE_DROPIN' ) ) {
	define( 'SQLITE_DROPIN', true );
}

// Constant for backward compatibility.
if ( ! defined( 'DATABASE_TYPE' ) ) {
	define( 'DATABASE_TYPE', 'sqlite' );
}
// Define SQLite constant.
if ( ! defined( 'DB_ENGINE' ) ) {
	define( 'DB_ENGINE', 'sqlite' );
}

// Require the implementation from the plugin.
require_once dirname( __DIR__ ) . '/wp-includes/sqlite/db.php';

// Activate the performance-lab plugin if it is not already activated.
add_action(
	'admin_footer',
	function() {
		if ( defined( 'SQLITE_MAIN_FILE' ) ) {
			return;
		}
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_file = str_replace( WP_PLUGIN_DIR . '/', '', SQLITE_MAIN_FILE );
		if ( is_plugin_inactive( $plugin_file ) ) {
			// If `activate_plugin()` returns a value other than null (like WP_Error),
			// the plugin could not be found. Try with a hardcoded string,
			// because that probably means the file was directly copy-pasted.
			if ( null !== activate_plugin( $plugin_file, '', false, true ) ) {
				activate_plugin( 'sqlite-database-integration/load.php', '', false, true );
			}
		}
	}
);
