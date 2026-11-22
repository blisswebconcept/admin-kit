<?php
/**
 * "Expand all / Collapse all" for the collapsible groups in one container.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Expand all. Hidden until the
 * script runs; each <details> still opens on its own without it.
 *
 * @phpstan-type ExpandAllProps array{ target: string }
 */
final class ExpandAll {

	use Renders;

	/**
	 * The button.
	 *
	 * @param array<string, mixed> $props target: id of the element holding the groups.
	 * @phpstan-param ExpandAllProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'ExpandAll', $props, 'target' ) ) {
			return '';
		}

		return '<button' . Html::attributes(
			array(
				'type'                    => 'button',
				'class'                   => 'bwc-button bwc-button--small bwc-button--link',
				'data-bwc-toggle-details' => (string) $props['target'],
				'aria-controls'           => (string) $props['target'],
				'hidden'                  => true,
			)
		) . '><span class="bwc-button__label" data-bwc-toggle-details-label>' . esc_html( Kit::instance()->strings()->get( 'expand_all' ) ) . '</span></button>';
	}
}
