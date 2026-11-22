<?php
/**
 * Builds one plugin's copy of the kit's stylesheet and script.
 *
 * Runs from the command line in each plugin's release (bin/bwc-build), so it
 * must not need WordPress.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Build;

// A command-line tool, but it ships in every plugin's copy of the kit: never run it from a web request.
defined( 'ABSPATH' ) || 'cli' === PHP_SAPI || exit;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI build tool: runs without WordPress, messages go to a terminal.

/**
 * Joins the kit's CSS files and scopes every rule to one plugin, and joins
 * the script modules into one file that reads that plugin's config object
 *.
 *
 * Scoping: each top-level selector S becomes ".bwc-scope--{id} S" (inside a
 * kit root) and ".bwc-scope--{id}:is(S)" (the root itself). When S has a
 * combinator, its first part may be the root too, so "C1 rest" also becomes
 * ".bwc-scope--{id}:is(C1) rest" (".bwc-screen .bwc-screen__title"). The extra class
 * keeps two plugins' copies apart and lifts kit rules above WordPress's own
 * admin styles, which aren't in a cascade layer, so kit CSS doesn't use one.
 */
final class Builder {

	/**
	 * Valid plugin id: lowercase words joined by single hyphens.
	 */
	public const ID_PATTERN = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

	/**
	 * At-rules whose block holds more rules to scope. Every other block
	 * at-rule (@keyframes, @font-face, @property…) is left exactly as written.
	 */
	private const GROUPING_AT_RULES = array( 'media', 'container', 'supports', 'layer', 'scope', 'starting-style' );

	/**
	 * Constructor.
	 *
	 * @param string $id Plugin id, such as "my-plugin".
	 * @throws \InvalidArgumentException When the id isn't valid.
	 */
	public function __construct( private string $id ) {
		if ( ! preg_match( self::ID_PATTERN, $id ) ) {
			throw new \InvalidArgumentException( "bwc-build: the id \"{$id}\" must be lowercase letters and digits, words joined by single hyphens." );
		}
	}

