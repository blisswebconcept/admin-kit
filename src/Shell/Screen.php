<?php
/**
 * The root of every kit admin page.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Shell;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Scope classes, the header bar (brand, navigation, actions, version), the
 * WordPress notice spot, the live region and the content.
 *
 * @phpstan-type ScreenProps array{
 *     title: string,
 *     version?: string,
 *     icon_html?: string,
 *     subtitle?: string,
 *     actions_html?: string,
 *     nav?: array<string, mixed>,
 *     content_html?: string,
 * }
 */
final class Screen {

	/**
	 * The whole screen as HTML.
	 *
	 * @param array<string, mixed> $props title, version, icon_html, subtitle, actions_html, nav (Nav props), content_html.
	 * @phpstan-param ScreenProps $props
	 */
	public static function markup( array $props ): string {
		return self::open_markup( $props ) . ( $props['content_html'] ?? '' ) . self::close_markup();
	}

	/**
	 * Prints the whole screen.
	 *
	 * @param array<string, mixed> $props See markup().
	 * @phpstan-param ScreenProps $props
	 */
	public static function render( array $props ): void {
		echo self::markup( $props ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
	}

	/**
	 * Prints the start of the screen, for screens that echo their body.
	 *
	 * @param array<string, mixed> $props See markup(), without content_html.
	 * @phpstan-param ScreenProps $props
	 */
	public static function open( array $props ): void {
		echo self::open_markup( $props ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in open_markup().
	}

	/**
	 * Prints the end of a screen started with open().
	 */
	public static function close(): void {
		echo self::close_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
	}

	/**
	 * Start of the screen.
	 *
	 * @param array<string, mixed> $props See markup().
	 * @phpstan-param ScreenProps $props
	 * @throws \InvalidArgumentException When the title is missing and WP_DEBUG is on.
	 */
	private static function open_markup( array $props ): string {
		$kit = Kit::instance();
		// Without a title: an error while debugging; otherwise the menu's own title, so the screen still opens.
		$title = Html::has( 'Screen', $props, 'title' ) ? trim( (string) $props['title'] ) : trim( wp_strip_all_tags( get_admin_page_title() ) );

		$html  = '<div class="wrap ' . esc_attr( $kit->root_classes() ) . ' bwc-screen">';
		$html .= '<div class="bwc-screen__bar" data-bwc-screen-bar>';
		$html .= '<div class="bwc-screen__brand"><span class="bwc-screen__icon" aria-hidden="true">' . ( $props['icon_html'] ?? '' ) . '</span>';
		$html .= '<div class="bwc-screen__titles"><h1 class="bwc-screen__title">' . esc_html( $title ) . '</h1>';

		if ( ! empty( $props['subtitle'] ) ) {
			$html .= '<p class="bwc-screen__subtitle">' . esc_html( (string) $props['subtitle'] ) . '</p>';
		}

		$html .= '</div></div>';
		$html .= empty( $props['nav'] ) ? '' : '<div class="bwc-screen__nav">' . Nav::markup( (array) $props['nav'] ) . '</div>';

		if ( ! empty( $props['actions_html'] ) ) {
			$html .= '<div class="bwc-screen__actions">' . $props['actions_html'] . '</div>';
		}

		if ( ! empty( $props['version'] ) ) {
			$html .= '<span class="bwc-screen__version">' . esc_html( $kit->strings()->get( 'version_short', (string) $props['version'] ) ) . '</span>';
		}

		$html .= '</div>';
		$html .= '<hr class="wp-header-end">';
		$html .= '<p class="bwc-visually-hidden" aria-live="polite" data-bwc-live></p>';
		$html .= '<div class="bwc-screen__content">';
		$html .= $kit->notices()->markup();

		return $html;
	}

	/**
	 * End of the screen: the content closes, then any action forms.
	 */
	private static function close_markup(): string {
		// Forms of action buttons that sat inside another form (Actions::button()).
		return '</div>' . Kit::instance()->actions()->forms() . '</div>';
	}
}
