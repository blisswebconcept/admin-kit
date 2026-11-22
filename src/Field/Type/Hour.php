<?php
/**
 * The hour a daily task runs.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "hour": a select of the 24 hours in the
 * site's time format, stored as 0–23.
 */
class Hour extends Select {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'hour';
	}

	/**
	 * Extra keys: timezone_help.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'       => 0,
			'multiple'      => false,
			'timezone_help' => true,
		);
	}

	/**
	 * The 24 hours.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<int|string, string>
	 */
	protected function options( array $field ): array {
		$format = (string) get_option( 'time_format', 'g:i a' );
		$hours  = array();
		for ( $hour = 0; $hour < 24; $hour++ ) {
			$hours[ (string) $hour ] = (string) wp_date( $format, $hour * HOUR_IN_SECONDS, new \DateTimeZone( 'UTC' ) );
		}

		return $hours;
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
	 * A whole number from 0 to 23.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$text = is_scalar( $raw ) ? trim( (string) $raw ) : '';

		if ( ! preg_match( '/^-?\d+$/', $text ) ) {
			return Result::rejected( $stored, self::say( 'field_choice' ) );
		}

		// Out of range: the nearest hour, saying so.
		$hour = (int) $text;
		if ( $hour > 23 ) {
			return Result::adjusted( 23, self::say( 'field_clamped_max', '23' ) );
		}
		if ( $hour < 0 ) {
			return Result::adjusted( 0, self::say( 'field_clamped_min', '0' ) );
		}

		return Result::ok( $hour );
	}

	/**
	 * An integer from 0 to 23.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return array(
			'type'    => 'integer',
			'minimum' => 0,
			'maximum' => 23,
		);
	}
}
