<?php
/**
 * Plugin Name:       Salanaz Estate Management
 * Plugin URI:        https://salanaz.com
 * Description:       Estate management system for Salanaz Global Services Ltd — roles, estates & plots, transactions, installments, payment verification and reporting.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            Salanaz Global Services Ltd
 * License:           GPL-2.0-or-later
 * Text Domain:       salanaz
 * Domain Path:       /languages
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version. Bumped whenever the database schema changes so that
 * Salanaz_Schema can run its upgrade routine on the next admin request.
 */
define( 'SALANAZ_VERSION', '1.0.0' );

/** Schema version — tracked separately from the plugin version. */
define( 'SALANAZ_DB_VERSION', '1.0.0' );

define( 'SALANAZ_PLUGIN_FILE', __FILE__ );
define( 'SALANAZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SALANAZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SALANAZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SALANAZ_PLUGIN_DIR . 'includes/functions-helpers.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-roles.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-schema.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-post-types.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-inventory.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-query.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-profile.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-templates.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-activity.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-notifications.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-users.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-auth.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-plans.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-transactions.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-uploads.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-dashboard.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-verification.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-notes.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-paystack.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-pdf.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-documents.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-cron.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-mail-log.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-reports.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-pages.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-updater.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-cli.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-demo-data.php';

if ( is_admin() ) {
	require_once SALANAZ_PLUGIN_DIR . 'includes/admin/class-salanaz-admin.php';
	require_once SALANAZ_PLUGIN_DIR . 'includes/admin/class-salanaz-metaboxes.php';
}
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-activator.php';
require_once SALANAZ_PLUGIN_DIR . 'includes/class-salanaz-plugin.php';

register_activation_hook( __FILE__, array( 'Salanaz_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Salanaz_Activator', 'deactivate' ) );

/**
 * Main plugin instance.
 *
 * @return Salanaz_Plugin
 */
function salanaz(): Salanaz_Plugin {
	return Salanaz_Plugin::instance();
}

salanaz()->boot();
