<?php
/**
 * A time of day, HH:MM.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "time": the browser's time box, in
 * 15-minute steps unless isValid step says otherwise (in seconds).
 */
class Time extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'time';
	}

	/**
	 * Extra keys: timezone_help.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'       => '',
			'timezone_help' => true,
		);
	}

	/**
	 * The time box.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$valid = (array) $context->field['isValid'];

		return '<input' . Html::attributes(
			array_merge(
				array(
					'type'  => 'time',
					'class' => 'bwc-control bwc-control--time',
					'value' => (string) $context->value,
					'step'  => (int) ( $valid['step'] ?? 900 ),
				),
				$context->attributes()
			)
		) . '>';
	}

	/**
	 * "In the site's timezone (…)."
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function help( array $field ): string {
		return $field['timezone_help'] ? self::say( 'field_timezone', self::timezone() ) : '';
	}

	/**
	 * HH:MM, seconds dropped.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$text = is_scalar( $raw ) ? trim( (string) $raw ) : '';

		if ( '' === $text ) {
			return Result::ok( '' );
		}

		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $text, $match ) ) {
			return Result::rejected( $stored, self::say( 'field_time' ) );
		}

		return Result::ok( $match[1] . ':' . $match[2] );
	}

	/**
	 * A string, HH:MM.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'    => 'string',
			'pattern' => '^(?:([01]\d|2[0-3]):[0-5]\d)?$',
		);
	}
}
