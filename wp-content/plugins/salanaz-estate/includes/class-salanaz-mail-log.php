<?php
/**
 * Development mail log.
 *
 * Local environments rarely have a working mail transport, which makes email
 * automation impossible to confirm by eye. When SALANAZ_DEV_MODE is set, every
 * outgoing message is recorded so it can be inspected under
 * Salanaz -> Automation.
 *
 * This is inert unless the constant is defined, so it never runs in production.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Records outgoing mail while developing.
 */
class Salanaz_Mail_Log {

	private const OPTION = 'salanaz_mail_log';
	private const LIMIT  = 60;

	/**
	 * Whether logging is active.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return defined( 'SALANAZ_DEV_MODE' ) && SALANAZ_DEV_MODE;
	}

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		add_filter( 'wp_mail', array( $this, 'capture' ), 999 );
	}

	/**
	 * Record a message on its way out.
	 *
	 * @param array<string, mixed> $args wp_mail() arguments.
	 * @return array<string, mixed> Unmodified.
	 */
	public function capture( array $args ): array {
		$log = get_option( self::OPTION, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift(
			$log,
			array
			(
				'to'      => is_array( $args['to'] ?? '' ) ? implode( ', ', $args['to'] ) : (string) ( $args['to'] ?? '' ),
				'subject' => (string) ( $args['subject'] ?? '' ),
				'sent_at' => current_time( 'mysql' ),
			)
		);

		update_option( self::OPTION, array_slice( $log, 0, self::LIMIT ), false );

		return $args;
	}

	/**
	 * Recorded messages, newest first.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function entries(): array {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Empty the log.
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
