<?php
/**
 * Fallback template.
 *
 * Batch 1 placeholder — archive, single, page, search and 404 templates are
 * built out in Batch 3.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="slz-container slz-section">
	<?php if ( have_posts() ) : ?>

		<?php if ( is_archive() || is_home() ) : ?>
			<h1><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ?: get_bloginfo( 'name' ) ) ); ?></h1>
		<?php endif; ?>

		<div class="slz-grid slz-grid--3">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'slz-card' ); ?>>
					<div class="slz-card__body">
						<h2 class="slz-card__title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>
						<p class="slz-card__meta"><?php echo esc_html( get_the_date() ); ?></p>
						<div><?php the_excerpt(); ?></div>
					</div>
				</article>
				<?php
			endwhile;
			?>
		</div>

		<?php the_posts_pagination(); ?>

	<?php else : ?>

		<h1><?php esc_html_e( 'Nothing found', 'salanaz' ); ?></h1>
		<p><?php esc_html_e( 'We could not find anything here. Try the estates listing or the search box.', 'salanaz' ); ?></p>
		<?php get_search_form(); ?>

	<?php endif; ?>
</div>

<?php
get_footer();
