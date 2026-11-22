<?php
/**
 * Pick one from a list too long for radio buttons.
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
 * Field type "select". With 'multiple' => true it shows
 * as checkboxes: native multi-selects are hard to use. A string placeholder adds an
 * empty first option.
 */
class Select extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'select';
	}

	/**
	 * Extra keys: multiple.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'  => '',
			'multiple' => false,
		);
	}

	/**
	 * One select, or a fieldset of boxes when multiple.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return empty( $field['multiple'] ) ? 'input' : 'group';
	}

	/**
	 * Multiple: no box ticked posts nothing.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return ! empty( $field['multiple'] );
	}

	/**
	 * The select or the boxes.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field    = $context->field;
		$elements = $this->options( $field );

		if ( ! empty( $field['multiple'] ) ) {
			return Checkboxes::list_markup( $context, $elements, array_map( 'strval', (array) $context->value ), 'checkbox', $context->name . '[]' );
		}

		$value   = (string) $context->value;
		$options = '';
		if ( is_string( $field['placeholder'] ) && '' !== $field['placeholder'] ) {
			$options .= '<option value="">' . esc_html( $field['placeholder'] ) . '</option>';
		}
		foreach ( $elements as $key => $label ) {
			$options .= '<option value="' . esc_attr( (string) $key ) . '"' . selected( $value, (string) $key, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return '<select' . Html::attributes(
			array_merge(
				array( 'class' => 'bwc-control' ),
				$context->attributes( false ),
				array( 'disabled' => $context->read_only )
			)
		) . '>' . $options . '</select>';
	}

	/**
	 * The options, value => label.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<int|string, string>
	 */
	protected function options( array $field ): array {
		return self::elements( $field );
	}

	/**
	 * Offered value(s) only.
	 *
	 * @param mixed                $raw    Submitted value, or null.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$offered = array_map( 'strval', array_keys( $this->options( $field ) ) );

		if ( ! empty( $field['multiple'] ) ) {
			return Result::ok( array_values( array_intersect( $offered, array_map( 'strval', is_array( $raw ) ? $raw : array() ) ) ) );
		}

		$value = is_scalar( $raw ) ? (string) $raw : '';
		if ( '' === $value || in_array( $value, $offered, true ) ) {
			return Result::ok( $value );
		}

		return Result::rejected( $stored, self::say( 'field_choice' ) );
	}

	/**
	 * A string or array from the offered values.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		$enum = array_map( 'strval', array_keys( $this->options( $field ) ) );

		return empty( $field['multiple'] )
			? array(
				'type' => 'string',
				'enum' => array_merge( array( '' ), $enum ),
			)
			: array(
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
					'enum' => $enum,
				),
				'uniqueItems' => true,
			);
	}
}
