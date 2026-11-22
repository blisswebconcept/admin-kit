<?php
/**
 * A whole section that can't show anything yet.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Placeholder: "Not built yet", "WooCommerce
 * isn't active". Replaces a section; Empty state sits inside a list.
 *
 * @phpstan-type PlaceholderProps array{ lead: string, text?: string, action_html?: string }
 */
final class Placeholder {

	use Renders;

	/**
	 * The placeholder.
	 *
	 * @param array<string, mixed> $props lead, text, action_html.
	 * @phpstan-param PlaceholderProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Placeholder', $props, 'lead' ) ) {
			return '';
		}

		return '<section class="bwc-placeholder">'
			. '<p class="bwc-placeholder__lead">' . esc_html( (string) $props['lead'] ) . '</p>'
			. ( empty( $props['text'] ) ? '' : '<p class="bwc-placeholder__text">' . esc_html( (string) $props['text'] ) . '</p>' )
			. ( empty( $props['action_html'] ) ? '' : '<div class="bwc-placeholder__action">' . $props['action_html'] . '</div>' )
			. '</section>';
	}
}
