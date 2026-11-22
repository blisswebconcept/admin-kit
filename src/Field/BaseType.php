<?php
/**
 * Shared behaviour for the built-in field types.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Base field type: an input field with no marker, no extra help, no scripts.
 */
abstract class BaseType implements Type {

	/**
	 * Extra description keys and their defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array( 'default' => '' );
	}

	/**
	 * Laid out as a label and one control.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'input';
	}

	/**
	 * No extra help.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function help( array $field ): string {
		return '';
	}

	/**
	 * Empty values post something.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return false;
	}

	/**
	 * No scripts.
	 *
	 * @return list<string>
	 */
	public function scripts(): array {
		return array();
	}

	/**
	 * A kit string.
	 *
	 * @param string           $key     Key.
	 * @param string|int|float ...$args Placeholders.
	 */
	protected static function say( string $key, string|int|float ...$args ): string {
		return Kit::instance()->strings()->get( $key, ...$args );
	}

	/**
	 * The site's timezone for help text: its name, or "UTC+05:30" when the
	 * site is set to an offset (WordPress gives a bare "+05:30" then).
	 */
	protected static function timezone(): string {
		$zone = wp_timezone_string();

		return preg_match( '/^[+-]\d/', $zone ) ? 'UTC' . $zone : $zone;
	}

	/**
	 * The field's options as a value => label map, from a list of
	 * [ value, label ] pairs, a plain map, or a callable returning either.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<int|string, string>
	 */
	public static function elements( array $field ): array {
		$elements = $field['elements'] ?? array();
		if ( is_callable( $elements ) ) {
			$elements = $elements();
		}

		$map = array();
		foreach ( (array) $elements as $key => $element ) {
			if ( is_array( $element ) && array_key_exists( 'value', $element ) ) {
				$map[ (string) $element['value'] ] = (string) ( $element['label'] ?? $element['value'] );
			} elseif ( is_scalar( $element ) ) {
				$map[ (string) $key ] = (string) $element;
			}
		}

		return $map;
	}

	/**
	 * Option descriptions (radio), keyed by value.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<int|string, string>
	 */
	protected static function element_descriptions( array $field ): array {
		$elements = $field['elements'] ?? array();
		if ( is_callable( $elements ) ) {
			$elements = $elements();
		}

		$map = array();
		foreach ( (array) $elements as $element ) {
			if ( is_array( $element ) && isset( $element['value'], $element['description'] ) ) {
				$map[ (string) $element['value'] ] = (string) $element['description'];
			}
		}

		return $map;
	}
}
