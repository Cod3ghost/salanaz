<?php
/**
 * Custom roles and capabilities.
 *
 * Every AJAX/REST endpoint in this plugin gates on one of the SALANAZ
 * capabilities below rather than on is_user_logged_in() or a role name, so that
 * capabilities can be moved between roles without touching endpoint code.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and removes the three Salanaz roles.
 */
class Salanaz_Roles {

	public const COFOUNDER = 'salanaz_cofounder';
	public const STAFF     = 'salanaz_sales_staff';
	public const CLIENT    = 'salanaz_client';

	/* ---------------------------------------------------------------------
	 * Capabilities
	 * ------------------------------------------------------------------ */

	/** Approve or reject pending client registrations. */
	public const CAP_APPROVE_CLIENTS = 'salanaz_approve_clients';

	/** Create and manage sales staff accounts. */
	public const CAP_MANAGE_STAFF = 'salanaz_manage_staff';

	/** Assign and reassign clients to sales staff. */
	public const CAP_ASSIGN_CLIENTS = 'salanaz_assign_clients';

	/** Verify or reject uploaded payment proofs. */
	public const CAP_VERIFY_PAYMENTS = 'salanaz_verify_payments';

	/** Create/edit estates and plots, set pricing and availability. */
	public const CAP_MANAGE_INVENTORY = 'salanaz_manage_inventory';

	/** Read-only access to the inventory (staff and clients). */
	public const CAP_VIEW_INVENTORY = 'salanaz_view_inventory';

	/** See every client record on the system. */
	public const CAP_VIEW_ALL_CLIENTS = 'salanaz_view_all_clients';

	/** See only the clients assigned to you. */
	public const CAP_VIEW_OWN_CLIENTS = 'salanaz_view_own_clients';

	/** See every transaction on the system. */
	public const CAP_VIEW_ALL_TRANSACTIONS = 'salanaz_view_all_transactions';

	/** See your own transactions only. */
	public const CAP_VIEW_OWN_TRANSACTIONS = 'salanaz_view_own_transactions';

	/** Add notes / follow-ups against a client record. */
	public const CAP_ADD_CLIENT_NOTES = 'salanaz_add_client_notes';

	/** Reach the analytics / reporting dashboard. */
	public const CAP_VIEW_ANALYTICS = 'salanaz_view_analytics';

	/** Reserve a plot and start a payment. */
	public const CAP_PURCHASE_PLOT = 'salanaz_purchase_plot';

	/** Upload proof of a bank transfer. */
	public const CAP_UPLOAD_PAYMENT_PROOF = 'salanaz_upload_payment_proof';

	/** Download receipts and allocation letters. */
	public const CAP_DOWNLOAD_DOCUMENTS = 'salanaz_download_documents';

	/**
	 * Capability map, keyed by role slug.
	 *
	 * @return array<string, array{name: string, caps: array<string, bool>}>
	 */
	public static function definitions(): array {
		$cofounder = array(
			self::CAP_APPROVE_CLIENTS       => true,
			self::CAP_MANAGE_STAFF          => true,
			self::CAP_ASSIGN_CLIENTS        => true,
			self::CAP_VERIFY_PAYMENTS       => true,
			self::CAP_MANAGE_INVENTORY      => true,
			self::CAP_VIEW_INVENTORY        => true,
			self::CAP_VIEW_ALL_CLIENTS      => true,
			self::CAP_VIEW_OWN_CLIENTS      => true,
			self::CAP_VIEW_ALL_TRANSACTIONS => true,
			self::CAP_VIEW_OWN_TRANSACTIONS => true,
			self::CAP_ADD_CLIENT_NOTES      => true,
			self::CAP_VIEW_ANALYTICS        => true,
			self::CAP_DOWNLOAD_DOCUMENTS    => true,

			// WordPress primitives the co-founder genuinely needs.
			'read'                          => true,
			'upload_files'                  => true,
			'list_users'                    => true,
			'edit_users'                    => true,
			'create_users'                  => true,
			'promote_users'                 => true,
		);

		$staff = array(
			self::CAP_VIEW_OWN_CLIENTS      => true,
			self::CAP_VIEW_OWN_TRANSACTIONS => true,
			self::CAP_VIEW_INVENTORY        => true,
			self::CAP_ADD_CLIENT_NOTES      => true,
			'read'                          => true,
			'upload_files'                  => true,
		);

		$client = array(
			self::CAP_PURCHASE_PLOT         => true,
			self::CAP_UPLOAD_PAYMENT_PROOF  => true,
			self::CAP_VIEW_OWN_TRANSACTIONS => true,
			self::CAP_VIEW_INVENTORY        => true,
			self::CAP_DOWNLOAD_DOCUMENTS    => true,
			'read'                          => true,
		);

		return array(
			self::COFOUNDER => array(
				'name' => __( 'Co-Founder', 'salanaz' ),
				'caps' => $cofounder,
			),
			self::STAFF     => array(
				'name' => __( 'Sales Staff', 'salanaz' ),
				'caps' => $staff,
			),
			self::CLIENT    => array(
				'name' => __( 'Client', 'salanaz' ),
				'caps' => $client,
			),
		);
	}

	/**
	 * Every capability this plugin defines.
	 *
	 * @return string[]
	 */
	public static function all_caps(): array {
		$caps = array();

		foreach ( self::definitions() as $definition ) {
			foreach ( $definition['caps'] as $cap => $granted ) {
				// Only collect the plugin's own capabilities, not WP primitives.
				if ( str_starts_with( $cap, 'salanaz_' ) ) {
					$caps[ $cap ] = true;
				}
			}
		}

		return array_keys( $caps );
	}

	/**
	 * Create the roles and mirror every Salanaz capability onto administrator.
	 *
	 * Safe to call repeatedly — used on activation and on version upgrade.
	 */
	public static function install(): void {
		foreach ( self::definitions() as $slug => $definition ) {
			// remove_role() then add_role() so capability changes in a new
			// plugin version actually land on existing installs.
			remove_role( $slug );
			add_role( $slug, $definition['name'], $definition['caps'] );
		}

		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( self::all_caps() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove the roles and strip the capabilities from administrator.
	 *
	 * Called from uninstall.php only — never on deactivation, which would lock
	 * real users out of their own accounts.
	 */
	public static function uninstall(): void {
		foreach ( array_keys( self::definitions() ) as $slug ) {
			remove_role( $slug );
		}

		$administrator = get_role( 'administrator' );

		if ( $administrator ) {
			foreach ( self::all_caps() as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}
	}

	/**
	 * Human-readable label for a role slug.
	 *
	 * @param string $slug Role slug.
	 * @return string
	 */
	public static function label( string $slug ): string {
		$definitions = self::definitions();

		return $definitions[ $slug ]['name'] ?? $slug;
	}
}
