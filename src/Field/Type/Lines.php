<?php
/**
 * A list typed one item per line.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "list"; the class is Lines because "list"
 * is a reserved word in PHP. Valid lines are saved; invalid ones are named in
 * the message so the owner can fix and re-add them. Item kinds: text, email,
 * url, path, id, and user (a username or email per line, stored as the
 * account's id; lines with no account are named).
 */
class Lines extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'list';
	}

	/**
	 * Extra keys.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'    => array(),
			'item'       => 'text',
			'max_items'  => 0,
			'separators' => "\n",
			'unique'     => true,
			'rows'       => 5,
		);
	}

	/**
	 * A textarea with one item per line.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field = $context->field;

		return '<textarea' . Html::attributes(
			array_merge(
				array(
					'class' => Html::classes(
						'bwc-control',
						array(
							'textarea',
							'list',
							'full',
							'mono' => ! empty( $field['monospace'] ) || in_array( $field['item'], array( 'path', 'id' ), true ),
						)
					),
					'rows'  => (int) $field['rows'],
				),
				$context->attributes()
			)
		) . '>' . esc_textarea( implode( "\n", 'user' === $field['item'] ? self::logins( (array) $context->value ) : array_map( 'strval', (array) $context->value ) ) ) . '</textarea>';
	}

	/**
	 * Split, trim, check each line.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$text  = is_array( $raw ) ? implode( "\n", array_map( 'strval', $raw ) ) : ( is_scalar( $raw ) ? (string) $raw : '' );
		$lines = preg_split( '/[' . preg_quote( (string) $field['separators'], '/' ) . '\r]+/', $text );
		$kept  = array();
		$bad   = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			$clean = 'user' === $field['item'] ? self::user( $line ) : self::item( (string) $field['item'], $line );
			if ( null === $clean ) {
				$bad[] = sanitize_text_field( $line );
			} elseif ( ! $field['unique'] || ! in_array( $clean, $kept, true ) ) {
				$kept[] = $clean;
			}
		}

		$messages = array();
		if ( $bad ) {
			$messages[] = self::say( 'field_lines_rejected', implode( ', ', $bad ) );
		}

		$max = (int) $field['max_items'];
		if ( $max > 0 && count( $kept ) > $max ) {
			$kept       = array_slice( $kept, 0, $max );
			$messages[] = Kit::instance()->strings()->count( 'field_max_items', $max );
		}

		if ( ! $messages ) {
			return Result::ok( $kept );
		}

		return $bad ? Result::partial( $kept, implode( ' ', $messages ) ) : Result::adjusted( $kept, implode( ' ', $messages ) );
	}

	/**
	 * One line, checked and cleaned for its kind, or null when it isn't valid.
	 *
	 * @param string $kind text, email, url, path or id.
	 * @param string $line The line, trimmed.
	 */
	public static function item( string $kind, string $line ): ?string {
		switch ( $kind ) {
			case 'email':
				$email = sanitize_email( $line );
				return is_email( $email ) ? $email : null;
			case 'url':
				$url = esc_url_raw( $line, array( 'http', 'https' ) );
				return '' !== $url && wp_http_validate_url( $url ) ? $url : null;
			case 'path':
				return preg_match( '#^/[^\s<>"]*$#', $line ) ? $line : null;
			case 'id':
				return preg_match( '/^[A-Za-z][\w:.-]*$/', $line ) ? $line : null;
			default:
				$text = sanitize_text_field( $line );
				return '' === $text ? null : $text;
		}
	}

	/**
	 * A user's id from a line holding their username or email; null when no account matches.
	 *
	 * @param string $line The line, trimmed.
	 */
	public static function user( string $line ): ?int {
		$user = is_email( $line ) ? get_user_by( 'email', $line ) : get_user_by( 'login', $line );

		return $user ? (int) $user->ID : null;
	}

	/**
	 * Usernames of stored user ids, for the textarea; ids with no account are left out.
	 *
	 * @param array<int|string, mixed> $ids Stored ids.
	 * @return list<string>
	 */
	private static function logins( array $ids ): array {
		$logins = array();
		foreach ( $ids as $id ) {
			$user = is_numeric( $id ) ? get_userdata( (int) $id ) : false;
			if ( $user ) {
				$logins[] = (string) $user->user_login;
			}
		}

		return $logins;
	}

	/**
	 * An array of strings (user ids for a list of users).
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		$items = array( 'type' => 'user' === $field['item'] ? 'integer' : 'string' );
		if ( 'email' === $field['item'] ) {
			$items['format'] = 'email';
		} elseif ( 'url' === $field['item'] ) {
			$items['format'] = 'uri';
		}

		return array_filter(
			array(
				'type'     => 'array',
				'items'    => $items,
				'maxItems' => (int) $field['max_items'] > 0 ? (int) $field['max_items'] : null,
			),
			static fn( $value ): bool => null !== $value
		);
	}
}
