<?php
/**
 * Gallery: background jobs (one with a count, one in stages) and a log screen.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Card;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Cluster;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\ProgressBar;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Stack;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Kit;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Screen\CustomScreen;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Screen\LogScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Option that makes the sample job fail on some items.
 */
const JOB_DEMO_FAIL = 'bwc_gallery_job_fail';

/**
 * Registers the jobs, their actions and the two screens. Runs at init.
 *
 * @param Kit $kit The kit.
 * @return array{0: CustomScreen, 1: LogScreen}
 */
function register_jobs_demo( Kit $kit ): array {
	$kit->jobs()->register( Demo_Check_Job::class );
	$kit->jobs()->register( Demo_Fetch_Job::class );

	$actions = $kit->actions();
	$actions->register(
		'demo_job_start',
		static function ( array $input ): ?array {
			update_option( JOB_DEMO_FAIL, $input['fail'] ? 1 : 0, false );
			( 'fetch' === $input['job'] ? Demo_Fetch_Job::class : Demo_Check_Job::class )::start( null, get_current_user_id() );
			return null;
		},
		array(
			'input' => array(
				'job'  => 'key',
				'fail' => 'bool',
			),
		)
	);
	$actions->register(
		'demo_job_retry',
		static function (): ?array {
			update_option( JOB_DEMO_FAIL, 0, false );
			$state = Demo_Check_Job::state();
			Demo_Check_Job::start( (array) ( $state['failed'] ?? array() ), get_current_user_id() );
			return null;
		}
	);

	return array(
		$kit->screen(
			array(
				'id'     => 'jobs',
				'label'  => __( 'Jobs', 'bwc-gallery' ),
				'render' => __NAMESPACE__ . '\\jobs_demo_render',
			)
		),
		$kit->log_screen(
			array(
				'id'      => 'log',
				'label'   => __( 'Log', 'bwc-gallery' ),
				'title'   => __( 'Activity', 'bwc-gallery' ),
				'intro'   => __( 'What the daily runs did, grouped by brand. Sample data.', 'bwc-gallery' ),
				'days'    => 30,
				'totals'  => __NAMESPACE__ . '\\log_demo_totals',
				'runs'    => __NAMESPACE__ . '\\log_demo_runs',
				'run'     => __NAMESPACE__ . '\\log_demo_run',
				'kinds'   => array(
					'added'    => array(
						'label'  => __( 'Added', 'bwc-gallery' ),
						'tone'   => 'success',
						'filter' => 'offers',
					),
					'updated'  => array(
						'label'  => __( 'Re-checked', 'bwc-gallery' ),
						'tone'   => 'info',
						'filter' => 'offers',
					),
					'expired'  => array(
						'label'  => __( 'Expired', 'bwc-gallery' ),
						'tone'   => 'neutral',
						'filter' => 'offers',
					),
					'rejected' => array(
						'label'  => __( 'Not added', 'bwc-gallery' ),
						'tone'   => 'danger',
						'filter' => 'notes',
					),
					'brand'    => array(
						'label'  => __( 'New brand', 'bwc-gallery' ),
						'tone'   => 'purple',
						'filter' => 'brands',
					),
				),
				'filters' => array(
					'all'    => __( 'All', 'bwc-gallery' ),
					'offers' => __( 'Offers', 'bwc-gallery' ),
					'brands' => __( 'Brands', 'bwc-gallery' ),
					'notes'  => __( 'Not added', 'bwc-gallery' ),
				),
			)
		),
	);
}

/**
 * The Jobs screen: each job's card while it runs, its result once it ends,
 * and buttons to start one.
 */
