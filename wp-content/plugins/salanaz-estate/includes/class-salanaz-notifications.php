<?php
/**
 * Transactional email.
 *
 * Batch 2 covers the account-lifecycle messages. Payment, installment reminder
 * and overdue messages are added in Batch 9, along with the cron wiring and the
 * SMTP configuration notes.
 *
 * Every message is sent as HTML through wp_mail(). Sites should route wp_mail()
 * through a real SMTP service (Brevo, Mailgun) — shared hosting PHP mail() is
 * routinely dropped by Gmail and Yahoo, which is where most Nigerian clients
 * read their mail.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and sends the plugin's emails.
 */
class Salanaz_Notifications {

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_filter( 'wp_mail_content_type', array( $this, 'content_type' ) );
	}

	/**
	 * Send HTML mail.
	 *
	 * @return string
	 */
	public function content_type(): string {
		return 'text/html';
	}

	/**
	 * Company name used in message copy.
	 *
	 * @return string
	 */
	private static function company(): string {
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Wrap body copy in the branded shell.
	 *
	 * Inline styles only — Gmail strips <style> blocks.
	 *
	 * @param string $heading Headline.
	 * @param string $body    HTML body, already escaped.
	 * @param array  $cta     Optional ['label' => string, 'url' => string].
	 * @return string
	 */
	private static function wrap( string $heading, string $body, array $cta = array() ): string {
		$button = '';

		if ( ! empty( $cta['url'] ) && ! empty( $cta['label'] ) ) {
			$button = sprintf(
				'<p style="margin:26px 0 0;"><a href="%s" style="display:inline-block;background:#e8a61a;color:#2a1e00;font-weight:700;text-decoration:none;padding:13px 26px;border-radius:8px;">%s</a></p>',
				esc_url( $cta['url'] ),
				esc_html( $cta['label'] )
			);
		}

		return sprintf(
			'<div style="background:#f6f8fb;padding:28px 12px;font-family:Segoe UI,Helvetica,Arial,sans-serif;">
				<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;">
					<div style="background:#0b3c8f;padding:22px 28px;">
						<div style="color:#ffffff;font-size:18px;font-weight:800;letter-spacing:-0.3px;">%1$s</div>
						<div style="color:#e8a61a;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;margin-top:3px;">%2$s</div>
					</div>
					<div style="padding:28px;color:#4a5261;font-size:15px;line-height:1.65;">
						<h1 style="margin:0 0 14px;color:#1a1d23;font-size:21px;line-height:1.3;">%3$s</h1>
						%4$s
						%5$s
					</div>
					<div style="padding:18px 28px;border-top:1px solid #e3e7ee;color:#7b8494;font-size:12px;line-height:1.6;">
						%6$s
					</div>
				</div>
			</div>',
			esc_html( self::company() ),
			esc_html__( 'Built on TRUST. Driven by EXCELLENCE.', 'salanaz' ),
			esc_html( $heading ),
			$body,
			$button,
			sprintf(
				/* translators: %s: company name */
				esc_html__( 'This message was sent by %s. If you did not expect it, please contact us before acting on it. We never ask you to pay into a personal bank account.', 'salanaz' ),
				esc_html( self::company() )
			)
		);
	}

	/**
	 * Send one message.
	 *
	 * @param string $to      Recipient address.
	 * @param string $subject Subject line.
	 * @param string $heading Headline in the body.
	 * @param string $body    HTML body.
	 * @param array  $cta     Optional call to action.
	 * @return bool
	 */
	private static function send( string $to, string $subject, string $heading, string $body, array $cta = array() ): bool {
		if ( ! is_email( $to ) ) {
			return false;
		}

		return wp_mail(
			$to,
			sprintf( '[%s] %s', self::company(), $subject ),
			self::wrap( $heading, $body, $cta ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/* =====================================================================
	 * Account lifecycle
	 * ================================================================== */

	/**
	 * Confirm a registration was received and is awaiting review.
	 *
	 * @param WP_User $user The new client.
	 */
	public static function registration_received( WP_User $user ): void {
		self::send(
			$user->user_email,
			__( 'We have received your registration', 'salanaz' ),
			sprintf(
				/* translators: %s: first name */
				__( 'Thank you, %s', 'salanaz' ),
				$user->first_name ?: $user->display_name
			),
			'<p>' . esc_html__( 'Your account has been created and is now waiting for approval by our team. We review new registrations during business hours and you will hear from us shortly.', 'salanaz' ) . '</p>'
			. '<p>' . esc_html__( 'Once approved, you will be able to reserve a plot, choose a payment plan and track every payment from your dashboard.', 'salanaz' ) . '</p>'
		);

		self::notify_cofounders_of_registration( $user );
	}

	/**
	 * Tell the co-founders somebody is waiting for approval.
	 *
	 * @param WP_User $user The new client.
	 */
	private static function notify_cofounders_of_registration( WP_User $user ): void {
		foreach ( self::cofounders() as $cofounder ) {
			self::send(
				$cofounder->user_email,
				__( 'New client registration awaiting approval', 'salanaz' ),
				__( 'A new client has registered', 'salanaz' ),
				'<p>' . sprintf(
					/* translators: 1: client name, 2: email, 3: phone */
					esc_html__( '%1$s (%2$s, %3$s) has registered and is waiting for approval.', 'salanaz' ),
					esc_html( $user->display_name ),
					esc_html( $user->user_email ),
					esc_html( (string) get_user_meta( $user->ID, Salanaz_Profile::META_PHONE, true ) )
				) . '</p>',
				array(
					'label' => __( 'Review registration', 'salanaz' ),
					'url'   => admin_url( 'admin.php?page=salanaz-approvals' ),
				)
			);
		}
	}

	/**
	 * Welcome an approved client.
	 *
	 * @param WP_User $user The client.
	 */
	public static function client_approved( WP_User $user ): void {
		self::send(
			$user->user_email,
			__( 'Your account has been approved', 'salanaz' ),
			sprintf(
				/* translators: %s: first name */
				__( 'Welcome aboard, %s', 'salanaz' ),
				$user->first_name ?: $user->display_name
			),
			'<p>' . esc_html__( 'Your account has been approved. You can now log in, browse available plots, reserve one and choose a payment plan that suits you.', 'salanaz' ) . '</p>'
			. '<p>' . esc_html__( 'A sales officer will be assigned to you and will be in touch. You can always see their name and phone number on your dashboard.', 'salanaz' ) . '</p>',
			array(
				'label' => __( 'Go to your dashboard', 'salanaz' ),
				'url'   => home_url( '/dashboard/' ),
			)
		);
	}

	/**
	 * Tell a client their registration was declined.
	 *
	 * @param WP_User $user   The client.
	 * @param string  $reason Optional reason supplied by the co-founder.
	 */
	public static function client_rejected( WP_User $user, string $reason = '' ): void {
		$body = '<p>' . esc_html__( 'We are sorry — we were not able to approve your registration at this time.', 'salanaz' ) . '</p>';

		if ( $reason ) {
			$body .= '<p><strong>' . esc_html__( 'Reason given:', 'salanaz' ) . '</strong><br />' . esc_html( $reason ) . '</p>';
		}

		$body .= '<p>' . esc_html__( 'If you believe this is a mistake, please reply to this message or call our office and we will look at it again.', 'salanaz' ) . '</p>';

		self::send(
			$user->user_email,
			__( 'About your registration', 'salanaz' ),
			__( 'We could not approve your registration', 'salanaz' ),
			$body
		);
	}

	/**
	 * Send a new sales staff member their credentials.
	 *
	 * The password is included because the co-founder creates the account on
	 * the staff member's behalf and they have no other way in. It is a one-time
	 * password and the message tells them to change it.
	 *
	 * @param WP_User $user     The staff member.
	 * @param string  $password Generated password.
	 */
	public static function staff_created( WP_User $user, string $password ): void {
		self::send(
			$user->user_email,
			__( 'Your sales staff account', 'salanaz' ),
			sprintf(
				/* translators: %s: first name */
				__( 'Your account is ready, %s', 'salanaz' ),
				$user->first_name ?: $user->display_name
			),
			'<p>' . esc_html__( 'A sales staff account has been created for you. Use the details below to log in, then change your password immediately.', 'salanaz' ) . '</p>'
			. '<div style="background:#f6f8fb;border-radius:10px;padding:16px;margin:18px 0;">'
			. '<div><strong>' . esc_html__( 'Username:', 'salanaz' ) . '</strong> ' . esc_html( $user->user_login ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Temporary password:', 'salanaz' ) . '</strong> <code>' . esc_html( $password ) . '</code></div>'
			. '</div>'
			. '<p>' . esc_html__( 'Never share this password. Our team will never ask you for it.', 'salanaz' ) . '</p>',
			array(
				'label' => __( 'Log in', 'salanaz' ),
				'url'   => home_url( '/login/' ),
			)
		);
	}

	/**
	 * Tell a sales staff member a client has been assigned to them.
	 *
	 * @param WP_User $staff  The staff member.
	 * @param WP_User $client The client.
	 */
	public static function client_assigned( WP_User $staff, WP_User $client ): void {
		self::send(
			$staff->user_email,
			__( 'A new client has been assigned to you', 'salanaz' ),
			__( 'New client assigned', 'salanaz' ),
			'<p>' . sprintf(
				/* translators: %s: client name */
				esc_html__( '%s has been assigned to you. Please make contact within 24 hours.', 'salanaz' ),
				esc_html( $client->display_name )
			) . '</p>'
			. '<div style="background:#f6f8fb;border-radius:10px;padding:16px;margin:18px 0;">'
			. '<div><strong>' . esc_html__( 'Email:', 'salanaz' ) . '</strong> ' . esc_html( $client->user_email ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Phone:', 'salanaz' ) . '</strong> ' . esc_html( (string) get_user_meta( $client->ID, Salanaz_Profile::META_PHONE, true ) ) . '</div>'
			. '</div>',
			array(
				'label' => __( 'Open your dashboard', 'salanaz' ),
				'url'   => home_url( '/dashboard/' ),
			)
		);
	}

	/**
	 * Tell a client who their sales officer is.
	 *
	 * @param WP_User $client The client.
	 * @param WP_User $staff  The staff member.
	 */
	public static function staff_introduced( WP_User $client, WP_User $staff ): void {
		self::send(
			$client->user_email,
			__( 'Your sales officer', 'salanaz' ),
			__( 'Meet your sales officer', 'salanaz' ),
			'<p>' . sprintf(
				/* translators: %s: staff name */
				esc_html__( '%s will be looking after your account from here on. They will contact you shortly, and you can reach them any time using the details below.', 'salanaz' ),
				esc_html( $staff->display_name )
			) . '</p>'
			. '<div style="background:#f6f8fb;border-radius:10px;padding:16px;margin:18px 0;">'
			. '<div><strong>' . esc_html__( 'Email:', 'salanaz' ) . '</strong> ' . esc_html( $staff->user_email ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Phone:', 'salanaz' ) . '</strong> ' . esc_html( (string) get_user_meta( $staff->ID, Salanaz_Profile::META_PHONE, true ) ) . '</div>'
			. '</div>'
		);
	}

	/* =====================================================================
	 * Payments
	 * ================================================================== */

	/**
	 * Confirm a payment was verified.
	 *
	 * @param WP_User $client The client.
	 * @param object  $txn    Transaction row.
	 */
	public static function payment_verified( WP_User $client, object $txn ): void {
		$plot    = get_post( (int) $txn->plot_id );
		$paid    = Salanaz_Transactions::paid_for_plot( (int) $txn->client_id, (int) $txn->plot_id );
		$plan    = Salanaz_Plans::plan_for_plot( (int) $txn->client_id, (int) $txn->plot_id );
		$total   = $plan ? (float) $plan->total_amount : Salanaz_Inventory::plot_price( (int) $txn->plot_id );
		$balance = max( 0, $total - $paid );

		$body = '<p>' . sprintf(
			/* translators: %s: amount */
			esc_html__( 'We have confirmed your payment of %s. Thank you.', 'salanaz' ),
			esc_html( salanaz_money( (float) $txn->amount ) )
		) . '</p>'
		. '<div style="background:#f6f8fb;border-radius:10px;padding:16px;margin:18px 0;">'
		. '<div><strong>' . esc_html__( 'Reference:', 'salanaz' ) . '</strong> ' . esc_html( $txn->reference ) . '</div>';

		if ( $plot instanceof WP_Post ) {
			$body .= '<div style="margin-top:6px;"><strong>' . esc_html__( 'Plot:', 'salanaz' ) . '</strong> ' . esc_html( get_the_title( $plot ) ) . '</div>';
		}

		$body .= '<div style="margin-top:6px;"><strong>' . esc_html__( 'Total paid:', 'salanaz' ) . '</strong> ' . esc_html( salanaz_money( $paid ) ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Outstanding:', 'salanaz' ) . '</strong> ' . esc_html( salanaz_money( $balance ) ) . '</div>'
			. '</div>';

		if ( $balance <= 0.005 ) {
			$body .= '<p>' . esc_html__( 'Your plot is now fully paid. We will prepare your allocation letter and be in touch about the handover.', 'salanaz' ) . '</p>';
		}

		self::send(
			$client->user_email,
			__( 'Payment confirmed', 'salanaz' ),
			__( 'Your payment has been verified', 'salanaz' ),
			$body,
			array(
				'label' => __( 'View your dashboard', 'salanaz' ),
				'url'   => home_url( '/dashboard/' ),
			)
		);
	}

	/**
	 * Tell a client a payment could not be verified.
	 *
	 * @param WP_User $client The client.
	 * @param object  $txn    Transaction row.
	 * @param string  $reason Reason given.
	 */
	public static function payment_rejected( WP_User $client, object $txn, string $reason = '' ): void {
		$body = '<p>' . sprintf(
			/* translators: 1: amount, 2: reference */
			esc_html__( 'We were not able to verify your payment of %1$s (reference %2$s).', 'salanaz' ),
			esc_html( salanaz_money( (float) $txn->amount ) ),
			esc_html( $txn->reference )
		) . '</p>';

		if ( $reason ) {
			$body .= '<p><strong>' . esc_html__( 'Reason given:', 'salanaz' ) . '</strong><br />' . esc_html( $reason ) . '</p>';
		}

		$body .= '<p>' . esc_html__( 'This usually means the transfer had not cleared, or the proof did not match the amount. Please check with your bank and upload again, or call your sales officer.', 'salanaz' ) . '</p>';

		self::send(
			$client->user_email,
			__( 'About your payment', 'salanaz' ),
			__( 'We could not verify your payment', 'salanaz' ),
			$body,
			array(
				'label' => __( 'Upload again', 'salanaz' ),
				'url'   => home_url( '/dashboard/' ),
			)
		);
	}

	/* =====================================================================
	 * Installment reminders
	 * ================================================================== */

	/**
	 * Warn a client that an instalment is nearly due.
	 *
	 * @param WP_User $client      The client.
	 * @param object  $installment Installment row, joined with client_id/plot_id.
	 * @param int     $days        Days until due.
	 */
	public static function installment_due_soon( WP_User $client, object $installment, int $days ): void {
		$plot = get_post( (int) $installment->plot_id );
		$due  = mysql2date( 'j F Y', $installment->due_date );

		$heading = 1 === $days
			? __( 'Your payment is due tomorrow', 'salanaz' )
			: sprintf(
				/* translators: %d: number of days */
				__( 'Your payment is due in %d days', 'salanaz' ),
				$days
			);

		$body = '<p>' . sprintf(
			/* translators: 1: first name, 2: amount, 3: date */
			esc_html__( 'Hello %1$s, this is a friendly reminder that your next instalment of %2$s is due on %3$s.', 'salanaz' ),
			esc_html( $client->first_name ?: $client->display_name ),
			esc_html( salanaz_money( (float) $installment->amount ) ),
			esc_html( $due )
		) . '</p>';

		$body .= '<div style="background:#f6f8fb;border-radius:10px;padding:16px;margin:18px 0;">'
			. '<div><strong>' . esc_html__( 'Instalment:', 'salanaz' ) . '</strong> #' . esc_html( (string) $installment->installment_no ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Amount:', 'salanaz' ) . '</strong> ' . esc_html( salanaz_money( (float) $installment->amount ) ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Due date:', 'salanaz' ) . '</strong> ' . esc_html( $due ) . '</div>';

		if ( $plot instanceof WP_Post ) {
			$body .= '<div style="margin-top:6px;"><strong>' . esc_html__( 'Plot:', 'salanaz' ) . '</strong> ' . esc_html( get_the_title( $plot ) ) . '</div>';
		}

		$body .= '</div>';

		$body .= '<p>' . esc_html__( 'If you have already paid, thank you — you can ignore this message. Payments can take a little time to appear once we have checked them.', 'salanaz' ) . '</p>';

		self::send(
			$client->user_email,
			1 === $days ? __( 'Payment due tomorrow', 'salanaz' ) : __( 'Payment due soon', 'salanaz' ),
			$heading,
			$body,
			array(
				'label' => __( 'Make this payment', 'salanaz' ),
				'url'   => add_query_arg(
					array(
						'view' => 'pay',
						'plot' => (int) $installment->plot_id,
					),
					home_url( '/dashboard/' )
				),
			)
		);
	}

	/**
	 * Tell a client an instalment is overdue, and escalate it.
	 *
	 * @param WP_User $client      The client.
	 * @param object  $installment Installment row, joined with client_id/plot_id.
	 */
	public static function installment_overdue( WP_User $client, object $installment ): void {
		$plot = get_post( (int) $installment->plot_id );
		$due  = mysql2date( 'j F Y', $installment->due_date );
		$late = max( 1, (int) floor( ( time() - strtotime( $installment->due_date ) ) / DAY_IN_SECONDS ) );

		$body = '<p>' . sprintf(
			/* translators: 1: first name, 2: amount, 3: date */
			esc_html__( 'Hello %1$s, our records show that your instalment of %2$s, due on %3$s, has not yet been received.', 'salanaz' ),
			esc_html( $client->first_name ?: $client->display_name ),
			esc_html( salanaz_money( (float) $installment->amount ) ),
			esc_html( $due )
		) . '</p>';

		$body .= '<div style="background:#fdeceb;border-radius:10px;padding:16px;margin:18px 0;">'
			. '<div><strong>' . esc_html__( 'Amount outstanding:', 'salanaz' ) . '</strong> ' . esc_html( salanaz_money( (float) $installment->amount - (float) $installment->amount_paid ) ) . '</div>'
			. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Days late:', 'salanaz' ) . '</strong> ' . esc_html( (string) $late ) . '</div>'
			. '</div>';

		$body .= '<p>' . esc_html__( 'Your plot remains reserved for you. Please make the payment, or speak to your sales officer if you need to discuss the schedule — we would much rather hear from you than not.', 'salanaz' ) . '</p>';

		self::send(
			$client->user_email,
			__( 'Payment overdue', 'salanaz' ),
			__( 'A payment is overdue', 'salanaz' ),
			$body,
			array(
				'label' => __( 'Make this payment', 'salanaz' ),
				'url'   => add_query_arg(
					array(
						'view' => 'pay',
						'plot' => (int) $installment->plot_id,
					),
					home_url( '/dashboard/' )
				),
			)
		);

		self::escalate_overdue( $client, $installment, $plot, $late );
	}

	/**
	 * Copy an overdue instalment to the sales officer and the co-founders.
	 *
	 * @param WP_User      $client      The client.
	 * @param object       $installment Installment row.
	 * @param WP_Post|null $plot        The plot.
	 * @param int          $late        Days late.
	 */
	private static function escalate_overdue( WP_User $client, object $installment, ?WP_Post $plot, int $late ): void {
		$detail = '<p>' . sprintf(
			/* translators: 1: client name, 2: amount, 3: days late */
			esc_html__( '%1$s has an instalment of %2$s that is now %3$d days late.', 'salanaz' ),
			esc_html( $client->display_name ),
			esc_html( salanaz_money( (float) $installment->amount ) ),
			(int) $late
		) . '</p>'
		. '<div style="background:#f6f8fb;border-radius:10px;padding:16px;margin:18px 0;">'
		. '<div><strong>' . esc_html__( 'Client:', 'salanaz' ) . '</strong> ' . esc_html( $client->display_name ) . '</div>'
		. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Phone:', 'salanaz' ) . '</strong> ' . esc_html( (string) get_user_meta( $client->ID, Salanaz_Profile::META_PHONE, true ) ) . '</div>'
		. ( $plot instanceof WP_Post ? '<div style="margin-top:6px;"><strong>' . esc_html__( 'Plot:', 'salanaz' ) . '</strong> ' . esc_html( get_the_title( $plot ) ) . '</div>' : '' )
		. '<div style="margin-top:6px;"><strong>' . esc_html__( 'Due:', 'salanaz' ) . '</strong> ' . esc_html( mysql2date( 'j F Y', $installment->due_date ) ) . '</div>'
		. '</div>';

		$staff = Salanaz_Users::assigned_staff( $client->ID );

		if ( $staff instanceof WP_User ) {
			self::send(
				$staff->user_email,
				__( 'Your client has an overdue payment', 'salanaz' ),
				__( 'Overdue payment to chase', 'salanaz' ),
				$detail . '<p>' . esc_html__( 'Please make contact today and log the outcome on their file.', 'salanaz' ) . '</p>',
				array(
					'label' => __( 'Open client file', 'salanaz' ),
					'url'   => add_query_arg(
						array(
							'view'   => 'client',
							'client' => $client->ID,
						),
						home_url( '/dashboard/' )
					),
				)
			);
		}

		$officer = $staff instanceof WP_User
			? $staff->display_name
			: __( 'nobody — this client has no sales officer assigned', 'salanaz' );

		foreach ( self::cofounders() as $cofounder ) {
			self::send(
				$cofounder->user_email,
				__( 'Overdue instalment', 'salanaz' ),
				__( 'An instalment has gone overdue', 'salanaz' ),
				$detail . '<p><strong>' . esc_html__( 'Sales officer:', 'salanaz' ) . '</strong> ' . esc_html( $officer ) . '</p>',
				array(
					'label' => __( 'Open the admin', 'salanaz' ),
					'url'   => admin_url( 'admin.php?page=salanaz-payments' ),
				)
			);
		}
	}

	/**
	 * All co-founder accounts.
	 *
	 * @return WP_User[]
	 */
	public static function cofounders(): array {
		return get_users( array( 'role' => Salanaz_Roles::COFOUNDER ) );
	}
}
