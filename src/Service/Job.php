<?php
/**
 * Work over a list, spread over short steps, so no request runs long.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Background job. Item ids may be numbers or strings, and percent() is null
 * while there is no count of items.
 *
 * A job names its items and says how to do a batch. Everything else is here:
 * the state, the lock, the time budget, the WP-Cron fallback, stopping, and
 * running again over the items that failed.
 *
 * Two things move a job on, and the lock keeps them from working at the same
 * time: the open screen, which asks for a short step about every second
 * (Jobs::handle_step), and WP-Cron, which takes a longer step each tick and
 * books the next while there is work left.
 *
 * Names belong to the plugin's kit: state option {hook}_bwc_job_{id}, lock
 * {hook}_bwc_job_lock_{id}, cron hook {hook}_bwc_job_{id}.
 */
abstract class Job {

	/**
	 * Seconds of work per step asked for by the screen.
	 */
	public const SCREEN_SECONDS = 8;

	/**
	 * Seconds of work per WP-Cron tick.
	 */
	public const CRON_SECONDS = 20;

	/**
	 * Seconds between WP-Cron ticks while a job runs.
	 */
	public const CRON_GAP = 30;

	/**
	 * A running job that hasn't moved for this long can be started again.
	 */
	public const ABANDON_SECONDS = 900;

	/**
	 * A batch tried this many times without finishing is skipped.
	 */
	public const MAX_TRIES = 3;

	/**
	 * A lock held this long was left by a request that died. Longer than any step can take.
	 */
	private const LOCK_SECONDS = 60;

	/**
	 * Stages after which a job does nothing more.
	 */
	private const ENDED = array( 'done', 'cancelled' );

	/**
	 * Job id: lowercase letters, digits and underscores, used in option and hook names.
	 */
	abstract public static function id(): string;

	/**
	 * Every item to work through, fixed when the job starts.
	 *
	 * @return list<int|string>
	 */
	abstract protected static function items(): array;

	/**
	 * Does one batch: records each item's result and adds to the state's
	 * counts, rows (results worth showing) and failed (items worth trying again).
	 *
	 * An item that takes more than one batch (one stage of a longer task, such
	 * as a feed read a page at a time) goes in the state's `again` list: it
	 * stays first in line for the next batch and isn't counted as done.
	 *
	 * @param list<int|string>     $batch Item ids.
	 * @param array<string, mixed> $state State, changed in place.
	 */
	abstract protected static function work( array $batch, array &$state ): void;

	/**
	 * Where the job is, in a few words, for the progress line.
	 *
	 * @param array<string, mixed> $state State.
	 */
	abstract public static function progress( array $state ): string;

	/**
	 * The next batch. Override to take several items at once.
	 *
	 * @param list<int|string> $pending Items not done yet, in order.
	 * @return list<int|string>
	 */
	protected static function batch( array $pending ): array {
		return array_slice( $pending, 0, 1 );
	}

	/**
	 * Seconds after which a held lock counts as left by a request that died.
	 * Longer than any one batch can take; override for a job whose batches can
	 * run longer than a minute.
	 */
	protected static function lock_seconds(): int {
		return self::LOCK_SECONDS;
	}

	/**
	 * Pause after each batch, in microseconds, so the host's firewall doesn't see a burst.
	 */
	protected static function pause(): int {
		return 0;
	}

	/**
	 * Records items that were tried MAX_TRIES times without finishing, or that threw.
	 *
	 * @param list<int|string>     $batch   Item ids.
	 * @param string               $message Why.
	 * @param array<string, mixed> $state   State, changed in place.
	 */
	protected static function skipped( array $batch, string $message, array &$state ): void {
		$state['counts']['skipped'] = (int) ( $state['counts']['skipped'] ?? 0 ) + count( $batch );
		$state['failed']            = array_values( array_unique( array_merge( (array) $state['failed'], $batch ), SORT_REGULAR ) );
		$state['message']           = $message;
	}

	/**
	 * Runs once, after the last batch.
	 *
	 * @param array<string, mixed> $state State, changed in place.
	 */
	protected static function finish( array &$state ): void {
	}

	/**
	 * How the ended job went, for the notice its starter sees once.
	 * Override for the plugin's own words.
	 *
	 * @param array<string, mixed> $state The ended job's state.
	 * @return array{tone: string, text: string}
	 */
	public static function result( array $state ): array {
		$strings = Kit::instance()->strings();
		$failed  = count( (array) ( $state['failed'] ?? array() ) );

		if ( 'cancelled' === ( $state['stage'] ?? '' ) ) {
			return array(
				'tone' => 'info',
				'text' => $strings->get( 'job_stopped' ) . ' ' . static::progress( $state ),
			);
		}

		return array(
			'tone' => $failed > 0 ? 'warning' : 'success',
			'text' => $strings->get( 'job_finished' ) . ' ' . static::progress( $state ),
		);
	}

