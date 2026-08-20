<?php
/**
 * Minimal PDF writer.
 *
 * Receipts and allocation letters have to work on ordinary Nigerian shared
 * hosting, where Composer, GD and shell access are all unreliable. Rather than
 * depend on dompdf or TCPDF, this writes PDF 1.4 directly using the fourteen
 * fonts every reader has built in. No dependencies, no binary, no network.
 *
 * The trade-off is deliberate and worth knowing:
 *
 * - Text is encoded as Windows-1252, so the document is Latin-1 only.
 * - The Naira sign (U+20A6) is not in that character set, so money is written
 *   as "NGN 1,140,000.00" — which is what formal Nigerian receipts use anyway.
 * - Layout is explicit positioning, not flowed HTML.
 *
 * If richer documents are ever needed, swap this for a real library behind the
 * same Salanaz_Documents API.
 *
 * @package Salanaz
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds a PDF document.
 */
class Salanaz_PDF {

	/* A4 in PostScript points. */
	public const WIDTH  = 595.28;
	public const HEIGHT = 841.89;

	public const MARGIN = 48.0;

	/** @var string[] Finished page content streams. */
	private array $pages = array();

	/** @var string The page currently being drawn. */
	private string $buffer = '';

	/** @var float Current vertical cursor, measured from the top. */
	private float $y = self::MARGIN;

	public function __construct() {
		$this->buffer = '';
		$this->y      = self::MARGIN;
	}

	/* =====================================================================
	 * Geometry
	 * ================================================================== */

	/**
	 * Usable width between the margins.
	 *
	 * @return float
	 */
	public function content_width(): float {
		return self::WIDTH - ( self::MARGIN * 2 );
	}

	/**
	 * Current cursor position from the top of the page.
	 *
	 * @return float
	 */
	public function cursor(): float {
		return $this->y;
	}

	/**
	 * Move the cursor down.
	 *
	 * @param float $amount Points.
	 */
	public function advance( float $amount ): void {
		$this->y += $amount;
	}

	/**
	 * Set the cursor.
	 *
	 * @param float $y Points from the top.
	 */
	public function set_cursor( float $y ): void {
		$this->y = $y;
	}

	/**
	 * Start a new page, keeping the current one.
	 */
	public function new_page(): void {
		$this->pages[] = $this->buffer;
		$this->buffer  = '';
		$this->y       = self::MARGIN;
	}

	/**
	 * Break to a new page when there is not enough room left.
	 *
	 * @param float $needed Points required.
	 */
	public function ensure_space( float $needed ): void {
		if ( $this->y + $needed > self::HEIGHT - self::MARGIN ) {
			$this->new_page();
		}
	}

	/* =====================================================================
	 * Drawing
	 * ================================================================== */

	/**
	 * Draw a line of text.
	 *
	 * @param string $text  The text.
	 * @param float  $x     Left position.
	 * @param float  $size  Font size.
	 * @param bool   $bold  Whether to use the bold face.
	 * @param array  $rgb   Colour as [r, g, b], each 0-1.
	 * @param float|null $y Vertical position from the top; defaults to the cursor.
	 */
	public function text( string $text, float $x, float $size = 10.0, bool $bold = false, array $rgb = array( 0, 0, 0 ), ?float $y = null ): void {
		$y  = $y ?? $this->y;
		$py = self::HEIGHT - $y;

		$this->buffer .= sprintf(
			"BT %.3f %.3f %.3f rg /%s %.2f Tf %.2f %.2f Td (%s) Tj ET\n",
			$rgb[0],
			$rgb[1],
			$rgb[2],
			$bold ? 'F2' : 'F1',
			$size,
			$x,
			$py,
			self::escape( $text )
		);
	}

	/**
	 * Draw text aligned to a right edge.
	 *
	 * @param string $text  The text.
	 * @param float  $right Right edge.
	 * @param float  $size  Font size.
	 * @param bool   $bold  Bold face.
	 * @param array  $rgb   Colour.
	 * @param float|null $y Vertical position.
	 */
	public function text_right( string $text, float $right, float $size = 10.0, bool $bold = false, array $rgb = array( 0, 0, 0 ), ?float $y = null ): void {
		$this->text( $text, $right - $this->width_of( $text, $size, $bold ), $size, $bold, $rgb, $y );
	}

