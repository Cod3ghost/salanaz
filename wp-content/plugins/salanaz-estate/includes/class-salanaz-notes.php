<?php
/**
 * Client notes and follow-ups.
 *
 * Every read and write here re-checks that the acting user is allowed to see
 * the client, so a sales officer cannot reach another officer's file by
 * changing an id in a URL.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the client notes table.
 */
class Salanaz_Notes {

	/* Note types. */
	public const TYPE_NOTE     = 'note';
	public const TYPE_CALL     = 'call';
	public const TYPE_MEETING  = 'meeting';
	public const TYPE_WHATSAPP = 'whatsapp';
	public const TYPE_INSPECT  = 'inspection';

	/**
	 * Selectable note types.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array(
			self::TYPE_NOTE     => __( 'General note', 'salanaz' ),
			self::TYPE_CALL     => __( 'Phone call', 'salanaz' ),
			self::TYPE_WHATSAPP => __( 'WhatsApp', 'salanaz' ),
			self::TYPE_MEETING  => __( 'Meeting', 'salanaz' ),
			self::TYPE_INSPECT  => __( 'Site inspection', 'salanaz' ),
		);
	}

	/**
	 * Human label for a note type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function type_label( string $type ): string {
		$types = self::types();

		return $types[ $type ] ?? ucfirst( $type );
	}

	/**
	 * Record a note against a client.
	 *
	 * @param int    $client_id  Client user ID.
	 * @param string $note       Note body.
	 * @param string $type       One of the type constants.
	 * @param string $follow_up  Optional follow-up date, Y-m-d.
	 * @return int|WP_Error Note ID.
	 */
	public static function add( int $client_id, string $note, string $type = self::TYPE_NOTE, string $follow_up = '' ) {
		global $wpdb;

		if ( ! current_user_can( Salanaz_Roles::CAP_ADD_CLIENT_NOTES ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to add notes.', 'salanaz' ) );
		}

		if ( ! Salanaz_Users::staff_can_view_client( $client_id ) ) {
			return new WP_Error( 'salanaz_not_yours', __( 'That client is not assigned to you.', 'salanaz' ) );
		}

		$note = trim( $note );

		if ( '' === $note ) {
			return new WP_Error( 'salanaz_empty_note', __( 'Please write something before saving.', 'salanaz' ) );
		}

		if ( ! array_key_exists( $type, self::types() ) ) {
			$type = self::TYPE_NOTE;
		}

		// Accept only a real Y-m-d date; anything else is stored as no follow-up.
		$date = null;

		if ( $follow_up ) {
			$parsed = DateTime::createFromFormat( 'Y-m-d', $follow_up );

			if ( $parsed && $parsed->format( 'Y-m-d' ) === $follow_up ) {
				$date = $follow_up;
			}
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			salanaz_table( 'client_notes' ),
			array(
				'client_id'      => $client_id,
				'author_id'      => get_current_user_id(),
				'note_type'      => $type,
				'note'           => $note,
				'follow_up_date' => $date,
				'created_at'     => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'salanaz_note_failed', __( 'The note could not be saved. Please try again.', 'salanaz' ) );
		}

		Salanaz_Activity::log(
			Salanaz_Activity::NOTE_ADDED,
			array(
				'subject_id'  => $client_id,
				'object_type' => 'note',
				'object_id'   => (int) $wpdb->insert_id,
				'details'     => array( 'type' => $type ),
			)
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Notes for a client, newest first.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $limit     Maximum rows.
	 * @return array<int, object>
	 */
	public static function for_client( int $client_id, int $limit = 100 ): array {
		global $wpdb;

		if ( ! Salanaz_Users::staff_can_view_client( $client_id ) ) {
			return array();
		}

		$table = salanaz_table( 'client_notes' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE client_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
				$client_id,
				max( 1, min( 200, $limit ) )
			)
		);
	}

	/**
	 * Outstanding follow-ups for a staff member.
	 *
	 * Only the most recent follow-up per client is returned, so an officer sees
	 * one row per client rather than every note they ever dated.
	 *
	 * @param int $staff_id Staff user ID.
	 * @param int $limit    Maximum rows.
	 * @return array<int, object>
	 */
	public static function follow_ups_for_staff( int $staff_id, int $limit = 20 ): array {
		global $wpdb;

		$client_ids = Salanaz_Users::clients_for_staff( $staff_id );

		if ( ! $client_ids ) {
			return array();
		}

		$table        = salanaz_table( 'client_notes' );
		$placeholders = implode( ',', array_fill( 0, count( $client_ids ), '%d' ) );

		$params = $client_ids;
		$params[] = max( 1, min( 100, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT n.* FROM {$table} n
				INNER JOIN (
					SELECT client_id, MAX( id ) AS latest
					FROM {$table}
					WHERE follow_up_date IS NOT NULL AND client_id IN ({$placeholders})
					GROUP BY client_id
				) latest_note ON latest_note.latest = n.id
				ORDER BY n.follow_up_date ASC
				LIMIT %d",
				...$params
			)
		);
	}

	/**
	 * How many follow-ups are due today or overdue.
	 *
	 * @param int $staff_id Staff user ID.
	 * @return int
	 */
	public static function due_count( int $staff_id ): int {
		$today = current_time( 'Y-m-d' );
		$due   = 0;

		foreach ( self::follow_ups_for_staff( $staff_id, 100 ) as $note ) {
			if ( $note->follow_up_date && $note->follow_up_date <= $today ) {
				++$due;
			}
		}

		return $due;
	}
}
