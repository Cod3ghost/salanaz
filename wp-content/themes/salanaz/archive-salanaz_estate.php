<?php
/**
 * Estates archive.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();

$filters = salanaz_plugin_active() ? Salanaz_Query::current_filters() : array();
$total   = (int) $GLOBALS['wp_query']->found_posts;
?>

<header class="slz-pagehead">
	<div class="slz-container">
		<nav class="slz-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'salanaz' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'salanaz' ); ?></a>
			<span aria-hidden="true">/</span>
			<span><?php esc_html_e( 'Estates', 'salanaz' ); ?></span>
		</nav>

		<h1><?php esc_html_e( 'Our estates', 'salanaz' ); ?></h1>
		<p class="slz-pagehead__lede">
			<?php esc_html_e( 'Fenced, surveyed and documented land across Lagos, Abuja, Enugu and Port Harcourt. Prices update as plots are taken.', 'salanaz' ); ?>
		</p>
	</div>
</header>

<div class="slz-container slz-section">

	<?php if ( salanaz_plugin_active() ) : ?>
		<?php get_template_part( 'template-parts/filter-bar' ); ?>
	<?php endif; ?>

	<p class="slz-resultcount">
		<?php
		printf(
			/* translators: %s: number of estates found */
			esc_html( _n( '%s estate found', '%s estates found', $total, 'salanaz' ) ),
			'<strong>' . esc_html( number_format( $total ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
		?>
	</p>

	<?php if ( have_posts() ) : ?>

		<div class="slz-grid slz-grid--3">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/card', 'estate', array( 'estate' => get_post() ) );
			endwhile;
			?>
		</div>

		<?php
		the_posts_pagination(
			array(
				'mid_size'  => 1,
				'prev_text' => __( 'Previous', 'salanaz' ),
				'next_text' => __( 'Next', 'salanaz' ),
			)
		);
		?>

	<?php else : ?>

		<div class="slz-empty">
			<h2><?php esc_html_e( 'No estates match those filters', 'salanaz' ); ?></h2>
			<p><?php esc_html_e( 'Try widening your budget or plot size, or clear the filters to see everything we have available.', 'salanaz' ); ?></p>
			<a class="slz-btn slz-btn--primary" href="<?php echo esc_url( salanaz_estates_url() ); ?>">
				<?php esc_html_e( 'Show all estates', 'salanaz' ); ?>
			</a>
		</div>

	<?php endif; ?>

</div>

<?php
get_footer();
