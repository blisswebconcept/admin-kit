<?php
/**
 * Typed blocks that reports and detail views are built from.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Component\Avatar;
use BlissWebConcept\AdminKit\Component\Card;
use BlissWebConcept\AdminKit\Component\Chart;
use BlissWebConcept\AdminKit\Component\EmptyState;
use BlissWebConcept\AdminKit\Component\Facts;
use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Component\ProgressBar;
use BlissWebConcept\AdminKit\Component\Table;
use BlissWebConcept\AdminKit\Component\Tiles;
use BlissWebConcept\AdminKit\Component\Timeline;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks. A report tab or a
 * detail view returns an ordered list of blocks; each is drawn with its
 * component. Blocks with `half` sit two to a row on wide screens.
 *
 * Types: tiles, chart, table, cards, notes, facts, section, html, and for
 * detail views profile, progress and timeline.
 */
final class Blocks {

	/**
	 * Blocks as HTML.
	 *
	 * @param list<array<string, mixed>> $blocks Blocks.
	 * @param callable(array<string, scalar|null>): string $url Builds a link to the current view with changes (for timeline pages).
	 */
	public static function render( array $blocks, callable $url ): string {
		$html = '';
		$row  = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$one = self::block( $block, $url );
			if ( '' === $one ) {
				continue;
			}

			if ( ! empty( $block['half'] ) ) {
				$row[] = $one;
				if ( 2 === count( $row ) ) {
					$html .= '<div class="bwc-report__row">' . implode( '', $row ) . '</div>';
					$row   = array();
				}
				continue;
			}

			$html .= self::flush( $row ) . $one;
			$row   = array();
		}

