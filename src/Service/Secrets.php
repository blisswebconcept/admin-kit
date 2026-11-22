<?php
/**
 * API keys and tokens: encrypted at rest, never shown back.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Secrets. A secret is stored as
 * "sodium:" + base64( nonce + ciphertext ) in its own non-autoloaded option,
 * {hook}_bwc_secret_{name}, encrypted with a key made from the plugin id, the
 * secret's name and the site's auth salt. A non-empty wp-config.php constant
 * always wins. After the salts change a saved secret can't be read: it shows
 * as "unreadable" until it's entered again. Nothing is ever stored in plain text.
 */
final class Secrets {

	/**
	 * Prefix of a stored value.
	 */
	private const PREFIX = 'sodium:';

	/**
	 * Defined secrets: name => constant, mask.
	 *
	 * @var array<string, array{constant: string, mask: callable|null}>
	 */
	private array $defined = array();

	/**
	 * Constructor.
	 *
	 * @param Kit $kit The kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * Describes a secret: the wp-config.php constant that wins, and how to mask it.
	 *
	 * @param string               $name Secret name: lowercase letters, digits, underscores.
	 * @param array<string, mixed> $args constant, mask (callable( string $value ): string).
	 */
	public function define( string $name, array $args = array() ): void {
		$this->defined[ sanitize_key( $name ) ] = array(
			'constant' => isset( $args['constant'] ) ? (string) $args['constant'] : '',
			'mask'     => isset( $args['mask'] ) && is_callable( $args['mask'] ) ? $args['mask'] : null,
		);
	}

	/**
	 * Where a secret comes from: "config" (the constant), "saved", "none", or
	 * "unreadable" (saved under salts that have since changed, or no sodium).
	 *
	 * @param string $name Secret name.
	 */
	public function source( string $name ): string {
		if ( '' !== $this->from_constant( $name ) ) {
			return 'config';
		}

		$stored = get_option( $this->option( $name ), '' );
		if ( ! is_string( $stored ) || '' === $stored ) {
			return 'none';
		}

		return null === $this->decrypt( $name, $stored ) ? 'unreadable' : 'saved';
	}

	/**
	 * The secret, or '' when there is none or it can't be read.
	 *
	 * @param string $name Secret name.
	 */
	public function get( string $name ): string {
		$constant = $this->from_constant( $name );
		if ( '' !== $constant ) {
			return $constant;
		}

		$stored = get_option( $this->option( $name ), '' );

		return is_string( $stored ) && '' !== $stored ? (string) $this->decrypt( $name, $stored ) : '';
	}

	/**
	 * The secret masked, for a field's placeholder: "••••••••abcd". '' when none.
	 *
	 * @param string $name Secret name.
	 */
	public function masked( string $name ): string {
		$value = $this->get( $name );
		if ( '' === $value ) {
			return '';
		}

		$mask = $this->defined[ sanitize_key( $name ) ]['mask'] ?? null;

		return null === $mask ? str_repeat( '•', 8 ) . ( strlen( $value ) > 8 ? substr( $value, -4 ) : '' ) : (string) $mask( $value );
	}

	/**
	 * Saves a secret, encrypted. False when sodium is missing: secrets are
	 * never stored in plain text.
	 *
	 * @param string $name  Secret name.
	 * @param string $value The secret.
	 */
	public function set( string $name, string $value ): bool {
		if ( ! self::available() || '' === $value ) {
			return false;
		}

		try {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $value, $nonce, $this->key( $name ) );
		} catch ( \Throwable $error ) {
			return false;
		}

		update_option( $this->option( $name ), self::PREFIX . base64_encode( $nonce . $cipher ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- storing binary ciphertext as text.

		return true;
	}

	/**
	 * Deletes a saved secret (a constant stays).
	 *
	 * @param string $name Secret name.
	 */
	public function delete( string $name ): void {
		delete_option( $this->option( $name ) );
	}

	/**
	 * Whether secrets can be stored here (PHP's sodium extension).
	 */
	public static function available(): bool {
		return function_exists( 'sodium_crypto_secretbox' );
	}

	/**
	 * The constant that wins for a secret, if it's defined and not empty.
	 *
	 * @param string $name Secret name.
	 */
	public function constant( string $name ): string {
		return $this->defined[ sanitize_key( $name ) ]['constant'] ?? '';
	}

	/**
	 * The constant's value, or ''.
	 *
	 * @param string $name Secret name.
	 */
	private function from_constant( string $name ): string {
		$constant = $this->constant( $name );

		return '' !== $constant && defined( $constant ) && is_scalar( constant( $constant ) ) ? trim( (string) constant( $constant ) ) : '';
	}

	/**
	 * A stored value, decrypted; null when it can't be read.
	 *
	 * @param string $name   Secret name.
	 * @param string $stored Stored value.
	 */
	private function decrypt( string $name, string $stored ): ?string {
		if ( ! self::available() || ! str_starts_with( $stored, self::PREFIX ) ) {
			return null;
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reading back stored ciphertext.
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		try {
			$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $this->key( $name ) );
		} catch ( \Throwable $error ) {
			return null;
		}

		return false === $plain ? null : $plain;
	}

	/**
	 * The key for a secret: its own per plugin and per name.
	 *
	 * @param string $name Secret name.
	 */
	private function key( string $name ): string {
		return hash( 'sha256', $this->kit->id() . '|' . sanitize_key( $name ) . '|' . wp_salt( 'auth' ), true );
	}

	/**
	 * Option name of a stored secret.
	 *
	 * @param string $name Secret name.
	 */
	private function option( string $name ): string {
		return $this->kit->name( 'secret_' . sanitize_key( $name ) );
	}
}
