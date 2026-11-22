<?php
/**
 * REST schemas from field descriptions.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Schema helpers.
 */
final class Schema {

	/**
	 * Properties for a list of fields: each type's schema, with the field's
	 * label as its description and its default. Types that return null are left out.
	 *
	 * @param list<array<string, mixed>> $fields Field descriptions.
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( array $fields ): array {
		$properties = array();
		foreach ( Form::normalize( $fields ) as $field ) {
			$schema = self::field( $field );
			if ( null !== $schema ) {
				$properties[ (string) $field['id'] ] = $schema;
			}
		}

		return $properties;
	}

	/**
	 * One field's schema, or null when its type keeps it out of the REST API.
	 *
	 * @param array<string, mixed> $field Normalised description.
	 * @return array<string, mixed>|null
	 */
	public static function field( array $field ): ?array {
		$type   = Kit::instance()->fields()->get( (string) $field['type'] );
		$schema = null === $type ? null : $type->schema( $field );

		if ( null === $schema ) {
			return null;
		}

		return array_merge(
			$schema,
			array(
				'description' => (string) $field['label'],
				'default'     => $field['default'],
			)
		);
	}

	/**
	 * A raw meta value cast to the field's schema type (meta comes back as strings).
	 *
	 * @param array<string, mixed> $field Normalised description.
	 * @param mixed                $raw   Stored meta value.
	 */
	public static function cast( array $field, mixed $raw ): mixed {
		$schema = self::field( $field );

		switch ( $schema['type'] ?? 'string' ) {
			case 'boolean':
				return (bool) $raw;
			case 'integer':
				return is_numeric( $raw ) ? (int) $raw : $field['default'];
			case 'number':
				return is_numeric( $raw ) ? (float) $raw : $field['default'];
			case 'array':
			case 'object':
				return is_array( $raw ) ? $raw : $field['default'];
			default:
				return is_scalar( $raw ) ? (string) $raw : $field['default'];
		}
	}
}
