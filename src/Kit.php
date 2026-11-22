<?php
/**
 * The kit object a plugin boots once.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit;

use BlissWebConcept\AdminKit\Service\Actions;
use BlissWebConcept\AdminKit\Service\Assets;
use BlissWebConcept\AdminKit\Service\Cache;
use BlissWebConcept\AdminKit\Service\Csv;
use BlissWebConcept\AdminKit\Service\Jobs;
use BlissWebConcept\AdminKit\Service\Secrets;
use BlissWebConcept\AdminKit\Service\Notices;
use BlissWebConcept\AdminKit\Service\Strings;

defined( 'ABSPATH' ) || exit;

/**
 * One kit per plugin. Strauss gives every plugin its own copy of this class,
 * so the instance kept here belongs to that plugin alone. A second boot with
 * another id means two plugins share one unprefixed copy, which the kit
 * refuses.
 */
final class Kit {

	/**
	 * Kit version, for the gallery and debug output.
	 */
	public const VERSION = '0.2.0';

	/**
	 * Text domain of the kit's own strings.
	 */
	public const TEXT_DOMAIN = 'bwc-admin-kit';

	/**
	 * The booted kit.
	 */
	private static ?self $instance = null;

	/**
	 * Strings service.
	 */
	private ?Strings $strings = null;

	/**
	 * Assets service.
	 */
	private ?Assets $assets = null;

	/**
	 * Field types.
	 */
	private ?Field\Registry $fields = null;

	/**
	 * Notices service.
	 */
	private ?Notices $notices = null;

	/**
	 * Actions service.
	 */
	private ?Actions $actions = null;

	/**
	 * CSV service.
	 */
	private ?Csv $csv = null;

	/**
	 * Cache service.
	 */
	private ?Cache $cache = null;

	/**
	 * Jobs service.
	 */
	private ?Jobs $jobs = null;

	/**
	 * Secrets service.
	 */
	private ?Secrets $secrets = null;

	/**
	 * Constructor.
	 *
	 * @param array{id: string, file: string, version: string, text_domain: string, capability: string, accent: ?string, strings: array<string, string|list<string>>, build_dir: string} $config Clean config.
	 */
	private function __construct( private array $config ) {}

	/**
	 * Boots the plugin's kit, or returns the one already booted with this id.
	 *
	 * @param array<string, mixed> $config id, file, version, text_domain, capability, accent, strings, build_dir.
	 * @throws \InvalidArgumentException When the config is incomplete, or another plugin's id was booted first.
	 */
	public static function boot( array $config ): self {
		$clean = self::clean_config( $config );

		if ( null !== self::$instance ) {
			if ( self::$instance->id() === $clean['id'] ) {
				return self::$instance;
			}

			throw new \InvalidArgumentException(
				esc_html(
					sprintf(
						'Admin Kit is already booted for "%1$s", so "%2$s" can\'t boot it too. Each plugin needs its own copy of the kit: run Strauss in "%2$s".',
						self::$instance->id(),
						$clean['id']
					)
				)
			);
		}

		self::$instance = new self( $clean );
		self::$instance->assets()->register();
		self::$instance->notices()->register();
		self::$instance->actions()->register_footer();
		self::$instance->cache()->register_refresh();
		Field\PickerSearch::register( self::$instance );
		self::load_translations();

		return self::$instance;
	}

	/**
	 * The kit's own translations (text domain "bwc-admin-kit"): WordPress's
	 * languages folder first (languages/plugins/bwc-admin-kit-{locale}.mo),
	 * then the kit's own languages folder. Loaded once, whichever plugin's
	 * copy of the kit gets there first.
	 */
	private static function load_translations(): void {
		$load = static function (): void {
			if ( is_textdomain_loaded( 'bwc-admin-kit' ) ) {
				return;
			}
			$locale = determine_locale();
			$file   = 'bwc-admin-kit-' . $locale . '.mo';
			foreach ( array( WP_LANG_DIR . '/plugins/' . $file, dirname( __DIR__ ) . '/languages/' . $file ) as $path ) {
				if ( is_readable( $path ) ) {
					load_textdomain( 'bwc-admin-kit', $path, $locale );
					return;
				}
			}
		};

		if ( did_action( 'init' ) ) {
			$load();
		} else {
			add_action( 'init', $load, 0 );
		}
	}

