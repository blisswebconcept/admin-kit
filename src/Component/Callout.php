<?php
/**
 * A short aside next to data.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Callout: explains the data beside it and
 * never needs action; a Notice reports a state or problem.
 *
 * @phpstan-type CalloutProps array{ text?: string, content_html?: string, mark?: 'info'|'warning' }
 */
final class Callout {

	use Renders;

	/**
	 * The callout.
	 *
	 * @param array<string, mixed> $props text or content_html, mark.
	 * @phpstan-param CalloutProps $props
	 */
	public static function markup( array $props ): string {
		$body = ! empty( $props['content_html'] ) ? (string) $props['content_html'] : ( empty( $props['text'] ) ? '' : '<p>' . esc_html( (string) $props['text'] ) . '</p>' );

		if ( '' === $body ) {
			Html::has( 'Callout', $props, 'text' );
			return '';
		}

		$warning = 'warning' === ( $props['mark'] ?? 'info' );

		return '<div class="' . esc_attr( Html::classes( 'bwc-callout', array( 'warning' => $warning ) ) ) . '" role="note">'
			. '<span class="bwc-callout__mark" aria-hidden="true">' . ( $warning ? '!' : 'i' ) . '</span>'
			. '<div class="bwc-callout__body">' . $body . '</div>'
			. '</div>';
	}
}
