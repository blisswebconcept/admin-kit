<?php
/**
 * A screen's sections as tabs or a side menu.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Shell;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Renders;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Nav. Links, not ARIA tabs: every item is a
 * real page or anchor, so it works without the script.
 *
 * @phpstan-type NavItem array{ label: string, url: string, badge?: int|string, badge_tone?: 'info'|'danger', meta?: string, hook?: string }
 * @phpstan-type NavProps array{ label: string, layout?: 'tabs'|'side', current: string, items: array<string, NavItem> }
 */
final class Nav {

	use Renders;

	/**
	 * The navigation.
	 *
	 * @param array<string, mixed> $props label, layout, current, items.
	 * @phpstan-param NavProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Nav', $props, 'label', 'items' ) ) {
			return '';
		}

		$layout  = (string) Html::pick( $props['layout'] ?? 'tabs', array( 'tabs', 'side' ), 'tabs' );
		$current = (string) ( $props['current'] ?? '' );
		$html    = '<nav class="' . esc_attr( Html::classes( 'bwc-nav', array( $layout ) ) ) . '" aria-label="' . esc_attr( (string) $props['label'] ) . '"' . ( 'tabs' === $layout ? ' data-bwc-scroll-current' : '' ) . '>';

		foreach ( (array) $props['items'] as $key => $item ) {
			$html .= self::item( (string) $key, (array) $item, (string) $key === $current, $layout );
		}

		return $html . '</nav>';
	}

	/**
	 * One item.
	 *
	 * @param string               $key     Item key.
	 * @param array<string, mixed> $item    Item.
	 * @param bool                 $current Whether it's the current one.
	 * @param string               $layout  "tabs" or "side".
	 */
	private static function item( string $key, array $item, bool $current, string $layout ): string {
		$hook   = isset( $item['hook'] ) ? (string) $item['hook'] : '';
		$badge  = $item['badge'] ?? '';
		$output = '<a' . Html::attributes(
			array(
				'class'             => 'bwc-nav__item' . ( $current ? ' is-current' : '' ),
				'href'              => esc_url( (string) ( $item['url'] ?? '#' ) ),
				'aria-current'      => $current ? ( '' === $hook ? 'page' : 'true' ) : null,
				'data-bwc-nav-item' => '' === $hook ? null : $hook,
			)
		) . '><span class="bwc-nav__label">' . esc_html( (string) ( $item['label'] ?? $key ) ) . '</span>';

		if ( '' !== (string) $badge && '0' !== (string) $badge ) {
			$danger  = 'danger' === ( $item['badge_tone'] ?? 'info' );
			$output .= '<span class="' . esc_attr( Html::classes( 'bwc-nav__badge', array( 'danger' => $danger ) ) ) . '">'
				. esc_html( is_int( $badge ) ? number_format_i18n( $badge ) : (string) $badge )
				. '<span class="bwc-visually-hidden"> ' . esc_html( Kit::instance()->strings()->get( $danger ? 'badge_attention' : 'badge_waiting' ) ) . '</span></span>';
		}

		if ( 'side' === $layout && ! empty( $item['meta'] ) ) {
			$output .= '<span class="bwc-nav__meta" data-bwc-nav-meta>' . esc_html( (string) $item['meta'] ) . '</span>';
		}

		return $output . '</a>';
	}
}
