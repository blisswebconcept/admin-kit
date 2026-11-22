<?php
/**
 * The log screen: runs on one side, the chosen run on the other.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Component\Card;
use BlissWebConcept\AdminKit\Component\Chips;
use BlissWebConcept\AdminKit\Component\Collapsible;
use BlissWebConcept\AdminKit\Component\EmptyState;
use BlissWebConcept\AdminKit\Component\ExpandAll;
use BlissWebConcept\AdminKit\Component\Feed;
use BlissWebConcept\AdminKit\Component\Pagination;
use BlissWebConcept\AdminKit\Component\Pill;
use BlissWebConcept\AdminKit\Component\RunList;
use BlissWebConcept\AdminKit\Component\Tiles;
use BlissWebConcept\AdminKit\Shell\PageHead;

defined( 'ABSPATH' ) || exit;

/**
 * LogScreen. The plugin turns its own log rows
 * into runs, groups and entries; the kit draws the runs by day, the run card,
 * the filter chips, the collapsible groups and Expand all.
 *
 * URL: `run` (and `day` for runs split by day), and `runs_page` for the page of the run list.
 */
final class LogScreen extends BaseScreen {

	/**
	 * A group opens by itself with this many entries or fewer.
	 */
	private const OPEN_UP_TO = 10;

	/**
	 * Runs per page of the run list, unless the screen says.
	 */
	private const PER_PAGE = 20;

	/**
	 * URL argument for the page of the run list.
	 */
	public const PAGE_ARG = 'runs_page';

