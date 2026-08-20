<?php
/**
 * Customizer settings.
 *
 * Everything a site owner is likely to want to change — the office details in
 * the footer, the wording on the home page, the brand colours — is exposed here
 * rather than left in the template files.
 *
 * Defaults match the copy the theme ships with, so an untouched install looks
 * exactly as designed and each field can be overridden one at a time.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Editable text, with its default.
 *
 * @param string $key     Setting key, without the salanaz_ prefix.
 * @param string $default Default copy.
 * @return string
 */
function salanaz_text( string $key, string $default = '' ): string {
	$value = get_theme_mod( 'salanaz_' . $key, $default );

	return '' === trim( (string) $value ) ? $default : (string) $value;
}

/**
 * Register the Customizer panels, sections and controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 */
function salanaz_customize_register( WP_Customize_Manager $wp_customize ): void {
	// Live preview for the simple text fields.
	foreach ( array( 'blogname', 'blogdescription' ) as $core ) {
		if ( $wp_customize->get_setting( $core ) ) {
			$wp_customize->get_setting( $core )->transport = 'postMessage';
		}
	}

	$wp_customize->add_panel(
		'salanaz_panel',
		array(
			'title'       => __( 'Salanaz', 'salanaz' ),
			'description' => __( 'Contact details, home page wording and brand colours.', 'salanaz' ),
			'priority'    => 20,
		)
	);

	/* -----------------------------------------------------------------
	 * Contact details
	 * -------------------------------------------------------------- */

	$wp_customize->add_section(
		'salanaz_contact',
		array(
			'title'       => __( 'Contact details', 'salanaz' ),
			'panel'       => 'salanaz_panel',
			'description' => __( 'Shown in the footer, on the closing call to action and on estate pages.', 'salanaz' ),
		)
	);

	$contact = array(
		'salanaz_office_address' => array(
			'label'   => __( 'Office address', 'salanaz' ),
			'default' => '',
			'type'    => 'textarea',
		),
		'salanaz_office_phone'   => array(
			'label'   => __( 'Phone number', 'salanaz' ),
			'default' => '',
			'type'    => 'text',
		),
		'salanaz_office_email'   => array(
			'label'   => __( 'Email address', 'salanaz' ),
			'default' => '',
			'type'    => 'email',
		),
		'salanaz_office_whatsapp' => array(
			'label'       => __( 'WhatsApp number', 'salanaz' ),
			'default'     => '',
			'type'        => 'text',
			'description' => __( 'With country code, e.g. 2348031234567. Leave blank to hide the button.', 'salanaz' ),
		),
		'salanaz_rc_number'      => array(
			'label'   => __( 'RC number', 'salanaz' ),
			'default' => '',
			'type'    => 'text',
		),
	);

	foreach ( $contact as $key => $field ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $field['default'],
				'sanitize_callback' => 'textarea' === $field['type'] ? 'sanitize_textarea_field' : 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			$key,
			array(
				'label'       => $field['label'],
				'section'     => 'salanaz_contact',
				'type'        => $field['type'],
				'description' => $field['description'] ?? '',
			)
		);
	}

	/* -----------------------------------------------------------------
	 * Home page
	 * -------------------------------------------------------------- */

	$wp_customize->add_section(
		'salanaz_home',
		array(
			'title'       => __( 'Home page', 'salanaz' ),
			'panel'       => 'salanaz_panel',
			'description' => __( 'The wording across the home page. Leave a field empty to restore the original text.', 'salanaz' ),
		)
	);

	$home = salanaz_home_defaults();

	foreach ( $home as $key => $field ) {
		$wp_customize->add_setting(
			'salanaz_' . $key,
			array(
				'default'           => $field['default'],
				'sanitize_callback' => 'textarea' === $field['type'] ? 'sanitize_textarea_field' : 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			'salanaz_' . $key,
			array(
				'label'   => $field['label'],
				'section' => 'salanaz_home',
				'type'    => $field['type'],
			)
		);
	}

	/* -----------------------------------------------------------------
	 * Brand colours
	 * -------------------------------------------------------------- */

	$wp_customize->add_section(
		'salanaz_brand',
		array(
			'title' => __( 'Brand colours', 'salanaz' ),
			'panel' => 'salanaz_panel',
		)
	);

	$colours = array(
		'salanaz_colour_navy' => array( __( 'Primary (navy)', 'salanaz' ), '#0b3c8f' ),
		'salanaz_colour_gold' => array( __( 'Accent (gold)', 'salanaz' ), '#e8a61a' ),
	);

	foreach ( $colours as $key => list( $label, $default ) ) {
		$wp_customize->add_setting(
			$key,
			array(
				'default'           => $default,
				'sanitize_callback' => 'sanitize_hex_color',
				'transport'         => 'refresh',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$key,
				array(
					'label'   => $label,
					'section' => 'salanaz_brand',
				)
			)
		);
	}
}
add_action( 'customize_register', 'salanaz_customize_register' );

/**
 * The editable home page strings and their shipped defaults.
 *
 * @return array<string, array{label:string, default:string, type:string}>
 */