function jobs_demo_render(): string {
	$kit  = Kit::instance();
	$jobs = $kit->jobs();
	$html = $jobs->result_notice(
		Demo_Check_Job::class,
		array(
			'action' => 'demo_job_retry',
			/* translators: %d: number of links. */
			'label'  => __( 'Check the %d that failed again', 'bwc-gallery' ),
		)
	) . $jobs->result_notice( Demo_Fetch_Job::class );

	$cards = $jobs->card( Demo_Check_Job::class, __( 'Checking sample links', 'bwc-gallery' ) )
		. $jobs->card( Demo_Fetch_Job::class, __( 'Reading the sample feed', 'bwc-gallery' ) );

	$buttons = Cluster::markup(
		array(
			'items_html' => array(
				$kit->actions()->button(
					'demo_job_start',
					__( 'Check 40 sample links', 'bwc-gallery' ),
					array(
						'input'   => array( 'job' => 'check' ),
						'variant' => 'primary',
					)
				),
				$kit->actions()->button(
					'demo_job_start',
					__( 'Check them, with two that fail', 'bwc-gallery' ),
					array(
						'input'   => array(
							'job'  => 'check',
							'fail' => '1',
						),
						'variant' => 'outline',
					)
				),
				$kit->actions()->button(
					'demo_job_start',
					__( 'Read a feed in stages', 'bwc-gallery' ),
					array(
						'input'   => array( 'job' => 'fetch' ),
						'variant' => 'outline',
					)
				),
			),
		)
	);

	$bars = '';
	foreach ( array( array( 64, 'accent' ), array( 100, 'success' ), array( 35, 'warning' ), array( 12, 'danger' ) ) as [ $value, $tone ] ) {
		$bars .= '<p class="bwc-gallery-row">' . ProgressBar::markup(
			array(
				'value' => $value,
				'label' => __( 'Course progress', 'bwc-gallery' ),
				'tone'  => $tone,
			)
		) . '</p>';
	}

	return Stack::markup(
		array(
			'items_html' => array_values(
				array_filter(
					array(
						$html,
						$cards,
						Card::markup(
							array(
								'title'        => __( 'Background jobs', 'bwc-gallery' ),
								'subtitle'     => __( 'Work through a list in short steps, so no request runs long. This page moves a job on while it\'s open; close it and WP-Cron carries on. Stop keeps what was done.', 'bwc-gallery' ),
								'content_html' => $buttons,
								'size'         => 'large',
							)
						),
						Card::markup(
							array(
								'title'        => __( 'Progress bars', 'bwc-gallery' ),
								'content_html' => $bars,
							)
						),
					)
				)
			),
		)
	);
}

/**
 * Sample tiles for the log: the last 30 days.
 *
 * @return list<array<string, mixed>>
 */
function log_demo_totals(): array {
	return array(
		array(
			'label' => __( 'Offers added', 'bwc-gallery' ),
			'value' => '412',
		),
		array(
			'label' => __( 'Re-checked', 'bwc-gallery' ),
			'value' => '1,087',
		),
		array(
			'label' => __( 'Expired', 'bwc-gallery' ),
			'value' => '96',
		),
		array(
			'label' => __( 'New brands', 'bwc-gallery' ),
			'value' => '14',
		),
	);
}

/**
 * Sample runs: a daily run each of the last five days, and a check run.
 *
 * @return list<array<string, mixed>>
 */
function log_demo_runs(): array {
	$runs = array();
	for ( $back = 0; $back < 5; $back++ ) {
		$day   = wp_date( 'Y-m-d', time() - $back * DAY_IN_SECONDS );
		$start = (int) strtotime( $day . ' 11:00:00' );

		$runs[] = array(
			'id'      => 'daily-' . $day,
			'day'     => $day,
			'title'   => __( 'Daily run', 'bwc-gallery' ),
			'started' => $start,
			/* translators: 1: offers added, 2: offers re-checked. */
			'line'    => sprintf( __( '%1$d added · %2$d re-checked', 'bwc-gallery' ), 9 + $back * 3, 30 - $back ),
			'problem' => 1 === $back ? __( '1 problem', 'bwc-gallery' ) : null,
		);
		if ( 2 === $back ) {
			$runs[] = array(
				'id'      => 'check-' . $day,
				'day'     => $day,
				'title'   => __( 'Link check', 'bwc-gallery' ),
				'started' => $start - 4 * HOUR_IN_SECONDS,
				'line'    => __( '71 checked · 2 broken', 'bwc-gallery' ),
			);
		}
	}

	return $runs;
}

