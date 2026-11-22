<?php
/**
 * A short status or count label.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Pill.
 *
 * @phpstan-type PillProps array{ text: string, tone?: string, dot?: bool, shape?: 'round'|'square', outline?: bool }
 */
final class Pill {

	use Renders;

	/**
	 * The pill.
	 *
	 * @param array<string, mixed> $props text, tone, dot, shape, outline.
	 * @phpstan-param PillProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Pill', $props, 'text' ) ) {
			return '';
		}

		$tone = Html::tone( $props['tone'] ?? 'neutral' );

		return '<span class="' . esc_attr(
			Html::classes(
				'bwc-pill',
				array(
					$tone,
					'square'  => 'square' === ( $props['shape'] ?? 'round' ),
					'outline' => ! empty( $props['outline'] ),
				)
			)
		) . '">'
			. ( empty( $props['dot'] ) ? '' : '<span class="bwc-pill__dot" aria-hidden="true"></span>' )
			. esc_html( (string) $props['text'] )
			. '</span>';
	}
}
