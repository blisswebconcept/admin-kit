<?php
/**
 * A row of items that wraps.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Cluster: button groups, chip rows, toolbar rows.
 *
 * @phpstan-type ClusterProps array{ items_html: list<string>, gap?: 1|2|3, align?: 'start'|'center'|'baseline', justify?: 'start'|'between'|'end' }
 */
final class Cluster {

	use Renders;

	/**
	 * The cluster.
	 *
	 * @param array<string, mixed> $props items_html, gap, align, justify.
	 * @phpstan-param ClusterProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Cluster', $props, 'items_html' ) ) {
			return '';
		}

		$gap     = Html::pick( $props['gap'] ?? 2, array( 1, 2, 3 ), 2 );
		$align   = (string) Html::pick( $props['align'] ?? 'center', array( 'start', 'center', 'baseline' ), 'center' );
		$justify = (string) Html::pick( $props['justify'] ?? 'start', array( 'start', 'between', 'end' ), 'start' );

		$modifiers = array( 'gap-' . $gap );
		if ( 'center' !== $align ) {
			$modifiers[] = 'align-' . $align;
		}
		if ( 'start' !== $justify ) {
			$modifiers[] = $justify;
		}

		return '<div class="' . esc_attr( Html::classes( 'bwc-cluster', $modifiers ) ) . '">' . implode( '', (array) $props['items_html'] ) . '</div>';
	}
}