/**
 * One sample run's card.
 *
 * @param string      $id  Run id.
 * @param string|null $day Its day.
 * @return array<string, mixed>|null
 */
function log_demo_run( string $id, ?string $day ): ?array {
	foreach ( log_demo_runs() as $run ) {
		if ( $run['id'] !== $id || ( null !== $day && $run['day'] !== $day ) ) {
			continue;
		}

		$start  = (int) $run['started'];
		$brands = array( 'Northwind', 'Contoso', 'Fabrikam', 'Wingtip', 'Proseware', 'Relecloud' );
		$groups = array();

		foreach ( $brands as $index => $brand ) {
			$entries = array();
			$count   = 2 + ( strlen( $brand ) + strlen( $id ) ) % 13;
			for ( $n = 0; $n < $count; $n++ ) {
				$kind  = array( 'added', 'updated', 'updated', 'expired', 'rejected' )[ ( $n + $index ) % 5 ];
				$entry = array(
					'time' => $start + ( $index * 60 + $n * 7 ) * 6,
					'kind' => $kind,
					/* translators: 1: brand, 2: offer number. */
					'text' => sprintf( __( '%1$s: offer %2$d', 'bwc-gallery' ), $brand, $n + 1 ),
					'link' => array(
						'label' => __( 'Open', 'bwc-gallery' ),
						'url'   => admin_url( 'admin.php?page=bwc-gallery-settings-list' ),
					),
				);
				if ( 'rejected' === $kind ) {
					$entry['reasons'] = array( __( 'The code has a space in it.', 'bwc-gallery' ), __( 'The end date has passed.', 'bwc-gallery' ) );
					unset( $entry['link'] );
				}
				$entries[] = $entry;
			}
			if ( 0 === $index ) {
				array_unshift(
					$entries,
					array(
						'time' => $start,
						'kind' => 'brand',
						'text' => __( 'New brand: Northwind (draft)', 'bwc-gallery' ),
					)
				);
			}

			$groups[] = array(
				'title'   => $brand,
				'tags'    => array(
					array(
						/* translators: %d: entries. */
						'text' => sprintf( _n( '%d entry', '%d entries', count( $entries ), 'bwc-gallery' ), count( $entries ) ),
						'tone' => 'neutral',
					),
				),
				'entries' => $entries,
			);
		}

		$problem = ! empty( $run['problem'] );

		return array(
			'title'  => (string) $run['title'],
			/* translators: 1: date, 2: start time. */
			'when'   => sprintf( __( '%1$s, started at %2$s', 'bwc-gallery' ), wp_date( (string) get_option( 'date_format' ), $start ), wp_date( (string) get_option( 'time_format' ), $start ) ),
			'status' => $problem ? array(
				'tone' => 'warning',
				'text' => __( 'Finished with a problem', 'bwc-gallery' ),
			) : array(
				'tone' => 'success',
				'text' => __( 'Finished', 'bwc-gallery' ),
			),
			'tiles'  => array(
				array(
					'label' => __( 'Added', 'bwc-gallery' ),
					'value' => '12',
					'soft'  => true,
				),
				array(
					'label' => __( 'Re-checked', 'bwc-gallery' ),
					'value' => '29',
					'soft'  => true,
				),
				array(
					'label' => __( 'Not added', 'bwc-gallery' ),
					'value' => '5',
					'soft'  => true,
				),
			),
			'notes'  => $problem ? array( __( 'One network didn\'t answer, so its offers are a day old.', 'bwc-gallery' ) ) : array(),
			'groups' => $groups,
		);
	}

	return null;
}