	/**
	 * Builds both files from a kit folder into an output folder.
	 *
	 * @param string $kit_root Folder holding the kit's assets/ folder.
	 * @param string $out_dir  Folder to write {id}-bwc.css and {id}-bwc.js into.
	 * @return array{css: string, js: string} Paths written.
	 * @throws \RuntimeException When a file can't be read or written.
	 */
	public function build( string $kit_root, string $out_dir ): array {
		$kit_root = rtrim( $kit_root, '/' );

		$css_files = array_merge(
			array( $kit_root . '/assets/css/tokens.css' ),
			self::sorted_glob( $kit_root . '/assets/css/components/*.css' )
		);
		$js_files  = array_merge(
			array( $kit_root . '/assets/js/core.js' ),
			array_values( array_diff( self::sorted_glob( $kit_root . '/assets/js/*.js' ), array( $kit_root . '/assets/js/core.js' ) ) )
		);

		$css = $this->css( array_map( array( self::class, 'read' ), $css_files ) );
		$js  = $this->js( array_map( array( self::class, 'read' ), $js_files ) );

		if ( ! is_dir( $out_dir ) && ! mkdir( $out_dir, 0755, true ) && ! is_dir( $out_dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- CLI build, no WordPress.
			throw new \RuntimeException( "bwc-build: can't create {$out_dir}." );
		}

		$paths = array(
			'css' => rtrim( $out_dir, '/' ) . '/' . $this->id . '-bwc.css',
			'js'  => rtrim( $out_dir, '/' ) . '/' . $this->id . '-bwc.js',
		);

		foreach ( array(
			'css' => $css,
			'js'  => $js,
		) as $type => $content ) {
			if ( false === file_put_contents( $paths[ $type ], $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI build, no WordPress.
				throw new \RuntimeException( "bwc-build: can't write {$paths[ $type ]}." );
			}
		}

		return $paths;
	}

	/**
	 * The scoped, minified stylesheet.
	 *
	 * @param string[] $sources CSS file contents, in order.
	 */
	public function css( array $sources ): string {
		$css = self::strip_comments( implode( "\n", $sources ) );
		$css = $this->scope_block( $css );
		$css = $this->rename_keyframes( $css );

		return '/* Admin Kit for ' . $this->id . ". Built by bwc-build; don't edit. */\n" . self::minify( $css ) . "\n";
	}

	/**
	 * The joined script, wired to this plugin's config object and scope.
	 *
	 * @param string[] $modules Module contents, core.js first.
	 */
	public function js( array $modules ): string {
		return '/* Admin Kit for ' . $this->id . ". Built by bwc-build; don't edit. */\n"
			. "( () => {\n'use strict';\n"
			. 'const BWC_CONFIG = window[ ' . self::js_string( $this->script_global() ) . ' ] || {};' . "\n"
			. 'const BWC_SCOPE = ' . self::js_string( 'bwc-scope--' . $this->id ) . ";\n"
			. implode( "\n", $modules )
			. "\n} )();\n";
	}

	/**
	 * Name of the config object on window: "{idCamel}Bwc", as Kit::script_global().
	 */
	public function script_global(): string {
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $this->id ) ) ) ) . 'Bwc';
	}

	/**
	 * Scopes every rule in a block of CSS, recursing into grouping at-rules.
	 *
	 * @param string $css CSS without comments.
	 * @throws \InvalidArgumentException On @import, which the kit doesn't allow.
	 */
	private function scope_block( string $css ): string {
		$out    = '';
		$length = strlen( $css );
		$i      = 0;

		while ( $i < $length ) {
			// Prelude: up to "{" or ";" outside strings and brackets.
			$start = $i;
			$depth = 0;
			while ( $i < $length ) {
				$char = $css[ $i ];
				if ( '"' === $char || "'" === $char ) {
					$i = self::skip_string( $css, $i );
					continue;
				}
				if ( '(' === $char || '[' === $char ) {
					++$depth;
				} elseif ( ')' === $char || ']' === $char ) {
					--$depth;
				} elseif ( 0 === $depth && ( '{' === $char || ';' === $char ) ) {
					break;
				}
				++$i;
			}

			$prelude = trim( substr( $css, $start, $i - $start ) );

			if ( $i >= $length ) {
				$out .= $prelude;
				break;
			}

			if ( ';' === $css[ $i ] ) {
				// A statement at-rule such as "@layer a, b;".
				if ( preg_match( '/^@import\b/i', $prelude ) ) {
					throw new \InvalidArgumentException( 'bwc-build: @import is not allowed in kit CSS.' );
				}
				$out .= $prelude . ";\n";
				++$i;
				continue;
			}

			$block_end = self::matching_brace( $css, $i );
			$body      = substr( $css, $i + 1, $block_end - $i - 1 );
			$i         = $block_end + 1;

			if ( '' === $prelude ) {
				continue;
			}

			if ( '@' === $prelude[0] ) {
				$name = strtolower( (string) preg_replace( '/^@([a-z-]+).*$/s', '$1', $prelude ) );
				if ( in_array( $name, self::GROUPING_AT_RULES, true ) ) {
					$out .= $prelude . "{\n" . $this->scope_block( $body ) . "}\n";
				} else {
					$out .= $prelude . '{' . $body . "}\n";
				}
				continue;
			}

			$out .= $this->scope_selector_list( $prelude ) . '{' . $body . "}\n";
		}

		return $out;
	}

	/**
	 * Scopes each selector in a comma-separated list.
	 *
	 * @param string $selectors Selector list.
	 */
	public function scope_selector_list( string $selectors ): string {
		$scope  = '.bwc-scope--' . $this->id;
		$scoped = array();

		foreach ( self::split_selectors( $selectors ) as $selector ) {
			$scoped[] = $scope . ' ' . $selector;

			// The root itself carries kit classes too, but :is() can't hold pseudo-elements.
			if ( ! str_contains( $selector, '::' ) ) {
				$scoped[] = $scope . ':is(' . $selector . ')';
			}

			// The first part of a chain may be the root: ".bwc-screen .bwc-screen__title".
			$split = self::first_combinator( $selector );
			if ( null !== $split ) {
				$scoped[] = $scope . ':is(' . substr( $selector, 0, $split ) . ')' . substr( $selector, $split );
			}
		}

		return implode( ', ', $scoped );
	}

	/**
	 * Position of the first combinator (space, >, +, ~) outside brackets, or
	 * null when the selector is one compound.
	 *
	 * @param string $selector One selector.
	 */
	private static function first_combinator( string $selector ): ?int {
		$depth  = 0;
		$length = strlen( $selector );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $selector[ $i ];
			if ( '"' === $char || "'" === $char ) {
				$i = self::skip_string( $selector, $i ) - 1;
				continue;
			}
			if ( '(' === $char || '[' === $char ) {
				++$depth;
			} elseif ( ')' === $char || ']' === $char ) {
				--$depth;
			} elseif ( 0 === $depth && $i > 0 && in_array( $char, array( ' ', '>', '+', '~' ), true ) ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Gives the kit's keyframes this plugin's name, so two copies can't clash.
	 * Kit keyframe names must start with "bwc-kf-".
	 *
	 * @param string $css Scoped CSS.
	 */
	private function rename_keyframes( string $css ): string {
		return (string) preg_replace( '/(?<![.\w-])bwc-kf-([a-z0-9-]+)/', $this->id . '-bwc-kf-$1', $css );
	}

	/**
	 * Splits a selector list on top-level commas.
	 *
	 * @param string $selectors Selector list.
	 * @return string[]
	 */
	public static function split_selectors( string $selectors ): array {
		$parts   = array();
		$current = '';
		$depth   = 0;
		$length  = strlen( $selectors );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $selectors[ $i ];
			if ( '"' === $char || "'" === $char ) {
				$end      = self::skip_string( $selectors, $i );
				$current .= substr( $selectors, $i, $end - $i );
				$i        = $end - 1;
				continue;
			}
			if ( '(' === $char || '[' === $char ) {
				++$depth;
			} elseif ( ')' === $char || ']' === $char ) {
				--$depth;
			} elseif ( ',' === $char && 0 === $depth ) {
				$parts[] = trim( $current );
				$current = '';
				continue;
			}
			$current .= $char;
		}

		$parts[] = trim( $current );

		return array_values( array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );
	}

	/**
	 * Position of the "}" that closes the "{" at $open.
	 *
	 * @param string $css  CSS.
	 * @param int    $open Position of "{".
	 * @throws \InvalidArgumentException When braces don't match.
	 */
	private static function matching_brace( string $css, int $open ): int {
		$depth  = 0;
		$length = strlen( $css );

		for ( $i = $open; $i < $length; $i++ ) {
			$char = $css[ $i ];
			if ( '"' === $char || "'" === $char ) {
				$i = self::skip_string( $css, $i ) - 1;
				continue;
			}
			if ( '{' === $char ) {
				++$depth;
			} elseif ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					return $i;
				}
			}
		}

		throw new \InvalidArgumentException( 'bwc-build: a "{" in the kit CSS is never closed.' );
	}

	/**
	 * Position just after the string that starts at $start.
	 *
	 * @param string $text  Text.
	 * @param int    $start Position of the opening quote.
	 */
	private static function skip_string( string $text, int $start ): int {
		$quote  = $text[ $start ];
		$length = strlen( $text );

		for ( $i = $start + 1; $i < $length; $i++ ) {
			if ( '\\' === $text[ $i ] ) {
				++$i;
			} elseif ( $text[ $i ] === $quote ) {
				return $i + 1;
			}
		}

		return $length;
	}

	/**
	 * Removes comments, keeping strings intact.
	 *
	 * @param string $css CSS.
	 */
	public static function strip_comments( string $css ): string {
		$out    = '';
		$length = strlen( $css );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $css[ $i ];
			if ( '"' === $char || "'" === $char ) {
				$end  = self::skip_string( $css, $i );
				$out .= substr( $css, $i, $end - $i );
				$i    = $end - 1;
				continue;
			}
			if ( '/' === $char && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
				$close = strpos( $css, '*/', $i + 2 );
				$i     = false === $close ? $length : $close + 1;
				continue;
			}
			$out .= $char;
		}

		return $out;
	}

	/**
	 * Safe minifying: collapses whitespace and drops it around braces and
	 * semicolons. Leaves spaces before ":" alone, since "a :hover" and
	 * "a:hover" mean different things.
	 *
	 * @param string $css CSS without comments.
	 */
	public static function minify( string $css ): string {
		$css = (string) preg_replace( '/\s+/', ' ', $css );
		$css = (string) preg_replace( '/\s*([{};])\s*/', '$1', $css );
		$css = str_replace( ';}', '}', $css );

		return trim( $css );
	}

	/**
	 * A JavaScript string literal.
	 *
	 * @param string $value Value.
	 */
	private static function js_string( string $value ): string {
		return (string) json_encode( $value, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- CLI build, no WordPress.
	}

	/**
	 * Files matching a pattern, sorted by name.
	 *
	 * @param string $pattern Glob pattern.
	 * @return string[]
	 */
	private static function sorted_glob( string $pattern ): array {
		$files = glob( $pattern );
		$files = is_array( $files ) ? $files : array();
		sort( $files );

		return $files;
	}

	/**
	 * A file's contents.
	 *
	 * @param string $path Path.
	 * @throws \RuntimeException When it can't be read.
	 */
	private static function read( string $path ): string {
		$content = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI build, no WordPress.
		if ( false === $content ) {
			throw new \RuntimeException( "bwc-build: can't read {$path}." );
		}

		return $content;
	}
}
