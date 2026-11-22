<?php
/**
 * One connected service with its state, numbers, last activity and buttons.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Status card: an affiliate network, Brevo,
 * Notion. Numbers are a <dl>, label after the number visually, so screen
 * readers get "offers to review, 365".
 *
 * @phpstan-type StatusCardProps array{
 *     title: string,
 *     state: array{ text: string, tone: string },
 *     stats?: list<array{ value: int|string, label: string }>,
 *     text?: string,
 *     details?: string,
 *     actions_html?: string,
 *     picked?: bool,
 *     variant?: 'default'|'dashed',
 * }
 */
final class StatusCard {

	use Renders;

	/**
	 * The card.
	 *
	 * @param array<string, mixed> $props title, state, stats, text, details, actions_html, picked, variant.
	 * @phpstan-param StatusCardProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'StatusCard', $props, 'title', 'state' ) ) {
			return '';
		}

		$id    = Html::id( 'status-card' );
		$state = (array) $props['state'];
		$class = Html::classes( 'bwc-status-card', array( 'dashed' => 'dashed' === ( $props['variant'] ?? 'default' ) ), empty( $props['picked'] ) ? '' : 'is-current' );

		$html  = '<section class="' . esc_attr( $class ) . '" aria-labelledby="' . esc_attr( $id ) . '">';
		$html .= '<div class="bwc-status-card__head"><h3 class="bwc-status-card__title" id="' . esc_attr( $id ) . '">' . esc_html( (string) $props['title'] ) . '</h3>';
		$html .= Pill::markup(
			array(
				'text' => (string) ( $state['text'] ?? '' ),
				'tone' => (string) ( $state['tone'] ?? 'neutral' ),
				'dot'  => true,
			)
		);
		$html .= '</div>';

		if ( ! empty( $props['stats'] ) ) {
			$html .= '<dl class="bwc-status-card__stats">';
			foreach ( (array) $props['stats'] as $stat ) {
				$html .= '<div class="bwc-status-card__stat"><dt class="bwc-status-card__label">' . esc_html( (string) ( $stat['label'] ?? '' ) ) . '</dt>'
					. '<dd class="bwc-status-card__number">' . esc_html( Html::number( $stat['value'] ?? '' ) ) . '</dd></div>';
			}
			$html .= '</dl>';
		}

		if ( ! empty( $props['details'] ) ) {
			$html .= '<details class="bwc-status-card__details"><summary class="bwc-status-card__text">' . esc_html( (string) ( $props['text'] ?? '' ) ) . '</summary>'
				. '<p>' . esc_html( (string) $props['details'] ) . '</p></details>';
		} elseif ( ! empty( $props['text'] ) ) {
			$html .= '<p class="bwc-status-card__text">' . esc_html( (string) $props['text'] ) . '</p>';
		}

		$html .= empty( $props['actions_html'] ) ? '' : '<div class="bwc-status-card__actions">' . $props['actions_html'] . '</div>';

		return $html . '</section>';
	}
}
