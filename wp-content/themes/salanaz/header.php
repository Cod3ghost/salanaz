<?php
/**
 * Site header.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="profile" href="https://gmpg.org/xfn/11" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#slz-content">
	<?php esc_html_e( 'Skip to content', 'salanaz' ); ?>
</a>

<header class="slz-header">
	<div class="slz-container slz-header__inner">

		<a class="slz-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			$logo = salanaz_logo_markup();
			if ( $logo ) {
				echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
			}
			?>
			<span class="slz-brand__text">
				<span class="slz-brand__name"><?php bloginfo( 'name' ); ?></span>
				<span class="slz-brand__tagline"><?php bloginfo( 'description' ); ?></span>
			</span>
		</a>

		<nav class="slz-nav" id="slz-primary-nav" aria-label="<?php esc_attr_e( 'Primary navigation', 'salanaz' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'depth'          => 2,
					'fallback_cb'    => 'salanaz_fallback_menu',
				)
			);
			?>
		</nav>

		<div class="slz-header__actions">
			<?php if ( is_user_logged_in() ) : ?>
				<a class="slz-btn slz-btn--primary slz-btn--sm" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">
					<?php esc_html_e( 'Dashboard', 'salanaz' ); ?>
				</a>
			<?php else : ?>
				<a class="slz-btn slz-btn--ghost slz-btn--sm" href="<?php echo esc_url( home_url( '/login/' ) ); ?>">
					<?php esc_html_e( 'Log in', 'salanaz' ); ?>
				</a>
				<a class="slz-btn slz-btn--gold slz-btn--sm" href="<?php echo esc_url( home_url( '/register/' ) ); ?>">
					<?php esc_html_e( 'Register', 'salanaz' ); ?>
				</a>
			<?php endif; ?>

			<button class="slz-nav-toggle"
				type="button"
				aria-expanded="false"
				aria-controls="slz-primary-nav"
				aria-label="<?php esc_attr_e( 'Toggle menu', 'salanaz' ); ?>">
				<span></span><span></span><span></span>
			</button>
		</div>

	</div>
</header>

<main id="slz-content">
