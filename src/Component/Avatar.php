<?php
/**
 * A person's initials in a circle.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Avatar: initials only, no Gravatar, so admin
 * screens make no outside requests. Always decorative: the name is printed
 * next to it.
 *
 * @phpstan-type AvatarProps array{ name: string, size?: 'small'|'large' }
 */
final class Avatar {

	use Renders;

	/**
	 * The avatar.
	 *
	 * @param array<string, mixed> $props name, size.
	 * @phpstan-param AvatarProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Avatar', $props, 'name' ) ) {
			return '';
		}

		return '<span class="' . esc_attr( Html::classes( 'bwc-avatar', array( 'large' => 'large' === ( $props['size'] ?? 'small' ) ) ) ) . '" aria-hidden="true">'
			. esc_html( self::initials( (string) $props['name'] ) )
			. '</span>';
	}

	/**
	 * First letters of the first two words, upper case, multibyte-safe.
	 *
	 * @param string $name Name or email.
	 */
	public static function initials( string $name ): string {
		$name  = (string) preg_replace( '/@.*$/', '', trim( $name ) );
		$words = preg_split( '/[\s._-]+/u', $name, -1, PREG_SPLIT_NO_EMPTY );
		$words = is_array( $words ) ? array_slice( $words, 0, 2 ) : array();

		$initials = '';
		foreach ( $words as $word ) {
			$initials .= mb_substr( $word, 0, 1 );
		}

		return '' === $initials ? '?' : mb_strtoupper( $initials );
	}
}
