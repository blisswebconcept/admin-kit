<?php
/**
 * A row of filter choices with counts.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Chips. Links filter the list through the URL;
 * buttons filter in place and stay hidden until the script runs, since
 * without it they'd do nothing.
 *
 * @phpstan-type ChipItem array{ label: string, count?: int, tone?: 'default'|'danger', url?: string }
 * @phpstan-type ChipsProps array{ label: string, kind?: 'links'|'buttons', current: string, items: array<string, ChipItem>, target?: string }
 */
final class Chips {

	use Renders;

	/**
	 * The chips.
	 *
	 * @param array<string, mixed> $props label, kind, current, items, target.
	 * @phpstan-param ChipsProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Chips', $props, 'label', 'items' ) ) {
			return '';
		}

		$buttons = 'buttons' === ( $props['kind'] ?? 'links' );
		$current = (string) ( $props['current'] ?? '' );
		$html    = '<div' . Html::attributes(
			array(
				'class'            => 'bwc-chips',
				'role'             => 'group',
				'aria-label'       => (string) $props['label'],
				'data-bwc-filters' => $buttons ? (string) ( $props['target'] ?? '' ) : null,
				'hidden'           => $buttons,
			)
		) . '>';

		foreach ( (array) $props['items'] as $key => $item ) {
			$on    = (string) $key === $current;
			$class = Html::classes( 'bwc-chip', array( 'danger' => 'danger' === ( $item['tone'] ?? 'default' ) ), $on && ! $buttons ? 'is-current' : '' );
			$inner = esc_html( (string) ( $item['label'] ?? $key ) )
				. ( isset( $item['count'] ) ? ' <span class="bwc-chip__count">' . esc_html( Html::number( (int) $item['count'] ) ) . '</span>' : '' );

			$html .= $buttons
				? '<button' . Html::attributes(
					array(
						'type'            => 'button',
						'class'           => $class,
						'aria-pressed'    => $on ? 'true' : 'false',
						'data-bwc-filter' => (string) $key,
					)
				) . '>' . $inner . '</button>'
				: '<a' . Html::attributes(
					array(
						'class'        => $class,
						'href'         => esc_url( (string) ( $item['url'] ?? '#' ) ),
						'aria-current' => $on ? 'true' : null,
					)
				) . '>' . $inner . '</a>';
		}

		return $html . '</div>';
	}
}
