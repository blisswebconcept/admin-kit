<?php
/**
 * Settings stored in one option, a bucket per set.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Option storage. Defaults apply on read and are never
 * written; saving merges only the submitted buckets and keys; WordPress's
 * second sanitize call in a request gets the already-clean value back.
 */
final class OptionStorage implements Storage {

	/**
	 * Clean values the kit is writing itself (a screen's save, write(),
	 * migrate()), by option name, so the sanitize callback lets them through.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $sanitized = array();

	/**
	 * The last result of the sanitize callback, by option name. WordPress
	 * sanitizes a new option twice (update_option() falls through to
	 * add_option() with the clean value), and the second pass gets it back.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $last = array();

	/**
	 * The stored option, read once.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $memo = null;

	/**
	 * Messages from the last save, read once.
	 *
	 * @var array{errors: array<string, string>, notes: array<string, string>}|null
	 */
	private ?array $messages = null;

	/**
	 * Constructor.
	 *
	 * @param Kit                                       $kit        The kit.
	 * @param string                                    $option     Option name.
	 * @param array<string, list<array<string, mixed>>> $sets       Set id => field descriptions.
	 * @param string                                    $group      Settings group (settings_fields()).
	 * @param bool                                      $autoload   Autoload the option.
	 * @param array<int, callable>                      $migrations Schema number => callable( array $stored ): array.
	 * @param string|null                               $capability The screen's own capability, if it set one.
	 * @param string                                    $context    Capability context: the screen id, or "settings".
	 */
	public function __construct(
		private Kit $kit,
		private string $option,
		private array $sets,
		private string $group = '',
		private bool $autoload = true,
		private array $migrations = array(),
		private ?string $capability = null,
		private string $context = 'settings',
	) {
		$this->group = '' === $group ? $option : $group;
		// A secret stands on its own in settings, but never in a repeater's rows.
		$this->sets = array_map( static fn( $fields ): array => Form::without_secrets( (array) $fields, 'a repeater', true ), $sets );
	}

	/**
	 * Registers the setting (with its REST schema) and the migrations.
	 *
	 * The option can also be written outside the kit's form: options.php and
	 * the REST settings endpoint only ask for manage_options. Both are held to
	 * the screen's capability (the "{hook}_bwc_capability" filter included):
	 * options.php through its capability filter, REST with a 403, and any
	 * other save by a logged-in user through sanitize(), which keeps what's stored.
	 */
	public function register(): void {
		$register = function (): void {
			register_setting(
				$this->group,
				$this->option,
				array(
					'type'              => 'object',
					'sanitize_callback' => array( $this, 'sanitize' ),
					'default'           => array(),
					'show_in_rest'      => array( 'schema' => $this->schema() ),
				)
			);

			$this->kit->fields()->allow_search( array_merge( ...array_values( array_map( 'array_values', $this->sets ) ) ), array( $this, 'can_edit' ) );
		};

		add_filter( 'option_page_capability_' . $this->group, array( $this, 'capability' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'rest_guard' ), 10, 3 );

		if ( did_action( 'init' ) ) {
			$register();
		} else {
			add_action( 'init', $register );
		}

		if ( $this->migrations ) {
			add_action( 'admin_init', array( $this, 'migrate' ) );
		}
	}

	/**
	 * "settings".
	 */
	public function context(): string {
		return 'settings';
	}

	/**
	 * The option name.
	 */
	public function name_base(): string {
		return $this->option;
	}

	/**
	 * The settings group, for settings_fields().
	 */
	public function group(): string {
		return $this->group;
	}

