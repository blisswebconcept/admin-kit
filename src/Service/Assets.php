<?php
/**
 * Loads the plugin's built kit stylesheet and script where kit parts show.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Handles are "{id}-bwc", never shared between plugins. The files are the
 * ones bin/bwc-build wrote into the plugin's build folder.
 */
final class Assets {

	/**
	 * Strings every script needs.
	 */
	private const SCRIPT_STRINGS = array( 'copy', 'copied', 'copy_failed', 'saved', 'unsaved', 'retrying', 'job_done', 'expand_all', 'collapse_all', 'filter_shown', 'on_count', 'picker_search', 'picker_found', 'picker_more', 'picker_none', 'picker_failed', 'picker_remove', 'picker_chosen', 'ordered_moved', 'ordered_handle', 'ordered_add', 'ordered_hint', 'repeater_row', 'repeater_moved', 'added', 'removed', 'move_up', 'move_down', 'remove' );

	/**
	 * Background of a kit screen's page: a little lighter and cooler than WordPress's #f0f0f1.
	 */
	private const PAGE_COLOUR = 'oklch(98.4% 0.003 248)';

	/**
	 * Admin screen hook suffixes that load the assets.
	 *
	 * @var array<string, true>
	 */
	private array $screens = array();

	/**
	 * Extra checks that load the assets, for meta boxes, term and profile fields.
	 *
	 * @var array<int, callable(string): bool>
	 */
	private array $tests = array();

	/**
	 * Extra values for the script config object.
	 *
	 * @var array<string, mixed>
	 */
	private array $script_config = array();

	/**
	 * Constructor.
	 *
	 * @param Kit $kit Kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * Hooks loading. Called once by Kit::boot().
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
		add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );
	}

	/**
	 * Loads the assets on these admin screens.
	 *
	 * @param string ...$hook_suffixes Hook suffixes, as add_menu_page() and add_submenu_page() return them.
	 */
	public function load_on( string ...$hook_suffixes ): void {
		foreach ( $hook_suffixes as $suffix ) {
			if ( '' !== $suffix ) {
				$this->screens[ $suffix ] = true;
			}
		}
	}

	/**
	 * Loads the assets wherever a test says so (meta boxes, term and profile fields).
	 *
	 * @param callable(string): bool $test Gets the hook suffix; true loads the assets.
	 */
	public function load_when( callable $test ): void {
		$this->tests[] = $test;
	}

	/**
	 * Adds a value to the script config object.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value JSON-serialisable value, or a Closure returning one, called when the page is printed.
	 */
	public function add_script_config( string $key, mixed $value ): void {
		$this->script_config[ $key ] = $value;
	}

	/**
	 * Whether this is one of the plugin's own kit screens (not a meta box or
	 * field set on someone else's screen).
	 *
	 * @param string $hook_suffix Admin screen.
	 */
	public function is_kit_screen( string $hook_suffix ): bool {
		return isset( $this->screens[ $hook_suffix ] );
	}

	/**
	 * Whether this screen shows kit parts.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function wanted( string $hook_suffix ): bool {
		if ( isset( $this->screens[ $hook_suffix ] ) ) {
			return true;
		}

		foreach ( $this->tests as $test ) {
			if ( $test( $hook_suffix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueues on screens that want it.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function maybe_enqueue( string $hook_suffix ): void {
		if ( ! $this->wanted( $hook_suffix ) ) {
			return;
		}

		$this->enqueue();

		// A kit screen (not a meta box or field on someone else's page) also gets the page colour.
		if ( isset( $this->screens[ $hook_suffix ] ) ) {
			add_filter( 'admin_body_class', array( $this, 'body_class' ) );
		}
	}

	/**
	 * Marks the page as a kit screen, for the page colour.
	 *
	 * @param string $classes Admin body classes.
	 */
	public function body_class( string $classes ): string {
		return $classes . ' ' . $this->page_class();
	}

	/**
	 * Body class of this plugin's kit screens.
	 */
	public function page_class(): string {
		return 'bwc-page--' . $this->kit->id();
	}

	/**
	 * Handle of the stylesheet and of the script.
	 */
	public function handle(): string {
		return $this->kit->id() . '-bwc';
	}

