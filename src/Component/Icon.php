<?php
/**
 * Inline icons, drawn with the text colour.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Component;

defined( 'ABSPATH' ) || exit;

/**
 * Icon. Decorative unless given a label.
 *
 * @phpstan-type IconProps array{ name: string, size?: int, label?: string }
 */
final class Icon {

	use Renders;

	/**
	 * Icons plugins registered, name => SVG inner markup.
	 *
	 * @var array<string, string>
	 */
	private static array $registered = array();

	/**
	 * The kit's icons: 24×24 strokes.
	 */
	private const PATHS = array(
		'ticket'     => '<path d="M3 9V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a3 3 0 0 0 0 6v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a3 3 0 0 0 0-6z"/><path d="M14 5v2M14 11v2M14 17v2"/>',
		'search'     => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
		'refresh'    => '<path d="M20 12a8 8 0 1 1-2.3-5.7"/><path d="M20 4v5h-5"/>',
		'alert'      => '<path d="M12 3l9.5 17h-19z"/><path d="M12 10v4M12 17.5v.01"/>',
		'error'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16v.01"/>',
		'info'       => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/>',
		'check'      => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'close'      => '<path d="M6 6l12 12M18 6L6 18"/>',
		'chevron'    => '<path d="M9 6l6 6-6 6"/>',
		'chevrons'   => '<path d="M6 6l6 6-6 6M13 6l6 6-6 6"/>',
		'copy'       => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a1 1 0 0 1 1-1h10"/>',
		'grip'       => '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
		'external'   => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
		'pause'      => '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
		'play'       => '<path d="M8 5.5l11 6.5-11 6.5z"/>',
		'video'      => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10.5l5-3v9l-5-3z"/>',
		'quiz'       => '<path d="M10 6h10M10 12h10M10 18h10"/><path d="M3.5 6l1.5 1.5 2.5-2.5M3.5 12l1.5 1.5 2.5-2.5M3.5 18l1.5 1.5 2.5-2.5"/>',
		'question'   => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.4 2.3c-.6.3-.9.9-.9 1.5V14M12 17v.01"/>',
		'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
		'download'   => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'upload'     => '<path d="M12 15V4M7 9l5-5 5 5M5 20h14"/>',
		'arrow-up'   => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
		'plus'       => '<path d="M12 5v14M5 12h14"/>',
		'trash'      => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>',
		'settings'   => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M3 12h2.5M18.5 12H21M5.6 5.6l1.8 1.8M16.6 16.6l1.8 1.8M5.6 18.4l1.8-1.8M16.6 7.4l1.8-1.8"/>',
	);

	/**
	 * Adds an icon a plugin needs. Its markup is SVG shapes for a 24×24
	 * viewBox, drawn with "currentColor" strokes, and must be trusted.
	 *
	 * @param string $name  Icon name.
	 * @param string $paths SVG inner markup.
	 */
	public static function register( string $name, string $paths ): void {
		self::$registered[ sanitize_key( $name ) ] = $paths;
	}

	/**
	 * Every icon name.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array_keys( self::PATHS + self::$registered );
	}

	/**
	 * The icon as an inline SVG.
	 *
	 * @param array<string, mixed> $props name, size (14, 16 or 20), label.
	 * @phpstan-param IconProps $props
	 */
	public static function markup( array $props ): string {
		if ( ! Html::has( 'Icon', $props, 'name' ) ) {
			return '';
		}

		$name  = (string) $props['name'];
		$paths = self::$registered[ $name ] ?? self::PATHS[ $name ] ?? '';
		$size  = (int) Html::pick( $props['size'] ?? 16, array( 14, 16, 20 ), 16 );
		$label = isset( $props['label'] ) ? (string) $props['label'] : '';

		if ( '' === $paths ) {
			return '';
		}

		return '<svg' . Html::attributes(
			array(
				'class'           => 'bwc-icon',
				'width'           => $size,
				'height'          => $size,
				'viewBox'         => '0 0 24 24',
				'fill'            => 'none',
				'stroke'          => 'currentColor',
				'stroke-width'    => 2,
				'stroke-linecap'  => 'round',
				'stroke-linejoin' => 'round',
				'aria-hidden'     => '' === $label ? 'true' : null,
				'role'            => '' === $label ? null : 'img',
				'aria-label'      => '' === $label ? null : $label,
				'focusable'       => 'false',
			)
		) . '>' . $paths . '</svg>';
	}
}
