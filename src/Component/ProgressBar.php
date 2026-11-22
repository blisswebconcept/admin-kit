<?php
/**
 * Progress bar: a fixed share, such as course completion.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Progress bar. A native <meter>, so screen
 * readers hear the value and no inline width is needed; the visible number
 * means colour and length never carry it alone.
 *
 * @phpstan-type ProgressBarProps array{ value: float|int, max?: float|int, label: string, show_value?: bool, tone?: 'accent'|'success'|'warning'|'danger', size?: 'thin'|'regular' }
 */
final class ProgressBar {

	use Renders;

	/**
	 * The bar.
	 *
	 * @param array<string, mixed> $props value, max, label, show_value, tone, size.
	 * @phpstan-param ProgressBarProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'ProgressBar', $props, 'label' ) ) {
			return '';
		}

		$max     = max( 0.0001, (float) ( $props['max'] ?? 100 ) );
		$value   = min( $max, max( 0, (float) ( $props['value'] ?? 0 ) ) );
		$percent = (int) round( 100 * $value / $max );
		$tone    = (string) Html::pick( $props['tone'] ?? 'accent', array( 'accent', 'success', 'warning', 'danger' ), 'accent' );
		$text    = Html::number( $percent ) . '%';

		return '<span class="' . esc_attr(
			Html::classes(
				'bwc-bar',
				array(
					'success' => 'success' === $tone,
					'warning' => 'warning' === $tone,
					'danger'  => 'danger' === $tone,
					'regular' => 'regular' === ( $props['size'] ?? 'thin' ),
				)
			)
		) . '"><meter class="bwc-bar__meter" min="0" max="100" value="' . esc_attr( (string) $percent ) . '" aria-label="' . esc_attr( (string) $props['label'] ) . '">' . esc_html( $text ) . '</meter>'
			. ( false === ( $props['show_value'] ?? true ) ? '' : '<span class="bwc-bar__value">' . esc_html( $text ) . '</span>' )
			. '</span>';
	}
}
