<?php
/**
 * Payment proof uploads.
 *
 * Proofs are bank statements and transfer screenshots — personal financial
 * data. They never go into the media library and are never served from a
 * guessable URL. They are written to a randomised private directory and can
 * only be read back through an authenticated, capability-checked endpoint.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Validates, stores and serves payment proof files.
 */
class Salanaz_Uploads {

	/** Maximum accepted upload size, in bytes. */
	public const MAX_BYTES = 5242880; // 5 MB.

	/**
	 * Accepted types, mapped to their canonical extension.
	 *
	 * Deliberately narrow: images and PDFs only. No SVG — it can carry script.
	 *
	 * @return array<string, string>
	 */
	public static function allowed_types(): array {
		return array(
			'image/jpeg'      => 'jpg',
			'image/png'       => 'png',
			'image/webp'      => 'webp',
			'application/pdf' => 'pdf',
		);
	}

	/**
	 * Wire up the download endpoint.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'maybe_serve_proof' ) );
	}

	/**
	 * Validate and store an uploaded proof.
	 *
	 * @param array<string, mixed> $file      One entry from $_FILES.
	 * @param int                  $client_id Owning client.
	 * @return array{path:string, mime:string}|WP_Error Relative path and MIME.
	 */
	public static function store_proof( array $file, int $client_id ) {
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new WP_Error( 'salanaz_upload_invalid', __( 'No file was received.', 'salanaz' ) );
		}

		switch ( (int) $file['error'] ) {
			case UPLOAD_ERR_OK:
				break;
			case UPLOAD_ERR_NO_FILE:
				return new WP_Error( 'salanaz_upload_none', __( 'Please choose a file to upload.', 'salanaz' ) );
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return new WP_Error( 'salanaz_upload_big', __( 'That file is too large. The limit is 5 MB.', 'salanaz' ) );
			default:
				return new WP_Error( 'salanaz_upload_failed', __( 'The upload failed. Please try again.', 'salanaz' ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'salanaz_upload_invalid', __( 'That upload could not be verified.', 'salanaz' ) );
		}

		if ( (int) $file['size'] > self::MAX_BYTES ) {
			return new WP_Error( 'salanaz_upload_big', __( 'That file is too large. The limit is 5 MB.', 'salanaz' ) );
		}

		if ( (int) $file['size'] <= 0 ) {
			return new WP_Error( 'salanaz_upload_empty', __( 'That file appears to be empty.', 'salanaz' ) );
		}

		// Trust the file's actual contents, not the browser-supplied type or
		// the extension on the original filename.
		$finfo = new finfo( FILEINFO_MIME_TYPE );
		$mime  = (string) $finfo->file( $file['tmp_name'] );

		$allowed = self::allowed_types();

		if ( ! isset( $allowed[ $mime ] ) ) {
			return new WP_Error(
				'salanaz_upload_type',
				__( 'Only JPG, PNG, WebP or PDF files are accepted.', 'salanaz' )
			);
		}

		// Cross-check against WordPress's own sniffing so a file whose contents
		// and extension disagree is rejected rather than silently renamed.
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], (string) $file['name'], self::wp_mime_map() );

		if ( empty( $checked['type'] ) || $checked['type'] !== $mime ) {
			return new WP_Error(
				'salanaz_upload_mismatch',
				__( 'That file type could not be confirmed. Please upload a plain JPG, PNG, WebP or PDF.', 'salanaz' )
			);
		}

		$dir = salanaz_private_dir() . '/' . gmdate( 'Y/m' );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'salanaz_upload_dir', __( 'Storage is not writable. Please contact us.', 'salanaz' ) );
		}

		// Randomised name: the original filename is never used, so a crafted
		// name cannot influence the path and the file cannot be guessed.
		$name = sprintf(
			'%d-%s.%s',
			$client_id,
			wp_generate_password( 24, false, false ),
			$allowed[ $mime ]
		);

		$target = $dir . '/' . $name;

		if ( ! move_uploaded_file( $file['tmp_name'], $target ) ) {
			return new WP_Error( 'salanaz_upload_move', __( 'The file could not be saved. Please try again.', 'salanaz' ) );
		}

		@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return array(
			'path' => gmdate( 'Y/m' ) . '/' . $name,
			'mime' => $mime,
		);
	}

	/**
	 * Extension-to-MIME map for wp_check_filetype_and_ext().
	 *
	 * @return array<string, string>
	 */
	private static function wp_mime_map(): array {
		return array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'pdf'      => 'application/pdf',
		);
	}

	/**
	 * A signed URL for viewing a stored proof.
	 *
	 * @param int $transaction_id Transaction ID.
	 * @return string
	 */
	public static function proof_url( int $transaction_id ): string {
		return add_query_arg(
			array(
				'salanaz_proof' => $transaction_id,
				'_wpnonce'      => wp_create_nonce( 'salanaz_proof_' . $transaction_id ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Serve a proof file to a permitted user.
	 *
	 * Reads the file through PHP rather than redirecting, so the private
	 * directory is never exposed.
	 */
	public function maybe_serve_proof(): void {
		if ( ! isset( $_GET['salanaz_proof'] ) ) {
			return;
		}

		$id = absint( $_GET['salanaz_proof'] );

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in to view this document.', 'salanaz' ), '', array( 'response' => 401 ) );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'salanaz_proof_' . $id ) ) {
			wp_die( esc_html__( 'That link has expired. Please reload the page and try again.', 'salanaz' ), '', array( 'response' => 403 ) );
		}

		$txn = Salanaz_Transactions::get( $id );

		if ( ! $txn || ! $txn->proof_path ) {
			wp_die( esc_html__( 'That document could not be found.', 'salanaz' ), '', array( 'response' => 404 ) );
		}

		if ( ! self::user_can_view( $txn ) ) {
			wp_die( esc_html__( 'You do not have permission to view this document.', 'salanaz' ), '', array( 'response' => 403 ) );
		}

		$path = salanaz_private_dir() . '/' . ltrim( (string) $txn->proof_path, '/' );

		// Resolve and confirm the file really sits inside the private directory,
		// so a tampered stored path cannot walk out of it.
		$real = realpath( $path );
		$base = realpath( salanaz_private_dir() );

		if ( ! $real || ! $base || ! str_starts_with( $real, $base ) || ! is_file( $real ) ) {
			wp_die( esc_html__( 'That document could not be found.', 'salanaz' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . ( $txn->proof_mime ?: 'application/octet-stream' ) );
		header( 'Content-Length: ' . filesize( $real ) );
		header( 'Content-Disposition: inline; filename="proof-' . sanitize_file_name( $txn->reference ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'' );

		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Whether the current user may view a transaction's proof.
	 *
	 * The owning client, anyone who can verify payments, and the client's
	 * assigned sales officer.
	 *
	 * @param object $txn Transaction row.
	 * @return bool
	 */
	public static function user_can_view( object $txn ): bool {
		$user_id = get_current_user_id();

		if ( (int) $txn->client_id === $user_id ) {
			return true;
		}

		if ( current_user_can( Salanaz_Roles::CAP_VERIFY_PAYMENTS ) || current_user_can( Salanaz_Roles::CAP_VIEW_ALL_TRANSACTIONS ) ) {
			return true;
		}

		return Salanaz_Users::staff_can_view_client( (int) $txn->client_id, $user_id );
	}
}
