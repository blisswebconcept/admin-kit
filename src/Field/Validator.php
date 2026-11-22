<?php
/**
 * The isValid checks every field can have.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Validator: required, min, max,
 * minLength, maxLength, pattern and custom, run after the type's sanitize.
 */
final class Validator {

	/**
	 * The first problem with a clean value, or null.
	 *
	 * @param array<string, mixed> $field  Field description, normalised.
	 * @param mixed                $value  Clean value.
	 * @param array<string, mixed> $values The form's other submitted values.
	 */
	public static function check( array $field, mixed $value, array $values ): ?string {
		$valid   = (array) ( $field['isValid'] ?? array() );
		$strings = Kit::instance()->strings();

		if ( ! empty( $valid['required'] ) && self::is_empty( $value ) ) {
			return $strings->get( is_bool( $value ) ? 'field_required_check' : 'field_required' );
		}

		if ( self::is_empty( $value ) ) {
			return self::custom( $valid, $value, $values );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $valid['min'] ) && $value < $valid['min'] ) {
				return $strings->get( 'field_min', Html::number( $valid['min'] ) );
			}
			if ( isset( $valid['max'] ) && $value > $valid['max'] ) {
				return $strings->get( 'field_max', Html::number( $valid['max'] ) );
			}
		}

		if ( is_string( $value ) ) {
			$length = mb_strlen( $value );
			if ( isset( $valid['minLength'] ) && $length < (int) $valid['minLength'] ) {
				return $strings->count( 'field_min_length', (int) $valid['minLength'] );
			}
			if ( isset( $valid['maxLength'] ) && $length > (int) $valid['maxLength'] ) {
				return $strings->count( 'field_max_length', (int) $valid['maxLength'] );
			}
			if ( isset( $valid['pattern'] ) && ! preg_match( '/^(?:' . str_replace( '/', '\/', (string) $valid['pattern'] ) . ')$/u', $value ) ) {
				return $strings->get( 'field_pattern' );
			}
		}

		return self::custom( $valid, $value, $values );
	}

	/**
	 * The custom check: a callable returning true or a WP_Error whose message is shown.
	 *
	 * @param array<string, mixed> $valid  isValid.
	 * @param mixed                $value  Clean value.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	private static function custom( array $valid, mixed $value, array $values ): ?string {
		if ( empty( $valid['custom'] ) || ! is_callable( $valid['custom'] ) ) {
			return null;
		}

		$answer = call_user_func( $valid['custom'], $value, $values );

		return is_wp_error( $answer ) ? $answer->get_error_message() : null;
	}

	/**
	 * Whether a value counts as empty for "required".
	 *
	 * @param mixed $value Value.
	 */
	public static function is_empty( mixed $value ): bool {
		return null === $value || '' === $value || array() === $value || false === $value;
	}
}
