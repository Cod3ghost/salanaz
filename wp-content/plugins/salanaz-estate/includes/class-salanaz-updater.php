<?php
/**
 * Updates from GitHub releases.
 *
 * Lets the theme and plugin update themselves from wp-admin the same way any
 * other theme or plugin does, instead of somebody zipping folders and uploading
 * them by hand.
 *
 * It reads the newest release from the GitHub API and compares its tag with the
 * installed version. The download is the **release asset** we attach in CI, not
 * GitHub's auto-generated source archive — that one unpacks to a folder named
 * after the repository and tag, which would rename the theme directory and
 * break the site on every update.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks GitHub for newer releases and feeds them to the WordPress updater.
 */
class Salanaz_Updater {

	/** How long a release lookup is cached. GitHub allows 60 calls an hour. */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	private const CACHE_KEY = 'salanaz_github_release';

	/** Where releases are published, unless overridden. */
	private const DEFAULT_REPO = 'Cod3ghost/salanaz';

	/** Theme directory name, which must survive an update unchanged. */
	private const THEME_SLUG = 'salanaz';

	/** Release asset filenames produced by the build workflow. */
	private const THEME_ASSET  = 'salanaz-theme.zip';
	private const PLUGIN_ASSET = 'salanaz-estate.zip';

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		if ( '' === self::repository() ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', array( $this, 'check_plugin' ) );
		add_filter( 'site_transient_update_themes', array( $this, 'check_theme' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_details' ), 10, 3 );
		add_filter( 'http_request_args', array( $this, 'authorise_download' ), 10, 2 );
		add_filter( 'upgrader_source_selection', array( $this, 'keep_folder_name' ), 10, 4 );
	}

	/* =====================================================================
	 * Configuration
	 * ================================================================== */

	/**
	 * The repository to check, as `owner/name`.
	 *
	 * @return string Empty when updates are not configured.
	 */
	public static function repository(): string {
		if ( defined( 'SALANAZ_GITHUB_REPO' ) && SALANAZ_GITHUB_REPO ) {
			return trim( (string) SALANAZ_GITHUB_REPO, '/ ' );
		}

		return trim( (string) get_option( 'salanaz_github_repo', self::DEFAULT_REPO ), '/ ' );
	}

	/**
	 * Token for a private repository, if one is configured.
	 *
	 * @return string
	 */
	private static function token(): string {
		if ( defined( 'SALANAZ_GITHUB_TOKEN' ) && SALANAZ_GITHUB_TOKEN ) {
			return (string) SALANAZ_GITHUB_TOKEN;
		}

		return (string) get_option( 'salanaz_github_token', '' );
	}

	/**
	 * Whether updates are switched on.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return '' !== self::repository();
	}

	/* =====================================================================
	 * The GitHub call
	 * ================================================================== */

	/**
	 * The newest release, or null.
	 *
	 * @param bool $force Skip the cache.
	 * @return array<string, mixed>|null
	 */
	public static function latest_release( bool $force = false ): ?array {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );

			if ( is_array( $cached ) ) {
				return $cached;
			}

			// A cached miss is stored as a string so we do not hammer the API
			// every page load when the repo has no releases yet.
			if ( 'none' === $cached ) {
				return null;
			}
		}

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Salanaz-Updater',
		);

		$token = self::token();

		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get(
			sprintf( 'https://api.github.com/repos/%s/releases/latest', self::repository() ),
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_site_transient( self::CACHE_KEY, 'none', HOUR_IN_SECONDS );

			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_site_transient( self::CACHE_KEY, 'none', HOUR_IN_SECONDS );

			return null;
		}

		$release = array(
			'version'   => ltrim( (string) $body['tag_name'], 'vV' ),
			'notes'     => (string) ( $body['body'] ?? '' ),
			'published' => (string) ( $body['published_at'] ?? '' ),
			'url'       => (string) ( $body['html_url'] ?? '' ),
			'assets'    => array(),
		);

		foreach ( (array) ( $body['assets'] ?? array() ) as $asset ) {
			if ( empty( $asset['name'] ) ) {
				continue;
			}

			$release['assets'][ (string) $asset['name'] ] = '' !== self::token()
				? (string) ( $asset['url'] ?? '' )            // API URL, needs auth.
				: (string) ( $asset['browser_download_url'] ?? '' );
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

		return $release;
	}

	/**
	 * Clear the cached release so the next check hits GitHub.
	 */
	public static function flush(): void {
		delete_site_transient( self::CACHE_KEY );
	}

	/* =====================================================================
	 * Feeding the WordPress updater
	 * ================================================================== */

