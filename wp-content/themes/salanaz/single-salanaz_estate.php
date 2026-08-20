<?php
/**
 * Single estate.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$estate_id = get_the_ID();
	$stats     = salanaz_plugin_active() ? Salanaz_Inventory::estate_stats( $estate_id ) : array();
	$plots     = salanaz_plugin_active() ? Salanaz_Inventory::get_plots_for_estate( $estate_id ) : array();
	$address   = (string) get_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_ADDRESS, true );
	$title_doc = (string) get_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_TITLE_DOC, true );
	$lat       = (float) get_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_LAT, true );
	$lng       = (float) get_post_meta( $estate_id, Salanaz_Post_Types::META_ESTATE_LNG, true );
	$amenities = wp_get_object_terms( $estate_id, Salanaz_Post_Types::TAX_AMENITY, array( 'fields' => 'names' ) );
	$locations = wp_get_object_terms( $estate_id, Salanaz_Post_Types::TAX_LOCATION, array( 'fields' => 'names' ) );
	?>

	<article <?php post_class( 'slz-estate' ); ?>>

		<!-- Hero -->
		<header class="slz-estate__hero">
			<div class="slz-estate__hero-media">
				<?php echo salanaz_card_image( $estate_id, 'salanaz-hero' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<div class="slz-container slz-estate__hero-inner">
				<nav class="slz-breadcrumb slz-breadcrumb--light" aria-label="<?php esc_attr_e( 'Breadcrumb', 'salanaz' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'salanaz' ); ?></a>
					<span aria-hidden="true">/</span>
					<a href="<?php echo esc_url( salanaz_estates_url() ); ?>"><?php esc_html_e( 'Estates', 'salanaz' ); ?></a>
					<span aria-hidden="true">/</span>
					<span><?php the_title(); ?></span>
				</nav>

				<?php if ( ! is_wp_error( $locations ) && $locations ) : ?>
					<span class="slz-estate__location"><?php echo esc_html( implode( ', ', $locations ) ); ?></span>
				<?php endif; ?>

				<h1><?php the_title(); ?></h1>

				<?php if ( $address ) : ?>
					<p class="slz-estate__address"><?php echo esc_html( $address ); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<div class="slz-container slz-section">
			<div class="slz-estate__layout">

				<!-- Main column -->
				<div class="slz-estate__main">

					<?php if ( ! empty( $stats['total'] ) ) : ?>
						<div class="slz-keyfacts">
							<div class="slz-keyfact">
								<span class="slz-keyfact__label"><?php esc_html_e( 'From', 'salanaz' ); ?></span>
								<span class="slz-keyfact__value"><?php echo esc_html( salanaz_money( $stats['min_price'] ) ); ?></span>
							</div>
							<div class="slz-keyfact">
								<span class="slz-keyfact__label"><?php esc_html_e( 'Plot sizes', 'salanaz' ); ?></span>
								<span class="slz-keyfact__value"><?php echo esc_html( salanaz_size_range( $stats ) ); ?></span>
							</div>
							<div class="slz-keyfact">
								<span class="slz-keyfact__label"><?php esc_html_e( 'Available', 'salanaz' ); ?></span>
								<span class="slz-keyfact__value"><?php echo esc_html( number_format( (int) $stats['available'] ) ); ?></span>
							</div>
							<div class="slz-keyfact">
								<span class="slz-keyfact__label"><?php esc_html_e( 'Title', 'salanaz' ); ?></span>
								<span class="slz-keyfact__value slz-keyfact__value--sm"><?php echo esc_html( $title_doc ); ?></span>
							</div>
						</div>
					<?php endif; ?>

					<div class="slz-prose">
						<?php the_content(); ?>
					</div>

					<?php if ( ! is_wp_error( $amenities ) && $amenities ) : ?>
						<section class="slz-block">
							<h2><?php esc_html_e( 'Estate features', 'salanaz' ); ?></h2>
							<ul class="slz-amenities">
								<?php foreach ( $amenities as $amenity ) : ?>
									<li><?php echo esc_html( $amenity ); ?></li>
								<?php endforeach; ?>
							</ul>
						</section>
					<?php endif; ?>

					<!-- Plot availability -->
					<section class="slz-block" id="plots">
						<h2><?php esc_html_e( 'Available plots', 'salanaz' ); ?></h2>

						<?php if ( $plots ) : ?>
							<div class="slz-table-wrap">
								<table class="slz-table">
									<thead>
										<tr>
											<th scope="col"><?php esc_html_e( 'Plot', 'salanaz' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Size', 'salanaz' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Price', 'salanaz' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Status', 'salanaz' ); ?></th>
											<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'salanaz' ); ?></span></th>
										</tr>
									</thead>
									<tbody>
										<?php
										foreach ( $plots as $plot ) :
											$status = Salanaz_Inventory::plot_status( $plot->ID );
											?>
											<tr>
												<th scope="row">
													<?php echo esc_html( (string) get_post_meta( $plot->ID, Salanaz_Post_Types::META_PLOT_NUMBER, true ) ); ?>
												</th>
												<td><?php echo esc_html( number_format( Salanaz_Inventory::plot_size( $plot->ID ) ) . ' sqm' ); ?></td>
												<td class="slz-table__price"><?php echo esc_html( salanaz_money( Salanaz_Inventory::plot_price( $plot->ID ) ) ); ?></td>
												<td>
													<span class="slz-pill slz-pill--<?php echo esc_attr( $status ); ?>">
														<?php echo esc_html( Salanaz_Post_Types::status_label( $status ) ); ?>
													</span>
												</td>
												<td class="slz-table__action">
													<?php if ( Salanaz_Post_Types::STATUS_AVAILABLE === $status ) : ?>
														<a class="slz-btn slz-btn--gold slz-btn--sm"
															href="<?php echo esc_url( add_query_arg( 'plot', $plot->ID, home_url( '/register/' ) ) ); ?>">
															<?php esc_html_e( 'Reserve', 'salanaz' ); ?>
														</a>
													<?php else : ?>
														<span class="slz-table__muted">&mdash;</span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php else : ?>
							<p><?php esc_html_e( 'No plots have been listed for this estate yet. Contact us and we will let you know as soon as they are released.', 'salanaz' ); ?></p>
						<?php endif; ?>
					</section>

					<?php if ( $lat && $lng ) : ?>
						<section class="slz-block">
							<h2><?php esc_html_e( 'Location', 'salanaz' ); ?></h2>
							<p><?php echo esc_html( $address ); ?></p>
							<p class="slz-coords">
								<?php
								printf(
									/* translators: 1: latitude, 2: longitude */
									esc_html__( 'Coordinates: %1$s, %2$s', 'salanaz' ),
									esc_html( (string) $lat ),
									esc_html( (string) $lng )
								);
								?>
							</p>
							<a class="slz-btn slz-btn--ghost"
								href="<?php echo esc_url( sprintf( 'https://www.google.com/maps/search/?api=1&query=%s,%s', $lat, $lng ) ); ?>"
								target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Open in Google Maps', 'salanaz' ); ?>
							</a>
						</section>
					<?php endif; ?>

				</div>

				<!-- Sidebar -->
				<aside class="slz-estate__aside">
					<div class="slz-sidecard">
						<h2 class="slz-sidecard__title"><?php esc_html_e( 'Interested in this estate?', 'salanaz' ); ?></h2>
						<p><?php esc_html_e( 'Create an account to reserve a plot, or book a free site inspection and see the land for yourself.', 'salanaz' ); ?></p>

						<a class="slz-btn slz-btn--gold" href="<?php echo esc_url( home_url( '/register/' ) ); ?>">
							<?php esc_html_e( 'Reserve a plot', 'salanaz' ); ?>
						</a>
						<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
							<?php esc_html_e( 'Book an inspection', 'salanaz' ); ?>
						</a>

						<?php $phone = get_theme_mod( 'salanaz_office_phone' ); ?>
						<?php if ( $phone ) : ?>
							<p class="slz-sidecard__phone">
								<?php esc_html_e( 'Or call', 'salanaz' ); ?>
								<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
							</p>
						<?php endif; ?>
					</div>

					<div class="slz-sidecard slz-sidecard--muted">
						<h2 class="slz-sidecard__title"><?php esc_html_e( 'Verify before you pay', 'salanaz' ); ?></h2>
						<p><?php esc_html_e( 'We encourage every buyer to run an independent search at the state land registry. Ask us for the file — we will hand it over.', 'salanaz' ); ?></p>
					</div>
				</aside>

			</div>
		</div>

	</article>

	<?php
endwhile;

get_footer();
