<?php
/**
 * A select in the kit's control style.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Select.
 *
 * @phpstan-import-type ControlProps from Control
 */
final class Select {

	use Renders;

	/**
	 * The label and the select.
	 *
	 * @param array<string, mixed> $props id, name, label, label_visible, value, options, auto_submit, width, required, disabled, invalid, describedby.
	 * @phpstan-param ControlProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Select', $props, 'id', 'name', 'label', 'options' ) ) {
			return '';
		}

		$value   = (string) ( $props['value'] ?? '' );
		$options = '';
		foreach ( (array) $props['options'] as $key => $text ) {
			$options .= '<option value="' . esc_attr( (string) $key ) . '"' . selected( $value, (string) $key, false ) . '>' . esc_html( (string) $text ) . '</option>';
		}

		return Control::label( $props ) . '<select' . Html::attributes( Control::attributes( $props ) ) . '>' . $options . '</select>';
	}
}
