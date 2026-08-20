<?php
/**
 * Presentation helpers.
 *
 * Data comes from Salanaz_Inventory in the plugin; everything here is about
 * turning it into markup.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * A branded placeholder image, used when an estate or plot has no featured
 * image yet.
 *
 * Generated as an inline SVG data URI rather than a remote file so it renders
 * offline and costs no HTTP request. The hue shifts deterministically with the
 * post ID so a listing does not look like the same tile repeated.
 *
 * @param int    $post_id Post ID, used as the colour seed.
 * @param string $label   Short text drawn on the tile.
 * @return string A data: URI suitable for a src attribute.
 */
function salanaz_placeholder_image( int $post_id, string $label = '' ): string {
	$palettes = array(
		array( '#0b3c8f', '#1d55b8' ),
		array( '#07306f', '#0b3c8f' ),
		array( '#123a7a', '#2563c9' ),
		array( '#0a2f66', '#1a4fa8' ),
	);

	list( $from, $to ) = $palettes[ $post_id % count( $palettes ) ];

	$label = strtoupper( mb_substr( wp_strip_all_tags( $label ), 0, 22 ) );

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 450" width="720" height="450">
			<defs>
				<linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
					<stop offset="0%%" stop-color="%1$s"/>
					<stop offset="100%%" stop-color="%2$s"/>
				</linearGradient>
			</defs>
			<rect width="720" height="450" fill="url(#g)"/>
			<g fill="#ffffff" opacity="0.07">
				<rect x="90" y="210" width="80" height="180"/>
				<rect x="185" y="160" width="95" height="230"/>
				<rect x="295" y="235" width="70" height="155"/>
				<rect x="380" y="130" width="110" height="260"/>
				<rect x="505" y="200" width="85" height="190"/>
			</g>
			<path d="M0 390 Q 180 340 360 380 T 720 360 L720 450 L0 450 Z" fill="#e8a61a" opacity="0.16"/>
			<circle cx="620" cy="95" r="46" fill="#e8a61a" opacity="0.22"/>
			<text x="50%%" y="50%%" text-anchor="middle" dominant-baseline="middle"
				font-family="Segoe UI, Arial, sans-serif" font-size="26" font-weight="700"
				letter-spacing="3" fill="#ffffff" opacity="0.82">%3$s</text>
		</svg>',
		$from,
		$to,
		htmlspecialchars( $label, ENT_QUOTES | ENT_XML1, 'UTF-8' )
	);

	return 'data:image/svg+xml;charset=utf-8,' . rawurlencode( preg_replace( '/\s+/', ' ', $svg ) );
}

/**
 * Featured image for a card, falling back to the branded placeholder.
 *
 * @param int    $post_id Post ID.
 * @param string $size    Registered image size.
 * @return string Escaped <img> markup.
 */
function salanaz_card_image( int $post_id, string $size = 'salanaz-card' ): string {
	if ( has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail( $post_id, $size, array( 'loading' => 'lazy' ) );
	}

	return sprintf(
		'<img src="%s" alt="" width="720" height="450" loading="lazy" />',
		esc_attr( salanaz_placeholder_image( $post_id, get_the_title( $post_id ) ) )
	);
}

/**
 * Inline SVG icon.
 *
 * Icons are inlined rather than loaded from an icon font so they inherit
 * currentColor and cost no extra request.
 *
 * @param string $name Icon key.
 * @return string SVG markup, or an empty string for an unknown key.
 */
function salanaz_icon( string $name ): string {
	$paths = array(
		'shield'  => '<path d="M12 2 4 5.5v6c0 5 3.4 9.3 8 10.5 4.6-1.2 8-5.5 8-10.5v-6L12 2Z"/><path d="m9 12 2 2 4-4" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
		'wallet'  => '<path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1h1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/><circle cx="17" cy="13" r="1.6" fill="#fff"/>',
		'receipt' => '<path d="M5 2h14a1 1 0 0 1 1 1v19l-3-2-3 2-3-2-3 2-3-2V3a1 1 0 0 1 1-1Z"/><path d="M8 8h8M8 12h8M8 16h5" stroke="#fff" stroke-width="1.8" stroke-linecap="round" fill="none"/>',
		'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0Z"/>',
		'pin'     => '<path d="M12 2a7 7 0 0 0-7 7c0 5.3 7 13 7 13s7-7.7 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false">%s</svg>',
		$paths[ $name ]
	);
}

/**
 * Compact size range for an estate, e.g. "300 – 1,000 sqm".
 *
 * @param array<string, mixed> $stats Result of Salanaz_Inventory::estate_stats().
 * @return string
 */
function salanaz_size_range( array $stats ): string {
	if ( empty( $stats['min_size'] ) ) {
		return '';
	}

	if ( $stats['min_size'] === $stats['max_size'] ) {
		return number_format( (float) $stats['min_size'] ) . ' sqm';
	}

	return sprintf(
		'%s – %s sqm',
		number_format( (float) $stats['min_size'] ),
		number_format( (float) $stats['max_size'] )
	);
}

/**
 * Availability pill for an estate card.
 *
 * @param array<string, mixed> $stats Result of Salanaz_Inventory::estate_stats().
 * @return string Escaped markup.
 */
function salanaz_availability_pill( array $stats ): string {
	$available = (int) $stats['available'];

	if ( $available > 0 ) {
		return sprintf(
			'<span class="slz-pill slz-pill--available">%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: number of available plots */
					_n( '%d plot available', '%d plots available', $available, 'salanaz' ),
					$available
				)
			)
		);
	}

	return sprintf(
		'<span class="slz-pill slz-pill--sold">%s</span>',
		esc_html__( 'Fully subscribed', 'salanaz' )
	);
}

/**
 * The estates archive URL.
 *
 * @return string
 */
function salanaz_estates_url(): string {
	if ( ! salanaz_plugin_active() ) {
		return home_url( '/estates/' );
	}

	$link = get_post_type_archive_link( Salanaz_Post_Types::ESTATE );

	return $link ?: home_url( '/estates/' );
}

/**
 * Locations that currently have at least one published estate, for the hero
 * search dropdown.
 *
 * @return WP_Term[]
 */
function salanaz_active_locations(): array {
	if ( ! salanaz_plugin_active() ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => Salanaz_Post_Types::TAX_LOCATION,
			'hide_empty' => true,
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}
