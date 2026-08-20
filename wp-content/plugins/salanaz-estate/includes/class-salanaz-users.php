<?php
/**
 * Client and staff account operations.
 *
 * Every method here re-checks capabilities itself rather than trusting the
 * caller, so the same code is safe from an admin screen, an AJAX handler or
 * WP-CLI.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Approvals, staff creation and client-staff assignment.
 */
class Salanaz_Users {

	/* =====================================================================
	 * Approvals
	 * ================================================================== */

	/**
	 * Clients awaiting approval.
	 *
	 * @param int $limit Maximum rows.
	 * @return WP_User[]
	 */
	public static function pending_clients( int $limit = 100 ): array {
		return get_users(
			array(
				'role'       => Salanaz_Roles::CLIENT,
				'number'     => $limit,
				'orderby'    => 'registered',
				'order'      => 'ASC',
				'meta_key'   => Salanaz_Profile::META_ACCOUNT_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => Salanaz_Profile::STATUS_PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Approve a client registration.
	 *
	 * @param int $client_id Client user ID.
	 * @return true|WP_Error
	 */
	public static function approve_client( int $client_id ) {
		if ( ! current_user_can( Salanaz_Roles::CAP_APPROVE_CLIENTS ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to approve registrations.', 'salanaz' ) );
		}

		$client = get_userdata( $client_id );

		if ( ! $client instanceof WP_User ) {
			return new WP_Error( 'salanaz_no_user', __( 'That user could not be found.', 'salanaz' ) );
		}

		update_user_meta( $client_id, Salanaz_Profile::META_ACCOUNT_STATUS, Salanaz_Profile::STATUS_APPROVED );
		update_user_meta( $client_id, Salanaz_Profile::META_APPROVED_BY, get_current_user_id() );
		update_user_meta( $client_id, Salanaz_Profile::META_APPROVED_AT, current_time( 'mysql', true ) );
		delete_user_meta( $client_id, Salanaz_Profile::META_REJECT_REASON );

		Salanaz_Activity::log(
			Salanaz_Activity::CLIENT_APPROVED,
			array(
				'subject_id'  => $client_id,
				'object_type' => 'user',
				'object_id'   => $client_id,
			)
		);

		Salanaz_Notifications::client_approved( $client );

		/**
		 * Fires after a client registration is approved.
		 *
		 * @param WP_User $client The approved client.
		 */
		do_action( 'salanaz_client_approved', $client );

		return true;
	}

	/**
	 * Reject a client registration.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $reason    Optional reason shown to the client.
	 * @return true|WP_Error
	 */
	public static function reject_client( int $client_id, string $reason = '' ) {
		if ( ! current_user_can( Salanaz_Roles::CAP_APPROVE_CLIENTS ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to reject registrations.', 'salanaz' ) );
		}

		$client = get_userdata( $client_id );

		if ( ! $client instanceof WP_User ) {
			return new WP_Error( 'salanaz_no_user', __( 'That user could not be found.', 'salanaz' ) );
		}

		update_user_meta( $client_id, Salanaz_Profile::META_ACCOUNT_STATUS, Salanaz_Profile::STATUS_REJECTED );
		update_user_meta( $client_id, Salanaz_Profile::META_REJECT_REASON, $reason );

		Salanaz_Activity::log(
			Salanaz_Activity::CLIENT_REJECTED,
			array(
				'subject_id'  => $client_id,
				'object_type' => 'user',
				'object_id'   => $client_id,
				'details'     => array( 'reason' => $reason ),
			)
		);

		Salanaz_Notifications::client_rejected( $client, $reason );

		/**
		 * Fires after a client registration is rejected.
		 *
		 * @param WP_User $client The rejected client.
		 * @param string  $reason The reason given.
		 */
		do_action( 'salanaz_client_rejected', $client, $reason );

		return true;
	}

	/* =====================================================================
	 * Staff accounts
	 * ================================================================== */

	/**
	 * Create a sales staff account.
	 *
	 * Staff cannot self-register — only a co-founder reaches this.
	 *
	 * @param array<string, string> $data first_name, last_name, email, phone.
	 * @return int|WP_Error New user ID.
	 */
	public static function create_staff( array $data ) {
		if ( ! current_user_can( Salanaz_Roles::CAP_MANAGE_STAFF ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to create staff accounts.', 'salanaz' ) );
		}

		$first = sanitize_text_field( $data['first_name'] ?? '' );
		$last  = sanitize_text_field( $data['last_name'] ?? '' );
		$email = sanitize_email( $data['email'] ?? '' );
		$phone = salanaz_normalize_phone( $data['phone'] ?? '' );

		if ( ! $first || ! $last ) {
			return new WP_Error( 'salanaz_missing_name', __( 'Please provide both a first and last name.', 'salanaz' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'salanaz_bad_email', __( 'Please provide a valid email address.', 'salanaz' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'salanaz_email_exists', __( 'An account already uses that email address.', 'salanaz' ) );
		}

		if ( '' === $phone ) {
			return new WP_Error( 'salanaz_bad_phone', __( 'Please provide a valid Nigerian phone number.', 'salanaz' ) );
		}

		$login    = self::unique_login( $first, $last );
		$password = wp_generate_password( 14, true, false );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $password,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => $first . ' ' . $last,
				'role'         => Salanaz_Roles::STAFF,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		update_user_meta( $user_id, Salanaz_Profile::META_PHONE, $phone );
		update_user_meta( $user_id, Salanaz_Profile::META_ACCOUNT_STATUS, Salanaz_Profile::STATUS_APPROVED );
		update_user_meta( $user_id, Salanaz_Profile::META_REGISTERED_AT, current_time( 'mysql', true ) );

		Salanaz_Activity::log(
			Salanaz_Activity::STAFF_CREATED,
			array(
				'subject_id'  => $user_id,
				'object_type' => 'user',
				'object_id'   => $user_id,
			)
		);

		Salanaz_Notifications::staff_created( get_userdata( $user_id ), $password );

		return (int) $user_id;
	}

	/**
	 * Build a unique login from a name.
	 *
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @return string
	 */
	private static function unique_login( string $first, string $last ): string {
		$base  = sanitize_user( strtolower( $first . '.' . $last ), true );
		$base  = $base ?: 'staff';
		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		return $login;
	}

	/* =====================================================================
	 * Assignment
	 * ================================================================== */

	/**
	 * Assign a client to a sales staff member.
	 *
	 * Any existing active assignment is deactivated rather than deleted, so the
	 * history of who handled an account is preserved.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $staff_id  Staff user ID.
	 * @return true|WP_Error
	 */
	public static function assign_client( int $client_id, int $staff_id ) {
		global $wpdb;

		if ( ! current_user_can( Salanaz_Roles::CAP_ASSIGN_CLIENTS ) ) {
			return new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to assign clients.', 'salanaz' ) );
		}

		$client = get_userdata( $client_id );
		$staff  = get_userdata( $staff_id );

		if ( ! $client instanceof WP_User || ! salanaz_user_has_role( Salanaz_Roles::CLIENT, $client ) ) {
			return new WP_Error( 'salanaz_bad_client', __( 'That client could not be found.', 'salanaz' ) );
		}

		if ( ! $staff instanceof WP_User || ! salanaz_user_has_role( Salanaz_Roles::STAFF, $staff ) ) {
			return new WP_Error( 'salanaz_bad_staff', __( 'That sales staff member could not be found.', 'salanaz' ) );
		}

		$current = self::assigned_staff_id( $client_id );

		if ( $current === $staff_id ) {
			return true;
		}

		$table = salanaz_table( 'assignments' );
		$now   = current_time( 'mysql', true );

		if ( $current ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'is_active'     => 0,
					'unassigned_at' => $now,
				),
				array(
					'client_id' => $client_id,
					'is_active' => 1,
				),
				array( '%d', '%s' ),
				array( '%d', '%d' )
			);
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'client_id'   => $client_id,
				'staff_id'    => $staff_id,
				'assigned_by' => get_current_user_id(),
				'is_active'   => 1,
				'assigned_at' => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);

		wp_cache_delete( 'salanaz_assigned_staff_' . $client_id, 'salanaz' );

		Salanaz_Activity::log(
			Salanaz_Activity::CLIENT_ASSIGNED,
			array(
				'subject_id'  => $client_id,
				'object_type' => 'user',
				'object_id'   => $staff_id,
				'details'     => array( 'previous_staff_id' => $current ),
			)
		);

		Salanaz_Notifications::client_assigned( $staff, $client );
		Salanaz_Notifications::staff_introduced( $client, $staff );

		/**
		 * Fires after a client is assigned to a sales staff member.
		 *
		 * @param WP_User $client The client.
		 * @param WP_User $staff  The staff member.
		 */
		do_action( 'salanaz_client_assigned', $client, $staff );

		return true;
	}

	/**
	 * The staff member currently assigned to a client.
	 *
	 * @param int $client_id Client user ID.
	 * @return int Staff user ID, or 0 when unassigned.
	 */
	public static function assigned_staff_id( int $client_id ): int {
		global $wpdb;

		$cached = wp_cache_get( 'salanaz_assigned_staff_' . $client_id, 'salanaz' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = salanaz_table( 'assignments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$staff_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT staff_id FROM {$table} WHERE client_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
				$client_id
			)
		);

		wp_cache_set( 'salanaz_assigned_staff_' . $client_id, $staff_id, 'salanaz', 5 * MINUTE_IN_SECONDS );

		return $staff_id;
	}

	/**
	 * The staff member assigned to a client, as a user object.
	 *
	 * @param int $client_id Client user ID.
	 * @return WP_User|null
	 */
	public static function assigned_staff( int $client_id ): ?WP_User {
		$staff_id = self::assigned_staff_id( $client_id );

		if ( ! $staff_id ) {
			return null;
		}

		$staff = get_userdata( $staff_id );

		return $staff instanceof WP_User ? $staff : null;
	}

	/**
	 * Client IDs assigned to a staff member.
	 *
	 * @param int $staff_id Staff user ID.
	 * @return int[]
	 */
	public static function clients_for_staff( int $staff_id ): array {
		global $wpdb;

		$table = salanaz_table( 'assignments' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT client_id FROM {$table} WHERE staff_id = %d AND is_active = 1 ORDER BY assigned_at DESC",
				$staff_id
			)
		);

		return array_map( 'absint', $ids );
	}

	/**
	 * Whether a staff member may view a given client.
	 *
	 * Used by every staff-facing screen and endpoint so a sales officer cannot
	 * read another officer's client by changing an ID in the URL.
	 *
	 * @param int      $client_id Client user ID.
	 * @param int|null $staff_id  Staff user ID. Defaults to the current user.
	 * @return bool
	 */
	public static function staff_can_view_client( int $client_id, ?int $staff_id = null ): bool {
		if ( current_user_can( Salanaz_Roles::CAP_VIEW_ALL_CLIENTS ) ) {
			return true;
		}

		if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_OWN_CLIENTS ) ) {
			return false;
		}

		$staff_id = $staff_id ?: get_current_user_id();

		return self::assigned_staff_id( $client_id ) === $staff_id;
	}

	/**
	 * All sales staff.
	 *
	 * @return WP_User[]
	 */
	public static function all_staff(): array {
		return get_users(
			array(
				'role'    => Salanaz_Roles::STAFF,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
	}

	/**
	 * All clients, optionally filtered by account status.
	 *
	 * @param string $status Optional account status.
	 * @return WP_User[]
	 */
	public static function all_clients( string $status = '' ): array {
		$args = array(
			'role'    => Salanaz_Roles::CLIENT,
			'orderby' => 'registered',
			'order'   => 'DESC',
			'number'  => 500,
		);

		if ( $status ) {
			$args['meta_key']   = Salanaz_Profile::META_ACCOUNT_STATUS; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = $status; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		return get_users( $args );
	}
}
