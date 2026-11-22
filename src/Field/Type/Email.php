<?php
/**
 * An email address.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "email". Empty is allowed unless required.
 */
class Email extends Text {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'email';
	}

	/**
	 * The input's type attribute.
	 */
	protected function input_type(): string {
		return 'email';
	}

	/**
	 * A valid address, or rejected.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$text = trim( is_scalar( $raw ) ? (string) $raw : '' );
		if ( '' === $text ) {
			return Result::ok( '' );
		}

		$email = sanitize_email( $text );

		return is_email( $email ) ? Result::ok( $email ) : Result::rejected( $stored, self::say( 'field_email' ) );
	}

	/**
	 * A string in email format.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'   => 'string',
			'format' => 'email',
		);
	}
}
