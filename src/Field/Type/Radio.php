<?php
/**
 * Pick exactly one, with room to explain each option.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "radio". Each element may have a
 * description and its own field ("Give partial credit from [80] %"): the
 * form saves that field under its own key, active only while its option is
 * chosen, and the radio prints it inside the option.
 */
class Radio extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'radio';
	}

	/**
	 * A fieldset of radio buttons.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'group';
	}

	/**
	 * The radio buttons.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		return Checkboxes::list_markup( $context, self::elements( $context->field ), array( (string) $context->value ), 'radio', $context->name, false, 1, self::element_descriptions( $context->field ), $this->inline_fields( $context ) );
	}

	/**
	 * Options' own fields, each inside its option: inert unless that option is
	 * chosen, named by the option's text.
	 *
	 * @param Context $context The radio's context.
	 * @return array<string, string> Value => escaped HTML.
	 */
	private function inline_fields( Context $context ): array {
		$registry = $context->kit->fields();
		$set      = preg_match( '/\[([^\[\]]+)\]\[[^\[\]]+\]$/', $context->name, $match ) ? $match[1] : '';
		$extras   = array();
		$i        = 0;

		foreach ( (array) $context->field['elements'] as $element ) {
			if ( ! is_array( $element ) || ! isset( $element['value'] ) ) {
				continue;
			}
			++$i;
			if ( ! isset( $element['field'] ) || ! is_array( $element['field'] ) ) {
				continue;
			}

			$value  = (string) $element['value'];
			$inline = $registry->normalize( $element['field'] + array( 'label' => (string) ( $element['label'] ?? $value ) ) );
			$type   = $inline ? $registry->get( (string) $inline['type'] ) : null;
			if ( null === $type ) {
				continue;
			}

			$sibling = $context->sibling( $inline );
			$control = (string) preg_replace( '/<(input|select|textarea)\b/', '<$1 aria-label="' . esc_attr( (string) ( $element['label'] ?? $value ) ) . '"', $type->control( $sibling ), 1 );
			$chosen  = (string) $context->value === $value;

			$extras[ $value ] = '<span class="bwc-choices__inline" data-bwc-depends="' . esc_attr( $set . '.' . (string) $context->field['id'] ) . '" data-bwc-depends-is="' . esc_attr( (string) wp_json_encode( array( $value ) ) ) . '"' . ( $chosen ? '' : ' inert' ) . '>'
				. $control . ( '' === (string) $inline['unit'] ? '' : ' <span class="bwc-field__unit">' . esc_html( (string) $inline['unit'] ) . '</span>' ) . '</span>';
		}

		return $extras;
	}

	/**
	 * One of the offered values.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$value = is_scalar( $raw ) ? (string) $raw : '';

		if ( '' === $value || array_key_exists( $value, self::elements( $field ) ) ) {
			return Result::ok( $value );
		}

		return Result::rejected( $stored, self::say( 'field_choice' ) );
	}

	/**
	 * A string from the offered values.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type' => 'string',
			'enum' => array_merge( array( '' ), array_map( 'strval', array_keys( self::elements( $field ) ) ) ),
		);
	}
}
