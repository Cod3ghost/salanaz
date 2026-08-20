<?php
/**
 * Receipts and allocation letters.
 *
 * Documents are written into the same private directory as payment proofs and
 * served only through a capability-checked endpoint — a receipt names a person,
 * a plot and an amount, so it is not something to leave on a guessable URL.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generates and serves PDF documents.
 */
class Salanaz_Documents {

	/** Brand navy, as PDF RGB. */
	private const NAVY = array( 0.043, 0.235, 0.561 );
	private const GOLD = array( 0.910, 0.651, 0.102 );
	private const GREY = array( 0.48, 0.52, 0.58 );
	private const INK  = array( 0.10, 0.11, 0.14 );

	/**
	 * Wire up hooks.
	 */
	public function register_hooks(): void {
		// A verified payment always produces a receipt.
		add_action( 'salanaz_payment_verified', array( $this, 'on_payment_verified' ) );
		add_action( 'salanaz_paystack_verified', array( $this, 'on_payment_verified' ) );

		// A fully paid plot produces its allocation letter.
		add_action( 'salanaz_plot_allocated', array( $this, 'on_plot_allocated' ), 10, 2 );

		add_action( 'init', array( $this, 'maybe_serve_document' ) );
	}

	/**
	 * Generate the receipt for a freshly verified payment.
	 *
	 * @param int $txn_id Transaction ID.
	 */
	public function on_payment_verified( int $txn_id ): void {
		self::generate_receipt( $txn_id );
	}

	/**
	 * Generate the allocation letter for a newly completed plot.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 */
	public function on_plot_allocated( int $client_id, int $plot_id ): void {
		self::generate_allocation_letter( $client_id, $plot_id );
	}

	/* =====================================================================
	 * Receipt
	 * ================================================================== */

	/**
	 * Build and store a receipt PDF.
	 *
	 * @param int $txn_id Transaction ID.
	 * @return string|WP_Error Stored relative path.
	 */
	public static function generate_receipt( int $txn_id ) {
		$txn = Salanaz_Transactions::get( $txn_id );

		if ( ! $txn ) {
			return new WP_Error( 'salanaz_no_txn', __( 'That payment could not be found.', 'salanaz' ) );
		}

		if ( Salanaz_Schema::TXN_VERIFIED !== $txn->status ) {
			return new WP_Error( 'salanaz_not_verified', __( 'A receipt is only issued for a verified payment.', 'salanaz' ) );
		}

		$client = get_userdata( (int) $txn->client_id );
		$plot   = get_post( (int) $txn->plot_id );
		$estate = Salanaz_Inventory::plot_estate( (int) $txn->plot_id );

		$paid  = Salanaz_Transactions::paid_for_plot( (int) $txn->client_id, (int) $txn->plot_id );
		$plan  = Salanaz_Plans::plan_for_plot( (int) $txn->client_id, (int) $txn->plot_id );
		$total = $plan ? (float) $plan->total_amount : Salanaz_Inventory::plot_price( (int) $txn->plot_id );

		$pdf   = new Salanaz_PDF();
		$left  = Salanaz_PDF::MARGIN;
		$right = Salanaz_PDF::WIDTH - Salanaz_PDF::MARGIN;

		self::header( $pdf, __( 'PAYMENT RECEIPT', 'salanaz' ), $txn->reference );

		$pdf->set_cursor( 172 );

		// Who and when.
		$pdf->text( __( 'Received from', 'salanaz' ), $left, 9, false, self::GREY );
		$pdf->text_right( __( 'Date', 'salanaz' ), $right, 9, false, self::GREY );
		$pdf->advance( 14 );

		$pdf->text( $client instanceof WP_User ? $client->display_name : __( 'Unknown client', 'salanaz' ), $left, 12, true, self::INK );
		$pdf->text_right( mysql2date( 'j F Y', $txn->verified_at ?: $txn->created_at ), $right, 11, false, self::INK );
		$pdf->advance( 30 );

		$pdf->rule( $left, $pdf->cursor(), $right - $left );
		$pdf->advance( 22 );

		// The amount, given its own weight.
		$pdf->rect( $left, $pdf->cursor(), $right - $left, 58, array( 0.965, 0.973, 0.984 ) );
		$pdf->text( __( 'Amount received', 'salanaz' ), $left + 16, 9, false, self::GREY, $pdf->cursor() + 18 );
		$pdf->text( self::money( (float) $txn->amount ), $left + 16, 22, true, self::NAVY, $pdf->cursor() + 44 );
		$pdf->advance( 78 );

		// Details.
		$pdf->row( __( 'Payment method', 'salanaz' ), self::method_label( (string) $txn->payment_method ), $left, $right );

		if ( $plot instanceof WP_Post ) {
			$pdf->row( __( 'Plot', 'salanaz' ), get_the_title( $plot ), $left, $right );
		}

		if ( $estate instanceof WP_Post ) {
			$pdf->row( __( 'Estate', 'salanaz' ), get_the_title( $estate ), $left, $right );
		}

		if ( $plan ) {
			$pdf->row(
				__( 'Payment plan', 'salanaz' ),
				sprintf(
					/* translators: %d: months */
					__( '%d months', 'salanaz' ),
					(int) $plan->tenure_months
				),
				$left,
				$right
			);
		}

		$pdf->advance( 6 );
		$pdf->rule( $left, $pdf->cursor(), $right - $left );
		$pdf->advance( 18 );

		$pdf->row( __( 'Total paid to date', 'salanaz' ), self::money( $paid ), $left, $right );
		$pdf->row( __( 'Outstanding balance', 'salanaz' ), self::money( max( 0, $total - $paid ) ), $left, $right );

		$pdf->advance( 14 );

		if ( $total > 0 && $paid >= $total - 0.005 ) {
			$pdf->text( __( 'This plot is fully paid.', 'salanaz' ), $left, 11, true, array( 0.11, 0.48, 0.12 ) );
			$pdf->advance( 24 );
		}

		$pdf->paragraph(
			__( 'This receipt confirms a payment recorded against the plot named above. Keep it safe — you will be asked for it at allocation. If any detail here is wrong, contact us before making a further payment.', 'salanaz' ),
			$left,
			$right - $left,
			9.5,
			14
		);

		self::footer( $pdf );

		return self::store( $pdf->output(), 'receipt', (int) $txn->client_id, (string) $txn->reference, $txn_id );
	}

