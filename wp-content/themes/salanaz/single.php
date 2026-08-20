<?php
/**
 * Single blog post.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<header class="slz-pagehead">
		<div class="slz-container slz-container--narrow">
			<nav class="slz-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'salanaz' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'salanaz' ); ?></a>
				<span aria-hidden="true">/</span>
				<?php $blog = get_option( 'page_for_posts' ); ?>
				<a href="<?php echo esc_url( $blog ? get_permalink( $blog ) : home_url( '/' ) ); ?>">
					<?php esc_html_e( 'News', 'salanaz' ); ?>
				</a>
			</nav>

			<h1><?php the_title(); ?></h1>
			<p class="slz-pagehead__meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
			</p>
		</div>
	</header>

	<div class="slz-container slz-container--narrow slz-section">
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="slz-featured"><?php the_post_thumbnail( 'salanaz-hero' ); ?></figure>
		<?php endif; ?>

		<div class="slz-prose">
			<?php the_content(); ?>
		</div>

		<?php
		the_post_navigation(
			array(
				'prev_text' => '<span class="slz-postnav__label">' . esc_html__( 'Previous', 'salanaz' ) . '</span> %title',
				'next_text' => '<span class="slz-postnav__label">' . esc_html__( 'Next', 'salanaz' ) . '</span> %title',
			)
		);
		?>
	</div>

	<?php
endwhile;

get_footer();
