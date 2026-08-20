<?php
/**
 * Login form.
 *
 * Override by copying to yourtheme/salanaz/auth/login.php
 *
 * @var bool     $registered Whether the visitor just registered.
 * @var string   $redirect   Where to send them after signing in.
 * @var int      $plot_id    Plot they were trying to reserve, if any.
 * @var string[] $errors     Error messages to display.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="slz-auth">

	<?php if ( $registered ) : ?>
		<div class="slz-notice slz-notice--success">
			<strong><?php esc_html_e( 'Registration received.', 'salanaz' ); ?></strong>
			<?php esc_html_e( 'Your account is waiting for approval by our team. We will email you as soon as it is reviewed — you can sign in now to check your status.', 'salanaz' ); ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $errors as $error ) : ?>
		<div class="slz-notice slz-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endforeach; ?>

	<div class="slz-auth__card">
		<h2 class="slz-auth__title"><?php esc_html_e( 'Sign in to your account', 'salanaz' ); ?></h2>
		<p class="slz-auth__lede"><?php esc_html_e( 'Track your payments, see your plot and download your documents.', 'salanaz' ); ?></p>

		<?php
		wp_login_form(
			array(
				'echo'           => true,
				'redirect'       => $redirect,
				'form_id'        => 'salanaz-login',
				'label_username' => __( 'Username or email', 'salanaz' ),
				'label_password' => __( 'Password', 'salanaz' ),
				'label_remember' => __( 'Keep me signed in', 'salanaz' ),
				'label_log_in'   => __( 'Sign in', 'salanaz' ),
				'remember'       => true,
			)
		);
		?>

		<p class="slz-auth__links">
			<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgotten your password?', 'salanaz' ); ?></a>
		</p>
	</div>

	<div class="slz-auth__alt">
		<?php esc_html_e( 'New to Salanaz?', 'salanaz' ); ?>
		<a href="<?php echo esc_url( $plot_id ? add_query_arg( 'plot', $plot_id, Salanaz_Auth::register_page_url() ) : Salanaz_Auth::register_page_url() ); ?>">
			<?php esc_html_e( 'Create an account', 'salanaz' ); ?>
		</a>
	</div>

</div>
