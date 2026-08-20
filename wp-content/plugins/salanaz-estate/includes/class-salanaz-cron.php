<?php
/**
 * Scheduled installment reminders.
 *
 * Runs once a day and does three things: warns clients whose next instalment
 * falls due shortly, flags instalments that have slipped past their due date,
 * and escalates those to the sales officer and management.
 *
 * Every message sets a timestamp on the instalment row before the next run, so
 * a reminder is never sent twice for the same milestone — which matters because
 * WP-Cron can fire more than once under load.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and runs the daily reminder sweep.
 */
class Salanaz_Cron {

	public const DAILY_HOOK = 'salanaz_daily_reminders';

	/** Option holding a summary of the last run. */
	private const LAST_RUN = 'salanaz_last_reminder_run';

	/** Most instalments handled in one sweep, so a run cannot time out. */
	private const BATCH = 100;

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_action( self::DAILY_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Make sure the daily event exists.
	 *
	 * Scheduling on activation alone is not enough — a site restored from a
	 * backup, or one where cron was cleared, would silently stop reminding.
	 */
	public function ensure_scheduled(): void {
		if ( wp_next_scheduled( self::DAILY_HOOK ) ) {
			return;
		}

		// 07:00 local time: early enough to land before the working day.
		$next = strtotime( 'tomorrow 07:00', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

		wp_schedule_event( $next, 'daily', self::DAILY_HOOK );
	}

	/**
	 * Remove the scheduled event.
	 */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::DAILY_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::DAILY_HOOK );
		}
	}

	/**
	 * When the next sweep is due.
	 *
	 * @return int Unix timestamp, or 0 when nothing is scheduled.
	 */
	public static function next_run(): int {
		return (int) wp_next_scheduled( self::DAILY_HOOK );
	}

	/**
	 * Summary of the last sweep.
	 *
	 * @return array{ran_at:string, due_7:int, due_1:int, overdue:int}
	 */
	public static function last_run(): array {
		$stored = get_option( self::LAST_RUN, array() );

		return wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			array(
				'ran_at'  => '',
				'due_7'   => 0,
				'due_1'   => 0,
				'overdue' => 0,
			)
		);
	}

	/* =====================================================================
	 * The sweep
	 * ================================================================== */

	/**
	 * Run the daily reminder sweep.
	 *
	 * @return array{due_7:int, due_1:int, overdue:int} Counts of messages sent.
	 */
	public function run(): array {
		$today = current_time( 'Y-m-d' );

		$counts = array(
			'due_7'   => $this->send_advance_notices( $today, 7 ),
			'due_1'   => $this->send_advance_notices( $today, 1 ),
			'overdue' => $this->send_overdue_notices( $today ),
		);

		update_option(
			self::LAST_RUN,
			array_merge( $counts, array( 'ran_at' => current_time( 'mysql' ) ) ),
			false
		);

		/**
		 * Fires after the daily reminder sweep.
		 *
		 * @param array $counts Messages sent, by type.
		 */
		do_action( 'salanaz_reminders_sent', $counts );

		return $counts;
	}

	/**
	 * Warn clients whose instalment falls due in N days.
	 *
	 * @param string $today Today, Y-m-d.
	 * @param int    $days  Days ahead: 7 or 1.
	 * @return int Messages sent.
	 */
	private function send_advance_notices( string $today, int $days ): int {
		global $wpdb;

		$column = 7 === $days ? 'reminder_7d_sent_at' : 'reminder_1d_sent_at';
		$target = gmdate( 'Y-m-d', strtotime( $today . ' +' . $days . ' days' ) );

		$installments = salanaz_table( 'installments' );
		$plans        = salanaz_table( 'installment_plans' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, p.client_id, p.plot_id
				FROM {$installments} i
				INNER JOIN {$plans} p ON p.id = i.plan_id
				WHERE i.due_date = %s
					AND i.status <> %s
					AND i.{$column} IS NULL
					AND p.status = %s
				ORDER BY i.due_date ASC
				LIMIT %d",
				$target,
				Salanaz_Schema::INST_PAID,
				Salanaz_Schema::PLAN_ACTIVE,
				self::BATCH
			)
		);

		$sent = 0;

		foreach ( $rows as $row ) {
			$client = get_userdata( (int) $row->client_id );

			// Stamp first. A send that fails is better than one that repeats
			// every time cron fires.
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$installments,
				array( $column => current_time( 'mysql', true ) ),
				array( 'id' => (int) $row->id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( ! $client instanceof WP_User ) {
				continue;
			}

			Salanaz_Notifications::installment_due_soon( $client, $row, $days );
			++$sent;
		}

		return $sent;
	}

	/**
	 * Flag overdue instalments and escalate them.
	 *
	 * A first notice goes out as soon as an instalment slips. After that it is
	 * repeated weekly, because a single email rarely collects a debt.
	 *
	 * @param string $today Today, Y-m-d.
	 * @return int Messages sent.
	 */
	private function send_overdue_notices( string $today ): int {
		global $wpdb;

		$installments = salanaz_table( 'installments' );
		$plans        = salanaz_table( 'installment_plans' );

		/**
		 * Days between repeat overdue notices.
		 *
		 * @param int $days Default 7.
		 */
		$repeat_after = (int) apply_filters( 'salanaz_overdue_repeat_days', 7 );
		$cutoff       = gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, $repeat_after ) . ' days' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT i.*, p.client_id, p.plot_id
				FROM {$installments} i
				INNER JOIN {$plans} p ON p.id = i.plan_id
				WHERE i.due_date < %s
					AND i.status <> %s
					AND p.status = %s
					AND ( i.overdue_notice_sent_at IS NULL OR i.overdue_notice_sent_at < %s )
				ORDER BY i.due_date ASC
				LIMIT %d",
				$today,
				Salanaz_Schema::INST_PAID,
				Salanaz_Schema::PLAN_ACTIVE,
				$cutoff,
				self::BATCH
			)
		);

		$sent = 0;

		foreach ( $rows as $row ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$installments,
				array(
					'status'                 => Salanaz_Schema::INST_OVERDUE,
					'overdue_notice_sent_at' => current_time( 'mysql', true ),
					'updated_at'             => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$client = get_userdata( (int) $row->client_id );

			if ( ! $client instanceof WP_User ) {
				continue;
			}

			Salanaz_Notifications::installment_overdue( $client, $row );
			++$sent;
		}

		return $sent;
	}
}
