<?php
/**
 * Background jobs, job progress and the log screen. Loaded by run.php.
 * Writes only the test job's state, lock and tick, each removed again.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Component\JobProgress;
use BlissWebConcept\AdminKit\Component\ProgressBar;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Service\Job;

defined( 'ABSPATH' ) || exit;

/**
 * Resets the test job on a fresh test kit.
 *
 * @param list<int|string> $items Items.
 */
function bwc_test_job( array $items ): void {
	bwc_test_kit();
	wp_set_current_user( 1 );
	BWC_Test_Job::reset( $items );
}

bwc_test(
	'jobs: a job moves one batch at a time, saves its place after each, and finishes once',
	static function (): void {
		bwc_test_job( array( 1, 2, 3, 4, 5 ) );
		BWC_Test_Job::$size = 2;

		$state = BWC_Test_Job::start( null, 7 );
		bwc_same( 'running', $state['stage'], 'starts running' );
		bwc_same( 5, $state['total'], 'counts its items' );
		bwc_same( 'bwc_test_bwc_job_test', BWC_Test_Job::hook(), 'names belong to the plugin\'s kit' );
		bwc_same( true, (bool) wp_next_scheduled( BWC_Test_Job::hook() ), 'a cron tick is booked as a fallback' );
		bwc_same( $state, BWC_Test_Job::start(), 'starting again while it runs returns the running job' );

		$state = BWC_Test_Job::step( 0 );
		bwc_same( array( array( 1, 2 ) ), BWC_Test_Job::$batches, 'one batch per 0-second step' );
		bwc_same( 2, BWC_Test_Job::state()['done'], 'place saved' );
		bwc_same( '2/5', BWC_Test_Job::progress( $state ), 'progress' );
		bwc_same( 40, BWC_Test_Job::percent( $state ), 'percent' );

		BWC_Test_Job::step( 0 );
		$state = BWC_Test_Job::step( 0 );
		bwc_same( array( array( 1, 2 ), array( 3, 4 ), array( 5 ) ), BWC_Test_Job::$batches, 'every item once' );
		bwc_same( 'done', $state['stage'], 'done after the last batch, without another step' );
		bwc_same( 1, BWC_Test_Job::$finished, 'finish ran once' );
		bwc_same( 'finished', $state['message'], 'finish can change the state' );
		bwc_same( array( 2, 4 ), $state['failed'], 'failed items kept' );

		BWC_Test_Job::step( 10 );
		bwc_same( 3, count( BWC_Test_Job::$batches ), 'a finished job does nothing' );
		BWC_Test_Job::clear();
	}
);


