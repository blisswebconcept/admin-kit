<?php
/**
 * On/off for a feature: a real checkbox drawn as a switch.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Switch. Named Toggle because "switch" is a
 * reserved word in PHP; the CSS block is bwc-switch.
 *
 * @phpstan-type ToggleProps array{ id: string, name: string, checked: bool, labelledby: string, describedby?: string, value?: string, on_text?: string, off_text?: string, disabled?: bool, hook?: string }
 */
final class Toggle {

	use Renders;

	/**
	 * The switch.
	 *
	 * @param array<string, mixed> $props id, name, checked, labelledby, describedby, value, on_text, off_text, disabled, hook.
	 * @phpstan-param ToggleProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Toggle', $props, 'id', 'name', 'labelledby' ) ) {
			return '';
		}

		$strings = Kit::instance()->strings();
		$hook    = empty( $props['hook'] ) ? '' : sanitize_key( (string) $props['hook'] );
		$input   = array(
			'class'            => 'bwc-switch__input',
			'type'             => 'checkbox',
			'role'             => 'switch',
			'id'               => (string) $props['id'],
			'name'             => (string) $props['name'],
			'value'            => (string) ( $props['value'] ?? '1' ),
			'checked'          => ! empty( $props['checked'] ),
			'disabled'         => ! empty( $props['disabled'] ),
			'aria-labelledby'  => (string) $props['labelledby'],
			'aria-describedby' => empty( $props['describedby'] ) ? null : (string) $props['describedby'],
		);

		if ( '' !== $hook ) {
			$input[ 'data-bwc-' . $hook ] = true;
		}

		return '<label class="bwc-switch"><input' . Html::attributes( $input ) . '>'
			. '<span class="bwc-switch__state" aria-hidden="true"><span class="bwc-switch__on">' . esc_html( (string) ( $props['on_text'] ?? $strings->get( 'on' ) ) ) . '</span>'
			. '<span class="bwc-switch__off">' . esc_html( (string) ( $props['off_text'] ?? $strings->get( 'off' ) ) ) . '</span></span>'
			. '<span class="bwc-switch__track" aria-hidden="true"></span></label>';
	}
}
