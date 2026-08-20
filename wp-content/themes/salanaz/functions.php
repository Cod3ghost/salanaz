<?php
/**
 * Salanaz theme setup.
 *
 * Presentation only. Roles, post types, payments and reporting belong to the
 * Salanaz Estate Management plugin so the theme can be replaced without taking
 * the estate management system down with it.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

define( 'SALANAZ_THEME_VERSION', '1.0.0' );

require_once get_theme_file_path( 'inc/template-functions.php' );
require_once get_theme_file_path( 'inc/customizer.php' );
require_once get_theme_file_path( 'inc/plugin-installer.php' );

/**
 * Theme supports and menus.
 */
function salanaz_theme_setup(): void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 120,
		'width'       => 120,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'salanaz' ),
		'footer'  => __( 'Footer Menu', 'salanaz' ),
		'legal'   => __( 'Legal Menu', 'salanaz' ),
	) );

	add_image_size( 'salanaz-card', 720, 450, true );
	add_image_size( 'salanaz-hero', 1600, 900, true );
}
add_action( 'after_setup_theme', 'salanaz_theme_setup' );

/**
 * Front-end assets.
 */
function salanaz_enqueue_assets(): void {
	$css = get_theme_file_path( 'assets/css/main.css' );

	wp_enqueue_style(
		'salanaz-main',
		get_theme_file_uri( 'assets/css/main.css' ),
		array(),
		file_exists( $css ) ? (string) filemtime( $css ) : SALANAZ_THEME_VERSION
	);

	$js = get_theme_file_path( 'assets/js/main.js' );

	wp_enqueue_script(
		'salanaz-main',
		get_theme_file_uri( 'assets/js/main.js' ),
		array(),
		file_exists( $js ) ? (string) filemtime( $js ) : SALANAZ_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'salanaz_enqueue_assets' );

/**
 * Site logo, falling back to the bundled brand mark.
 *
 * @return string Escaped <img> markup, or an empty string.
 */
function salanaz_logo_markup(): string {
	$custom_logo_id = get_theme_mod( 'custom_logo' );

	if ( $custom_logo_id ) {
		return wp_get_attachment_image( $custom_logo_id, 'full', false, array( 'alt' => get_bloginfo( 'name' ) ) );
	}

	$fallback = get_theme_file_path( 'assets/images/logo.png' );

	if ( file_exists( $fallback ) ) {
		return sprintf(
			'<img src="%s" alt="%s" width="120" height="120" />',
			esc_url( get_theme_file_uri( 'assets/images/logo.png' ) ),
			esc_attr( get_bloginfo( 'name' ) )
		);
	}

	return '';
}

/**
 * Menu shown before the client has built a primary menu in the Customizer.
 *
 * Keeps a fresh install navigable instead of rendering an empty header.
 */
function salanaz_fallback_menu(): void {
	$items = array(
		home_url( '/' )          => __( 'Home', 'salanaz' ),
		home_url( '/estates/' )  => __( 'Estates', 'salanaz' ),
		home_url( '/about/' )    => __( 'About', 'salanaz' ),
		home_url( '/blog/' )     => __( 'News', 'salanaz' ),
		home_url( '/faq/' )      => __( 'FAQ', 'salanaz' ),
		home_url( '/contact/' )  => __( 'Contact', 'salanaz' ),
	);

	echo '<ul class="slz-menu">';

	foreach ( $items as $url => $label ) {
		printf(
			'<li><a href="%s">%s</a></li>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	echo '</ul>';
}

/**
 * Whether the estate management plugin is active.
 *
 * Templates use this to degrade gracefully rather than fatal when the theme is
 * running without its companion plugin.
 *
 * @return bool
 */
function salanaz_plugin_active(): bool {
	return function_exists( 'salanaz' ) && class_exists( 'Salanaz_Post_Types' );
}
