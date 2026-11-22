<?php
/**
 * One record's history, newest first, a heading for each day.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Timeline. Days are list items with headings, so
 * screen-reader users can jump by heading. Icons are kit Icon names.
 *
 * @phpstan-type TimelineEvent array{ time: string, text: string, meta?: string, kind?: string, icon?: string }
 * @phpstan-type TimelineProps array{ title: string, level?: 2|3, days: list<array{ label: string, events: list<TimelineEvent> }>, pagination?: array<string, mixed>, empty?: string, foot?: string }
 */
final class Timeline {

	use Renders;

	/**
	 * The timeline.
	 *
	 * @param array<string, mixed> $props title, level, days, pagination, empty, foot.
	 * @phpstan-param TimelineProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Timeline', $props, 'title' ) ) {
			return '';
		}

		$level    = (int) Html::pick( $props['level'] ?? 3, array( 2, 3 ), 3 );
		$title_id = Html::id( 'timeline' );
		$days     = (array) ( $props['days'] ?? array() );

		$html = '<section class="bwc-timeline" aria-labelledby="' . esc_attr( $title_id ) . '">'
			. '<h' . $level . ' class="bwc-timeline__title" id="' . esc_attr( $title_id ) . '">' . esc_html( (string) $props['title'] ) . '</h' . $level . '>';

		if ( ! $days ) {
			$html .= empty( $props['empty'] ) ? '' : EmptyState::markup(
				array(
					'text'    => (string) $props['empty'],
					'variant' => 'inline',
				)
			);
		} else {
			$html .= '<ol class="bwc-timeline__list">';

			foreach ( $days as $day ) {
				$html .= '<li class="bwc-timeline__day"><h' . ( $level + 1 ) . ' class="bwc-timeline__day-label">' . esc_html( (string) ( $day['label'] ?? '' ) ) . '</h' . ( $level + 1 ) . '><ol class="bwc-timeline__events">';

				foreach ( (array) ( $day['events'] ?? array() ) as $event ) {
					$kind  = empty( $event['kind'] ) ? '' : sanitize_html_class( (string) $event['kind'] );
					$icon  = Icon::markup(
						array(
							'name' => empty( $event['icon'] ) ? 'check' : (string) $event['icon'],
							'size' => 14,
						)
					);
					$meta  = implode( ' · ', array_filter( array( (string) ( $event['time'] ?? '' ), (string) ( $event['meta'] ?? '' ) ) ) );
					$html .= '<li class="' . esc_attr( Html::classes( 'bwc-timeline__event', array( $kind ) ) ) . '">'
						. '<span class="bwc-timeline__icon" aria-hidden="true">' . $icon . '</span>'
						. '<span class="bwc-timeline__text">' . esc_html( (string) ( $event['text'] ?? '' ) )
						. ( '' === $meta ? '' : '<span class="bwc-timeline__meta">' . esc_html( $meta ) . '</span>' )
						. '</span></li>';
				}

				$html .= '</ol></li>';
			}

			$html .= '</ol>';
		}

		if ( ! empty( $props['pagination'] ) && is_array( $props['pagination'] ) ) {
			$html .= '<div class="bwc-timeline__pages">' . Pagination::markup( array( 'labels' => 'time' ) + $props['pagination'] ) . '</div>';
		}

		return $html . ( empty( $props['foot'] ) ? '' : '<p class="bwc-timeline__foot">' . esc_html( (string) $props['foot'] ) . '</p>' ) . '</section>';
	}
}