	/* =====================================================================
	 * Allocation letter
	 * ================================================================== */

	/**
	 * Build and store an allocation letter for a fully paid plot.
	 *
	 * @param int $client_id Client user ID.
	 * @param int $plot_id   Plot post ID.
	 * @return string|WP_Error Stored relative path.
	 */
	public static function generate_allocation_letter( int $client_id, int $plot_id ) {
		$client = get_userdata( $client_id );
		$plot   = get_post( $plot_id );

		if ( ! $client instanceof WP_User || ! $plot instanceof WP_Post ) {
			return new WP_Error( 'salanaz_not_found', __( 'That client or plot could not be found.', 'salanaz' ) );
		}

		$paid  = Salanaz_Transactions::paid_for_plot( $client_id, $plot_id );
		$plan  = Salanaz_Plans::plan_for_plot( $client_id, $plot_id );
		$total = $plan ? (float) $plan->total_amount : Salanaz_Inventory::plot_price( $plot_id );

		if ( $total <= 0 || $paid < $total - 0.005 ) {
			return new WP_Error(
				'salanaz_not_paid',
				__( 'An allocation letter is only issued once the plot is fully paid.', 'salanaz' )
			);
		}

		$estate    = Salanaz_Inventory::plot_estate( $plot_id );
		$reference = salanaz_generate_reference( 'ALC' );

		$pdf   = new Salanaz_PDF();
		$left  = Salanaz_PDF::MARGIN;
		$right = Salanaz_PDF::WIDTH - Salanaz_PDF::MARGIN;

		self::header( $pdf, __( 'LETTER OF ALLOCATION', 'salanaz' ), $reference );

		$pdf->set_cursor( 178 );

		$pdf->text( mysql2date( 'j F Y', current_time( 'mysql' ) ), $left, 10, false, self::GREY );
		$pdf->advance( 26 );

		$pdf->text( $client->display_name, $left, 12, true, self::INK );
		$pdf->advance( 16 );

		$address = (string) get_user_meta( $client_id, Salanaz_Profile::META_ADDRESS, true );

		if ( $address ) {
			$pdf->paragraph( $address, $left, 260, 10, 13 );
		}

		$pdf->advance( 18 );

		$pdf->text( __( 'Dear Sir/Madam,', 'salanaz' ), $left, 10.5 );
		$pdf->advance( 24 );

		$pdf->text( __( 'ALLOCATION OF PLOT', 'salanaz' ), $left, 11, true, self::NAVY );
		$pdf->advance( 20 );

		$pdf->paragraph(
			sprintf(
				/* translators: 1: plot title, 2: estate name */
				__( 'We are pleased to confirm that following the completion of your payment, the plot described below has been allocated to you at %2$s: %1$s.', 'salanaz' ),
				get_the_title( $plot ),
				$estate instanceof WP_Post ? get_the_title( $estate ) : __( 'our estate', 'salanaz' )
			),
			$left,
			$right - $left,
			10.5,
			15
		);

		$pdf->advance( 12 );
		$pdf->rule( $left, $pdf->cursor(), $right - $left );
		$pdf->advance( 18 );

		$pdf->row( __( 'Allottee', 'salanaz' ), $client->display_name, $left, $right );

		if ( $estate instanceof WP_Post ) {
			$pdf->row( __( 'Estate', 'salanaz' ), get_the_title( $estate ), $left, $right );

			$address_meta = (string) get_post_meta( $estate->ID, Salanaz_Post_Types::META_ESTATE_ADDRESS, true );

			if ( $address_meta ) {
				$pdf->row( __( 'Location', 'salanaz' ), $address_meta, $left, $right );
			}

			$title_doc = (string) get_post_meta( $estate->ID, Salanaz_Post_Types::META_ESTATE_TITLE_DOC, true );

			if ( $title_doc ) {
				$pdf->row( __( 'Title', 'salanaz' ), $title_doc, $left, $right );
			}
		}

		$plot_number = (string) get_post_meta( $plot_id, Salanaz_Post_Types::META_PLOT_NUMBER, true );

		if ( $plot_number ) {
			$pdf->row( __( 'Plot number', 'salanaz' ), $plot_number, $left, $right );
		}

		$size = Salanaz_Inventory::plot_size( $plot_id );

		if ( $size > 0 ) {
			$pdf->row( __( 'Plot size', 'salanaz' ), number_format( $size ) . ' sqm', $left, $right );
		}

		$pdf->row( __( 'Total consideration', 'salanaz' ), self::money( $total ), $left, $right );
		$pdf->row( __( 'Amount paid', 'salanaz' ), self::money( $paid ), $left, $right );

		$pdf->advance( 8 );
		$pdf->rule( $left, $pdf->cursor(), $right - $left );
		$pdf->advance( 20 );

		$pdf->paragraph(
			__( 'This allocation is subject to the terms of sale you accepted at the point of purchase, and to the estate rules and building guidelines in force from time to time. Physical allocation and the handing over of survey documents will follow at the next scheduled allocation exercise, of which you will be notified.', 'salanaz' ),
			$left,
			$right - $left,
			10,
			14.5
		);

		$pdf->advance( 10 );

		$pdf->paragraph(
			__( 'We thank you for the confidence you have placed in us, and we look forward to welcoming you to the estate.', 'salanaz' ),
			$left,
			$right - $left,
			10,
			14.5
		);

		$pdf->advance( 34 );

		$pdf->text( __( 'Yours faithfully,', 'salanaz' ), $left, 10 );
		$pdf->advance( 42 );
		$pdf->rule( $left, $pdf->cursor(), 190, array( 0.6, 0.63, 0.68 ) );
		$pdf->advance( 14 );
		$pdf->text( __( 'For: Salanaz Global Services Ltd', 'salanaz' ), $left, 10, true, self::INK );
		$pdf->advance( 13 );
		$pdf->text( __( 'Authorised signatory', 'salanaz' ), $left, 9, false, self::GREY );

		self::footer( $pdf );

		$path = self::store( $pdf->output(), 'allocation', $client_id, $reference, 0 );

		if ( ! is_wp_error( $path ) ) {
			update_post_meta( $plot_id, '_salanaz_allocation_path', $path );
			update_post_meta( $plot_id, '_salanaz_allocation_reference', $reference );

			Salanaz_Activity::log(
				'allocation_issued',
				array(
					'subject_id'  => $client_id,
					'object_type' => 'plot',
					'object_id'   => $plot_id,
					'details'     => array( 'reference' => $reference ),
				)
			);
		}

		return $path;
	}

