<?php
/**
 * Vertical spacing between siblings.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Stack.
 *
 * @phpstan-type StackProps array{ items_html: list<string>, gap?: 1|2|3|4|5|6, tag?: 'div'|'section' }
 */
final class Stack {

	use Renders;

	/**
	 * The stack.
	 *
	 * @param array<string, mixed> $props items_html, gap, tag.
	 * @phpstan-param StackProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Stack', $props, 'items_html' ) ) {
			return '';
		}

		$gap = Html::pick( $props['gap'] ?? 4, array( 1, 2, 3, 4, 5, 6 ), 4 );
		$tag = (string) Html::pick( $props['tag'] ?? 'div', array( 'div', 'section' ), 'div' );

		return '<' . $tag . ' class="' . esc_attr( Html::classes( 'bwc-stack', array( 'gap-' . $gap ) ) ) . '">' . implode( '', (array) $props['items_html'] ) . '</' . $tag . '>';
	}
}
