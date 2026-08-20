<?php
/**
 * Single page.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<header class="slz-pagehead">
		<div class="slz-container">
			<h1><?php the_title(); ?></h1>
		</div>
	</header>

	<div class="slz-container slz-container--narrow slz-section">
		<div class="slz-prose">
			<?php
			the_content();

			wp_link_pages(
				array(
					'before' => '<div class="slz-linkpages">',
					'after'  => '</div>',
				)
			);
			?>
		</div>
	</div>

	<?php
endwhile;

get_footer();
