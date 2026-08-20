<?php
/**
 * Payment verification.
 *
 * Verifying a payment is the point where money becomes an entitlement, so the
 * schedule is always recalculated from the sum of verified transactions rather
 * than incremented in place. That makes the operation idempotent: re-running it
 * after a correction produces the same result instead of double-crediting.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Approves and rejects manual payment submissions.
 */
class Salanaz_Verification {

	/**
	 * Approve a payment.
	 *
	 * @param int $txn_id Transaction ID.
	 * @return true|WP_Error
	 */
	public static function verify( int $txn_id ) {
		if ( ! current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to verify payments.', 'salanaz' ) );
		}

		$txn = Salanaz_Transactions::get( $txn_id );

		if ( ! $txn ) {
			return new WP_Error( 'salanaz_no_txn', __( 'That payment could not be found.', 'salanaz' ) );
		}

		if ( Salanaz_Schema::TXN_VERIFIED === $txn->status ) {
			return new WP_Error( 'salanaz_already', __( 'That payment has already been verified.', 'salanaz' ) );
		}

		Salanaz_Transactions::update(
			$txn_id,
			array(
				'status'           => Salanaz_Schema::TXN_VERIFIED,
				'verified_by'      => get_current_user_id(),
				'verified_at'      => current_time( 'mysql', true ),
				'rejection_reason' => null,
			)
		);

		self::reconcile( (int) $txn->client_id, (int) $txn->plot_id );

		Salanaz_Activity::log(
			Salanaz_Activity::PAYMENT_VERIFIED,
			array(
				'subject_id'  => (int) $txn->client_id,
				'object_type' => 'transaction',
				'object_id'   => $txn_id,
				'details'     => array( 'amount' => (float) $txn->amount ),
			)
		);

		$client = get_userdata( (int) $txn->client_id );

		if ( $client instanceof WP_User ) {
			Salanaz_Notifications::payment_verified( $client, Salanaz_Transactions::get( $txn_id ) );
		}

		/**
		 * Fires after a payment is verified.
		 *
		 * @param int $txn_id Transaction ID.
		 */
		do_action( 'salanaz_payment_verified', $txn_id );

		return true;
	}

	/**
	 * Reject a payment.
	 *
	 * @param int    $txn_id Transaction ID.
	 * @param string $reason Reason shown to the client.
	 * @return true|WP_Error
	 */
	public static function reject( int $txn_id, string $reason = '' ) {
		if ( ! current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to verify payments.', 'salanaz' ) );
		}

		$txn = Salanaz_Transactions::get( $txn_id );

		if ( ! $txn ) {
			return new WP_Error( 'salanaz_no_txn', __( 'That payment could not be found.', 'salanaz' ) );
		}

		$was_verified = ( Salanaz_Schema::TXN_VERIFIED === $txn->status );

		Salanaz_Transactions::update(
			$txn_id,
			array(
				'status'           => Salanaz_Schema::TXN_REJECTED,
				'verified_by'      => get_current_user_id(),
				'verified_at'      => current_time( 'mysql', true ),
				'rejection_reason' => $reason,
			)
		);

		// Reversing a previously verified payment must give back the credit.
		if ( $was_verified ) {
			self::reconcile( (int) $txn->client_id, (int) $txn->plot_id );
		}

		Salanaz_Activity::log(
			Salanaz_Activity::PAYMENT_REJECTED,
			array(
				'subject_id'  => (int) $txn->client_id,
				'object_type' => 'transaction',
				'object_id'   => $txn_id,
				'details'     => array( 'reason' => $reason ),
			)
		);

		$client = get_userdata( (int) $txn->client_id );

		if ( $client instanceof WP_User ) {
			Salanaz_Notifications::payment_rejected( $client, Salanaz_Transactions::get( $txn_id ), $reason );
		}

		/**
		 * Fires after a payment is rejected.
		 *
		 * @param int    $txn_id Transaction ID.
		 * @param string $reason The reason given.
		 */
		do_action( 'salanaz_payment_rejected', $txn_id, $reason );

		return true;
	}

	/**
	 * Recalculate a client's position on one plot from verified payments.
	 *
	 * Rebuilds the whole instalment schedule state and the plot status, so it is
	 * safe to call repeatedly and after a reversal.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 */
	public static function reconcile( int $client_id, int $plot_id ): void {
		global $wpdb;

		$paid = Salanaz_Transactions::paid_for_plot( $client_id, $plot_id );
		$plan = Salanaz_Plans::plan_for_plot( $client_id, $plot_id );
		$now  = current_time( 'mysql', true );

		$total = $plan ? (float) $plan->total_amount : Salanaz_Inventory::plot_price( $plot_id );

		if ( $plan ) {
			// Anything above the deposit flows into the schedule, oldest first.
			$remaining = max( 0.0, $paid - (float) $plan->down_payment );

			foreach ( Salanaz_Plans::schedule( (int) $plan->id ) as $row ) {
				$due     = (float) $row->amount;
				$applied = min( $remaining, $due );
				$remaining -= $applied;

				if ( $applied >= $due - 0.005 ) {
					$status  = Salanaz_Schema::INST_PAID;
					$paid_at = $row->paid_at ?: $now;
				} elseif ( $applied > 0 ) {
					$status  = Salanaz_Schema::INST_PARTIAL;
					$paid_at = null;
				} else {
					$status  = ( strtotime( $row->due_date ) < time() )
						? Salanaz_Schema::INST_OVERDUE
						: Salanaz_Schema::INST_PENDING;
					$paid_at = null;
				}

				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					salanaz_table( 'installments' ),
					array(
						'amount_paid' => round( $applied, 2 ),
						'status'      => $status,
						'paid_at'     => $paid_at,
						'updated_at'  => $now,
					),
					array( 'id' => (int) $row->id ),
					array( '%f', '%s', '%s', '%s' ),
					array( '%d' )
				);
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				salanaz_table( 'installment_plans' ),
				array(
					'status'     => ( $paid >= $total - 0.005 ) ? Salanaz_Schema::PLAN_COMPLETED : Salanaz_Schema::PLAN_ACTIVE,
					'updated_at' => $now,
				),
				array( 'id' => (int) $plan->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		// Plot status follows the money.
		$owner    = (int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_RESERVED_BY, true );
		$was_sold = Salanaz_Post_Types::STATUS_SOLD === Salanaz_Inventory::plot_status( $plot_id );

		if ( $owner === $client_id ) {
			if ( $total > 0 && $paid >= $total - 0.005 ) {
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_STATUS, Salanaz_Post_Types::STATUS_SOLD );
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_OWNER, $client_id );

				// Only on the transition into sold, so a later reconcile does
				// not reissue the letter.
				if ( ! $was_sold ) {
					/**
					 * Fires when a plot becomes fully paid.
					 *
					 * @param int $client_id Client user ID.
					 * @param int $plot_id   Plot post ID.
					 */
					do_action( 'salanaz_plot_allocated', $client_id, $plot_id );
				}
			} else {
				// Falling back below the full price reopens the reservation, so
				// the ownership marker must go with it.
				update_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_STATUS, Salanaz_Post_Types::STATUS_RESERVED );
				delete_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_OWNER );
			}
		}

		Salanaz_Inventory::flush_estate_stats(
			(int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_ESTATE, true )
		);
	}
}
