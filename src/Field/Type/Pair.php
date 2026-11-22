<?php
/**
 * Two values that only make sense together.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;
use BlissWebConcept\AdminKit\Field\Validator;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "pair": exactly two simple sub-fields
 * (text, number, length, email, url, select), each with its own small label.
 * Stored as array( first id => …, second id => … ). A bad half keeps its stored
 * value and gets the message; the good half is saved.
 */
class Pair extends BaseType {

	/**
	 * Types a half may be.
	 */
	private const SIMPLE = array( 'text', 'number', 'length', 'email', 'url', 'select' );

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'pair';
	}

	/**
	 * Extra keys: fields (the two halves).
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default' => array(),
			'fields'  => array(),
		);
	}

	/**
	 * A fieldset with the two halves.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'group';
	}

	/**
	 * The two halves, normalised.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return list<array<string, mixed>>
	 */
	public static function halves( array $field ): array {
		$halves = array();
		foreach ( array_slice( (array) $field['fields'], 0, 2 ) as $half ) {
			if ( is_array( $half ) && in_array( $half['type'] ?? 'text', self::SIMPLE, true ) ) {
				$normal = Kit::instance()->fields()->normalize( $half );
				if ( $normal ) {
					$halves[] = $normal;
				}
			}
		}

		return $halves;
	}

	/**
	 * The two halves, side by side.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$registry = $context->kit->fields();
		$value    = (array) $context->value;
		$html     = '<div class="bwc-pair">';

		foreach ( self::halves( $context->field ) as $half ) {
			$sub   = $context->sub( $half, $value[ $half['id'] ] ?? $half['default'] );
			$type  = $registry->get( (string) $half['type'] );
			$html .= '<div class="bwc-pair__half"><label class="bwc-pair__label" for="' . esc_attr( $sub->id ) . '">' . esc_html( (string) $half['label'] ) . '</label>'
				. ( null === $type ? '' : $type->control( $sub ) )
				. ( '' === (string) $half['unit'] ? '' : ' <span class="bwc-field__unit">' . esc_html( (string) $half['unit'] ) . '</span>' )
				. '</div>';
		}

		return $html . '</div>';
	}

	/**
	 * Each half by its own type and checks.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$registry = Kit::instance()->fields();
		$raw      = is_array( $raw ) ? $raw : array();
		$stored   = (array) $stored;
		$clean    = array();
		$errors   = array();
		$notes    = array();

		foreach ( self::halves( $field ) as $half ) {
			$id     = (string) $half['id'];
			$kept   = $stored[ $id ] ?? $half['default'];
			$type   = $registry->get( (string) $half['type'] );
			$result = null === $type ? Result::rejected( $kept, '' ) : $type->sanitize( $raw[ $id ] ?? '', $kept, $half, $values );

			if ( $result->saved() ) {
				$problem = Validator::check( $half, $result->value, $values );
				if ( null !== $problem ) {
					$result = Result::rejected( $kept, $problem );
				}
			}

			$clean[ $id ] = $result->value;
			if ( '' !== $result->message ) {
				$line = self::say( 'field_part', (string) $half['label'], $result->message );
				if ( $result->error ) {
					$errors[] = $line;
				} else {
					$notes[] = $line;
				}
			}
		}

		if ( $errors ) {
			return Result::partial( $clean, implode( ' ', array_merge( $errors, $notes ) ) );
		}

		return $notes ? Result::adjusted( $clean, implode( ' ', $notes ) ) : Result::ok( $clean );
	}

	/**
	 * An object with the two halves' schemas.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		$registry   = Kit::instance()->fields();
		$properties = array();

		foreach ( self::halves( $field ) as $half ) {
			$type = $registry->get( (string) $half['type'] );
			if ( null !== $type ) {
				$properties[ (string) $half['id'] ] = (array) $type->schema( $half );
			}
		}

		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
	}
}
