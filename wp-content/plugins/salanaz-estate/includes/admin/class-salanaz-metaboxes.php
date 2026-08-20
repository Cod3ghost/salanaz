<?php
/**
 * Inventory edit screens.
 *
 * Estates and plots carry their commercial data in post meta, which has no UI
 * of its own. These boxes are what make inventory management possible from
 * wp-admin: price, size, availability and which estate a plot belongs to.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes for the Estate and Plot post types.
 */
class Salanaz_Metaboxes {

	private const NONCE = 'salanaz_meta_save';

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post_' . Salanaz_Post_Types::ESTATE, array( $this, 'save_estate' ), 10, 2 );
		add_action( 'save_post_' . Salanaz_Post_Types::PLOT, array( $this, 'save_plot' ), 10, 2 );

		add_filter( 'manage_' . Salanaz_Post_Types::PLOT . '_posts_columns', array( $this, 'plot_columns' ) );
		add_action( 'manage_' . Salanaz_Post_Types::PLOT . '_posts_custom_column', array( $this, 'plot_column' ), 10, 2 );
		add_filter( 'manage_edit-' . Salanaz_Post_Types::PLOT . '_sortable_columns', array( $this, 'plot_sortable' ) );
	}

	/**
	 * Register the boxes.
	 */
	public function register(): void {
		add_meta_box(
			'salanaz_estate_details',
			__( 'Estate details', 'salanaz' ),
			array( $this, 'render_estate' ),
			Salanaz_Post_Types::ESTATE,
			'normal',
			'high'
		);

		add_meta_box(
			'salanaz_plot_details',
			__( 'Plot details', 'salanaz' ),
			array( $this, 'render_plot' ),
			Salanaz_Post_Types::PLOT,
			'normal',
			'high'
		);
	}

	/**
	 * Estate fields.
	 *
	 * @param WP_Post $post The estate.
	 */
	public function render_estate( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, '_salanaz_nonce' );

		$address = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_ESTATE_ADDRESS, true );
		$lat     = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_ESTATE_LAT, true );
		$lng     = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_ESTATE_LNG, true );
		$title   = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_ESTATE_TITLE_DOC, true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="slz-address"><?php esc_html_e( 'Address', 'salanaz' ); ?></label></th>
				<td>
					<input type="text" id="slz-address" name="salanaz_address" class="large-text"
						value="<?php echo esc_attr( $address ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown on the estate page and in listings.', 'salanaz' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="slz-title-doc"><?php esc_html_e( 'Title document', 'salanaz' ); ?></label></th>
				<td>
					<input type="text" id="slz-title-doc" name="salanaz_title_document" class="large-text"
						value="<?php echo esc_attr( $title ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. Certificate of Occupancy (C of O)', 'salanaz' ); ?>" />
					<p class="description"><?php esc_html_e( 'Buyers look for this first. State it exactly.', 'salanaz' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="slz-lat"><?php esc_html_e( 'Coordinates', 'salanaz' ); ?></label></th>
				<td>
					<input type="text" id="slz-lat" name="salanaz_latitude" class="small-text"
						value="<?php echo esc_attr( $lat ); ?>" placeholder="<?php esc_attr_e( 'Latitude', 'salanaz' ); ?>" />
					<input type="text" name="salanaz_longitude" class="small-text"
						value="<?php echo esc_attr( $lng ); ?>" placeholder="<?php esc_attr_e( 'Longitude', 'salanaz' ); ?>" />
					<p class="description"><?php esc_html_e( 'Used for the map link. Leave blank to hide it.', 'salanaz' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Plot fields.
	 *
	 * @param WP_Post $post The plot.
	 */
	public function render_plot( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, '_salanaz_nonce' );

		$estate_id = (int) get_post_meta( $post->ID, Salanaz_Post_Types::META_PLOT_ESTATE, true );
		$number    = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_PLOT_NUMBER, true );
		$size      = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_PLOT_SIZE_SQM, true );
		$price     = (string) get_post_meta( $post->ID, Salanaz_Post_Types::META_PLOT_PRICE, true );
		$status    = Salanaz_Inventory::plot_status( $post->ID );
		$reserved  = (int) get_post_meta( $post->ID, Salanaz_Post_Types::META_PLOT_RESERVED_BY, true );

		$estates = get_posts(
			array(
				'post_type'      => Salanaz_Post_Types::ESTATE,
				'posts_per_page' => 200,
				'post_status'    => 'any',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="slz-estate"><?php esc_html_e( 'Estate', 'salanaz' ); ?></label></th>
				<td>
					<select id="slz-estate" name="salanaz_estate_id" required>
						<option value=""><?php esc_html_e( 'Select an estate', 'salanaz' ); ?></option>
						<?php foreach ( $estates as $estate ) : ?>
							<option value="<?php echo esc_attr( (string) $estate->ID ); ?>" <?php selected( $estate_id, $estate->ID ); ?>>
								<?php echo esc_html( get_the_title( $estate ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'A plot must belong to an estate to appear in listings.', 'salanaz' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="slz-number"><?php esc_html_e( 'Plot number', 'salanaz' ); ?></label></th>
				<td><input type="text" id="slz-number" name="salanaz_plot_number" class="regular-text"
					value="<?php echo esc_attr( $number ); ?>" placeholder="A01" /></td>
			</tr>
			<tr>
				<th><label for="slz-size"><?php esc_html_e( 'Size (sqm)', 'salanaz' ); ?></label></th>
				<td><input type="number" id="slz-size" name="salanaz_size_sqm" class="regular-text"
					step="0.01" min="0" value="<?php echo esc_attr( $size ); ?>" /></td>
			</tr>
			<tr>
				<th><label for="slz-price"><?php esc_html_e( 'Price', 'salanaz' ); ?></label></th>
				<td>
					<input type="number" id="slz-price" name="salanaz_price" class="regular-text"
						step="0.01" min="0" value="<?php echo esc_attr( $price ); ?>" />
					<p class="description">
						<?php echo esc_html( $price ? salanaz_money( (float) $price ) : __( 'Outright price in Naira, before any payment plan.', 'salanaz' ) ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th><label for="slz-status"><?php esc_html_e( 'Availability', 'salanaz' ); ?></label></th>
				<td>
					<select id="slz-status" name="salanaz_status">
						<?php foreach ( Salanaz_Post_Types::plot_statuses() as $value ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
								<?php echo esc_html( Salanaz_Post_Types::status_label( $value ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php if ( $reserved ) : ?>
						<?php $holder = get_userdata( $reserved ); ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: client name */
								esc_html__( 'Currently held by %s. Changing this by hand will not refund or alter their payments.', 'salanaz' ),
								esc_html( $holder instanceof WP_User ? $holder->display_name : (string) $reserved )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Shared guard for both save handlers.
	 *
	 * @param int $post_id Post ID.
	 * @return bool Whether it is safe to save.
	 */
	private function can_save( int $post_id ): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! isset( $_POST['_salanaz_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_salanaz_nonce'] ) ), self::NONCE ) ) {
			return false;
		}

		return current_user_can( Salanaz_Roles::CAP_MANAGE_INVENTORY )
			&& current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Persist estate fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_estate( int $post_id, WP_Post $post ): void {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		$text = array(
			'salanaz_address'        => Salanaz_Post_Types::META_ESTATE_ADDRESS,
			'salanaz_title_document' => Salanaz_Post_Types::META_ESTATE_TITLE_DOC,
		);

		foreach ( $text as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		$coords = array(
			'salanaz_latitude'  => Salanaz_Post_Types::META_ESTATE_LAT,
			'salanaz_longitude' => Salanaz_Post_Types::META_ESTATE_LNG,
		);

		foreach ( $coords as $field => $meta_key ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );

			if ( '' === $raw ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, (float) $raw );
		}
	}

	/**
	 * Persist plot fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_plot( int $post_id, WP_Post $post ): void {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		$previous_estate = (int) get_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_ESTATE, true );

		if ( isset( $_POST['salanaz_estate_id'] ) ) {
			$estate_id = absint( $_POST['salanaz_estate_id'] );

			// Only accept an ID that really is an estate.
			if ( $estate_id && Salanaz_Post_Types::ESTATE === get_post_type( $estate_id ) ) {
				update_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_ESTATE, $estate_id );
			} elseif ( ! $estate_id ) {
				delete_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_ESTATE );
			}
		}

		if ( isset( $_POST['salanaz_plot_number'] ) ) {
			update_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_NUMBER, sanitize_text_field( wp_unslash( $_POST['salanaz_plot_number'] ) ) );
		}

		$numbers = array(
			'salanaz_size_sqm' => Salanaz_Post_Types::META_PLOT_SIZE_SQM,
			'salanaz_price'    => Salanaz_Post_Types::META_PLOT_PRICE,
		);

		foreach ( $numbers as $field => $meta_key ) {
			if ( isset( $_POST[ $field ] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to float.
				update_post_meta( $post_id, $meta_key, max( 0, (float) $_POST[ $field ] ) );
			}
		}

		if ( isset( $_POST['salanaz_status'] ) ) {
			$status = sanitize_key( wp_unslash( $_POST['salanaz_status'] ) );

			if ( in_array( $status, Salanaz_Post_Types::plot_statuses(), true ) ) {
				update_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_STATUS, $status );

				// Releasing a plot should not leave a stale holder behind.
				if ( Salanaz_Post_Types::STATUS_AVAILABLE === $status ) {
					delete_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_RESERVED_BY );
				}
			}
		}

		// Both the old and the new estate need their cached figures cleared.
		$current_estate = (int) get_post_meta( $post_id, Salanaz_Post_Types::META_PLOT_ESTATE, true );

		foreach ( array_unique( array_filter( array( $previous_estate, $current_estate ) ) ) as $estate_id ) {
			Salanaz_Inventory::flush_estate_stats( $estate_id );
		}

		delete_transient( 'salanaz_global_stats' );
	}

	/**
	 * Add useful columns to the plots list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function plot_columns( array $columns ): array {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['salanaz_estate'] = __( 'Estate', 'salanaz' );
				$new['salanaz_size']   = __( 'Size', 'salanaz' );
				$new['salanaz_price']  = __( 'Price', 'salanaz' );
				$new['salanaz_status'] = __( 'Availability', 'salanaz' );
			}
		}

		return $new;
	}

	/**
	 * Render a custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function plot_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'salanaz_estate':
				$estate = Salanaz_Inventory::plot_estate( $post_id );
				echo $estate ? esc_html( get_the_title( $estate ) ) : '&mdash;';
				break;

			case 'salanaz_size':
				$size = Salanaz_Inventory::plot_size( $post_id );
				echo $size ? esc_html( number_format( $size ) . ' sqm' ) : '&mdash;';
				break;

			case 'salanaz_price':
				$price = Salanaz_Inventory::plot_price( $post_id );
				echo $price ? esc_html( salanaz_money( $price ) ) : '&mdash;';
				break;

			case 'salanaz_status':
				$status = Salanaz_Inventory::plot_status( $post_id );
				printf(
					'<span class="slz-badge slz-badge--%s">%s</span>',
					esc_attr( $status ),
					esc_html( Salanaz_Post_Types::status_label( $status ) )
				);
				break;
		}
	}

	/**
	 * Make price and size sortable.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function plot_sortable( array $columns ): array {
		$columns['salanaz_price'] = 'salanaz_price';
		$columns['salanaz_size']  = 'salanaz_size';

		return $columns;
	}
}
