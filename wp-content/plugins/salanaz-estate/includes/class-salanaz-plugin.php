<?php
/**
 * Plugin container — instantiates the modules and wires their hooks.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton bootstrap.
 */
final class Salanaz_Plugin {

	/** @var Salanaz_Plugin|null */
	private static ?Salanaz_Plugin $instance = null;

	/** @var array<string, object> Instantiated modules, keyed by short name. */
	private array $modules = array();

	/** @var bool Guard so boot() is idempotent. */
	private bool $booted = false;

	/**
	 * Shared instance.
	 */
	public static function instance(): Salanaz_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Instantiate modules and register their hooks.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->modules['post_types'] = new Salanaz_Post_Types();
		$this->modules['inventory']  = new Salanaz_Inventory();
		$this->modules['query']         = new Salanaz_Query();
		$this->modules['profile']       = new Salanaz_Profile();
		$this->modules['notifications'] = new Salanaz_Notifications();
		$this->modules['auth']          = new Salanaz_Auth();
		$this->modules['uploads']       = new Salanaz_Uploads();
		$this->modules['dashboard']     = new Salanaz_Dashboard();
		$this->modules['paystack']      = new Salanaz_Paystack();
		$this->modules['documents']     = new Salanaz_Documents();
		$this->modules['cron']          = new Salanaz_Cron();
		$this->modules['mail_log']      = new Salanaz_Mail_Log();
		$this->modules['pages']         = new Salanaz_Pages();
		$this->modules['updater']       = new Salanaz_Updater();

		if ( is_admin() ) {
			$this->modules['admin']     = new Salanaz_Admin();
			$this->modules['metaboxes'] = new Salanaz_Metaboxes();
		}

		foreach ( $this->modules as $module ) {
			if ( method_exists( $module, 'register_hooks' ) ) {
				$module->register_hooks();
			}
		}

		// Reporting figures are cached; anything that moves money or
		// inventory invalidates them.
		foreach ( array( 'salanaz_payment_verified', 'salanaz_payment_rejected', 'salanaz_paystack_verified', 'salanaz_plot_allocated' ) as $event ) {
			add_action( $event, array( 'Salanaz_Reports', 'flush' ) );
		}

		add_action( 'save_post_' . Salanaz_Post_Types::PLOT, array( 'Salanaz_Reports', 'flush' ) );

		add_action( 'admin_init', array( 'Salanaz_Schema', 'maybe_upgrade' ) );
		add_action( 'admin_init', array( $this, 'maybe_refresh_roles' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Fetch a module.
	 *
	 * @param string $name Module key, e.g. 'post_types'.
	 * @return object|null
	 */
	public function module( string $name ): ?object {
		return $this->modules[ $name ] ?? null;
	}

	/**
	 * Re-install roles after a plugin update so new capabilities land on
	 * existing sites without requiring a deactivate/reactivate cycle.
	 */
	public function maybe_refresh_roles(): void {
		if ( get_option( 'salanaz_version' ) !== SALANAZ_VERSION ) {
			Salanaz_Roles::install();
			Salanaz_Activator::create_private_directory();
			flush_rewrite_rules();
			update_option( 'salanaz_version', SALANAZ_VERSION, false );
		}
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'salanaz', false, dirname( SALANAZ_PLUGIN_BASENAME ) . '/languages' );
	}
}