		return $html . self::flush( $row );
	}

	/**
	 * A half block left without a partner takes the whole row.
	 *
	 * @param list<string> $row Blocks waiting for a partner.
	 */
	private static function flush( array $row ): string {
		return $row ? '<div class="bwc-report__row">' . implode( '', $row ) . '</div>' : '';
	}

	/**
	 * One block.
	 *
	 * @param array<string, mixed>                          $block Block.
	 * @param callable(array<string, scalar|null>): string $url   Link builder.
	 */
	private static function block( array $block, callable $url ): string {
		$type  = (string) ( $block['type'] ?? '' );
		$title = (string) ( $block['title'] ?? '' );

		switch ( $type ) {
			case 'tiles':
				return Tiles::markup(
					array(
						'label'   => '' === $title ? Kit::instance()->strings()->get( 'summary' ) : $title,
						'tiles'   => array_values( (array) ( $block['items'] ?? array() ) ),
						'columns' => 6 === (int) ( $block['columns'] ?? 4 ) ? 'six' : 'auto',
					)
				);

			case 'chart':
				$chart = Chart::markup( $block );
				return '' === $chart ? '' : self::panel( $chart );

			case 'table':
				return self::panel( self::table( $block ), $title, (string) ( $block['description'] ?? '' ) );

			case 'cards':
				return self::cards( $block );

			case 'notes':
				$notes = '';
				foreach ( (array) ( $block['items'] ?? array() ) as $note ) {
					if ( ! is_array( $note ) || empty( $note['text'] ) ) {
						continue;
					}
					$props = array(
						'tone' => (string) ( $note['tone'] ?? 'info' ),
						'text' => (string) $note['text'],
					);
					if ( ! empty( $note['link']['url'] ) ) {
						$props['actions_html'] = '<a href="' . esc_url( (string) $note['link']['url'] ) . '">' . esc_html( (string) ( $note['link']['label'] ?? '' ) ) . '</a>';
					}
					$notes .= Notice::markup( $props );
				}
				return '' === $notes ? '' : '<div class="bwc-report__notes">' . $notes . '</div>';

			case 'facts':
				$items = array();
				foreach ( (array) ( $block['items'] ?? array() ) as $term => $item ) {
					$items[] = is_array( $item ) ? array(
						'label' => (string) ( $item['term'] ?? $item['label'] ?? '' ),
						'value' => (string) ( $item['description'] ?? $item['value'] ?? '' ),
					) : array(
						'label' => (string) $term,
						'value' => (string) $item,
					);
				}
				return self::panel( Facts::markup( array( 'items' => $items ) ), $title );

			case 'section':
				$inner = self::render( array_values( array_filter( (array) ( $block['blocks'] ?? array() ), 'is_array' ) ), $url );
				return '<section class="bwc-report__section">'
					. ( '' === $title ? '' : '<h2 class="bwc-report__section-title">' . esc_html( $title ) . '</h2>' )
					. ( empty( $block['intro'] ) ? '' : '<p class="bwc-report__section-intro">' . esc_html( (string) $block['intro'] ) . '</p>' )
					. $inner . '</section>';

			case 'html':
				return (string) ( $block['html'] ?? '' );

			case 'profile':
				return self::profile( $block );

			case 'progress':
				$list = '';
				foreach ( (array) ( $block['items'] ?? array() ) as $item ) {
					if ( ! is_array( $item ) || ! isset( $item['label'] ) ) {
						continue;
					}
					$list .= '<li class="bwc-report__progress-item"><span class="bwc-report__progress-label">' . esc_html( (string) $item['label'] ) . '</span>'
						. ProgressBar::markup(
							array(
								'value' => (float) ( $item['value'] ?? 0 ),
								'max'   => (float) ( $item['max'] ?? 100 ),
								'label' => (string) $item['label'],
								'tone'  => (string) ( $item['tone'] ?? 'accent' ),
							)
						)
						. ( empty( $item['note'] ) ? '' : '<span class="bwc-report__progress-note">' . esc_html( (string) $item['note'] ) . '</span>' )
						. '</li>';
				}
				return '' === $list ? '' : self::panel( '<ul class="bwc-report__progress">' . $list . '</ul>', $title );

			case 'timeline':
				return self::timeline( $block, $url );
		}

		return '';
	}

	/**
	 * A block's white panel, with a title when it has one.
	 *
	 * @param string $content     Escaped HTML.
	 * @param string $title       Title.
	 * @param string $description Line under the title.
	 */
	private static function panel( string $content, string $title = '', string $description = '' ): string {
		if ( '' === $content ) {
			return '';
		}

		$props = array( 'content_html' => $content );
		if ( '' !== $title ) {
			$props['title'] = $title;
			$props['level'] = 3;
		}
		if ( '' !== $description ) {
			$props['subtitle'] = $description;
		}

		return '<div class="bwc-report__block">' . Card::markup( $props ) . '</div>';
	}

	/**
	 * A table block: list fields over items, cells formatted as in ListScreen.
	 *
	 * @param array<string, mixed> $block Block.
	 */
	private static function table( array $block ): string {
		$fields = ListView::fields( (array) ( $block['fields'] ?? array() ) );
		$items  = array_values( array_filter( (array) ( $block['items'] ?? array() ), 'is_array' ) );

		if ( ! $items ) {
			return EmptyState::markup( array( 'text' => (string) ( $block['empty'] ?? Kit::instance()->strings()->get( 'nothing_to_show' ) ) ) );
		}

		$columns = array();
		foreach ( $fields as $id => $field ) {
			if ( $field['column'] ) {
				$columns[ $id ] = array(
					'label' => $field['label'],
					'align' => $field['align'],
					'width' => $field['width'],
				);
			}
		}

		$rows = array();
		foreach ( $items as $item ) {
			$cells = array();
			foreach ( array_keys( $columns ) as $id ) {
				$cells[ $id ] = ListScreen::cell( $fields[ $id ], $item );
			}
			$rows[] = array( 'cells' => $cells );
		}

		return Table::markup(
			array(
				'caption'   => (string) ( $block['title'] ?? '' ),
				'columns'   => $columns,
				'rows'      => $rows,
				'compact'   => ! empty( $block['compact'] ),
				'min_width' => (string) ( $block['min_width'] ?? 'medium' ),
			)
		);
	}

	/**
	 * Cards that link somewhere, used as navigation.
	 *
	 * @param array<string, mixed> $block Block.
	 */
	private static function cards( array $block ): string {
		$cards = '';
		foreach ( (array) ( $block['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) || empty( $item['title'] ) ) {
				continue;
			}
			$current = ! empty( $item['current'] );
			$inner   = '<span class="bwc-report__card-title">' . esc_html( (string) $item['title'] ) . '</span>'
				. ( empty( $item['meta'] ) ? '' : '<span class="bwc-report__card-meta">' . esc_html( (string) $item['meta'] ) . '</span>' );

			$cards .= empty( $item['url'] )
				? '<span class="bwc-report__card">' . $inner . '</span>'
				: '<a' . Html::attributes(
					array(
						'class'        => 'bwc-report__card' . ( $current ? ' is-current' : '' ),
						'href'         => esc_url( (string) $item['url'] ),
						'aria-current' => $current ? 'page' : null,
					)
				) . '>' . $inner . '</a>';
		}

		if ( '' === $cards ) {
			return '';
		}

		$title = (string) ( $block['title'] ?? '' );

		return '<nav class="bwc-report__cards"' . ( '' === $title ? '' : ' aria-label="' . esc_attr( $title ) . '"' ) . '>' . $cards . '</nav>';
	}

	/**
	 * A person or thing: avatar with initials, name, facts.
	 *
	 * @param array<string, mixed> $block Block.
	 */
	private static function profile( array $block ): string {
		$name  = (string) ( $block['avatar'] ?? $block['name'] ?? '' );
		$facts = array();
		foreach ( (array) ( $block['facts'] ?? array() ) as $term => $value ) {
			$facts[] = array(
				'label' => (string) $term,
				'value' => (string) $value,
			);
		}

		return self::panel(
			'<div class="bwc-report__profile">'
				. ( '' === $name ? '' : Avatar::markup(
					array(
						'name' => $name,
						'size' => 'large',
					)
				) )
				. '<div class="bwc-report__profile-body">'
				. ( empty( $block['line'] ) ? '' : '<p class="bwc-report__profile-line">' . esc_html( (string) $block['line'] ) . '</p>' )
				. ( $facts ? Facts::markup(
					array(
						'items'  => $facts,
						'layout' => 'columns',
					)
				) : '' )
				. '</div></div>'
		);
	}

	/**
	 * A timeline with its own page parameter; paging keeps the reader's place.
	 *
	 * @param array<string, mixed>                          $block Block: title, days (label, events), param, perPage, total, page, empty.
	 * @param callable(array<string, scalar|null>): string $url   Link builder.
	 */
	private static function timeline( array $block, callable $url ): string {
		$param = sanitize_key( (string) ( $block['param'] ?? 'events' ) );
		$props = array(
			'title' => (string) ( $block['title'] ?? '' ),
			'level' => 3,
			'days'  => array_values( array_filter( (array) ( $block['days'] ?? $block['items'] ?? array() ), 'is_array' ) ),
		);
		if ( ! empty( $block['empty'] ) ) {
			$props['empty'] = (string) $block['empty'];
		}

		$total = (int) ( $block['total'] ?? 0 );
		$per   = max( 1, (int) ( $block['perPage'] ?? 25 ) );
		if ( $total > $per ) {
			$props['pagination'] = array(
				'page'     => max( 1, (int) ( $block['page'] ?? 1 ) ),
				'per_page' => $per,
				'total'    => $total,
				'url'      => static fn( int $page ): string => $url( array( $param => $page > 1 ? $page : null ) ),
				'anchor'   => 'bwc-timeline',
			);
		}

		return '<div class="bwc-report__block" id="bwc-timeline">' . Card::markup( array( 'content_html' => Timeline::markup( $props ) ) ) . '</div>';
	}
}
