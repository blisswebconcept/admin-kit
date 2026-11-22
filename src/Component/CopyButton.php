<?php
/**
 * Copies a value and shows it worked.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Copy button. Hidden until the script runs,
 * since it can't copy without it; the value itself stays on the page next to it.
 *
 * @phpstan-type CopyButtonProps array{ value: string, label: string, icon_only?: bool, size?: 'regular'|'small' }
 */
final class CopyButton {

	use Renders;

	/**
	 * The button.
	 *
	 * @param array<string, mixed> $props value, label, icon_only, size.
	 * @phpstan-param CopyButtonProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'CopyButton', $props, 'value', 'label' ) ) {
			return '';
		}

		$icon_only = $props['icon_only'] ?? true;
		$icons     = '<span class="bwc-copy__icon" aria-hidden="true">' . Icon::markup( array( 'name' => 'copy' ) ) . '</span>'
			. '<span class="bwc-copy__done" aria-hidden="true">' . Icon::markup( array( 'name' => 'check' ) ) . '</span>';

		return '<button' . Html::attributes(
			array(
				'type'          => 'button',
				'class'         => Html::classes(
					'bwc-button',
					array(
						'small' => 'regular' !== ( $props['size'] ?? 'small' ),
						'icon'  => (bool) $icon_only,
					),
					'bwc-copy'
				),
				'data-bwc-copy' => (string) $props['value'],
				'aria-label'    => $icon_only ? (string) $props['label'] : null,
				'hidden'        => true,
			)
		) . '>' . $icons
			. ( $icon_only ? '' : '<span class="bwc-copy__text" data-bwc-copy-text>' . esc_html( (string) $props['label'] ) . '</span>' )
			. '</button>';
	}
}
