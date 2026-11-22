<?php
/**
 * Values stored as post, term or user meta.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Meta storage: one meta key per field,
 * {prefix}_{field id}, so other code and meta queries can use them; or, with
 * single_key, the whole set as one array. Values read back are cast to the
 * field's type (meta comes back as strings).
 */
abstract class MetaStorage implements Storage {

	/**
	 * True while the kit writes, so WordPress's meta sanitize filter (for REST
	 * writes) doesn't sanitize already-clean values a second time.
	 *
	 * @var bool
	 */
	protected static bool $writing = false;

	/**
	 * Constructor.
	 *
	 * @param Kit                        $kit        The kit.
	 * @param int                        $object_id  Post, term or user ID.
	 * @param list<array<string, mixed>> $fields     Field descriptions.
	 * @param string                     $prefix     Meta key prefix.
	 * @param string                     $single_key One meta key for the whole set, or "".
	 */
	public function __construct(
		protected Kit $kit,
		protected int $object_id,
		protected array $fields,
		protected string $prefix,
		protected string $single_key = '',
	) {}

	/**
	 * "post", "term" or "user": WordPress's meta type.
	 */
	abstract protected function meta_type(): string;

	/**
	 * "{hook}_bwc".
	 */
	public function name_base(): string {
		return $this->kit->hook() . '_bwc';
	}

	/**
	 * The storage context: the meta type.
	 */
	public function context(): string {
		return $this->meta_type();
	}

	/**
	 * A field's meta key.
	 *
	 * @param string $id Field id.
	 */
	public function key( string $id ): string {
		return $this->prefix . '_' . $id;
	}

	/**
	 * Stored values over the defaults, cast to each field's type.
	 *
	 * @param string                     $set    Set id (unused: the object and keys say where).
	 * @param list<array<string, mixed>> $fields Field descriptions.
	 * @return array<string, mixed>
	 */
	public function values( string $set, array $fields ): array {
		$type   = $this->meta_type();
		$values = array();
		$single = '' === $this->single_key ? null : get_metadata( $type, $this->object_id, $this->single_key, true );

		foreach ( Form::normalize( $fields ) as $field ) {
			$id = (string) $field['id'];

			if ( is_array( $single ) ) {
				$values[ $id ] = array_key_exists( $id, $single ) ? $single[ $id ] : $field['default'];
			} elseif ( null === $single && $this->object_id > 0 && metadata_exists( $type, $this->object_id, $this->key( $id ) ) ) {
				$values[ $id ] = Schema::cast( $field, get_metadata( $type, $this->object_id, $this->key( $id ), true ) );
			} else {
				$values[ $id ] = $field['default'];
			}
		}

		return $values;
	}

	/**
	 * Saves the given values; other keys are untouched.
	 *
	 * @param string               $set    Set id.
	 * @param array<string, mixed> $values Clean values.
	 */
	public function write( string $set, array $values ): bool {
		if ( ! $values || $this->object_id <= 0 ) {
			return false;
		}

		self::$writing = true;
		$type          = $this->meta_type();

		if ( '' !== $this->single_key ) {
			$stored = get_metadata( $type, $this->object_id, $this->single_key, true );
			update_metadata( $type, $this->object_id, $this->single_key, array_merge( is_array( $stored ) ? $stored : array(), $values ) );
		} else {
			foreach ( $values as $id => $value ) {
				update_metadata( $type, $this->object_id, $this->key( (string) $id ), $value );
			}
		}

		self::$writing = false;

		return true;
	}

	/**
	 * Sanitizes what was posted for this set, saves it, and keeps the messages
	 * for the page that loads next.
	 *
	 * @param string $set     Set id.
	 * @param string $message_key Messages key.
	 */
	protected function save_posted( string $set, string $message_key ): void {
		// The caller checks the nonce first; each field's type sanitizes its own value.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted = isset( $_POST[ $this->name_base() ] ) && is_array( $_POST[ $this->name_base() ] ) ? wp_unslash( $_POST[ $this->name_base() ] ) : array();
		// phpcs:enable

		$result = Form::sanitize( $this->fields, $set, (array) $posted, $this->values( $set, $this->fields ) );
		$this->write( $set, $result['values'] );
		Kit::instance()->notices()->fields( $message_key, $result['errors'], $result['notes'] );
	}

	/**
	 * Registers each field's meta key with its REST schema.
	 *
	 * The auth callback is the form's whole test (edit rights plus the box's
	 * or set's own capability), so the REST API and the Custom Fields panel
	 * can't change what the form wouldn't let the user change. A read-only
	 * field can't be changed from outside at all. In REST a field is shown
	 * in the "edit" context only: to users who can edit the object, never in
	 * a public response.
	 *
	 * @param Kit                        $kit       The kit.
	 * @param string                     $meta_type post, term or user.
	 * @param string                     $subtype   Post type or taxonomy, or "".
	 * @param list<array<string, mixed>> $fields    Field descriptions.
	 * @param string                     $prefix    Key prefix.
	 * @param bool                       $rest      Show in the REST API.
	 * @param callable                   $auth      auth_callback( $allowed, $key, $object_id ): bool.
	 */
	protected static function register_keys( Kit $kit, string $meta_type, string $subtype, array $fields, string $prefix, bool $rest, callable $auth ): void {
		foreach ( Form::normalize( $fields ) as $field ) {
			$schema = Schema::field( $field );
			$type   = $kit->fields()->get( (string) $field['type'] );
			if ( null === $schema || null === $type ) {
				continue;
			}

			$args = array(
				'type'              => (string) $schema['type'],
				'single'            => true,
				'default'           => $field['default'],
				'show_in_rest'      => $rest ? array( 'schema' => $schema + array( 'context' => array( 'edit' ) ) ) : false,
				'auth_callback'     => empty( $field['readOnly'] ) ? $auth : '__return_false',
				'sanitize_callback' => static function ( $value ) use ( $type, $field ) {
					if ( self::$writing ) {
						return $value;
					}
					$result = $type->sanitize( $value, $field['default'], $field, array() );
					return $result->saved() ? $result->value : $field['default'];
				},
			);

			if ( '' !== $subtype ) {
				$args['object_subtype'] = $subtype;
			}

			register_meta( $meta_type, $prefix . '_' . $field['id'], $args );
		}
	}

	/**
	 * Registers the one key a set stores all its fields in ("single_key"),
	 * kept out of the REST API and the Custom Fields panel: only the form
	 * (and the plugin's own code) writes it.
	 *
	 * @param string $meta_type post, term or user.
	 * @param string $subtype   Post type or taxonomy, or "".
	 * @param string $key       Meta key.
	 */
	protected static function register_single_key( string $meta_type, string $subtype, string $key ): void {
		$args = array(
			'type'          => 'object',
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => '__return_false',
		);

		if ( '' !== $subtype ) {
			$args['object_subtype'] = $subtype;
		}

		register_meta( $meta_type, $key, $args );
	}

	/**
	 * Runs a callback on init, now if init has run.
	 *
	 * @param callable $callback Callback.
	 */
	protected static function on_init( callable $callback ): void {
		if ( did_action( 'init' ) ) {
			$callback();
		} else {
			add_action( 'init', $callback );
		}
	}
}
