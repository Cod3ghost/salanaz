<?php
/**
 * Filter bar for the estates archive.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$filters = Salanaz_Query::current_filters();
$action  = $args['action'] ?? salanaz_estates_url();
?>

<form class="slz-filters" method="get" action="<?php echo esc_url( $action ); ?>">

	<div class="slz-filters__field">
		<label for="f-location"><?php esc_html_e( 'Location', 'salanaz' ); ?></label>
		<select name="estate_location" id="f-location">
			<option value=""><?php esc_html_e( 'All locations', 'salanaz' ); ?></option>
			<?php foreach ( salanaz_active_locations() as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filters['location'], $term->slug ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="slz-filters__field">
		<label for="f-size"><?php esc_html_e( 'Minimum size', 'salanaz' ); ?></label>
		<select name="min_size" id="f-size">
			<option value=""><?php esc_html_e( 'Any size', 'salanaz' ); ?></option>
			<?php
			foreach ( array( 300, 500, 600, 1000 ) as $size ) :
				?>
				<option value="<?php echo esc_attr( (string) $size ); ?>" <?php selected( (int) $filters['min_size'], $size ); ?>>
					<?php
					printf(
						/* translators: %s: plot size in square metres */
						esc_html__( '%s sqm and above', 'salanaz' ),
						esc_html( number_format( $size ) )
					);
					?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="slz-filters__field">
		<label for="f-budget"><?php esc_html_e( 'Maximum budget', 'salanaz' ); ?></label>
		<select name="max_price" id="f-budget">
			<option value=""><?php esc_html_e( 'Any budget', 'salanaz' ); ?></option>
			<?php
			$budgets = array(
				5000000  => __( 'Under ₦5 million', 'salanaz' ),
				10000000 => __( 'Under ₦10 million', 'salanaz' ),
				20000000 => __( 'Under ₦20 million', 'salanaz' ),
				50000000 => __( 'Under ₦50 million', 'salanaz' ),
			);

			foreach ( $budgets as $value => $label ) :
				?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (int) $filters['max_price'], $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="slz-filters__actions">
		<button type="submit" class="slz-btn slz-btn--primary"><?php esc_html_e( 'Apply filters', 'salanaz' ); ?></button>
		<?php if ( Salanaz_Query::has_filters( $filters ) ) : ?>
			<a class="slz-btn slz-btn--ghost" href="<?php echo esc_url( $action ); ?>">
				<?php esc_html_e( 'Clear', 'salanaz' ); ?>
			</a>
		<?php endif; ?>
	</div>

</form>
