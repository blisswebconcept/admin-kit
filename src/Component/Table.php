<?php
/**
 * Rows of records.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Service\Strings;

defined( 'ABSPATH' ) || exit;

/**
 * Table. Sorting is links, so it works without the
 * script and with pagination. Below its minimum width the table scrolls in a
 * focusable, labelled box instead of squeezing.
 *
 * @phpstan-type TableColumn array{ label: string, align?: 'start'|'end', width?: 'auto'|'narrow'|'medium'|'wide', sort?: array{ url_asc: string, url_desc: string, current?: 'asc'|'desc' }, hide_label?: bool }
 * @phpstan-type TableRow array{ cells: array<string, string>, state?: 'default'|'current'|'quiet', id?: string, label?: string }
 * @phpstan-type TableProps array{
 *     caption: string,
 *     caption_visible?: bool,
 *     columns: array<string, TableColumn>,
 *     rows: list<TableRow>,
 *     bulk?: array{ name: string, actions: array<string, string>, label: string },
 *     compact?: bool,
 *     empty_html?: string,
 *     min_width?: 'none'|'medium'|'wide',
 * }
 */
final class Table {

	use Renders;

	/**
	 * The table, its scrolling box and, with bulk actions, the action picker.
	 *
	 * @param array<string, mixed> $props caption, caption_visible, columns, rows, bulk, compact, empty_html, min_width.
	 * @phpstan-param TableProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Table', $props, 'caption', 'columns' ) ) {
			return '';
		}

		$strings = Kit::instance()->strings();
		$columns = (array) $props['columns'];
		$rows    = (array) ( $props['rows'] ?? array() );
		$bulk    = ! empty( $props['bulk']['name'] ) ? (array) $props['bulk'] : null;
		$caption = (string) $props['caption'];

		$html = '';

		if ( null !== $bulk && ! empty( $bulk['actions'] ) ) {
			$select_id = Html::id( 'bulk' );
			$options   = '<option value="">' . esc_html( $strings->get( 'bulk_actions' ) ) . '</option>';
			foreach ( (array) $bulk['actions'] as $value => $text ) {
				$options .= '<option value="' . esc_attr( (string) $value ) . '">' . esc_html( (string) $text ) . '</option>';
			}
			$html .= '<div class="bwc-table-bulk">'
				. '<label class="bwc-visually-hidden" for="' . esc_attr( $select_id ) . '">' . esc_html( (string) ( $bulk['label'] ?? $strings->get( 'bulk_actions' ) ) ) . '</label>'
				. '<select class="bwc-control" id="' . esc_attr( $select_id ) . '" name="' . esc_attr( $bulk['name'] . '_action' ) . '">' . $options . '</select>'
				. Button::markup(
					array(
						'label' => $strings->get( 'apply' ),
						'type'  => 'submit',
						'size'  => 'small',
					)
				)
				. '</div>';
		}

		$html .= '<div class="bwc-table-wrap" tabindex="0" role="region" data-bwc-table-scroll aria-label="' . esc_attr( $strings->get( 'table_region', $caption ) ) . '">'
			. '<table class="' . esc_attr(
				Html::classes(
					'bwc-table',
					array(
						'compact' => ! empty( $props['compact'] ),
						'none' === ( $props['min_width'] ?? 'medium' ) ? 'fluid' : '',
						'wide' === ( $props['min_width'] ?? 'medium' ) ? 'wide' : '',
					)
				)
			) . '">'
			. '<caption class="' . ( empty( $props['caption_visible'] ) ? 'bwc-visually-hidden' : 'bwc-table__caption' ) . '">' . esc_html( $caption ) . '</caption>'
			. self::colgroup( $columns, null !== $bulk )
			. '<thead><tr>';

		if ( null !== $bulk ) {
			$html .= '<th scope="col" class="bwc-table__check"><input type="checkbox" data-bwc-select-all hidden aria-label="' . esc_attr( $strings->get( 'select_all' ) ) . '"></th>';
		}

		foreach ( $columns as $column ) {
			$html .= self::header( (array) $column, $strings );
		}

		$html .= '</tr></thead><tbody>';

		if ( ! $rows ) {
			$html .= '<tr class="bwc-table__row"><td class="bwc-table__empty" colspan="' . ( count( $columns ) + ( null !== $bulk ? 1 : 0 ) ) . '">'
				. ( $props['empty_html'] ?? esc_html( $strings->get( 'nothing_to_show' ) ) )
				. '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$state = (string) ( $row['state'] ?? 'default' );
			$html .= '<tr class="bwc-table__row' . ( 'current' === $state ? ' is-current' : '' ) . ( 'quiet' === $state ? ' is-quiet' : '' ) . '">';

			if ( null !== $bulk ) {
				$cells = (array) ( $row['cells'] ?? array() );
				$name  = isset( $row['label'] ) ? (string) $row['label'] : trim( wp_strip_all_tags( (string) reset( $cells ) ) );
				$html .= '<td class="bwc-table__check"><input type="checkbox" name="' . esc_attr( $bulk['name'] . '[]' ) . '" value="' . esc_attr( (string) ( $row['id'] ?? '' ) ) . '" aria-label="' . esc_attr( $strings->get( 'select_row', $name ) ) . '"></td>';
			}

			foreach ( $columns as $key => $column ) {
				$html .= '<td' . ( 'end' === ( $column['align'] ?? 'start' ) ? ' class="bwc-table__number"' : '' ) . '>' . ( $row['cells'][ $key ] ?? '' ) . '</td>';
			}

			$html .= '</tr>';
		}

		return $html . '</tbody></table></div>';
	}

	/**
	 * Column widths, shared by every table that must line up with this one.
	 *
	 * @param array<string, array<string, mixed>> $columns Columns.
	 * @param bool                                $check   With a checkbox column.
	 */
	public static function colgroup( array $columns, bool $check = false ): string {
		$html = '<colgroup>' . ( $check ? '<col class="bwc-table__col bwc-table__col--check">' : '' );

		foreach ( $columns as $column ) {
			$width = (string) Html::pick( $column['width'] ?? 'auto', array( 'auto', 'narrow', 'medium', 'wide' ), 'auto' );
			$html .= '<col class="' . esc_attr( Html::classes( 'bwc-table__col', array( 'auto' === $width ? '' : $width ) ) ) . '">';
		}

		return $html . '</colgroup>';
	}

