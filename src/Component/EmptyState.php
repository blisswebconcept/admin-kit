<?php
/**
 * "Nothing here" inside a list, card or filter result.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Empty state.
 *
 * @phpstan-type EmptyStateProps array{ text: string, action_html?: string, variant?: 'plain'|'boxed'|'inline' }
 */
final class EmptyState {

	use Renders;

	/**
	 * The empty state.
	 *
	 * @param array<string, mixed> $props text, action_html, variant.
	 * @phpstan-param EmptyStateProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'EmptyState', $props, 'text' ) ) {
			return '';
		}

		$variant = (string) Html::pick( $props['variant'] ?? 'plain', array( 'plain', 'boxed', 'inline' ), 'plain' );
		$class   = Html::classes( 'bwc-empty', array( 'plain' === $variant ? '' : $variant ) );

		if ( empty( $props['action_html'] ) ) {
			return '<p class="' . esc_attr( $class ) . '">' . esc_html( (string) $props['text'] ) . '</p>';
		}

		return '<div class="' . esc_attr( $class ) . '"><p class="bwc-empty__text">' . esc_html( (string) $props['text'] ) . '</p>'
			. '<div class="bwc-empty__action">' . $props['action_html'] . '</div></div>';
	}
}
