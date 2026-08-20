<?php
/**
 * Payment plan definitions and schedule generation.
 *
 * Plan terms are defined here rather than stored per-plot so that changing a
 * commercial term does not require editing every plot. A generated schedule is
 * a snapshot: once the rows exist in the installments table, later changes to
 * these definitions do not alter an existing client's plan.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plan catalogue and instalment maths.
 */
class Salanaz_Plans {

	public const OUTRIGHT = 'outright';
	public const SIX      = 'months_6';
	public const TWELVE   = 'months_12';
	public const TWENTY4  = 'months_24';

	/**
	 * The plan catalogue.
	 *
	 * `deposit` is the fraction payable up front. `interest` is a flat uplift
	 * applied to the whole price, expressed as a fraction.
	 *
	 * @return array<string, array{label:string, months:int, deposit:float, interest:float, note:string}>
	 */
	public static function all(): array {
		$plans = array(
			self::OUTRIGHT => array(
				'label'    => __( 'Outright payment', 'salanaz' ),
				'months'   => 0,
				'deposit'  => 1.0,
				'interest' => 0.0,
				'note'     => __( 'Pay in full. Best price, and allocation follows immediately.', 'salanaz' ),
			),
			self::SIX      => array(
				'label'    => __( '6 months', 'salanaz' ),
				'months'   => 6,
				'deposit'  => 0.30,
				'interest' => 0.0,
				'note'     => __( '30% deposit, then 6 monthly payments. No interest.', 'salanaz' ),
			),
			self::TWELVE   => array(
				'label'    => __( '12 months', 'salanaz' ),
				'months'   => 12,
				'deposit'  => 0.30,
				'interest' => 0.0,
				'note'     => __( '30% deposit, then 12 monthly payments. No interest.', 'salanaz' ),
			),
			self::TWENTY4  => array(
				'label'    => __( '24 months', 'salanaz' ),
				'months'   => 24,
				'deposit'  => 0.20,
				'interest' => 0.10,
				'note'     => __( '20% deposit, then 24 monthly payments. A 10% carrying charge applies.', 'salanaz' ),
			),
		);

		/**
		 * Filter the payment plan catalogue.
		 *
		 * @param array $plans The plan definitions.
		 */
		return (array) apply_filters( 'salanaz_payment_plans', $plans );
	}

	/**
	 * A single plan definition.
	 *
	 * @param string $key Plan key.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $key ): ?array {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Whether a plan key is valid.
	 *
	 * @param string $key Plan key.
	 * @return bool
	 */
	public static function exists( string $key ): bool {
		return null !== self::get( $key );
	}

	/**
	 * Work out the money for a plan against a price.
	 *
	 * Rounding is handled by giving every instalment the same rounded amount
	 * and pushing the remainder onto the final one, so the schedule always sums
	 * to exactly the total.
	 *
	 * @param string $key   Plan key.
	 * @param float  $price Plot price in Naira.
	 * @return array{total:float, deposit:float, balance:float, months:int, per_month:float, final_month:float, interest:float}|null
	 */
	public static function quote( string $key, float $price ): ?array {
		$plan = self::get( $key );

		if ( ! $plan || $price <= 0 ) {
			return null;
		}

		$total   = round( $price * ( 1 + (float) $plan['interest'] ), 2 );
		$deposit = round( $total * (float) $plan['deposit'], 2 );
		$balance = round( $total - $deposit, 2 );
		$months  = (int) $plan['months'];

		if ( $months < 1 ) {
			return array(
				'total'       => $total,
				'deposit'     => $total,
				'balance'     => 0.0,
				'months'      => 0,
				'per_month'   => 0.0,
				'final_month' => 0.0,
				'interest'    => (float) $plan['interest'],
			);
		}

		$per_month = floor( ( $balance / $months ) * 100 ) / 100;
		$final     = round( $balance - ( $per_month * ( $months - 1 ) ), 2 );

		return array(
			'total'       => $total,
			'deposit'     => $deposit,
			'balance'     => $balance,
			'months'      => $months,
			'per_month'   => $per_month,
			'final_month' => $final,
			'interest'    => (float) $plan['interest'],
		);
	}