	/* =====================================================================
	 * Shared chrome
	 * ================================================================== */

	/**
	 * Draw the branded header band.
	 *
	 * @param Salanaz_PDF $pdf       Document.
	 * @param string      $title     Document title.
	 * @param string      $reference Document reference.
	 */
	private static function header( Salanaz_PDF $pdf, string $title, string $reference ): void {
		$left  = Salanaz_PDF::MARGIN;
		$right = Salanaz_PDF::WIDTH - Salanaz_PDF::MARGIN;

		$pdf->rect( 0, 0, Salanaz_PDF::WIDTH, 104, self::NAVY );
		$pdf->rect( 0, 104, Salanaz_PDF::WIDTH, 4, self::GOLD );

		$pdf->text( get_bloginfo( 'name' ), $left, 17, true, array( 1, 1, 1 ), 46 );
		$pdf->text( __( 'Built on TRUST. Driven by EXCELLENCE.', 'salanaz' ), $left, 8.5, false, self::GOLD, 64 );

		$pdf->text_right( $title, $right, 12, true, array( 1, 1, 1 ), 46 );
		$pdf->text_right( $reference, $right, 9, false, array( 0.78, 0.83, 0.92 ), 64 );

		$contact = array_filter(
			array(
				(string) get_theme_mod( 'salanaz_office_address', '' ),
				(string) get_theme_mod( 'salanaz_office_phone', '' ),
				(string) get_theme_mod( 'salanaz_office_email', '' ),
			)
		);

		if ( $contact ) {
			$pdf->text( implode( '  |  ', $contact ), $left, 8, false, array( 0.72, 0.78, 0.89 ), 88 );
		}
	}

