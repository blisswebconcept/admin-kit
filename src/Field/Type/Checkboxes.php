<?php
/**
 * Pick any of a known set.
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
 * Field type "checkboxes". 'store' => 'unchecked' saves
 * the options that were NOT ticked, so options added later start ticked.
 */
class Checkboxes extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'checkboxes';
	}

	/**
	 * Extra keys: show_value, columns, store.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'    => array(),
			'show_value' => false,
			'columns'    => 1,
			'store'      => 'checked',
		);
	}

	/**
	 * A fieldset of boxes.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'group';
	}

	/**
	 * No box ticked posts nothing.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return true;
	}

	/**
	 * The boxes.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field    = $context->field;
		$elements = self::elements( $field );
		$value    = array_map( 'strval', (array) $context->value );
		$ticked   = 'unchecked' === $field['store'] ? array_diff( array_keys( $elements ), $value ) : $value;

		return self::list_markup( $context, $elements, array_map( 'strval', $ticked ), 'checkbox', $context->name . '[]', (bool) $field['show_value'], (int) $field['columns'] );
	}

	/**
	 * A list of boxes or radio buttons.
	 *
	 * @param Context               $context      Field context.
	 * @param array<int|string, string> $elements     Value => label.
	 * @param list<string>          $on           Values that are ticked.
	 * @param string                $input        "checkbox" or "radio".
	 * @param string                $name         Input name.
	 * @param bool                  $show_value   Print the value in code after the label.
	 * @param int                   $columns      1 to 3.
	 * @param array<string, string> $descriptions Value => description.
	 * @param array<string, string> $extras       Value => escaped HTML after the option (a radio's inline field).
	 */
	public static function list_markup( Context $context, array $elements, array $on, string $input, string $name, bool $show_value = false, int $columns = 1, array $descriptions = array(), array $extras = array() ): string {
		$html = '<ul class="' . esc_attr( Html::classes( 'bwc-choices', array( $columns > 1 ? 'columns-' . min( 3, $columns ) : '' ) ) ) . '">';
		$i    = 0;

		foreach ( $elements as $value => $label ) {
			$id    = $context->id . '-' . ( ++$i );
			$html .= '<li class="bwc-choices__item"><label class="bwc-choices__label" for="' . esc_attr( $id ) . '"><input' . Html::attributes(
				array(
					'type'             => $input,
					'id'               => $id,
					'name'             => $name,
					'value'            => (string) $value,
					'checked'          => in_array( (string) $value, $on, true ),
					'disabled'         => $context->read_only,
					'aria-describedby' => isset( $descriptions[ $value ] ) ? $id . '-desc' : null,
				)
			) . '><span class="bwc-choices__text">' . esc_html( $label )
				. ( $show_value ? ' <code>' . esc_html( (string) $value ) . '</code>' : '' )
				. '</span></label>'
				. ( isset( $descriptions[ $value ] ) ? '<p class="bwc-choices__description" id="' . esc_attr( $id . '-desc' ) . '">' . esc_html( $descriptions[ $value ] ) . '</p>' : '' )
				. ( $extras[ $value ] ?? '' )
				. '</li>';
		}

		return $html . '</ul>';
	}

	/**
	 * Only offered values.
	 *
	 * @param mixed                $raw    Submitted value, or null.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$offered = array_map( 'strval', array_keys( self::elements( $field ) ) );
		$chosen  = array_values( array_intersect( $offered, array_map( 'strval', is_array( $raw ) ? $raw : array() ) ) );

		return Result::ok( 'unchecked' === $field['store'] ? array_values( array_diff( $offered, $chosen ) ) : $chosen );
	}

	/**
	 * An array of the offered values.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
				'enum' => array_map( 'strval', array_keys( self::elements( $field ) ) ),
			),
			'uniqueItems' => true,
		);
	}
}
