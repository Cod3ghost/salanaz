<?php
/**
 * Client dashboard — account not yet approved.
 *
 * @var WP_User $user
 * @var string  $status
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$rejected = Salanaz_Profile::STATUS_REJECTED === $status;
$reason   = (string) get_user_meta( $user->ID, Salanaz_Profile::META_REJECT_REASON, true );
?>

<div class="slz-dash slz-dash--narrow">

	<div class="slz-panel slz-panel--center">
		<span class="slz-statusicon <?php echo esc_attr( $rejected ? 'slz-statusicon--error' : 'slz-statusicon--wait' ); ?>" aria-hidden="true"></span>

		<?php if ( $rejected ) : ?>
			<h1><?php esc_html_e( 'Your registration was not approved', 'salanaz' ); ?></h1>
			<?php if ( $reason ) : ?>
				<p><strong><?php esc_html_e( 'Reason given:', 'salanaz' ); ?></strong> <?php echo esc_html( $reason ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'If you think this is a mistake, please call our office and we will look at it again.', 'salanaz' ); ?></p>
		<?php else : ?>
			<h1><?php esc_html_e( 'Your account is being reviewed', 'salanaz' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: first name */
					esc_html__( 'Thank you for registering, %s. A member of our team is reviewing your account. This usually takes a few hours during business days.', 'salanaz' ),
					esc_html( $user->first_name ?: $user->display_name )
				);
				?>
			</p>
			<p><?php esc_html_e( 'You will be emailed as soon as it is approved, and a sales officer will be assigned to you. In the meantime you can keep browsing.', 'salanaz' ); ?></p>
		<?php endif; ?>

		<p class="slz-empty__actions">
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'Browse estates', 'salanaz' ); ?>
			</a>
			<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
				<?php esc_html_e( 'Contact us', 'salanaz' ); ?>
			</a>
		</p>

		<p class="slz-muted slz-block-sm">
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'salanaz' ); ?></a>
		</p>
	</div>

</div>
