<?php
/**
 * Sales staff dashboard — one client's file.
 *
 * Reached only after Salanaz_Users::staff_can_view_client() has passed.
 *
 * @var WP_User $user     The staff member.
 * @var WP_User $client   The client.
 * @var array   $holdings
 * @var array   $txns
 * @var array   $notes
 * @var string  $nonce
 * @var array   $notices
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$outstanding = 0.0;
$paid        = 0.0;

foreach ( $holdings as $holding ) {
	$outstanding += (float) $holding['outstanding'];
	$paid        += (float) $holding['paid'];
}

$phone = (string) get_user_meta( $client->ID, Salanaz_Profile::META_PHONE, true );
?>

<div class="slz-dash">

	<?php foreach ( $notices as $notice ) : ?>
		<div class="slz-notice slz-notice--<?php echo esc_attr( $notice['type'] ); ?>"><?php echo esc_html( $notice['text'] ); ?></div>
	<?php endforeach; ?>

	<p class="slz-dash__back">
		<a href="<?php echo esc_url( Salanaz_Auth::dashboard_url() ); ?>">&larr; <?php esc_html_e( 'Back to your clients', 'salanaz' ); ?></a>
	</p>

	<header class="slz-dash__head">
		<div>
			<h1><?php echo esc_html( $client->display_name ); ?></h1>
			<p class="slz-dash__sub">
				<?php echo esc_html( Salanaz_Profile::status_label( salanaz_account_status( $client->ID ) ) ); ?>
				&middot;
				<?php
				printf(
					/* translators: %s: registration date */
					esc_html__( 'Client since %s', 'salanaz' ),
					esc_html( mysql2date( 'j M Y', $client->user_registered ) )
				);
				?>
			</p>
		</div>
		<span class="slz-dash__price">
			<?php echo esc_html( salanaz_money( $outstanding ) ); ?>
			<small><?php esc_html_e( 'outstanding', 'salanaz' ); ?></small>
		</span>
	</header>

	<div class="slz-dash__grid">
		<div class="slz-dash__main">

			<!-- Plots -->
			<section class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Plots', 'salanaz' ); ?></h2>

				<?php if ( ! $holdings ) : ?>
					<p class="slz-muted"><?php esc_html_e( 'This client has not reserved a plot yet. A good reason to call them.', 'salanaz' ); ?></p>
				<?php else : ?>
					<?php foreach ( $holdings as $holding ) : ?>
						<?php $progress = $holding['total'] > 0 ? min( 100, ( $holding['paid'] / $holding['total'] ) * 100 ) : 0; ?>
						<article class="slz-holding">
							<header class="slz-holding__head">
								<div>
									<h3><a href="<?php echo esc_url( get_permalink( $holding['plot'] ) ); ?>"><?php echo esc_html( get_the_title( $holding['plot'] ) ); ?></a></h3>
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

							<?php if ( $holding['next_due'] ) : ?>
								<p class="slz-muted slz-block-sm">
									<?php
									printf(
										/* translators: 1: amount, 2: date */
										esc_html__( 'Next instalment %1$s, due %2$s.', 'salanaz' ),
										esc_html( salanaz_money( (float) $holding['next_due']->amount ) ),
										esc_html( mysql2date( 'j M Y', $holding['next_due']->due_date ) )
									);
									?>
								</p>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>

			<!-- Notes -->
			<section class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Notes and follow-ups', 'salanaz' ); ?></h2>

				<form method="post" action="" class="slz-form slz-noteform">
					<?php wp_nonce_field( $nonce ); ?>
					<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->ID ); ?>" />

					<p class="slz-field">
						<label for="note"><?php esc_html_e( 'Log an interaction', 'salanaz' ); ?></label>
						<textarea name="note" id="note" rows="3" required
							placeholder="<?php esc_attr_e( 'What was discussed, and what happens next?', 'salanaz' ); ?>"></textarea>
					</p>

					<div class="slz-form__row">
						<p class="slz-field">
							<label for="note_type"><?php esc_html_e( 'Type', 'salanaz' ); ?></label>
							<select name="note_type" id="note_type">
								<?php foreach ( Salanaz_Notes::types() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p class="slz-field">
							<label for="follow_up_date"><?php esc_html_e( 'Follow up on (optional)', 'salanaz' ); ?></label>
							<input type="date" name="follow_up_date" id="follow_up_date" />
						</p>
					</div>

					<p class="slz-form__submit">
						<button type="submit" name="salanaz_note_submit" value="1" class="slz-btn slz-btn--primary">
							<?php esc_html_e( 'Save note', 'salanaz' ); ?>
						</button>
					</p>
				</form>

				<?php if ( ! $notes ) : ?>
					<p class="slz-muted"><?php esc_html_e( 'No notes recorded yet.', 'salanaz' ); ?></p>
				<?php else : ?>
					<ol class="slz-timeline">
						<?php
						foreach ( $notes as $note ) :
							$author = get_userdata( (int) $note->author_id );
							?>
							<li class="slz-timeline__item">
								<div class="slz-timeline__head">
									<span class="slz-pill slz-pill--pending"><?php echo esc_html( Salanaz_Notes::type_label( $note->note_type ) ); ?></span>
									<span class="slz-muted">
										<?php echo esc_html( mysql2date( 'j M Y, g:i a', $note->created_at ) ); ?>
										<?php if ( $author instanceof WP_User ) : ?>
											&middot; <?php echo esc_html( $author->display_name ); ?>
										<?php endif; ?>
									</span>
								</div>
								<p class="slz-timeline__body"><?php echo esc_html( $note->note ); ?></p>
								<?php if ( $note->follow_up_date ) : ?>
									<p class="slz-timeline__followup">
										<?php
										printf(
											/* translators: %s: date */
											esc_html__( 'Follow up on %s', 'salanaz' ),
											esc_html( mysql2date( 'j M Y', $note->follow_up_date ) )
										);
										?>
									</p>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</section>

			<!-- Payments -->
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
									<th scope="col"><?php esc_html_e( 'Status', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Receipt', 'salanaz' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $txns as $txn ) : ?>
									<tr>
										<th scope="row"><code><?php echo esc_html( $txn->reference ); ?></code></th>
										<td><?php echo esc_html( mysql2date( 'j M Y', $txn->created_at ) ); ?></td>
										<td class="slz-table__price"><?php echo esc_html( salanaz_money( (float) $txn->amount ) ); ?></td>
										<td>
											<span class="slz-pill slz-pill--<?php echo esc_attr( Salanaz_Transactions::status_class( $txn->status ) ); ?>">
												<?php echo esc_html( Salanaz_Transactions::status_label( $txn->status ) ); ?>
											</span>
										</td>
										<td>
											<?php if ( $txn->receipt_path ) : ?>
												<a href="<?php echo esc_url( Salanaz_Documents::document_url( 'receipt', (int) $txn->id ) ); ?>" target="_blank" rel="noopener">
													<?php esc_html_e( 'PDF', 'salanaz' ); ?>
												</a>
											<?php else : ?>
												<span class="slz-muted">&mdash;</span>
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

		<aside class="slz-dash__aside">
			<div class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Contact', 'salanaz' ); ?></h2>
				<p class="slz-officer__contact">
					<a href="mailto:<?php echo esc_attr( $client->user_email ); ?>"><?php echo esc_html( $client->user_email ); ?></a>
					<?php if ( $phone ) : ?>
						<br /><a href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a>
					<?php endif; ?>
				</p>

				<?php
				$address = (string) get_user_meta( $client->ID, Salanaz_Profile::META_ADDRESS, true );
				$city    = (string) get_user_meta( $client->ID, Salanaz_Profile::META_CITY, true );
				$state   = (string) get_user_meta( $client->ID, Salanaz_Profile::META_STATE, true );
				$where   = trim( $city . ( $city && $state ? ', ' : '' ) . $state );
				?>

				<?php if ( $address || $where ) : ?>
					<h3 class="slz-panel__subtitle"><?php esc_html_e( 'Address', 'salanaz' ); ?></h3>
					<p class="slz-muted slz-block-sm">
						<?php echo esc_html( $address ); ?>
						<?php if ( $where ) : ?><br /><?php echo esc_html( $where ); ?><?php endif; ?>
					</p>
				<?php endif; ?>

				<?php
				$nok_name  = (string) get_user_meta( $client->ID, Salanaz_Profile::META_NOK_NAME, true );
				$nok_phone = (string) get_user_meta( $client->ID, Salanaz_Profile::META_NOK_PHONE, true );
				?>

				<?php if ( $nok_name || $nok_phone ) : ?>
					<h3 class="slz-panel__subtitle"><?php esc_html_e( 'Next of kin', 'salanaz' ); ?></h3>
					<p class="slz-muted slz-block-sm">
						<?php echo esc_html( $nok_name ); ?>
						<?php if ( $nok_phone ) : ?><br /><?php echo esc_html( $nok_phone ); ?><?php endif; ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="slz-panel slz-panel--muted">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Summary', 'salanaz' ); ?></h2>
				<dl class="slz-holding__facts">
					<div>
						<dt><?php esc_html_e( 'Paid', 'salanaz' ); ?></dt>
						<dd><?php echo esc_html( salanaz_money( $paid ) ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></dt>
						<dd><?php echo esc_html( salanaz_money( $outstanding ) ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Plots', 'salanaz' ); ?></dt>
						<dd><?php echo esc_html( (string) count( $holdings ) ); ?></dd>
					</div>
				</dl>
			</div>
		</aside>
	</div>

</div>
