<?php
/**
 * Time-stamped entries with a kind label.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Feed. Each item's filter value is the
 * data-bwc-kind that filter chips match.
 *
 * @phpstan-type FeedItem array{ time: string, datetime?: string, kind: array{ text: string, tone?: string }, text: string, link_html?: string, details?: list<string>, filter?: string }
 * @phpstan-type FeedProps array{ items: list<FeedItem> }
 */
final class Feed {

	use Renders;

	/**
	 * The feed.
	 *
	 * @param array<string, mixed> $props items.
	 * @phpstan-param FeedProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Feed', $props, 'items' ) ) {
			return '';
		}

		$html = '<ul class="bwc-feed">';

		foreach ( (array) $props['items'] as $item ) {
			$details = '';
			foreach ( (array) ( $item['details'] ?? array() ) as $line ) {
				$details .= '<li>' . esc_html( (string) $line ) . '</li>';
			}

			$html .= '<li' . Html::attributes(
				array(
					'class'         => 'bwc-feed__item',
					'data-bwc-kind' => empty( $item['filter'] ) ? null : (string) $item['filter'],
				)
			) . '>' . ( empty( $item['datetime'] )
				? '<span class="bwc-feed__time">' . esc_html( (string) ( $item['time'] ?? '' ) ) . '</span>'
				: '<time class="bwc-feed__time" datetime="' . esc_attr( (string) $item['datetime'] ) . '">' . esc_html( (string) ( $item['time'] ?? '' ) ) . '</time>' )
				. '<span class="bwc-feed__kind">' . Pill::markup(
					array(
						'text'  => (string) ( $item['kind']['text'] ?? '' ),
						'tone'  => (string) ( $item['kind']['tone'] ?? 'neutral' ),
						'shape' => 'square',
					)
				) . '</span>'
				. '<span class="bwc-feed__text">' . esc_html( (string) ( $item['text'] ?? '' ) )
				. ( empty( $item['link_html'] ) ? '' : ' <span class="bwc-feed__link">' . $item['link_html'] . '</span>' )
				. ( '' === $details ? '' : '<ul class="bwc-feed__details">' . $details . '</ul>' )
				. '</span></li>';
		}

		return $html . '</ul>';
	}
}
