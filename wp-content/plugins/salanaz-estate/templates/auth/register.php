<?php
/**
 * Registration form.
 *
 * Override by copying to yourtheme/salanaz/auth/register.php
 *
 * @var string[]              $errors  Validation errors.
 * @var array<string, string> $values  Previously submitted values.
 * @var int                   $plot_id Plot the visitor wants to reserve.
 * @var string                $nonce   Nonce action name.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Echo a previously submitted value.
 *
 * @param array<string, string> $values Submitted values.
 * @param string                $key    Field key.
 */
$slz_value = static function ( array $values, string $key ): string {
	return esc_attr( $values[ $key ] ?? '' );
};

$plot   = $plot_id ? get_post( $plot_id ) : null;
$estate = $plot_id ? Salanaz_Inventory::plot_estate( $plot_id ) : null;
?>

<div class="slz-auth slz-auth--wide">

	<?php if ( $plot instanceof WP_Post ) : ?>
		<div class="slz-notice slz-notice--info">
			<strong><?php esc_html_e( 'Reserving:', 'salanaz' ); ?></strong>
			<?php echo esc_html( get_the_title( $plot ) ); ?>
			<?php if ( $estate ) : ?>
				&mdash; <?php echo esc_html( salanaz_money( Salanaz_Inventory::plot_price( $plot->ID ) ) ); ?>
			<?php endif; ?>
			<br />
			<?php esc_html_e( 'Create your account below. Once approved, this plot will be waiting in your dashboard.', 'salanaz' ); ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $errors as $error ) : ?>
		<div class="slz-notice slz-notice--error"><?php echo esc_html( $error ); ?></div>
	<?php endforeach; ?>

	<div class="slz-auth__card">
		<h2 class="slz-auth__title"><?php esc_html_e( 'Create your account', 'salanaz' ); ?></h2>
		<p class="slz-auth__lede">
			<?php esc_html_e( 'Registrations are reviewed by our team before activation. It usually takes a few hours during business days.', 'salanaz' ); ?>
		</p>

		<form method="post" action="" class="slz-form" novalidate>
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" name="plot_id" value="<?php echo esc_attr( (string) $plot_id ); ?>" />

			<fieldset class="slz-form__group">
				<legend><?php esc_html_e( 'Your details', 'salanaz' ); ?></legend>

				<div class="slz-form__row">
					<p class="slz-field">
						<label for="first_name"><?php esc_html_e( 'First name', 'salanaz' ); ?> <span class="slz-req">*</span></label>
						<input type="text" name="first_name" id="first_name" value="<?php echo $slz_value( $values, 'first_name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" required autocomplete="given-name" />
					</p>
					<p class="slz-field">
						<label for="last_name"><?php esc_html_e( 'Last name', 'salanaz' ); ?> <span class="slz-req">*</span></label>
						<input type="text" name="last_name" id="last_name" value="<?php echo $slz_value( $values, 'last_name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" required autocomplete="family-name" />
					</p>
				</div>

				<div class="slz-form__row">
					<p class="slz-field">
						<label for="email"><?php esc_html_e( 'Email address', 'salanaz' ); ?> <span class="slz-req">*</span></label>
						<input type="email" name="email" id="email" value="<?php echo $slz_value( $values, 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" required autocomplete="email" />
					</p>
					<p class="slz-field">
						<label for="phone"><?php esc_html_e( 'Phone number', 'salanaz' ); ?> <span class="slz-req">*</span></label>
						<input type="tel" name="phone" id="phone" value="<?php echo $slz_value( $values, 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" required autocomplete="tel" placeholder="0803 123 4567" />
						<span class="slz-field__hint"><?php esc_html_e( 'Nigerian mobile number', 'salanaz' ); ?></span>
					</p>
				</div>
			</fieldset>

			<fieldset class="slz-form__group">
				<legend><?php esc_html_e( 'Where you live', 'salanaz' ); ?></legend>

				<p class="slz-field">
					<label for="address"><?php esc_html_e( 'Residential address', 'salanaz' ); ?></label>
					<textarea name="address" id="address" rows="2" autocomplete="street-address"><?php echo esc_textarea( $values['address'] ?? '' ); ?></textarea>
				</p>

				<div class="slz-form__row">
					<p class="slz-field">
						<label for="city"><?php esc_html_e( 'City / LGA', 'salanaz' ); ?></label>
						<input type="text" name="city" id="city" value="<?php echo $slz_value( $values, 'city' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
					</p>
					<p class="slz-field">
						<label for="state"><?php esc_html_e( 'State', 'salanaz' ); ?></label>
						<input type="text" name="state" id="state" value="<?php echo $slz_value( $values, 'state' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
					</p>
				</div>
			</fieldset>

			<fieldset class="slz-form__group">
				<legend><?php esc_html_e( 'Next of kin', 'salanaz' ); ?></legend>
				<p class="slz-form__note"><?php esc_html_e( 'Required on the allocation contract. You can complete this later from your dashboard.', 'salanaz' ); ?></p>

				<div class="slz-form__row">
					<p class="slz-field">
						<label for="nok_name"><?php esc_html_e( 'Full name', 'salanaz' ); ?></label>
						<input type="text" name="nok_name" id="nok_name" value="<?php echo $slz_value( $values, 'nok_name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
					</p>
					<p class="slz-field">
						<label for="nok_phone"><?php esc_html_e( 'Phone number', 'salanaz' ); ?></label>
						<input type="tel" name="nok_phone" id="nok_phone" value="<?php echo $slz_value( $values, 'nok_phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
					</p>
				</div>
			</fieldset>

			<fieldset class="slz-form__group">
				<legend><?php esc_html_e( 'Choose a password', 'salanaz' ); ?></legend>

				<div class="slz-form__row">
					<p class="slz-field">
						<label for="password"><?php esc_html_e( 'Password', 'salanaz' ); ?> <span class="slz-req">*</span></label>
						<input type="password" name="password" id="password" required autocomplete="new-password" minlength="8" />
						<span class="slz-field__hint"><?php esc_html_e( 'At least 8 characters', 'salanaz' ); ?></span>
					</p>
					<p class="slz-field">
						<label for="password_confirm"><?php esc_html_e( 'Confirm password', 'salanaz' ); ?> <span class="slz-req">*</span></label>
						<input type="password" name="password_confirm" id="password_confirm" required autocomplete="new-password" />
					</p>
				</div>
			</fieldset>

			<?php // Honeypot — hidden from people, tempting to bots. ?>
			<p class="slz-hp" aria-hidden="true">
				<label for="salanaz_website"><?php esc_html_e( 'Leave this field empty', 'salanaz' ); ?></label>
				<input type="text" name="salanaz_website" id="salanaz_website" tabindex="-1" autocomplete="off" />
			</p>

			<p class="slz-field slz-field--check">
				<input type="checkbox" name="ndpr_consent" id="ndpr_consent" value="1" required />
				<label for="ndpr_consent">
					<?php
					$privacy = get_privacy_policy_url();
					printf(
						/* translators: %s: privacy policy link */
						esc_html__( 'I agree that Salanaz may store and process my personal data to manage my account, in line with the %s.', 'salanaz' ),
						$privacy // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							? '<a href="' . esc_url( $privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'salanaz' ) . '</a>'
							: esc_html__( 'privacy policy', 'salanaz' )
					);
					?>
				</label>
			</p>

			<p class="slz-form__submit">
				<button type="submit" name="salanaz_register_submit" value="1" class="slz-btn slz-btn--gold">
					<?php esc_html_e( 'Create my account', 'salanaz' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="slz-auth__alt">
		<?php esc_html_e( 'Already have an account?', 'salanaz' ); ?>
		<a href="<?php echo esc_url( Salanaz_Auth::login_page_url() ); ?>"><?php esc_html_e( 'Sign in', 'salanaz' ); ?></a>
	</div>

</div>
