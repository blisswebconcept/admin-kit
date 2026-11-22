<?php
/**
 * What a plugin's parts do: an icon, a title and a sentence or two each.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Features: a list for overview and "how it
 * works" pages, two across on wide screens.
 *
 * @phpstan-type FeatureItem array{ title: string, text?: string, text_html?: string, icon?: string }
 * @phpstan-type FeaturesProps array{ items: list<FeatureItem>, level?: 3|4 }
 */
final class Features {

	use Renders;

	/**
	 * The list.
	 *
	 * @param array<string, mixed> $props items, level.
	 * @phpstan-param FeaturesProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Features', $props, 'items' ) ) {
			return '';
		}

		$level = (int) Html::pick( $props['level'] ?? 3, array( 3, 4 ), 3 );
		$html  = '<ul class="bwc-features">';

		foreach ( (array) $props['items'] as $item ) {
			$icon  = empty( $item['icon'] ) ? '' : Icon::markup(
				array(
					'name' => (string) $item['icon'],
					'size' => 20,
				)
			);
			$text  = isset( $item['text_html'] ) ? (string) $item['text_html'] : esc_html( (string) ( $item['text'] ?? '' ) );
			$html .= '<li class="bwc-features__item"><div class="bwc-features__head">'
				. ( '' === $icon ? '' : '<span class="bwc-features__icon" aria-hidden="true">' . $icon . '</span>' )
				. '<h' . $level . ' class="bwc-features__title">' . esc_html( (string) ( $item['title'] ?? '' ) ) . '</h' . $level . '></div>'
				. ( '' === $text ? '' : '<p class="bwc-features__text">' . $text . '</p>' )
				. '</li>';
		}

		return $html . '</ul>';
	}
}
