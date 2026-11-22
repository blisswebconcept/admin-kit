<?php
/**
 * Choose some items from a set, and put them in order.
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
 * Field type "ordered".
 * Without scripts it's a list, one choice per line, in order, with the
 * choices named in the help; the ordered-chips script turns it into chips
 * that can be dragged, moved with arrow keys, removed and added.
 * Stored: a list of element values, in order.
 */
class Ordered extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'ordered';
	}

	/**
	 * Extra keys: max_items, add_label.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'   => array(),
			'max_items' => 0,
			'add_label' => '',
		);
	}

	/**
	 * The list, and what the script needs to turn it into chips.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$elements = self::elements( $context->field );
		$chosen   = array_values( array_filter( array_map( 'strval', (array) $context->value ), static fn( string $value ): bool => isset( $elements[ $value ] ) ) );
		$options  = array();
		foreach ( $elements as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => $label,
			);
		}

		return '<div' . Html::attributes(
			array(
				'class'                   => 'bwc-ordered',
				'data-bwc-ordered'        => (string) wp_json_encode( $options ),
				'data-bwc-ordered-name'   => $context->name . '[]',
				'data-bwc-ordered-chosen' => (string) wp_json_encode( $chosen ),
				'data-bwc-ordered-max'    => (string) (int) $context->field['max_items'],
				'data-bwc-ordered-add'    => (string) $context->field['add_label'],
				'data-bwc-ordered-id'     => $context->id,
			)
		) . '><textarea' . Html::attributes(
			array_merge(
				array(
					'class'                     => 'bwc-control bwc-control--textarea bwc-control--list bwc-control--full',
					'rows'                      => (string) max( 3, min( 8, count( $chosen ) + 1 ) ),
					'data-bwc-ordered-fallback' => true,
				),
				$context->attributes()
			)
		) . '>' . esc_textarea( implode( "\n", array_map( static fn( string $value ): string => $elements[ $value ], $chosen ) ) ) . '</textarea>'
			// Without the script, the choices it can name; the script removes this with the list.
			. ( $elements ? '<p class="bwc-ordered__choices" data-bwc-ordered-choices>' . esc_html( self::say( 'ordered_fallback', implode( ', ', array_values( $elements ) ) ) ) . '</p>' : '' )
			. '</div>';
	}

	/**
	 * An empty list posts nothing.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return true;
	}

	/**
	 * Offered values in the order given, once each, up to max_items. Lines
	 * may name a choice by its label or its value.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$elements = self::elements( $field );
		$by_label = array();
		foreach ( $elements as $value => $label ) {
			$by_label[ mb_strtolower( $label ) ] = (string) $value;
		}

		$given = is_array( $raw ) ? $raw : preg_split( '/[\r\n]+/', is_scalar( $raw ) ? (string) $raw : '' );
		$kept  = array();
		$bad   = array();

		foreach ( (array) $given as $one ) {
			$one = is_scalar( $one ) ? trim( (string) $one ) : '';
			if ( '' === $one ) {
				continue;
			}
			$value = isset( $elements[ $one ] ) ? $one : ( $by_label[ mb_strtolower( $one ) ] ?? null );
			if ( null === $value ) {
				$bad[] = $one;
			} elseif ( ! in_array( $value, $kept, true ) ) {
				$kept[] = $value;
			}
		}

		$max  = (int) $field['max_items'];
		$note = '';
		if ( $max > 0 && count( $kept ) > $max ) {
			$kept = array_slice( $kept, 0, $max );
			$note = \BlissWebConcept\AdminKit\Kit::instance()->strings()->count( 'field_max_items', $max );
		}

		if ( $bad ) {
			return Result::partial( $kept, trim( self::say( 'field_lines_rejected', implode( ', ', $bad ) ) . ' ' . $note ) );
		}

		return '' === $note ? Result::ok( $kept ) : Result::adjusted( $kept, $note );
	}

	/**
	 * A list of the offered values.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'  => 'array',
			'items' => array(
				'type' => 'string',
				'enum' => array_map( 'strval', array_keys( self::elements( $field ) ) ),
			),
		);
	}

	/**
	 * The ordered-chips script.
	 *
	 * @return list<string>
	 */
	public function scripts(): array {
		return array( 'ordered-chips' );
	}
}
