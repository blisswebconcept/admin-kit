<?php
/**
 * WordPress tests for the kit, run inside a WordPress site through WP-CLI:
 *
 *     wp eval-file /path/to/admin-kit/tests/wp/run.php
 *
 * Loads the kit from its own vendor/autoload.php (unprefixed), so it can run
 * next to a plugin that bundles a prefixed copy; that is the isolation the
 * kit promises. Leaves nothing behind.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Service\Assets;
use BlissWebConcept\AdminKit\Shell\Screen;

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$GLOBALS['bwc_tests'] = array();

/**
 * Registers a test.
 *
 * @param string   $name Name.
 * @param callable $body Throws on failure.
 */
function bwc_test( string $name, callable $body ): void {
	$GLOBALS['bwc_tests'][ $name ] = $body;
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
 * Fails unless the callable throws.
 *
 * @param callable $body Code that should throw.
 * @param string   $what What should be refused.
 * @throws RuntimeException When it doesn't throw.
 */
function bwc_throws( callable $body, string $what ): void {
	try {
		$body();
	} catch ( Throwable $error ) {
		return;
	}
	throw new RuntimeException( $what . ': expected an exception, none thrown.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test output.
}

/**
 * Boots a test kit.
 *
 * @param array<string, mixed> $config Config to merge over the defaults.
 */
function bwc_test_kit( array $config = array() ): Kit {
	Kit::reset();

	return Kit::boot(
		$config + array(
			'id'          => 'bwc-test',
			'file'        => WP_PLUGIN_DIR . '/bwc-test/bwc-test.php',
			'version'     => '1.2.3',
			'text_domain' => 'bwc-test',
		)
	);
}

bwc_test(
	'boot returns the same kit for the same id and refuses a second plugin',
	static function (): void {
		$kit = bwc_test_kit();
		bwc_same(
			$kit,
			Kit::boot(
				array(
					'id'          => 'bwc-test',
					'file'        => 'x',
					'version'     => '1',
					'text_domain' => 'x',
				)
			),
			'same kit'
		);
		bwc_same( $kit, Kit::instance(), 'instance' );
		bwc_throws(
			static fn() => Kit::boot(
				array(
					'id'          => 'other-plugin',
					'file'        => 'x',
					'version'     => '1',
					'text_domain' => 'x',
				)
			),
			'second plugin'
		);
	}
);

bwc_test(
	'boot refuses a bad id and missing keys',
	static function (): void {
		Kit::reset();
		bwc_throws(
			static fn() => Kit::boot(
				array(
					'id'          => 'Acme_Shop',
					'file'        => 'x',
					'version'     => '1',
					'text_domain' => 'x',
				)
			),
			'bad id'
		);
		bwc_throws(
			static fn() => Kit::boot(
				array(
					'id'          => 'acme-shop',
					'version'     => '1',
					'text_domain' => 'x',
				)
			),
			'missing file'
		);
		bwc_same( false, Kit::is_booted(), 'nothing booted' );
	}
);

bwc_test(
	'names: hyphenated id for handles and classes, underscored for hooks',
	static function (): void {
		$kit = bwc_test_kit( array( 'id' => 'acme-shop' ) );
		bwc_same( 'acme_shop', $kit->hook(), 'hook' );
		bwc_same( 'acme_shop_bwc_capability', $kit->name( 'capability' ), 'name' );
		bwc_same( 'acmeShopBwc', $kit->script_global(), 'script global' );
		bwc_same( 'bwc-scope--acme-shop', $kit->scope_class(), 'scope class' );
		bwc_same( 'bwc-scope bwc-scope--acme-shop', $kit->root_classes(), 'root classes' );
		bwc_same( 'acme-shop-bwc', $kit->assets()->handle(), 'asset handle' );
		bwc_same( 'assets/bwc/acme-shop-bwc.css', $kit->assets()->built_file( 'css' ), 'built file' );
	}
);

bwc_test(
	'the stylesheet loads after WordPress tokens, and a plugin accent beats the profile colour',
	static function (): void {
		$kit    = bwc_test_kit( array( 'accent' => 'oklch(55% 0.15 30)' ) );
		$styles = wp_styles();
		$kit->assets()->enqueue();
		$style = $styles->registered[ $kit->assets()->handle() ];
		bwc_same( array( 'wp-theme', 'wp-base-styles' ), $style->deps, 'token stylesheets' );
		// Two classes of weight, as the built token rule has, so the accent wins.
		bwc_same(
			array(
				'html:has(> body.bwc-page--bwc-test),body.bwc-page--bwc-test{background:oklch(98.4% 0.003 248)}',
				'.bwc-scope.bwc-scope--bwc-test{--bwc-accent-source:oklch(55% 0.15 30)}',
			),
			$styles->get_data( $kit->assets()->handle(), 'after' ),
			'page colour, then the accent override'
		);
		wp_dequeue_style( $kit->assets()->handle() );
		wp_deregister_style( $kit->assets()->handle() );
		wp_dequeue_script( $kit->assets()->handle() );
		wp_deregister_script( $kit->assets()->handle() );
	}
);

bwc_test(
	'the capability can be filtered per plugin and per context',
	static function (): void {
		$kit    = bwc_test_kit();
		$filter = static fn( string $capability, string $context ): string => 'links' === $context ? 'edit_posts' : $capability;
		add_filter( 'bwc_test_bwc_capability', $filter, 10, 2 );
		bwc_same( 'manage_options', $kit->capability(), 'default' );
		bwc_same( 'edit_posts', $kit->capability( 'links' ), 'filtered' );
		bwc_same( 'edit_posts', $kit->capability( 'links', 'edit_pages' ), 'a screen\'s own capability goes through the filter too' );
		bwc_same( 'edit_pages', $kit->capability( 'offers', 'edit_pages' ), 'its own capability when the filter leaves it' );
		remove_filter( 'bwc_test_bwc_capability', $filter );
	}
);

bwc_test(
	'strings: defaults, plugin overrides, the filter, plurals, unknown keys',
	static function (): void {
		$kit = bwc_test_kit(
			array(
				'strings' => array(
					'saved'             => 'Everything is saved',
					'turn_on_to_change' => array( 'Switch on for its %d setting.', 'Switch on for its %d settings.' ),
				),
			)
		);
		bwc_same( 'Save changes', $kit->strings()->get( 'save' ), 'default' );
		bwc_same( 'Everything is saved', $kit->strings()->get( 'saved' ), 'override' );
		bwc_same( 'Showing 1–20 of 245', $kit->strings()->get( 'showing_range', 1, 20, 245 ), 'placeholders' );
		bwc_same( 'Switch on for its 1 setting.', $kit->strings()->count( 'turn_on_to_change', 1 ), 'plural override at boot, singular' );
		bwc_same( 'Switch on for its 4 settings.', $kit->strings()->count( 'turn_on_to_change', 4 ), 'plural override at boot, plural' );
		bwc_same( 'no_such_key', @$kit->strings()->get( 'no_such_key' ), 'unknown key' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the debug notice is expected here.

		$kit    = bwc_test_kit();
		$filter = static fn( array $strings ): array => array( 'stop' => 'Halt' ) + $strings;
		add_filter( 'bwc_test_bwc_strings', $filter );
		bwc_same( 'Halt', $kit->strings()->get( 'stop' ), 'filtered' );
		remove_filter( 'bwc_test_bwc_strings', $filter );
	}
);

bwc_test(
	'assets load only on screens and places that ask for them',
	static function (): void {
		$kit = bwc_test_kit();
		$kit->assets()->load_on( 'toplevel_page_bwc-test' );
		$kit->assets()->load_when( static fn( string $suffix ): bool => 'post.php' === $suffix );
		bwc_same( true, $kit->assets()->wanted( 'toplevel_page_bwc-test' ), 'own screen' );
		bwc_same( true, $kit->assets()->wanted( 'post.php' ), 'meta box screen' );
		bwc_same( false, $kit->assets()->wanted( 'edit.php' ), 'other screen' );
	}
);

bwc_test(
	'the script config names the plugin, its scope and the strings scripts need',
	static function (): void {
		$kit = bwc_test_kit();
		$kit->assets()->add_script_config( 'nonce', 'abc' );
		$config = $kit->assets()->script_config();
		bwc_same( 'bwc-test', $config['id'], 'id' );
		bwc_same( 'bwc-scope--bwc-test', $config['scope'], 'scope' );
		bwc_same( 'Copied', $config['strings']['copied'], 'strings' );
		bwc_same( 'abc', $config['nonce'], 'extra value' );
	}
);

bwc_test(
	'only real colours reach the inline accent style',
	static function (): void {
		bwc_same( 'oklch(55% 0.15 30)', Assets::clean_colour( 'oklch(55% 0.15 30)' ), 'oklch' );
		bwc_same( '#2271b1', Assets::clean_colour( '#2271b1' ), 'hex' );
		bwc_same( '', Assets::clean_colour( 'red;} body{display:none' ), 'injection' );
		bwc_same( '', Assets::clean_colour( 'url(x)' ), 'url' );
	}
);

bwc_test(
	'the screen prints its scope, header bar, notice spot and live region',
	static function (): void {
		bwc_test_kit();
		bwc_same(
			'<div class="wrap bwc-scope bwc-scope--bwc-test bwc-screen"><div class="bwc-screen__bar" data-bwc-screen-bar><div class="bwc-screen__brand"><span class="bwc-screen__icon" aria-hidden="true"></span><div class="bwc-screen__titles"><h1 class="bwc-screen__title">Gallery &amp; tests</h1><p class="bwc-screen__subtitle">Every part, every state.</p></div></div>'
			. '<div class="bwc-screen__nav"><nav class="bwc-nav bwc-nav--tabs" aria-label="Sections" data-bwc-scroll-current><a class="bwc-nav__item is-current" href="https://example.test/a" aria-current="page"><span class="bwc-nav__label">Overview</span></a></nav></div>'
			. '<div class="bwc-screen__actions"><a href="#">Export</a></div><span class="bwc-screen__version">v0.1.0</span></div>'
			. '<hr class="wp-header-end"><p class="bwc-visually-hidden" aria-live="polite" data-bwc-live></p><div class="bwc-screen__content"><p>Body</p></div></div>',
			Screen::markup(
				array(
					'title'        => 'Gallery & tests',
					'subtitle'     => 'Every part, every state.',
					'version'      => '0.1.0',
					'nav'          => array(
						'label'   => 'Sections',
						'current' => 'overview',
						'items'   => array(
							'overview' => array(
								'label' => 'Overview',
								'url'   => 'https://example.test/a',
							),
						),
					),
					'actions_html' => '<a href="#">Export</a>',
					'content_html' => '<p>Body</p>',
				)
			),
			'markup'
		);
		bwc_throws( static fn() => Screen::markup( array( 'title' => ' ' ) ), 'empty title' );
	}
);

bwc_test(
	'kit screens get the page colour body class; meta boxes elsewhere do not',
	static function (): void {
		$kit = bwc_test_kit();
		$kit->assets()->load_on( 'toplevel_page_bwc-test' );
		$kit->assets()->load_when( static fn( string $hook ): bool => 'post.php' === $hook );

		$kit->assets()->maybe_enqueue( 'post.php' );
		bwc_same( false, str_contains( apply_filters( 'admin_body_class', '' ), 'bwc-page--bwc-test' ), 'meta box page' );

		$kit->assets()->maybe_enqueue( 'toplevel_page_bwc-test' );
		bwc_same( true, str_contains( apply_filters( 'admin_body_class', '' ), ' bwc-page--bwc-test' ), 'kit screen' );

		remove_filter( 'admin_body_class', array( $kit->assets(), 'body_class' ) );
		foreach ( array( 'wp_dequeue_style', 'wp_deregister_style', 'wp_dequeue_script', 'wp_deregister_script' ) as $undo ) {
			$undo( $kit->assets()->handle() );
		}
	}
);

require __DIR__ . '/components.php';
require __DIR__ . '/controls.php';
require __DIR__ . '/fields.php';
require __DIR__ . '/screens.php';
require __DIR__ . '/lists.php';
require __DIR__ . '/class-bwc-test-job.php';
require __DIR__ . '/jobs.php';
require __DIR__ . '/reports.php';
require __DIR__ . '/fields-more.php';
require __DIR__ . '/security.php';

$bwc_failed = 0;
foreach ( $GLOBALS['bwc_tests'] as $name => $body ) {
	try {
		$body();
		WP_CLI::log( "  ok    {$name}" );
	} catch ( Throwable $error ) {
		++$bwc_failed;
		WP_CLI::log( "  FAIL  {$name}\n        " . str_replace( "\n", "\n        ", $error->getMessage() ) );
	}
}
Kit::reset();

$bwc_total = count( $GLOBALS['bwc_tests'] );
if ( $bwc_failed ) {
	WP_CLI::error( "{$bwc_failed} failed, " . ( $bwc_total - $bwc_failed ) . ' passed.' );
}
WP_CLI::success( "All {$bwc_total} passed." );
