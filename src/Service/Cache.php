<?php
/**
 * Slow results kept for a few minutes, with their age and a refresh.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Cache with refresh. Each entry is a
 * transient holding version, build time and data; an index option lists them
 * so forget_all() and uninstall can clear every one.
 */
final class Cache {

	/**
	 * Entries read in this request, by transient name.
	 *
	 * @var array<string, array{version: int, built: int, data: mixed, failed?: bool}>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Kit $kit The kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * The cached result, or a fresh one built and stored.
	 *
	 * A build that returns a WP_Error or throws is stored as a failure for
	 * failure_ttl, so a broken source isn't asked again on every page load.
	 *
	 * @param string               $name    Entry name: lowercase letters, digits, underscores.
	 * @param int                  $version Bump when the data's shape changes; older entries are rebuilt.
	 * @param int                  $ttl     Seconds to keep it.
	 * @param callable(): mixed    $build   Builds the data.
	 * @param array<string, mixed> $args    vary (array: one entry per variant), failure_ttl (seconds, default 300).
	 * @return mixed The data, or a WP_Error when the build failed.
	 */
	public function remember( string $name, int $version, int $ttl, callable $build, array $args = array() ): mixed {
		$key   = $this->key( $name, (array) ( $args['vary'] ?? array() ) );
		$entry = $this->read( $key );

		if ( null !== $entry && $entry['version'] === $version ) {
			return ! empty( $entry['failed'] ) ? new \WP_Error( 'bwc_cache_failed', (string) $entry['data'] ) : $entry['data'];
		}

		try {
			$data = $build();
		} catch ( \Throwable $error ) {
			$data = new \WP_Error( 'bwc_cache_failed', $error->getMessage() );
		}

		$failed = is_wp_error( $data );
		$entry  = array(
			'version' => $version,
			'built'   => time(),
			'data'    => $failed ? $data->get_error_message() : $data,
		);
		if ( $failed ) {
			$entry['failed'] = true;
		}

		set_transient( $key, $entry, $failed ? (int) ( $args['failure_ttl'] ?? 5 * MINUTE_IN_SECONDS ) : $ttl );
		$this->memo[ $key ] = $entry;
		$this->index( $name, $key );

		return $data;
	}

	/**
	 * When an entry was built (Unix time), or null when there is none.
	 *
	 * @param string               $name Entry name.
	 * @param array<string, mixed> $vary The variant, as given to remember().
	 */
	public function built( string $name, array $vary = array() ): ?int {
		$entry = $this->read( $this->key( $name, $vary ) );

		return null === $entry ? null : $entry['built'];
	}

	/**
	 * Forgets an entry, every variant of it.
	 *
	 * @param string $name Entry name.
	 */
	public function forget( string $name ): void {
		$index = $this->index_read();
		foreach ( $index[ $name ] ?? array() as $key ) {
			delete_transient( $key );
			unset( $this->memo[ $key ] );
		}
		unset( $index[ $name ] );
		$this->index_write( $index );
	}

	/**
	 * Forgets every entry this plugin's kit made.
	 */
	public function forget_all(): void {
		foreach ( array_keys( $this->index_read() ) as $name ) {
			$this->forget( (string) $name );
		}
		delete_option( $this->kit->name( 'cache_index' ) );
	}

	/**
	 * Registers the "refresh" action: forgets the named entries and comes back.
	 * Screens show it with refresh_button().
	 */
	public function register_refresh(): void {
		if ( $this->kit->actions()->has( 'refresh' ) ) {
			return;
		}

		$this->kit->actions()->register(
			'refresh',
			function ( array $input ): ?array {
				foreach ( $input['cache'] as $name ) {
					$this->forget( $name );
				}
				return null;
			},
			array( 'input' => array( 'cache' => 'key[]' ) )
		);
	}

	/**
	 * A "Refresh data" button that forgets these entries.
	 *
	 * @param list<string>         $names Entry names.
	 * @param array<string, mixed> $args  Button args for Actions::button() (label, variant, inside_form…).
	 */
	public function refresh_button( array $names, array $args = array() ): string {
		$this->register_refresh();
		$label = (string) ( $args['label'] ?? $this->kit->strings()->get( 'refresh' ) );
		unset( $args['label'] );

		return $this->kit->actions()->button(
			'refresh',
			$label,
			$args + array(
				'input'   => array( 'cache' => array_values( $names ) ),
				'variant' => 'outline',
				'size'    => 'small',
				'icon'    => 'refresh',
			)
		);
	}

	/**
	 * Transient name of an entry: "{hook}_bwc_cache_{name}", plus a hash of the variant.
	 *
	 * @param string               $name Entry name.
	 * @param array<string, mixed> $vary The variant.
	 */
	private function key( string $name, array $vary ): string {
		$key = $this->kit->name( 'cache_' . sanitize_key( $name ) );
		if ( $vary ) {
			ksort( $vary );
			$key .= '_' . substr( md5( (string) wp_json_encode( $vary ) ), 0, 10 );
		}

		return $key;
	}

	/**
	 * A stored entry, or null.
	 *
	 * @param string $key Transient name.
	 * @return array{version: int, built: int, data: mixed, failed?: bool}|null
	 */
	private function read( string $key ): ?array {
		if ( ! isset( $this->memo[ $key ] ) ) {
			$entry = get_transient( $key );
			if ( ! is_array( $entry ) || ! isset( $entry['version'], $entry['built'] ) ) {
				return null;
			}
			$this->memo[ $key ] = array(
				'version' => (int) $entry['version'],
				'built'   => (int) $entry['built'],
				'data'    => $entry['data'] ?? null,
			) + ( empty( $entry['failed'] ) ? array() : array( 'failed' => true ) );
		}

		return $this->memo[ $key ];
	}

	/**
	 * Notes an entry's transient in the index.
	 *
	 * @param string $name Entry name.
	 * @param string $key  Transient name.
	 */
	private function index( string $name, string $key ): void {
		$index = $this->index_read();
		if ( ! in_array( $key, $index[ $name ] ?? array(), true ) ) {
			$index[ $name ][] = $key;
			$this->index_write( $index );
		}
	}

	/**
	 * The index: entry name => its transient names.
	 *
	 * @return array<string, list<string>>
	 */
	private function index_read(): array {
		$index = get_option( $this->kit->name( 'cache_index' ), array() );

		return is_array( $index ) ? $index : array();
	}

	/**
	 * Saves the index, not autoloaded.
	 *
	 * @param array<string, list<string>> $index The index.
	 */
	private function index_write( array $index ): void {
		if ( ! $index ) {
			delete_option( $this->kit->name( 'cache_index' ) );
			return;
		}
		update_option( $this->kit->name( 'cache_index' ), $index, false );
	}
}
