<?php
/**
 * Tests for bin/bwc-build. Plain PHP, no WordPress:
 *
 *     php tests/build/run.php
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Build\Builder;

require_once dirname( __DIR__, 2 ) . '/src/Build/Builder.php';

$tests  = array();
$failed = 0;

/**
 * Registers a test.
 *
 * @param string   $name Name.
 * @param callable $body Throws on failure.
 */
function bwc_build_test( string $name, callable $body ): void {
	$GLOBALS['tests'][ $name ] = $body;
}

/**
 * Fails unless two values are identical.
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $what     What is compared.
 * @throws RuntimeException On mismatch.
 */
function bwc_same( mixed $expected, mixed $actual, string $what ): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException( $what . ":\n  expected " . var_export( $expected, true ) . "\n  got      " . var_export( $actual, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test output.
	}
}

/**
 * Fails unless a string contains another.
 *
 * @param string $needle   Expected part.
 * @param string $haystack Text.
 * @param string $what     What is checked.
 * @throws RuntimeException When missing.
 */
function bwc_contains( string $needle, string $haystack, string $what ): void {
	if ( ! str_contains( $haystack, $needle ) ) {
		throw new RuntimeException( $what . ": \"{$needle}\" not found in:\n" . $haystack ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test output.
	}
}

bwc_build_test(
	'refuses an invalid id',
	static function (): void {
		foreach ( array( 'BC', 'acme_shop', 'acme--shop', '-bc', '' ) as $id ) {
			$threw = false;
			try {
				new Builder( $id );
			} catch ( InvalidArgumentException $error ) {
				$threw = true;
			}
			bwc_same( true, $threw, "id \"{$id}\" refused" );
		}
	}
);

bwc_build_test(
	'scopes each selector to the plugin, inside a root and on the root itself',
	static function (): void {
		$builder = new Builder( 'acme-shop' );
		bwc_same(
			'.bwc-scope--acme-shop .bwc-tile, .bwc-scope--acme-shop:is(.bwc-tile), .bwc-scope--acme-shop .bwc-pill > a, .bwc-scope--acme-shop:is(.bwc-pill > a), .bwc-scope--acme-shop:is(.bwc-pill) > a',
			$builder->scope_selector_list( '.bwc-tile, .bwc-pill > a' ),
			'scoped list'
		);
	}
);

bwc_build_test(
	'a chain whose first part is the root also matches from the root',
	static function (): void {
		bwc_same(
			'.bwc-scope--x .bwc-screen .bwc-screen__title, .bwc-scope--x:is(.bwc-screen .bwc-screen__title), .bwc-scope--x:is(.bwc-screen) .bwc-screen__title, .bwc-scope--x .bwc-nav > a, .bwc-scope--x:is(.bwc-nav > a), .bwc-scope--x:is(.bwc-nav) > a',
			( new Builder( 'x' ) )->scope_selector_list( '.bwc-screen .bwc-screen__title, .bwc-nav > a' ),
			'root-first chains'
		);
		bwc_same(
			'.bwc-scope--x .a:is(.b .c), .bwc-scope--x:is(.a:is(.b .c))',
			( new Builder( 'x' ) )->scope_selector_list( '.a:is(.b .c)' ),
			'spaces inside :is() are not combinators'
		);
	}
);

bwc_build_test(
	'keeps commas inside :is() and attribute selectors together',
	static function (): void {
		bwc_same(
			array( '.a:is(.b, .c)', '[data-x="1,2"]', '.d' ),
			Builder::split_selectors( '.a:is(.b, .c), [data-x="1,2"], .d' ),
			'split'
		);
	}
);

bwc_build_test(
	'pseudo-elements are only scoped as descendants, since :is() cannot hold them',
	static function (): void {
		bwc_same(
			'.bwc-scope--x .bwc-tile::before',
			( new Builder( 'x' ) )->scope_selector_list( '.bwc-tile::before' ),
			'pseudo-element'
		);
	}
);

bwc_build_test(
	'scopes rules inside @media and @container, and leaves nested rules alone',
	static function (): void {
		$css = ( new Builder( 'x' ) )->css(
			array( "@media (min-width: 600px) { .bwc-a { color: red; & .bwc-b { color: blue; } } }\n@container bwc-screen (inline-size < 600px) { .bwc-c { gap: 0; } }" )
		);
		bwc_contains( '@media (min-width: 600px){.bwc-scope--x .bwc-a, .bwc-scope--x:is(.bwc-a){color: red;& .bwc-b{color: blue}}}', $css, 'media' );
		bwc_contains( '@container bwc-screen (inline-size < 600px){.bwc-scope--x .bwc-c, .bwc-scope--x:is(.bwc-c){gap: 0}}', $css, 'container' );
	}
);

bwc_build_test(
	'renames kit keyframes for the plugin and leaves @keyframes unscoped',
	static function (): void {
		$css = ( new Builder( 'acme-shop' ) )->css(
			array( '@keyframes bwc-kf-spin { from { rotate: 0deg; } to { rotate: 360deg; } } .bwc-spinner { animation: bwc-kf-spin 1s linear infinite; }' )
		);
		bwc_contains( '@keyframes acme-shop-bwc-kf-spin{from{rotate: 0deg}to{rotate: 360deg}}', $css, 'keyframes' );
		bwc_contains( 'animation: acme-shop-bwc-kf-spin 1s', $css, 'animation' );
	}
);

bwc_build_test(
	'strips comments but not text inside strings',
	static function (): void {
		bwc_same( '.a { content: "/* keep */"; } ', Builder::strip_comments( '.a { content: "/* keep */"; } /* drop */' ), 'comments' );
	}
);

bwc_build_test(
	'refuses @import',
	static function (): void {
		$threw = false;
		try {
			( new Builder( 'x' ) )->css( array( '@import url("other.css");' ) );
		} catch ( InvalidArgumentException $error ) {
			$threw = true;
		}
		bwc_same( true, $threw, '@import refused' );
	}
);

bwc_build_test(
	'keeps the space before a descendant pseudo-class when minifying',
	static function (): void {
		bwc_same( '.a :hover{color: red}', Builder::minify( ".a :hover {\n\tcolor: red;\n}" ), 'minify' );
	}
);

bwc_build_test(
	'wires the script to the plugin config object and scope class',
	static function (): void {
		$js = ( new Builder( 'acme-shop' ) )->js( array( 'const bwc = {};' ) );
		bwc_contains( "const BWC_CONFIG = window[ \"acmeShopBwc\" ] || {};\nconst BWC_SCOPE = \"bwc-scope--acme-shop\";", $js, 'globals' );
		bwc_contains( '( () => {', $js, 'wrapped' );
	}
);

bwc_build_test(
	'builds the real kit assets into a folder',
	static function (): void {
		$out   = sys_get_temp_dir() . '/bwc-build-test-' . getmypid();
		$paths = ( new Builder( 'bwc-gallery' ) )->build( dirname( __DIR__, 2 ), $out );
		$css   = (string) file_get_contents( $paths['css'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test.
		$js    = (string) file_get_contents( $paths['js'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- test.

		bwc_contains( '.bwc-scope--bwc-gallery .bwc-scope, .bwc-scope--bwc-gallery:is(.bwc-scope){--bwc-accent-source:', $css, 'tokens scoped' );
		bwc_same( 1, preg_match( '/\.bwc-scope--bwc-gallery:is\(\.bwc-screen\)\{[^}]*container: bwc-screen \/ inline-size/', $css ), 'screen root scoped' );
		bwc_contains( '.bwc-scope--bwc-gallery .bwc-screen__bar, .bwc-scope--bwc-gallery:is(.bwc-screen__bar){position: sticky', $css, 'header bar sticky on wide screens, scoped inside the media query' );
		bwc_same( false, str_contains( $css, '/* stylelint' ), 'comments stripped' );
		bwc_contains( 'window[ "bwcGalleryBwc" ]', $js, 'script config' );

		array_map( 'unlink', array( $paths['css'], $paths['js'] ) );
		rmdir( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- test, no WordPress.
	}
);

foreach ( $GLOBALS['tests'] as $name => $body ) {
	try {
		$body();
		echo "  ok    {$name}\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
	} catch ( Throwable $error ) {
		++$failed;
		echo "  FAIL  {$name}\n        " . str_replace( "\n", "\n        ", $error->getMessage() ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
	}
}

$total = count( $GLOBALS['tests'] );
echo $failed ? "\n{$failed} failed, " . ( $total - $failed ) . " passed.\n" : "\nAll {$total} passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI.
exit( $failed ? 1 : 0 );