	/**
	 * Draw text centred on the page.
	 *
	 * @param string $text The text.
	 * @param float  $size Font size.
	 * @param bool   $bold Bold face.
	 * @param array  $rgb  Colour.
	 * @param float|null $y Vertical position.
	 */
	public function text_center( string $text, float $size = 10.0, bool $bold = false, array $rgb = array( 0, 0, 0 ), ?float $y = null ): void {
		$this->text( $text, ( self::WIDTH - $this->width_of( $text, $size, $bold ) ) / 2, $size, $bold, $rgb, $y );
	}

	/**
	 * Draw wrapped body text, advancing the cursor.
	 *
	 * @param string $text     The text.
	 * @param float  $x        Left position.
	 * @param float  $width    Wrap width.
	 * @param float  $size     Font size.
	 * @param float  $leading  Line height.
	 * @param bool   $bold     Bold face.
	 */
	public function paragraph( string $text, float $x, float $width, float $size = 10.0, float $leading = 14.0, bool $bold = false ): void {
		foreach ( $this->wrap( $text, $width, $size, $bold ) as $line ) {
			$this->ensure_space( $leading );
			$this->text( $line, $x, $size, $bold );
			$this->y += $leading;
		}
	}

	/**
	 * Split text into lines that fit a width.
	 *
	 * @param string $text  The text.
	 * @param float  $width Available width.
	 * @param float  $size  Font size.
	 * @param bool   $bold  Bold face.
	 * @return string[]
	 */
	public function wrap( string $text, float $width, float $size, bool $bold = false ): array {
		$lines = array();

		foreach ( preg_split( '/\R/', $text ) as $source ) {
			$words   = preg_split( '/\s+/', trim( $source ) );
			$current = '';

			foreach ( $words as $word ) {
				if ( '' === $word ) {
					continue;
				}

				$candidate = '' === $current ? $word : $current . ' ' . $word;

				if ( $this->width_of( $candidate, $size, $bold ) <= $width ) {
					$current = $candidate;
					continue;
				}

				if ( '' !== $current ) {
					$lines[] = $current;
				}

				$current = $word;
			}

			$lines[] = $current;
		}

		return $lines;
	}

	/**
	 * Draw a filled rectangle.
	 *
	 * @param float $x      Left.
	 * @param float $y      Top.
	 * @param float $w      Width.
	 * @param float $h      Height.
	 * @param array $rgb    Fill colour.
	 */
	public function rect( float $x, float $y, float $w, float $h, array $rgb = array( 0, 0, 0 ) ): void {
		$this->buffer .= sprintf(
			"%.3f %.3f %.3f rg %.2f %.2f %.2f %.2f re f\n",
			$rgb[0],
			$rgb[1],
			$rgb[2],
			$x,
			self::HEIGHT - $y - $h,
			$w,
			$h
		);
	}

	/**
	 * Draw a horizontal rule.
	 *
	 * @param float $x     Left.
	 * @param float $y     Vertical position from the top.
	 * @param float $w     Width.
	 * @param array $rgb   Colour.
	 * @param float $thick Thickness.
	 */
	public function rule( float $x, float $y, float $w, array $rgb = array( 0.85, 0.87, 0.91 ), float $thick = 0.7 ): void {
		$this->rect( $x, $y, $w, $thick, $rgb );
	}

	/**
	 * Draw a label/value row and advance the cursor.
	 *
	 * @param string $label Label text.
	 * @param string $value Value text.
	 * @param float  $x     Left.
	 * @param float  $right Right edge for the value.
	 * @param float  $size  Font size.
	 */
	public function row( string $label, string $value, float $x, float $right, float $size = 10.0 ): void {
		$this->ensure_space( 20.0 );
		$this->text( $label, $x, $size, false, array( 0.48, 0.52, 0.58 ) );
		$this->text_right( $value, $right, $size, true );
		$this->y += 18.0;
	}

	/* =====================================================================
	 * Text metrics
	 * ================================================================== */

