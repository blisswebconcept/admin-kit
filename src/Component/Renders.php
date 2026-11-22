<?php
/**
 * The render() every component has.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Echoes the component's markup().
 */
trait Renders {

	/**
	 * Prints the component.
	 *
	 * @param array<string, mixed> $props Props, as markup() takes them.
	 */
	public static function render( array $props ): void {
		echo static::markup( $props ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes.
	}

	/**
	 * The component as escaped HTML.
	 *
	 * @param array<string, mixed> $props Props.
	 */
	abstract public static function markup( array $props ): string;
}
