<?php
/**
 * A web address.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "url". 'local' => true limits it to this site.
 */
class Url extends Text {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'url';
	}

	/**
	 * Extra keys: local, schemes.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default' => '',
			'local'   => false,
			'schemes' => array( 'http', 'https' ),
		);
	}

	/**
	 * The input's type attribute.
	 */
	protected function input_type(): string {
		return 'url';
	}

	/**
	 * A valid address with an allowed scheme, on this site when local.
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

		// A scheme and no spaces first: esc_url_raw() would turn "not a url" into "http://notaurl".
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://[^\s/?\#]+\S*$#i', $text ) ) {
			return Result::rejected( $stored, self::say( 'field_url' ) );
		}

		$url    = esc_url_raw( $text, (array) $field['schemes'] );
		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );

		if ( '' === $url || ! in_array( strtolower( $scheme ), (array) $field['schemes'], true ) || ! wp_parse_url( $url, PHP_URL_HOST ) ) {
			return Result::rejected( $stored, self::say( 'field_url' ) );
		}

		if ( $field['local'] && '' === wp_validate_redirect( $url, '' ) ) {
			return Result::rejected( $stored, self::say( 'field_url_local' ) );
		}

		return Result::ok( $url );
	}

	/**
	 * A string in URI format.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'   => 'string',
			'format' => 'uri',
		);
	}
}