	/**
	 * Approximate the width of a string in Helvetica.
	 *
	 * Uses the real Helvetica advance widths for the ASCII range, which keeps
	 * right-alignment and wrapping accurate without embedding a metrics file.
	 *
	 * @param string $text The text.
	 * @param float  $size Font size.
	 * @param bool   $bold Bold face.
	 * @return float
	 */
	public function width_of( string $text, float $size, bool $bold = false ): float {
		$widths = self::helvetica_widths();
		$total  = 0.0;
		$string = self::to_winansi( $text );
		$length = strlen( $string );

		for ( $i = 0; $i < $length; $i++ ) {
			$code   = ord( $string[ $i ] );
			$total += $widths[ $code ] ?? 556;
		}

		// Bold is roughly 4% wider across the alphabet.
		$total *= $bold ? 1.04 : 1.0;

		return ( $total / 1000 ) * $size;
	}

	/**
	 * Helvetica advance widths, keyed by character code.
	 *
	 * @return array<int, int>
	 */
	private static function helvetica_widths(): array {
		static $widths = null;

		if ( null !== $widths ) {
			return $widths;
		}

		// Widths for codes 32-126; everything else falls back to 556.
		$table = array(
			278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
			556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
			1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
			667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
			333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
			556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
		);

		$widths = array();

		foreach ( $table as $index => $width ) {
			$widths[ 32 + $index ] = $width;
		}

		return $widths;
	}

	/* =====================================================================
	 * Encoding
	 * ================================================================== */

	/**
	 * Convert UTF-8 to Windows-1252, replacing what cannot be represented.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	public static function to_winansi( string $text ): string {
		// The Naira sign has no Windows-1252 code point; spell the currency out.
		$text = str_replace( array( "\u{20A6}", "\u{2014}", "\u{2013}", "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}" ), array( 'NGN ', '-', '-', "'", "'", '"', '"' ), $text );

		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

			if ( false !== $converted ) {
				return $converted;
			}
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
		}

		return preg_replace( '/[^\x20-\x7E]/', '', $text );
	}

	/**
	 * Escape a string for a PDF literal.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	private static function escape( string $text ): string {
		$text = self::to_winansi( $text );
		$out  = '';

		$length = strlen( $text );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $text[ $i ];
			$code = ord( $char );

			if ( '(' === $char || ')' === $char || '\\' === $char ) {
				$out .= '\\' . $char;
			} elseif ( $code < 32 || $code > 126 ) {
				$out .= sprintf( '\\%03o', $code );
			} else {
				$out .= $char;
			}
		}

		return $out;
	}

	/* =====================================================================
	 * Output
	 * ================================================================== */

	/**
	 * Assemble the finished PDF.
	 *
	 * @return string Raw PDF bytes.
	 */
	public function output(): string {
		$pages = $this->pages;

		if ( '' !== $this->buffer || ! $pages ) {
			$pages[] = $this->buffer;
		}

		$count = count( $pages );

		// Object numbering: 1 catalog, 2 pages, 3..(2+n) page objects,
		// then n content streams, then the two fonts.
		$first_content = 3 + $count;
		$font_regular  = $first_content + $count;
		$font_bold     = $font_regular + 1;

		$objects = array();

		$objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

		$kids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$kids[] = ( 3 + $i ) . ' 0 R';
		}

		$objects[2] = sprintf( '<< /Type /Pages /Kids [%s] /Count %d >>', implode( ' ', $kids ), $count );

		for ( $i = 0; $i < $count; $i++ ) {
			$objects[ 3 + $i ] = sprintf(
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> /Contents %d 0 R >>',
				self::WIDTH,
				self::HEIGHT,
				$font_regular,
				$font_bold,
				$first_content + $i
			);

			$stream = $pages[ $i ];

			$objects[ $first_content + $i ] = sprintf(
				"<< /Length %d >>\nstream\n%s\nendstream",
				strlen( $stream ),
				$stream
			);
		}

		$objects[ $font_regular ] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[ $font_bold ]    = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

		ksort( $objects );

		$pdf     = "%PDF-1.4\n";
		$offsets = array();

		foreach ( $objects as $number => $content ) {
			$offsets[ $number ] = strlen( $pdf );
			$pdf               .= $number . " 0 obj\n" . $content . "\nendobj\n";
		}

		$xref_position = strlen( $pdf );
		$total         = count( $objects ) + 1;

		$pdf .= "xref\n0 " . $total . "\n";
		$pdf .= "0000000000 65535 f \n";

		for ( $number = 1; $number < $total; $number++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $number ] ?? 0 );
		}

		$pdf .= sprintf(
			"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
			$total,
			$xref_position
		);

		return $pdf;
	}
}
