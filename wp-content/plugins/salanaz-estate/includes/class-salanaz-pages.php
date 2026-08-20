<?php
/**
 * Required pages.
 *
 * The client and staff experience lives on ordinary pages carrying shortcodes.
 * On a fresh install those pages do not exist, and without them sign-in,
 * registration and the dashboard all 404 — so the plugin creates them itself on
 * activation rather than relying on somebody remembering to.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and tracks the pages the system depends on.
 */
class Salanaz_Pages {

	/** Option mapping page slug => post ID. */
	private const OPTION = 'salanaz_pages';

	/**
	 * The pages the system needs, and what goes in them.
	 *
	 * @return array<string, array{title:string, content:string}>
	 */
	public static function required(): array {
		return array(
			'login'     => array(
				'title'   => __( 'Sign In', 'salanaz' ),
				'content' => '[salanaz_login]',
			),
			'register'  => array(
				'title'   => __( 'Create an Account', 'salanaz' ),
				'content' => '[salanaz_register]',
			),
			'dashboard' => array(
				'title'   => __( 'My Dashboard', 'salanaz' ),
				'content' => '[salanaz_dashboard]',
			),
		);
	}

	/**
	 * Create any missing page. Safe to run repeatedly.
	 *
	 * An existing page at the same slug is adopted rather than duplicated, and
	 * its content is left alone — the site owner may have added copy around the
	 * shortcode.
	 *
	 * @return array<string, int> Slug => post ID.
	 */
	public static function ensure(): array {
		$map = get_option( self::OPTION, array() );
		$map = is_array( $map ) ? $map : array();

		foreach ( self::required() as $slug => $page ) {
			// Still there from a previous run?
			if ( ! empty( $map[ $slug ] ) && 'page' === get_post_type( (int) $map[ $slug ] ) ) {
				continue;
			}

			$existing = get_page_by_path( $slug );

			if ( $existing instanceof WP_Post ) {
				$map[ $slug ] = $existing->ID;
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_title'   => $page['title'],
					'post_name'    => $slug,
					'post_content' => $page['content'],
					'post_status'  => 'publish',
					'comment_status' => 'closed',
					'ping_status'  => 'closed',
				)
			);

			if ( ! is_wp_error( $page_id ) ) {
				$map[ $slug ] = (int) $page_id;
			}
		}

		update_option( self::OPTION, $map, false );

		return $map;
	}

	/**
	 * Pages that are missing, or present but no longer carrying their shortcode.
	 *
	 * @return array<int, array{slug:string, title:string, shortcode:string, reason:string}>
	 */
	public static function problems(): array {
		$problems = array();

		foreach ( self::required() as $slug => $page ) {
			$post      = get_page_by_path( $slug );
			$shortcode = trim( $page['content'], '[]' );

			if ( ! $post instanceof WP_Post ) {
				$problems[] = array(
					'slug'      => $slug,
					'title'     => $page['title'],
					'shortcode' => $page['content'],
					'reason'    => __( 'The page does not exist.', 'salanaz' ),
				);
				continue;
			}

			if ( 'publish' !== $post->post_status ) {
				$problems[] = array(
					'slug'      => $slug,
					'title'     => $page['title'],
					'shortcode' => $page['content'],
					'reason'    => __( 'The page is not published.', 'salanaz' ),
				);
				continue;
			}

			if ( ! has_shortcode( (string) $post->post_content, $shortcode ) ) {
				$problems[] = array(
					'slug'      => $slug,
					'title'     => $page['title'],
					'shortcode' => $page['content'],
					'reason'    => __( 'The page no longer contains its shortcode.', 'salanaz' ),
				);
			}
		}

		return $problems;
	}

	/**
	 * Warn in wp-admin when a required page is missing or broken.
	 */
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Render the warning.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$problems = self::problems();

		if ( ! $problems ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'Salanaz: a page the system needs is missing.', 'salanaz' );
		echo '</strong></p><ul style="list-style:disc;margin-left:20px;">';

		foreach ( $problems as $problem ) {
			printf(
				'<li><code>/%s/</code> — %s %s</li>',
				esc_html( $problem['slug'] ),
				esc_html( $problem['reason'] ),
				sprintf(
					/* translators: %s: shortcode */
					esc_html__( 'Create it and add %s.', 'salanaz' ),
					'<code>' . esc_html( $problem['shortcode'] ) . '</code>'
				)
			);
		}

		echo '</ul></div>';
	}
}