	/**
	 * Draw the footer on the current page.
	 *
	 * @param Salanaz_PDF $pdf Document.
	 */
	private static function footer( Salanaz_PDF $pdf ): void {
		$left  = Salanaz_PDF::MARGIN;
		$right = Salanaz_PDF::WIDTH - Salanaz_PDF::MARGIN;
		$y     = Salanaz_PDF::HEIGHT - 58;

		$pdf->rule( $left, $y, $right - $left );

		$pdf->text(
			sprintf(
				/* translators: %s: site name */
				__( 'Generated by %s. This is a computer-generated document.', 'salanaz' ),
				get_bloginfo( 'name' )
			),
			$left,
			8,
			false,
			self::GREY,
			$y + 16
		);

		$pdf->text_right( mysql2date( 'j M Y, g:i a', current_time( 'mysql' ) ), $right, 8, false, self::GREY, $y + 16 );
	}

	/**
	 * Money formatted for a PDF.
	 *
	 * The Naira sign is not representable in the PDF core-font encoding, so
	 * documents spell the currency out — which is standard on formal receipts.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private static function money( float $amount ): string {
		return 'NGN ' . number_format( $amount, 2, '.', ',' );
	}

	/**
	 * Human label for a payment method.
	 *
	 * @param string $method Method key.
	 * @return string
	 */
	private static function method_label( string $method ): string {
		return Salanaz_Schema::METHOD_PAYSTACK === $method
			? __( 'Card / transfer (Paystack)', 'salanaz' )
			: __( 'Bank transfer', 'salanaz' );
	}

	/* =====================================================================
	 * Storage and serving
	 * ================================================================== */

