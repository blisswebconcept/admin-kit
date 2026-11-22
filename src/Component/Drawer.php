<?php
/**
 * Drawer: add or edit a record in a side panel over a list.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Drawer. A server-rendered <dialog open>; the
 * script reopens it as a modal so the page behind is inert and focus stays
 * inside. Cancel, the backdrop and Escape follow the close link.
 *
 * @phpstan-type DrawerProps array{
 *     id: string,
 *     title: string,
 *     close_url: string,
 *     form: array{ action: string, method?: 'post'|'get', fields_html: string, hidden_html?: string },
 *     footer_start_html?: string,
 *     footer_end_html: string,
 *     alert_html?: string,
 * }
 */
final class Drawer {

	use Renders;

	/**
	 * The drawer.
	 *
	 * @param array<string, mixed> $props id, title, close_url, form (action, method, fields_html, hidden_html), footer_start_html, footer_end_html, alert_html.
	 * @phpstan-param DrawerProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Drawer', $props, 'id', 'title', 'close_url' ) ) {
			return '';
		}

		$id    = sanitize_html_class( (string) $props['id'] );
		$close = esc_url( (string) $props['close_url'] );
		$form  = (array) ( $props['form'] ?? array() );
		$label = esc_html( \BlissWebConcept\AdminKit\Kit::instance()->strings()->get( 'close' ) );

		$html  = '<dialog class="bwc-drawer" id="' . esc_attr( $id ) . '" open aria-labelledby="' . esc_attr( $id . '-title' ) . '" data-bwc-drawer>';
		$html .= '<a class="bwc-drawer__backdrop" href="' . $close . '" tabindex="-1" aria-hidden="true"></a>';
		$html .= '<form class="bwc-drawer__panel" method="' . ( 'get' === ( $form['method'] ?? 'post' ) ? 'get' : 'post' ) . '" action="' . esc_url( (string) ( $form['action'] ?? '' ) ) . '" autocomplete="off">';
		$html .= '<div class="bwc-drawer__head"><h2 class="bwc-drawer__title" id="' . esc_attr( $id . '-title' ) . '">' . esc_html( (string) $props['title'] ) . '</h2>';
		$html .= '<a class="bwc-button bwc-button--icon bwc-drawer__close" href="' . $close . '" data-bwc-drawer-close>' . Icon::markup( array( 'name' => 'close' ) ) . '<span class="bwc-visually-hidden">' . $label . '</span></a></div>';
		$html .= '<div class="bwc-drawer__body">' . ( $props['alert_html'] ?? '' ) . ( $form['fields_html'] ?? '' ) . '</div>';
		$html .= '<div class="bwc-drawer__foot"><div class="bwc-drawer__foot-start">' . ( $props['footer_start_html'] ?? '' ) . '</div>';
		$html .= '<div class="bwc-drawer__foot-end">' . ( $props['footer_end_html'] ?? '' ) . '</div></div>';
		$html .= ( $form['hidden_html'] ?? '' ) . '</form></dialog>';

		return $html;
	}
}
