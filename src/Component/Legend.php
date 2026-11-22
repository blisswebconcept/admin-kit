<?php
/**
 * Names for chart series or bar colours.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Legend.
 *
 * @phpstan-type LegendProps array{ items: list<array{ label: string, series?: 1|2|3|4|5|6, line?: 'solid'|'dashed' }> }
 */
final class Legend {

	use Renders;

	/**
	 * The legend.
	 *
	 * @param array<string, mixed> $props items.
	 * @phpstan-param LegendProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Legend', $props, 'items' ) ) {
			return '';
		}

		$html = '<ul class="bwc-legend">';

		foreach ( (array) $props['items'] as $item ) {
			$series = Html::pick( $item['series'] ?? 1, array( 1, 2, 3, 4, 5, 6 ), 1 );
			$swatch = Html::classes(
				'bwc-legend__swatch',
				array(
					'series-' . $series,
					'dashed' => 'dashed' === ( $item['line'] ?? 'solid' ),
				)
			);
			$html  .= '<li class="bwc-legend__item"><span class="' . esc_attr( $swatch ) . '" aria-hidden="true"></span>' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</li>';
		}

		return $html . '</ul>';
	}
}
