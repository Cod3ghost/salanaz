<?php
/**
 * Activation and deactivation routines.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sets up roles, tables, the private upload directory and rewrite rules.
 */
class Salanaz_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		Salanaz_Roles::install();
		Salanaz_Schema::install();
		self::create_private_directory();

		// Post types are registered on init, which has already fired by the
		// time activation runs, so register them once here before flushing.
		( new Salanaz_Post_Types() )->register_post_types();
		( new Salanaz_Post_Types() )->register_taxonomies();
		flush_rewrite_rules();

		// Sign-in, registration and the dashboard live on pages carrying
		// shortcodes. Without them the system has no front door.
		Salanaz_Pages::ensure();

		( new Salanaz_Cron() )->ensure_scheduled();

		// A brand-new site gets sample estates so there is something to look at
		// and edit. Anything already here is left completely alone.
		if ( ! get_option( 'salanaz_demo_seeded' ) && Salanaz_Demo_Data::site_is_empty() ) {
			Salanaz_Demo_Data::seed();
			set_transient( 'salanaz_demo_autoseeded', 1, DAY_IN_SECONDS );
		}

		update_option( 'salanaz_version', SALANAZ_VERSION, false );
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Deliberately does not touch roles, tables or uploads — deactivation is
	 * routine (updates, debugging) and must not destroy client data.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();

		Salanaz_Cron::unschedule();
	}

	/**
	 * Create the non-public directory that payment proofs are stored in, and
	 * lay down the guards that keep it from being served directly.
	 */
	public static function create_private_directory(): void {
		$dir = salanaz_private_dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Apache.
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$htaccess,
				"Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			);
		}

		// IIS.
		$webconfig = $dir . '/web.config';
		if ( ! file_exists( $webconfig ) ) {
			file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				$webconfig,
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n"
			);
		}

		// Nginx and anything else: at minimum stop directory listing.
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}
