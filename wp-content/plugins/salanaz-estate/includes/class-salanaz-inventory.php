<?php
/**
 * Inventory query API.
 *
 * The theme never queries plot meta directly — it calls these helpers. That
 * keeps meta keys, status logic and pricing rules inside the plugin so the
 * theme stays replaceable.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read helpers for estates and plots.
 */
class Salanaz_Inventory {

	/**
	 * Fetch estates.
	 *
	 * @param array<string, mixed> $args Overrides for WP_Query.
	 * @return WP_Post[]
	 */
	public static function get_estates( array $args = array() ): array {
		$query = new WP_Query(
			wp_parse_args(
				$args,
				array(
					'post_type'           => Salanaz_Post_Types::ESTATE,
					'posts_per_page'      => 6,
					'post_status'         => 'publish',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'menu_order date',
					'order'               => 'ASC',
				)
			)
		);

		return $query->posts;
	}

	/**
	 * Plots belonging to an estate.
	 *
	 * @param int                  $estate_id Estate post ID.
	 * @param array<string, mixed> $args      Overrides for WP_Query.
	 * @return WP_Post[]
	 */
	public static function get_plots_for_estate( int $estate_id, array $args = array() ): array {
		$query = new WP_Query(
			wp_parse_args(
				$args,
				array(
					'post_type'      => Salanaz_Post_Types::PLOT,
					'posts_per_page' => 50,
					'post_status'    => 'publish',
					'no_found_rows'  => true,
					'orderby'        => 'meta_value_num',
					'meta_key'       => Salanaz_Post_Types::META_PLOT_PRICE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'order'          => 'ASC',
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => Salanaz_Post_Types::META_PLOT_ESTATE,
							'value' => $estate_id,
						),
					),
				)
			)
		);

		return $query->posts;
	}

	/**
	 * Aggregate plot figures for an estate — used for the "from ₦X" line and
	 * the availability pill on estate cards.
	 *
	 * Cached in a transient because it runs for every card in a listing.
	 *
	 * @param int $estate_id Estate post ID.
	 * @return array{total:int, available:int, reserved:int, sold:int, min_price:float, max_price:float, min_size:float, max_size:float}
	 */
	public static function estate_stats( int $estate_id ): array {
		$cache_key = 'salanaz_estate_stats_' . $estate_id;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT
					MAX( CASE WHEN pm.meta_key = %s THEN pm.meta_value END ) AS status,
					MAX( CASE WHEN pm.meta_key = %s THEN pm.meta_value END ) AS price,
					MAX( CASE WHEN pm.meta_key = %s THEN pm.meta_value END ) AS size
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} link
					ON link.post_id = p.ID AND link.meta_key = %s AND link.meta_value = %d
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = %s AND p.post_status = 'publish'
				GROUP BY p.ID",
				Salanaz_Post_Types::META_PLOT_STATUS,
				Salanaz_Post_Types::META_PLOT_PRICE,
				Salanaz_Post_Types::META_PLOT_SIZE_SQM,
				Salanaz_Post_Types::META_PLOT_ESTATE,
				$estate_id,
				Salanaz_Post_Types::PLOT
			)
		);

		$stats = array(
			'total'     => 0,
			'available' => 0,
			'reserved'  => 0,
			'sold'      => 0,
			'min_price' => 0.0,
			'max_price' => 0.0,
			'min_size'  => 0.0,
			'max_size'  => 0.0,
		);

		$prices = array();
		$sizes  = array();

		foreach ( $rows as $row ) {
			++$stats['total'];

			$status = $row->status ?: Salanaz_Post_Types::STATUS_AVAILABLE;

			if ( isset( $stats[ $status ] ) ) {
				++$stats[ $status ];
			}

			if ( $row->price > 0 ) {
				$prices[] = (float) $row->price;
			}

			if ( $row->size > 0 ) {
				$sizes[] = (float) $row->size;
			}
		}

		if ( $prices ) {
			$stats['min_price'] = min( $prices );
			$stats['max_price'] = max( $prices );
		}

		if ( $sizes ) {
			$stats['min_size'] = min( $sizes );
			$stats['max_size'] = max( $sizes );
		}

		set_transient( $cache_key, $stats, 10 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Clear a cached estate summary. Hooked to plot saves.
	 *
	 * @param int $estate_id Estate post ID.
	 */
	public static function flush_estate_stats( int $estate_id ): void {
		delete_transient( 'salanaz_estate_stats_' . $estate_id );
	}

	/**
	 * Site-wide counters for the homepage trust bar.
	 *
	 * @return array{estates:int, plots:int, available:int, sold:int, clients:int}
	 */
	public static function global_stats(): array {
		$cached = get_transient( 'salanaz_global_stats' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$estates = (int) wp_count_posts( Salanaz_Post_Types::ESTATE )->publish;
		$plots   = (int) wp_count_posts( Salanaz_Post_Types::PLOT )->publish;

		$by_status = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.meta_value AS status, COUNT(*) AS total
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish'
				GROUP BY pm.meta_value",
				Salanaz_Post_Types::META_PLOT_STATUS,
				Salanaz_Post_Types::PLOT
			),
			OBJECT_K
		);

		$roles = count_users()['avail_roles'];

		$stats = array(
			'estates'   => $estates,
			'plots'     => $plots,
			'available' => isset( $by_status[ Salanaz_Post_Types::STATUS_AVAILABLE ] ) ? (int) $by_status[ Salanaz_Post_Types::STATUS_AVAILABLE ]->total : 0,
			'sold'      => isset( $by_status[ Salanaz_Post_Types::STATUS_SOLD ] ) ? (int) $by_status[ Salanaz_Post_Types::STATUS_SOLD ]->total : 0,
			'clients'   => (int) ( $roles[ Salanaz_Roles::CLIENT ] ?? 0 ),
		);

		set_transient( 'salanaz_global_stats', $stats, 15 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Price of a plot.
	 *
	 * @param int $plot_id Plot post ID.
	 * @return float
	 */
	public static function plot_price( int $plot_id ): float {
		return (float) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_PRICE, true );
	}

	/**
	 * Size of a plot in square metres.
	 *
	 * @param int $plot_id Plot post ID.
	 * @return float
	 */
	public static function plot_size( int $plot_id ): float {
		return (float) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_SIZE_SQM, true );
	}

	/**
	 * Availability status of a plot.
	 *
	 * @param int $plot_id Plot post ID.
	 * @return string
	 */
	public static function plot_status( int $plot_id ): string {
		$status = (string) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_STATUS, true );

		return $status ?: Salanaz_Post_Types::STATUS_AVAILABLE;
	}

	/**
	 * Estate a plot belongs to.
	 *
	 * @param int $plot_id Plot post ID.
	 * @return WP_Post|null
	 */
	public static function plot_estate( int $plot_id ): ?WP_Post {
		$estate_id = (int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_ESTATE, true );

		if ( ! $estate_id ) {
			return null;
		}

		$estate = get_post( $estate_id );

		return $estate instanceof WP_Post ? $estate : null;
	}

	/**
	 * Register cache invalidation.
	 */
	public function register_hooks(): void {
		add_action( 'save_post_' . Salanaz_Post_Types::PLOT, array( $this, 'on_plot_saved' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'on_post_deleted' ), 10, 2 );
	}

	/**
	 * Bust the parent estate's cached summary when a plot changes.
	 *
	 * @param int $plot_id Plot post ID.
	 */
	public function on_plot_saved( int $plot_id ): void {
		$estate_id = (int) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_ESTATE, true );

		if ( $estate_id ) {
			self::flush_estate_stats( $estate_id );
		}

		delete_transient( 'salanaz_global_stats' );
	}

	/**
	 * Same, on deletion.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_post_deleted( int $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && Salanaz_Post_Types::PLOT === $post->post_type ) {
			$this->on_plot_saved( $post_id );
		}
	}
}
