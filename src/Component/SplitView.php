<?php
/**
 * A side column and a main column.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * SplitView. Named SplitView because
 * PHPCompatibility reads Split::markup() as the removed split() function.
 * The side goes above the main column when the container is narrower than
 * 760px.
 *
 * @phpstan-type SplitViewProps array{ side_html: string, main_html: string, side_label?: string, side_width?: 'narrow'|'medium' }
 */
final class SplitView {

	use Renders;

	/**
	 * The split layout.
	 *
	 * @param array<string, mixed> $props side_html, main_html, side_label, side_width.
	 * @phpstan-param SplitViewProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'SplitView', $props, 'main_html' ) ) {
			return '';
		}

		$label = isset( $props['side_label'] ) ? (string) $props['side_label'] : '';
		$side  = '' === $label
			? '<div class="bwc-split__side">' . ( $props['side_html'] ?? '' ) . '</div>'
			: '<aside class="bwc-split__side" aria-label="' . esc_attr( $label ) . '">' . ( $props['side_html'] ?? '' ) . '</aside>';

		return '<div class="' . esc_attr( Html::classes( 'bwc-split', array( 'side-narrow' => 'narrow' === ( $props['side_width'] ?? 'medium' ) ) ) ) . '">'
			. $side
			. '<div class="bwc-split__main">' . $props['main_html'] . '</div>'
			. '</div>';
	}
}
