<?php
/**
 * Site footer.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;
?>
</main><!-- #slz-content -->

<footer class="slz-footer">
	<div class="slz-container">

		<div class="slz-footer__grid">
			<div>
				<h4><?php bloginfo( 'name' ); ?></h4>
				<p><?php bloginfo( 'description' ); ?></p>
				<p>
					<?php
					$address = get_theme_mod( 'salanaz_office_address' );
					$phone   = get_theme_mod( 'salanaz_office_phone' );
					$email   = get_theme_mod( 'salanaz_office_email' );

					if ( $address ) {
						echo esc_html( $address ) . '<br />';
					}
					if ( $phone ) {
						printf( '<a href="tel:%1$s">%2$s</a><br />', esc_attr( preg_replace( '/\s+/', '', $phone ) ), esc_html( $phone ) );
					}
					if ( $email ) {
						printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $email ) );
					}
					?>
				</p>
			</div>

			<div>
				<h4><?php esc_html_e( 'Company', 'salanaz' ); ?></h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => '__return_empty_string',
					)
				);
				?>
			</div>

			<div>
				<h4><?php esc_html_e( 'Legal', 'salanaz' ); ?></h4>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'legal',
						'container'      => false,
						'depth'          => 1,
						'fallback_cb'    => '__return_empty_string',
					)
				);
				?>
			</div>
		</div>

		<div class="slz-footer__bottom">
			<span>
				<?php
				printf(
					/* translators: 1: year, 2: site name */
					esc_html__( '© %1$s %2$s. All rights reserved.', 'salanaz' ),
					esc_html( gmdate( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</span>
			<span><?php esc_html_e( 'RC Number available on request. NDPR compliant.', 'salanaz' ); ?></span>
		</div>

	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
