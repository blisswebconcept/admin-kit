<?php
/**
 * An input with fixed text before it.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Prefixed input. The prefix is hidden from
 * screen readers, so the help text named by describedby should repeat it.
 *
 * @phpstan-type PrefixedProps array{ id: string, name: string, value: string, prefix: string, label: string, label_visible?: bool, required?: bool, describedby?: string }
 */
final class Prefixed {

	use Renders;

	/**
	 * The label and the prefixed input.
	 *
	 * @param array<string, mixed> $props id, name, value, prefix, label, label_visible, required, describedby.
	 * @phpstan-param PrefixedProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Prefixed', $props, 'id', 'name', 'prefix', 'label' ) ) {
			return '';
		}

		// The prefix is read once, as the input's description, not again as loose text.
		$prefix_id = (string) $props['id'] . '-prefix';

		return Control::label( $props ) . '<div class="bwc-prefixed"><span class="bwc-prefixed__prefix" id="' . esc_attr( $prefix_id ) . '" aria-hidden="true">' . esc_html( (string) $props['prefix'] ) . '</span>'
			. '<input' . Html::attributes(
				array(
					'class'            => 'bwc-prefixed__input',
					'type'             => 'text',
					'id'               => (string) $props['id'],
					'name'             => (string) $props['name'],
					'value'            => (string) ( $props['value'] ?? '' ),
					'required'         => ! empty( $props['required'] ),
					'aria-describedby' => trim( $prefix_id . ' ' . (string) ( $props['describedby'] ?? '' ) ),
				)
			) . '></div>';
	}
}
