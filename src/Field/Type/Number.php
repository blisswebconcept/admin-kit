<?php
/**
 * A whole number or decimal with limits.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "number". Not a number: rejected. Out of
 * range: clamped to the nearest limit, saved, and the owner is told. Empty:
 * back to the default.
 */
class Number extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'number';
	}

	/**
	 * Extra keys: decimals (0 stores whole numbers).
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'  => 0,
			'decimals' => 0,
		);
	}

	/**
	 * The number box.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field = $context->field;
		$valid = (array) $field['isValid'];
		$step  = $valid['step'] ?? ( (int) $field['decimals'] > 0 ? 1 / ( 10 ** (int) $field['decimals'] ) : 1 );

		return '<input' . Html::attributes(
			array_merge(
				array(
					'type'      => 'number',
					'class'     => 'bwc-control bwc-control--number',
					'value'     => (string) $context->value,
					'inputmode' => (int) $field['decimals'] > 0 ? 'decimal' : 'numeric',
				),
				$context->attributes(),
				array(
					'min'  => $valid['min'] ?? null,
					'max'  => $valid['max'] ?? null,
					'step' => $step,
				)
			)
		) . '>';
	}

	/**
	 * A number, clamped into range.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$text = is_scalar( $raw ) ? trim( (string) $raw ) : '';

		if ( '' === $text ) {
			// Left empty: the default, unless the field must be filled in.
			return empty( $field['isValid']['required'] ) ? Result::ok( $field['default'] ) : Result::rejected( $stored, self::say( 'field_required' ) );
		}

		if ( ! is_numeric( $text ) ) {
			return Result::rejected( $stored, self::say( 'field_not_number' ) );
		}

		$decimals = (int) $field['decimals'];
		$number   = $decimals > 0 ? round( (float) $text, $decimals ) : (int) round( (float) $text );
		$valid    = (array) $field['isValid'];

		if ( isset( $valid['min'] ) && $number < $valid['min'] ) {
			$number = $decimals > 0 ? (float) $valid['min'] : (int) $valid['min'];
			return Result::adjusted( $number, self::say( 'field_clamped_min', Html::number( $number ) ) );
		}

		if ( isset( $valid['max'] ) && $number > $valid['max'] ) {
			$number = $decimals > 0 ? (float) $valid['max'] : (int) $valid['max'];
			return Result::adjusted( $number, self::say( 'field_clamped_max', Html::number( $number ) ) );
		}

		return Result::ok( $number );
	}

	/**
	 * An integer or number with its limits.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		$valid = (array) $field['isValid'];

		return array_filter(
			array(
				'type'    => (int) $field['decimals'] > 0 ? 'number' : 'integer',
				'minimum' => $valid['min'] ?? null,
				'maximum' => $valid['max'] ?? null,
			),
			static fn( $value ): bool => null !== $value
		);
	}
}
