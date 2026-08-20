<?php
/**
 * Front-end query filtering for the estate and plot archives.
 *
 * Filter parsing lives in the plugin rather than the theme so that the meta
 * keys and the shape of the query stay with the data model.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the public search filters to the main query.
 */
class Salanaz_Query {

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		add_action( 'pre_get_posts', array( $this, 'filter_archives' ) );
	}

	/**
	 * Read, sanitise and normalise the filter parameters from the request.
	 *
	 * Nonce verification is deliberately absent: these are public, read-only
	 * GET filters on a browse page, not a state-changing form.
	 *
	 * @return array{location:string, min_size:float, max_size:float, min_price:float, max_price:float, status:string, orderby:string}
	 */
	public static function current_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return array(
			'location'  => isset( $_GET['estate_location'] ) ? sanitize_title( wp_unslash( $_GET['estate_location'] ) ) : '',
			'min_size'  => isset( $_GET['min_size'] ) ? (float) $_GET['min_size'] : 0.0,
			'max_size'  => isset( $_GET['max_size'] ) ? (float) $_GET['max_size'] : 0.0,
			'min_price' => isset( $_GET['min_price'] ) ? (float) $_GET['min_price'] : 0.0,
			'max_price' => isset( $_GET['max_price'] ) ? (float) $_GET['max_price'] : 0.0,
			'status'    => isset( $_GET['plot_status'] ) ? sanitize_key( wp_unslash( $_GET['plot_status'] ) ) : '',
			'orderby'   => isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Whether any filter is currently applied.
	 *
	 * @param array<string, mixed>|null $filters Filters, or null to read the request.
	 * @return bool
	 */
	public static function has_filters( ?array $filters = null ): bool {
		$filters = $filters ?? self::current_filters();

		foreach ( $filters as $key => $value ) {
			if ( 'orderby' !== $key && $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Apply filters to the estate and plot archives.
	 *
	 * @param WP_Query $query The query being prepared.
	 */
	public function filter_archives( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$is_estate_archive = $query->is_post_type_archive( Salanaz_Post_Types::ESTATE )
			|| $query->is_tax( Salanaz_Post_Types::TAX_LOCATION );
		$is_plot_archive   = $query->is_post_type_archive( Salanaz_Post_Types::PLOT );

		if ( ! $is_estate_archive && ! $is_plot_archive ) {
			return;
		}

		$filters = self::current_filters();

		if ( $is_estate_archive ) {
			$this->apply_estate_filters( $query, $filters );
			return;
		}

		$this->apply_plot_filters( $query, $filters );
	}

	/**
	 * Estates listing.
	 *
	 * Size and price filters describe plots, not estates, so they are resolved
	 * to the set of estates that own at least one matching plot.
	 *
	 * @param WP_Query             $query   The query.
	 * @param array<string, mixed> $filters Parsed filters.
	 */
	private function apply_estate_filters( WP_Query $query, array $filters ): void {
		$query->set( 'posts_per_page', 12 );
		$query->set( 'orderby', 'menu_order date' );
		$query->set( 'order', 'ASC' );

		if ( $filters['location'] ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => Salanaz_Post_Types::TAX_LOCATION,
						'field'    => 'slug',
						'terms'    => $filters['location'],
					),
				)
			);
		}

		$needs_plot_lookup = $filters['min_size'] || $filters['max_size'] || $filters['min_price'] || $filters['max_price'];

		if ( ! $needs_plot_lookup ) {
			return;
		}

		$estate_ids = self::estate_ids_with_matching_plots( $filters );

		// post__in with an empty array is ignored by WP_Query, which would show
		// everything. Use a sentinel so "no matches" really means no results.
		$query->set( 'post__in', $estate_ids ?: array( 0 ) );
	}

	/**
	 * Plots listing.
	 *
	 * @param WP_Query             $query   The query.
	 * @param array<string, mixed> $filters Parsed filters.
	 */
	private function apply_plot_filters( WP_Query $query, array $filters ): void {
		$query->set( 'posts_per_page', 24 );

		$meta_query = array( 'relation' => 'AND' );

		if ( $filters['min_size'] ) {
			$meta_query[] = array(
				'key'     => Salanaz_Post_Types::META_PLOT_SIZE_SQM,
				'value'   => $filters['min_size'],
				'type'    => 'DECIMAL(15,2)',
				'compare' => '>=',
			);
		}

		if ( $filters['max_size'] ) {
			$meta_query[] = array(
				'key'     => Salanaz_Post_Types::META_PLOT_SIZE_SQM,
				'value'   => $filters['max_size'],
				'type'    => 'DECIMAL(15,2)',
				'compare' => '<=',
			);
		}

		if ( $filters['min_price'] ) {
			$meta_query[] = array(
				'key'     => Salanaz_Post_Types::META_PLOT_PRICE,
				'value'   => $filters['min_price'],
				'type'    => 'DECIMAL(15,2)',
				'compare' => '>=',
			);
		}

		if ( $filters['max_price'] ) {
			$meta_query[] = array(
				'key'     => Salanaz_Post_Types::META_PLOT_PRICE,
				'value'   => $filters['max_price'],
				'type'    => 'DECIMAL(15,2)',
				'compare' => '<=',
			);
		}

		if ( $filters['status'] && in_array( $filters['status'], Salanaz_Post_Types::plot_statuses(), true ) ) {
			$meta_query[] = array(
				'key'   => Salanaz_Post_Types::META_PLOT_STATUS,
				'value' => $filters['status'],
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$query->set( 'meta_query', $meta_query );
		}

		$this->apply_sort( $query, $filters['orderby'] );
	}

	/**
	 * Translate a sort key into query vars.
	 *
	 * @param WP_Query $query   The query.
	 * @param string   $orderby Sort key from the request.
	 */
	private function apply_sort( WP_Query $query, string $orderby ): void {
		switch ( $orderby ) {
			case 'price_asc':
			case 'price_desc':
				$query->set( 'meta_key', Salanaz_Post_Types::META_PLOT_PRICE );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'price_asc' === $orderby ? 'ASC' : 'DESC' );
				break;

			case 'size_asc':
			case 'size_desc':
				$query->set( 'meta_key', Salanaz_Post_Types::META_PLOT_SIZE_SQM );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'size_asc' === $orderby ? 'ASC' : 'DESC' );
				break;

			default:
				$query->set( 'orderby', 'title' );
				$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * IDs of estates owning at least one plot that matches the size/price
	 * filters.
	 *
	 * @param array<string, mixed> $filters Parsed filters.
	 * @return int[]
	 */
	public static function estate_ids_with_matching_plots( array $filters ): array {
		global $wpdb;

		// SQL fragments and their bindings are appended together so the
		// parameter order always matches the placeholder order.
		$joins  = '';
		$where  = array( "p.post_status = 'publish'" );
		$params = array();

		$joins    .= " INNER JOIN {$wpdb->postmeta} link ON link.post_id = p.ID AND link.meta_key = %s";
		$params[]  = Salanaz_Post_Types::META_PLOT_ESTATE;

		if ( $filters['min_size'] || $filters['max_size'] ) {
			$joins   .= " INNER JOIN {$wpdb->postmeta} sz ON sz.post_id = p.ID AND sz.meta_key = %s";
			$params[] = Salanaz_Post_Types::META_PLOT_SIZE_SQM;
		}

		if ( $filters['min_price'] || $filters['max_price'] ) {
			$joins   .= " INNER JOIN {$wpdb->postmeta} pr ON pr.post_id = p.ID AND pr.meta_key = %s";
			$params[] = Salanaz_Post_Types::META_PLOT_PRICE;
		}

		// WHERE placeholders bind after every JOIN placeholder.
		$where[]  = 'p.post_type = %s';
		$params[] = Salanaz_Post_Types::PLOT;

		foreach ( array(
			array( 'min_size', 'sz', '>=' ),
			array( 'max_size', 'sz', '<=' ),
			array( 'min_price', 'pr', '>=' ),
			array( 'max_price', 'pr', '<=' ),
		) as list( $key, $alias, $operator ) ) {
			if ( $filters[ $key ] ) {
				$where[]  = "CAST({$alias}.meta_value AS DECIMAL(15,2)) {$operator} %f";
				$params[] = (float) $filters[ $key ];
			}
		}

		$sql = "SELECT DISTINCT link.meta_value AS estate_id
			FROM {$wpdb->posts} p{$joins}
			WHERE " . implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$params ) );

		return array_map( 'absint', $ids );
	}
}