	/**
	 * The booted kit.
	 *
	 * @throws \LogicException When nothing booted it yet.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			throw new \LogicException( 'Admin Kit is used before Kit::boot() was called.' );
		}

		return self::$instance;
	}

	/**
	 * Whether a kit is booted.
	 */
	public static function is_booted(): bool {
		return null !== self::$instance;
	}

	/**
	 * Forgets the booted kit. Tests only.
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Checks and completes the config.
	 *
	 * @param array<string, mixed> $config Raw config.
	 * @return array{id: string, file: string, version: string, text_domain: string, capability: string, accent: ?string, strings: array<string, string|list<string>>, build_dir: string}
	 * @throws \InvalidArgumentException When a required key is missing or malformed.
	 */
	private static function clean_config( array $config ): array {
		$id = isset( $config['id'] ) ? (string) $config['id'] : '';
		if ( ! preg_match( '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $id ) ) {
			throw new \InvalidArgumentException( esc_html( "Admin Kit: the id \"{$id}\" must be lowercase letters and digits, words joined by single hyphens." ) );
		}

		foreach ( array( 'file', 'version', 'text_domain' ) as $key ) {
			if ( empty( $config[ $key ] ) || ! is_string( $config[ $key ] ) ) {
				throw new \InvalidArgumentException( esc_html( "Admin Kit: \"{$key}\" is required." ) );
			}
		}

		$strings = isset( $config['strings'] ) && is_array( $config['strings'] ) ? $config['strings'] : array();

		return array(
			'id'          => $id,
			'file'        => (string) $config['file'],
			'version'     => (string) $config['version'],
			'text_domain' => (string) $config['text_domain'],
			'capability'  => isset( $config['capability'] ) ? (string) $config['capability'] : 'manage_options',
			'accent'      => isset( $config['accent'] ) && '' !== $config['accent'] ? (string) $config['accent'] : null,
			// A plural override is a [ singular, plural ] pair; anything else is text.
			'strings'     => array_map( static fn( $text ): string|array => is_array( $text ) ? array_map( 'strval', array_values( $text ) ) : (string) $text, $strings ),
			'build_dir'   => isset( $config['build_dir'] ) ? trim( (string) $config['build_dir'], '/' ) : 'assets/bwc',
		);
	}

	/**
	 * Plugin id as given, hyphenated: asset handles, scope class, HTML ids.
	 */
	public function id(): string {
		return $this->config['id'];
	}

	/**
	 * Plugin id with underscores: hooks, options, transients, actions, nonces.
	 */
	public function hook(): string {
		return str_replace( '-', '_', $this->config['id'] );
	}

	/**
	 * A WordPress name for this plugin's kit: "{hook}_bwc_{suffix}".
	 *
	 * @param string $suffix What it names, such as "capability".
	 */
	public function name( string $suffix ): string {
		return $this->hook() . '_bwc_' . $suffix;
	}

	/**
	 * Plugin id in camel case, for the script config object.
	 */
	public function id_camel(): string {
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $this->config['id'] ) ) ) );
	}

	/**
	 * Name of the script config object on window: "{idCamel}Bwc".
	 */
	public function script_global(): string {
		return $this->id_camel() . 'Bwc';
	}

	/**
	 * Class that scopes the kit's CSS and scripts to this plugin.
	 */
	public function scope_class(): string {
		return 'bwc-scope--' . $this->config['id'];
	}

	/**
	 * Every class a kit root element carries: the shared scope and the plugin's.
	 */
	public function root_classes(): string {
		return 'bwc-scope ' . $this->scope_class();
	}

	/**
	 * The plugin's accent colour, or null to follow the user's admin colour scheme.
	 */
	public function accent(): ?string {
		return $this->config['accent'];
	}

	/**
	 * Main plugin file.
	 */
	public function file(): string {
		return $this->config['file'];
	}

	/**
	 * Plugin version, for cache-busting the built assets.
	 */
	public function version(): string {
		return $this->config['version'];
	}

	/**
	 * The plugin's text domain, for strings it passes in.
	 */
	public function text_domain(): string {
		return $this->config['text_domain'];
	}

	/**
	 * Folder of the built kit assets, relative to the plugin.
	 */
	public function build_dir(): string {
		return $this->config['build_dir'];
	}

	/**
	 * Strings the plugin overrides.
	 *
	 * @return array<string, string|list<string>>
	 */
	public function string_overrides(): array {
		return $this->config['strings'];
	}

	/**
	 * Capability for a screen or action. Filter "{hook}_bwc_capability",
	 * which also sees a capability the screen or action set for itself.
	 *
	 * @param string      $context Screen id or action name, or '' for the default.
	 * @param string|null $own     The screen's or action's own capability, if it set one.
	 */
	public function capability( string $context = '', ?string $own = null ): string {
		/**
		 * Filters the capability a kit screen or action needs.
		 *
		 * @param string $capability Capability, "manage_options" unless the plugin chose another.
		 * @param string $context    Screen id or action name, or ''.
		 */
		return (string) apply_filters( $this->name( 'capability' ), $own ?? $this->config['capability'], $context );
	}

	/**
	 * Whether the current user may use a screen or action.
	 *
	 * @param string $context Screen id or action name, or ''.
	 */
	public function can( string $context = '' ): bool {
		return current_user_can( $this->capability( $context ) );
	}

	/**
	 * URL of a file in the plugin.
	 *
	 * @param string $path Path relative to the plugin folder.
	 */
	public function url( string $path ): string {
		return plugins_url( ltrim( $path, '/' ), $this->config['file'] );
	}

	/**
	 * Absolute path of a file in the plugin.
	 *
	 * @param string $path Path relative to the plugin folder.
	 */
	public function path( string $path ): string {
		return trailingslashit( dirname( $this->config['file'] ) ) . ltrim( $path, '/' );
	}

	/**
	 * The Strings service.
	 */
	public function strings(): Strings {
		$this->strings ??= new Strings( $this );

		return $this->strings;
	}

	/**
	 * The Assets service.
	 */
	public function assets(): Assets {
		$this->assets ??= new Assets( $this );

		return $this->assets;
	}

	/**
	 * The Notices service: messages that survive the redirect after an action.
	 */
	public function notices(): Notices {
		$this->notices ??= new Notices( $this );

		return $this->notices;
	}

	/**
	 * The Actions service: buttons that run something and come back.
	 */
	public function actions(): Actions {
		$this->actions ??= new Actions( $this );

		return $this->actions;
	}

	/**
	 * The CSV service: spreadsheet-safe downloads.
	 */
	public function csv(): Csv {
		$this->csv ??= new Csv( $this );

		return $this->csv;
	}

	/**
	 * The Cache service: slow results kept for a few minutes, with a refresh.
	 */
	public function cache(): Cache {
		$this->cache ??= new Cache( $this );

		return $this->cache;
	}

	/**
	 * The Jobs service: work over growing lists in short steps.
	 */
	public function jobs(): Jobs {
		$this->jobs ??= new Jobs( $this );

		return $this->jobs;
	}

	/**
	 * The Secrets service: API keys and tokens, encrypted, never shown back.
	 */
	public function secrets(): Secrets {
		$this->secrets ??= new Secrets( $this );

		return $this->secrets;
	}

	/**
	 * Field types: the built-in ones and any the plugin registers.
	 */
	public function fields(): Field\Registry {
		$this->fields ??= new Field\Registry();

		return $this->fields;
	}

	/**
	 * Settings stored in one option, a bucket per set.
	 * Registers the setting with its REST schema; returns the storage, whose
	 * form( $set ) prints a set's fields and get( $set, $id ) reads a value.
	 *
	 * @param array<string, mixed> $config option, sets (set id => fields), group, autoload, migrations, capability (filtered with the context "settings").
	 */
	public function settings( array $config ): Storage\OptionStorage {
		$storage = new Storage\OptionStorage(
			$this,
			(string) $config['option'],
			(array) ( $config['sets'] ?? array() ),
			(string) ( $config['group'] ?? '' ),
			(bool) ( $config['autoload'] ?? true ),
			(array) ( $config['migrations'] ?? array() ),
			isset( $config['capability'] ) ? (string) $config['capability'] : null
		);
		$storage->register();

		return $storage;
	}

	/**
	 * A meta box of fields on post edit screens.
	 *
	 * @param array<string, mixed> $config id, title, post_types, fields, context, priority, capability, prefix, single_key, show_in_rest.
	 */
	public function meta_box( array $config ): void {
		Storage\PostMeta::box( $this, $config );
	}

	/**
	 * Fields on a taxonomy's add and edit forms.
	 *
	 * @param array<string, mixed> $config id, taxonomy, fields, capability, prefix, show_in_rest.
	 */
	public function term_fields( array $config ): void {
		Storage\TermMeta::fields( $this, $config );
	}

	/**
	 * A section of fields on user profile screens.
	 *
	 * @param array<string, mixed> $config id, title, fields, who, capability, prefix, show_in_rest.
	 */
	public function user_fields( array $config ): void {
		Storage\UserMeta::fields( $this, $config );
	}

	/**
	 * A settings screen: groups of sections of fields, with navigation,
	 * search, module switches and saving.
	 * Describe it at init or later; give it to shell() to show it.
	 *
	 * @param array<string, mixed> $config id, label, option, autoload, migrations, navigation, search, intro, groups, danger, capability.
	 */
	public function settings_screen( array $config ): Screen\SettingsScreen {
		$screen = new Screen\SettingsScreen( $this, $config );
		$screen->register();

		return $screen;
	}

	/**
	 * A list screen: records with search, filters, sorting, pages, bulk
	 * actions, CSV export and a side-panel form.
	 * Describe it at init or later; give it to shell() to show it.
	 *
	 * @param array<string, mixed> $config id, label, title, intro, fields, query, view, per_page, layout, search, actions, bulk, export, drawer, before, empty, lists, capability.
	 */
	public function list_screen( array $config ): Screen\ListScreen {
		$screen = new Screen\ListScreen( $this, $config );
		$screen->register();

		return $screen;
	}

	/**
	 * A log screen: runs or days on one side, the chosen run's summary and
	 * its entries, grouped and filterable, on the other.
	 *
	 * @param array<string, mixed> $config id, label, title, intro, days, totals, runs, run, kinds, filters, empty, capability.
	 */
	public function log_screen( array $config ): Screen\LogScreen {
		return new Screen\LogScreen( $this, $config );
	}

	/**
	 * A report screen: tabs of tiles, charts, tables and notes, described as
	 * blocks.
	 *
	 * @param array<string, mixed> $config id, label, title, intro, tabs, persist, data, header_actions, capability.
	 */
	public function report_screen( array $config ): Screen\ReportScreen {
		$screen = new Screen\ReportScreen( $this, $config );
		$screen->register();

		return $screen;
	}

	/**
	 * One record in full, opened in place from a list or a report tab by its
	 * parameter.
	 *
	 * @param array<string, mixed> $config param, load, title, back, missing, blocks.
	 */
	public function detail_screen( array $config ): Screen\DetailScreen {
		return new Screen\DetailScreen( $this, $config );
	}

	/**
	 * A screen whose body the plugin draws itself.
	 *
	 * @param array<string, mixed> $config id, label, render (callable(): string, escaped HTML), capability, badge, hidden.
	 */
	public function screen( array $config ): Screen\CustomScreen {
		return new Screen\CustomScreen( $this, $config );
	}

	/**
	 * Admin pages for screens: the menu, the header, the tabs. Call at plugins_loaded or init; it registers the
	 * menu on admin_menu.
	 *
	 * @param array<string, mixed> $config menu, header, screens, tabs.
	 */
	public function shell( array $config ): Shell\Shell {
		$shell = new Shell\Shell( $this, $config );
		$shell->register();

		return $shell;
	}
}