	/**
	 * WP-Cron hook that moves this job on: "{hook}_bwc_job_{id}".
	 */
	public static function hook(): string {
		return Kit::instance()->name( 'job_' . static::id() );
	}

	/**
	 * Starts the job, unless it is already running.
	 *
	 * @param list<int|string>|null $only Work through only these items, such as the ones that failed last time.
	 * @param int                   $user Who started it from the screen, so the result is shown to them; 0 for scheduled runs.
	 * @return array<string, mixed> The job's state.
	 */
	public static function start( ?array $only = null, int $user = 0 ): array {
		$state = static::state();

		if ( $state && static::is_running( $state ) && ! static::is_abandoned( $state ) ) {
			return $state;
		}

		$items = self::ids( static::items() );
		if ( null !== $only ) {
			$wanted = array_map( 'strval', $only );
			$items  = array_values( array_filter( $items, static fn( $item ): bool => in_array( (string) $item, $wanted, true ) ) );
		}

		$state = array(
			'job'     => static::id(),
			'stage'   => 'running',
			'started' => time(),
			'updated' => time(),
			'ended'   => 0,
			'user'    => $user,
			'shown'   => 0 === $user,
			'retry'   => null !== $only,
			'pending' => $items,
			'total'   => count( $items ),
			'done'    => 0,
			'counts'  => array(),
			'rows'    => array(),
			'failed'  => array(),
			'attempt' => array(),
			'message' => '',
		);

		self::save( $state );
		self::book_cron();

		return $state;
	}

	/**
	 * The job's state, or null when it never ran.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function state(): ?array {
		$name = self::option();

		wp_cache_delete( $name, 'options' );
		$state = get_option( $name, null );

		return is_array( $state ) ? $state : null;
	}

	/**
	 * Whether a job is still working.
	 *
	 * @param array<string, mixed>|null $state State.
	 */
	public static function is_running( ?array $state ): bool {
		return null !== $state && ! in_array( $state['stage'] ?? 'done', self::ENDED, true );
	}

	/**
	 * Whether a running job stopped moving long ago.
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function is_abandoned( array $state ): bool {
		return (int) ( $state['updated'] ?? 0 ) < time() - self::ABANDON_SECONDS;
	}

	/**
	 * Does up to $seconds of work. Returns at once if another request holds the job.
	 *
	 * Stops early when another batch, at the average time a batch has taken
	 * this step, would run past the budget. 0 seconds does exactly one batch.
	 *
	 * @param int $seconds Time budget.
	 * @return array<string, mixed>|null The job's state afterwards.
	 */
	public static function step( int $seconds ): ?array {
		$state = static::state();
		$lock  = self::lock();

		if ( ! static::is_running( $state ) || ! Lock::take( $lock, static::lock_seconds() ) ) {
			return $state;
		}

		try {
			$started  = microtime( true );
			$deadline = $started + max( 0, $seconds );
			$batches  = 0;

			do {
				$state = self::work_batch( (array) $state );

				// Stopped from the screen while this batch ran: keep the stop.
				$stored = static::state();
				if ( 'cancelled' === ( $stored['stage'] ?? '' ) ) {
					$state['stage'] = 'cancelled';
					$state['ended'] = (int) $stored['ended'];
					$state['shown'] = (bool) $stored['shown'];
				}

				self::save( $state );

				$average = ( microtime( true ) - $started ) / ++$batches;
				$more    = static::is_running( $state ) && microtime( true ) + $average < $deadline;

				if ( $more && static::pause() ) {
					usleep( static::pause() );
				}
			} while ( $more );
		} finally {
			Lock::release( $lock );
		}

		return $state;
	}

	/**
	 * WP-Cron tick: a longer step, then the next tick if there is more to do.
	 */
	public static function on_cron(): void {
		if ( static::is_running( static::step( self::CRON_SECONDS ) ) ) {
			self::book_cron();
		}
	}

