<?php
/**
 * Client dashboard — choose a plot and payment plan.
 *
 * @var WP_Post|null $plot
 * @var string       $nonce
 * @var array        $notices
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$price  = $plot ? Salanaz_Inventory::plot_price( $plot->ID ) : 0.0;
$status = $plot ? Salanaz_Inventory::plot_status( $plot->ID ) : '';
$estate = $plot ? Salanaz_Inventory::plot_estate( $plot->ID ) : null;
?>

<div class="slz-dash">

	<?php foreach ( $notices as $notice ) : ?>
		<div class="slz-notice slz-notice--<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['text'] ); ?></div>
	<?php endforeach; ?>

	<p class="slz-dash__back">
		<a href="<?php echo esc_url( Salanaz_Auth::dashboard_url() ); ?>">&larr; <?php esc_html_e( 'Back to dashboard', 'salanaz' ); ?></a>
	</p>

	<?php if ( ! $plot instanceof WP_Post || Salanaz_Post_Types::PLOT !== $plot->post_type ) : ?>

		<div class="slz-panel">
			<h1><?php esc_html_e( 'Choose a plot', 'salanaz' ); ?></h1>
			<p><?php esc_html_e( 'Browse our estates and pick the plot you want. You will choose a payment plan on the next step.', 'salanaz' ); ?></p>
			<a class="slz-btn slz-btn--gold" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'Browse estates', 'salanaz' ); ?>
			</a>
		</div>

	<?php elseif ( Salanaz_Post_Types::STATUS_AVAILABLE !== $status ) : ?>

		<div class="slz-panel">
			<h1><?php esc_html_e( 'That plot is no longer available', 'salanaz' ); ?></h1>
			<p><?php esc_html_e( 'It has been taken since you last looked. Please choose another — there are more in the same estate.', 'salanaz' ); ?></p>
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( $estate ? get_permalink( $estate ) : salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'See other plots', 'salanaz' ); ?>
			</a>
		</div>

	<?php else : ?>

		<header class="slz-dash__head">
			<div>
				<h1><?php esc_html_e( 'Reserve this plot', 'salanaz' ); ?></h1>
				<p class="slz-dash__sub">
					<?php echo esc_html( get_the_title( $plot ) ); ?>
					<?php if ( $estate ) : ?>
						&mdash; <?php echo esc_html( get_the_title( $estate ) ); ?>
					<?php endif; ?>
				</p>
			</div>
			<span class="slz-dash__price"><?php echo esc_html( salanaz_money( $price ) ); ?></span>
		</header>

		<form method="post" action="" class="slz-plans-form">
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" name="plot_id" value="<?php echo esc_attr( (string) $plot->ID ); ?>" />

			<h2 class="slz-panel__title"><?php esc_html_e( 'Choose how you want to pay', 'salanaz' ); ?></h2>

			<div class="slz-planlist">
				<?php
				$first = true;
				foreach ( Salanaz_Plans::all() as $key => $plan ) :
					$quote = Salanaz_Plans::quote( $key, $price );

					if ( ! $quote ) {
						continue;
					}
					?>
					<label class="slz-planopt">
						<input type="radio" name="plan" value="<?php echo esc_attr( $key ); ?>" <?php checked( $first ); ?> required />
						<span class="slz-planopt__body">
							<span class="slz-planopt__head">
								<span class="slz-planopt__name"><?php echo esc_html( $plan['label'] ); ?></span>
								<?php if ( $quote['months'] > 0 ) : ?>
									<span class="slz-planopt__price">
										<?php
										printf(
											/* translators: %s: monthly amount */
											esc_html__( '%s / month', 'salanaz' ),
											esc_html( salanaz_money( $quote['per_month'] ) )
										);
										?>
									</span>
								<?php else : ?>
									<span class="slz-planopt__price"><?php echo esc_html( salanaz_money( $quote['total'] ) ); ?></span>
								<?php endif; ?>
							</span>
							<span class="slz-planopt__note"><?php echo esc_html( $plan['note'] ); ?></span>
							<span class="slz-planopt__facts">
								<span>
									<?php esc_html_e( 'Pay now:', 'salanaz' ); ?>
									<strong><?php echo esc_html( salanaz_money( $quote['deposit'] ) ); ?></strong>
								</span>
								<span>
									<?php esc_html_e( 'Total:', 'salanaz' ); ?>
									<strong><?php echo esc_html( salanaz_money( $quote['total'] ) ); ?></strong>
								</span>
								<?php if ( $quote['interest'] > 0 ) : ?>
									<span class="slz-planopt__warn">
										<?php
										printf(
											/* translators: %s: percentage */
											esc_html__( 'Includes %s%% carrying charge', 'salanaz' ),
											esc_html( (string) round( $quote['interest'] * 100 ) )
										);
										?>
									</span>
								<?php endif; ?>
							</span>
						</span>
					</label>
					<?php
					$first = false;
				endforeach;
				?>
			</div>

			<div class="slz-notice slz-notice--info">
				<?php esc_html_e( 'Reserving holds this plot in your name. It is confirmed once your first payment is verified. Allocation follows full payment.', 'salanaz' ); ?>
			</div>

			<p class="slz-form__submit">
				<button type="submit" name="salanaz_reserve_submit" value="1" class="slz-btn slz-btn--gold">
					<?php esc_html_e( 'Reserve this plot', 'salanaz' ); ?>
				</button>
			</p>
		</form>

	<?php endif; ?>
</div>
