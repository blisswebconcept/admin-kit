<?php
/**
 * Freshness: how old the data on a report is, and a way to refresh it.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Freshness: "Data read 4 minutes ago · Refresh data".
 *
 * @phpstan-type FreshnessProps array{ built: int|null, refresh_html?: string }
 */
final class Freshness {

	use Renders;

	/**
	 * The line; '' when nothing has been read yet and there's nothing to refresh.
	 *
	 * @param array<string, mixed> $props built (Unix time the data was read), refresh_html (the Refresh button).
	 * @phpstan-param FreshnessProps $props
	 */
	public static function markup( array $props ): string {
		$built   = isset( $props['built'] ) ? (int) $props['built'] : 0;
		$refresh = (string) ( $props['refresh_html'] ?? '' );

		if ( $built <= 0 && '' === $refresh ) {
			return '';
		}

		$text = $built > 0 ? Kit::instance()->strings()->get( 'data_read', human_time_diff( $built, time() ) ) : '';

		return '<div class="bwc-freshness">'
			. ( '' === $text ? '' : '<time class="bwc-freshness__text" datetime="' . esc_attr( gmdate( 'c', $built ) ) . '">' . esc_html( $text ) . '</time>' )
			. $refresh . '</div>';
	}
}