	/**
	 * Offer a plugin update.
	 *
	 * @param mixed $transient The update_plugins transient.
	 * @return mixed
	 */
	public function check_plugin( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::latest_release();

		if ( ! $release || empty( $release['assets'][ self::PLUGIN_ASSET ] ) ) {
			return $transient;
		}

		if ( ! version_compare( $release['version'], SALANAZ_VERSION, '>' ) ) {
			return $transient;
		}

		$basename = plugin_basename( SALANAZ_PLUGIN_FILE );

		$update                = new stdClass();
		$update->slug          = dirname( $basename );
		$update->plugin        = $basename;
		$update->new_version   = $release['version'];
		$update->url           = $release['url'];
		$update->package       = $release['assets'][ self::PLUGIN_ASSET ];
		$update->tested        = get_bloginfo( 'version' );
		$update->requires_php  = '8.0';

		$transient->response[ $basename ] = $update;

		return $transient;
	}

	/**
	 * Offer a theme update.
	 *
	 * @param mixed $transient The update_themes transient.
	 * @return mixed
	 */
	public function check_theme( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$theme = wp_get_theme( self::THEME_SLUG );

		if ( ! $theme->exists() ) {
			return $transient;
		}

		$release = self::latest_release();

		if ( ! $release || empty( $release['assets'][ self::THEME_ASSET ] ) ) {
			return $transient;
		}

		$installed = (string) $theme->get( 'Version' );

		if ( ! version_compare( $release['version'], $installed, '>' ) ) {
			return $transient;
		}

		$transient->response[ self::THEME_SLUG ] = array(
			'theme'       => self::THEME_SLUG,
			'new_version' => $release['version'],
			'url'         => $release['url'],
			'package'     => $release['assets'][ self::THEME_ASSET ],
		);

		return $transient;
	}

	/**
	 * Fill the "View details" panel for the plugin.
	 *
	 * @param mixed  $result The API result.
	 * @param string $action The requested action.
	 * @param object $args   Request arguments.
	 * @return mixed
	 */
	public function plugin_details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = dirname( plugin_basename( SALANAZ_PLUGIN_FILE ) );

		if ( empty( $args->slug ) || $args->slug !== $slug ) {
			return $result;
		}

		$release = self::latest_release();

		if ( ! $release ) {
			return $result;
		}

		$information               = new stdClass();
		$information->name         = 'Salanaz Estate Management';
		$information->slug         = $slug;
		$information->version      = $release['version'];
		$information->requires     = '6.4';
		$information->requires_php = '8.0';
		$information->last_updated = $release['published'];
		$information->sections     = array(
			'description' => esc_html__( 'Estate management for Salanaz Global Services Ltd.', 'salanaz' ),
			'changelog'   => wp_kses_post( wpautop( $release['notes'] ) ),
		);
		$information->download_link = $release['assets'][ self::PLUGIN_ASSET ] ?? '';

		return $information;
	}

	/**
	 * Attach the token when downloading from a private repository.
	 *
	 * GitHub only serves a private asset for an API URL carrying both an
	 * Authorization header and an octet-stream Accept header.
	 *
	 * @param array<string, mixed> $args Request arguments.
	 * @param string               $url  Request URL.
	 * @return array<string, mixed>
	 */
	public function authorise_download( $args, $url ) {
		$token = self::token();

		if ( '' === $token || ! str_contains( (string) $url, 'api.github.com/repos/' . self::repository() ) ) {
			return $args;
		}

		$args['headers'] = array_merge(
			(array) ( $args['headers'] ?? array() ),
			array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/octet-stream',
				'User-Agent'    => 'Salanaz-Updater',
			)
		);

		return $args;
	}

	/**
	 * Make sure an update keeps the original folder name.
	 *
	 * Our release assets already unpack to `salanaz/` and `salanaz-estate/`, but
	 * if somebody ever points this at a source archive the folder would be named
	 * after the tag. Renaming it here keeps the site from breaking.
	 *
	 * @param string       $source        Unpacked source directory.
	 * @param string       $remote_source Root of the unpacked upload.
	 * @param WP_Upgrader  $upgrader      The upgrader.
	 * @param array        $hook_extra    Context.
	 * @return string|WP_Error
	 */
	public function keep_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		$desired = '';

		if ( ! empty( $hook_extra['theme'] ) && self::THEME_SLUG === $hook_extra['theme'] ) {
			$desired = self::THEME_SLUG;
		} elseif ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] === plugin_basename( SALANAZ_PLUGIN_FILE ) ) {
			$desired = dirname( plugin_basename( SALANAZ_PLUGIN_FILE ) );
		}

		if ( '' === $desired ) {
			return $source;
		}

		$corrected = trailingslashit( $remote_source ) . $desired;

		if ( untrailingslashit( $source ) === untrailingslashit( $corrected ) ) {
			return $source;
		}

		if ( $wp_filesystem instanceof WP_Filesystem_Base && $wp_filesystem->move( $source, $corrected ) ) {
			return trailingslashit( $corrected );
		}

		return $source;
	}
}
