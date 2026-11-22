<?php
/**
 * "Showing 1–20 of 245" with previous and next.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Pagination. "pages" labels work like
 * WordPress's list tables: first, previous, a box to type a page number into,
 * "of 13", next and last. The box is a small GET form built from the plugin's
 * own page URLs, so it works without the script. "time" labels say
 * "Newer"/"Older", for feeds and timelines where page 1 is the newest.
 *
 * @phpstan-type PaginationProps array{ page: int, per_page: int, total: int, url: callable(int): string, labels?: 'pages'|'time', anchor?: string, label?: string }
 */
final class Pagination {

	use Renders;

	/**
	 * A page number no real list reaches; asking the URL builder for it shows
	 * which query argument carries the page.
	 */
	private const PROBE = 987654321;

	/**
	 * The summary and the navigation.
	 *
	 * @param array<string, mixed> $props page, per_page, total, url, labels, anchor, label (the nav's name when a page has more than one).
	 * @phpstan-param PaginationProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Pagination', $props, 'per_page', 'url' ) || ! is_callable( $props['url'] ) ) {
			return '';
		}

		$strings  = Kit::instance()->strings();
		$per_page = max( 1, (int) $props['per_page'] );
		$total    = max( 0, (int) ( $props['total'] ?? 0 ) );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( max( 1, (int) ( $props['page'] ?? 1 ) ), $pages );
		$anchor   = empty( $props['anchor'] ) ? '' : '#' . sanitize_html_class( (string) $props['anchor'] );
		$time     = 'time' === ( $props['labels'] ?? 'pages' );

		$summary = 0 === $total
			? $strings->get( 'nothing_to_show' )
			: $strings->get( 'showing_range', Html::number( ( $page - 1 ) * $per_page + 1 ), Html::number( min( $page * $per_page, $total ) ), Html::number( $total ) );

		$html = '<div class="bwc-pagination"><span class="bwc-pagination__summary">' . esc_html( $summary ) . '</span>';

		if ( $pages < 2 ) {
			return $html . '</div>';
		}

		$html .= '<nav class="bwc-pagination__nav" aria-label="' . esc_attr( empty( $props['label'] ) ? $strings->get( 'pages' ) : (string) $props['label'] ) . '">';

		if ( $time ) {
			return $html
				. self::link( $props, $page - 1, $page > 1, $strings->get( 'newer' ), '', 'prev', $anchor )
				. self::link( $props, $page + 1, $page < $pages, $strings->get( 'older' ), '', 'next', $anchor )
				. '</nav></div>';
		}

		return $html
			. self::link( $props, 1, $page > 1, $strings->get( 'first_page' ), 'chevrons', 'first', $anchor )
			. self::link( $props, $page - 1, $page > 1, $strings->get( 'previous_page' ), 'chevron', 'prev', $anchor )
			. self::jump( $props, $page, $pages, $anchor )
			. self::link( $props, $page + 1, $page < $pages, $strings->get( 'next_page' ), 'chevron', 'next', $anchor )
			. self::link( $props, $pages, $page < $pages, $strings->get( 'last_page' ), 'chevrons', 'last', $anchor )
			. '</nav></div>';
	}

	/**
	 * One step: first, previous, next, last, newer or older.
	 *
	 * @param array<string, mixed> $props   Pagination props.
	 * @param int                  $to      Page it goes to.
	 * @param bool                 $enabled Whether it goes anywhere from this page.
	 * @param string               $label   Text, or the screen-reader label of an icon.
	 * @param string               $icon    Icon name, or "" for a text button.
	 * @param string               $which   first, prev, next or last.
	 * @param string               $anchor  "#id" or "".
	 */
	private static function link( array $props, int $to, bool $enabled, string $label, string $icon, string $which, string $anchor ): string {
		return Button::markup(
			array(
				'label'     => $label,
				'size'      => 'small',
				'icon'      => $icon,
				'icon_only' => '' !== $icon,
				'url'       => $enabled ? ( $props['url'] )( $to ) . $anchor : '#',
				'disabled'  => ! $enabled,
				'class'     => 'bwc-pagination__' . $which,
			)
		);
	}

	/**
	 * The page box: type a page and press Enter. When the plugin's URLs don't
	 * carry the page in a query argument, "Page 3 of 13" as text instead.
	 *
	 * @param array<string, mixed> $props  Pagination props.
	 * @param int                  $page   Current page.
	 * @param int                  $pages  Number of pages.
	 * @param string               $anchor "#id" or "".
	 */
	private static function jump( array $props, int $page, int $pages, string $anchor ): string {
		$strings = Kit::instance()->strings();
		$probe   = explode( '#', (string) ( $props['url'] )( self::PROBE ), 2 )[0];
		$parts   = explode( '?', $probe, 2 );
		$query   = array();
		parse_str( $parts[1] ?? '', $query );
		$name = array_search( (string) self::PROBE, $query, true );

		if ( ! is_string( $name ) ) {
			return '<span class="bwc-pagination__jump">' . esc_html( $strings->get( 'page_n_of', Html::number( $page ), Html::number( $pages ) ) ) . '</span>';
		}

		unset( $query[ $name ] );
		$id    = Html::id( 'page' );
		$input = '<input' . Html::attributes(
			array(
				'class'     => 'bwc-control bwc-pagination__page',
				'type'      => 'number',
				'id'        => $id,
				'name'      => $name,
				'value'     => $page,
				'min'       => 1,
				'max'       => $pages,
				'inputmode' => 'numeric',
			)
		) . '>';

		return '<form class="bwc-pagination__jump" method="get" action="' . esc_url( $parts[0] . $anchor ) . '">'
			. self::hidden_fields( $query )
			. '<label class="bwc-visually-hidden" for="' . esc_attr( $id ) . '">' . esc_html( $strings->get( 'current_page' ) ) . '</label>'
			. sprintf( esc_html( $strings->get( 'page_of' ) ), $input, '<span class="bwc-pagination__total">' . esc_html( Html::number( $pages ) ) . '</span>' )
			. '</form>';
	}

	/**
	 * Hidden inputs that carry the rest of the URL's query, nested arrays included.
	 *
	 * @param array<int|string, mixed> $query  Query arguments.
	 * @param string                   $prefix Name of the array they belong to.
	 */
	private static function hidden_fields( array $query, string $prefix = '' ): string {
		$html = '';

		foreach ( $query as $key => $value ) {
			$name  = '' === $prefix ? (string) $key : $prefix . '[' . $key . ']';
			$html .= is_array( $value )
				? self::hidden_fields( $value, $name )
				: '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}

		return $html;
	}
}
