<?php
/**
 * A card that holds a list view.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Panel: an optional title, folder tabs across
 * the top (the current one joins the content below), a toolbar, the list, a
 * footer for pagination and a note.
 *
 * @phpstan-type PanelTab array{ label: string, url: string, count?: int|string, icon?: string, hook?: string }
 * @phpstan-type PanelProps array{ title?: string, level?: 2|3, tabs?: array<string, PanelTab>, current_tab?: string, tabs_label?: string, toolbar_html?: string, content_html: string, footer_html?: string, note?: string }
 */
final class Panel {

	use Renders;

	/**
	 * The panel.
	 *
	 * @param array<string, mixed> $props title, level, tabs, current_tab, tabs_label, toolbar_html, content_html, footer_html, note.
	 * @phpstan-param PanelProps $props
	 */
	public static function markup( array $props ): string {
		$title    = isset( $props['title'] ) ? (string) $props['title'] : '';
		$title_id = '' === $title ? '' : Html::id( 'panel-title' );
		$level    = (int) Html::pick( $props['level'] ?? 2, array( 2, 3 ), 2 );

		$html = '<section' . Html::attributes(
			array(
				'class'           => 'bwc-panel',
				'aria-labelledby' => '' === $title ? null : $title_id,
			)
		) . '>';

		if ( '' !== $title ) {
			$html .= '<h' . $level . ' class="bwc-panel__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $title ) . '</h' . $level . '>';
		}

		if ( ! empty( $props['tabs'] ) ) {
			$current = (string) ( $props['current_tab'] ?? '' );
			$tabs    = trim( (string) ( $props['tabs_label'] ?? '' ) );
			// A nav needs a name; the panel's title is the fallback.
			$tabs  = '' !== $tabs ? $tabs : $title;
			$html .= '<nav class="bwc-panel__tabs"' . ( '' === $tabs ? '' : ' aria-label="' . esc_attr( $tabs ) . '"' ) . ' data-bwc-scroll-current>';

			foreach ( (array) $props['tabs'] as $key => $tab ) {
				$on    = (string) $key === $current;
				$count = $tab['count'] ?? '';
				$hook  = isset( $tab['hook'] ) ? (string) $tab['hook'] : '';
				$html .= '<a' . Html::attributes(
					array(
						'class'             => 'bwc-panel__tab' . ( $on ? ' is-current' : '' ),
						'href'              => esc_url( (string) ( $tab['url'] ?? '#' ) ),
						'aria-current'      => $on ? ( '' === $hook ? 'page' : 'true' ) : null,
						'data-bwc-nav-item' => '' === $hook ? null : $hook,
					)
				) . '>'
				. ( empty( $tab['icon'] ) ? '' : Icon::markup(
					array(
						'name' => (string) $tab['icon'],
						'size' => 16,
					)
				) )
				. esc_html( (string) ( $tab['label'] ?? $key ) )
				. ( '' === (string) $count ? '' : ' <span class="bwc-panel__count">' . esc_html( is_int( $count ) ? number_format_i18n( $count ) : (string) $count ) . '</span>' )
				. '</a>';
			}

			$html .= '</nav>';
		}

		$html .= empty( $props['toolbar_html'] ) ? '' : '<div class="bwc-panel__toolbar">' . $props['toolbar_html'] . '</div>';
		$html .= '<div class="bwc-panel__body">' . ( $props['content_html'] ?? '' ) . '</div>';
		$html .= empty( $props['footer_html'] ) ? '' : '<footer class="bwc-panel__footer">' . $props['footer_html'] . '</footer>';
		$html .= empty( $props['note'] ) ? '' : '<p class="bwc-panel__note">' . esc_html( (string) $props['note'] ) . '</p>';

		return $html . '</section>';
	}
}