function salanaz_home_defaults(): array {
	return array(
		'hero_eyebrow'  => array(
			'label'   => __( 'Hero — small label above the heading', 'salanaz' ),
			'default' => __( 'Verified title · Documented allocation', 'salanaz' ),
			'type'    => 'text',
		),
		'hero_heading'  => array(
			'label'   => __( 'Hero — heading', 'salanaz' ),
			'default' => __( 'Own land you can actually build on.', 'salanaz' ),
			'type'    => 'text',
		),
		'hero_lede'     => array(
			'label'   => __( 'Hero — paragraph', 'salanaz' ),
			'default' => __( 'Salanaz sells surveyed, dry, titled plots across Lagos, Abuja, Enugu and Port Harcourt — with flexible payment plans and a portal that shows you exactly what you have paid and what is left.', 'salanaz' ),
			'type'    => 'textarea',
		),
		'hero_cta'      => array(
			'label'   => __( 'Hero — main button', 'salanaz' ),
			'default' => __( 'Browse available plots', 'salanaz' ),
			'type'    => 'text',
		),
		'estates_title' => array(
			'label'   => __( 'Estates section — heading', 'salanaz' ),
			'default' => __( 'Land available right now', 'salanaz' ),
			'type'    => 'text',
		),
		'estates_lede'  => array(
			'label'   => __( 'Estates section — paragraph', 'salanaz' ),
			'default' => __( 'Every estate below is fenced, surveyed and documented. Prices shown are current and update as plots are taken.', 'salanaz' ),
			'type'    => 'textarea',
		),
		'why_title'     => array(
			'label'   => __( 'Why us — heading', 'salanaz' ),
			'default' => __( 'Built on TRUST. Driven by EXCELLENCE.', 'salanaz' ),
			'type'    => 'text',
		),
		'why_lede'      => array(
			'label'   => __( 'Why us — paragraph', 'salanaz' ),
			'default' => __( 'Land fraud is the single biggest fear for Nigerian buyers. Everything we do is designed to remove that risk.', 'salanaz' ),
			'type'    => 'textarea',
		),
		'cta_title'     => array(
			'label'   => __( 'Closing banner — heading', 'salanaz' ),
			'default' => __( 'Ready to secure your plot?', 'salanaz' ),
			'type'    => 'text',
		),
		'cta_lede'      => array(
			'label'   => __( 'Closing banner — paragraph', 'salanaz' ),
			'default' => __( 'Create an account, get approved, and reserve your land today. Or call us and we will walk you through it.', 'salanaz' ),
			'type'    => 'textarea',
		),
	);
}

/**
 * Apply the chosen brand colours by overriding the CSS custom properties.
 */
function salanaz_customizer_css(): void {
	$navy = get_theme_mod( 'salanaz_colour_navy', '#0b3c8f' );
	$gold = get_theme_mod( 'salanaz_colour_gold', '#e8a61a' );

	if ( '#0b3c8f' === $navy && '#e8a61a' === $gold ) {
		return;
	}

	printf(
		'<style id="salanaz-brand">:root{--slz-navy:%s;--slz-gold:%s;--slz-navy-dark:%s;--slz-navy-light:%s;--slz-gold-dark:%s;}</style>',
		esc_attr( $navy ),
		esc_attr( $gold ),
		esc_attr( salanaz_shade( $navy, -22 ) ),
		esc_attr( salanaz_shade( $navy, 22 ) ),
		esc_attr( salanaz_shade( $gold, -18 ) )
	);
}
add_action( 'wp_head', 'salanaz_customizer_css', 20 );

/**
 * Lighten or darken a hex colour.
 *
 * @param string $hex     Hex colour, with or without the hash.
 * @param int    $percent -100 to 100.
 * @return string Hex colour.
 */
function salanaz_shade( string $hex, int $percent ): string {
	$hex = ltrim( $hex, '#' );

	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '#' . $hex;
	}

	$out = '#';

	for ( $i = 0; $i < 3; $i++ ) {
		$channel = hexdec( substr( $hex, $i * 2, 2 ) );
		$channel = (int) round( $channel + ( ( $percent / 100 ) * ( $percent > 0 ? 255 - $channel : $channel ) ) );
		$out    .= str_pad( dechex( max( 0, min( 255, $channel ) ) ), 2, '0', STR_PAD_LEFT );
	}

	return $out;
}

/**
 * Live-preview script for the title and tagline.
 */
function salanaz_customize_preview_js(): void {
	wp_add_inline_script(
		'customize-preview',
		"( function( api ) {
			api( 'blogname', function( v ) { v.bind( function( to ) {
				document.querySelectorAll( '.slz-brand__name' ).forEach( function( el ) { el.textContent = to; } );
			} ); } );
			api( 'blogdescription', function( v ) { v.bind( function( to ) {
				document.querySelectorAll( '.slz-brand__tagline' ).forEach( function( el ) { el.textContent = to; } );
			} ); } );
		}( wp.customize ) );"
	);
}
add_action( 'customize_preview_init', 'salanaz_customize_preview_js' );
