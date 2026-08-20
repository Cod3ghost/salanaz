<?php
/**
 * Shared helpers used across the plugin.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Table name for one of the plugin's custom tables.
 *
 * @param string $slug Unprefixed table slug, e.g. 'transactions'.
 * @return string Fully prefixed table name.
 */
function salanaz_table( string $slug ): string {
	global $wpdb;
	return $wpdb->prefix . 'salanaz_' . $slug;
}

/**
 * Format an amount as Naira.
 *
 * @param float|int|string $amount   Amount in Naira (not kobo).
 * @param bool             $decimals Whether to show kobo.
 * @return string
 */
function salanaz_money( $amount, bool $decimals = false ): string {
	return '₦' . number_format( (float) $amount, $decimals ? 2 : 0, '.', ',' );
}

/**
 * Convert Naira to kobo, which is the unit Paystack transacts in.
 *
 * @param float|int|string $naira Amount in Naira.
 * @return int
 */
function salanaz_to_kobo( $naira ): int {
	return (int) round( (float) $naira * 100 );
}

/**
 * Normalise a Nigerian phone number to +234XXXXXXXXXX.
 *
 * Accepts 08012345678, 8012345678, 2348012345678, +234 801 234 5678.
 *
 * @param string $phone Raw input.
 * @return string Normalised number, or an empty string when unparseable.
 */
function salanaz_normalize_phone( string $phone ): string {
	$digits = preg_replace( '/\D+/', '', $phone );

	if ( '' === $digits ) {
		return '';
	}

	// 2348012345678
	if ( str_starts_with( $digits, '234' ) && 13 === strlen( $digits ) ) {
		return '+' . $digits;
	}

	// 08012345678
	if ( str_starts_with( $digits, '0' ) && 11 === strlen( $digits ) ) {
		return '+234' . substr( $digits, 1 );
	}

	// 8012345678
	if ( 10 === strlen( $digits ) ) {
		return '+234' . $digits;
	}

	return '';
}

/**
 * Generate a unique, human-readable reference.
 *
 * @param string $prefix Short type marker, e.g. 'TXN' or 'ALC'.
 * @return string e.g. SLNZ-TXN-20260807-4F2A9C
 */
function salanaz_generate_reference( string $prefix = 'TXN' ): string {
	return sprintf(
		'SLNZ-%s-%s-%s',
		strtoupper( $prefix ),
		gmdate( 'Ymd' ),
		strtoupper( wp_generate_password( 6, false, false ) )
	);
}

/**
 * Whether a user holds one of the Salanaz roles.
 *
 * @param string       $role    Role slug, e.g. Salanaz_Roles::CLIENT.
 * @param int|WP_User|null $user User or ID. Defaults to the current user.
 * @return bool
 */
function salanaz_user_has_role( string $role, $user = null ): bool {
	$user = $user instanceof WP_User ? $user : get_userdata( $user ? (int) $user : get_current_user_id() );

	return $user instanceof WP_User && in_array( $role, (array) $user->roles, true );
}

/**
 * Current account status for a client.
 *
 * @param int|null $user_id User ID. Defaults to the current user.
 * @return string One of pending|approved|rejected|suspended, or '' when unset.
 */
function salanaz_account_status( ?int $user_id = null ): string {
	$user_id = $user_id ?: get_current_user_id();

	return (string) get_user_meta( $user_id, Salanaz_Profile::META_ACCOUNT_STATUS, true );
}

/**
 * Absolute path to the private directory holding payment proofs.
 *
 * These are bank statements and transfer screenshots — financial data that must
 * never be fetchable by URL. Two things protect it:
 *
 * 1. Location. We prefer a directory *above* the web root, which no web server
 *    can serve regardless of its configuration. Only if that is not writable do
 *    we fall back inside wp-content/uploads.
 * 2. Guards. The fallback carries .htaccess, web.config and index.php files.
 *    Note that .htaccess is ignored by nginx and by any non-Apache server, so
 *    the fallback needs an explicit server rule — see the README.
 *
 * The chosen base is recorded in an option so that it stays stable once files
 * have been written into it.
 *
 * @return string Path with no trailing slash.
 */
function salanaz_private_dir(): string {
	// An explicit constant always wins, so a host (or the local dev
	// environment) can pin storage to a path it knows is durable.
	if ( defined( 'SALANAZ_PRIVATE_DIR' ) && SALANAZ_PRIVATE_DIR ) {
		$path = untrailingslashit( (string) SALANAZ_PRIVATE_DIR );

		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
			salanaz_write_directory_guards( $path );
		}

		return $path;
	}

	$stored = get_option( 'salanaz_private_dir_path' );

	if ( $stored && is_string( $stored ) ) {
		return untrailingslashit( $stored );
	}

	$hash = get_option( 'salanaz_private_dir_key' );

	if ( ! $hash ) {
		$hash = wp_generate_password( 20, false, false );
		update_option( 'salanaz_private_dir_key', $hash, false );
	}

	$folder = 'salanaz-private-' . $hash;

	// Preferred: one level above the WordPress root, unreachable by URL.
	$above = untrailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . '/' . $folder;

	// Fallback: inside uploads, guarded by the files written at activation.
	$uploads  = wp_upload_dir();
	$fallback = untrailingslashit( $uploads['basedir'] ) . '/' . $folder;

	$path = salanaz_dir_is_usable( $above ) ? $above : $fallback;

	/**
	 * Filter where payment proofs are stored.
	 *
	 * Set this to a path outside the web root if your host allows it.
	 *
	 * @param string $path Absolute path, no trailing slash.
	 */
	$path = (string) apply_filters( 'salanaz_private_dir', $path );

	update_option( 'salanaz_private_dir_path', $path, false );

	return untrailingslashit( $path );
}

/**
 * Write the deny rules that stop a web server serving a directory.
 *
 * Apache reads .htaccess and IIS reads web.config. Nginx reads neither, so a
 * directory inside the web root additionally needs an explicit server rule —
 * see the README.
 *
 * @param string $dir Absolute path.
 */
function salanaz_write_directory_guards( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$guards = array(
		'.htaccess'  => "Order deny,allow
Deny from all
<IfModule mod_authz_core.c>
	Require all denied
</IfModule>
",
		'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<configuration>
	<system.webServer>
		<authorization>
			<deny users=\"*\" />
		</authorization>
	</system.webServer>
</configuration>
",
		'index.php'  => "<?php
// Silence is golden.
",
	);

	foreach ( $guards as $name => $contents ) {
		$file = $dir . '/' . $name;

		if ( ! file_exists( $file ) ) {
			file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}
}

/**
 * Whether a directory exists (or can be created) and is writable.
 *
 * @param string $path Absolute path.
 * @return bool
 */
function salanaz_dir_is_usable( string $path ): bool {
	if ( is_dir( $path ) ) {
		return wp_is_writable( $path );
	}

	$parent = dirname( $path );

	if ( ! is_dir( $parent ) || ! wp_is_writable( $parent ) ) {
		return false;
	}

	return (bool) wp_mkdir_p( $path );
}

/**
 * Whether payment proofs currently sit inside the web root.
 *
 * When true the site depends on a server rule to block direct access, which
 * .htaccess cannot provide on nginx. Surfaced as an admin warning.
 *
 * @return bool
 */
function salanaz_private_dir_is_web_reachable(): bool {
	$dir  = salanaz_private_dir();
	$root = untrailingslashit( ABSPATH );

	return str_starts_with( wp_normalize_path( $dir ), wp_normalize_path( $root ) );
}
