<?php
/**
 * A titled block of entries that opens and closes.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Collapsible group: a <details>, with tag pills in
 * its summary row. hook "filter-group" makes it take part in chip filtering.
 *
 * @phpstan-type CollapsibleProps array{ title: string, tags?: list<array{ text: string, tone?: string }>, content_html: string, open?: bool, more?: string, hook?: string }
 */
final class Collapsible {

	use Renders;

	/**
	 * The group.
	 *
	 * @param array<string, mixed> $props title, tags, content_html, open, more, hook.
	 * @phpstan-param CollapsibleProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Collapsible', $props, 'title' ) ) {
			return '';
		}

		$tags = '';
		foreach ( (array) ( $props['tags'] ?? array() ) as $tag ) {
			$tags .= Pill::markup(
				array(
					'text'  => (string) ( $tag['text'] ?? '' ),
					'tone'  => (string) ( $tag['tone'] ?? 'neutral' ),
					'shape' => 'square',
				)
			);
		}

		$attributes = array(
			'class' => 'bwc-group',
			'open'  => ! empty( $props['open'] ),
		);
		if ( ! empty( $props['hook'] ) ) {
			$attributes[ 'data-bwc-' . sanitize_key( (string) $props['hook'] ) ] = true;
		}

		return '<details' . Html::attributes( $attributes ) . '><summary class="bwc-group__summary">'
			. '<span class="bwc-group__chevron" aria-hidden="true">' . Icon::markup(
				array(
					'name' => 'chevron',
					'size' => 14,
				)
			) . '</span>'
			. '<span class="bwc-group__title">' . esc_html( (string) $props['title'] ) . '</span>'
			. ( '' === $tags ? '' : '<span class="bwc-group__tags">' . $tags . '</span>' )
			. '</summary><div class="bwc-group__body">' . ( $props['content_html'] ?? '' ) . '</div>'
			. ( empty( $props['more'] ) ? '' : '<p class="bwc-group__more">' . esc_html( (string) $props['more'] ) . '</p>' )
			. '</details>';
	}
}
