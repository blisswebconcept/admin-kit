<?php
/**
 * Every button and button-styled link.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Button. With a url it's a link; without, a
 * button. A disabled link has no href and aria-disabled, since links can't be
 * disabled.
 *
 * @phpstan-type ButtonProps array{
 *     label: string,
 *     variant?: 'secondary'|'primary'|'outline'|'link'|'danger',
 *     size?: 'regular'|'small',
 *     icon?: string,
 *     icon_only?: bool,
 *     url?: string,
 *     type?: 'button'|'submit',
 *     name?: string,
 *     value?: string,
 *     pressed?: bool|null,
 *     disabled?: bool,
 *     title?: string,
 *     confirm?: string,
 *     hook?: string,
 *     class?: string,
 *     form?: string|null,
 * }
 */
final class Button {

	use Renders;

	/**
	 * The button.
	 *
	 * @param array<string, mixed> $props label, variant, size, icon, icon_only, url, type, name, value, pressed, disabled, title (shown on hover), confirm, hook, class, form (id of the form it submits, when it sits outside it).
	 * @phpstan-param ButtonProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Button', $props, 'label' ) ) {
			return '';
		}

		$variant   = (string) Html::pick( $props['variant'] ?? 'secondary', array( 'secondary', 'primary', 'outline', 'link', 'danger' ), 'secondary' );
		$icon      = empty( $props['icon'] ) ? '' : (string) $props['icon'];
		$icon_only = '' !== $icon && ! empty( $props['icon_only'] );
		$disabled  = ! empty( $props['disabled'] );
		$hook      = empty( $props['hook'] ) ? '' : sanitize_key( (string) $props['hook'] );
		$pressed   = $props['pressed'] ?? null;

		$attributes = array(
			'class'            => Html::classes(
				'bwc-button',
				array(
					'secondary' === $variant ? '' : $variant,
					'small' => 'small' === ( $props['size'] ?? 'regular' ),
					'icon'  => $icon_only,
				),
				(string) ( $props['class'] ?? '' )
			),
			'aria-pressed'     => null === $pressed ? null : ( $pressed ? 'true' : 'false' ),
			'title'            => empty( $props['title'] ) ? null : (string) $props['title'],
			'data-bwc-confirm' => empty( $props['confirm'] ) ? null : (string) $props['confirm'],
		);

		if ( '' !== $hook ) {
			$attributes[ 'data-bwc-' . $hook ] = true;
		}

		$inner = ( '' === $icon ? '' : '<span class="bwc-button__icon" aria-hidden="true">' . Icon::markup( array( 'name' => $icon ) ) . '</span>' )
			. ( $icon_only
				? '<span class="bwc-visually-hidden">' . esc_html( (string) $props['label'] ) . '</span>'
				: '<span class="bwc-button__label">' . esc_html( (string) $props['label'] ) . '</span>' );

		if ( ! empty( $props['url'] ) ) {
			$link = $disabled
				? array( 'aria-disabled' => 'true' )
				: array( 'href' => esc_url( (string) $props['url'] ) );

			return '<a' . Html::attributes( array_merge( $attributes, $link ) ) . '>' . $inner . '</a>';
		}

		return '<button' . Html::attributes(
			array_merge(
				array( 'type' => 'submit' === ( $props['type'] ?? 'button' ) ? 'submit' : 'button' ),
				$attributes,
				array(
					'name'     => empty( $props['name'] ) ? null : (string) $props['name'],
					'value'    => isset( $props['value'] ) ? (string) $props['value'] : null,
					'form'     => empty( $props['form'] ) ? null : (string) $props['form'],
					'disabled' => $disabled,
				)
			)
		) . '>' . $inner . '</button>';
	}
}
