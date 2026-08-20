<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 *
 * Data destruction is opt-in: nothing is removed unless the site owner has set
 * the salanaz_delete_data_on_uninstall option. Client financial records should
 * survive an accidental delete.
 *
 * @package Salanaz
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! get_option( 'salanaz_delete_data_on_uninstall' ) ) {
	return;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/functions-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-salanaz-roles.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-salanaz-schema.php';

Salanaz_Roles::uninstall();
Salanaz_Schema::drop_tables();

foreach ( array( 'salanaz_version', 'salanaz_private_dir_key', 'salanaz_delete_data_on_uninstall' ) as $option ) {
	delete_option( $option );
}