	/**
	 * Create an installment plan and its schedule rows.
	 *
	 * @param int    $client_id Client user ID.
	 * @param int    $plot_id   Plot post ID.
	 * @param string $key       Plan key.
	 * @param float  $price     Plot price.
	 * @return int|WP_Error Plan ID.
	 */
	public static function create_plan( int $client_id, int $plot_id, string $key, float $price ) {
		global $wpdb;

		$quote = self::quote( $key, $price );

		if ( ! $quote || $quote['months'] < 1 ) {
			return new WP_Error( 'salanaz_bad_plan', __( 'That payment plan is not valid for this plot.', 'salanaz' ) );
		}

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			salanaz_table( 'installment_plans' ),
			array(
				'reference'    => salanaz_generate_reference( 'PLN' ),
				'client_id'    => $client_id,
				'plot_id'      => $plot_id,
				'total_amount' => $quote['total'],
				'down_payment' => $quote['deposit'],
				'tenure_months' => $quote['months'],
				'frequency'    => 'monthly',
				'start_date'   => current_time( 'Y-m-d' ),
				'status'       => Salanaz_Schema::PLAN_ACTIVE,
				'created_by'   => get_current_user_id(),
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%d', '%d', '%f', '%f', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'salanaz_plan_failed', __( 'The payment plan could not be created.', 'salanaz' ) );
		}

		$plan_id = (int) $wpdb->insert_id;

		self::generate_schedule( $plan_id, $quote );

		return $plan_id;
	}

	/**
	 * Write the schedule rows for a plan.
	 *
	 * The first due date is one month after the deposit, so a client never has
	 * a deposit and an instalment falling on the same day.
	 *
	 * @param int                  $plan_id Plan ID.
	 * @param array<string, mixed> $quote   Result of quote().
	 */
	private static function generate_schedule( int $plan_id, array $quote ): void {
		global $wpdb;

		$now   = current_time( 'mysql', true );
		$start = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		for ( $i = 1; $i <= $quote['months']; $i++ ) {
			$amount = ( $i === $quote['months'] ) ? $quote['final_month'] : $quote['per_month'];

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				salanaz_table( 'installments' ),
				array(
					'plan_id'        => $plan_id,
					'installment_no' => $i,
					'due_date'       => gmdate( 'Y-m-d', strtotime( "+{$i} month", $start ) ),
					'amount'         => $amount,
					'amount_paid'    => 0,
					'status'         => Salanaz_Schema::INST_PENDING,
					'created_at'     => $now,
					'updated_at'     => $now,
				),
				array( '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Schedule rows for a plan.
	 *
	 * @param int $plan_id Plan ID.
	 * @return array<int, object>
	 */
	public static function schedule( int $plan_id ): array {
		global $wpdb;

		$table = salanaz_table( 'installments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE plan_id = %d ORDER BY installment_no ASC", $plan_id )
		);
	}

	/**
	 * The active plan for a client and plot.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 * @return object|null
	 */
	public static function active_plan( int $client_id, int $plot_id ): ?object {
		global $wpdb;

		$table = salanaz_table( 'installment_plans' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE client_id = %d AND plot_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
				$client_id,
				$plot_id,
				Salanaz_Schema::PLAN_ACTIVE
			)
		);

		return $row ?: null;
	}

	/**
	 * The latest plan for a client and plot, whatever its status.
	 *
	 * Reconciliation must use this rather than active_plan(): once a plan is
	 * marked completed it is no longer "active", and a later correction (an
	 * over-credit reversed, a bounced transfer rejected) still has to reopen and
	 * recalculate it.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 * @return object|null
	 */
	public static function plan_for_plot( int $client_id, int $plot_id ): ?object {
		global $wpdb;

		$table = salanaz_table( 'installment_plans' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE client_id = %d AND plot_id = %d AND status <> %s ORDER BY id DESC LIMIT 1",
				$client_id,
				$plot_id,
				Salanaz_Schema::PLAN_CANCELLED
			)
		);

		return $row ?: null;
	}

	/**
	 * All plans belonging to a client.
	 *
	 * @param int $client_id Client user ID.
	 * @return array<int, object>
	 */
	public static function plans_for_client( int $client_id ): array {
		global $wpdb;

		$table = salanaz_table( 'installment_plans' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE client_id = %d ORDER BY id DESC", $client_id )
		);
	}

	/**
	 * The next unpaid instalment on a plan.
	 *
	 * @param int $plan_id Plan ID.
	 * @return object|null
	 */
	public static function next_due( int $plan_id ): ?object {
		global $wpdb;

		$table = salanaz_table( 'installments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE plan_id = %d AND status <> %s ORDER BY due_date ASC LIMIT 1",
				$plan_id,
				Salanaz_Schema::INST_PAID
			)
		);

		return $row ?: null;
	}
}