	/**
	 * Write a document into private storage.
	 *
	 * @param string $bytes     PDF content.
	 * @param string $kind      'receipt' or 'allocation'.
	 * @param int    $client_id Owning client.
	 * @param string $reference Document reference.
	 * @param int    $txn_id    Transaction to record the path against, if any.
	 * @return string|WP_Error Relative path.
	 */
	private static function store( string $bytes, string $kind, int $client_id, string $reference, int $txn_id = 0 ) {
		$dir = salanaz_private_dir() . '/documents/' . gmdate( 'Y/m' );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'salanaz_doc_dir', __( 'Document storage is not writable.', 'salanaz' ) );
		}

		$name = sprintf( '%s-%d-%s.pdf', $kind, $client_id, sanitize_file_name( $reference ) );
		$path = $dir . '/' . $name;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $path, $bytes ) ) {
			return new WP_Error( 'salanaz_doc_write', __( 'The document could not be saved.', 'salanaz' ) );
		}

		$relative = 'documents/' . gmdate( 'Y/m' ) . '/' . $name;

		if ( $txn_id ) {
			Salanaz_Transactions::update( $txn_id, array( 'receipt_path' => $relative ) );
		}

		return $relative;
	}

	/**
	 * A signed URL for a stored document.
	 *
	 * @param string $type receipt|allocation.
	 * @param int    $id   Transaction ID for a receipt, plot ID for a letter.
	 * @return string
	 */
	public static function document_url( string $type, int $id ): string {
		return add_query_arg(
			array(
				'salanaz_doc'  => $type,
				'salanaz_id'   => $id,
				'_wpnonce'     => wp_create_nonce( 'salanaz_doc_' . $type . '_' . $id ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Serve a stored document to a permitted user.
	 */
	public function maybe_serve_document(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- nonce is checked below.
		if ( ! isset( $_GET['salanaz_doc'], $_GET['salanaz_id'] ) ) {
			return;
		}

		$type = sanitize_key( wp_unslash( $_GET['salanaz_doc'] ) );
		$id   = absint( $_GET['salanaz_id'] );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $type, array( 'receipt', 'allocation' ), true ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please sign in to view this document.', 'salanaz' ), '', array( 'response' => 401 ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'salanaz_doc_' . $type . '_' . $id ) ) {
			wp_die( esc_html__( 'That link has expired. Please reload the page and try again.', 'salanaz' ), '', array( 'response' => 403 ) );
		}

		if ( 'receipt' === $type ) {
			$txn = Salanaz_Transactions::get( $id );

			if ( ! $txn || ! $txn->receipt_path ) {
				wp_die( esc_html__( 'That receipt could not be found.', 'salanaz' ), '', array( 'response' => 404 ) );
			}

			if ( ! Salanaz_Uploads::user_can_view( $txn ) ) {
				wp_die( esc_html__( 'You do not have permission to view this document.', 'salanaz' ), '', array( 'response' => 403 ) );
			}

			self::stream( (string) $txn->receipt_path, 'receipt-' . $txn->reference . '.pdf' );
		}

		$relative = (string) get_post_meta( $id, '_salanaz_allocation_path', true );
		$owner    = (int) get_post_meta( $id, Salanaz_Post_Types::META_PLOT_OWNER, true );

		if ( ! $relative ) {
			wp_die( esc_html__( 'That document could not be found.', 'salanaz' ), '', array( 'response' => 404 ) );
		}

		$allowed = get_current_user_id() === $owner
			|| current_user_can( Salanaz_Roles::CAP_VIEW_ALL_TRANSACTIONS )
			|| Salanaz_Users::staff_can_view_client( $owner );

		if ( ! $allowed ) {
			wp_die( esc_html__( 'You do not have permission to view this document.', 'salanaz' ), '', array( 'response' => 403 ) );
		}

		self::stream( $relative, 'allocation-letter.pdf' );
	}

	/**
	 * Send a stored file, confirming it really sits inside private storage.
	 *
	 * @param string $relative Relative path.
	 * @param string $filename Download filename.
	 */
	private static function stream( string $relative, string $filename ): void {
		$path = salanaz_private_dir() . '/' . ltrim( $relative, '/' );
		$real = realpath( $path );
		$base = realpath( salanaz_private_dir() );

		if ( ! $real || ! $base || ! str_starts_with( $real, $base ) || ! is_file( $real ) ) {
			wp_die( esc_html__( 'That document could not be found.', 'salanaz' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . filesize( $real ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
