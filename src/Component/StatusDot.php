<?php
/**
 * A coloured dot before text.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Status dot. `class` adds a plugin's own
 * modifier for colours beyond the six tones, styled in the plugin's CSS.
 *
 * @phpstan-type StatusDotProps array{ tone: string, label: string, hide_label?: bool, class?: string }
 */
final class StatusDot {

	use Renders;

	/**
	 * The dot and its label.
	 *
	 * @param array<string, mixed> $props tone, label, hide_label, class.
	 * @phpstan-param StatusDotProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'StatusDot', $props, 'label' ) ) {
			return '';
		}

		$tone  = Html::tone( $props['tone'] ?? 'neutral' );
		$label = empty( $props['hide_label'] ) ? 'bwc-dot__label' : 'bwc-visually-hidden';

		return '<span class="' . esc_attr( Html::classes( 'bwc-dot', array( $tone ), (string) ( $props['class'] ?? '' ) ) ) . '">'
			. '<span class="bwc-dot__mark" aria-hidden="true"></span>'
			. '<span class="' . $label . '">' . esc_html( (string) $props['label'] ) . '</span>'
			. '</span>';
	}
}
