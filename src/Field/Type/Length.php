<?php
/**
 * A CSS length: 12px, 1.5rem, 40%.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Field\Result;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "length".
 */
class Length extends Text {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'length';
	}

	/**
	 * Extra keys: units, allow_zero, allow_negative.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'        => '',
			'units'          => array( 'px', 'rem', 'em', '%', 'vw', 'vh', 'ch' ),
			'allow_zero'     => true,
			'allow_negative' => false,
			'monospace'      => true,
		);
	}

	/**
	 * "A number and px, rem or %."
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function help( array $field ): string {
		return self::say( 'field_length_help', self::units_text( (array) $field['units'] ) );
	}

	/**
	 * The units joined with commas and "or".
	 *
	 * @param array<int, string> $units Units.
	 */
	public static function units_text( array $units ): string {
		$units = array_values( array_map( 'strval', $units ) );
		$last  = array_pop( $units );

		return $units ? Kit::instance()->strings()->get( 'list_or', implode( ', ', $units ), (string) $last ) : (string) $last;
	}

	/**
	 * Matches a number and one of the units.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$value = strtolower( trim( is_scalar( $raw ) ? (string) $raw : '' ) );

		if ( '' === $value || ( '0' === $value && $field['allow_zero'] ) ) {
			return Result::ok( $value );
		}

		if ( ! preg_match( self::pattern( $field ), $value ) ) {
			return Result::rejected( $stored, self::say( 'field_length', self::units_text( (array) $field['units'] ) ) );
		}

		return Result::ok( $value );
	}

	/**
	 * The pattern a length must match.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	private static function pattern( array $field ): string {
		$units = implode( '|', array_map( static fn( $unit ): string => preg_quote( (string) $unit, '/' ), (array) $field['units'] ) );

		return '/^' . ( $field['allow_negative'] ? '-?' : '' ) . '\d+(?:\.\d+)?(?:' . $units . ')$/';
	}

	/**
	 * A string with the pattern.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'    => 'string',
			'pattern' => trim( self::pattern( $field ), '/' ) . ( $field['allow_zero'] ? '|^0$|^$' : '|^$' ),
		);
	}
}
