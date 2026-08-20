<?php
/**
 * Client dashboard — overview.
 *
 * @var WP_User      $user
 * @var WP_User|null $staff
 * @var array        $holdings
 * @var array        $txns
 * @var array        $notices
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$total_out = 0.0;
$total_paid = 0.0;

foreach ( $holdings as $holding ) {
	$total_out  += (float) $holding['outstanding'];
	$total_paid += (float) $holding['paid'];
}
?>

<div class="slz-dash">

	<?php foreach ( $notices as $notice ) : ?>
		<div class="slz-notice slz-notice--<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['text'] ); ?></div>
	<?php endforeach; ?>

	<header class="slz-dash__head">
		<div>
			<h1><?php
				printf(
					/* translators: %s: first name */
					esc_html__( 'Welcome back, %s', 'salanaz' ),
					esc_html( $user->first_name ?: $user->display_name )
				);
			?></h1>
			<p class="slz-dash__sub"><?php esc_html_e( 'Here is where your purchase stands.', 'salanaz' ); ?></p>
		</div>
		<a class="slz-btn slz-btn--gold" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
			<?php esc_html_e( 'Browse plots', 'salanaz' ); ?>
		</a>
	</header>

	<!-- Summary -->
	<div class="slz-dash__stats">
		<div class="slz-dstat">
			<span class="slz-dstat__label"><?php esc_html_e( 'Plots held', 'salanaz' ); ?></span>
			<span class="slz-dstat__value"><?php echo esc_html( (string) count( $holdings ) ); ?></span>
		</div>
		<div class="slz-dstat">
			<span class="slz-dstat__label"><?php esc_html_e( 'Total paid', 'salanaz' ); ?></span>
			<span class="slz-dstat__value"><?php echo esc_html( salanaz_money( $total_paid ) ); ?></span>
		</div>
		<div class="slz-dstat slz-dstat--accent">
			<span class="slz-dstat__label"><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></span>
			<span class="slz-dstat__value"><?php echo esc_html( salanaz_money( $total_out ) ); ?></span>
		</div>
	</div>

	<div class="slz-dash__grid">

		<div class="slz-dash__main">

			<!-- Holdings -->
			<section class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Your plots', 'salanaz' ); ?></h2>

				<?php if ( ! $holdings ) : ?>
					<div class="slz-panel__empty">
						<p><strong><?php esc_html_e( 'You have not reserved a plot yet.', 'salanaz' ); ?></strong></p>
						<p><?php esc_html_e( 'Browse our estates, pick a plot that suits you, and choose a payment plan.', 'salanaz' ); ?></p>
						<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
							<?php esc_html_e( 'Find a plot', 'salanaz' ); ?>
						</a>
					</div>
				<?php else : ?>
					<?php foreach ( $holdings as $holding ) : ?>
						<?php
						$plot     = $holding['plot'];
						$plan     = $holding['plan'];
						$progress = $holding['total'] > 0 ? min( 100, ( $holding['paid'] / $holding['total'] ) * 100 ) : 0;
						?>
						<article class="slz-holding">
							<header class="slz-holding__head">
								<div>
									<h3><a href="<?php echo esc_url( get_permalink( $plot ) ); ?>"><?php echo esc_html( get_the_title( $plot ) ); ?></a></h3>
									<?php if ( $holding['estate'] ) : ?>
										<p class="slz-holding__estate"><?php echo esc_html( get_the_title( $holding['estate'] ) ); ?></p>
									<?php endif; ?>
								</div>
								<span class="slz-pill slz-pill--<?php echo esc_attr( $holding['status'] ); ?>">
									<?php echo esc_html( Salanaz_Post_Types::status_label( $holding['status'] ) ); ?>
								</span>
							</header>

							<div class="slz-progress">
								<div class="slz-progress__bar" style="width: <?php echo esc_attr( (string) round( $progress ) ); ?>%"></div>
							</div>
							<p class="slz-progress__label">
								<?php
								printf(
									/* translators: 1: amount paid, 2: total, 3: percentage */
									esc_html__( '%1$s of %2$s paid (%3$s%%)', 'salanaz' ),
									esc_html( salanaz_money( $holding['paid'] ) ),
									esc_html( salanaz_money( $holding['total'] ) ),
									esc_html( (string) round( $progress ) )
								);
								?>
							</p>

							<dl class="slz-holding__facts">
								<div>
									<dt><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></dt>
									<dd><?php echo esc_html( salanaz_money( $holding['outstanding'] ) ); ?></dd>
								</div>
								<?php if ( $plan ) : ?>
									<div>
										<dt><?php esc_html_e( 'Plan', 'salanaz' ); ?></dt>
										<dd><?php
											printf(
												/* translators: %d: months */
												esc_html__( '%d months', 'salanaz' ),
												(int) $plan->tenure_months
											);
										?></dd>
									</div>
								<?php endif; ?>
								<?php if ( $holding['next_due'] ) : ?>
									<div>
										<dt><?php esc_html_e( 'Next due', 'salanaz' ); ?></dt>
										<dd>
											<?php echo esc_html( salanaz_money( (float) $holding['next_due']->amount ) ); ?>
											<span class="slz-muted">
												<?php echo esc_html( mysql2date( 'j M Y', $holding['next_due']->due_date ) ); ?>
											</span>
										</dd>
									</div>
								<?php endif; ?>
							</dl>

							<?php if ( $holding['outstanding'] > 0 ) : ?>
								<a class="slz-btn slz-btn--gold slz-btn--sm"
									href="<?php echo esc_url( add_query_arg( array( 'view' => 'pay', 'plot' => $plot->ID ), Salanaz_Auth::dashboard_url() ) ); ?>">
									<?php esc_html_e( 'Make a payment', 'salanaz' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( get_post_meta( $plot->ID, '_salanaz_allocation_path', true ) ) : ?>
								<a class="slz-btn slz-btn--primary slz-btn--sm"
									href="<?php echo esc_url( Salanaz_Documents::document_url( 'allocation', (int) $plot->ID ) ); ?>"
									target="_blank" rel="noopener">
									<?php esc_html_e( 'Allocation letter', 'salanaz' ); ?>
								</a>
							<?php endif; ?>

							<?php if ( $plan ) : ?>
								<details class="slz-schedule">
									<summary><?php esc_html_e( 'View payment schedule', 'salanaz' ); ?></summary>
									<div class="slz-table-wrap">
										<table class="slz-table">
											<thead>
												<tr>
													<th scope="col"><?php esc_html_e( '#', 'salanaz' ); ?></th>
													<th scope="col"><?php esc_html_e( 'Due', 'salanaz' ); ?></th>
													<th scope="col"><?php esc_html_e( 'Amount', 'salanaz' ); ?></th>
													<th scope="col"><?php esc_html_e( 'Status', 'salanaz' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( Salanaz_Plans::schedule( (int) $plan->id ) as $row ) : ?>
													<tr>
														<th scope="row"><?php echo esc_html( (string) $row->installment_no ); ?></th>
														<td><?php echo esc_html( mysql2date( 'j M Y', $row->due_date ) ); ?></td>
														<td class="slz-table__price"><?php echo esc_html( salanaz_money( (float) $row->amount ) ); ?></td>
														<td>
															<span class="slz-pill slz-pill--<?php echo esc_attr( Salanaz_Schema::INST_PAID === $row->status ? 'verified' : 'pending' ); ?>">
																<?php echo esc_html( ucfirst( (string) $row->status ) ); ?>
															</span>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</details>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<!-- Payment history -->
			<section class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Payment history', 'salanaz' ); ?></h2>

				<?php if ( ! $txns ) : ?>
					<p class="slz-muted"><?php esc_html_e( 'No payments recorded yet.', 'salanaz' ); ?></p>
				<?php else : ?>
					<div class="slz-table-wrap">
						<table class="slz-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Reference', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Date', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Amount', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Method', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'salanaz' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $txns as $txn ) : ?>
									<tr>
										<th scope="row"><code><?php echo esc_html( $txn->reference ); ?></code></th>
										<td><?php echo esc_html( mysql2date( 'j M Y', $txn->created_at ) ); ?></td>
										<td class="slz-table__price"><?php echo esc_html( salanaz_money( (float) $txn->amount ) ); ?></td>
										<td>
											<?php
											echo esc_html(
												Salanaz_Schema::METHOD_PAYSTACK === $txn->payment_method
													? __( 'Card / transfer', 'salanaz' )
													: __( 'Bank transfer', 'salanaz' )
											);
											?>
											<?php if ( $txn->proof_path ) : ?>
												<a class="slz-block-sm" href="<?php echo esc_url( Salanaz_Uploads::proof_url( (int) $txn->id ) ); ?>" target="_blank" rel="noopener">
													<?php esc_html_e( 'View proof', 'salanaz' ); ?>
												</a>
											<?php endif; ?>
											<?php if ( $txn->receipt_path ) : ?>
												<a class="slz-block-sm" href="<?php echo esc_url( Salanaz_Documents::document_url( 'receipt', (int) $txn->id ) ); ?>" target="_blank" rel="noopener">
													<?php esc_html_e( 'Download receipt', 'salanaz' ); ?>
												</a>
											<?php endif; ?>
										</td>
										<td>
											<span class="slz-pill slz-pill--<?php echo esc_attr( Salanaz_Transactions::status_class( $txn->status ) ); ?>">
												<?php echo esc_html( Salanaz_Transactions::status_label( $txn->status ) ); ?>
											</span>
											<?php if ( $txn->rejection_reason ) : ?>
												<span class="slz-muted slz-block-sm"><?php echo esc_html( $txn->rejection_reason ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>

		</div>

		<!-- Sidebar -->
		<aside class="slz-dash__aside">
			<div class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Your sales officer', 'salanaz' ); ?></h2>

				<?php if ( $staff instanceof WP_User ) : ?>
					<div class="slz-officer">
						<span class="slz-officer__avatar"><?php echo esc_html( strtoupper( substr( $staff->display_name, 0, 1 ) ) ); ?></span>
						<div>
							<strong><?php echo esc_html( $staff->display_name ); ?></strong>
							<span class="slz-muted slz-block-sm"><?php esc_html_e( 'Sales officer', 'salanaz' ); ?></span>
						</div>
					</div>
					<p class="slz-officer__contact">
						<a href="mailto:<?php echo esc_attr( $staff->user_email ); ?>"><?php echo esc_html( $staff->user_email ); ?></a>
						<?php $sphone = (string) get_user_meta( $staff->ID, Salanaz_Profile::META_PHONE, true ); ?>
						<?php if ( $sphone ) : ?>
							<br /><a href="tel:<?php echo esc_attr( $sphone ); ?>"><?php echo esc_html( $sphone ); ?></a>
						<?php endif; ?>
					</p>
				<?php else : ?>
					<p class="slz-muted"><?php esc_html_e( 'A sales officer will be assigned to you shortly.', 'salanaz' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="slz-panel slz-panel--muted">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Paying by transfer?', 'salanaz' ); ?></h2>
				<p><?php esc_html_e( 'Always pay into the corporate account shown on your payment page. We never ask you to pay an individual.', 'salanaz' ); ?></p>
			</div>

			<div class="slz-panel slz-panel--muted">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Your details', 'salanaz' ); ?></h2>
				<p class="slz-muted slz-block-sm">
					<?php echo esc_html( $user->user_email ); ?><br />
					<?php echo esc_html( (string) get_user_meta( $user->ID, Salanaz_Profile::META_PHONE, true ) ); ?>
				</p>
				<p><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'salanaz' ); ?></a></p>
			</div>
		</aside>

	</div>
</div>
