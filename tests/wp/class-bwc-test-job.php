<?php
/**
 * A job for tests: its items, batch size and work are set by the test.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Service\Job;

defined( 'ABSPATH' ) || exit;

/**
 * A job for tests: its items, batch size and work are set by the test.
 */
final class BWC_Test_Job extends Job {

	/**
	 * Items the job works through.
	 *
	 * @var list<int|string>
	 */
	public static array $items = array();

	/**
	 * Items per batch.
	 */
	public static int $size = 1;

	/**
	 * Microseconds each item takes.
	 */
	public static int $sleep = 0;

	/**
	 * Items that throw.
	 *
	 * @var list<int|string>
	 */
	public static array $throw = array();

	/**
	 * Batches done, in order.
	 *
	 * @var list<list<int|string>>
	 */
	public static array $batches = array();

	/**
	 * Times finish() ran.
	 */
	public static int $finished = 0;

	/**
	 * Runs during each batch, as another request would.
	 *
	 * @var callable|null
	 */
	public static $during = null;

	/**
	 * No item count: percent() is null while running.
	 */
	public static bool $stages = false;

	/**
	 * Items that take more than one batch: item => batches still to go.
	 *
	 * @var array<string, int>
	 */
	public static array $again = array();

	/**
	 * Lock timeout in seconds, or 0 for the runner's.
	 */
	public static int $lock = 0;

	/**
	 * Lock timeout.
	 */
	protected static function lock_seconds(): int {
		return self::$lock ? self::$lock : parent::lock_seconds();
	}

	/**
	 * Back to the defaults, with no state left.
	 *
	 * @param list<int|string> $items Items.
	 */
	public static function reset( array $items ): void {
		self::$items    = $items;
		self::$size     = 1;
		self::$sleep    = 0;
		self::$throw    = array();
		self::$batches  = array();
		self::$finished = 0;
		self::$during   = null;
		self::$stages   = false;
		self::$again    = array();
		self::$lock     = 0;
		self::clear();
	}

	/**
	 * Id.
	 */
	public static function id(): string {
		return 'test';
	}

	/**
	 * Items.
	 *
	 * @return list<int|string>
	 */
	protected static function items(): array {
		return self::$items;
	}

	/**
	 * Batch.
	 *
	 * @param list<int|string> $pending Pending.
	 * @return list<int|string>
	 */
	protected static function batch( array $pending ): array {
		return array_slice( $pending, 0, self::$size );
	}

	/**
	 * Work.
	 *
	 * @param list<int|string>     $batch Batch.
	 * @param array<string, mixed> $state State.
	 * @throws RuntimeException For items in $throw.
	 */
	protected static function work( array $batch, array &$state ): void {
		self::$batches[] = $batch;

		if ( self::$during ) {
			( self::$during )();
		}

		foreach ( $batch as $id ) {
			if ( ( self::$again[ (string) $id ] ?? 0 ) > 0 ) {
				--self::$again[ (string) $id ];
				$state['again'][] = $id;
				continue;
			}
			if ( in_array( $id, self::$throw, true ) ) {
				throw new RuntimeException( 'item ' . esc_html( (string) $id ) . ' broke' );
			}
			if ( self::$sleep ) {
				usleep( self::$sleep );
			}
			$state['counts']['ok'] = (int) ( $state['counts']['ok'] ?? 0 ) + 1;
			if ( is_int( $id ) && 0 === $id % 2 ) {
				$state['failed'][] = $id;
			}
		}
	}

	/**
	 * Finish.
	 *
	 * @param array<string, mixed> $state State.
	 */
	protected static function finish( array &$state ): void {
		++self::$finished;
		$state['message'] = 'finished';
	}

	/**
	 * Progress.
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function progress( array $state ): string {
		return $state['done'] . '/' . $state['total'];
	}

	/**
	 * Percent, or null for a job in stages.
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function percent( array $state ): ?int {
		return self::$stages && self::is_running( $state ) ? null : parent::percent( $state );
	}
}
