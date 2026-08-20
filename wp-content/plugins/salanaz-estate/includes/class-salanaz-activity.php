<?php
/**
 * Audit trail writer.
 *
 * The NDPR expects access to personal and financial data to be accountable, so
 * approvals, rejections, staff creation, assignments and payment verifications
 * are all recorded here with the acting user and their IP.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes and reads rows in the activity log table.
 */
class Salanaz_Activity {

	/* Action keys. */
	public const CLIENT_REGISTERED = 'client_registered';
	public const CLIENT_APPROVED   = 'client_approved';
	public const CLIENT_REJECTED   = 'client_rejected';
	public const CLIENT_SUSPENDED  = 'client_suspended';
	public const STAFF_CREATED     = 'staff_created';
	public const CLIENT_ASSIGNED   = 'client_assigned';
	public const CLIENT_UNASSIGNED = 'client_unassigned';
	public const NOTE_ADDED        = 'note_added';
	public const PAYMENT_SUBMITTED = 'payment_submitted';
	public const PAYMENT_VERIFIED  = 'payment_verified';
	public const PAYMENT_REJECTED  = 'payment_rejected';

	/**
	 * Record an event.
	 *
	 * @param string               $action      One of the action constants.
	 * @param array<string, mixed> $args        {
	 *     @type int    $subject_id  The user the action was performed on.
	 *     @type string $object_type e.g. 'user', 'transaction', 'plot'.
	 *     @type int    $object_id   Related object ID.
	 *     @type array  $details     Arbitrary context, stored as JSON.
	 *     @type int    $actor_id    Override the acting user.
	 * }
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public static function log( string $action, array $args = array() ): int {
		global $wpdb;

		$defaults = array(
			'subject_id'  => 0,
			'object_type' => '',
			'object_id'   => 0,
			'details'     => array(),
			'actor_id'    => get_current_user_id(),
		);

		$args = wp_parse_args( $args, $defaults );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			salanaz_table( 'activity_log' ),
			array(
				'actor_id'    => (int) $args['actor_id'] ?: null,
				'subject_id'  => (int) $args['subject_id'] ?: null,
				'object_type' => sanitize_key( (string) $args['object_type'] ),
				'object_id'   => (int) $args['object_id'] ?: null,
				'action'      => sanitize_key( $action ),
				'details'     => $args['details'] ? wp_json_encode( $args['details'] ) : null,
				'ip_address'  => self::client_ip(),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Recent log entries, newest first.
	 *
	 * @param array<string, mixed> $args Optional subject_id, object_type, action, limit.
	 * @return array<int, object>
	 */
	public static function recent( array $args = array() ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'subject_id'  => 0,
				'object_type' => '',
				'action'      => '',
				'limit'       => 25,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['subject_id'] ) {
			$where[]  = 'subject_id = %d';
			$params[] = (int) $args['subject_id'];
		}

		if ( $args['object_type'] ) {
			$where[]  = 'object_type = %s';
			$params[] = sanitize_key( $args['object_type'] );
		}

		if ( $args['action'] ) {
			$where[]  = 'action = %s';
			$params[] = sanitize_key( $args['action'] );
		}

		$params[] = max( 1, min( 200, (int) $args['limit'] ) );

		$table = salanaz_table( 'activity_log' );
		$sql   = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC, id DESC LIMIT %d';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * Best-effort client IP.
	 *
	 * REMOTE_ADDR only — forwarded headers are trivially spoofed and this value
	 * ends up in an audit record, so a wrong-but-honest value beats an
	 * attacker-controlled one. Sites behind a trusted proxy should filter this.
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filter the IP recorded in the audit log.
		 *
		 * @param string $ip The detected address.
		 */
		$ip = (string) apply_filters( 'salanaz_audit_ip_address', $ip );

		return rest_is_ip_address( $ip ) ? $ip : '';
	}
}
