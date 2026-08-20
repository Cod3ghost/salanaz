<?php
/**
 * Dashboard for co-founders, whose tooling lives in wp-admin.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="slz-dash slz-dash--narrow">
	<div class="slz-panel slz-panel--center">
		<h1><?php esc_html_e( 'Management tools', 'salanaz' ); ?></h1>
		<p><?php esc_html_e( 'Approvals, payment verification, inventory and reporting all live in the admin area.', 'salanaz' ); ?></p>
		<p class="slz-empty__actions">
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=salanaz-approvals' ) ); ?>">
				<?php esc_html_e( 'Open admin', 'salanaz' ); ?>
			</a>
		</p>
	</div>
</div>
