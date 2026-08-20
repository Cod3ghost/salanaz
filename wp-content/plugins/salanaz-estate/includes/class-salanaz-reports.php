<?php
/**
 * Reporting queries.
 *
 * Aggregation is deliberately done in PHP over simple, portable SELECTs rather
 * than in clever SQL. The volumes here are hundreds to low thousands of rows,
 * and the plugin has to behave identically on MySQL and on the SQLite
 * translation layer used for local development.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Figures for the analytics screen.
 */
class Salanaz_Reports {

	private const CACHE_KEY = 'salanaz_report_portfolio';
	private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Drop cached figures. Hooked to payment and inventory changes.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	/* =====================================================================
	 * Portfolio
	 * ================================================================== */

	/**
	 * Walk every held plot once and total the money and counts.
	 *
	 * @return array{
	 *   collected:float, outstanding:float, contracted:float,
	 *   plots_sold:int, plots_reserved:int, plots_available:int,
	 *   clients_holding:int, by_client:array<int, array{collected:float, outstanding:float, plots:int}>
	 * }
	 */
	public static function portfolio(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$held = get_posts(
			array(
				'post_type'      => Salanaz_Post_Types::PLOT,
				'posts_per_page' => 2000,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => Salanaz_Post_Types::META_PLOT_RESERVED_BY,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$report = array(
			'collected'       => 0.0,
			'outstanding'     => 0.0,
			'contracted'      => 0.0,
			'plots_sold'      => 0,
			'plots_reserved'  => 0,
			'plots_available' => 0,
			'clients_holding' => 0,
			'by_client'       => array(),
		);

		$clients = array();

		foreach ( $held as $plot_id ) {
			$client_id = (int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_RESERVED_BY, true );

			if ( ! $client_id ) {
				continue;
			}

			$plan  = Salanaz_Plans::plan_for_plot( $client_id, $plot_id );
			$total = $plan ? (float) $plan->total_amount : Salanaz_Inventory::plot_price( $plot_id );
			$paid  = Salanaz_Transactions::paid_for_plot( $client_id, $plot_id );
			$owing = max( 0.0, $total - $paid );

			$report['collected']   += $paid;
			$report['outstanding'] += $owing;
			$report['contracted']  += $total;

			$status = Salanaz_Inventory::plot_status( $plot_id );

			if ( Salanaz_Post_Types::STATUS_SOLD === $status ) {
				++$report['plots_sold'];
			} elseif ( Salanaz_Post_Types::STATUS_RESERVED === $status ) {
				++$report['plots_reserved'];
			}

			if ( ! isset( $report['by_client'][ $client_id ] ) ) {
				$report['by_client'][ $client_id ] = array(
					'collected'   => 0.0,
					'outstanding' => 0.0,
					'plots'       => 0,
				);
			}

			$report['by_client'][ $client_id ]['collected']   += $paid;
			$report['by_client'][ $client_id ]['outstanding'] += $owing;
			$report['by_client'][ $client_id ]['plots']++;

			$clients[ $client_id ] = true;
		}

		$report['clients_holding'] = count( $clients );
		$report['plots_available'] = (int) Salanaz_Inventory::global_stats()['available'];

		set_transient( self::CACHE_KEY, $report, self::CACHE_TTL );

		return $report;
	}

	/* =====================================================================
	 * Revenue
	 * ================================================================== */

	/**
	 * Verified revenue per month.
	 *
	 * @param int $months How many months back, including this one.
	 * @return array<int, array{label:string, key:string, total:float, count:int}>
	 */
	public static function revenue_by_month( int $months = 12 ): array {
		global $wpdb;

		$months = max( 1, min( 36, $months ) );
		$since  = gmdate( 'Y-m-01 00:00:00', strtotime( '-' . ( $months - 1 ) . ' months' ) );
		$table  = salanaz_table( 'transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT amount, created_at FROM {$table} WHERE status = %s AND created_at >= %s",
				Salanaz_Schema::TXN_VERIFIED,
				$since
			)
		);

		// Seed every bucket so a quiet month still shows as a gap, not a hole.
		$buckets = array();

		for ( $i = $months - 1; $i >= 0; $i-- ) {
			$stamp = strtotime( '-' . $i . ' months' );
			$key   = gmdate( 'Y-m', $stamp );

			$buckets[ $key ] = array(
				'label' => gmdate( 'M y', $stamp ),
				'key'   => $key,
				'total' => 0.0,
				'count' => 0,
			);
		}

		foreach ( $rows as $row ) {
			$key = substr( (string) $row->created_at, 0, 7 );

			if ( isset( $buckets[ $key ] ) ) {
				$buckets[ $key ]['total'] += (float) $row->amount;
				$buckets[ $key ]['count']++;
			}
		}

		return array_values( $buckets );
	}

	/* =====================================================================
	 * Staff performance
	 * ================================================================== */

	/**
	 * Per sales officer: clients, plots and money.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function sales_by_staff(): array {
		$portfolio = self::portfolio();
		$rows      = array();

		foreach ( Salanaz_Users::all_staff() as $staff ) {
			$client_ids = Salanaz_Users::clients_for_staff( $staff->ID );

			$collected   = 0.0;
			$outstanding = 0.0;
			$plots       = 0;

			foreach ( $client_ids as $client_id ) {
				if ( ! isset( $portfolio['by_client'][ $client_id ] ) ) {
					continue;
				}

				$collected   += $portfolio['by_client'][ $client_id ]['collected'];
				$outstanding += $portfolio['by_client'][ $client_id ]['outstanding'];
				$plots       += $portfolio['by_client'][ $client_id ]['plots'];
			}

			$rows[] = array(
				'staff'       => $staff,
				'clients'     => count( $client_ids ),
				'plots'       => $plots,
				'collected'   => $collected,
				'outstanding' => $outstanding,
			);
		}

		// Best collector first — that is the number the business cares about.
		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return $b['collected'] <=> $a['collected'];
			}
		);

		return $rows;
	}

	/**
	 * Clients with no sales officer assigned.
	 *
	 * @return int
	 */
	public static function unassigned_clients(): int {
		$count = 0;

		foreach ( Salanaz_Users::all_clients( Salanaz_Profile::STATUS_APPROVED ) as $client ) {
			if ( ! Salanaz_Users::assigned_staff_id( $client->ID ) ) {
				++$count;
			}
		}

		return $count;
	}

	/* =====================================================================
	 * Inventory
	 * ================================================================== */

	/**
	 * Per estate: plot counts and the value still on the shelf.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function inventory_by_estate(): array {
		$rows = array();

		foreach ( Salanaz_Inventory::get_estates( array( 'posts_per_page' => 100 ) ) as $estate ) {
			$stats = Salanaz_Inventory::estate_stats( $estate->ID );

			$unsold_value = 0.0;

			foreach ( Salanaz_Inventory::get_plots_for_estate( $estate->ID, array( 'posts_per_page' => 500 ) ) as $plot ) {
				if ( Salanaz_Post_Types::STATUS_AVAILABLE === Salanaz_Inventory::plot_status( $plot->ID ) ) {
					$unsold_value += Salanaz_Inventory::plot_price( $plot->ID );
				}
			}

			$rows[] = array(
				'estate'       => $estate,
				'total'        => (int) $stats['total'],
				'available'    => (int) $stats['available'],
				'reserved'     => (int) $stats['reserved'],
				'sold'         => (int) $stats['sold'],
				'unsold_value' => $unsold_value,
			);
		}

		return $rows;
	}

	/* =====================================================================
	 * Collections
	 * ================================================================== */

	/**
	 * Overdue instalments across all active plans.
	 *
	 * @param int $limit Maximum rows returned in `items`.
	 * @return array{count:int, value:float, items:array<int, object>}
	 */
	public static function overdue( int $limit = 20 ): array {
		global $wpdb;

		$installments = salanaz_table( 'installments' );
		$plans        = salanaz_table( 'installment_plans' );
		$today        = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, p.client_id, p.plot_id
				FROM {$installments} i
				INNER JOIN {$plans} p ON p.id = i.plan_id
				WHERE i.due_date < %s AND i.status <> %s AND p.status = %s
				ORDER BY i.due_date ASC",
				$today,
				Salanaz_Schema::INST_PAID,
				Salanaz_Schema::PLAN_ACTIVE
			)
		);

		$value = 0.0;

		foreach ( $rows as $row ) {
			$value += (float) $row->amount - (float) $row->amount_paid;
		}

		return array(
			'count' => count( $rows ),
			'value' => $value,
			'items' => array_slice( $rows, 0, max( 1, $limit ) ),
		);
	}

	/**
	 * Payments waiting to be checked.
	 *
	 * @return array{count:int, value:float}
	 */
	public static function pending_verification(): array {
		$rows  = Salanaz_Transactions::awaiting_verification( 500 );
		$value = 0.0;

		foreach ( $rows as $row ) {
			$value += (float) $row->amount;
		}

		return array(
			'count' => count( $rows ),
			'value' => $value,
		);
	}
}
