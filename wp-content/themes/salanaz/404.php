<?php
/**
 * 404.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="slz-container slz-section">
	<div class="slz-empty">
		<h1><?php esc_html_e( 'Page not found', 'salanaz' ); ?></h1>
		<p><?php esc_html_e( 'The page you were looking for has moved or no longer exists. Try our estates listing instead.', 'salanaz' ); ?></p>
		<p class="slz-empty__actions">
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'Browse estates', 'salanaz' ); ?>
			</a>
			<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Back to home', 'salanaz' ); ?>
			</a>
		</p>
	</div>
</div>

<?php
get_footer();
