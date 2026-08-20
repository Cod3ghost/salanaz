<?php
/**
 * Client and staff profile fields.
 *
 * Next-of-kin and NIN are collected because Nigerian land allocation contracts
 * routinely require them. They are personal data under the NDPR, so they are
 * readable only by the account owner and by users holding
 * CAP_VIEW_ALL_CLIENTS / CAP_VIEW_OWN_CLIENTS.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers user meta and renders the fields on the admin profile screen.
 */
class Salanaz_Profile {

	public const META_ACCOUNT_STATUS = 'salanaz_account_status';
	public const META_PHONE          = 'salanaz_phone';
	public const META_ADDRESS        = 'salanaz_address';
	public const META_CITY           = 'salanaz_city';
	public const META_STATE          = 'salanaz_state';
	public const META_NIN            = 'salanaz_nin';
	public const META_OCCUPATION     = 'salanaz_occupation';
	public const META_NOK_NAME       = 'salanaz_nok_name';
	public const META_NOK_PHONE      = 'salanaz_nok_phone';
	public const META_NOK_RELATION   = 'salanaz_nok_relationship';
	public const META_NOK_ADDRESS    = 'salanaz_nok_address';
	public const META_REGISTERED_AT  = 'salanaz_registered_at';
	public const META_APPROVED_BY    = 'salanaz_approved_by';
	public const META_APPROVED_AT    = 'salanaz_approved_at';
	public const META_REJECT_REASON  = 'salanaz_rejection_reason';
	public const META_CONSENT_NDPR   = 'salanaz_ndpr_consent_at';

	/* Account statuses. */
	public const STATUS_PENDING   = 'pending';
	public const STATUS_APPROVED  = 'approved';
	public const STATUS_REJECTED  = 'rejected';
	public const STATUS_SUSPENDED = 'suspended';

	/**
	 * Wire up registration and admin rendering.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'show_user_profile', array( $this, 'render_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_fields' ) );
	}

	/**
	 * Field definitions: key => [label, input type, sanitizer].
	 *
	 * @return array<string, array{0: string, 1: string, 2: callable|string}>
	 */
	public static function fields(): array {
		return array(
			self::META_PHONE        => array( __( 'Phone number', 'salanaz' ), 'tel', 'salanaz_normalize_phone' ),
			self::META_ADDRESS      => array( __( 'Residential address', 'salanaz' ), 'textarea', 'sanitize_textarea_field' ),
			self::META_CITY         => array( __( 'City / LGA', 'salanaz' ), 'text', 'sanitize_text_field' ),
			self::META_STATE        => array( __( 'State', 'salanaz' ), 'text', 'sanitize_text_field' ),
			self::META_NIN          => array( __( 'NIN / Means of ID', 'salanaz' ), 'text', 'sanitize_text_field' ),
			self::META_OCCUPATION   => array( __( 'Occupation', 'salanaz' ), 'text', 'sanitize_text_field' ),
			self::META_NOK_NAME     => array( __( 'Next of kin — full name', 'salanaz' ), 'text', 'sanitize_text_field' ),
			self::META_NOK_PHONE    => array( __( 'Next of kin — phone', 'salanaz' ), 'tel', 'salanaz_normalize_phone' ),
			self::META_NOK_RELATION => array( __( 'Next of kin — relationship', 'salanaz' ), 'text', 'sanitize_text_field' ),
			self::META_NOK_ADDRESS  => array( __( 'Next of kin — address', 'salanaz' ), 'textarea', 'sanitize_textarea_field' ),
		);
	}

	/**
	 * Register every profile meta key with a sanitizer and an auth callback.
	 */
	public function register_meta(): void {
		foreach ( self::fields() as $key => list( , , $sanitizer ) ) {
			register_meta(
				'user',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitizer,
					'auth_callback'     => array( $this, 'can_read_profile' ),
				)
			);
		}

