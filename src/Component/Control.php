<?php
/**
 * Shared markup for Select and Input: the label and the common attributes.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Control: one bwc-control block for
 * every text box, number box, select and textarea.
 *
 * @phpstan-type ControlProps array{
 *     id: string,
 *     name: string,
 *     label: string,
 *     label_visible?: bool,
 *     value?: string,
 *     options?: array<string, string>,
 *     type?: string,
 *     placeholder?: string,
 *     auto_submit?: bool,
 *     width?: 'auto'|'full',
 *     mono?: bool,
 *     rows?: int,
 *     required?: bool,
 *     disabled?: bool,
 *     invalid?: bool,
 *     describedby?: string,
 * }
 */
final class Control {

	/**
	 * The label, visible or for screen readers only.
	 *
	 * @param array<string, mixed> $props id, label, label_visible.
	 */
	public static function label( array $props ): string {
		return '<label class="' . ( empty( $props['label_visible'] ) ? 'bwc-visually-hidden' : 'bwc-label' ) . '" for="' . esc_attr( (string) $props['id'] ) . '">' . esc_html( (string) $props['label'] ) . '</label>';
	}

	/**
	 * Attributes every control shares.
	 *
	 * @param array<string, mixed> $props See ControlProps.
	 * @param list<string>         $modifiers Extra bwc-control modifiers.
	 * @return array<string, mixed>
	 */
	public static function attributes( array $props, array $modifiers = array() ): array {
		return array(
			'class'                => Html::classes(
				'bwc-control',
				array_merge(
					$modifiers,
					array(
						'full' => 'full' === ( $props['width'] ?? 'auto' ),
						'mono' => ! empty( $props['mono'] ),
					)
				)
			),
			'id'                   => (string) $props['id'],
			'name'                 => (string) $props['name'],
			'required'             => ! empty( $props['required'] ),
			'disabled'             => ! empty( $props['disabled'] ),
			'aria-invalid'         => empty( $props['invalid'] ) ? null : 'true',
			'aria-describedby'     => empty( $props['describedby'] ) ? null : (string) $props['describedby'],
			'data-bwc-auto-submit' => ! empty( $props['auto_submit'] ),
		);
	}
}
