<?php
/**
 * A side-column list of runs or days to pick from.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Run list: links, grouped under headings, each
 * with a title, a time, a one-line summary and an optional problem line.
 *
 * @phpstan-type RunItem array{ title: string, time?: string, line?: string, problem?: string, url: string, current?: bool }
 * @phpstan-type RunListProps array{ label: string, sections: list<array{ heading: string, items: list<RunItem> }> }
 */
final class RunList {

	use Renders;

	/**
	 * The list.
	 *
	 * @param array<string, mixed> $props label, sections.
	 * @phpstan-param RunListProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'RunList', $props, 'label', 'sections' ) ) {
			return '';
		}

		$html = '<nav class="bwc-runs" aria-label="' . esc_attr( (string) $props['label'] ) . '">';

		foreach ( (array) $props['sections'] as $section ) {
			$html .= '<h3 class="bwc-runs__heading">' . esc_html( (string) ( $section['heading'] ?? '' ) ) . '</h3><ul class="bwc-runs__list">';

			foreach ( (array) ( $section['items'] ?? array() ) as $item ) {
				$on    = ! empty( $item['current'] );
				$html .= '<li><a' . Html::attributes(
					array(
						'class'        => 'bwc-run' . ( $on ? ' is-current' : '' ),
						'href'         => esc_url( (string) ( $item['url'] ?? '#' ) ),
						'aria-current' => $on ? 'page' : null,
					)
				) . '><span class="bwc-run__top"><span class="bwc-run__title">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</span>'
					. ( empty( $item['time'] ) ? '' : '<span class="bwc-run__time">' . esc_html( (string) $item['time'] ) . '</span>' )
					. '</span>'
					. ( empty( $item['line'] ) ? '' : '<span class="bwc-run__line">' . esc_html( (string) $item['line'] ) . '</span>' )
					. ( empty( $item['problem'] ) ? '' : '<span class="bwc-run__problem"><span class="bwc-run__dot" aria-hidden="true"></span>' . esc_html( (string) $item['problem'] ) . '</span>' )
					. '</a></li>';
			}

			$html .= '</ul>';
		}

		return $html . '</nav>';
	}
}
