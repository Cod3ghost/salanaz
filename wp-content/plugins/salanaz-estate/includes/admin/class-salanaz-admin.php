<?php
/**
 * Co-founder admin screens.
 *
 * Batch 2 covers approvals, staff accounts and assignment. The payment
 * verification queue and analytics are added in Batches 5 and 10.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Salanaz admin menu and handles its form posts.
 */
class Salanaz_Admin {

	private const NONCE = 'salanaz_admin_action';

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_notices', array( $this, 'storage_warning' ) );
		add_action( 'admin_notices', array( $this, 'autoseed_notice' ) );
		add_action( 'admin_post_salanaz_admin_action', array( $this, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Warn when payment proofs are stored somewhere a web server could serve.
	 *
	 * The bundled .htaccess covers Apache, but nginx ignores it entirely, so
	 * this needs a human to confirm the server blocks the path.
	 */
	public function autoseed_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'salanaz_demo_autoseeded' ) ) {
			return;
		}

		delete_transient( 'salanaz_demo_autoseeded' );

		printf(
			'<div class="notice notice-info is-dismissible"><p><strong>%s</strong> %s</p><p>%s</p></div>',
			esc_html__( 'Salanaz added sample content.', 'salanaz' ),
			esc_html__( 'Four example estates and some plots are in place so you can see how everything fits together. Edit them under Estates and Plots, or replace them with your own.', 'salanaz' ),
			sprintf(
				/* translators: %s: settings URL */
				wp_kses_post( __( 'When you are ready, remove it all from <a href="%s">Salanaz &rarr; Settings</a>.', 'salanaz' ) ),
				esc_url( admin_url( 'admin.php?page=salanaz-settings' ) )
			)
		);
	}

	public function storage_warning(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) ) {
			return;
		}

		if ( ! salanaz_private_dir_is_web_reachable() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s<br /><code>%s</code></p></div>',
			esc_html__( 'Salanaz — payment proof storage:', 'salanaz' ),
			esc_html__( 'Uploaded payment proofs are stored inside the web root. Apache and IIS are covered by the bundled rules, but nginx ignores them. Confirm your server denies direct requests to this path, or move it above the web root with the salanaz_private_dir filter.', 'salanaz' ),
			esc_html( salanaz_private_dir() )
		);
	}

	/**
	 * Admin stylesheet.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue( string $hook ): void {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen ? (string) $screen->post_type : '';

		$is_salanaz_screen = str_contains( $hook, 'salanaz' )
			|| in_array( $post_type, array( Salanaz_Post_Types::ESTATE, Salanaz_Post_Types::PLOT ), true );

		if ( ! $is_salanaz_screen ) {
			return;
		}

		$path = SALANAZ_PLUGIN_DIR . 'assets/css/admin.css';

		wp_enqueue_style(
			'salanaz-admin',
			SALANAZ_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : SALANAZ_VERSION
		);
	}

	/**
	 * Register the menu and its subpages.
	 */
	public function register_menu(): void {
		$cap = Salanaz_Roles::CAP_APPROVE_CLIENTS;

		add_menu_page(
			__( 'Salanaz', 'salanaz' ),
			__( 'Salanaz', 'salanaz' ),
			$cap,
			'salanaz-approvals',
			array( $this, 'render_approvals' ),
			'dashicons-building',
			25
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Client Approvals', 'salanaz' ),
			__( 'Approvals', 'salanaz' ),
			$cap,
			'salanaz-approvals',
			array( $this, 'render_approvals' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Payment Verification', 'salanaz' ),
			__( 'Payments', 'salanaz' ),
			Salanaz_Roles::CAP_VERIFY_PAYMENTS,
			'salanaz-payments',
			array( $this, 'render_payments' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Clients', 'salanaz' ),
			__( 'Clients', 'salanaz' ),
			Salanaz_Roles::CAP_VIEW_ALL_CLIENTS,
			'salanaz-clients',
			array( $this, 'render_clients' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Sales Staff', 'salanaz' ),
			__( 'Sales Staff', 'salanaz' ),
			Salanaz_Roles::CAP_MANAGE_STAFF,
			'salanaz-staff',
			array( $this, 'render_staff' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Analytics', 'salanaz' ),
			__( 'Analytics', 'salanaz' ),
			Salanaz_Roles::CAP_VIEW_ANALYTICS,
			'salanaz-analytics',
			array( $this, 'render_analytics' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Automation', 'salanaz' ),
			__( 'Automation', 'salanaz' ),
			Salanaz_Roles::CAP_VIEW_ANALYTICS,
			'salanaz-automation',
			array( $this, 'render_automation' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Settings', 'salanaz' ),
			__( 'Settings', 'salanaz' ),
			'manage_options',
			'salanaz-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'salanaz-approvals',
			__( 'Activity Log', 'salanaz' ),
			__( 'Activity Log', 'salanaz' ),
			Salanaz_Roles::CAP_VIEW_ANALYTICS,
			'salanaz-activity',
			array( $this, 'render_activity' )
		);
	}

	/* =====================================================================
	 * Form handling
	 * ================================================================== */

	/**
	 * Route admin-post submissions to the right operation.
	 */
	public function handle_action(): void {
		check_admin_referer( self::NONCE );

		$action = isset( $_POST['salanaz_action'] ) ? sanitize_key( wp_unslash( $_POST['salanaz_action'] ) ) : '';
		$back   = wp_get_referer() ?: admin_url( 'admin.php?page=salanaz-approvals' );

		switch ( $action ) {
			case 'approve_client':
				$result = Salanaz_Users::approve_client( absint( $_POST['client_id'] ?? 0 ) );
				$this->finish( $result, $back, __( 'Client approved and notified.', 'salanaz' ) );
				break;

			case 'reject_client':
				$result = Salanaz_Users::reject_client(
					absint( $_POST['client_id'] ?? 0 ),
					sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) )
				);
				$this->finish( $result, $back, __( 'Client registration rejected.', 'salanaz' ) );
				break;

			case 'create_staff':
				$result = Salanaz_Users::create_staff(
					array(
						'first_name' => wp_unslash( $_POST['first_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in create_staff().
						'last_name'  => wp_unslash( $_POST['last_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						'email'      => wp_unslash( $_POST['email'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
						'phone'      => wp_unslash( $_POST['phone'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					)
				);
				$this->finish( $result, $back, __( 'Sales staff account created and credentials emailed.', 'salanaz' ) );
				break;

			case 'run_reminders':
				if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_ANALYTICS ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to run this.', 'salanaz' ) ),
						$back,
						''
					);
				}

				$counts = ( new Salanaz_Cron() )->run();
				$this->finish(
					true,
					$back,
					sprintf(
						/* translators: 1: 7-day count, 2: 1-day count, 3: overdue count */
						__( 'Reminder sweep finished: %1$d due in 7 days, %2$d due tomorrow, %3$d overdue.', 'salanaz' ),
						$counts['due_7'],
						$counts['due_1'],
						$counts['overdue']
					)
				);
				break;

			case 'clear_mail_log':
				if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_ANALYTICS ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to do that.', 'salanaz' ) ),
						$back,
						''
					);
				}

				Salanaz_Mail_Log::clear();
				$this->finish( true, $back, __( 'Mail log cleared.', 'salanaz' ) );
				break;

			case 'save_updates':
				if ( ! current_user_can( 'manage_options' ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to change settings.', 'salanaz' ) ),
						$back,
						''
					);
				}

				update_option(
					'salanaz_github_repo',
					sanitize_text_field( wp_unslash( $_POST['github_repo'] ?? '' ) ),
					false
				);
				update_option(
					'salanaz_github_token',
					sanitize_text_field( wp_unslash( $_POST['github_token'] ?? '' ) ),
					false
				);

				Salanaz_Updater::flush();
				$this->finish( true, $back, __( 'Update settings saved.', 'salanaz' ) );
				break;

			case 'check_updates':
				if ( ! current_user_can( 'manage_options' ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to do that.', 'salanaz' ) ),
						$back,
						''
					);
				}

				Salanaz_Updater::flush();
				$release = Salanaz_Updater::latest_release( true );

				// Make WordPress ask again on the next screen load.
				delete_site_transient( 'update_themes' );
				delete_site_transient( 'update_plugins' );

				if ( ! $release ) {
					$this->finish(
						new WP_Error(
							'salanaz_no_release',
							__( 'No release could be read from that repository. Check the name, that a release exists, and that a token is set if the repo is private.', 'salanaz' )
						),
						$back,
						''
					);
				}

				$this->finish(
					true,
					$back,
					sprintf(
						/* translators: 1: released version, 2: installed version */
						__( 'Latest release is %1$s. You are running %2$s.', 'salanaz' ),
						$release['version'],
						SALANAZ_VERSION
					)
				);
				break;

			case 'save_bank':
				if ( ! current_user_can( 'manage_options' ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to change settings.', 'salanaz' ) ),
						$back,
						''
					);
				}

				$bank = array(
					'salanaz_bank_account_name'   => sanitize_text_field( wp_unslash( $_POST['account_name'] ?? '' ) ),
					'salanaz_bank_name'           => sanitize_text_field( wp_unslash( $_POST['bank_name'] ?? '' ) ),
					'salanaz_bank_account_number' => sanitize_text_field( wp_unslash( $_POST['account_number'] ?? '' ) ),
				);

				foreach ( $bank as $option => $value ) {
					update_option( $option, $value, false );
				}

				$this->finish( true, $back, __( 'Bank details saved. Clients will see these on the payment page.', 'salanaz' ) );
				break;

			case 'remove_demo':
				$result = Salanaz_Demo_Data::remove();

				if ( is_wp_error( $result ) ) {
					$this->finish( $result, $back, '' );
				}

				$this->finish(
					true,
					$back,
					sprintf(
						/* translators: 1: posts removed, 2: users removed */
						__( 'Demo content removed: %1$d items and %2$d accounts.', 'salanaz' ),
						$result['posts'],
						$result['users']
					)
				);
				break;

			case 'seed_demo':
				if ( ! current_user_can( 'manage_options' ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to do that.', 'salanaz' ) ),
						$back,
						''
					);
				}

				if ( empty( $_POST['confirm'] ) ) {
					$this->finish(
						new WP_Error( 'salanaz_unconfirmed', __( 'Tick the confirmation box first.', 'salanaz' ) ),
						$back,
						''
					);
				}

				$seeded = Salanaz_Demo_Data::seed();
				$this->finish(
					true,
					$back,
					sprintf(
						/* translators: 1: estates, 2: plots, 3: users */
						__( 'Demo data created: %1$d estates, %2$d plots, %3$d accounts. Every test account uses the password "password" — remove them before going live.', 'salanaz' ),
						$seeded['estates'],
						$seeded['plots'],
						$seeded['users']
					)
				);
				break;

			case 'create_pages':
				if ( ! current_user_can( 'manage_options' ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to do that.', 'salanaz' ) ),
						$back,
						''
					);
				}

				Salanaz_Pages::ensure();
				$this->finish( true, $back, __( 'Required pages checked and created where missing.', 'salanaz' ) );
				break;

			case 'save_paystack':
				if ( ! current_user_can( 'manage_options' ) ) {
					$this->finish(
						new WP_Error( 'salanaz_forbidden', __( 'You are not allowed to change settings.', 'salanaz' ) ),
						$back,
						''
					);
				}

				Salanaz_Paystack::save_settings(
					array(
						'enabled'    => ! empty( $_POST['enabled'] ),
						'test_mode'  => ! empty( $_POST['test_mode'] ),
						'public_key' => wp_unslash( $_POST['public_key'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in save_settings().
						'secret_key' => wp_unslash( $_POST['secret_key'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					)
				);
				$this->finish( true, $back, __( 'Payment settings saved.', 'salanaz' ) );
				break;

			case 'verify_payment':
				$result = Salanaz_Verification::verify( absint( $_POST['txn_id'] ?? 0 ) );
				$this->finish( $result, $back, __( 'Payment verified. The client has been notified.', 'salanaz' ) );
				break;

			case 'reject_payment':
				$result = Salanaz_Verification::reject(
					absint( $_POST['txn_id'] ?? 0 ),
					sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) )
				);
				$this->finish( $result, $back, __( 'Payment rejected. The client has been notified.', 'salanaz' ) );
				break;

			case 'assign_client':
				$result = Salanaz_Users::assign_client(
					absint( $_POST['client_id'] ?? 0 ),
					absint( $_POST['staff_id'] ?? 0 )
				);
				$this->finish( $result, $back, __( 'Client assigned. Both parties have been notified.', 'salanaz' ) );
				break;

			default:
				wp_safe_redirect( $back );
				exit;
		}
	}

	/**
	 * Redirect back with a success or error flag.
	 *
	 * @param mixed  $result  Result from the operation.
	 * @param string $back    Redirect target.
	 * @param string $message Success message.
	 */
	private function finish( $result, string $back, string $message ): void {
		if ( is_wp_error( $result ) ) {
			$back = add_query_arg( 'salanaz_error', rawurlencode( $result->get_error_message() ), $back );
		} else {
			$back = add_query_arg( 'salanaz_ok', rawurlencode( $message ), $back );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Render any queued notice.
	 */
	private function notices(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags.
		if ( isset( $_GET['salanaz_ok'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sanitize_text_field( wp_unslash( $_GET['salanaz_ok'] ) ) )
			);
		}

		if ( isset( $_GET['salanaz_error'] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( sanitize_text_field( wp_unslash( $_GET['salanaz_error'] ) ) )
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Open a form that posts to admin-post.php.
	 *
	 * @param string $action The salanaz_action value.
	 */
	private function form_open( string $action ): void {
		printf(
			'<form method="post" action="%s" class="slz-admin-form">',
			esc_url( admin_url( 'admin-post.php' ) )
		);
		wp_nonce_field( self::NONCE );
		printf( '<input type="hidden" name="action" value="salanaz_admin_action" />' );
		printf( '<input type="hidden" name="salanaz_action" value="%s" />', esc_attr( $action ) );
	}

	/* =====================================================================
	 * Screens
	 * ================================================================== */

	/**
	 * Pending client approvals.
	 */
	public function render_approvals(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_APPROVE_CLIENTS ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$pending = Salanaz_Users::pending_clients();
		$staff   = Salanaz_Users::all_staff();
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Client Approvals', 'salanaz' ); ?></h1>
			<?php $this->notices(); ?>

			<p class="description">
				<?php esc_html_e( 'New registrations wait here until approved. Approving sends a welcome email; you can assign a sales officer at the same time.', 'salanaz' ); ?>
			</p>

			<?php if ( ! $pending ) : ?>
				<div class="slz-admin-empty">
					<p><strong><?php esc_html_e( 'Nothing waiting.', 'salanaz' ); ?></strong></p>
					<p><?php esc_html_e( 'Every registration has been reviewed.', 'salanaz' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Name', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Contact', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Location', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Registered', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Decision', 'salanaz' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending as $client ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $client->display_name ); ?></strong><br />
									<span class="slz-muted"><?php echo esc_html( $client->user_login ); ?></span>
								</td>
								<td>
									<?php echo esc_html( $client->user_email ); ?><br />
									<span class="slz-muted"><?php echo esc_html( (string) get_user_meta( $client->ID, Salanaz_Profile::META_PHONE, true ) ); ?></span>
								</td>
								<td>
									<?php
									$city  = (string) get_user_meta( $client->ID, Salanaz_Profile::META_CITY, true );
									$state = (string) get_user_meta( $client->ID, Salanaz_Profile::META_STATE, true );
									echo esc_html( trim( $city . ( $city && $state ? ', ' : '' ) . $state ) ?: '—' );
									?>
								</td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), $client->user_registered ) ); ?></td>
								<td class="slz-admin-decision">
									<?php $this->form_open( 'approve_client' ); ?>
										<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->ID ); ?>" />
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve', 'salanaz' ); ?></button>
									</form>

									<?php $this->form_open( 'reject_client' ); ?>
										<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->ID ); ?>" />
										<input type="text" name="reason" placeholder="<?php esc_attr_e( 'Reason (optional)', 'salanaz' ); ?>" />
										<button type="submit" class="button"><?php esc_html_e( 'Reject', 'salanaz' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! $staff ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'No sales staff exist yet. Create one before approving clients so they can be assigned an officer.', 'salanaz' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * All clients, with assignment controls.
	 */
	public function render_clients(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_ALL_CLIENTS ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$clients   = Salanaz_Users::all_clients();
		$staff     = Salanaz_Users::all_staff();
		$can_assign = current_user_can( Salanaz_Roles::CAP_ASSIGN_CLIENTS );
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Clients', 'salanaz' ); ?></h1>
			<?php $this->notices(); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Client', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Contact', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sales officer', 'salanaz' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $clients ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No clients yet.', 'salanaz' ); ?></td></tr>
					<?php endif; ?>

					<?php
					foreach ( $clients as $client ) :
						$status   = (string) get_user_meta( $client->ID, Salanaz_Profile::META_ACCOUNT_STATUS, true );
						$assigned = Salanaz_Users::assigned_staff_id( $client->ID );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $client->display_name ); ?></strong><br />
								<span class="slz-muted"><?php echo esc_html( $client->user_login ); ?></span>
							</td>
							<td>
								<?php echo esc_html( $client->user_email ); ?><br />
								<span class="slz-muted"><?php echo esc_html( (string) get_user_meta( $client->ID, Salanaz_Profile::META_PHONE, true ) ); ?></span>
							</td>
							<td>
								<span class="slz-badge slz-badge--<?php echo esc_attr( $status ); ?>">
									<?php echo esc_html( Salanaz_Profile::status_label( $status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! $can_assign || ! $staff ) : ?>
									<?php
									$current = $assigned ? get_userdata( $assigned ) : null;
									echo esc_html( $current instanceof WP_User ? $current->display_name : __( 'Unassigned', 'salanaz' ) );
									?>
								<?php else : ?>
									<?php $this->form_open( 'assign_client' ); ?>
										<input type="hidden" name="client_id" value="<?php echo esc_attr( (string) $client->ID ); ?>" />
										<?php // required, so the placeholder cannot be submitted as an unassign. ?>
										<select name="staff_id" required>
											<option value=""><?php esc_html_e( 'Unassigned', 'salanaz' ); ?></option>
											<?php foreach ( $staff as $member ) : ?>
												<option value="<?php echo esc_attr( (string) $member->ID ); ?>" <?php selected( $assigned, $member->ID ); ?>>
													<?php echo esc_html( $member->display_name ); ?>
												</option>
											<?php endforeach; ?>
										</select>
										<button type="submit" class="button"><?php esc_html_e( 'Save', 'salanaz' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Sales staff list and creation form.
	 */
	public function render_staff(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_MANAGE_STAFF ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$staff = Salanaz_Users::all_staff();
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Sales Staff', 'salanaz' ); ?></h1>
			<?php $this->notices(); ?>

			<p class="description">
				<?php esc_html_e( 'Sales staff cannot register themselves. Creating an account here emails them a one-time password.', 'salanaz' ); ?>
			</p>

			<div class="slz-admin-columns">
				<div>
					<h2><?php esc_html_e( 'Current staff', 'salanaz' ); ?></h2>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', 'salanaz' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Contact', 'salanaz' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Clients', 'salanaz' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! $staff ) : ?>
								<tr><td colspan="3"><?php esc_html_e( 'No sales staff yet.', 'salanaz' ); ?></td></tr>
							<?php endif; ?>

							<?php foreach ( $staff as $member ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $member->display_name ); ?></strong><br />
										<span class="slz-muted"><?php echo esc_html( $member->user_login ); ?></span>
									</td>
									<td>
										<?php echo esc_html( $member->user_email ); ?><br />
										<span class="slz-muted"><?php echo esc_html( (string) get_user_meta( $member->ID, Salanaz_Profile::META_PHONE, true ) ); ?></span>
									</td>
									<td><?php echo esc_html( (string) count( Salanaz_Users::clients_for_staff( $member->ID ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div>
					<h2><?php esc_html_e( 'Add a staff member', 'salanaz' ); ?></h2>
					<?php $this->form_open( 'create_staff' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="s-first"><?php esc_html_e( 'First name', 'salanaz' ); ?></label></th>
								<td><input type="text" name="first_name" id="s-first" class="regular-text" required /></td>
							</tr>
							<tr>
								<th><label for="s-last"><?php esc_html_e( 'Last name', 'salanaz' ); ?></label></th>
								<td><input type="text" name="last_name" id="s-last" class="regular-text" required /></td>
							</tr>
							<tr>
								<th><label for="s-email"><?php esc_html_e( 'Email', 'salanaz' ); ?></label></th>
								<td><input type="email" name="email" id="s-email" class="regular-text" required /></td>
							</tr>
							<tr>
								<th><label for="s-phone"><?php esc_html_e( 'Phone', 'salanaz' ); ?></label></th>
								<td><input type="tel" name="phone" id="s-phone" class="regular-text" placeholder="0803 123 4567" required /></td>
							</tr>
						</table>
						<p>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Create staff account', 'salanaz' ); ?></button>
						</p>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Payment verification queue.
	 */
	public function render_payments(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$queue  = Salanaz_Transactions::awaiting_verification();
		$recent = Salanaz_Transactions::recent( 40 );
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Payment Verification', 'salanaz' ); ?></h1>
			<?php $this->notices(); ?>

			<p class="description">
				<?php esc_html_e( 'Check each uploaded proof against your bank statement before approving. Approving updates the client balance, advances the payment schedule, and marks the plot sold once it is fully paid.', 'salanaz' ); ?>
			</p>

			<h2><?php esc_html_e( 'Awaiting verification', 'salanaz' ); ?></h2>

			<?php if ( ! $queue ) : ?>
				<div class="slz-admin-empty">
					<p><strong><?php esc_html_e( 'Nothing waiting.', 'salanaz' ); ?></strong></p>
					<p><?php esc_html_e( 'Every submitted payment has been reviewed.', 'salanaz' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Client', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Plot', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Amount', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Submitted', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Proof', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Decision', 'salanaz' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $queue as $txn ) :
							$client = get_userdata( (int) $txn->client_id );
							$plot   = get_post( (int) $txn->plot_id );
							?>
							<tr>
								<td>
									<strong><?php echo esc_html( $client instanceof WP_User ? $client->display_name : '#' . (int) $txn->client_id ); ?></strong>
									<span class="slz-muted slz-block-sm"><code><?php echo esc_html( $txn->reference ); ?></code></span>
								</td>
								<td>
									<?php if ( $plot instanceof WP_Post ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $plot->ID ) ); ?>"><?php echo esc_html( get_the_title( $plot ) ); ?></a>
									<?php else : ?>
										&mdash;
									<?php endif; ?>
								</td>
								<td><strong><?php echo esc_html( salanaz_money( (float) $txn->amount ) ); ?></strong></td>
								<td><?php echo esc_html( mysql2date( 'j M Y, g:i a', $txn->created_at ) ); ?></td>
								<td>
									<?php if ( $txn->proof_path ) : ?>
										<a href="<?php echo esc_url( Salanaz_Uploads::proof_url( (int) $txn->id ) ); ?>" target="_blank" rel="noopener">
											<?php esc_html_e( 'View proof', 'salanaz' ); ?>
										</a>
									<?php else : ?>
										<span class="slz-muted"><?php esc_html_e( 'None', 'salanaz' ); ?></span>
									<?php endif; ?>
									<?php if ( $txn->payer_note ) : ?>
										<span class="slz-muted slz-block-sm"><?php echo esc_html( $txn->payer_note ); ?></span>
									<?php endif; ?>
								</td>
								<td class="slz-admin-decision">
									<?php $this->form_open( 'verify_payment' ); ?>
										<input type="hidden" name="txn_id" value="<?php echo esc_attr( (string) $txn->id ); ?>" />
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Verify', 'salanaz' ); ?></button>
									</form>

									<?php $this->form_open( 'reject_payment' ); ?>
										<input type="hidden" name="txn_id" value="<?php echo esc_attr( (string) $txn->id ); ?>" />
										<input type="text" name="reason" placeholder="<?php esc_attr_e( 'Reason (optional)', 'salanaz' ); ?>" />
										<button type="submit" class="button"><?php esc_html_e( 'Reject', 'salanaz' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Recent payments', 'salanaz' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Reference', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Client', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Amount', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Verified by', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Receipt', 'salanaz' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $recent ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No payments recorded yet.', 'salanaz' ); ?></td></tr>
					<?php endif; ?>

					<?php
					foreach ( $recent as $txn ) :
						$client   = get_userdata( (int) $txn->client_id );
						$verifier = $txn->verified_by ? get_userdata( (int) $txn->verified_by ) : null;
						?>
						<tr>
							<td><code><?php echo esc_html( $txn->reference ); ?></code></td>
							<td><?php echo esc_html( $client instanceof WP_User ? $client->display_name : '&mdash;' ); ?></td>
							<td><?php echo esc_html( salanaz_money( (float) $txn->amount ) ); ?></td>
							<td>
								<span class="slz-badge slz-badge--<?php echo esc_attr( Salanaz_Transactions::status_class( $txn->status ) ); ?>">
									<?php echo esc_html( Salanaz_Transactions::status_label( $txn->status ) ); ?>
								</span>
								<?php if ( $txn->rejection_reason ) : ?>
									<span class="slz-muted slz-block-sm"><?php echo esc_html( $txn->rejection_reason ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $verifier instanceof WP_User ? $verifier->display_name : '&mdash;' ); ?></td>
							<td>
								<?php if ( $txn->receipt_path ) : ?>
									<a href="<?php echo esc_url( Salanaz_Documents::document_url( 'receipt', (int) $txn->id ) ); ?>" target="_blank" rel="noopener">
										<?php esc_html_e( 'PDF', 'salanaz' ); ?>
									</a>
								<?php else : ?>
									<span class="slz-muted">&mdash;</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Gateway settings.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$settings = Salanaz_Paystack::settings();
		$has_const = defined( 'SALANAZ_PAYSTACK_SECRET' ) && SALANAZ_PAYSTACK_SECRET;
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Salanaz Settings', 'salanaz' ); ?></h1>
			<?php $this->notices(); ?>

			<h2><?php esc_html_e( 'Updates', 'salanaz' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Point this at your GitHub repository and new versions appear under Dashboard → Updates, like any other theme or plugin. No zipping, no uploading.', 'salanaz' ); ?>
			</p>

			<?php $repo_const = defined( 'SALANAZ_GITHUB_REPO' ) && SALANAZ_GITHUB_REPO; ?>

			<?php if ( $repo_const ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'The repository is set in wp-config.php, so the field below is ignored.', 'salanaz' ); ?></p>
				</div>
			<?php endif; ?>

			<?php $this->form_open( 'save_updates' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gh-repo"><?php esc_html_e( 'Repository', 'salanaz' ); ?></label></th>
						<td>
							<input type="text" id="gh-repo" name="github_repo" class="regular-text code"
								value="<?php echo esc_attr( (string) get_option( 'salanaz_github_repo', '' ) ); ?>"
								placeholder="your-name/salanaz" />
							<p class="description"><?php esc_html_e( 'In the form owner/repository. Leave empty to switch updates off.', 'salanaz' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gh-token"><?php esc_html_e( 'Access token', 'salanaz' ); ?></label></th>
						<td>
							<input type="password" id="gh-token" name="github_token" class="regular-text code"
								value="<?php echo esc_attr( (string) get_option( 'salanaz_github_token', '' ) ); ?>"
								autocomplete="off" placeholder="ghp_..." />
							<p class="description">
								<?php esc_html_e( 'Only needed for a private repository. A fine-grained token with read access to Contents is enough.', 'salanaz' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Installed', 'salanaz' ); ?></th>
						<td>
							<code><?php echo esc_html( SALANAZ_VERSION ); ?></code>
							<?php
							$theme_version = wp_get_theme( 'salanaz' )->get( 'Version' );

							if ( $theme_version ) {
								printf(
									' &nbsp; %s <code>%s</code>',
									esc_html__( 'Theme:', 'salanaz' ),
									esc_html( (string) $theme_version )
								);
							}
							?>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save update settings', 'salanaz' ); ?></button></p>
			</form>

			<?php if ( Salanaz_Updater::is_configured() ) : ?>
				<?php $this->form_open( 'check_updates' ); ?>
					<p><button type="submit" class="button"><?php esc_html_e( 'Check for updates now', 'salanaz' ); ?></button></p>
				</form>
			<?php endif; ?>

			<hr />

			<h2><?php esc_html_e( 'Bank details', 'salanaz' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Shown to clients on the payment page when they pay by transfer. Use your corporate account — never an individual one.', 'salanaz' ); ?>
			</p>

			<?php $this->form_open( 'save_bank' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bank-acct-name"><?php esc_html_e( 'Account name', 'salanaz' ); ?></label></th>
						<td><input type="text" id="bank-acct-name" name="account_name" class="regular-text"
							value="<?php echo esc_attr( (string) get_option( 'salanaz_bank_account_name', '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bank-name"><?php esc_html_e( 'Bank', 'salanaz' ); ?></label></th>
						<td><input type="text" id="bank-name" name="bank_name" class="regular-text"
							value="<?php echo esc_attr( (string) get_option( 'salanaz_bank_name', '' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="bank-acct-no"><?php esc_html_e( 'Account number', 'salanaz' ); ?></label></th>
						<td><input type="text" id="bank-acct-no" name="account_number" class="regular-text"
							value="<?php echo esc_attr( (string) get_option( 'salanaz_bank_account_number', '' ) ); ?>" /></td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save bank details', 'salanaz' ); ?></button></p>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Paystack', 'salanaz' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Card, bank transfer and USSD payments. Clients can always pay by bank transfer and upload proof, whether or not this is switched on.', 'salanaz' ); ?>
			</p>

			<?php if ( $has_const ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'A secret key is defined in wp-config.php. That value is used and the field below is ignored.', 'salanaz' ); ?></p>
				</div>
			<?php endif; ?>

			<?php $this->form_open( 'save_paystack' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable card payment', 'salanaz' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
								<?php esc_html_e( 'Offer Paystack checkout on the payment page', 'salanaz' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Mode', 'salanaz' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="test_mode" value="1" <?php checked( ! empty( $settings['test_mode'] ) ); ?> />
								<?php esc_html_e( 'Test mode (use your sk_test / pk_test keys)', 'salanaz' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pk"><?php esc_html_e( 'Public key', 'salanaz' ); ?></label></th>
						<td><input type="text" id="pk" name="public_key" class="regular-text code"
							value="<?php echo esc_attr( (string) $settings['public_key'] ); ?>" placeholder="pk_test_..." /></td>
					</tr>
					<tr>
						<th scope="row"><label for="sk"><?php esc_html_e( 'Secret key', 'salanaz' ); ?></label></th>
						<td>
							<input type="password" id="sk" name="secret_key" class="regular-text code"
								value="<?php echo esc_attr( (string) $settings['secret_key'] ); ?>" placeholder="sk_test_..." autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Better still, define SALANAZ_PAYSTACK_SECRET in wp-config.php so it never sits in the database.', 'salanaz' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'salanaz' ); ?></th>
						<td>
							<code><?php echo esc_html( Salanaz_Paystack::webhook_url() ); ?></code>
							<p class="description">
								<?php esc_html_e( 'Paste this into Paystack → Settings → API Keys & Webhooks. Payments are only credited from a correctly signed webhook or a direct verify call.', 'salanaz' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'salanaz' ); ?></button></p>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Setup', 'salanaz' ); ?></h2>

			<h3><?php esc_html_e( 'Required pages', 'salanaz' ); ?></h3>
			<?php $problems = Salanaz_Pages::problems(); ?>

			<?php if ( ! $problems ) : ?>
				<p><?php esc_html_e( 'All required pages are present.', 'salanaz' ); ?></p>
			<?php else : ?>
				<div class="notice notice-error inline">
					<p><?php esc_html_e( 'Something is missing:', 'salanaz' ); ?></p>
					<ul style="list-style:disc;margin-left:20px;">
						<?php foreach ( $problems as $problem ) : ?>
							<li><code>/<?php echo esc_html( $problem['slug'] ); ?>/</code> — <?php echo esc_html( $problem['reason'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php $this->form_open( 'create_pages' ); ?>
				<p><button type="submit" class="button"><?php esc_html_e( 'Check and create pages', 'salanaz' ); ?></button></p>
			</form>

			<h3><?php esc_html_e( 'Demo data', 'salanaz' ); ?></h3>
			<div class="notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'For testing only.', 'salanaz' ); ?></strong>
					<?php esc_html_e( 'This creates fictional estates and plots, and test accounts that all share the password "password". Never leave it on a site real clients can reach.', 'salanaz' ); ?>
				</p>
			</div>

			<?php if ( get_option( 'salanaz_demo_seeded' ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %s: date */
						esc_html__( 'Demo data was last created on %s. Running it again reuses what is already there.', 'salanaz' ),
						esc_html( mysql2date( 'j M Y, g:i a', (string) get_option( 'salanaz_demo_seeded' ) ) )
					);
					?>
				</p>
			<?php endif; ?>

			<?php $this->form_open( 'seed_demo' ); ?>
				<p>
					<label>
						<input type="checkbox" name="confirm" value="1" />
						<?php esc_html_e( 'I understand this adds fictional inventory and weak test accounts', 'salanaz' ); ?>
					</label>
				</p>
				<p><button type="submit" class="button"><?php esc_html_e( 'Create demo data', 'salanaz' ); ?></button></p>
			</form>

			<?php if ( get_option( 'salanaz_demo_seeded' ) ) : ?>
				<?php $this->form_open( 'remove_demo' ); ?>
					<p>
						<button type="submit" class="button button-link-delete">
							<?php esc_html_e( 'Remove all demo content', 'salanaz' ); ?>
						</button>
					</p>
					<p class="description">
						<?php esc_html_e( 'Deletes only the estates, plots, posts and accounts the seeder created. Anything you added by hand is kept, and the sign-in pages are left in place.', 'salanaz' ); ?>
					</p>
				</form>
			<?php endif; ?>

			<h3><?php esc_html_e( 'Storage', 'salanaz' ); ?></h3>
			<p>
				<?php esc_html_e( 'Payment proofs and documents are written to:', 'salanaz' ); ?><br />
				<code><?php echo esc_html( salanaz_private_dir() ); ?></code>
			</p>
			<?php if ( salanaz_private_dir_is_web_reachable() ) : ?>
				<p class="description">
					<?php esc_html_e( 'This is inside the web root. Apache and IIS are covered by the bundled rules; on nginx you must add a deny rule yourself.', 'salanaz' ); ?>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'This is above the web root, so no web server can serve it.', 'salanaz' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Analytics and reporting.
	 */
	public function render_analytics(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_ANALYTICS ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$portfolio  = Salanaz_Reports::portfolio();
		$revenue    = Salanaz_Reports::revenue_by_month( 12 );
		$staff      = Salanaz_Reports::sales_by_staff();
		$inventory  = Salanaz_Reports::inventory_by_estate();
		$overdue    = Salanaz_Reports::overdue();
		$pending    = Salanaz_Reports::pending_verification();
		$unassigned = Salanaz_Reports::unassigned_clients();

		$peak = 0.0;

		foreach ( $revenue as $month ) {
			$peak = max( $peak, (float) $month['total'] );
		}

		$collection_rate = $portfolio['contracted'] > 0
			? ( $portfolio['collected'] / $portfolio['contracted'] ) * 100
			: 0.0;
		?>
		<div class="wrap salanaz-admin salanaz-analytics">
			<h1><?php esc_html_e( 'Analytics', 'salanaz' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Money is counted from verified payments only. Outstanding is what is still owed on plots currently held by a client.', 'salanaz' ); ?>
			</p>

			<!-- Headline -->
			<div class="slz-cards">
				<div class="slz-card slz-card--good">
					<span class="slz-card__label"><?php esc_html_e( 'Collected', 'salanaz' ); ?></span>
					<span class="slz-card__value"><?php echo esc_html( salanaz_money( $portfolio['collected'] ) ); ?></span>
					<span class="slz-card__note">
						<?php
						printf(
							/* translators: %s: percentage */
							esc_html__( '%s%% of contracted value', 'salanaz' ),
							esc_html( number_format( $collection_rate, 1 ) )
						);
						?>
					</span>
				</div>
				<div class="slz-card slz-card--warn">
					<span class="slz-card__label"><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></span>
					<span class="slz-card__value"><?php echo esc_html( salanaz_money( $portfolio['outstanding'] ) ); ?></span>
					<span class="slz-card__note"><?php esc_html_e( 'owed on held plots', 'salanaz' ); ?></span>
				</div>
				<div class="slz-card">
					<span class="slz-card__label"><?php esc_html_e( 'Plots sold', 'salanaz' ); ?></span>
					<span class="slz-card__value"><?php echo esc_html( number_format( $portfolio['plots_sold'] ) ); ?></span>
					<span class="slz-card__note">
						<?php
						printf(
							/* translators: 1: reserved, 2: available */
							esc_html__( '%1$d reserved, %2$d available', 'salanaz' ),
							(int) $portfolio['plots_reserved'],
							(int) $portfolio['plots_available']
						);
						?>
					</span>
				</div>
				<div class="slz-card <?php echo $overdue['count'] > 0 ? 'slz-card--bad' : ''; ?>">
					<span class="slz-card__label"><?php esc_html_e( 'Overdue', 'salanaz' ); ?></span>
					<span class="slz-card__value"><?php echo esc_html( salanaz_money( $overdue['value'] ) ); ?></span>
					<span class="slz-card__note">
						<?php
						printf(
							/* translators: %d: number of instalments */
							esc_html( _n( '%d instalment late', '%d instalments late', (int) $overdue['count'], 'salanaz' ) ),
							(int) $overdue['count']
						);
						?>
					</span>
				</div>
			</div>

			<!-- Things needing attention -->
			<?php if ( $pending['count'] > 0 || $unassigned > 0 ) : ?>
				<div class="notice notice-info inline slz-attention">
					<p><strong><?php esc_html_e( 'Needs attention', 'salanaz' ); ?></strong></p>
					<ul>
						<?php if ( $pending['count'] > 0 ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=salanaz-payments' ) ); ?>">
									<?php
									printf(
										/* translators: 1: count, 2: amount */
										esc_html__( '%1$d payment(s) awaiting verification, worth %2$s', 'salanaz' ),
										(int) $pending['count'],
										esc_html( salanaz_money( $pending['value'] ) )
									);
									?>
								</a>
							</li>
						<?php endif; ?>
						<?php if ( $unassigned > 0 ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=salanaz-clients' ) ); ?>">
									<?php
									printf(
										/* translators: %d: count */
										esc_html__( '%d approved client(s) have no sales officer', 'salanaz' ),
										(int) $unassigned
									);
									?>
								</a>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endif; ?>

			<!-- Revenue -->
			<h2><?php esc_html_e( 'Verified revenue, last 12 months', 'salanaz' ); ?></h2>

			<?php if ( $peak <= 0 ) : ?>
				<p class="slz-muted"><?php esc_html_e( 'No verified payments yet.', 'salanaz' ); ?></p>
			<?php else : ?>
				<div class="slz-chart" role="img"
					aria-label="<?php esc_attr_e( 'Verified revenue by month for the last twelve months', 'salanaz' ); ?>">
					<?php foreach ( $revenue as $month ) : ?>
						<?php $height = $peak > 0 ? max( 1, ( $month['total'] / $peak ) * 100 ) : 1; ?>
						<div class="slz-chart__col">
							<span class="slz-chart__amount"><?php echo esc_html( $month['total'] > 0 ? salanaz_money( $month['total'] ) : '' ); ?></span>
							<div class="slz-chart__bar" style="height: <?php echo esc_attr( (string) round( $height ) ); ?>%"></div>
							<span class="slz-chart__label"><?php echo esc_html( $month['label'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="screen-reader-text">
					<?php
					foreach ( $revenue as $month ) {
						printf( '%s: %s. ', esc_html( $month['label'] ), esc_html( salanaz_money( $month['total'] ) ) );
					}
					?>
				</p>
			<?php endif; ?>

			<!-- Staff -->
			<h2><?php esc_html_e( 'Sales by officer', 'salanaz' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Sales officer', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Clients', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Plots held', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Collected', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Outstanding', 'salanaz' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $staff ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No sales staff yet.', 'salanaz' ); ?></td></tr>
					<?php endif; ?>

					<?php foreach ( $staff as $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $row['staff']->display_name ); ?></th>
							<td><?php echo esc_html( (string) $row['clients'] ); ?></td>
							<td><?php echo esc_html( (string) $row['plots'] ); ?></td>
							<td><strong><?php echo esc_html( salanaz_money( $row['collected'] ) ); ?></strong></td>
							<td><?php echo esc_html( salanaz_money( $row['outstanding'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Inventory -->
			<h2><?php esc_html_e( 'Inventory by estate', 'salanaz' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Estate', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Plots', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Available', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Reserved', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Sold', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value unsold', 'salanaz' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $inventory ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No estates published yet.', 'salanaz' ); ?></td></tr>
					<?php endif; ?>

					<?php foreach ( $inventory as $row ) : ?>
						<?php $sold_pct = $row['total'] > 0 ? ( $row['sold'] / $row['total'] ) * 100 : 0; ?>
						<tr>
							<th scope="row">
								<?php echo esc_html( get_the_title( $row['estate'] ) ); ?>
								<span class="slz-muted slz-block-sm">
									<?php
									printf(
										/* translators: %s: percentage */
										esc_html__( '%s%% sold', 'salanaz' ),
										esc_html( number_format( $sold_pct, 0 ) )
									);
									?>
								</span>
							</th>
							<td><?php echo esc_html( (string) $row['total'] ); ?></td>
							<td><span class="slz-badge slz-badge--available"><?php echo esc_html( (string) $row['available'] ); ?></span></td>
							<td><span class="slz-badge slz-badge--reserved"><?php echo esc_html( (string) $row['reserved'] ); ?></span></td>
							<td><span class="slz-badge slz-badge--sold"><?php echo esc_html( (string) $row['sold'] ); ?></span></td>
							<td><?php echo esc_html( salanaz_money( $row['unsold_value'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Overdue detail -->
			<?php if ( $overdue['items'] ) : ?>
				<h2><?php esc_html_e( 'Overdue instalments', 'salanaz' ); ?></h2>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Client', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Sales officer', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Due', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Days late', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Amount', 'salanaz' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $overdue['items'] as $item ) :
							$client = get_userdata( (int) $item->client_id );
							$owner  = Salanaz_Users::assigned_staff( (int) $item->client_id );
							$late   = max( 0, (int) floor( ( time() - strtotime( $item->due_date ) ) / DAY_IN_SECONDS ) );
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $client instanceof WP_User ? $client->display_name : '#' . (int) $item->client_id ); ?></th>
								<td><?php echo esc_html( $owner instanceof WP_User ? $owner->display_name : __( 'Unassigned', 'salanaz' ) ); ?></td>
								<td><?php echo esc_html( mysql2date( 'j M Y', $item->due_date ) ); ?></td>
								<td><?php echo esc_html( (string) $late ); ?></td>
								<td><strong><?php echo esc_html( salanaz_money( (float) $item->amount - (float) $item->amount_paid ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Reminder automation status.
	 */
	public function render_automation(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_ANALYTICS ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$next = Salanaz_Cron::next_run();
		$last = Salanaz_Cron::last_run();
		$wp_cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Automation', 'salanaz' ); ?></h1>
			<?php $this->notices(); ?>

			<p class="description">
				<?php esc_html_e( 'Once a day the system warns clients about instalments due in 7 days and tomorrow, flags anything that has slipped past its due date, and copies overdue cases to the sales officer and management.', 'salanaz' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Next scheduled run', 'salanaz' ); ?></th>
					<td>
						<?php if ( $next ) : ?>
							<strong><?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'j M Y, g:i a' ) ); ?></strong>
						<?php else : ?>
							<span class="slz-muted"><?php esc_html_e( 'Not scheduled — deactivate and reactivate the plugin.', 'salanaz' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Last run', 'salanaz' ); ?></th>
					<td>
						<?php if ( $last['ran_at'] ) : ?>
							<strong><?php echo esc_html( mysql2date( 'j M Y, g:i a', $last['ran_at'] ) ); ?></strong><br />
							<span class="slz-muted">
								<?php
								printf(
									/* translators: 1: 7-day count, 2: 1-day count, 3: overdue count */
									esc_html__( '%1$d due in 7 days, %2$d due tomorrow, %3$d overdue', 'salanaz' ),
									(int) $last['due_7'],
									(int) $last['due_1'],
									(int) $last['overdue']
								);
								?>
							</span>
						<?php else : ?>
							<span class="slz-muted"><?php esc_html_e( 'Never', 'salanaz' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Cron source', 'salanaz' ); ?></th>
					<td>
						<?php if ( $wp_cron_off ) : ?>
							<?php esc_html_e( 'A real server cron is driving this (WP-Cron is disabled). That is the reliable setup.', 'salanaz' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'WP-Cron, which only fires when somebody visits the site. On a quiet day reminders can be late.', 'salanaz' ); ?>
							<p class="description">
								<?php esc_html_e( 'For reliable delivery, set DISABLE_WP_CRON to true in wp-config.php and add a server cron — see the README.', 'salanaz' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php $this->form_open( 'run_reminders' ); ?>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Run the sweep now', 'salanaz' ); ?></button></p>
			</form>

			<?php if ( Salanaz_Mail_Log::is_enabled() ) : ?>
				<h2><?php esc_html_e( 'Outgoing mail (development only)', 'salanaz' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'This log exists because local environments rarely deliver mail. It is inert unless SALANAZ_DEV_MODE is defined.', 'salanaz' ); ?>
				</p>

				<?php $entries = Salanaz_Mail_Log::entries(); ?>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Sent', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'To', 'salanaz' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Subject', 'salanaz' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $entries ) : ?>
							<tr><td colspan="3"><?php esc_html_e( 'Nothing sent yet.', 'salanaz' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'j M, g:i a', $entry['sent_at'] ) ); ?></td>
								<td><?php echo esc_html( $entry['to'] ); ?></td>
								<td><?php echo esc_html( $entry['subject'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $entries ) : ?>
					<?php $this->form_open( 'clear_mail_log' ); ?>
						<p><button type="submit" class="button"><?php esc_html_e( 'Clear log', 'salanaz' ); ?></button></p>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Audit trail.
	 */
	public function render_activity(): void {
		if ( ! current_user_can( Salanaz_Roles::CAP_VIEW_ANALYTICS ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'salanaz' ) );
		}

		$entries = Salanaz_Activity::recent( array( 'limit' => 100 ) );
		?>
		<div class="wrap salanaz-admin">
			<h1><?php esc_html_e( 'Activity Log', 'salanaz' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'An accountability record of who did what, kept for data-protection purposes.', 'salanaz' ); ?>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'When', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'By', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Subject', 'salanaz' ); ?></th>
						<th scope="col"><?php esc_html_e( 'IP', 'salanaz' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $entries ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Nothing recorded yet.', 'salanaz' ); ?></td></tr>
					<?php endif; ?>

					<?php
					foreach ( $entries as $entry ) :
						$actor   = $entry->actor_id ? get_userdata( (int) $entry->actor_id ) : null;
						$subject = $entry->subject_id ? get_userdata( (int) $entry->subject_id ) : null;
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'j M Y, g:i a', $entry->created_at ) ); ?></td>
							<td><code><?php echo esc_html( $entry->action ); ?></code></td>
							<td><?php echo esc_html( $actor instanceof WP_User ? $actor->display_name : '—' ); ?></td>
							<td><?php echo esc_html( $subject instanceof WP_User ? $subject->display_name : '—' ); ?></td>
							<td><span class="slz-muted"><?php echo esc_html( (string) $entry->ip_address ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