		register_meta(
			'user',
			self::META_ACCOUNT_STATUS,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => array( $this, 'sanitize_status' ),
				'auth_callback'     => array( $this, 'can_read_profile' ),
			)
		);
	}

	/**
	 * Constrain account status to the known set.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_status( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::statuses(), true ) ? $value : self::STATUS_PENDING;
	}

	/**
	 * Valid account statuses.
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return array( self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_SUSPENDED );
	}

	/**
	 * Human label for an account status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			self::STATUS_PENDING   => __( 'Pending Approval', 'salanaz' ),
			self::STATUS_APPROVED  => __( 'Approved', 'salanaz' ),
			self::STATUS_REJECTED  => __( 'Rejected', 'salanaz' ),
			self::STATUS_SUSPENDED => __( 'Suspended', 'salanaz' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Meta auth callback — the owner, or anyone allowed to view client records.
	 *
	 * @param bool   $allowed   Current decision.
	 * @param string $meta_key  Meta key.
	 * @param int    $object_id User ID.
	 * @return bool
	 */
	public function can_read_profile( $allowed, $meta_key, $object_id ): bool {
		if ( get_current_user_id() === (int) $object_id ) {
			return true;
		}

		return current_user_can( Salanaz_Roles::CAP_VIEW_ALL_CLIENTS )
			|| current_user_can( Salanaz_Roles::CAP_VIEW_OWN_CLIENTS );
	}

	/**
	 * Render the fields on wp-admin's profile screen.
	 *
	 * @param WP_User $user User being edited.
	 */
	public function render_fields( WP_User $user ): void {
		if ( ! $this->can_read_profile( false, '', $user->ID ) ) {
			return;
		}

		$status = get_user_meta( $user->ID, self::META_ACCOUNT_STATUS, true );
		?>
		<h2><?php esc_html_e( 'Salanaz Estate Profile', 'salanaz' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="salanaz_account_status"><?php esc_html_e( 'Account status', 'salanaz' ); ?></label></th>
				<td>
					<?php if ( current_user_can( Salanaz_Roles::CAP_APPROVE_CLIENTS ) ) : ?>
						<select name="<?php echo esc_attr( self::META_ACCOUNT_STATUS ); ?>" id="salanaz_account_status">
							<?php foreach ( self::statuses() as $value ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
									<?php echo esc_html( self::status_label( $value ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<strong><?php echo esc_html( $status ? self::status_label( $status ) : __( 'Not applicable', 'salanaz' ) ); ?></strong>
					<?php endif; ?>
				</td>
			</tr>
			<?php foreach ( self::fields() as $key => list( $label, $type ) ) : ?>
				<?php $value = (string) get_user_meta( $user->ID, $key, true ); ?>
				<tr>
					<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td>
						<?php if ( 'textarea' === $type ) : ?>
							<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" rows="3" class="regular-text"><?php echo esc_textarea( $value ); ?></textarea>
						<?php else : ?>
							<input type="<?php echo esc_attr( $type ); ?>"
								name="<?php echo esc_attr( $key ); ?>"
								id="<?php echo esc_attr( $key ); ?>"
								value="<?php echo esc_attr( $value ); ?>"
								class="regular-text" />
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Persist the profile fields from wp-admin.
	 *
	 * @param int $user_id User being saved.
	 */
	public function save_fields( int $user_id ): void {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// WordPress verifies the update-user_{id} nonce before firing this hook,
		// but check explicitly so the method is safe if called elsewhere.
		check_admin_referer( 'update-user_' . $user_id );

		foreach ( self::fields() as $key => list( , , $sanitizer ) ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			$raw   = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line.
			$value = call_user_func( $sanitizer, (string) $raw );

			update_user_meta( $user_id, $key, $value );
		}

		if ( current_user_can( Salanaz_Roles::CAP_APPROVE_CLIENTS ) && isset( $_POST[ self::META_ACCOUNT_STATUS ] ) ) {
			update_user_meta(
				$user_id,
				self::META_ACCOUNT_STATUS,
				$this->sanitize_status( sanitize_key( wp_unslash( $_POST[ self::META_ACCOUNT_STATUS ] ) ) )
			);
		}
	}
}