	/**
	 * Stops the job. What it did so far stays.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function cancel(): ?array {
		$state = static::state();

		if ( ! static::is_running( $state ) ) {
			return $state;
		}

		$state['stage'] = 'cancelled';
		$state['ended'] = time();

		self::save( $state );
		wp_clear_scheduled_hook( static::hook() );

		return $state;
	}

	/**
	 * The result of a job this user started from the screen, once, after it ended.
	 *
	 * @param int $user User id.
	 * @return array<string, mixed>|null
	 */
	public static function take_result( int $user ): ?array {
		$state = static::state();

		if ( ! $state || static::is_running( $state ) || ! empty( $state['shown'] ) || (int) $state['user'] !== $user ) {
			return null;
		}

		$state['shown'] = true;
		self::save( $state );

		return $state;
	}

	/**
	 * Share of the items done, 0 to 100; null while there is no count of
	 * items (a job that works in stages), so the bar shows no value.
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function percent( array $state ): ?int {
		$total = (int) ( $state['total'] ?? 0 );

		if ( $total > 0 ) {
			return (int) floor( 100 * (int) $state['done'] / $total );
		}

		return static::is_running( $state ) ? null : 100;
	}

	/**
	 * Removes the job's state, lock and booked tick.
	 */
	public static function clear(): void {
		delete_option( self::option() );
		Lock::release( self::lock() );
		wp_clear_scheduled_hook( static::hook() );
	}

	/**
	 * One batch, with the bookkeeping around it.
	 *
	 * The batch is saved as an attempt before it runs. If the request dies
	 * during it, the next step finds the same attempt and counts a try; after
	 * MAX_TRIES the batch is skipped, so one bad item can't hold the job.
	 *
	 * @param array<string, mixed> $state State.
	 * @return array<string, mixed>
	 */
	private static function work_batch( array $state ): array {
		$state['updated'] = time();
		$pending          = self::ids( (array) $state['pending'] );

		if ( ! $pending ) {
			static::finish( $state );
			$state['stage'] = 'done';
			$state['ended'] = time();

			return $state;
		}

		$batch   = self::ids( static::batch( $pending ) );
		$batch   = $batch ? $batch : array( $pending[0] );
		$attempt = (array) $state['attempt'];
		$tries   = ( $attempt['items'] ?? null ) === $batch ? (int) $attempt['tries'] + 1 : 1;

		if ( $tries > self::MAX_TRIES ) {
			static::skipped( $batch, Kit::instance()->strings()->get( 'job_skipped' ), $state );
		} else {
			$state['attempt'] = array(
				'items' => $batch,
				'tries' => $tries,
			);
			self::save( $state );

			try {
				static::work( $batch, $state );
			} catch ( \Throwable $error ) {
				static::skipped( $batch, $error->getMessage(), $state );
			}
		}

		$done  = array_map( 'strval', $batch );
		$keep  = array_map( 'strval', (array) ( $state['again'] ?? array() ) );
		$again = array_values( array_filter( $batch, static fn( $item ): bool => in_array( (string) $item, $keep, true ) ) );
		unset( $state['again'] );

		$state['pending'] = array_values( array_merge( $again, array_filter( $pending, static fn( $item ): bool => ! in_array( (string) $item, $done, true ) ) ) );
		$state['done']    = (int) $state['total'] - count( $state['pending'] );
		$state['attempt'] = array();

		if ( ! $state['pending'] ) {
			static::finish( $state );
			$state['stage'] = 'done';
			$state['ended'] = time();
		}

		return $state;
	}

	/**
	 * Item ids, once each, in order: ints stay ints, anything else becomes a string.
	 *
	 * @param array<int|string, mixed> $items Items.
	 * @return list<int|string>
	 */
	private static function ids( array $items ): array {
		$ids = array();
		foreach ( $items as $item ) {
			if ( is_int( $item ) || ( is_string( $item ) && '' !== $item ) ) {
				$ids[ ( is_int( $item ) ? 'i' : 's' ) . $item ] = $item;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Books the next WP-Cron tick, unless one is already booked.
	 */
	private static function book_cron(): void {
		if ( ! wp_next_scheduled( static::hook() ) ) {
			wp_schedule_single_event( time() + self::CRON_GAP, static::hook() );
		}
	}

	/**
	 * Name of the state option.
	 */
	private static function option(): string {
		return Kit::instance()->name( 'job_' . static::id() );
	}

	/**
	 * Name of the lock row.
	 */
	private static function lock(): string {
		return Kit::instance()->name( 'job_lock_' . static::id() );
	}

	/**
	 * Saves the state.
	 *
	 * @param array<string, mixed> $state State.
	 */
	private static function save( array $state ): void {
		update_option( self::option(), $state, false );
	}
}
