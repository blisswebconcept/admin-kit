<?php
/**
 * A text box, number box or textarea in the kit's control style.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Input. type "textarea" prints a
 * textarea; type "number" gets the narrow number box.
 *
 * @phpstan-import-type ControlProps from Control
 */
final class Input {

	use Renders;

	/**
	 * The label and the input.
	 *
	 * @param array<string, mixed> $props id, name, label, label_visible, value, type, placeholder, width, mono, rows, required, disabled, invalid, describedby, auto_submit.
	 * @phpstan-param ControlProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Input', $props, 'id', 'name', 'label' ) ) {
			return '';
		}

		$type  = sanitize_key( (string) ( $props['type'] ?? 'text' ) );
		$value = (string) ( $props['value'] ?? '' );
		$extra = array( 'placeholder' => empty( $props['placeholder'] ) ? null : (string) $props['placeholder'] );

		if ( 'textarea' === $type ) {
			return Control::label( $props ) . '<textarea' . Html::attributes(
				array_merge(
					Control::attributes( $props, array( 'textarea' ) ),
					$extra,
					array( 'rows' => (int) ( $props['rows'] ?? 4 ) )
				)
			) . '>' . esc_textarea( $value ) . '</textarea>';
		}

		return Control::label( $props ) . '<input' . Html::attributes(
			array_merge(
				Control::attributes( $props, 'number' === $type ? array( 'number' ) : array() ),
				array(
					'type'  => '' === $type ? 'text' : $type,
					'value' => $value,
				),
				$extra
			)
		) . '>';
	}
}