	/**
	 * The screen's body.
	 */
	public function render(): string {
		$kit     = $this->kit;
		$strings = $kit->strings();
		$days    = max( 1, (int) ( $this->config['days'] ?? 30 ) );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which run and which page of runs to show; nothing is changed.
		$run_id = isset( $_GET['run'] ) ? sanitize_text_field( wp_unslash( $_GET['run'] ) ) : '';
		$day    = isset( $_GET['day'] ) ? sanitize_text_field( wp_unslash( $_GET['day'] ) ) : '';
		$page   = isset( $_GET[ self::PAGE_ARG ] ) ? max( 1, absint( $_GET[ self::PAGE_ARG ] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		[ $runs, $total, $page ] = $this->runs_page( $days, $page );

		if ( '' === $run_id && $runs ) {
			$run_id = (string) ( $runs[0]['id'] ?? '' );
			$day    = (string) ( $runs[0]['day'] ?? '' );
		}
		$day = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ? $day : '';

		$html = '';
		if ( ! empty( $this->config['title'] ) ) {
			$html .= PageHead::markup(
				array(
					'title'       => (string) $this->config['title'],
					'description' => (string) ( $this->config['intro'] ?? '' ),
				)
			);
		}

		$totals = is_callable( $this->config['totals'] ?? null ) ? (array) $this->config['totals']( $days ) : array();
		if ( $totals ) {
			$html .= Tiles::markup(
				array(
					'label' => (string) ( $this->config['totals_label'] ?? $this->label ),
					'tiles' => array_values( $totals ),
				)
			);
		}

		$side = $runs ? $this->run_list( $runs, $run_id, $day, $page ) . $this->run_pages( $total, $page, $run_id, $day ) : EmptyState::markup(
			array(
				'text'    => (string) ( $this->config['empty'] ?? $strings->get( 'no_runs' ) ),
				'variant' => 'boxed',
			)
		);

		$run  = '' !== $run_id && is_callable( $this->config['run'] ?? null ) ? $this->config['run']( $run_id, '' === $day ? null : $day ) : null;
		$main = is_array( $run ) ? $this->run_card( $run, $run_id ) : ( $runs ? EmptyState::markup(
			array(
				'text'    => $strings->get( 'pick_run' ),
				'variant' => 'boxed',
			)
		) : '' );

		return '<div class="bwc-log-screen">' . $html . '<div class="bwc-log"><div class="bwc-log__side">' . $side . '</div><div class="bwc-log__main">' . $main . '</div></div></div>';
	}

	/**
	 * One page of runs, newest first, with how many there are in all.
	 *
	 * The plugin's `runs( $days, $offset, $limit )` reads one page itself and
	 * answers `array( 'runs' => …, 'total' => … )`. A plain list (every run)
	 * still works: the kit takes the page from it.
	 *
	 * @param int $days Days the log covers.
	 * @param int $page Page asked for, from 1.
	 * @return array{0: list<array<string, mixed>>, 1: int, 2: int} The page's runs, the total, the page shown.
	 */
	private function runs_page( int $days, int $page ): array {
		$callback = $this->config['runs'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return array( array(), 0, 1 );
		}

		$per_page = $this->per_page();
		$answer   = (array) $callback( $days, ( $page - 1 ) * $per_page, $per_page );

		if ( isset( $answer['runs'] ) && is_array( $answer['runs'] ) ) {
			$total = max( 0, (int) ( $answer['total'] ?? count( $answer['runs'] ) ) );
			$pages = max( 1, (int) ceil( $total / $per_page ) );

			// Past the last page: show the last one.
			if ( $page > $pages ) {
				return $this->runs_page( $days, $pages );
			}

			return array( array_values( array_filter( $answer['runs'], 'is_array' ) ), $total, $page );
		}

		$all   = array_values( array_filter( $answer, 'is_array' ) );
		$total = count( $all );
		$page  = min( $page, max( 1, (int) ceil( $total / $per_page ) ) );

		return array( array_slice( $all, ( $page - 1 ) * $per_page, $per_page ), $total, $page );
	}

	/**
	 * Runs per page of the run list.
	 */
	private function per_page(): int {
		return max( 1, (int) ( $this->config['per_page'] ?? self::PER_PAGE ) );
	}

	/**
	 * Newer and Older under the run list, keeping the chosen run.
	 *
	 * @param int    $total  Runs in all.
	 * @param int    $page   Page shown.
	 * @param string $run_id The chosen run.
	 * @param string $day    The chosen day.
	 */
	private function run_pages( int $total, int $page, string $run_id, string $day ): string {
		if ( $total <= $this->per_page() ) {
			return '';
		}

		return Pagination::markup(
			array(
				'page'     => $page,
				'per_page' => $this->per_page(),
				'total'    => $total,
				'labels'   => 'time',
				'label'    => $this->kit->strings()->get( 'runs_pages' ),
				'url'      => fn( int $to ): string => $this->url(
					array(
						'run'          => '' === $run_id ? null : $run_id,
						'day'          => '' === $day ? null : $day,
						self::PAGE_ARG => $to > 1 ? $to : null,
					)
				),
			)
		);
	}

	/**
	 * The runs, a section per day.
	 *
	 * @param list<array<string, mixed>> $runs   Runs, newest first.
	 * @param string                     $run_id The chosen run.
	 * @param string                     $day    The chosen day.
	 * @param int                        $page   Page of the run list, kept when a run is opened.
	 */
	private function run_list( array $runs, string $run_id, string $day, int $page = 1 ): string {
		$strings  = $this->kit->strings();
		$today    = wp_date( 'Y-m-d' );
		$yday     = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
		$sections = array();

		foreach ( $runs as $run ) {
			$id      = (string) ( $run['id'] ?? '' );
			$run_day = (string) ( $run['day'] ?? '' );
			$started = (int) ( $run['started'] ?? 0 );

			if ( ! isset( $sections[ $run_day ] ) ) {
				$stamp = (int) strtotime( $run_day . ' 12:00:00' );
				$date  = $stamp > 0 ? wp_date( 'M j', $stamp ) : $run_day;
				$name  = $run_day === $today ? $strings->get( 'today' ) . ' · ' . $date : ( $run_day === $yday ? $strings->get( 'yesterday' ) . ' · ' . $date : ( $stamp > 0 ? wp_date( 'l · M j', $stamp ) : $run_day ) );

				$sections[ $run_day ] = array(
					'heading' => (string) $name,
					'items'   => array(),
				);
			}

			$item = array(
				'title'   => (string) ( $run['title'] ?? $id ),
				'url'     => $this->url(
					array(
						'run'          => $id,
						'day'          => '' === $run_day ? null : $run_day,
						self::PAGE_ARG => $page > 1 ? $page : null,
					)
				),
				'current' => $id === $run_id && ( '' === $day || $run_day === $day ),
			);
			if ( $started > 0 ) {
				$item['time'] = (string) wp_date( (string) get_option( 'time_format' ), $started );
			}
			if ( ! empty( $run['line'] ) ) {
				$item['line'] = (string) $run['line'];
			}
			if ( ! empty( $run['problem'] ) ) {
				$item['problem'] = (string) $run['problem'];
			}

			$sections[ $run_day ]['items'][] = $item;
		}

		return RunList::markup(
			array(
				'label'    => $strings->get( 'runs' ),
				'sections' => array_values( $sections ),
			)
		);
	}

	/**
	 * The chosen run: summary, tiles, notes, then its entries in groups with
	 * chips to filter them by kind.
	 *
	 * @param array<string, mixed> $run    The run, from the plugin.
	 * @param string               $run_id Its id.
	 */
	private function run_card( array $run, string $run_id ): string {
		$strings = $this->kit->strings();
		$kinds   = (array) ( $this->config['kinds'] ?? array() );
		$base    = $this->kit->id() . '-' . $this->id . '-' . sanitize_html_class( $run_id );
		$body    = '';

		if ( ! empty( $run['tiles'] ) ) {
			$body .= Tiles::markup(
				array(
					'label' => $strings->get( 'run_totals', (string) ( $run['title'] ?? '' ) ),
					'tiles' => array_values( (array) $run['tiles'] ),
				)
			);
		}

		if ( ! empty( $run['notes'] ) ) {
			$body .= '<div class="bwc-log__notes">'
				. ( empty( $run['notes_title'] ) ? '' : '<h4 class="bwc-log__notes-title">' . esc_html( (string) $run['notes_title'] ) . '</h4>' )
				. '<ul class="bwc-log__notes-list">' . implode( '', array_map( static fn( $note ): string => '<li>' . esc_html( (string) $note ) . '</li>', (array) $run['notes'] ) ) . '</ul></div>';
		}

		$groups = array_values( array_filter( (array) ( $run['groups'] ?? array() ), 'is_array' ) );
		if ( $groups ) {
			$present = array();
			$markup  = '';

			foreach ( $groups as $group ) {
				$entries = array_values( array_filter( (array) ( $group['entries'] ?? array() ), 'is_array' ) );
				$items   = array();

				foreach ( $entries as $entry ) {
					$kind   = (string) ( $entry['kind'] ?? '' );
					$def    = (array) ( $kinds[ $kind ] ?? array() );
					$filter = (string) ( $def['filter'] ?? $kind );
					$time   = (int) ( $entry['time'] ?? 0 );

					$present[ $filter ] = ( $present[ $filter ] ?? 0 ) + 1;

					$item = array(
						'time'     => $time > 0 ? (string) wp_date( (string) get_option( 'time_format' ), $time ) : '',
						'datetime' => $time > 0 ? gmdate( 'c', $time ) : '',
						'kind'     => array(
							'text' => (string) ( $def['label'] ?? $kind ),
							'tone' => (string) ( $def['tone'] ?? 'neutral' ),
						),
						'text'     => (string) ( $entry['text'] ?? '' ),
						'filter'   => $filter,
					);
					if ( ! empty( $entry['link']['url'] ) ) {
						$item['link_html'] = '<a href="' . esc_url( (string) $entry['link']['url'] ) . '">' . esc_html( (string) ( $entry['link']['label'] ?? '' ) ) . '</a>';
					}
					if ( ! empty( $entry['reasons'] ) ) {
						$item['details'] = array_map( 'strval', (array) $entry['reasons'] );
					}
					$items[] = $item;
				}

				$markup .= Collapsible::markup(
					array(
						'title'        => (string) ( $group['title'] ?? '' ),
						'tags'         => array_values( array_filter( (array) ( $group['tags'] ?? array() ), 'is_array' ) ),
						'content_html' => Feed::markup( array( 'items' => $items ) ),
						'open'         => count( $items ) <= self::OPEN_UP_TO,
						'hook'         => 'filter-group',
					)
				);
			}

			$tools   = '';
			$filters = array();
			foreach ( (array) ( $this->config['filters'] ?? array() ) as $key => $label ) {
				if ( 'all' === $key || isset( $present[ (string) $key ] ) ) {
					$filters[ (string) $key ] = array(
						'label' => (string) $label,
						'count' => 'all' === $key ? (int) array_sum( $present ) : $present[ (string) $key ],
					);
				}
			}
			if ( count( $filters ) > 2 || ( count( $filters ) > 1 && ! isset( $filters['all'] ) ) ) {
				$tools .= Chips::markup(
					array(
						'label'   => (string) ( $this->config['filters_label'] ?? $strings->get( 'filter' ) ),
						'kind'    => 'buttons',
						'target'  => $base . '-entries',
						'current' => 'all',
						'items'   => $filters,
					)
				);
			}
			if ( count( $groups ) > 1 ) {
				$tools .= ExpandAll::markup( array( 'target' => $base . '-entries' ) );
			}

			$body .= ( '' === $tools ? '' : '<div class="bwc-log__tools">' . $tools . '</div>' )
				. '<div class="bwc-log__entries" id="' . esc_attr( $base . '-entries' ) . '">' . $markup
				. '<p class="bwc-log__empty" data-bwc-filter-empty hidden>' . esc_html( $strings->get( 'nothing_in_filter' ) ) . '</p></div>';
		} elseif ( ! empty( $this->config['run_empty'] ) ) {
			$body .= EmptyState::markup(
				array(
					'text'    => (string) $this->config['run_empty'],
					'variant' => 'boxed',
				)
			);
		}

		$status = (array) ( $run['status'] ?? array() );

		return Card::markup(
			array(
				'title'             => (string) ( $run['title'] ?? '' ),
				'subtitle'          => (string) ( $run['when'] ?? '' ),
				'head_actions_html' => empty( $status['text'] ) ? '' : Pill::markup(
					array(
						'text' => (string) $status['text'],
						'tone' => (string) ( $status['tone'] ?? 'neutral' ),
						'dot'  => true,
					)
				),
				'content_html'      => $body,
				'size'              => 'large',
			)
		);
	}
}
