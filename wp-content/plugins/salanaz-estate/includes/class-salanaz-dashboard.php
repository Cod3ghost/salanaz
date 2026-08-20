<?php
/**
 * Role-aware front-end dashboard.
 *
 * A single `/dashboard/` page carrying `[salanaz_dashboard]` serves clients and
 * sales staff. Co-founders are sent to wp-admin, where their tooling lives.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard rendering and its form handlers.
 */
class Salanaz_Dashboard {

	private const NONCE_RESERVE = 'salanaz_reserve';
	private const NONCE_PAY     = 'salanaz_pay';
	private const NONCE_NOTE    = 'salanaz_note';

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_shortcode( 'salanaz_dashboard', array( $this, 'render' ) );
		add_action( 'template_redirect', array( $this, 'handle_reserve' ) );
		add_action( 'template_redirect', array( $this, 'handle_payment' ) );
		add_action( 'template_redirect', array( $this, 'handle_note' ) );
		add_action( 'template_redirect', array( $this, 'handle_card_payment' ) );
	}

	/* =====================================================================
	 * Rendering
	 * ================================================================== */

	/**
	 * Render the dashboard for whoever is signed in.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return Salanaz_Templates::render( 'dashboard/signed-out.php' );
		}

		if ( current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) ) {
			return Salanaz_Templates::render( 'dashboard/cofounder.php' );
		}

		if ( salanaz_user_has_role( Salanaz_Roles::STAFF ) ) {
			return $this->render_staff( wp_get_current_user() );
		}

		if ( ! salanaz_user_has_role( Salanaz_Roles::CLIENT ) ) {
			return Salanaz_Templates::notice( __( 'There is no dashboard for this account type.', 'salanaz' ), 'info' );
		}

		return $this->render_client( wp_get_current_user() );
	}

	/**
	 * Client dashboard.
	 *
	 * @param WP_User $user The client.
	 * @return string
	 */
	private function render_client( WP_User $user ): string {
		$status = salanaz_account_status( $user->ID );

		if ( Salanaz_Profile::STATUS_APPROVED !== $status ) {
			return Salanaz_Templates::render(
				'dashboard/pending.php',
				array(
					'user'   => $user,
					'status' => $status,
				)
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'overview';
		$plot_id = isset( $_GET['plot'] ) ? absint( $_GET['plot'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// A plot carried through registration.
		if ( ! $plot_id ) {
			$plot_id = (int) get_user_meta( $user->ID, 'salanaz_interest_plot_id', true );

			if ( $plot_id && 'reserve' !== $view && ! self::client_holds_plot( $user->ID, $plot_id ) ) {
				$view = 'reserve';
			}
		}

		$vars = array(
			'user'     => $user,
			'staff'    => Salanaz_Users::assigned_staff( $user->ID ),
			'plans'    => Salanaz_Plans::plans_for_client( $user->ID ),
			'txns'     => Salanaz_Transactions::for_client( $user->ID ),
			'holdings' => self::client_holdings( $user->ID ),
			'notices'  => $this->flash_notices(),
			'view'     => $view,
			'plot_id'  => $plot_id,
		);

		if ( 'reserve' === $view ) {
			$vars['plot']  = $plot_id ? get_post( $plot_id ) : null;
			$vars['nonce'] = self::NONCE_RESERVE;

			return Salanaz_Templates::render( 'dashboard/reserve.php', $vars );
		}

		if ( 'pay' === $view ) {
			$vars['nonce'] = self::NONCE_PAY;

			return Salanaz_Templates::render( 'dashboard/pay.php', $vars );
		}

		return Salanaz_Templates::render( 'dashboard/client.php', $vars );
	}

	/**
	 * Messages carried across a redirect.
	 *
	 * @return array<int, array{type:string, text:string}>
	 */
	private function flash_notices(): array {
		$key    = 'salanaz_flash_' . get_current_user_id();
		$flash  = get_transient( $key );
		delete_transient( $key );

		return is_array( $flash ) ? $flash : array();
	}

	/**
	 * Queue a message and redirect.
	 *
	 * @param string $text Message.
	 * @param string $type info|success|warning|error.
	 * @param string $to   Destination URL.
	 */
	private function redirect_with( string $text, string $type, string $to ): void {
		set_transient(
			'salanaz_flash_' . get_current_user_id(),
			array( array( 'type' => $type, 'text' => $text ) ),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( $to );
		exit;
	}

	/**
	 * Sales staff dashboard.
	 *
	 * @param WP_User $user The staff member.
	 * @return string
	 */
	private function render_staff( WP_User $user ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view routing.
		$view      = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'overview';
		$client_id = isset( $_GET['client'] ) ? absint( $_GET['client'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$vars = array(
			'user'    => $user,
			'notices' => $this->flash_notices(),
		);

		if ( 'client' === $view && $client_id ) {
			// The gate: an officer may only open a client assigned to them.
			if ( ! Salanaz_Users::staff_can_view_client( $client_id ) ) {
				return Salanaz_Templates::notice(
					__( 'That client is not assigned to you.', 'salanaz' ),
					'error'
				);
			}

			$client = get_userdata( $client_id );

			if ( ! $client instanceof WP_User ) {
				return Salanaz_Templates::notice( __( 'That client could not be found.', 'salanaz' ), 'error' );
			}

			$vars['client']   = $client;
			$vars['holdings'] = self::client_holdings( $client_id );
			$vars['txns']     = Salanaz_Transactions::for_client( $client_id );
			$vars['notes']    = Salanaz_Notes::for_client( $client_id );
			$vars['nonce']    = self::NONCE_NOTE;

			return Salanaz_Templates::render( 'dashboard/staff-client.php', $vars );
		}

		$vars['clients']    = Salanaz_Users::clients_for_staff( $user->ID );
		$vars['follow_ups'] = Salanaz_Notes::follow_ups_for_staff( $user->ID );
		$vars['due_count']  = Salanaz_Notes::due_count( $user->ID );

		return Salanaz_Templates::render( 'dashboard/staff.php', $vars );
	}

	/**
	 * Start a Paystack checkout for a reserved plot.
	 */
	public function handle_card_payment(): void {
		if ( ! isset( $_POST['salanaz_card_submit'] ) ) {
			return;
		}

		$dashboard = Salanaz_Auth::dashboard_url();

		if ( ! current_user_can( Salanaz_Roles::CAP_PURCHASE_PLOT ) ) {
			$this->redirect_with( __( 'Your account cannot make payments yet.', 'salanaz' ), 'error', $dashboard );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_PAY ) ) {
			$this->redirect_with( __( 'Your session expired. Please try again.', 'salanaz' ), 'error', $dashboard );
		}

		$client_id = get_current_user_id();
		$plot_id   = absint( $_POST['plot_id'] ?? 0 );
		$amount    = round( (float) ( $_POST['amount'] ?? 0 ), 2 );

		$back = add_query_arg( array( 'view' => 'pay', 'plot' => $plot_id ), $dashboard );

		// Same ownership rule as the manual route: you may only pay for a plot
		// that is actually held in your name.
		if ( ! $plot_id || ! self::client_holds_plot( $client_id, $plot_id ) ) {
			$this->redirect_with( __( 'That plot is not reserved to your account.', 'salanaz' ), 'error', $dashboard );
		}

		if ( $amount <= 0 ) {
			$this->redirect_with( __( 'Please enter the amount you want to pay.', 'salanaz' ), 'error', $back );
		}

		$url = Salanaz_Paystack::start_checkout( $client_id, $plot_id, $amount );

		if ( is_wp_error( $url ) ) {
			$this->redirect_with( $url->get_error_message(), 'error', $back );
		}

		// Off-site to Paystack; wp_safe_redirect would strip an external host.
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- deliberate off-site gateway hand-off.
		exit;
	}

	/* =====================================================================
	 * Notes
	 * ================================================================== */

	/**
	 * Handle the add-note form on a client file.
	 */
	public function handle_note(): void {
		if ( ! isset( $_POST['salanaz_note_submit'] ) ) {
			return;
		}

		$dashboard = Salanaz_Auth::dashboard_url();

		if ( ! current_user_can( Salanaz_Roles::CAP_ADD_CLIENT_NOTES ) ) {
			$this->redirect_with( __( 'You are not allowed to add notes.', 'salanaz' ), 'error', $dashboard );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_NOTE ) ) {
			$this->redirect_with( __( 'Your session expired. Please try again.', 'salanaz' ), 'error', $dashboard );
		}

		$client_id = absint( $_POST['client_id'] ?? 0 );
		$back      = add_query_arg(
			array(
				'view'   => 'client',
				'client' => $client_id,
			),
			$dashboard
		);

		$result = Salanaz_Notes::add(
			$client_id,
			sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ),
			sanitize_key( wp_unslash( $_POST['note_type'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['follow_up_date'] ?? '' ) )
		);

		if ( is_wp_error( $result ) ) {
			$this->redirect_with( $result->get_error_message(), 'error', $back );
		}

		$this->redirect_with( __( 'Note saved.', 'salanaz' ), 'success', $back );
	}

	/* =====================================================================
	 * Holdings
	 * ================================================================== */

	/**
	 * Plots this client has reserved or bought, with their money position.
	 *
	 * @param int $client_id Client user ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function client_holdings( int $client_id ): array {
		$plots = get_posts(
			array(
				'post_type'      => Salanaz_Post_Types::PLOT,
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => Salanaz_Post_Types::META_PLOT_RESERVED_BY,
						'value' => $client_id,
					),
				),
			)
		);

		$out = array();

		foreach ( $plots as $plot ) {
			$plan  = Salanaz_Plans::plan_for_plot( $client_id, $plot->ID );
			$total = $plan ? (float) $plan->total_amount : Salanaz_Inventory::plot_price( $plot->ID );
			$paid  = Salanaz_Transactions::paid_for_plot( $client_id, $plot->ID );

			$out[] = array(
				'plot'        => $plot,
				'estate'      => Salanaz_Inventory::plot_estate( $plot->ID ),
				'plan'        => $plan,
				'total'       => $total,
				'paid'        => $paid,
				'outstanding' => max( 0, $total - $paid ),
				'next_due'    => $plan ? Salanaz_Plans::next_due( (int) $plan->id ) : null,
				'status'      => Salanaz_Inventory::plot_status( $plot->ID ),
			);
		}

		return $out;
	}

	/**
	 * Whether a client already holds a given plot.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 * @return bool
	 */
	public static function client_holds_plot( int $client_id, int $plot_id ): bool {
		return (int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_RESERVED_BY, true ) === $client_id;
	}

	/* =====================================================================
	 * Reservation
	 * ================================================================== */

	/**
	 * Handle the reserve-a-plot form.
	 */
	public function handle_reserve(): void {
		if ( ! isset( $_POST['salanaz_reserve_submit'] ) ) {
			return;
		}

		$dashboard = Salanaz_Auth::dashboard_url();

		if ( ! current_user_can( Salanaz_Roles::CAP_PURCHASE_PLOT ) ) {
			$this->redirect_with( __( 'Your account cannot reserve plots yet.', 'salanaz' ), 'error', $dashboard );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_RESERVE ) ) {
			$this->redirect_with( __( 'Your session expired. Please try again.', 'salanaz' ), 'error', $dashboard );
		}

		$client_id = get_current_user_id();

		if ( Salanaz_Profile::STATUS_APPROVED !== salanaz_account_status( $client_id ) ) {
			$this->redirect_with( __( 'Your account is still awaiting approval.', 'salanaz' ), 'warning', $dashboard );
		}

		$plot_id = absint( $_POST['plot_id'] ?? 0 );
		$plan_key = sanitize_key( wp_unslash( $_POST['plan'] ?? '' ) );

		$plot = get_post( $plot_id );

		if ( ! $plot instanceof WP_Post || Salanaz_Post_Types::PLOT !== $plot->post_type ) {
			$this->redirect_with( __( 'That plot could not be found.', 'salanaz' ), 'error', $dashboard );
		}

		if ( ! Salanaz_Plans::exists( $plan_key ) ) {
			$this->redirect_with( __( 'Please choose a valid payment plan.', 'salanaz' ), 'error', $dashboard );
		}

		// Re-read status at the last moment: two clients can reach this form for
		// the same plot at the same time.
		if ( Salanaz_Post_Types::STATUS_AVAILABLE !== Salanaz_Inventory::plot_status( $plot_id ) ) {
			$this->redirect_with(
				__( 'Sorry — that plot has just been taken. Please choose another.', 'salanaz' ),
				'warning',
				$dashboard
			);
		}

		$price = Salanaz_Inventory::plot_price( $plot_id );
		$quote = Salanaz_Plans::quote( $plan_key, $price );

		if ( ! $quote ) {
			$this->redirect_with( __( 'That plan is not available for this plot.', 'salanaz' ), 'error', $dashboard );
		}

		// Hold the plot.
		update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_STATUS, Salanaz_Post_Types::STATUS_RESERVED );
		update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_RESERVED_BY, $client_id );

		$plan_id = null;

		if ( $quote['months'] > 0 ) {
			$created = Salanaz_Plans::create_plan( $client_id, $plot_id, $plan_key, $price );

			if ( is_wp_error( $created ) ) {
				// Roll the hold back rather than stranding the plot.
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_STATUS, Salanaz_Post_Types::STATUS_AVAILABLE );
				delete_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_RESERVED_BY );

				$this->redirect_with( $created->get_error_message(), 'error', $dashboard );
			}

			$plan_id = $created;
		}

		delete_user_meta( $client_id, 'salanaz_interest_plot_id' );
		Salanaz_Inventory::flush_estate_stats( (int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_ESTATE, true ) );

		Salanaz_Activity::log(
			'plot_reserved',
			array(
				'subject_id'  => $client_id,
				'object_type' => 'plot',
				'object_id'   => $plot_id,
				'details'     => array( 'plan' => $plan_key ),
			)
		);

		$this->redirect_with(
			sprintf(
				/* translators: %s: amount due now */
				__( 'Plot reserved. Please pay %s to confirm it.', 'salanaz' ),
				salanaz_money( $quote['deposit'] )
			),
			'success',
			add_query_arg(
				array(
					'view' => 'pay',
					'plot' => $plot_id,
				),
				$dashboard
			)
		);
	}

	/* =====================================================================
	 * Payment
	 * ================================================================== */

	/**
	 * Handle the manual payment-proof form.
	 */
	public function handle_payment(): void {
		if ( ! isset( $_POST['salanaz_pay_submit'] ) ) {
			return;
		}

		$dashboard = Salanaz_Auth::dashboard_url();

		if ( ! current_user_can( Salanaz_Roles::CAP_UPLOAD_PAYMENT_PROOF ) ) {
			$this->redirect_with( __( 'Your account cannot submit payments yet.', 'salanaz' ), 'error', $dashboard );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_PAY ) ) {
			$this->redirect_with( __( 'Your session expired. Please try again.', 'salanaz' ), 'error', $dashboard );
		}

		$client_id = get_current_user_id();
		$plot_id   = absint( $_POST['plot_id'] ?? 0 );
		$amount    = round( (float) ( $_POST['amount'] ?? 0 ), 2 );
		$note      = sanitize_textarea_field( wp_unslash( $_POST['payer_note'] ?? '' ) );

		$back = add_query_arg( array( 'view' => 'pay', 'plot' => $plot_id ), $dashboard );

		// The plot must actually be held by this client — otherwise anyone could
		// post a payment against someone else's reservation.
		if ( ! $plot_id || ! self::client_holds_plot( $client_id, $plot_id ) ) {
			$this->redirect_with( __( 'That plot is not reserved to your account.', 'salanaz' ), 'error', $dashboard );
		}

		if ( $amount <= 0 ) {
			$this->redirect_with( __( 'Please enter the amount you paid.', 'salanaz' ), 'error', $back );
		}

		if ( empty( $_FILES['proof'] ) ) {
			$this->redirect_with( __( 'Please attach your proof of payment.', 'salanaz' ), 'error', $back );
		}

		$stored = Salanaz_Uploads::store_proof( $_FILES['proof'], $client_id ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated inside store_proof().

		if ( is_wp_error( $stored ) ) {
			$this->redirect_with( $stored->get_error_message(), 'error', $back );
		}

		$plan = Salanaz_Plans::plan_for_plot( $client_id, $plot_id );

		$txn_id = Salanaz_Transactions::create(
			array(
				'client_id'      => $client_id,
				'plot_id'        => $plot_id,
				'plan_id'        => $plan ? (int) $plan->id : null,
				'amount'         => $amount,
				'payment_method' => Salanaz_Schema::METHOD_MANUAL,
				'payment_type'   => $plan ? Salanaz_Schema::TYPE_INSTALLMENT : Salanaz_Schema::TYPE_OUTRIGHT,
				'status'         => Salanaz_Schema::TXN_AWAITING,
				'proof_path'     => $stored['path'],
				'proof_mime'     => $stored['mime'],
				'payer_note'     => $note,
			)
		);

		if ( is_wp_error( $txn_id ) ) {
			$this->redirect_with( $txn_id->get_error_message(), 'error', $back );
		}

		Salanaz_Activity::log(
			Salanaz_Activity::PAYMENT_SUBMITTED,
			array(
				'subject_id'  => $client_id,
				'object_type' => 'transaction',
				'object_id'   => $txn_id,
				'details'     => array( 'amount' => $amount ),
			)
		);

		/**
		 * Fires after a client submits a manual payment proof.
		 *
		 * @param int $txn_id    Transaction ID.
		 * @param int $client_id Client user ID.
		 */
		do_action( 'salanaz_payment_submitted', $txn_id, $client_id );

		$this->redirect_with(
			__( 'Payment proof received. Our team will verify it and you will be emailed either way.', 'salanaz' ),
			'success',
			$dashboard
		);
	}
}