	/**
	 * The stored option, as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function stored(): array {
		if ( null === $this->memo ) {
			$value      = get_option( $this->option, array() );
			$this->memo = is_array( $value ) ? $value : array();
		}

		return $this->memo;
	}

	/**
	 * A bucket's values over its defaults.
	 *
	 * @param string                     $set    Set id.
	 * @param list<array<string, mixed>> $fields Field descriptions.
	 * @return array<string, mixed>
	 */
	public function values( string $set, array $fields ): array {
		$stored = $this->stored()[ $set ] ?? array();

		return array_merge( Form::defaults( $fields ), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One value for the plugin's code: the stored value, or the default
	 * (also when the field shows its default as the placeholder and is empty).
	 *
	 * @param string $set Set id.
	 * @param string $id  Field id.
	 */
	public function get( string $set, string $id ): mixed {
		foreach ( Form::normalize( $this->sets[ $set ] ?? array() ) as $field ) {
			if ( $field['id'] === $id ) {
				$value = $this->values( $set, $this->sets[ $set ] )[ $id ] ?? $field['default'];
				return ( true === $field['placeholder'] && '' === $value ) ? $field['default'] : $value;
			}
		}

		return null;
	}

	/**
	 * Merges values into a bucket and saves.
	 *
	 * @param string               $set    Set id.
	 * @param array<string, mixed> $values Clean values.
	 */
	public function write( string $set, array $values ): bool {
		$all         = $this->stored();
		$bucket      = isset( $all[ $set ] ) && is_array( $all[ $set ] ) ? $all[ $set ] : array();
		$all[ $set ] = array_merge( $bucket, $values );

		self::$sanitized[ $this->option ] = $all;
		$saved                            = update_option( $this->option, $all, $this->autoload );
		unset( self::$sanitized[ $this->option ] );
		$this->memo = null;

		return $saved;
	}

	/**
	 * The capability the settings need: the screen's, through the kit's filter.
	 */
	public function capability(): string {
		return $this->kit->capability( $this->context, $this->capability );
	}

	/**
	 * Whether the current user may change the settings.
	 */
	public function can_edit(): bool {
		return current_user_can( $this->capability() );
	}

	/**
	 * Refuses a REST request that writes this option without the screen's capability.
	 *
	 * @param mixed            $result  The response so far, usually null.
	 * @param \WP_REST_Server  $server  The server.
	 * @param \WP_REST_Request $request The request.
	 * @return mixed
	 */
	public function rest_guard( mixed $result, \WP_REST_Server $server, \WP_REST_Request $request ): mixed {
		if ( null !== $result || '/wp/v2/settings' !== $request->get_route() || 'GET' === $request->get_method() ) {
			return $result;
		}

		if ( ! array_key_exists( $this->option, $request->get_params() ) || $this->can_edit() ) {
			return $result;
		}

		return new \WP_Error( 'rest_forbidden', $this->kit->strings()->get( 'settings_not_allowed' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Sanitize callback for register_setting: the posted buckets through their
	 * fields, merged over what's stored. From the REST API (no markers), the
	 * keys a request sends count as shown, and the rest keep what's stored.
	 *
	 * @param mixed $input Posted value, unslashed.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		if ( isset( self::$sanitized[ $this->option ] ) ) {
			return self::$sanitized[ $this->option ];
		}

		// The value this callback just returned, sanitized again by add_option().
		if ( isset( self::$last[ $this->option ] ) && $input === self::$last[ $this->option ] ) {
			return self::$last[ $this->option ];
		}

		// Saved by someone without the screen's capability (options.php's "all
		// settings" form, another plugin): keep what's stored. Cron, WP-CLI and
		// the plugin's own write() aren't a user's save and go through.
		if ( is_user_logged_in() && ! $this->can_edit() ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( $this->option, $this->kit->name( 'not_allowed' ), $this->kit->strings()->get( 'settings_not_allowed' ) );
			}
			return $this->stored();
		}

		$input = is_array( $input ) ? $input : array();
		$clean = $this->clean( $input, ! isset( $input['_shown'] ) );

		if ( $clean['errors'] && function_exists( 'add_settings_error' ) ) {
			add_settings_error( $this->option, $this->kit->name( 'fields' ), $this->kit->strings()->count( 'fields_not_saved', count( $clean['errors'] ) ) );
		}

		$this->kit->notices()->fields( $this->messages_key(), $clean['errors'], $clean['notes'] );
		self::$last[ $this->option ] = $clean['values'];
		$this->memo                  = null;

		return $clean['values'];
	}

	/**
	 * Saves a posted form (the settings screen's own save action, not
	 * options.php): sanitizes, merges over what's stored and writes.
	 *
	 * @param array<string, mixed> $posted Everything posted under the option name, unslashed.
	 * @return array{errors: array<string, string>, notes: array<string, string>}
	 */
	public function save( array $posted ): array {
		$clean = $this->clean( $posted, false );

		self::$sanitized[ $this->option ] = $clean['values'];
		update_option( $this->option, $clean['values'], $this->autoload );
		unset( self::$sanitized[ $this->option ] );
		$this->memo = null;

		return array(
			'errors' => $clean['errors'],
			'notes'  => $clean['notes'],
		);
	}

	/**
	 * The whole option after a posted form, with messages by "{bucket}.{id}".
	 *
	 * @param array<string, mixed> $input Posted value, unslashed.
	 * @param bool                 $rest  Count the keys sent in each bucket as shown.
	 * @return array{values: array<string, mixed>, errors: array<string, string>, notes: array<string, string>}
	 */
	private function clean( array $input, bool $rest ): array {
		$all    = $this->stored();
		$errors = array();
		$notes  = array();

		foreach ( $this->sets as $set => $fields ) {
			if ( ! isset( $input[ $set ] ) && ! isset( $input['_shown'][ $set ] ) ) {
				continue;
			}

			if ( $rest ) {
				// Only the keys the request sends: an unsent checkbox or list isn't "turned off".
				$sent                    = isset( $input[ $set ] ) && is_array( $input[ $set ] ) ? $input[ $set ] : array();
				$input['_shown'][ $set ] = array_fill_keys( array_keys( array_intersect_key( Form::defaults( $fields ), $sent ) ), '1' );
			}

			$result      = Form::sanitize( $fields, $set, $input, $this->values( $set, $fields ) );
			$bucket      = isset( $all[ $set ] ) && is_array( $all[ $set ] ) ? $all[ $set ] : array();
			$all[ $set ] = array_merge( $bucket, $result['values'] );

			foreach ( $result['errors'] as $id => $message ) {
				$errors[ $set . '.' . $id ] = $message;
			}
			foreach ( $result['notes'] as $id => $message ) {
				$notes[ $set . '.' . $id ] = $message;
			}
		}

		return array(
			'values' => $all,
			'errors' => $errors,
			'notes'  => $notes,
		);
	}

	/**
	 * Key of this option's field messages in the Notices service.
	 */
	public function messages_key(): string {
		return 'settings:' . $this->option;
	}

	/**
	 * The last save's field messages, read once per request.
	 *
	 * @return array{errors: array<string, string>, notes: array<string, string>}
	 */
	public function messages(): array {
		$this->messages ??= $this->kit->notices()->take_fields( $this->messages_key() );

		return $this->messages;
	}

	/**
	 * One set's fields with their values and the last save's messages.
	 *
	 * @param string $set    Set id.
	 * @param string $layout "stack" or "table".
	 */
	public function form( string $set, string $layout = 'stack' ): string {
		$messages = $this->messages();
		$mine     = array(
			'errors' => array(),
			'notes'  => array(),
		);

		foreach ( $mine as $kind => $unused ) {
			foreach ( $messages[ $kind ] as $path => $message ) {
				[ $bucket, $id ] = array_pad( explode( '.', (string) $path, 2 ), 2, '' );
				if ( $bucket === $set ) {
					$mine[ $kind ][ $id ] = $message;
				}
			}
		}

		return Form::markup(
			array(
				'fields'  => $this->sets[ $set ] ?? array(),
				'storage' => $this,
				'set'     => $set,
				'errors'  => $mine['errors'],
				'notes'   => $mine['notes'],
				'layout'  => $layout,
			)
		);
	}

	/**
	 * JSON Schema of the whole option: an object per bucket.
	 *
	 * @return array<string, mixed>
	 */
	public function schema(): array {
		$properties = array();
		foreach ( $this->sets as $set => $fields ) {
			$properties[ $set ] = array(
				'type'       => 'object',
				'properties' => Schema::fields( $fields ),
			);
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
		);
	}

	/**
	 * Runs the migration steps the stored option hasn't had yet.
	 */
	public function migrate(): void {
		$key  = $this->option . '_schema';
		$have = (int) get_option( $key, 0 );
		ksort( $this->migrations );

		$latest = $have;
		$all    = $this->stored();
		foreach ( $this->migrations as $number => $step ) {
			if ( (int) $number > $have && is_callable( $step ) ) {
				$all    = (array) $step( $all );
				$latest = (int) $number;
			}
		}

		if ( $latest > $have ) {
			self::$sanitized[ $this->option ] = $all;
			update_option( $this->option, $all, $this->autoload );
			unset( self::$sanitized[ $this->option ] );
			update_option( $key, $latest, false );
			$this->memo = null;
		}
	}
}
