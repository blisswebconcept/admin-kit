<?php
/**
 * Records grouped under headings, the rest of each group folded.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Grouped list. One visible column row, then each
 * group's lead rows and a <details> with the rest. Every table shares one
 * <colgroup>, so the columns line up; each has its own hidden header row and
 * caption for screen readers, and the visible column row is decorative.
 *
 * @phpstan-type GroupedRow array{ cells: array<string, string> }
 * @phpstan-type GroupedGroup array{ title: string, meta?: string, lead: list<GroupedRow>, rest?: list<GroupedRow>, rest_label?: string, open?: bool }
 * @phpstan-type GroupedListProps array{ caption: string, columns: array<string, array{ label: string, align?: 'start'|'end', width?: 'narrow'|'medium'|'wide'|'auto', hide_label?: bool }>, groups: list<GroupedGroup>, min_width?: 'medium'|'wide' }
 */
final class GroupedList {

	use Renders;

	/**
	 * The list.
	 *
	 * @param array<string, mixed> $props caption, columns, groups.
	 * @phpstan-param GroupedListProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'GroupedList', $props, 'caption', 'columns' ) ) {
			return '';
		}

		$strings  = Kit::instance()->strings();
		$columns  = (array) $props['columns'];
		$colgroup = Table::colgroup( $columns );
		$heads    = '';
		$visible  = '';
		foreach ( $columns as $column ) {
			$cell     = '<th scope="col"' . ( 'end' === ( $column['align'] ?? 'start' ) ? ' class="bwc-table__number"' : '' ) . '>';
			$heads   .= $cell . esc_html( (string) ( $column['label'] ?? '' ) ) . '</th>';
			$visible .= str_replace( ' scope="col"', '', $cell ) . ( empty( $column['hide_label'] ) ? esc_html( (string) ( $column['label'] ?? '' ) ) : '' ) . '</th>';
		}

		$html = '<div class="bwc-grouped' . ( 'wide' === ( $props['min_width'] ?? 'medium' ) ? ' bwc-grouped--wide' : '' ) . '" tabindex="0" role="region" aria-label="' . esc_attr( (string) $props['caption'] ) . '">'
			. '<table class="bwc-grouped__columns" aria-hidden="true">' . $colgroup . '<tr>' . $visible . '</tr></table>';

		foreach ( (array) ( $props['groups'] ?? array() ) as $group ) {
			$title    = (string) ( $group['title'] ?? '' );
			$title_id = Html::id( 'group' );
			$table    = static fn( array $rows ): string => '<table class="bwc-grouped__table"><caption class="bwc-visually-hidden">' . esc_html( $title ) . '</caption>'
				. $colgroup . '<thead class="bwc-visually-hidden"><tr>' . $heads . '</tr></thead><tbody>' . self::rows( $rows, $columns ) . '</tbody></table>';

			$html .= '<section class="bwc-grouped__group" aria-labelledby="' . esc_attr( $title_id ) . '">'
				. '<div class="bwc-grouped__head"><h3 class="bwc-grouped__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $title ) . '</h3>'
				. ( empty( $group['meta'] ) ? '' : '<p class="bwc-grouped__meta">' . esc_html( (string) $group['meta'] ) . '</p>' )
				. '</div>'
				. $table( (array) ( $group['lead'] ?? array() ) );

			if ( ! empty( $group['rest'] ) ) {
				$label = (string) ( $group['rest_label'] ?? '' );
				$html .= '<details class="bwc-grouped__more"' . ( empty( $group['open'] ) ? '' : ' open' ) . '><summary class="bwc-grouped__summary">'
					. '<span class="bwc-grouped__chevron" aria-hidden="true">' . Icon::markup(
						array(
							'name' => 'chevron',
							'size' => 14,
						)
					) . '</span>'
					. '<span class="bwc-grouped__more-show">' . esc_html( $strings->get( 'show_more', $label ) ) . '</span>'
					. '<span class="bwc-grouped__more-hide">' . esc_html( $strings->get( 'hide_more', $label ) ) . '</span>'
					. '</summary>' . $table( (array) $group['rest'] ) . '</details>';
			}

			$html .= '</section>';
		}

		return $html . '</div>';
	}

	/**
	 * Table rows.
	 *
	 * @param array<int, array<string, mixed>>    $rows    Rows.
	 * @param array<string, array<string, mixed>> $columns Columns.
	 */
	private static function rows( array $rows, array $columns ): string {
		$html = '';

		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $columns as $key => $column ) {
				$html .= '<td' . ( 'end' === ( $column['align'] ?? 'start' ) ? ' class="bwc-table__number"' : '' ) . '>' . ( $row['cells'][ $key ] ?? '' ) . '</td>';
			}
			$html .= '</tr>';
		}

		return $html;
	}
}
