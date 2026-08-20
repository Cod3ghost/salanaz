<?php
/**
 * Custom database tables.
 *
 * Transactional data lives in dedicated tables rather than post meta so that
 * reporting queries (revenue collected vs outstanding, sales per staff, overdue
 * installments) stay indexable as volume grows.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's tables.
 */
class Salanaz_Schema {

	/** Option holding the installed schema version. */
	public const VERSION_OPTION = 'salanaz_db_version';

	/* Transaction statuses. */
	public const TXN_PENDING      = 'pending';
	public const TXN_AWAITING     = 'pending_verification';
	public const TXN_VERIFIED     = 'verified';
	public const TXN_REJECTED     = 'rejected';
	public const TXN_FAILED       = 'failed';

	/* Payment methods. */
	public const METHOD_PAYSTACK = 'paystack';
	public const METHOD_MANUAL   = 'manual_transfer';

	/* Payment types. */
	public const TYPE_OUTRIGHT    = 'outright';
	public const TYPE_DOWNPAYMENT = 'down_payment';
	public const TYPE_INSTALLMENT = 'installment';

	/* Installment statuses. */
	public const INST_PENDING = 'pending';
	public const INST_PARTIAL = 'partial';
	public const INST_PAID    = 'paid';
	public const INST_OVERDUE = 'overdue';

	/* Plan statuses. */
	public const PLAN_ACTIVE    = 'active';
	public const PLAN_COMPLETED = 'completed';
	public const PLAN_DEFAULTED = 'defaulted';
	public const PLAN_CANCELLED = 'cancelled';

	/**
	 * Run dbDelta for every table, then record the schema version.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		foreach ( self::table_definitions( $charset ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::VERSION_OPTION, SALANAZ_DB_VERSION, false );
	}

	/**
	 * Re-run the installer when the shipped schema version moves ahead of the
	 * installed one. Hooked to admin_init.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== SALANAZ_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * All CREATE TABLE statements, formatted the way dbDelta expects
	 * (two spaces before KEY, one space around types, lowercase keywords left
	 * as written by WordPress core convention).
	 *
	 * @param string $charset Result of $wpdb->get_charset_collate().
	 * @return string[]
	 */
	private static function table_definitions( string $charset ): array {
		$transactions = salanaz_table( 'transactions' );
		$plans        = salanaz_table( 'installment_plans' );
		$installments = salanaz_table( 'installments' );
		$assignments  = salanaz_table( 'assignments' );
		$notes        = salanaz_table( 'client_notes' );
		$log          = salanaz_table( 'activity_log' );

		$sql = array();

		/*
		 * Transactions — one row per payment attempt, whether through Paystack
		 * or an uploaded bank-transfer proof.
		 */
		$sql[] = "CREATE TABLE {$transactions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(64) NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			plot_id bigint(20) unsigned NOT NULL,
			plan_id bigint(20) unsigned DEFAULT NULL,
			installment_id bigint(20) unsigned DEFAULT NULL,
			amount decimal(15,2) NOT NULL DEFAULT 0.00,
			currency char(3) NOT NULL DEFAULT 'NGN',
			payment_method varchar(32) NOT NULL DEFAULT 'manual_transfer',
			payment_type varchar(32) NOT NULL DEFAULT 'outright',
			status varchar(32) NOT NULL DEFAULT 'pending',
			gateway_reference varchar(191) DEFAULT NULL,
			gateway_payload longtext DEFAULT NULL,
			proof_path varchar(255) DEFAULT NULL,
			proof_mime varchar(100) DEFAULT NULL,
			payer_note text DEFAULT NULL,
			rejection_reason text DEFAULT NULL,
			verified_by bigint(20) unsigned DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			receipt_path varchar(255) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY client_id (client_id),
			KEY plot_id (plot_id),
			KEY plan_id (plan_id),
			KEY status (status),
			KEY gateway_reference (gateway_reference),
			KEY created_at (created_at)
		) {$charset};";

		/*
		 * Installment plans — the header record for a staged purchase.
		 */
		$sql[] = "CREATE TABLE {$plans} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			reference varchar(64) NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			plot_id bigint(20) unsigned NOT NULL,
			total_amount decimal(15,2) NOT NULL DEFAULT 0.00,
			down_payment decimal(15,2) NOT NULL DEFAULT 0.00,
			tenure_months smallint(5) unsigned NOT NULL DEFAULT 12,
			frequency varchar(20) NOT NULL DEFAULT 'monthly',
			start_date date DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'active',
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY client_id (client_id),
			KEY plot_id (plot_id),
			KEY status (status)
		) {$charset};";

		/*
		 * Installments — the generated schedule rows. Reminder flags live here
		 * so the cron job never double-sends.
		 */
		$sql[] = "CREATE TABLE {$installments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			plan_id bigint(20) unsigned NOT NULL,
			installment_no smallint(5) unsigned NOT NULL DEFAULT 1,
			due_date date NOT NULL,
			amount decimal(15,2) NOT NULL DEFAULT 0.00,
			amount_paid decimal(15,2) NOT NULL DEFAULT 0.00,
			status varchar(32) NOT NULL DEFAULT 'pending',
			transaction_id bigint(20) unsigned DEFAULT NULL,
			reminder_7d_sent_at datetime DEFAULT NULL,
			reminder_1d_sent_at datetime DEFAULT NULL,
			overdue_notice_sent_at datetime DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY plan_id (plan_id),
			KEY due_date (due_date),
			KEY status (status)
		) {$charset};";

		/*
		 * Client-to-staff assignments. History is retained: reassignment marks
		 * the old row inactive rather than deleting it.
		 */
		$sql[] = "CREATE TABLE {$assignments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			staff_id bigint(20) unsigned NOT NULL,
			assigned_by bigint(20) unsigned DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			assigned_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			unassigned_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY staff_id (staff_id),
			KEY is_active (is_active)
		) {$charset};";

		/*
		 * Sales-staff notes and follow-ups against a client.
		 */
		$sql[] = "CREATE TABLE {$notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			client_id bigint(20) unsigned NOT NULL,
			author_id bigint(20) unsigned NOT NULL,
			note_type varchar(32) NOT NULL DEFAULT 'note',
			note longtext NOT NULL,
			follow_up_date date DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY client_id (client_id),
			KEY author_id (author_id),
			KEY follow_up_date (follow_up_date)
		) {$charset};";

		/*
		 * Audit trail. NDPR asks that access to personal and financial data be
		 * accountable, so approvals, verifications, assignments and record
		 * views by staff are written here.
		 */
		$sql[] = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned DEFAULT NULL,
			subject_id bigint(20) unsigned DEFAULT NULL,
			object_type varchar(32) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned DEFAULT NULL,
			action varchar(64) NOT NULL DEFAULT '',
			details longtext DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY actor_id (actor_id),
			KEY subject_id (subject_id),
			KEY object_type_id (object_type,object_id),
			KEY created_at (created_at)
		) {$charset};";

		return $sql;
	}

	/**
	 * Drop every table. Called from uninstall.php only.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = array(
			salanaz_table( 'activity_log' ),
			salanaz_table( 'client_notes' ),
			salanaz_table( 'assignments' ),
			salanaz_table( 'installments' ),
			salanaz_table( 'installment_plans' ),
			salanaz_table( 'transactions' ),
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names cannot be prepared.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::VERSION_OPTION );
	}
}