	/**
	 * Path of a built file, relative to the plugin.
	 *
	 * @param string $extension "css" or "js".
	 */
	public function built_file( string $extension ): string {
		return $this->kit->build_dir() . '/' . $this->handle() . '.' . $extension;
	}

	/**
	 * Whether both built files exist.
	 */
	public function is_built(): bool {
		return is_readable( $this->kit->path( $this->built_file( 'css' ) ) ) && is_readable( $this->kit->path( $this->built_file( 'js' ) ) );
	}

	/**
	 * WordPress stylesheets the kit's tokens read: the design tokens (--wpds-*)
	 * and the admin colour schemes (--wp-admin-theme-color). Only those this
	 * WordPress has; on older versions the kit's fallback values apply.
	 *
	 * @return list<string> Style handles.
	 */
	private static function token_styles(): array {
		return array_values(
			array_filter(
				array( 'wp-theme', 'wp-base-styles' ),
				static fn( string $handle ): bool => wp_style_is( $handle, 'registered' )
			)
		);
	}

	/**
	 * Enqueues the stylesheet and script now.
	 */
	public function enqueue(): void {
		$handle = $this->handle();

		if ( ! wp_style_is( $handle, 'enqueued' ) ) {
			wp_enqueue_style(
				$handle,
				$this->kit->url( $this->built_file( 'css' ) ),
				self::token_styles(),
				$this->kit->version()
			);

			// The page colour. The body is outside the kit's scope, so this is printed here, not built.
			wp_add_inline_style( $handle, 'html:has(> body.' . $this->page_class() . '),body.' . $this->page_class() . '{background:' . self::PAGE_COLOUR . '}' );

			$accent = self::clean_colour( (string) $this->kit->accent() );
			if ( '' !== $accent ) {
				// Two classes, like the built token rule it overrides, and printed after it.
				wp_add_inline_style( $handle, '.bwc-scope.' . $this->kit->scope_class() . '{--bwc-accent-source:' . $accent . '}' );
			}
		}

		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			wp_enqueue_script( $handle, $this->kit->url( $this->built_file( 'js' ) ), array(), $this->kit->version(), true );
			wp_add_inline_script(
				$handle,
				'window.' . $this->kit->script_global() . ' = ' . wp_json_encode( $this->script_config() ) . ';',
				'before'
			);
		}
	}

	/**
	 * The script config object.
	 *
	 * @return array<string, mixed>
	 */
	public function script_config(): array {
		return array(
			'id'      => $this->kit->id(),
			'scope'   => $this->kit->scope_class(),
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'strings' => $this->kit->strings()->for_script( self::SCRIPT_STRINGS ),
		) + array_map(
			// A function is read now, when the page is printed: a nonce needs the logged-in user.
			static fn( $value ) => $value instanceof \Closure ? $value() : $value,
			$this->script_config
		);
	}

	/**
	 * Tells developers the build step hasn't run, while debugging.
	 */
	public function missing_build_notice(): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! current_user_can( 'manage_options' ) || $this->is_built() ) {
			return;
		}

		// The same hook suffix the enqueue check gets (a screen id differs for post.php and the like).
		$suffix = isset( $GLOBALS['hook_suffix'] ) && is_string( $GLOBALS['hook_suffix'] ) ? $GLOBALS['hook_suffix'] : '';
		if ( '' === $suffix || ! $this->wanted( $suffix ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					'Admin Kit: %1$s is missing. Run bin/bwc-build --id=%2$s --out=%3$s in the plugin.',
					$this->built_file( 'css' ),
					$this->kit->id(),
					$this->kit->build_dir()
				)
			)
		);
	}

	/**
	 * A CSS colour that is safe to print, or ''.
	 *
	 * @param string $colour oklch(), rgb(), hsl() or #hex.
	 */
	public static function clean_colour( string $colour ): string {
		$colour = trim( $colour );

		if ( preg_match( '/^#[0-9a-f]{3,8}$/i', $colour ) || preg_match( '/^(?:oklch|oklab|rgb|rgba|hsl|hsla)\(\s*[0-9.%\s,\/+-]+\)$/i', $colour ) ) {
			return $colour;
		}

		return '';
	}
}
