<?php
/**
 * Estate card.
 *
 * @param WP_Post $args['estate'] The estate post.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

$estate = $args['estate'] ?? null;

if ( ! $estate instanceof WP_Post ) {
	return;
}

$stats     = Salanaz_Inventory::estate_stats( $estate->ID );
$address   = (string) get_post_meta( $estate->ID, Salanaz_Post_Types::META_ESTATE_ADDRESS, true );
$title_doc = (string) get_post_meta( $estate->ID, Salanaz_Post_Types::META_ESTATE_TITLE_DOC, true );
$locations = wp_get_object_terms( $estate->ID, Salanaz_Post_Types::TAX_LOCATION, array( 'fields' => 'names' ) );
?>

<article class="slz-card slz-card--estate">

	<a class="slz-card__media" href="<?php echo esc_url( get_permalink( $estate ) ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo salanaz_card_image( $estate->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( ! is_wp_error( $locations ) && $locations ) : ?>
			<span class="slz-card__badge"><?php echo esc_html( $locations[0] ); ?></span>
		<?php endif; ?>
	</a>

	<div class="slz-card__body">

		<h3 class="slz-card__title">
			<a href="<?php echo esc_url( get_permalink( $estate ) ); ?>"><?php echo esc_html( get_the_title( $estate ) ); ?></a>
		</h3>

		<?php if ( $address ) : ?>
			<p class="slz-card__meta slz-card__meta--pin"><?php echo esc_html( $address ); ?></p>
		<?php endif; ?>

		<?php if ( $stats['min_price'] > 0 ) : ?>
			<p class="slz-card__price">
				<span class="slz-card__price-label"><?php esc_html_e( 'From', 'salanaz' ); ?></span>
				<?php echo esc_html( salanaz_money( $stats['min_price'] ) ); ?>
			</p>
		<?php endif; ?>

		<ul class="slz-card__specs">
			<?php if ( salanaz_size_range( $stats ) ) : ?>
				<li>
					<span class="slz-card__spec-label"><?php esc_html_e( 'Plot sizes', 'salanaz' ); ?></span>
					<span class="slz-card__spec-value"><?php echo esc_html( salanaz_size_range( $stats ) ); ?></span>
				</li>
			<?php endif; ?>
			<?php if ( $title_doc ) : ?>
				<li>
					<span class="slz-card__spec-label"><?php esc_html_e( 'Title', 'salanaz' ); ?></span>
					<span class="slz-card__spec-value"><?php echo esc_html( $title_doc ); ?></span>
				</li>
			<?php endif; ?>
		</ul>

		<div class="slz-card__footer">
			<?php echo salanaz_availability_pill( $stats ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<a class="slz-card__link" href="<?php echo esc_url( get_permalink( $estate ) ); ?>">
				<?php esc_html_e( 'View estate', 'salanaz' ); ?>
				<span aria-hidden="true">&rarr;</span>
			</a>
		</div>

	</div>
</article>
