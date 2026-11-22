<?php
/**
 * Everything a field type needs to print its control.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Field context. Types never build their own
 * input names or read their own values; the form hands them this.
 */
final class Context {

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit         The plugin's kit.
	 * @param array<string, mixed> $field       Field description, normalised.
	 * @param mixed                $value       Value, with its default.
	 * @param string               $name        Full input name.
	 * @param string               $id          Input id.
	 * @param string               $describedby Ids of the help and error lines that exist.
	 * @param bool                 $invalid     Whether there's an error to show.
	 * @param bool                 $read_only   Whether the field is read-only.
	 * @param string               $storage     "settings", "post", "term" or "user".
	 * @param array<string, mixed> $values      The set's other values (a radio's inline field reads its own).
	 */
	public function __construct(
		public readonly Kit $kit,
		public readonly array $field,
		public readonly mixed $value,
		public readonly string $name,
		public readonly string $id,
		public readonly string $describedby = '',
		public readonly bool $invalid = false,
		public readonly bool $read_only = false,
		public readonly string $storage = 'settings',
		public readonly array $values = array(),
	) {}

	/**
	 * Attributes most controls share: id, name, described-by, invalid, readonly,
	 * required and the browser-side limits from isValid.
	 *
	 * @param bool $readonly_attribute Print readonly (text-like inputs only).
	 * @return array<string, mixed>
	 */
	public function attributes( bool $readonly_attribute = true ): array {
		$valid = (array) ( $this->field['isValid'] ?? array() );

		return array(
			'id'               => $this->id,
			'name'             => $this->name,
			'aria-describedby' => '' === $this->describedby ? null : $this->describedby,
			'aria-invalid'     => $this->invalid ? 'true' : null,
			'readonly'         => $readonly_attribute && $this->read_only,
			'required'         => ! empty( $valid['required'] ) && ! $this->read_only,
		);
	}

	/**
	 * The same context for a sub-field (a pair's halves).
	 *
	 * @param array<string, mixed> $field Sub-field description, normalised.
	 * @param mixed                $value Its value.
	 */
	public function sub( array $field, mixed $value ): self {
		return new self(
			$this->kit,
			$field,
			$value,
			$this->name . '[' . $field['id'] . ']',
			$this->id . '-' . $field['id'],
			'',
			false,
			$this->read_only,
			$this->storage
		);
	}

	/**
	 * The context of a field beside this one in the same set (a radio's
	 * inline field): same name base, its own key, its own value.
	 *
	 * @param array<string, mixed> $field Field description, normalised.
	 */
	public function sibling( array $field ): self {
		$id = (string) $field['id'];

		return new self(
			$this->kit,
			$field,
			$this->values[ $id ] ?? $field['default'],
			(string) preg_replace( '/\[[^\[\]]*\]$/', '[' . $id . ']', $this->name ),
			(string) preg_replace( '/-' . preg_quote( (string) $this->field['id'], '/' ) . '$/', '-' . $id, $this->id ),
			'',
			false,
			$this->read_only,
			$this->storage,
			$this->values
		);
	}
}
