<?php
/**
 * Transaction repository.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the transactions table.
 */
class Salanaz_Transactions {

	/**
	 * Create a transaction row.
	 *
	 * @param array<string, mixed> $data Row data.
	 * @return int|WP_Error Transaction ID.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$row = wp_parse_args(
			$data,
			array(
				'reference'      => salanaz_generate_reference( 'TXN' ),
				'client_id'      => 0,
				'plot_id'        => 0,
				'plan_id'        => null,
				'installment_id' => null,
				'amount'         => 0,
				'currency'       => 'NGN',
				'payment_method' => Salanaz_Schema::METHOD_MANUAL,
				'payment_type'   => Salanaz_Schema::TYPE_OUTRIGHT,
				'status'         => Salanaz_Schema::TXN_PENDING,
				'proof_path'     => null,
				'proof_mime'     => null,
				'payer_note'     => null,
				'created_at'     => $now,
				'updated_at'     => $now,
			)
		);

		$inserted = $wpdb->insert( salanaz_table( 'transactions' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $inserted ) {
			return new WP_Error( 'salanaz_txn_failed', __( 'The payment could not be recorded. Please try again.', 'salanaz' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch one transaction.
	 *
	 * @param int $id Transaction ID.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

		return $row ?: null;
	}

	/**
	 * Update a transaction.
	 *
	 * @param int                  $id   Transaction ID.
	 * @param array<string, mixed> $data Columns to set.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql', true );

		return false !== $wpdb->update( salanaz_table( 'transactions' ), $data, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Transactions belonging to a client.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $limit     Maximum rows.
	 * @return array<int, object>
	 */
	public static function for_client( int $client_id, int $limit = 100 ): array {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE client_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$client_id,
				$limit
			)
		);
	}

	/**
	 * Transactions awaiting manual verification.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, object>
	 */
	public static function awaiting_verification( int $limit = 100 ): array {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at ASC LIMIT %d",
				Salanaz_Schema::TXN_AWAITING,
				$limit
			)
		);
	}

	/**
	 * The most recent transactions across all clients.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, object>
	 */
	public static function recent( int $limit = 40 ): array {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d",
				max( 1, min( 200, $limit ) )
			)
		);
	}

	/**
	 * Total verified payments a client has made against a plot.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 * @return float
	 */
	public static function paid_for_plot( int $client_id, int $plot_id ): float {
		global $wpdb;

		$table = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( amount ), 0 ) FROM {$table}
				WHERE client_id = %d AND plot_id = %d AND status = %s",
				$client_id,
				$plot_id,
				Salanaz_Schema::TXN_VERIFIED
			)
		);
	}

	/**
	 * Human label for a transaction status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			Salanaz_Schema::TXN_PENDING  => __( 'Pending', 'salanaz' ),
			Salanaz_Schema::TXN_AWAITING => __( 'Pending verification', 'salanaz' ),
			Salanaz_Schema::TXN_VERIFIED => __( 'Verified', 'salanaz' ),
			Salanaz_Schema::TXN_REJECTED => __( 'Rejected', 'salanaz' ),
			Salanaz_Schema::TXN_FAILED   => __( 'Failed', 'salanaz' ),
		);

		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}

	/**
	 * CSS modifier for a status pill.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_class( string $status ): string {
		$map = array(
			Salanaz_Schema::TXN_PENDING  => 'pending',
			Salanaz_Schema::TXN_AWAITING => 'pending',
			Salanaz_Schema::TXN_VERIFIED => 'verified',
			Salanaz_Schema::TXN_REJECTED => 'rejected',
			Salanaz_Schema::TXN_FAILED   => 'rejected',
		);

		return $map[ $status ] ?? 'pending';
	}
}
