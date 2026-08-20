<?php
/**
 * Paystack gateway.
 *
 * Paystack is the primary payment route for Nigerian buyers: card, bank
 * transfer and USSD in one checkout. Money is only ever credited after the
 * server has confirmed it — either from a signed webhook or from a direct
 * verify call — never from anything the browser hands back.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings, checkout initialisation, verification and the webhook.
 */
class Salanaz_Paystack {

	/** Option holding the gateway settings. */
	public const OPTION = 'salanaz_paystack';

	/** REST namespace and route for the webhook. */
	public const REST_NAMESPACE = 'salanaz/v1';
	public const REST_ROUTE     = '/paystack-webhook';

	private const API_BASE = 'https://api.paystack.co';

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'template_redirect', array( $this, 'handle_callback' ) );
	}

	/* =====================================================================
	 * Settings
	 * ================================================================== */

	/**
	 * Stored settings, with defaults.
	 *
	 * @return array{enabled:bool, test_mode:bool, public_key:string, secret_key:string}
	 */
	public static function settings(): array {
		$stored = get_option( self::OPTION, array() );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'enabled'    => false,
				'test_mode'  => true,
				'public_key' => '',
				'secret_key' => '',
			)
		);
	}

	/**
	 * Save settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> The stored settings.
	 */
	public static function save_settings( array $input ): array {
		$clean = array(
			'enabled'    => ! empty( $input['enabled'] ),
			'test_mode'  => ! empty( $input['test_mode'] ),
			'public_key' => sanitize_text_field( (string) ( $input['public_key'] ?? '' ) ),
			'secret_key' => sanitize_text_field( (string) ( $input['secret_key'] ?? '' ) ),
		);

		update_option( self::OPTION, $clean, false );

		return $clean;
	}

	/**
	 * The secret key.
	 *
	 * A constant wins over the stored option so production keys can live in
	 * wp-config.php rather than the database.
	 *
	 * @return string
	 */
	public static function secret_key(): string {
		if ( defined( 'SALANAZ_PAYSTACK_SECRET' ) && SALANAZ_PAYSTACK_SECRET ) {
			return (string) SALANAZ_PAYSTACK_SECRET;
		}

		return (string) self::settings()['secret_key'];
	}

	/**
	 * The public key.
	 *
	 * @return string
	 */
	public static function public_key(): string {
		if ( defined( 'SALANAZ_PAYSTACK_PUBLIC' ) && SALANAZ_PAYSTACK_PUBLIC ) {
			return (string) SALANAZ_PAYSTACK_PUBLIC;
		}

		return (string) self::settings()['public_key'];
	}

	/**
	 * Whether card payment can be offered.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		$settings = self::settings();

		return ! empty( $settings['enabled'] ) && '' !== self::secret_key() && '' !== self::public_key();
	}

	/**
	 * The webhook URL to paste into the Paystack dashboard.
	 *
	 * @return string
	 */
	public static function webhook_url(): string {
		return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
	}

	/* =====================================================================
	 * Checkout
	 * ================================================================== */

	/**
	 * Create a pending transaction and get a Paystack checkout URL.
	 *
	 * @param int   $client_id Client user ID.
	 * @param int   $plot_id   Plot post ID.
	 * @param float $amount    Amount in Naira.
	 * @return string|WP_Error The authorization URL.
	 */
	public static function start_checkout( int $client_id, int $plot_id, float $amount ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'salanaz_paystack_off', __( 'Card payment is not available at the moment.', 'salanaz' ) );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'salanaz_bad_amount', __( 'Please enter the amount you want to pay.', 'salanaz' ) );
		}

		$client = get_userdata( $client_id );

		if ( ! $client instanceof WP_User ) {
			return new WP_Error( 'salanaz_no_client', __( 'Your account could not be found.', 'salanaz' ) );
		}

		$plan      = Salanaz_Plans::plan_for_plot( $client_id, $plot_id );
		$reference = salanaz_generate_reference( 'PSK' );

		$txn_id = Salanaz_Transactions::create(
			array(
				'reference'      => $reference,
				'client_id'      => $client_id,
				'plot_id'        => $plot_id,
				'plan_id'        => $plan ? (int) $plan->id : null,
				'amount'         => $amount,
				'payment_method' => Salanaz_Schema::METHOD_PAYSTACK,
				'payment_type'   => $plan ? Salanaz_Schema::TYPE_INSTALLMENT : Salanaz_Schema::TYPE_OUTRIGHT,
				'status'         => Salanaz_Schema::TXN_PENDING,
			)
		);

		if ( is_wp_error( $txn_id ) ) {
			return $txn_id;
		}

		$response = wp_remote_post(
			self::API_BASE . '/transaction/initialize',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . self::secret_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'email'        => $client->user_email,
						'amount'       => salanaz_to_kobo( $amount ),
						'currency'     => 'NGN',
						'reference'    => $reference,
						'callback_url' => add_query_arg( 'salanaz_paystack_return', '1', Salanaz_Auth::dashboard_url() ),
						'metadata'     => array(
							'client_id' => $client_id,
							'plot_id'   => $plot_id,
							'txn_id'    => $txn_id,
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Salanaz_Transactions::update( $txn_id, array( 'status' => Salanaz_Schema::TXN_FAILED ) );

			return new WP_Error(
				'salanaz_paystack_unreachable',
				__( 'We could not reach the payment gateway. Please try again, or pay by transfer.', 'salanaz' )
			);
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['status'] ) || empty( $body['data']['authorization_url'] ) ) {
			Salanaz_Transactions::update( $txn_id, array( 'status' => Salanaz_Schema::TXN_FAILED ) );

			return new WP_Error(
				'salanaz_paystack_rejected',
				! empty( $body['message'] ) ? sanitize_text_field( (string) $body['message'] )
					: __( 'The payment gateway declined to start this payment.', 'salanaz' )
			);
		}

		Salanaz_Transactions::update(
			$txn_id,
			array( 'gateway_reference' => sanitize_text_field( (string) ( $body['data']['reference'] ?? $reference ) ) )
		);

		return (string) $body['data']['authorization_url'];
	}

	/* =====================================================================
	 * Verification
	 * ================================================================== */

	/**
	 * Ask Paystack about a reference and credit it if it succeeded.
	 *
	 * @param string $reference The transaction reference.
	 * @return true|WP_Error
	 */
	public static function verify_reference( string $reference ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'salanaz_paystack_off', __( 'Card payment is not available.', 'salanaz' ) );
		}

		$response = wp_remote_get(
			self::API_BASE . '/transaction/verify/' . rawurlencode( $reference ),
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . self::secret_key() ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'salanaz_paystack_unreachable', __( 'We could not confirm that payment yet.', 'salanaz' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['status'] ) || empty( $body['data'] ) ) {
			return new WP_Error( 'salanaz_paystack_unknown', __( 'That payment could not be confirmed.', 'salanaz' ) );
		}

		return self::apply_gateway_result( $body['data'] );
	}

	/**
	 * Credit a transaction from a confirmed gateway result.
	 *
	 * Shared by the webhook and the return-URL verify, and safe to run twice:
	 * an already-verified transaction returns early rather than double-crediting.
	 *
	 * @param array<string, mixed> $data The Paystack `data` object.
	 * @return true|WP_Error
	 */
	public static function apply_gateway_result( array $data ) {
		$reference = sanitize_text_field( (string) ( $data['reference'] ?? '' ) );

		if ( '' === $reference ) {
			return new WP_Error( 'salanaz_no_reference', __( 'That payment had no reference.', 'salanaz' ) );
		}

		$txn = self::find_by_reference( $reference );

		if ( ! $txn ) {
			return new WP_Error( 'salanaz_unknown_reference', __( 'That payment reference is not one of ours.', 'salanaz' ) );
		}

		// Already credited — a webhook and a return-URL verify routinely race.
		if ( Salanaz_Schema::TXN_VERIFIED === $txn->status ) {
			return true;
		}

		$gateway_status = strtolower( (string) ( $data['status'] ?? '' ) );

		if ( 'success' !== $gateway_status ) {
			Salanaz_Transactions::update(
				(int) $txn->id,
				array(
					'status'          => Salanaz_Schema::TXN_FAILED,
					'gateway_payload' => wp_json_encode( $data ),
				)
			);

			return new WP_Error( 'salanaz_payment_failed', __( 'That payment did not go through.', 'salanaz' ) );
		}

		// The amount Paystack reports is authoritative. If it does not match
		// what we recorded, credit what was actually paid rather than what the
		// browser claimed, and never more.
		$paid_naira = isset( $data['amount'] ) ? ( (float) $data['amount'] ) / 100 : 0.0;

		if ( $paid_naira <= 0 ) {
			return new WP_Error( 'salanaz_zero_amount', __( 'That payment had no amount.', 'salanaz' ) );
		}

		$currency = strtoupper( (string) ( $data['currency'] ?? 'NGN' ) );

		if ( 'NGN' !== $currency ) {
			return new WP_Error( 'salanaz_bad_currency', __( 'That payment was not in Naira.', 'salanaz' ) );
		}

		Salanaz_Transactions::update(
			(int) $txn->id,
			array(
				'amount'            => $paid_naira,
				'status'            => Salanaz_Schema::TXN_VERIFIED,
				'gateway_reference' => $reference,
				'gateway_payload'   => wp_json_encode( $data ),
				'verified_at'       => current_time( 'mysql', true ),
			)
		);

		Salanaz_Verification::reconcile( (int) $txn->client_id, (int) $txn->plot_id );

		Salanaz_Activity::log(
			Salanaz_Activity::PAYMENT_VERIFIED,
			array(
				'subject_id'  => (int) $txn->client_id,
				'object_type' => 'transaction',
				'object_id'   => (int) $txn->id,
				'actor_id'    => 0,
				'details'     => array(
					'source' => 'paystack',
					'amount' => $paid_naira,
				),
			)
		);

		$client = get_userdata( (int) $txn->client_id );

		if ( $client instanceof WP_User ) {
			Salanaz_Notifications::payment_verified( $client, Salanaz_Transactions::get( (int) $txn->id ) );
		}

		/**
		 * Fires after a gateway payment is credited.
		 *
		 * @param int $txn_id Transaction ID.
		 */
		do_action( 'salanaz_paystack_verified', (int) $txn->id );

		return true;
	}

	/**
	 * Find a transaction by its own reference or the gateway's.
	 *
	 * @param string $reference Reference.
	 * @return object|null
	 */
	public static function find_by_reference( string $reference ): ?object {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE reference = %s OR gateway_reference = %s ORDER BY id DESC LIMIT 1",
				$reference,
				$reference
			)
		);

		return $row ?: null;
	}

	/* =====================================================================
	 * Webhook
	 * ================================================================== */

	/**
	 * Register the webhook route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				// Authentication is the signature, checked inside the handler.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle a Paystack webhook.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$secret = self::secret_key();

		if ( '' === $secret ) {
			return new WP_REST_Response( array( 'message' => 'not configured' ), 503 );
		}

		$body      = $request->get_body();
		$signature = (string) $request->get_header( 'x_paystack_signature' );

		if ( ! self::signature_is_valid( $body, $signature, $secret ) ) {
			// Deliberately terse: a caller that cannot sign gets no detail.
			return new WP_REST_Response( array( 'message' => 'invalid signature' ), 401 );
		}

		$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) || empty( $payload['event'] ) ) {
			return new WP_REST_Response( array( 'message' => 'malformed' ), 400 );
		}

		if ( 'charge.success' !== $payload['event'] ) {
			// Acknowledge everything else so Paystack stops retrying.
			return new WP_REST_Response( array( 'message' => 'ignored' ), 200 );
		}

		$result = self::apply_gateway_result( (array) ( $payload['data'] ?? array() ) );

		if ( is_wp_error( $result ) ) {
			// 200 on a reference we do not recognise, so Paystack does not retry
			// forever for something that will never succeed.
			$code = 'salanaz_unknown_reference' === $result->get_error_code() ? 200 : 422;

			return new WP_REST_Response( array( 'message' => $result->get_error_code() ), $code );
		}

		return new WP_REST_Response( array( 'message' => 'ok' ), 200 );
	}

	/**
	 * Constant-time check of the Paystack signature.
	 *
	 * @param string $body      Raw request body.
	 * @param string $signature Value of the x-paystack-signature header.
	 * @param string $secret    Secret key.
	 * @return bool
	 */
	public static function signature_is_valid( string $body, string $signature, string $secret ): bool {
		if ( '' === $signature || '' === $body ) {
			return false;
		}

		$expected = hash_hmac( 'sha512', $body, $secret );

		return hash_equals( $expected, $signature );
	}

	/* =====================================================================
	 * Return URL
	 * ================================================================== */

	/**
	 * Handle the browser coming back from Paystack.
	 *
	 * The reference in the URL is only a hint — nothing is credited until the
	 * server has asked Paystack directly.
	 */
	public function handle_callback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- gateway return, validated by API call.
		if ( ! isset( $_GET['salanaz_paystack_return'] ) ) {
			return;
		}

		$reference = isset( $_GET['reference'] ) ? sanitize_text_field( wp_unslash( $_GET['reference'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dashboard = Salanaz_Auth::dashboard_url();

		if ( '' === $reference ) {
			self::flash( __( 'That payment could not be identified. If you were charged, contact us with your receipt.', 'salanaz' ), 'warning' );
			wp_safe_redirect( $dashboard );
			exit;
		}

		$result = self::verify_reference( $reference );

		if ( is_wp_error( $result ) ) {
			self::flash( $result->get_error_message(), 'warning' );
		} else {
			self::flash( __( 'Payment received and confirmed. Thank you.', 'salanaz' ), 'success' );
		}

		wp_safe_redirect( $dashboard );
		exit;
	}

	/**
	 * Queue a dashboard message for the current user.
	 *
	 * @param string $text Message.
	 * @param string $type info|success|warning|error.
	 */
	private static function flash( string $text, string $type ): void {
		set_transient(
			'salanaz_flash_' . get_current_user_id(),
			array( array( 'type' => $type, 'text' => $text ) ),
			MINUTE_IN_SECONDS
		);
	}
}
