<?php
/**
 * Two to four mutually exclusive views.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Segmented control: links, so the view is in
 * the URL and works without the script.
 *
 * @phpstan-type SegmentedProps array{ label: string, current: string, items: array<string, array{ label: string, count?: int, url: string }> }
 */
final class Segmented {

	use Renders;

	/**
	 * The control.
	 *
	 * @param array<string, mixed> $props label, current, items.
	 * @phpstan-param SegmentedProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Segmented', $props, 'label', 'items' ) ) {
			return '';
		}

		$current = (string) ( $props['current'] ?? '' );
		$html    = '<div class="bwc-segmented" role="group" aria-label="' . esc_attr( (string) $props['label'] ) . '">';

		foreach ( (array) $props['items'] as $key => $item ) {
			$on    = (string) $key === $current;
			$html .= '<a' . Html::attributes(
				array(
					'class'        => 'bwc-segmented__option' . ( $on ? ' is-current' : '' ),
					'href'         => esc_url( (string) ( $item['url'] ?? '#' ) ),
					'aria-current' => $on ? 'true' : null,
				)
			) . '>' . esc_html( (string) ( $item['label'] ?? $key ) )
				. ( isset( $item['count'] ) ? ' <span class="bwc-segmented__count">' . esc_html( Html::number( (int) $item['count'] ) ) . '</span>' : '' )
				. '</a>';
		}

		return $html . '</div>';
	}
}
