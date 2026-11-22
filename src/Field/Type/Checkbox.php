<?php
/**
 * One yes/no setting with a sentence.
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
 * Field type "checkbox": the label is the bold first
 * line inside the box's own label, checkbox_label the plain second line.
 */
class Checkbox extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'checkbox';
	}

	/**
	 * Extra keys: checkbox_label.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'        => false,
			'checkbox_label' => '',
		);
	}

	/**
	 * The control holds its own label.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'check';
	}

	/**
	 * An unticked box posts nothing.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return true;
	}

	/**
	 * The box and its two lines.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field = $context->field;

		return '<label class="bwc-field__check"><input' . Html::attributes(
			array_merge(
				array(
					'type'    => 'checkbox',
					'value'   => '1',
					'checked' => (bool) $context->value,
				),
				$context->attributes( false ),
				array( 'disabled' => $context->read_only )
			)
		) . '><span class="bwc-field__check-body"><span class="bwc-field__check-title">' . esc_html( (string) $field['label'] ) . '</span>'
			. ( '' === (string) $field['checkbox_label'] ? '' : '<span class="bwc-field__check-text">' . esc_html( (string) $field['checkbox_label'] ) . '</span>' )
			. '</span></label>';
	}

	/**
	 * Posted means on; marker only means off.
	 *
	 * @param mixed                $raw    Submitted value, or null.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		return Result::ok( null !== $raw && true === rest_sanitize_boolean( is_scalar( $raw ) ? (string) $raw : '' ) );
	}

	/**
	 * A boolean.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array( 'type' => 'boolean' );
	}
}
