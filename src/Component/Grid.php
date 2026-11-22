<?php
/**
 * Equal columns that fill the width.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Grid: three fixed column minimums
 * (narrow 180px, medium 260px, wide 340px) and a six-across option.
 *
 * @phpstan-type GridProps array{ items_html: list<string>, min?: 'narrow'|'medium'|'wide', columns?: 'auto'|'six', gap?: 2|3|4 }
 */
final class Grid {

	use Renders;

	/**
	 * The grid.
	 *
	 * @param array<string, mixed> $props items_html, min, columns, gap.
	 * @phpstan-param GridProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Grid', $props, 'items_html' ) ) {
			return '';
		}

		$min = (string) Html::pick( $props['min'] ?? 'medium', array( 'narrow', 'medium', 'wide' ), 'medium' );
		$gap = Html::pick( $props['gap'] ?? 3, array( 2, 3, 4 ), 3 );

		return '<div class="' . esc_attr(
			Html::classes(
				'bwc-grid',
				array(
					'min-' . $min,
					'gap-' . $gap,
					'six' => 'six' === ( $props['columns'] ?? 'auto' ),
				)
			)
		) . '">'
			. implode( '', (array) $props['items_html'] )
			. '</div>';
	}
}
