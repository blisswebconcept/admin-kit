<?php
/**
 * Facts about one record, as a key-value list.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Key-value list: a member's profile, a link's details.
 *
 * @phpstan-type FactsProps array{ items: list<array{ label: string, value?: string, value_html?: string }>, layout?: 'columns'|'rows' }
 */
final class Facts {

	use Renders;

	/**
	 * The list.
	 *
	 * @param array<string, mixed> $props items, layout.
	 * @phpstan-param FactsProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Facts', $props, 'items' ) ) {
			return '';
		}

		$html = '<dl class="' . esc_attr( Html::classes( 'bwc-facts', array( 'rows' => 'rows' === ( $props['layout'] ?? 'columns' ) ) ) ) . '">';

		foreach ( (array) $props['items'] as $item ) {
			$value = isset( $item['value_html'] ) ? (string) $item['value_html'] : esc_html( (string) ( $item['value'] ?? '' ) );
			$html .= '<div class="bwc-facts__item"><dt class="bwc-facts__label">' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</dt><dd class="bwc-facts__value">' . $value . '</dd></div>';
		}

		return $html . '</dl>';
	}
}
