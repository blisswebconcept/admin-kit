<?php
/**
 * A message inside the page.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Notice: info, success, warning, danger and
 * paused (a feature that can't run because something it needs is missing).
 *
 * @phpstan-type NoticeProps array{ tone: 'info'|'success'|'warning'|'danger'|'paused', title?: string, text: string, actions_html?: string, dismiss_url?: string, live?: bool }
 */
final class Notice {

	use Renders;

	/**
	 * Icon per tone.
	 */
	private const ICONS = array(
		'info'    => 'info',
		'success' => 'check',
		'warning' => 'alert',
		'danger'  => 'error',
		'paused'  => 'pause',
	);

	/**
	 * The notice.
	 *
	 * @param array<string, mixed> $props tone, title, text, actions_html, dismiss_url, live.
	 * @phpstan-param NoticeProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Notice', $props, 'text' ) ) {
			return '';
		}

		$tone    = Html::tone( $props['tone'] ?? 'info', array_keys( self::ICONS ), 'info' );
		$strings = Kit::instance()->strings();
		$role    = empty( $props['live'] ) ? null : ( 'danger' === $tone ? 'alert' : 'status' );

		$lead = '';
		if ( 'paused' === $tone ) {
			$lead .= '<strong class="bwc-notice__title">' . esc_html( $strings->get( 'paused_prefix' ) ) . '</strong> ';
		}
		if ( ! empty( $props['title'] ) ) {
			$lead .= '<strong class="bwc-notice__title">' . esc_html( (string) $props['title'] ) . '</strong> ';
		}

		$html  = '<div' . Html::attributes(
			array(
				'class'           => Html::classes( 'bwc-notice', array( $tone ) ),
				'role'            => $role,
				'data-bwc-notice' => empty( $props['dismiss_url'] ) ? null : true,
			)
		) . '>';
		$html .= '<span class="bwc-notice__icon" aria-hidden="true">' . Icon::markup( array( 'name' => self::ICONS[ $tone ] ) ) . '</span>';
		$html .= '<div class="bwc-notice__body"><p>' . $lead . esc_html( (string) $props['text'] ) . '</p>';

		if ( ! empty( $props['actions_html'] ) ) {
			$html .= '<div class="bwc-notice__actions">' . $props['actions_html'] . '</div>';
		}

		$html .= '</div>';

		if ( ! empty( $props['dismiss_url'] ) ) {
			$html .= '<a class="bwc-notice__close" href="' . esc_url( (string) $props['dismiss_url'] ) . '" data-bwc-dismiss>'
				. '<span class="bwc-visually-hidden">' . esc_html( $strings->get( 'hide_notice' ) ) . '</span>'
				. Icon::markup(
					array(
						'name' => 'close',
						'size' => 14,
					)
				)
				. '</a>';
		}

		return $html . '</div>';
	}
}
