<?php
/**
 * Front-end authentication.
 *
 * Clients and staff never see wp-admin. Login, registration and the dashboard
 * all live on ordinary pages driven by the shortcodes registered here.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registration, login, logout and wp-admin lockout.
 */
class Salanaz_Auth {

	/** Nonce action for the registration form. */
	private const NONCE_REGISTER = 'salanaz_register';

	/** Transient prefix for registration rate limiting. */
	private const RATE_PREFIX = 'salanaz_reg_';

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_shortcode( 'salanaz_login', array( $this, 'render_login' ) );
		add_shortcode( 'salanaz_register', array( $this, 'render_register' ) );

		add_action( 'template_redirect', array( $this, 'handle_registration' ) );
		add_action( 'template_redirect', array( $this, 'redirect_logged_in_users' ) );

		add_filter( 'authenticate', array( $this, 'block_disallowed_accounts' ), 30, 1 );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
		add_filter( 'registration_redirect', array( $this, 'registration_redirect' ) );

		add_action( 'admin_init', array( $this, 'block_admin_access' ) );
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );

		add_filter( 'login_url', array( $this, 'filter_login_url' ), 10, 3 );
		add_filter( 'logout_url', array( $this, 'filter_logout_url' ), 10, 2 );
	}

	/* =====================================================================
	 * URLs
	 * ================================================================== */

	/**
	 * URL of the front-end login page.
	 *
	 * @return string
	 */
	public static function login_page_url(): string {
		return home_url( '/login/' );
	}

	/**
	 * URL of the front-end registration page.
	 *
	 * @return string
	 */
	public static function register_page_url(): string {
		return home_url( '/register/' );
	}

	/**
	 * URL of the role-aware dashboard.
	 *
	 * @return string
	 */
	public static function dashboard_url(): string {
		return home_url( '/dashboard/' );
	}

	/**
	 * Point wp_login_url() at our page so core links follow it.
	 *
	 * @param string $login_url    Default URL.
	 * @param string $redirect     Redirect target.
	 * @param bool   $force_reauth Whether reauth was requested.
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect, $force_reauth ): string {
		// Leave the real wp-login.php alone for password resets and for
		// administrators, who still need core's screens.
		if ( $force_reauth ) {
			return $login_url;
		}

		$url = self::login_page_url();

		if ( $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}

		return $url;
	}

	/**
	 * Send logout back to the home page.
	 *
	 * @param string $logout_url Default URL.
	 * @param string $redirect   Redirect target.
	 * @return string
	 */
	public function filter_logout_url( $logout_url, $redirect ): string {
		if ( $redirect ) {
			return $logout_url;
		}

		return add_query_arg( 'redirect_to', rawurlencode( home_url( '/' ) ), $logout_url );
	}

	/* =====================================================================
	 * Access control
	 * ================================================================== */

	/**
	 * Block rejected and suspended accounts from authenticating.
	 *
	 * Pending clients are deliberately allowed in — they need somewhere to see
	 * that their account is awaiting review. Their capabilities are withheld
	 * until approval, so they can look but not transact.
	 *
	 * @param WP_User|WP_Error|null $user Result so far.
	 * @return WP_User|WP_Error|null
	 */
	public function block_disallowed_accounts( $user ) {
		if ( ! $user instanceof WP_User ) {
			return $user;
		}

		$status = get_user_meta( $user->ID, Salanaz_Profile::META_ACCOUNT_STATUS, true );

		if ( Salanaz_Profile::STATUS_REJECTED === $status ) {
			return new WP_Error(
				'salanaz_rejected',
				__( 'This account was not approved. Please contact our office if you believe that is a mistake.', 'salanaz' )
			);
		}

		if ( Salanaz_Profile::STATUS_SUSPENDED === $status ) {
			return new WP_Error(
				'salanaz_suspended',
				__( 'This account has been suspended. Please contact our office.', 'salanaz' )
			);
		}

		return $user;
	}

	/**
	 * Keep clients and sales staff out of wp-admin.
	 *
	 * AJAX is exempt because front-end dashboards post to admin-ajax.php.
	 */
	public function block_admin_access(): void {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) || current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) ) {
			return;
		}

		if ( salanaz_user_has_role( Salanaz_Roles::CLIENT ) || salanaz_user_has_role( Salanaz_Roles::STAFF ) ) {
			wp_safe_redirect( self::dashboard_url() );
			exit;
		}
	}

	/**
	 * Hide the admin bar from clients and staff.
	 *
	 * @param bool $show Current decision.
	 * @return bool
	 */
	public function hide_admin_bar( $show ): bool {
		if ( salanaz_user_has_role( Salanaz_Roles::CLIENT ) || salanaz_user_has_role( Salanaz_Roles::STAFF ) ) {
			return false;
		}

		return (bool) $show;
	}

	/**
	 * Send each role to the right place after logging in.
	 *
	 * @param string           $redirect_to Requested destination.
	 * @param string           $requested   Raw requested destination.
	 * @param WP_User|WP_Error $user        The user, or an error.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested, $user ): string {
		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}

		if ( salanaz_user_has_role( Salanaz_Roles::CLIENT, $user ) || salanaz_user_has_role( Salanaz_Roles::STAFF, $user ) ) {
			return self::dashboard_url();
		}

		if ( salanaz_user_has_role( Salanaz_Roles::COFOUNDER, $user ) ) {
			return admin_url( 'admin.php?page=salanaz-approvals' );
		}

		return $redirect_to;
	}

	/**
	 * Keep signed-in users off the login and register pages.
	 */
	public function redirect_logged_in_users(): void {
		if ( ! is_user_logged_in() || ! is_page() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( in_array( $post->post_name, array( 'login', 'register' ), true ) ) {
			wp_safe_redirect( self::dashboard_url() );
			exit;
		}
	}

	/**
	 * Redirect target after core registration.
	 *
	 * @return string
	 */
	public function registration_redirect(): string {
		return add_query_arg( 'registered', '1', self::login_page_url() );
	}

	/* =====================================================================
	 * Shortcodes
	 * ================================================================== */

	/**
	 * Render the login form.
	 *
	 * @return string
	 */
	public function render_login(): string {
		if ( is_user_logged_in() ) {
			return Salanaz_Templates::notice( __( 'You are already signed in.', 'salanaz' ), 'info' );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only flags.
		$registered = isset( $_GET['registered'] );
		$redirect   = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : self::dashboard_url();
		$plot       = isset( $_GET['plot'] ) ? absint( $_GET['plot'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return Salanaz_Templates::render(
			'auth/login.php',
			array(
				'registered' => $registered,
				'redirect'   => $redirect,
				'plot_id'    => $plot,
				'errors'     => $this->login_errors(),
			)
		);
	}

	/**
	 * Translate wp-login error codes carried back in the query string.
	 *
	 * @return string[]
	 */
	private function login_errors(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = isset( $_GET['login'] ) ? sanitize_key( wp_unslash( $_GET['login'] ) ) : '';

		$messages = array(
			'failed'    => __( 'That username or password was not correct. Please try again.', 'salanaz' ),
			'empty'     => __( 'Please enter both your username and your password.', 'salanaz' ),
			'rejected'  => __( 'This account was not approved. Please contact our office.', 'salanaz' ),
			'suspended' => __( 'This account has been suspended. Please contact our office.', 'salanaz' ),
		);

		return isset( $messages[ $code ] ) ? array( $messages[ $code ] ) : array();
	}

	/**
	 * Render the registration form.
	 *
	 * @return string
	 */
	public function render_register(): string {
		if ( is_user_logged_in() ) {
			return Salanaz_Templates::notice( __( 'You are already signed in.', 'salanaz' ), 'info' );
		}

		$state = get_transient( $this->submission_key() );
		delete_transient( $this->submission_key() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plot_id = isset( $_GET['plot'] ) ? absint( $_GET['plot'] ) : 0;

		return Salanaz_Templates::render(
			'auth/register.php',
			array(
				'errors'  => is_array( $state ) ? ( $state['errors'] ?? array() ) : array(),
				'values'  => is_array( $state ) ? ( $state['values'] ?? array() ) : array(),
				'plot_id' => $plot_id,
				'nonce'   => self::NONCE_REGISTER,
			)
		);
	}

	/* =====================================================================
	 * Registration processing
	 * ================================================================== */

	/**
	 * A per-visitor key for carrying validation state across the redirect.
	 *
	 * @return string
	 */
	private function submission_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		return 'salanaz_regstate_' . md5( $ip . wp_salt() );
	}

	/**
	 * Validate and process the registration form.
	 */
	public function handle_registration(): void {
		if ( ! isset( $_POST['salanaz_register_submit'] ) ) {
			return;
		}

		if ( is_user_logged_in() ) {
			wp_safe_redirect( self::dashboard_url() );
			exit;
		}

		$redirect_back = self::register_page_url();

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE_REGISTER ) ) {
			$this->fail( array( __( 'Your session expired. Please try again.', 'salanaz' ) ), array(), $redirect_back );
		}

		// Simple flood control: five attempts per address per hour.
		$rate_key = self::RATE_PREFIX . md5( ( $_SERVER['REMOTE_ADDR'] ?? '' ) . wp_salt() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$attempts = (int) get_transient( $rate_key );

		if ( $attempts >= 5 ) {
			$this->fail( array( __( 'Too many registration attempts. Please try again in an hour.', 'salanaz' ) ), array(), $redirect_back );
		}

		set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

		// Honeypot — real users never fill this in.
		if ( ! empty( $_POST['salanaz_website'] ) ) {
			wp_safe_redirect( add_query_arg( 'registered', '1', self::login_page_url() ) );
			exit;
		}

		$values = array(
			'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
			'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
			'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'phone'      => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'address'    => sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) ),
			'city'       => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'state'      => sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ),
			'nok_name'   => sanitize_text_field( wp_unslash( $_POST['nok_name'] ?? '' ) ),
			'nok_phone'  => sanitize_text_field( wp_unslash( $_POST['nok_phone'] ?? '' ) ),
		);

		$password = (string) ( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- passwords are not sanitized, only validated.
		$confirm  = (string) ( $_POST['password_confirm'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$consent  = ! empty( $_POST['ndpr_consent'] );

		$errors = $this->validate_registration( $values, $password, $confirm, $consent );

		if ( $errors ) {
			$this->fail( $errors, $values, $redirect_back );
		}

		$phone   = salanaz_normalize_phone( $values['phone'] );
		$login   = $this->unique_login_from_email( $values['email'] );
		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $values['email'],
				'user_pass'    => $password,
				'first_name'   => $values['first_name'],
				'last_name'    => $values['last_name'],
				'display_name' => $values['first_name'] . ' ' . $values['last_name'],
				'role'         => Salanaz_Roles::CLIENT,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			$this->fail( array( $user_id->get_error_message() ), $values, $redirect_back );
		}

		update_user_meta( $user_id, Salanaz_Profile::META_ACCOUNT_STATUS, Salanaz_Profile::STATUS_PENDING );
		update_user_meta( $user_id, Salanaz_Profile::META_PHONE, $phone );
		update_user_meta( $user_id, Salanaz_Profile::META_ADDRESS, $values['address'] );
		update_user_meta( $user_id, Salanaz_Profile::META_CITY, $values['city'] );
		update_user_meta( $user_id, Salanaz_Profile::META_STATE, $values['state'] );
		update_user_meta( $user_id, Salanaz_Profile::META_NOK_NAME, $values['nok_name'] );
		update_user_meta( $user_id, Salanaz_Profile::META_NOK_PHONE, salanaz_normalize_phone( $values['nok_phone'] ) );
		update_user_meta( $user_id, Salanaz_Profile::META_REGISTERED_AT, current_time( 'mysql', true ) );
		update_user_meta( $user_id, Salanaz_Profile::META_CONSENT_NDPR, current_time( 'mysql', true ) );

		// Remember which plot brought them in, so the dashboard can pick it up.
		$plot_id = isset( $_POST['plot_id'] ) ? absint( $_POST['plot_id'] ) : 0;

		if ( $plot_id && Salanaz_Post_Types::PLOT === get_post_type( $plot_id ) ) {
			update_user_meta( $user_id, 'salanaz_interest_plot_id', $plot_id );
		}

		Salanaz_Activity::log(
			Salanaz_Activity::CLIENT_REGISTERED,
			array(
				'subject_id'  => $user_id,
				'object_type' => 'user',
				'object_id'   => $user_id,
				'actor_id'    => $user_id,
			)
		);

		Salanaz_Notifications::registration_received( get_userdata( $user_id ) );

		/**
		 * Fires after a client self-registers.
		 *
		 * @param int $user_id The new client.
		 */
		do_action( 'salanaz_client_registered', $user_id );

		wp_safe_redirect( add_query_arg( 'registered', '1', self::login_page_url() ) );
		exit;
	}

	/**
	 * Validate the registration input.
	 *
	 * @param array<string, string> $values   Sanitised field values.
	 * @param string                $password Chosen password.
	 * @param string                $confirm  Password confirmation.
	 * @param bool                  $consent  Whether NDPR consent was given.
	 * @return string[] Error messages.
	 */
	private function validate_registration( array $values, string $password, string $confirm, bool $consent ): array {
		$errors = array();

		if ( ! $values['first_name'] || ! $values['last_name'] ) {
			$errors[] = __( 'Please enter both your first and last name.', 'salanaz' );
		}

		if ( ! is_email( $values['email'] ) ) {
			$errors[] = __( 'Please enter a valid email address.', 'salanaz' );
		} elseif ( email_exists( $values['email'] ) ) {
			$errors[] = __( 'An account already exists with that email address. Try signing in instead.', 'salanaz' );
		}

		if ( '' === salanaz_normalize_phone( $values['phone'] ) ) {
			$errors[] = __( 'Please enter a valid Nigerian phone number, for example 0803 123 4567.', 'salanaz' );
		}

		if ( $values['nok_phone'] && '' === salanaz_normalize_phone( $values['nok_phone'] ) ) {
			$errors[] = __( 'The next of kin phone number does not look valid.', 'salanaz' );
		}

		if ( strlen( $password ) < 8 ) {
			$errors[] = __( 'Your password must be at least 8 characters long.', 'salanaz' );
		}

		if ( $password !== $confirm ) {
			$errors[] = __( 'The two passwords do not match.', 'salanaz' );
		}

		if ( ! $consent ) {
			$errors[] = __( 'Please agree to our privacy policy so we can process your registration.', 'salanaz' );
		}

		return $errors;
	}

	/**
	 * Store validation state and bounce back to the form.
	 *
	 * @param string[]              $errors   Error messages.
	 * @param array<string, string> $values   Submitted values to repopulate.
	 * @param string                $redirect Where to send the user.
	 */
	private function fail( array $errors, array $values, string $redirect ): void {
		unset( $values['password'], $values['password_confirm'] );

		set_transient(
			$this->submission_key(),
			array(
				'errors' => $errors,
				'values' => $values,
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Derive a unique login from an email address.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function unique_login_from_email( string $email ): string {
		$base  = sanitize_user( strstr( $email, '@', true ) ?: 'client', true );
		$base  = $base ?: 'client';
		$login = $base;
		$n     = 1;

		while ( username_exists( $login ) ) {
			++$n;
			$login = $base . $n;
		}

		return $login;
	}
}