	/**
	 * One column header: plain, hidden or a sort link.
	 *
	 * @param array<string, mixed> $column  Column.
	 * @param Strings              $strings Strings.
	 */
	private static function header( array $column, Strings $strings ): string {
		$label  = (string) ( $column['label'] ?? '' );
		$number = 'end' === ( $column['align'] ?? 'start' );
		$sort   = isset( $column['sort'] ) && is_array( $column['sort'] ) ? $column['sort'] : null;

		if ( ! empty( $column['hide_label'] ) ) {
			return '<th scope="col"><span class="bwc-visually-hidden">' . esc_html( $label ) . '</span></th>';
		}

		if ( null === $sort ) {
			return '<th scope="col"' . ( $number ? ' class="bwc-table__number"' : '' ) . '>' . esc_html( $label ) . '</th>';
		}

		$current = $sort['current'] ?? null;
		$next    = 'asc' === $current ? 'desc' : 'asc';
		$arrow   = null === $current ? '' : Icon::markup(
			array(
				'name' => 'asc' === $current ? 'arrow-up' : 'arrow-down',
				'size' => 14,
			)
		);

		$hint = Html::id( 'sort-hint' );

		return '<th' . Html::attributes(
			array(
				'scope'     => 'col',
				'class'     => $number ? 'bwc-table__number' : null,
				'aria-sort' => null === $current ? null : ( 'asc' === $current ? 'ascending' : 'descending' ),
			)
		) . '><a class="bwc-table__sort' . ( null === $current ? '' : ' is-current' ) . '" href="' . esc_url( (string) ( 'asc' === $next ? $sort['url_asc'] : $sort['url_desc'] ) ) . '" aria-describedby="' . esc_attr( $hint ) . '">'
			// The column's name stays its label; what the link does is its description, so cells aren't read with it.
			. '<span>' . esc_html( $label ) . '</span>' . $arrow
			. '<span id="' . esc_attr( $hint ) . '" hidden>' . esc_html( $strings->get( 'asc' === $next ? 'sort_ascending' : 'sort_descending', $label ) ) . '</span></a></th>';
	}
}
