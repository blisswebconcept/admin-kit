<?php
/**
 * The title, a sentence or two, and actions at the top of a section.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Shell;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Renders;

defined( 'ABSPATH' ) || exit;

/**
 * Page head.
 *
 * @phpstan-type PageHeadProps array{ title: string, description?: string, level?: 2|3, meta?: string, actions_html?: string }
 */
final class PageHead {

	use Renders;

	/**
	 * The head.
	 *
	 * @param array<string, mixed> $props title, description, level, meta, actions_html.
	 * @phpstan-param PageHeadProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'PageHead', $props, 'title' ) ) {
			return '';
		}

		$level = (int) Html::pick( $props['level'] ?? 2, array( 2, 3 ), 2 );
		$html  = '<div class="bwc-head"><div class="bwc-head__text">';
		$html .= '<h' . $level . ' class="bwc-head__title">' . esc_html( (string) $props['title'] ) . '</h' . $level . '>';

		if ( ! empty( $props['description'] ) ) {
			$html .= '<p class="bwc-head__description">' . esc_html( (string) $props['description'] ) . '</p>';
		}

		$html .= '</div>';

		if ( ! empty( $props['meta'] ) || ! empty( $props['actions_html'] ) ) {
			$html .= '<div class="bwc-head__actions">';
			$html .= empty( $props['meta'] ) ? '' : '<span class="bwc-head__meta">' . esc_html( (string) $props['meta'] ) . '</span>';
			$html .= (string) ( $props['actions_html'] ?? '' );
			$html .= '</div>';
		}

		return $html . '</div>';
	}
}
