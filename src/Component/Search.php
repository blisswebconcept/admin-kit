<?php
/**
 * A search box with its icon and a label.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Search.
 *
 * @phpstan-type SearchProps array{ id: string, name: string, value?: string, label: string, label_visible?: bool, placeholder?: string, hook?: string }
 */
final class Search {

	use Renders;

	/**
	 * The search box.
	 *
	 * @param array<string, mixed> $props id, name, value, label, label_visible, placeholder, hook.
	 * @phpstan-param SearchProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Search', $props, 'id', 'name', 'label' ) ) {
			return '';
		}

		$hook  = empty( $props['hook'] ) ? '' : sanitize_key( (string) $props['hook'] );
		$input = array(
			'class'       => 'bwc-search__input',
			'type'        => 'search',
			'id'          => (string) $props['id'],
			'name'        => (string) $props['name'],
			'value'       => (string) ( $props['value'] ?? '' ),
			'placeholder' => empty( $props['placeholder'] ) ? null : (string) $props['placeholder'],
		);

		if ( '' !== $hook ) {
			$input[ 'data-bwc-' . $hook ] = true;
		}

		return '<div class="bwc-search">'
			. '<label class="' . ( empty( $props['label_visible'] ) ? 'bwc-visually-hidden' : 'bwc-label' ) . '" for="' . esc_attr( (string) $props['id'] ) . '">' . esc_html( (string) $props['label'] ) . '</label>'
			. '<span class="bwc-search__field"><span class="bwc-search__icon" aria-hidden="true">' . Icon::markup( array( 'name' => 'search' ) ) . '</span>'
			. '<input' . Html::attributes( $input ) . '></span></div>';
	}
}
