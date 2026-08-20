<?php
/**
 * Template loader.
 *
 * Templates ship with the plugin so the system works under any theme, but the
 * active theme can override any of them by placing a file of the same name in
 * a `salanaz/` folder — the same convention WooCommerce uses.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Locates and renders plugin templates.
 */
class Salanaz_Templates {

	/**
	 * Resolve a template path, preferring the theme's override.
	 *
	 * @param string $name Template file name, e.g. 'auth/login.php'.
	 * @return string Absolute path, or an empty string when not found.
	 */
	public static function locate( string $name ): string {
		$name = ltrim( str_replace( '..', '', $name ), '/' );

		$theme = locate_template( array( 'salanaz/' . $name ) );

		if ( $theme ) {
			return $theme;
		}

		$plugin = SALANAZ_PLUGIN_DIR . 'templates/' . $name;

		return file_exists( $plugin ) ? $plugin : '';
	}

	/**
	 * Render a template and return its output.
	 *
	 * @param string               $name Template file name.
	 * @param array<string, mixed> $vars Variables extracted into scope.
	 * @return string
	 */
	public static function render( string $name, array $vars = array() ): string {
		$path = self::locate( $name );

		if ( ! $path ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- deliberate template scoping.
		extract( $vars, EXTR_SKIP );

		ob_start();
		include $path;

		return (string) ob_get_clean();
	}

	/**
	 * Render a template directly to output.
	 *
	 * @param string               $name Template file name.
	 * @param array<string, mixed> $vars Variables extracted into scope.
	 */
	public static function output( string $name, array $vars = array() ): void {
		echo self::render( $name, $vars ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates escape their own output.
	}

	/**
	 * A styled notice block.
	 *
	 * @param string $message Message text, already translated.
	 * @param string $type    info|success|warning|error.
	 * @return string
	 */
	public static function notice( string $message, string $type = 'info' ): string {
		return sprintf(
			'<div class="slz-notice slz-notice--%s">%s</div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}
}
