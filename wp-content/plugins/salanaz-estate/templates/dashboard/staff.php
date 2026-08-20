<?php
/**
 * Sales staff dashboard — overview.
 *
 * @var WP_User $user
 * @var int[]   $clients    Assigned client IDs.
 * @var array   $follow_ups Latest dated note per client.
 * @var int     $due_count  Follow-ups due today or overdue.
 * @var array   $notices
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$today       = current_time( 'Y-m-d' );
$outstanding = 0.0;
$collected   = 0.0;
$rows        = array();

foreach ( $clients as $client_id ) {
	$client = get_userdata( $client_id );

	if ( ! $client instanceof WP_User ) {
		continue;
	}

	$holdings   = Salanaz_Dashboard::client_holdings( $client_id );
	$client_out = 0.0;
	$client_pd  = 0.0;

	foreach ( $holdings as $holding ) {
		$client_out += (float) $holding['outstanding'];
		$client_pd  += (float) $holding['paid'];
	}

	$outstanding += $client_out;
	$collected   += $client_pd;

	$rows[] = array(
		'client'      => $client,
		'holdings'    => $holdings,
		'outstanding' => $client_out,
		'paid'        => $client_pd,
		'status'      => salanaz_account_status( $client_id ),
	);
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
					esc_html__( 'Good to see you, %s', 'salanaz' ),
					esc_html( $user->first_name ?: $user->display_name )
				);
			?></h1>
			<p class="slz-dash__sub"><?php esc_html_e( 'Your clients and what needs attention today.', 'salanaz' ); ?></p>
		</div>
		<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
			<?php esc_html_e( 'View inventory', 'salanaz' ); ?>
		</a>
	</header>

	<div class="slz-dash__stats">
		<div class="slz-dstat">
			<span class="slz-dstat__label"><?php esc_html_e( 'Assigned clients', 'salanaz' ); ?></span>
			<span class="slz-dstat__value"><?php echo esc_html( (string) count( $rows ) ); ?></span>
		</div>
		<div class="slz-dstat">
			<span class="slz-dstat__label"><?php esc_html_e( 'Collected', 'salanaz' ); ?></span>
			<span class="slz-dstat__value"><?php echo esc_html( salanaz_money( $collected ) ); ?></span>
		</div>
		<div class="slz-dstat slz-dstat--accent">
			<span class="slz-dstat__label"><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></span>
			<span class="slz-dstat__value"><?php echo esc_html( salanaz_money( $outstanding ) ); ?></span>
		</div>
	</div>

	<div class="slz-dash__grid">
		<div class="slz-dash__main">

			<section class="slz-panel">
				<h2 class="slz-panel__title"><?php esc_html_e( 'Your clients', 'salanaz' ); ?></h2>

				<?php if ( ! $rows ) : ?>
					<div class="slz-panel__empty">
						<p><strong><?php esc_html_e( 'No clients assigned yet.', 'salanaz' ); ?></strong></p>
						<p><?php esc_html_e( 'A co-founder assigns clients to you after approving their registration.', 'salanaz' ); ?></p>
					</div>
				<?php else : ?>
					<div class="slz-table-wrap">
						<table class="slz-table">
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Client', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Contact', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Plots', 'salanaz' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></th>
									<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'salanaz' ); ?></span></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<?php $client = $row['client']; ?>
									<tr>
										<th scope="row">
											<?php echo esc_html( $client->display_name ); ?>
											<span class="slz-muted slz-block-sm">
												<?php echo esc_html( Salanaz_Profile::status_label( $row['status'] ) ); ?>
											</span>
										</th>
										<td>
											<a href="mailto:<?php echo esc_attr( $client->user_email ); ?>"><?php echo esc_html( $client->user_email ); ?></a>
											<?php $phone = (string) get_user_meta( $client->ID, Salanaz_Profile::META_PHONE, true ); ?>
											<?php if ( $phone ) : ?>
												<a class="slz-block-sm" href="tel:<?php echo esc_attr( $phone ); ?>"><?php echo esc_html( $phone ); ?></a>
											<?php endif; ?>
										</td>
										<td><?php echo esc_html( (string) count( $row['holdings'] ) ); ?></td>
										<td class="slz-table__price"><?php echo esc_html( salanaz_money( $row['outstanding'] ) ); ?></td>
										<td class="slz-table__action">
											<a class="slz-btn slz-btn--ghost slz-btn--sm"
												href="<?php echo esc_url( add_query_arg( array( 'view' => 'client', 'client' => $client->ID ), Salanaz_Auth::dashboard_url() ) ); ?>">
												<?php esc_html_e( 'Open file', 'salanaz' ); ?>
											</a>
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
				<h2 class="slz-panel__title">
					<?php esc_html_e( 'Follow-ups', 'salanaz' ); ?>
					<?php if ( $due_count > 0 ) : ?>
						<span class="slz-pill slz-pill--pending"><?php
							printf(
								/* translators: %d: number due */
								esc_html__( '%d due', 'salanaz' ),
								(int) $due_count
							);
						?></span>
					<?php endif; ?>
				</h2>

				<?php if ( ! $follow_ups ) : ?>
					<p class="slz-muted"><?php esc_html_e( 'Nothing scheduled. Add a follow-up date when you log a note.', 'salanaz' ); ?></p>
				<?php else : ?>
					<ul class="slz-followups">
						<?php
						foreach ( $follow_ups as $note ) :
							$client = get_userdata( (int) $note->client_id );

							if ( ! $client instanceof WP_User ) {
								continue;
							}

							$overdue = $note->follow_up_date <= $today;
							?>
							<li class="slz-followup <?php echo $overdue ? 'is-due' : ''; ?>">
								<a href="<?php echo esc_url( add_query_arg( array( 'view' => 'client', 'client' => $client->ID ), Salanaz_Auth::dashboard_url() ) ); ?>">
									<?php echo esc_html( $client->display_name ); ?>
								</a>
								<span class="slz-followup__date">
									<?php echo esc_html( mysql2date( 'j M Y', $note->follow_up_date ) ); ?>
								</span>
								<span class="slz-muted slz-block-sm"><?php echo esc_html( wp_trim_words( $note->note, 12 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="slz-panel slz-panel--muted">
				<h2 class="slz-panel__title"><?php esc_html_e( 'What you can do', 'salanaz' ); ?></h2>
				<p class="slz-muted">
					<?php esc_html_e( 'You can view your clients, log notes and follow-ups, and browse inventory. Approving payments and creating accounts are handled by management.', 'salanaz' ); ?>
				</p>
			</div>

			<div class="slz-panel slz-panel--muted">
				<p><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php esc_html_e( 'Sign out', 'salanaz' ); ?></a></p>
			</div>
		</aside>
	</div>

</div>
