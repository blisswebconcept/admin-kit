<?php
/**
 * Spreadsheet-safe CSV downloads.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * CSV export. UTF-8 with a byte-order mark,
 * a formula guard on every text cell, rows streamed as they come.
 */
final class Csv {

	/**
	 * Characters that make a spreadsheet read a cell as a formula.
	 */
	private const FORMULA_START = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Constructor.
	 *
	 * @param Kit $kit The kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * Sends a CSV file and ends the request.
	 *
	 * @param string                    $filename File name without ".csv".
	 * @param list<string>              $headings Column headings.
	 * @param iterable<array<int|string, mixed>> $rows     Rows, a generator for large exports.
	 */
	public function download( string $filename, array $headings, iterable $rows ): void {
		$name = sanitize_file_name( $filename );
		$name = ( '' === $name ? $this->kit->id() . '-export' : $name ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		if ( false !== $out ) {
			$this->write( $out, $headings, $rows );
			fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output stream.
		}
		exit;
	}

	/**
	 * Writes the byte-order mark, the headings and the rows to a stream.
	 *
	 * @param resource                           $out      Writable stream.
	 * @param list<string>                       $headings Column headings.
	 * @param iterable<array<int|string, mixed>> $rows     Rows.
	 * @return int Rows written, headings not counted.
	 */
	public function write( $out, array $headings, iterable $rows ): int {
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- writing to the export stream.
		fputcsv( $out, array_map( array( $this, 'cell' ), $headings ), ',', '"', '' );

		$count = 0;
		foreach ( $rows as $row ) {
			fputcsv( $out, array_map( array( $this, 'cell' ), array_values( $row ) ), ',', '"', '' );
			++$count;
		}

		return $count;
	}

	/**
	 * One cell: numbers as they are, text that could run as a formula
	 * behind a leading apostrophe, arrays joined, booleans as 1 or 0.
	 *
	 * @param mixed $value Cell value.
	 */
	public function cell( mixed $value ): string|int|float {
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
		}

		$text = is_scalar( $value ) ? (string) $value : '';

		if ( is_numeric( $text ) ) {
			return $text;
		}

		return '' !== $text && in_array( $text[0], self::FORMULA_START, true ) ? "'" . $text : $text;
	}
}
