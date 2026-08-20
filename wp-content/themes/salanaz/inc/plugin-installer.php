<?php
/**
 * Bundled plugin installer.
 *
 * The estate system lives in a separate plugin so the theme can be changed
 * later without taking client records with it. That separation is worth
 * keeping, but it should not mean two uploads — so the theme carries a copy of
 * the plugin and installs it on activation.
 *
 * If the filesystem is not writable the theme says so plainly and explains the
 * manual route, rather than failing quietly.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/** Where the bundled copy lives inside the theme. */
define( 'SALANAZ_BUNDLED_PLUGIN', 'bundled/salanaz-estate' );

/** The plugin's entry file, relative to the plugins directory. */
define( 'SALANAZ_PLUGIN_ENTRY', 'salanaz-estate/salanaz-estate.php' );

/**
 * Install and activate the bundled plugin when the theme is switched on.
 */
function salanaz_install_bundled_plugin(): void {
	if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$installed = WP_PLUGIN_DIR . '/' . SALANAZ_PLUGIN_ENTRY;

	// Already there — just make sure it is switched on.
	if ( file_exists( $installed ) ) {
		salanaz_activate_plugin_if_needed();
		return;
	}

	$source = get_theme_file_path( SALANAZ_BUNDLED_PLUGIN );

	if ( ! is_dir( $source ) ) {
		set_transient( 'salanaz_plugin_install_error', __( 'The bundled plugin is missing from the theme.', 'salanaz' ), HOUR_IN_SECONDS );
		return;
	}

	if ( ! wp_is_writable( WP_PLUGIN_DIR ) ) {
		set_transient(
			'salanaz_plugin_install_error',
			__( 'The plugins folder is not writable, so the estate system could not be installed automatically.', 'salanaz' ),
			HOUR_IN_SECONDS
		);
		return;
	}

	if ( ! salanaz_copy_directory( $source, WP_PLUGIN_DIR . '/salanaz-estate' ) ) {
		set_transient(
			'salanaz_plugin_install_error',
			__( 'The estate system could not be copied into the plugins folder.', 'salanaz' ),
			HOUR_IN_SECONDS
		);
		return;
	}

	salanaz_activate_plugin_if_needed();
}
add_action( 'after_switch_theme', 'salanaz_install_bundled_plugin' );

/**
 * Activate the plugin if it is installed but inactive.
 */
function salanaz_activate_plugin_if_needed(): void {
	if ( is_plugin_active( SALANAZ_PLUGIN_ENTRY ) ) {
		return;
	}

	$result = activate_plugin( SALANAZ_PLUGIN_ENTRY );

	if ( is_wp_error( $result ) ) {
		set_transient( 'salanaz_plugin_install_error', $result->get_error_message(), HOUR_IN_SECONDS );
		return;
	}

	set_transient( 'salanaz_plugin_installed', 1, HOUR_IN_SECONDS );
}

/**
 * Recursively copy a directory.
 *
 * Uses plain filesystem calls rather than WP_Filesystem because this runs
 * during theme activation, where credentials cannot be prompted for.
 *
 * @param string $source      Source directory.
 * @param string $destination Destination directory.
 * @return bool
 */
function salanaz_copy_directory( string $source, string $destination ): bool {
	if ( ! is_dir( $source ) ) {
		return false;
	}

	if ( ! is_dir( $destination ) && ! wp_mkdir_p( $destination ) ) {
		return false;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $items as $item ) {
		$target = $destination . DIRECTORY_SEPARATOR . $items->getSubPathName();

		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
				return false;
			}

			continue;
		}

		if ( ! copy( $item->getPathname(), $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			return false;
		}
	}

	return true;
}

/**
 * Tell the site owner what happened.
 */
function salanaz_plugin_install_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$error = get_transient( 'salanaz_plugin_install_error' );

	if ( $error ) {
		delete_transient( 'salanaz_plugin_install_error' );

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p><p>%s</p></div>',
			esc_html__( 'Salanaz:', 'salanaz' ),
			esc_html( (string) $error ),
			sprintf(
				/* translators: %s: plugins screen URL */
				wp_kses_post( __( 'Install <code>salanaz-estate.zip</code> by hand from <a href="%s">Plugins → Add New</a>. Without it, estates, payments and dashboards will not work.', 'salanaz' ) ),
				esc_url( admin_url( 'plugin-install.php' ) )
			)
		);

		return;
	}

	if ( get_transient( 'salanaz_plugin_installed' ) ) {
		delete_transient( 'salanaz_plugin_installed' );

		printf(
			'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Salanaz is ready.', 'salanaz' ),
			esc_html__( 'The estate management system was installed and switched on automatically.', 'salanaz' )
		);

		return;
	}

	// Ongoing reminder if the plugin is somehow not running.
	if ( ! salanaz_plugin_active() ) {
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Salanaz:', 'salanaz' ),
			esc_html__( 'The estate management plugin is not active, so estates, payments and dashboards are unavailable.', 'salanaz' )
		);
	}
}
add_action( 'admin_notices', 'salanaz_plugin_install_notice' );
