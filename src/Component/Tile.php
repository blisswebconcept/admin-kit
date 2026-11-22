<?php
/**
 * One number with a label and an optional note.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Tile. The label comes first in the source so
 * it's read before the number.
 *
 * @phpstan-type TileProps array{ label: string, value: int|float|string, note?: string, tone?: 'neutral'|'danger'|'warning'|'success', url?: string, soft?: bool }
 */
final class Tile {

	use Renders;

	/**
	 * The tile.
	 *
	 * @param array<string, mixed> $props label, value, note, tone, url, soft.
	 * @phpstan-param TileProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Tile', $props, 'label' ) || ! isset( $props['value'] ) ) {
			return '';
		}

		$tone  = Html::tone( $props['tone'] ?? 'neutral', array( 'neutral', 'danger', 'warning', 'success' ) );
		$url   = isset( $props['url'] ) ? (string) $props['url'] : '';
		$class = Html::classes(
			'bwc-tile',
			array(
				'soft' => ! empty( $props['soft'] ),
				'neutral' === $tone ? '' : $tone,
				'link' => '' !== $url,
			)
		);
		$inner = '<span class="bwc-tile__label">' . esc_html( (string) $props['label'] ) . '</span>'
			. '<span class="bwc-tile__value">' . esc_html( Html::number( $props['value'] ) ) . '</span>'
			. ( empty( $props['note'] ) ? '' : '<span class="bwc-tile__note">' . esc_html( (string) $props['note'] ) . '</span>' );

		return '' === $url
			? '<div class="' . esc_attr( $class ) . '">' . $inner . '</div>'
			: '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . $inner . '</a>';
	}
}
