<?php
/**
 * A folded table of reference facts.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Reference table: dynamic tags, shortcodes or
 * hooks in a <details>. Cells in mono columns are code with a Copy button.
 *
 * @phpstan-type ReferenceProps array{ summary: string, columns: array<string, string>, rows: list<array<string, string>>, mono?: list<string>, open?: bool }
 */
final class Reference {

	use Renders;

	/**
	 * The folded table.
	 *
	 * @param array<string, mixed> $props summary, columns, rows, mono, open.
	 * @phpstan-param ReferenceProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Reference', $props, 'summary', 'columns' ) ) {
			return '';
		}

		$copy    = Kit::instance()->strings()->get( 'copy' );
		$mono    = array_map( 'strval', (array) ( $props['mono'] ?? array() ) );
		$columns = array();
		foreach ( (array) $props['columns'] as $key => $label ) {
			$columns[ (string) $key ] = array( 'label' => (string) $label );
		}

		$rows = array();
		foreach ( (array) ( $props['rows'] ?? array() ) as $row ) {
			$cells = array();
			foreach ( $columns as $key => $column ) {
				$value         = (string) ( $row[ $key ] ?? '' );
				$cells[ $key ] = in_array( $key, $mono, true ) && '' !== $value
					? '<span class="bwc-reference__code"><code>' . esc_html( $value ) . '</code>' . CopyButton::markup(
						array(
							'value' => $value,
							'label' => $copy . ' ' . $value,
						)
					) . '</span>'
					: esc_html( $value );
			}
			$rows[] = array( 'cells' => $cells );
		}

		return '<details class="bwc-reference"' . ( empty( $props['open'] ) ? '' : ' open' ) . '>'
			. '<summary class="bwc-reference__summary"><span class="bwc-reference__chevron" aria-hidden="true">' . Icon::markup(
				array(
					'name' => 'chevron',
					'size' => 14,
				)
			) . '</span>' . esc_html( (string) $props['summary'] ) . '</summary>'
			. '<div class="bwc-reference__body">' . Table::markup(
				array(
					'caption'   => (string) $props['summary'],
					'columns'   => $columns,
					'rows'      => $rows,
					'compact'   => true,
					'min_width' => 'none',
				)
			) . '</div></details>';
	}
}
