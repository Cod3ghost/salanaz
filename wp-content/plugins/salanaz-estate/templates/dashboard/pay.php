<?php
/**
 * Client dashboard — submit proof of a bank transfer.
 *
 * The Paystack path is added in Batch 7; this is the manual route.
 *
 * @var WP_User $user
 * @var int     $plot_id
 * @var array   $holdings
 * @var string  $nonce
 * @var array   $notices
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$holding = null;

foreach ( $holdings as $candidate ) {
	if ( (int) $candidate['plot']->ID === $plot_id ) {
		$holding = $candidate;
		break;
	}
}

$suggested = 0.0;

if ( $holding ) {
	$suggested = $holding['next_due']
		? (float) $holding['next_due']->amount
		: (float) $holding['outstanding'];
}
?>

<div class="slz-dash">

	<?php foreach ( $notices as $notice ) : ?>
		<div class="slz-notice slz-notice--<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['text'] ); ?></div>
	<?php endforeach; ?>

	<p class="slz-dash__back">
		<a href="<?php echo esc_url( Salanaz_Auth::dashboard_url() ); ?>">&larr; <?php esc_html_e( 'Back to dashboard', 'salanaz' ); ?></a>
	</p>

	<?php if ( ! $holding ) : ?>

		<div class="slz-panel">
			<h1><?php esc_html_e( 'Nothing to pay for', 'salanaz' ); ?></h1>
			<p><?php esc_html_e( 'That plot is not reserved to your account. Reserve a plot first, then come back to pay.', 'salanaz' ); ?></p>
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'Browse plots', 'salanaz' ); ?>
			</a>
		</div>

	<?php else : ?>

		<header class="slz-dash__head">
			<div>
				<h1><?php esc_html_e( 'Make a payment', 'salanaz' ); ?></h1>
				<p class="slz-dash__sub"><?php echo esc_html( get_the_title( $holding['plot'] ) ); ?></p>
			</div>
			<span class="slz-dash__price"><?php echo esc_html( salanaz_money( $holding['outstanding'] ) ); ?>
				<small><?php esc_html_e( 'outstanding', 'salanaz' ); ?></small>
			</span>
		</header>

		<div class="slz-dash__grid">
			<div class="slz-dash__main">

				<?php if ( Salanaz_Paystack::is_available() ) : ?>
					<section class="slz-panel slz-panel--card">
						<h2 class="slz-panel__title"><?php esc_html_e( 'Pay instantly by card, transfer or USSD', 'salanaz' ); ?></h2>
						<p><?php esc_html_e( 'You are taken to Paystack to complete the payment. It is confirmed automatically — nothing to upload.', 'salanaz' ); ?></p>

						<form method="post" action="" class="slz-form">
							<?php wp_nonce_field( $nonce ); ?>
							<input type="hidden" name="plot_id" value="<?php echo esc_attr( (string) $plot_id ); ?>" />

							<p class="slz-field">
								<label for="card_amount"><?php esc_html_e( 'Amount to pay', 'salanaz' ); ?></label>
								<input type="number" name="amount" id="card_amount" step="0.01" min="1"
									value="<?php echo esc_attr( $suggested > 0 ? (string) $suggested : '' ); ?>" required />
							</p>

							<p class="slz-form__submit">
								<button type="submit" name="salanaz_card_submit" value="1" class="slz-btn slz-btn--primary">
									<?php esc_html_e( 'Continue to Paystack', 'salanaz' ); ?>
								</button>
							</p>
						</form>
					</section>

					<p class="slz-or"><span><?php esc_html_e( 'or pay by bank transfer', 'salanaz' ); ?></span></p>
				<?php endif; ?>

				<section class="slz-panel">
					<h2 class="slz-panel__title"><?php esc_html_e( 'Step 1 — Transfer to our account', 'salanaz' ); ?></h2>

					<dl class="slz-bank">
						<div>
							<dt><?php esc_html_e( 'Account name', 'salanaz' ); ?></dt>
							<dd><?php echo esc_html( (string) ( get_option( 'salanaz_bank_account_name' ) ?: 'Salanaz Global Services Ltd' ) ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Bank', 'salanaz' ); ?></dt>
							<dd><?php echo esc_html( (string) ( get_option( 'salanaz_bank_name' ) ?: __( 'To be configured', 'salanaz' ) ) ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Account number', 'salanaz' ); ?></dt>
							<dd><?php echo esc_html( (string) ( get_option( 'salanaz_bank_account_number' ) ?: __( 'To be configured', 'salanaz' ) ) ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Use as reference', 'salanaz' ); ?></dt>
							<dd><code><?php echo esc_html( $user->user_login ); ?></code></dd>
						</div>
					</dl>

					<div class="slz-notice slz-notice--warning">
						<?php esc_html_e( 'We will never ask you to pay into a personal account. If anyone asks you to, stop and call our office.', 'salanaz' ); ?>
					</div>
				</section>

				<section class="slz-panel">
					<h2 class="slz-panel__title"><?php esc_html_e( 'Step 2 — Upload your proof', 'salanaz' ); ?></h2>

					<form method="post" action="" enctype="multipart/form-data" class="slz-form">
						<?php wp_nonce_field( $nonce ); ?>
						<input type="hidden" name="plot_id" value="<?php echo esc_attr( (string) $plot_id ); ?>" />

						<p class="slz-field">
							<label for="amount"><?php esc_html_e( 'Amount paid (₦)', 'salanaz' ); ?> <span class="slz-req">*</span></label>
							<input type="number" name="amount" id="amount" step="0.01" min="1"
								value="<?php echo esc_attr( $suggested > 0 ? (string) $suggested : '' ); ?>" required />
							<?php if ( $holding['next_due'] ) : ?>
								<span class="slz-field__hint">
									<?php
									printf(
										/* translators: 1: amount, 2: date */
										esc_html__( 'Next instalment is %1$s, due %2$s.', 'salanaz' ),
										esc_html( salanaz_money( (float) $holding['next_due']->amount ) ),
										esc_html( mysql2date( 'j M Y', $holding['next_due']->due_date ) )
									);
									?>
								</span>
							<?php endif; ?>
						</p>

						<p class="slz-field">
							<label for="proof"><?php esc_html_e( 'Proof of payment', 'salanaz' ); ?> <span class="slz-req">*</span></label>
							<input type="file" name="proof" id="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required />
							<span class="slz-field__hint">
								<?php esc_html_e( 'A screenshot or PDF of the transfer. JPG, PNG, WebP or PDF, up to 5 MB.', 'salanaz' ); ?>
							</span>
						</p>

						<p class="slz-field">
							<label for="payer_note"><?php esc_html_e( 'Anything we should know? (optional)', 'salanaz' ); ?></label>
							<textarea name="payer_note" id="payer_note" rows="3"></textarea>
						</p>

						<p class="slz-form__submit">
							<button type="submit" name="salanaz_pay_submit" value="1" class="slz-btn slz-btn--gold">
								<?php esc_html_e( 'Submit for verification', 'salanaz' ); ?>
							</button>
						</p>
					</form>
				</section>

			</div>

			<aside class="slz-dash__aside">
				<div class="slz-panel slz-panel--muted">
					<h2 class="slz-panel__title"><?php esc_html_e( 'What happens next', 'salanaz' ); ?></h2>
					<ol class="slz-steplist">
						<li><?php esc_html_e( 'Your payment sits as "pending verification".', 'salanaz' ); ?></li>
						<li><?php esc_html_e( 'A co-founder checks it against our bank statement.', 'salanaz' ); ?></li>
						<li><?php esc_html_e( 'You are emailed either way, and your balance updates.', 'salanaz' ); ?></li>
						<li><?php esc_html_e( 'A receipt is issued for every verified payment.', 'salanaz' ); ?></li>
					</ol>
				</div>

				<div class="slz-panel slz-panel--muted">
					<h2 class="slz-panel__title"><?php esc_html_e( 'Card payment', 'salanaz' ); ?></h2>
					<?php if ( Salanaz_Paystack::is_available() ) : ?>
						<p class="slz-muted"><?php esc_html_e( 'Paying through Paystack confirms straight away, so your balance updates without waiting for us to check a transfer.', 'salanaz' ); ?></p>
					<?php else : ?>
						<p class="slz-muted"><?php esc_html_e( 'Card payment is not switched on at the moment. Please use the bank transfer route above.', 'salanaz' ); ?></p>
					<?php endif; ?>
				</div>
			</aside>
		</div>

	<?php endif; ?>
</div>
