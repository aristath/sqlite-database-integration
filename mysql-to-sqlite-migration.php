<?php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-lexer.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-query-rewriter.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-translator.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-token.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/wp-includes/sqlite/class-wp-sqlite-db.php';
require_once __DIR__ . '/wp-includes/sqlite/install-functions.php';

global $sqlite_db;
$sqlite_db = new WP_SQLite_DB();

/**
 * Get an array of the tables names from the MySQL database.
 *
 * @return array
 */
function sqlite_integration_get_mysql_tables_names() {
	global $wpdb;
	return array_map( function ( $table ) {
		return array_values( (array) $table )[0];
	}, $wpdb->get_results( 'SHOW TABLES' ) );
}

/**
 * Create a table in the SQLite database.
 *
 * @param string $table_name The name of the table to create.
 * @param string $create_table_query The CREATE TABLE query to execute.
 */
function sqlite_integration_create_table( $table_name ) {
	global $wpdb, $sqlite_db;
	// Get the table structure.
	$table_structure = $wpdb->get_results( "SHOW CREATE TABLE $table_name" );
	// Execute the CREATE TABLE query.
	$sqlite_db->query( $table_structure[0]->{'Create Table'} );
}

/**
 * Migrate data for a single table.
 *
 * @param string $table_name The name of the table to migrate data for.
 */
function sqlite_integration_migrate_table( $table_name ) {
	global $wpdb, $sqlite_db;
	// Get the data from the MySQL table, and insert it into the SQLite table.
	$data = $wpdb->get_results( "SELECT * FROM $table_name" );
	foreach ( $data as $row ) {
		$sqlite_db->insert( $table_name, (array) $row );
	}
}

/**
 * Run AJAX action to migrate a table.
 */
function sqlite_integration_migrate_table_ajax() {
	if ( ! isset( $_POST['table_name'] ) ) {
		wp_send_json_error( 'Table name is required' );
	}
	$table_name = sanitize_text_field( $_POST['table_name'] );
	sqlite_integration_create_table( $table_name );
	sqlite_integration_migrate_table( $table_name );
	wp_send_json_success( 'Table migrated' );
}
add_action( 'wp_ajax_sqlite_integration_migrate_table', 'sqlite_integration_migrate_table_ajax' );

/**
 * Run AJAX action to add the db.php file.
 */
function sqlite_integration_add_db_php_file_ajax() {
	// Check if the sqlite_plugin_copy_db_file function exists.
	if ( ! function_exists( 'sqlite_plugin_copy_db_file' ) ) {
		require_once __DIR__ . '/activate.php';
	}
	sqlite_plugin_copy_db_file();
	wp_send_json_success( 'db.php file added' );
}
add_action( 'wp_ajax_sqlite_integration_add_db_php_file', 'sqlite_integration_add_db_php_file_ajax' );

/**
 * Add a script to the admin page to handle the migration process.
 */
function sqlite_integration_add_admin_script() {
	?>
	<script>
		const databaseTables = <?php echo json_encode( sqlite_integration_get_mysql_tables_names() ); ?>;
		const sqliteMigrateTable = ( tableName ) => {
			jQuery( '#sqlite-migration-status' ).text( 'Migrating ' + tableName + ' table...' );
			jQuery.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'sqlite_integration_migrate_table',
					_wpnonce: '<?php echo wp_create_nonce( 'sqlite-confirm-mysql-migration' ); ?>',
					table_name: tableName
				},
				success: function( response ) {
					// Check if there is a next table to migrate.
					if ( databaseTables.indexOf( tableName ) + 1 >= databaseTables.length ) {
						jQuery( '#sqlite-migration-status' ).text( 'Database migrated. Adding the db.php file in your wp-content folder...' );
						jQuery.ajax( {
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'sqlite_integration_add_db_php_file',
								_wpnonce: '<?php echo wp_create_nonce( 'sqlite-confirm-mysql-migration' ); ?>',
							},
							success: function( response ) {
								jQuery( '#sqlite-migration-status' ).text( 'Database migrated. Refreshing the page...' );
								setTimeout( () => {
									// Remove the confirm-mysql-migration query arg from the URL.
									window.location.href = window.location.href.replace( '&confirm-mysql-migration=1', '' );
								}, 1000 );
							}
						} );
						return;
					}
					// Get the next table name.
					const nextTableName = databaseTables[ databaseTables.indexOf( tableName ) + 1 ];

					// Wait half a second, then migrate the next table.
					setTimeout( () => {
						sqliteMigrateTable( nextTableName );
					}, 500 );
				},
				error: function( response ) {

				}
			} );
		};
		jQuery( document ).ready( function() {
			let tableName = databaseTables[0];
			sqliteMigrateTable( tableName );
		} );
	</script>
	<?php
}
if ( isset( $_GET['confirm-mysql-migration'] ) ) {
	add_action( 'admin_footer', 'sqlite_integration_add_admin_script' );
}
