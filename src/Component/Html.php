<?php
/**
 * Small helpers every component uses.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Attributes, class lists, ids, tones and required props, done the same way
 * in every component.
 */
final class Html {

	/**
	 * The six tones pills, dots and tiles share.
	 */
	public const TONES = array( 'neutral', 'success', 'warning', 'danger', 'info', 'purple' );

	/**
	 * Ids handed out in this request, per prefix.
	 *
	 * @var array<string, int>
	 */
	private static array $ids = array();

	/**
	 * An attribute string: name="value" pairs, escaped. True prints the bare
	 * name, false and null leave the attribute out.
	 *
	 * @param array<string, string|int|float|bool|null> $attributes Name => value.
	 */
	public static function attributes( array $attributes ): string {
		$out = '';

		foreach ( $attributes as $name => $value ) {
			if ( null === $value || false === $value ) {
				continue;
			}

			$name = (string) preg_replace( '/[^a-z0-9_:-]/i', '', (string) $name );

			$out .= true === $value ? ' ' . $name : ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}

		return $out;
	}

	/**
	 * A class attribute value from a block and its modifiers.
	 *
	 * @param string                    $block     Block, such as "bwc-tile".
	 * @param array<int|string, mixed>  $modifiers Modifier names, or name => condition.
	 * @param string                    $extra     Extra classes (states, a plugin's own class).
	 */
	public static function classes( string $block, array $modifiers = array(), string $extra = '' ): string {
		$classes = array( $block );

		foreach ( $modifiers as $key => $value ) {
			if ( is_int( $key ) ) {
				if ( is_string( $value ) && '' !== $value ) {
					$classes[] = $block . '--' . $value;
				}
			} elseif ( $value ) {
				$classes[] = $block . '--' . $key;
			}
		}

		if ( '' !== trim( $extra ) ) {
			$classes[] = trim( $extra );
		}

		return implode( ' ', $classes );
	}

	/**
	 * A tone that exists, or the fallback.
	 *
	 * @param mixed    $tone     Requested tone.
	 * @param string[] $allowed  Allowed tones.
	 * @param string   $fallback Fallback tone.
	 */
	public static function tone( mixed $tone, array $allowed = self::TONES, string $fallback = 'neutral' ): string {
		return is_string( $tone ) && in_array( $tone, $allowed, true ) ? $tone : $fallback;
	}

	/**
	 * A value from a list of allowed ones, or the fallback.
	 *
	 * @param mixed                $value    Requested value.
	 * @param array<int, int|string> $allowed Allowed values.
	 * @param int|string           $fallback Fallback.
	 */
	public static function pick( mixed $value, array $allowed, int|string $fallback ): int|string {
		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * A unique HTML id in this request, prefixed with the plugin id.
	 *
	 * @param string $name What it labels, such as "card-title".
	 */
	public static function id( string $name ): string {
		$prefix = ( Kit::is_booted() ? Kit::instance()->id() . '-' : '' ) . sanitize_html_class( $name );

		self::$ids[ $prefix ] = ( self::$ids[ $prefix ] ?? 0 ) + 1;

		return $prefix . '-' . self::$ids[ $prefix ];
	}

	/**
	 * Forgets handed-out ids. Tests only, for stable snapshots.
	 */
	public static function reset_ids(): void {
		self::$ids = array();
	}

	/**
	 * Whether required props are present and not empty. In debug mode a
	 * missing one throws; otherwise the component prints nothing and
	 * WordPress logs a "doing it wrong" notice.
	 *
	 * @param string               $component Component name, for the message.
	 * @param array<string, mixed> $props     Props.
	 * @param string               ...$keys   Required keys.
	 * @throws \InvalidArgumentException In debug mode, when one is missing.
	 */
	public static function has( string $component, array $props, string ...$keys ): bool {
		foreach ( $keys as $key ) {
			$value = $props[ $key ] ?? null;

			if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) || ( is_array( $value ) && ! $value ) ) {
				$message = "Admin Kit {$component}: \"{$key}\" is required.";

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					throw new \InvalidArgumentException( esc_html( $message ) );
				}

				_doing_it_wrong( esc_html( $component ), esc_html( $message ), esc_html( Kit::VERSION ) );

				return false;
			}
		}

		return true;
	}

	/**
	 * A number or text for display: integers get the site's number format.
	 *
	 * @param int|float|string $value Value.
	 */
	public static function number( int|float|string $value ): string {
		if ( is_int( $value ) ) {
			return number_format_i18n( $value );
		}

		if ( is_float( $value ) ) {
			return number_format_i18n( $value, floor( $value ) === $value ? 0 : 1 );
		}

		return $value;
	}
}
