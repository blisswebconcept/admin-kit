<?php
/**
 * What a field's sanitize did with the submitted value.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize result: saved as is, saved after an
 * adjustment the owner is told about (a clamp), or rejected (the stored value
 * stays and a message says why).
 */
final class Result {

	public const OK       = 'ok';
	public const ADJUSTED = 'adjusted';
	public const REJECTED = 'rejected';

	/**
	 * Constructor.
	 *
	 * @param string $status  OK, ADJUSTED or REJECTED.
	 * @param mixed  $value   Value to save, or the kept value when rejected.
	 * @param string $message Message for the owner, or "".
	 * @param bool   $error   Whether the message is an error (red) rather than a note.
	 */
	private function __construct( public readonly string $status, public readonly mixed $value, public readonly string $message, public readonly bool $error = false ) {}

	/**
	 * Saved as is.
	 *
	 * @param mixed $value Clean value.
	 */
	public static function ok( mixed $value ): self {
		return new self( self::OK, $value, '' );
	}

	/**
	 * Saved after a change the owner is told about.
	 *
	 * @param mixed  $value   Value saved.
	 * @param string $message What changed.
	 */
	public static function adjusted( mixed $value, string $message ): self {
		return new self( self::ADJUSTED, $value, $message );
	}

	/**
	 * Not saved: the stored value stays.
	 *
	 * @param mixed  $kept    The stored value.
	 * @param string $message Why it wasn't saved.
	 */
	public static function rejected( mixed $kept, string $message ): self {
		return new self( self::REJECTED, $kept, $message, true );
	}

	/**
	 * Partly saved: the good part is saved, the message says what wasn't
	 * (invalid lines of a list, one half of a pair).
	 *
	 * @param mixed  $value   Value saved.
	 * @param string $message What wasn't saved.
	 */
	public static function partial( mixed $value, string $message ): self {
		return new self( self::ADJUSTED, $value, $message, true );
	}

	/**
	 * Whether the value is saved.
	 */
	public function saved(): bool {
		return self::REJECTED !== $this->status;
	}
}
