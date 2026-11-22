<?php
/**
 * A white box grouping related content.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Card. A titled card is a section labelled by
 * its title; an untitled one is a div. "large" is the roomy card a screen's
 * sections sit in; a card inside another card turns grey.
 *
 * @phpstan-type CardProps array{ title?: string, level?: 2|3|4, subtitle?: string, head_actions_html?: string, content_html: string, foot?: string, foot_html?: string, tone?: 'default'|'danger', size?: 'default'|'large', id?: string }
 */
final class Card {

	use Renders;

	/**
	 * The card.
	 *
	 * @param array<string, mixed> $props title, level, subtitle, head_actions_html, content_html, foot, foot_html, tone, size, id.
	 * @phpstan-param CardProps $props
	 */
	public static function markup( array $props ): string {
		$title    = isset( $props['title'] ) ? (string) $props['title'] : '';
		$level    = (int) Html::pick( $props['level'] ?? 2, array( 2, 3, 4 ), 2 );
		$tag      = '' === $title ? 'div' : 'section';
		$title_id = '' === $title ? '' : Html::id( 'card-title' );

		$html = '<' . $tag . Html::attributes(
			array(
				'class'           => Html::classes(
					'bwc-card',
					array(
						'large'  => 'large' === ( $props['size'] ?? 'default' ),
						'danger' => 'danger' === ( $props['tone'] ?? 'default' ),
					)
				),
				'id'              => empty( $props['id'] ) ? null : sanitize_html_class( (string) $props['id'] ),
				'aria-labelledby' => '' === $title ? null : $title_id,
			)
		) . '>';

		if ( '' !== $title ) {
			$html .= '<header class="bwc-card__head"><div class="bwc-card__titles">';
			$html .= '<h' . $level . ' class="bwc-card__title" id="' . esc_attr( $title_id ) . '">' . esc_html( $title ) . '</h' . $level . '>';
			$html .= empty( $props['subtitle'] ) ? '' : '<p class="bwc-card__subtitle">' . esc_html( (string) $props['subtitle'] ) . '</p>';
			$html .= '</div>';
			$html .= empty( $props['head_actions_html'] ) ? '' : '<div class="bwc-card__actions">' . $props['head_actions_html'] . '</div>';
			$html .= '</header>';
		}

		$html .= '<div class="bwc-card__body">' . ( $props['content_html'] ?? '' ) . '</div>';

		if ( ! empty( $props['foot_html'] ) ) {
			$html .= '<footer class="bwc-card__foot">' . $props['foot_html'] . '</footer>';
		} elseif ( ! empty( $props['foot'] ) ) {
			$html .= '<footer class="bwc-card__foot">' . esc_html( (string) $props['foot'] ) . '</footer>';
		}

		return $html . '</' . $tag . '>';
	}
}
