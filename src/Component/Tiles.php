<?php
/**
 * A row of tiles.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Tiles. The label names the group for screen readers.
 *
 * @phpstan-type TilesProps array{ label: string, tiles: list<array<string, mixed>>, columns?: 'auto'|'six' }
 */
final class Tiles {

	use Renders;

	/**
	 * The row.
	 *
	 * @param array<string, mixed> $props label, tiles, columns.
	 * @phpstan-param TilesProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Tiles', $props, 'label', 'tiles' ) ) {
			return '';
		}

		$html = '<section class="' . esc_attr( Html::classes( 'bwc-tiles', array( 'six' => 'six' === ( $props['columns'] ?? 'auto' ) ) ) ) . '" aria-label="' . esc_attr( (string) $props['label'] ) . '">';

		foreach ( (array) $props['tiles'] as $tile ) {
			$html .= Tile::markup( (array) $tile );
		}

		return $html . '</section>';
	}
}
