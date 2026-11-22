<?php
/**
 * Turning a feature on or off.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Toggle;
use BlissWebConcept\AdminKit\Field\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "switch"; the class is OnOff because
 * "switch" is a reserved word in PHP. Stored and sanitized like a checkbox.
 */
class OnOff extends Checkbox {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'switch';
	}

	/**
	 * The label, then the switch, labelled by it.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field = $context->field;

		return '<div class="bwc-field__switch"><span class="bwc-field__check-body"><span class="bwc-field__check-title" id="' . esc_attr( $context->id . '-label' ) . '">' . esc_html( (string) $field['label'] ) . '</span>'
			. ( '' === (string) $field['checkbox_label'] ? '' : '<span class="bwc-field__check-text">' . esc_html( (string) $field['checkbox_label'] ) . '</span>' )
			. '</span>'
			. Toggle::markup(
				array(
					'id'          => $context->id,
					'name'        => $context->name,
					'checked'     => (bool) $context->value,
					'labelledby'  => $context->id . '-label',
					'describedby' => $context->describedby,
					'disabled'    => $context->read_only,
				)
			) . '</div>';
	}
}