bwc_test(
	'jobs: an item that takes several batches stays first in line until it is done',
	static function (): void {
		bwc_test_job( array( 'feed-a', 'feed-b' ) );
		BWC_Test_Job::$again = array( 'feed-a' => 2 );

		BWC_Test_Job::start();
		$state = BWC_Test_Job::step( 0 );
		bwc_same( array( 'feed-a', 'feed-b' ), $state['pending'], 'kept first, nothing else started' );
		bwc_same( 0, $state['done'], 'not counted as done' );
		bwc_same( false, isset( $state['again'] ), 'the list is read once' );

		BWC_Test_Job::step( 0 );
		$state = BWC_Test_Job::step( 0 );
		bwc_same( array( 'feed-b' ), $state['pending'], 'done after its last batch' );
		bwc_same( 1, $state['done'], 'counted once' );

		BWC_Test_Job::step( 30 );
		bwc_same( array( array( 'feed-a' ), array( 'feed-a' ), array( 'feed-a' ), array( 'feed-b' ) ), BWC_Test_Job::$batches, 'the other follows' );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'jobs: a job can hold its lock longer than a minute when its batches are long',
	static function (): void {
		global $wpdb;

		bwc_test_job( array( 1 ) );
		BWC_Test_Job::$lock = 120;
		BWC_Test_Job::start();

		$lock = Kit::instance()->name( 'job_lock_test' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- simulates another request that took the lock 90 seconds ago.
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $lock, (string) ( time() - 90 ) ) );

		BWC_Test_Job::step( 0 );
		bwc_same( array(), BWC_Test_Job::$batches, 'still held after 90 seconds' );

		BWC_Test_Job::$lock = 0;
		BWC_Test_Job::step( 0 );
		bwc_same( array( array( 1 ) ), BWC_Test_Job::$batches, 'the runner\'s minute had passed' );
		BWC_Test_Job::clear();
	}
);
bwc_test(
	'jobs: a step stops before its time budget runs out, and the next step carries on',
	static function (): void {
		bwc_test_job( range( 1, 20 ) );
		BWC_Test_Job::$sleep = 300000;

		BWC_Test_Job::start();
		$began = microtime( true );
		$state = BWC_Test_Job::step( 1 );
		$took  = microtime( true ) - $began;

		bwc_same( true, BWC_Test_Job::is_running( $state ), 'not finished in one short step' );
		bwc_same( true, $state['done'] >= 2 && $state['done'] <= 4, 'about a second of work: ' . $state['done'] . ' items' );
		bwc_same( true, $took < 1.5, sprintf( 'stopped near the budget, took %.2fs', $took ) );

		BWC_Test_Job::$sleep = 0;
		BWC_Test_Job::step( 30 );
		bwc_same( 'done', BWC_Test_Job::state()['stage'], 'the next step finished it' );
		bwc_same( range( 1, 20 ), array_merge( ...BWC_Test_Job::$batches ), 'nothing done twice or missed' );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'jobs: only one request works on a job at a time, and a lock left by a dead request is taken over',
	static function (): void {
		global $wpdb;

		bwc_test_job( array( 1, 2 ) );
		BWC_Test_Job::start();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the lock row, as a running step leaves it.
		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => 'bwc_test_bwc_job_lock_test',
				'option_value' => (string) time(),
				'autoload'     => 'off',
			)
		);
		BWC_Test_Job::step( 0 );
		bwc_same( array(), BWC_Test_Job::$batches, 'held lock: no work' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- age the lock.
		$wpdb->update( $wpdb->options, array( 'option_value' => (string) ( time() - 600 ) ), array( 'option_name' => 'bwc_test_bwc_job_lock_test' ) );
		wp_cache_flush();
		BWC_Test_Job::step( 0 );
		bwc_same( array( array( 1 ) ), BWC_Test_Job::$batches, 'old lock taken over' );
		bwc_same( false, get_option( 'bwc_test_bwc_job_lock_test' ), 'lock released after the step' );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'jobs: a batch that keeps stopping the request is skipped, and one that throws is skipped at once',
	static function (): void {
		bwc_test_job( array( 1, 2, 3 ) );
		BWC_Test_Job::start();

		// As if three steps died while working on item 1.
		$state            = BWC_Test_Job::state();
		$state['attempt'] = array(
			'items' => array( 1 ),
			'tries' => Job::MAX_TRIES,
		);
		update_option( 'bwc_test_bwc_job_test', $state, false );

		$state = BWC_Test_Job::step( 0 );
		bwc_same( array(), BWC_Test_Job::$batches, 'not tried a fourth time' );
		bwc_same( 1, $state['counts']['skipped'], 'counted as skipped' );
		bwc_same( array( 1 ), $state['failed'], 'offered for a run again' );
		bwc_same( array( 2, 3 ), $state['pending'], 'the job moved on' );

		BWC_Test_Job::$throw = array( 2 );
		$state               = BWC_Test_Job::step( 0 );
		bwc_same( 2, $state['counts']['skipped'], 'an error skips the item' );
		bwc_same( 'item 2 broke', $state['message'], 'and says why' );
		bwc_same( true, BWC_Test_Job::is_running( $state ), 'without ending the job' );

		$state = BWC_Test_Job::step( 0 );
		bwc_same( 'done', $state['stage'], 'the rest still runs' );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'jobs: Stop keeps what was done, the result shows once to whoever started it, and a run can cover only the failed items',
	static function (): void {
		bwc_test_job( array( 1, 2, 3, 4 ) );

		BWC_Test_Job::start( null, 7 );
		BWC_Test_Job::step( 0 );
		bwc_same( null, BWC_Test_Job::take_result( 7 ), 'no result while running' );

		$state = BWC_Test_Job::cancel();
		bwc_same( 'cancelled', $state['stage'], 'stopped' );
		bwc_same( false, wp_next_scheduled( BWC_Test_Job::hook() ), 'tick cleared' );
		BWC_Test_Job::step( 10 );
		bwc_same( 1, count( BWC_Test_Job::$batches ), 'a stopped job does nothing' );

		bwc_same( null, BWC_Test_Job::take_result( 8 ), 'not shown to someone else' );
		$result = BWC_Test_Job::take_result( 7 );
		bwc_same( 1, $result['done'] ?? null, 'shown with the work done' );
		bwc_same( null, BWC_Test_Job::take_result( 7 ), 'shown once' );

		BWC_Test_Job::start( array( 3, 9 ), 7 );
		bwc_same( array( 3 ), BWC_Test_Job::state()['pending'], 'only the given items that still exist' );
		bwc_same( true, BWC_Test_Job::state()['retry'], 'marked as a second run' );
		BWC_Test_Job::clear();

		BWC_Test_Job::start();
		BWC_Test_Job::step( 30 );
		bwc_same( null, BWC_Test_Job::take_result( 0 ), 'a scheduled run shows no result card' );

		// Stop pressed, and the result shown, while a step is still working.
		BWC_Test_Job::clear();
		BWC_Test_Job::start( null, 7 );
		BWC_Test_Job::$during = static function (): void {
			BWC_Test_Job::cancel();
			BWC_Test_Job::take_result( 7 );
		};
		$state                = BWC_Test_Job::step( 30 );
		bwc_same( 'cancelled', $state['stage'], 'the step keeps the stop' );
		bwc_same( 1, $state['done'], 'and saves the batch it finished' );
		bwc_same( null, BWC_Test_Job::take_result( 7 ), 'the result isn\'t shown a second time' );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'jobs: a job that stopped moving long ago can be started again, and WP-Cron keeps a running job going',
	static function (): void {
		bwc_test_job( array( 1, 2, 3 ) );

		BWC_Test_Job::start();
		$state            = BWC_Test_Job::state();
		$state['updated'] = time() - Job::ABANDON_SECONDS - 1;
		$state['pending'] = array( 3 );
		update_option( 'bwc_test_bwc_job_test', $state, false );

		bwc_same( array( 1, 2, 3 ), BWC_Test_Job::start()['pending'], 'restarted from the beginning' );

		Kit::instance()->jobs()->register( BWC_Test_Job::class );
		wp_clear_scheduled_hook( BWC_Test_Job::hook() );
		do_action( BWC_Test_Job::hook() );
		bwc_same( 'done', BWC_Test_Job::state()['stage'], 'the tick did the work' );
		bwc_same( false, wp_next_scheduled( BWC_Test_Job::hook() ), 'no tick booked once done' );
		bwc_same( BWC_Test_Job::class, Kit::instance()->jobs()->get( 'test' ), 'registered for the screen' );
		remove_all_actions( BWC_Test_Job::hook() );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'jobs: string item ids, no percent while a job in stages runs, the screen\'s step answer and Stop',
	static function (): void {
		bwc_test_job( array( 'page-a', 'page-b', 7, 'page-a', '' ) );
		$kit = Kit::instance();
		$kit->jobs()->register( BWC_Test_Job::class );
		BWC_Test_Job::$stages = true;

		$state = BWC_Test_Job::start( array( 'page-b', '7' ), 1 );
		bwc_same( array( 'page-b', 7 ), $state['pending'], 'string and int ids, once each, filtered by the retry list as text' );
		bwc_same( null, BWC_Test_Job::percent( $state ), 'no count yet: no percent' );

		$answer = $kit->jobs()->step( BWC_Test_Job::class );
		bwc_same(
			array(
				'running' => false,
				'text'    => '2/2',
				'percent' => 100,
				'wait'    => 1000,
			),
			$answer,
			'one screen step finished it (8-second budget), and says so'
		);

		BWC_Test_Job::start( null, 1 );
		$card = $kit->jobs()->card( BWC_Test_Job::class, 'Reading the feed' );
		bwc_same( true, str_contains( $card, '<section class="bwc-job is-running" data-bwc-job="test" aria-busy="true">' ), 'the running card' );
		bwc_same( true, str_contains( $card, '<progress class="bwc-job__bar" max="100" data-bwc-job-bar aria-label="Progress"></progress>' ), 'a bar without a value' );
		bwc_same( true, str_contains( $card, 'value="bwc_test_bwc_job_cancel"' ) && str_contains( $card, '<input type="hidden" name="job" value="test">' ), 'Stop posts the job to the kit\'s action' );

		$kit->actions()->dispatch(
			'job_cancel',
			array(
				'_wpnonce' => wp_create_nonce( 'bwc_test_bwc_job_cancel' ),
				'job'      => 'test',
			)
		);
		bwc_same( 'cancelled', BWC_Test_Job::state()['stage'], 'Stop' );
		bwc_same( '', $kit->jobs()->card( BWC_Test_Job::class, 'Reading the feed' ), 'no card once it ended' );
		$notice = $kit->jobs()->result_notice( BWC_Test_Job::class );
		bwc_same( true, str_contains( $notice, 'bwc-notice--info' ) && str_contains( $notice, 'Stopped. What was done so far is kept.' ), 'the result, for whoever started it' );
		bwc_same( '', $kit->jobs()->result_notice( BWC_Test_Job::class ), 'once' );

		remove_all_actions( BWC_Test_Job::hook() );
		remove_all_actions( 'wp_ajax_bwc_test_bwc_job_step' );
		remove_all_actions( 'admin_post_bwc_test_bwc_job_cancel' );
		BWC_Test_Job::clear();
	}
);

bwc_test(
	'progress: a meter with its number; job cards that ended offer the failed items again',
	static function (): void {
		bwc_test_kit();
		bwc_same(
			'<span class="bwc-bar bwc-bar--warning"><meter class="bwc-bar__meter" min="0" max="100" value="64" aria-label="Course progress">64%</meter><span class="bwc-bar__value">64%</span></span>',
			ProgressBar::markup(
				array(
					'value' => 16,
					'max'   => 25,
					'label' => 'Course progress',
					'tone'  => 'warning',
				)
			),
			'bar'
		);

		Kit::instance()->actions()->register( 'retry_test', '__return_null' );
		$done = JobProgress::markup(
			array(
				'job'   => 'test',
				'title' => 'Checking links',
				'state' => array(
					'stage'  => 'done',
					'text'   => '71 checked',
					'failed' => 3,
				),
				'retry' => array(
					'label'  => 'Check the %d that failed again',
					'action' => 'retry_test',
				),
			)
		);
		bwc_same( true, str_contains( $done, 'class="bwc-job is-done"' ) && ! str_contains( $done, 'data-bwc-job=' ), 'an ended card isn\'t moved on by the script' );
		bwc_same( true, str_contains( $done, 'Check the 3 that failed again' ), 'retry button' );
		remove_all_actions( 'admin_post_bwc_test_bwc_retry_test' );
	}
);

bwc_test(
	'log screen: the run list comes a page at a time from the plugin, keeps the chosen run, and a plain list still works',
	static function (): void {
		bwc_test_kit();
		$today = wp_date( 'Y-m-d' );
		$start = (int) strtotime( $today . ' 11:00:00' );
		$runs  = array();
		for ( $i = 1; $i <= 45; $i++ ) {
			$runs[] = array(
				'id'      => 'r' . $i,
				'day'     => $today,
				'title'   => 'Run ' . $i,
				'started' => $start - $i * 60,
			);
		}

		$asked  = array();
		$screen = Kit::instance()->log_screen(
			array(
				'id'       => 'log-paged',
				'label'    => 'Log',
				'per_page' => 20,
				'runs'     => static function ( int $days, int $offset = 0, int $limit = 0 ) use ( $runs, &$asked ): array {
					$asked[] = array( $offset, $limit );
					return array(
						'runs'  => array_slice( $runs, $offset, $limit ),
						'total' => count( $runs ),
					);
				},
				'run'      => static fn( string $id ): ?array => array( 'title' => 'Run ' . substr( $id, 1 ) ),
			)
		);

		$_GET = array();
		$html = $screen->render();
		bwc_same( array( array( 0, 20 ) ), $asked, 'asks for the first page only' );
		bwc_same( 20, substr_count( $html, '<a class="bwc-run' ), 'twenty runs on it' );
		bwc_same( true, (bool) preg_match( '/href="[^"]*runs_page=2[^"]*"[^>]*>.*?Older/s', $html ), 'Older goes to the next page' );

		$asked = array();
		$_GET  = array(
			'run'       => 'r3',
			'day'       => $today,
			'runs_page' => '3',
		);
		$html  = $screen->render();
		bwc_same( array( array( 40, 20 ) ), $asked, 'asks for the third page' );
		bwc_same( 5, substr_count( $html, '<a class="bwc-run' ), 'the last five runs' );
		bwc_same( true, str_contains( $html, 'Run 3' ), 'an older run opened from a link still shows' );
		bwc_same( true, (bool) preg_match( '/href="[^"]*run=r3[^"]*runs_page=2/', $html ) || (bool) preg_match( '/href="[^"]*runs_page=2[^"]*run=r3/', $html ), 'Newer keeps the chosen run' );
		bwc_same( true, (bool) preg_match( '/<a class="bwc-run" href="[^"]*runs_page=3/', $html ), 'a run in the list keeps the page' );

		$_GET = array( 'runs_page' => '9' );
		$screen->render();
		bwc_same( array( 160, 20 ), $asked[ count( $asked ) - 2 ] ?? null, 'past the last page, asked once' );
		bwc_same( array( 40, 20 ), end( $asked ), 'then the last page' );

		$plain = Kit::instance()->log_screen(
			array(
				'id'       => 'log-plain',
				'label'    => 'Log',
				'per_page' => 20,
				'runs'     => static fn(): array => $runs,
				'run'      => static fn(): ?array => null,
			)
		);
		$_GET  = array( 'runs_page' => '2' );
		bwc_same( 20, substr_count( $plain->render(), '<a class="bwc-run' ), 'a plain list is paged by the kit' );
		$_GET = array();
	}
);

bwc_test(
	'log screen: runs by day, the chosen run with tiles, notes, chips, groups and entries',
	static function (): void {
		bwc_test_kit();
		$today  = wp_date( 'Y-m-d' );
		$start  = (int) strtotime( $today . ' 11:00:00' );
		$screen = Kit::instance()->log_screen(
			array(
				'id'      => 'activity',
				'label'   => 'Activity',
				'runs'    => static fn(): array => array(
					array(
						'id'      => 'r2',
						'day'     => $today,
						'title'   => 'Daily run',
						'started' => $start,
						'line'    => '3 added',
						'problem' => '1 problem',
					),
					array(
						'id'    => 'r1',
						'day'   => '2026-01-05',
						'title' => 'Daily run',
					),
				),
				'run'     => static fn( string $id ): ?array => 'r2' === $id ? array(
					'title'       => 'Daily run',
					'when'        => 'Today at 11:00',
					'status'      => array(
						'tone' => 'success',
						'text' => 'Finished',
					),
					'notes'       => array( 'One network was slow.' ),
					'notes_title' => 'The agent\'s notes',
					'groups'      => array(
						array(
							'title'   => 'Northwind',
							'entries' => array(
								array(
									'time' => $start,
									'kind' => 'added',
									'text' => 'Offer one',
								),
								array(
									'time'    => $start,
									'kind'    => 'rejected',
									'text'    => 'Offer two',
									'reasons' => array( 'Past its end date.' ),
								),
							),
						),
					),
				) : null,
				'kinds'   => array(
					'added'    => array(
						'label'  => 'Added',
						'tone'   => 'success',
						'filter' => 'offers',
					),
					'rejected' => array(
						'label'  => 'Not added',
						'tone'   => 'danger',
						'filter' => 'notes',
					),
				),
				'filters' => array(
					'all'    => 'All',
					'offers' => 'Offers',
					'brands' => 'Brands',
					'notes'  => 'Not added',
				),
			)
		);

		$_GET = array();
		$html = $screen->render();
		bwc_same( true, str_contains( $html, '<h3 class="bwc-runs__heading">Today · ' ), 'today\'s heading' );
		bwc_same( true, (bool) preg_match( '/<a class="bwc-run is-current" href="[^"]*run=r2[^"]*" aria-current="page"/', $html ), 'the newest run is chosen' );
		bwc_same( true, str_contains( $html, '1 problem' ) && str_contains( $html, 'One network was slow.' ), 'problem line and notes' );
		bwc_same( true, str_contains( $html, 'data-bwc-filter="offers"' ) && str_contains( $html, 'data-bwc-filter="notes"' ) && ! str_contains( $html, 'data-bwc-filter="brands"' ), 'chips only for kinds this run has' );
		bwc_same( true, str_contains( $html, 'data-bwc-kind="offers"' ) && str_contains( $html, 'Past its end date.' ), 'entries carry their filter, reasons listed' );
		bwc_same( true, str_contains( $html, 'data-bwc-filter-group' ) && str_contains( $html, ' open' ), 'a small group is open' );

		bwc_same( true, str_contains( $html, '<h4 class="bwc-log__notes-title">The agent&#039;s notes</h4>' ), 'notes under their title' );
		bwc_same( true, (bool) preg_match( '/data-bwc-filter="all"[^>]*>All <span class="bwc-chip__count">(\d+)<\/span>/', $html, $all ), 'chips count their entries' );

		$_GET = array( 'run' => 'r1' );
		bwc_same( true, str_contains( $screen->render(), 'Pick a run to see what it did.' ), 'a run the plugin can\'t show' );
		$_GET = array();

		$quiet = Kit::instance()->log_screen(
			array(
				'id'        => 'log-quiet',
				'label'     => 'Log',
				'run_empty' => 'This run made no changes.',
				'runs'      => static fn(): array => array(
					array(
						'id'      => 'q1',
						'day'     => $today,
						'title'   => 'Quiet run',
						'started' => $start,
					),
				),
				'run'       => static fn( string $id ): ?array => 'q1' === $id ? array( 'title' => 'Quiet run' ) : null,
			)
		);
		bwc_same( true, str_contains( $quiet->render(), 'This run made no changes.' ), 'a run with no entries says so' );
	}
);
