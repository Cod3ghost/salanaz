<?php
/**
 * Dashboard shown to signed-out visitors.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="slz-dash slz-dash--narrow">
	<div class="slz-panel slz-panel--center">
		<h1><?php esc_html_e( 'Please sign in', 'salanaz' ); ?></h1>
		<p><?php esc_html_e( 'Your dashboard shows your plot, your payments and your outstanding balance.', 'salanaz' ); ?></p>
		<p class="slz-empty__actions">
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( Salanaz_Auth::login_page_url() ); ?>">
				<?php esc_html_e( 'Sign in', 'salanaz' ); ?>
			</a>
			<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( Salanaz_Auth::register_page_url() ); ?>">
				<?php esc_html_e( 'Create an account', 'salanaz' ); ?>
			</a>
		</p>
	</div>
</div>
