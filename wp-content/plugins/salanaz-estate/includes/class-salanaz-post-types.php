<?php
/**
 * Custom post types and taxonomies for the inventory.
 *
 * Estates and plots are post types (not custom tables) because they need a
 * public-facing side: archives, single templates, galleries, SEO and the media
 * library. Transactional data stays in custom tables — see Salanaz_Schema.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Estate and Plot post types plus their taxonomies.
 */
class Salanaz_Post_Types {

	public const ESTATE = 'salanaz_estate';
	public const PLOT   = 'salanaz_plot';

	public const TAX_LOCATION = 'salanaz_location';
	public const TAX_AMENITY  = 'salanaz_amenity';

	/* Plot availability. */
	public const STATUS_AVAILABLE = 'available';
	public const STATUS_RESERVED  = 'reserved';
	public const STATUS_SOLD      = 'sold';

	/* Meta keys — estate. */
	public const META_ESTATE_ADDRESS   = '_salanaz_address';
	public const META_ESTATE_LAT       = '_salanaz_latitude';
	public const META_ESTATE_LNG       = '_salanaz_longitude';
	public const META_ESTATE_TITLE_DOC = '_salanaz_title_document';
	public const META_ESTATE_GALLERY   = '_salanaz_gallery_ids';

	/* Meta keys — plot. */
	public const META_PLOT_ESTATE      = '_salanaz_estate_id';
	public const META_PLOT_NUMBER      = '_salanaz_plot_number';
	public const META_PLOT_SIZE_SQM    = '_salanaz_size_sqm';
	public const META_PLOT_PRICE       = '_salanaz_price';
	public const META_PLOT_STATUS      = '_salanaz_status';
	public const META_PLOT_PLANS       = '_salanaz_payment_plans';
	public const META_PLOT_LAYOUT      = '_salanaz_layout_image';
	public const META_PLOT_RESERVED_BY = '_salanaz_reserved_by';
	public const META_PLOT_OWNER       = '_salanaz_owner_id';

	/**
	 * Wire up registration.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register the Estate and Plot post types.
	 */
	public function register_post_types(): void {
		$manage = Salanaz_Roles::CAP_MANAGE_INVENTORY;

		register_post_type(
			self::ESTATE,
			array(
				'labels'             => array(
					'name'               => __( 'Estates', 'salanaz' ),
					'singular_name'      => __( 'Estate', 'salanaz' ),
					'add_new_item'       => __( 'Add New Estate', 'salanaz' ),
					'edit_item'          => __( 'Edit Estate', 'salanaz' ),
					'search_items'       => __( 'Search Estates', 'salanaz' ),
					'not_found'          => __( 'No estates found.', 'salanaz' ),
					'menu_name'          => __( 'Estates', 'salanaz' ),
				),
				'public'             => true,
				'has_archive'        => 'estates',
				'rewrite'            => array(
					'slug'       => 'estates',
					'with_front' => false,
				),
				'menu_icon'          => 'dashicons-admin-multisite',
				'menu_position'      => 26,
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'show_in_rest'       => true,
				'capability_type'    => array( 'salanaz_estate', 'salanaz_estates' ),
				'map_meta_cap'       => true,
				'capabilities'       => $this->inventory_capabilities( $manage ),
			)
		);

		register_post_type(
			self::PLOT,
			array(
				'labels'          => array(
					'name'          => __( 'Plots', 'salanaz' ),
					'singular_name' => __( 'Plot', 'salanaz' ),
					'add_new_item'  => __( 'Add New Plot', 'salanaz' ),
					'edit_item'     => __( 'Edit Plot', 'salanaz' ),
					'search_items'  => __( 'Search Plots', 'salanaz' ),
					'not_found'     => __( 'No plots found.', 'salanaz' ),
					'menu_name'     => __( 'Plots', 'salanaz' ),
				),
				'public'          => true,
				'has_archive'     => 'plots',
				'rewrite'         => array(
					'slug'       => 'plots',
					'with_front' => false,
				),
				'menu_icon'       => 'dashicons-grid-view',
				'menu_position'   => 27,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'revisions' ),
				'show_in_rest'    => true,
				'capability_type' => array( 'salanaz_plot', 'salanaz_plots' ),
				'map_meta_cap'    => true,
				'capabilities'    => $this->inventory_capabilities( $manage ),
			)
		);
	}

	/**
	 * Collapse the full post-type capability set onto a single gate.
	 *
	 * Anyone who can manage inventory can do everything to estates and plots;
	 * everyone else can only read them. This keeps a sales staff member from
	 * editing pricing while still letting them browse inventory.
	 *
	 * Only *primitive* capabilities belong here. The meta capabilities
	 * (edit_post, read_post, delete_post) are derived by WordPress because the
	 * post types set map_meta_cap => true; declaring them would make WordPress
	 * treat them as primitives and check them without a post, which triggers a
	 * _doing_it_wrong notice from map_meta_cap().
	 *
	 * @param string $manage The manage-inventory capability.
	 * @return array<string, string>
	 */
	private function inventory_capabilities( string $manage ): array {
		return array(
			'edit_posts'             => $manage,
			'edit_others_posts'      => $manage,
			'edit_private_posts'     => $manage,
			'edit_published_posts'   => $manage,
			'publish_posts'          => $manage,
			'read_private_posts'     => $manage,
			'delete_posts'           => $manage,
			'delete_others_posts'    => $manage,
			'delete_private_posts'   => $manage,
			'delete_published_posts' => $manage,
			'create_posts'           => $manage,
		);
	}

	/**
	 * Register the Location and Amenity taxonomies.
	 */
	public function register_taxonomies(): void {
		$manage = Salanaz_Roles::CAP_MANAGE_INVENTORY;

		$tax_caps = array(
			'manage_terms' => $manage,
			'edit_terms'   => $manage,
			'delete_terms' => $manage,
			'assign_terms' => $manage,
		);

		register_taxonomy(
			self::TAX_LOCATION,
			array( self::ESTATE ),
			array(
				'labels'            => array(
					'name'          => __( 'Locations', 'salanaz' ),
					'singular_name' => __( 'Location', 'salanaz' ),
					'menu_name'     => __( 'Locations', 'salanaz' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'location' ),
				'capabilities'      => $tax_caps,
			)
		);

		register_taxonomy(
			self::TAX_AMENITY,
			array( self::ESTATE ),
			array(
				'labels'            => array(
					'name'          => __( 'Amenities', 'salanaz' ),
					'singular_name' => __( 'Amenity', 'salanaz' ),
					'menu_name'     => __( 'Amenities', 'salanaz' ),
				),
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'amenity' ),
				'capabilities'      => $tax_caps,
			)
		);
	}

	/**
	 * Register post meta so it is sanitised centrally and exposed to REST.
	 */
	public function register_meta(): void {
		$estate_meta = array(
			self::META_ESTATE_ADDRESS   => array( 'string', 'sanitize_text_field' ),
			self::META_ESTATE_LAT       => array( 'number', null ),
			self::META_ESTATE_LNG       => array( 'number', null ),
			self::META_ESTATE_TITLE_DOC => array( 'string', 'sanitize_text_field' ),
		);

		foreach ( $estate_meta as $key => list( $type, $sanitizer ) ) {
			register_post_meta(
				self::ESTATE,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitizer,
					'auth_callback'     => array( $this, 'can_manage_inventory' ),
				)
			);
		}

		register_post_meta(
			self::ESTATE,
			self::META_ESTATE_GALLERY,
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'items' => array( 'type' => 'integer' ),
					),
				),
				'auth_callback' => array( $this, 'can_manage_inventory' ),
			)
		);

		$plot_meta = array(
			self::META_PLOT_ESTATE   => array( 'integer', array( $this, 'sanitize_int' ) ),
			self::META_PLOT_NUMBER   => array( 'string', 'sanitize_text_field' ),
			self::META_PLOT_SIZE_SQM => array( 'number', array( $this, 'sanitize_float' ) ),
			self::META_PLOT_PRICE    => array( 'number', array( $this, 'sanitize_float' ) ),
			self::META_PLOT_STATUS   => array( 'string', array( $this, 'sanitize_plot_status' ) ),
			self::META_PLOT_LAYOUT   => array( 'integer', array( $this, 'sanitize_int' ) ),
			self::META_PLOT_OWNER    => array( 'integer', array( $this, 'sanitize_int' ) ),
		);

		foreach ( $plot_meta as $key => list( $type, $sanitizer ) ) {
			register_post_meta(
				self::PLOT,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitizer,
					'auth_callback'     => array( $this, 'can_manage_inventory' ),
				)
			);
		}
	}

	/**
	 * Cast a meta value to a float.
	 *
	 * WordPress calls meta sanitizers with four arguments. PHP 8 raises
	 * ArgumentCountError when an internal function such as floatval() is handed
	 * extra arguments, so every numeric sanitizer is wrapped rather than passed
	 * as a bare function name.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public function sanitize_float( $value ): float {
		return (float) $value;
	}

	/**
	 * Cast a meta value to a non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_int( $value ): int {
		return absint( $value );
	}

	/**
	 * Constrain plot status to the known set.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public function sanitize_plot_status( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, self::plot_statuses(), true ) ? $value : self::STATUS_AVAILABLE;
	}

	/**
	 * Valid plot statuses.
	 *
	 * @return string[]
	 */
	public static function plot_statuses(): array {
		return array( self::STATUS_AVAILABLE, self::STATUS_RESERVED, self::STATUS_SOLD );
	}

	/**
	 * Human label for a plot status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		$labels = array(
			self::STATUS_AVAILABLE => __( 'Available', 'salanaz' ),
			self::STATUS_RESERVED  => __( 'Reserved', 'salanaz' ),
			self::STATUS_SOLD      => __( 'Sold', 'salanaz' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}

	/**
	 * Meta auth callback.
	 *
	 * @return bool
	 */
	public function can_manage_inventory(): bool {
		return current_user_can( Salanaz_Roles::CAP_MANAGE_INVENTORY );
	}
}
