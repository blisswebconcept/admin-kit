<?php
/**
 * Gallery: a report screen (charts, tiles, tables, notes) with a member
 * detail view, over sample data kept in the Cache.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Chart;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Kit;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Screen\ReportScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Sample members, the same every time, read through the Cache so the report
 * can say how old its data is.
 *
 * @return list<array<string, mixed>>
 */
function report_demo_members(): array {
	$data = Kit::instance()->cache()->remember(
		'demo_members',
		1,
		10 * MINUTE_IN_SECONDS,
		static function (): array {
			$names    = array( 'Ama Mensah', 'Ben Okafor', 'Chloe Martin', 'Diego Ruiz', 'Esi Owusu', 'Femi Adeyemi', 'Grace Lee', 'Hiro Tanaka', 'Ines Silva', 'Jon Park', 'Kofi Boateng', 'Lena Weber', 'Maya Patel', 'Nia Johnson', 'Omar Haddad', 'Priya Nair', 'Quinn Doyle', 'Rosa Gomez', 'Sam Carter', 'Tara Singh', 'Uche Eze', 'Vera Novak', 'Will Brown', 'Yaw Asante' );
			$coupons  = array( 'velo50', 'launch', 'friend', 'none' );
			$segments = array( 'active', 'stuck', 'new', 'quiet' );
			$members  = array();

			foreach ( $names as $i => $name ) {
				$weeks = array();
				for ( $w = 0; $w < 12; $w++ ) {
					$weeks[] = ( ( $i + 3 ) * ( $w + 2 ) ) % 9;
				}
				$members[] = array(
					'id'        => $i + 1,
					'name'      => $name,
					'email'     => strtolower( str_replace( ' ', '.', $name ) ) . '@example.com',
					'coupon'    => $coupons[ $i % 4 ],
					'segment'   => $segments[ ( $i * 3 ) % 4 ],
					'progress'  => ( $i * 37 ) % 101,
					'weeks'     => $weeks,
					'last_seen' => time() - ( ( $i * 7919 ) % ( 20 * DAY_IN_SECONDS ) ),
					'team'      => 0 === $i % 7,
				);
			}

			return $members;
		}
	);

	return is_array( $data ) ? $data : array();
}

/**
 * Registers the report screen. Runs at init.
 *
 * @param Kit $kit The kit.
 */
function register_report_demo( Kit $kit ): ReportScreen {
	$member = $kit->detail_screen(
		array(
			'param'   => 'member',
			'load'    => static function ( int|string $id ): ?array {
				foreach ( report_demo_members() as $row ) {
					if ( (int) $row['id'] === (int) $id ) {
						return $row;
					}
				}
				return null;
			},
			'title'   => static fn( array $row ): string => (string) $row['name'],
			'back'    => __( 'All members', 'bwc-gallery' ),
			'missing' => __( 'That member no longer exists.', 'bwc-gallery' ),
			'blocks'  => __NAMESPACE__ . '\\report_demo_member_blocks',
		)
	);

	$coupons = array(
		'velo50' => 'VELO50',
		'launch' => 'LAUNCH',
		'friend' => 'FRIEND',
		'none'   => __( 'No coupon', 'bwc-gallery' ),
	);

	return $kit->report_screen(
		array(
			'id'             => 'report',
			'label'          => __( 'Report', 'bwc-gallery' ),
			'title'          => __( 'Insights', 'bwc-gallery' ),
			'intro'          => __( 'How members are getting on. Sample data, kept for 10 minutes like a real report.', 'bwc-gallery' ),
			'persist'        => array( 'team' ),
			'data'           => array( 'demo_members' ),
			'header_actions' => static function ( array $view, ReportScreen $screen ): string {
				$team = ! empty( $view['persist']['team'] );
				return '<a class="bwc-button bwc-button--outline" href="' . esc_url( $screen->link( $view, array( 'team' => $team ? null : '1' ) ) ) . '"><span class="bwc-button__label">'
					. esc_html( $team ? __( 'Hide team accounts', 'bwc-gallery' ) : __( 'Show team accounts', 'bwc-gallery' ) ) . '</span></a>';
			},
			'tabs'           => array(
				'overview' => array(
					'label'  => __( 'Overview', 'bwc-gallery' ),
					'blocks' => __NAMESPACE__ . '\\report_demo_overview',
				),
				'members'  => array(
					'label'   => __( 'Members', 'bwc-gallery' ),
					'filters' => array(
						array(
							'id'       => 'segment',
							'label'    => __( 'Segment', 'bwc-gallery' ),
							'type'     => 'enum',
							'elements' => array(
								'active' => __( 'Active', 'bwc-gallery' ),
								'stuck'  => __( 'Stuck', 'bwc-gallery' ),
								'new'    => __( 'New', 'bwc-gallery' ),
								'quiet'  => __( 'Quiet', 'bwc-gallery' ),
							),
							'filterBy' => array(
								'operators' => array( 'is' ),
								'control'   => 'chips',
							),
						),
						array(
							'id'       => 'coupon',
							'label'    => __( 'Coupon', 'bwc-gallery' ),
							'type'     => 'enum',
							'elements' => $coupons,
							'filterBy' => array(
								'operators' => array( 'is' ),
								'control'   => 'select',
							),
						),
					),
					'blocks'  => __NAMESPACE__ . '\\report_demo_members_blocks',
					'export'  => array(
						'fields'   => array(
							array(
								'id'    => 'name',
								'label' => __( 'Member', 'bwc-gallery' ),
							),
							array(
								'id'    => 'email',
								'label' => __( 'Email', 'bwc-gallery' ),
							),
							array(
								'id'    => 'coupon',
								'label' => __( 'Coupon', 'bwc-gallery' ),
							),
							array(
								'id'    => 'progress',
								'label' => __( 'Progress (%)', 'bwc-gallery' ),
							),
						),
						'rows'     => static fn( array $view ): array => report_demo_filter( $view ),
						'filename' => static fn(): string => 'gallery-members-' . wp_date( 'Y-m-d' ),
					),
					'detail'  => $member,
				),
			),
		)
	);
}

/**
 * The members a view asks for: team accounts only when shown, then the filters.
 *
 * @param array<string, mixed> $view The view.
 * @return list<array<string, mixed>>
 */
function report_demo_filter( array $view ): array {
	$team = ! empty( $view['persist']['team'] );

	return array_values(
		array_filter(
			report_demo_members(),
			static function ( array $row ) use ( $view, $team ): bool {
				if ( $row['team'] && ! $team ) {
					return false;
				}
				foreach ( $view['filters'] as $filter ) {
					if ( (string) $row[ $filter['field'] ] !== (string) $filter['value'] ) {
						return false;
					}
				}
				return true;
			}
		)
	);
}

/**
 * The Overview tab.
 *
 * @param array<string, mixed> $view The view.
 * @return list<array<string, mixed>>
 */
function report_demo_overview( array $view ): array {
	$members = report_demo_filter( $view );
	$days    = array();
	for ( $d = 29; $d >= 0; $d-- ) {
		$time   = time() - $d * DAY_IN_SECONDS;
		$days[] = array(
			'label'     => 0 === $d ? __( 'Today', 'bwc-gallery' ) : wp_date( 'j M', $time ),
			'value'     => 6 + ( ( 30 - $d ) * 7 ) % 13 + intdiv( 30 - $d, 3 ),
			'estimated' => $d > 22,
		);
	}

	$weeks = array();
	for ( $w = 7; $w >= 0; $w-- ) {
		$weeks[] = array(
			'label' => wp_date( 'j M', time() - $w * WEEK_IN_SECONDS ),
			'parts' => array(
				array(
					'series' => 1,
					'value'  => ( $w * 5 + 3 ) % 9,
				),
				array(
					'series' => 2,
					'value'  => ( $w * 3 + 1 ) % 6,
				),
				array(
					'series' => 3,
					'value'  => ( $w * 2 ) % 4,
				),
			),
		);
	}

	$today = end( $days );
	$most  = max( array_column( $days, 'value' ) );

	return array(
		array(
			'type'  => 'tiles',
			'items' => array(
				array(
					'label' => __( 'Members', 'bwc-gallery' ),
					'value' => number_format_i18n( count( $members ) ),
				),
				array(
					'label' => __( 'Active this week', 'bwc-gallery' ),
					'value' => '18',
					'note'  => __( '3 more than last week', 'bwc-gallery' ),
					'tone'  => 'success',
				),
				array(
					'label' => __( 'Stuck', 'bwc-gallery' ),
					'value' => '4',
					'tone'  => 'warning',
				),
				array(
					'label' => __( 'Finished the course', 'bwc-gallery' ),
					'value' => '7',
				),
			),
		),
		array(
			'type'          => 'chart',
			'kind'          => 'area',
			'title'         => __( 'Members active each day', 'bwc-gallery' ),
			'description'   => __( 'The last 30 days. Hover a day for the count.', 'bwc-gallery' ),
			/* translators: 1: members active today, 2: most in a day. */
			'summary'       => sprintf( __( 'Members active each day over the last 30 days: %1$d today, at most %2$d in a day.', 'bwc-gallery' ), $today['value'], $most ),
			'legend'        => array(
				array( 'label' => __( 'Recorded', 'bwc-gallery' ) ),
				array(
					'label' => __( 'Estimated', 'bwc-gallery' ),
					'line'  => 'dashed',
				),
			),
			'points'        => $days,
			'label_heading' => __( 'Day', 'bwc-gallery' ),
			'value_heading' => __( 'Members', 'bwc-gallery' ),
		),
		array(
			'type'          => 'chart',
			'kind'          => 'bars',
			'title'         => __( 'New members each week, by coupon', 'bwc-gallery' ),
			'description'   => __( 'Hover or tab to a week for the split.', 'bwc-gallery' ),
			'summary'       => __( 'New members in each of the last 8 weeks, split by the coupon they used.', 'bwc-gallery' ),
			'legend'        => array(
				array(
					'label'  => 'VELO50',
					'series' => 1,
				),
				array(
					'label'  => 'LAUNCH',
					'series' => 2,
				),
				array(
					'label'  => 'FRIEND',
					'series' => 3,
				),
			),
			'bars'          => $weeks,
			'label_heading' => __( 'Week of', 'bwc-gallery' ),
			'height'        => 'short',
		),
		array(
			'type'    => 'chart',
			'kind'    => 'funnel',
			'half'    => true,
			'title'   => __( 'From sign-up to the end of the course', 'bwc-gallery' ),
			'summary' => __( '24 signed up, 21 opened lesson 1, 15 reached lesson 5, 7 finished.', 'bwc-gallery' ),
			'steps'   => array(
				array(
					'label' => __( 'Signed up', 'bwc-gallery' ),
					'value' => 24,
				),
				array(
					'label' => __( 'Opened lesson 1', 'bwc-gallery' ),
					'value' => 21,
				),
				array(
					'label' => __( 'Reached lesson 5', 'bwc-gallery' ),
					'value' => 15,
				),
				array(
					'label' => __( 'Finished', 'bwc-gallery' ),
					'value' => 7,
				),
				array(
					'label'   => __( 'Left a review', 'bwc-gallery' ),
					'value'   => 0,
					'unknown' => true,
				),
			),
		),
		array(
			'type'    => 'chart',
			'kind'    => 'grouped',
			'half'    => true,
			'percent' => true,
			'title'   => __( 'How far each coupon\'s members got', 'bwc-gallery' ),
			'summary' => __( 'Share of each coupon\'s members who opened lesson 1, reached lesson 5 and finished.', 'bwc-gallery' ),
			'legend'  => array(
				array(
					'label'  => 'VELO50',
					'series' => 1,
				),
				array(
					'label'  => 'LAUNCH',
					'series' => 2,
				),
			),
			'groups'  => array(
				array(
					'label' => __( 'Lesson 1', 'bwc-gallery' ),
					'bars'  => array(
						array(
							'series' => 1,
							'value'  => 9,
							'max'    => 10,
						),
						array(
							'series' => 2,
							'value'  => 5,
							'max'    => 6,
						),
					),
				),
				array(
					'label' => __( 'Lesson 5', 'bwc-gallery' ),
					'bars'  => array(
						array(
							'series' => 1,
							'value'  => 7,
							'max'    => 10,
						),
						array(
							'series' => 2,
							'value'  => 3,
							'max'    => 6,
						),
					),
				),
				array(
					'label' => __( 'Finished', 'bwc-gallery' ),
					'bars'  => array(
						array(
							'series' => 1,
							'value'  => 4,
							'max'    => 10,
						),
						array(
							'series' => 2,
							'value'  => 1,
							'max'    => 6,
						),
					),
				),
			),
		),
		array(
			'type'  => 'notes',
			'items' => array(
				array(
					'tone' => 'warning',
					'text' => __( '4 members haven\'t opened a lesson in two weeks.', 'bwc-gallery' ),
					'link' => array(
						'label' => __( 'See them', 'bwc-gallery' ),
						'url'   => admin_url( 'admin.php?page=bwc-gallery-settings-report&tab=members&filters%5Bsegment%5D=stuck' ),
					),
				),
			),
		),
		array(
			'type'  => 'facts',
			'title' => __( 'About these numbers', 'bwc-gallery' ),
			'items' => array(
				__( 'Counted from', 'bwc-gallery' )  => __( 'Lessons opened, videos played, quizzes sent', 'bwc-gallery' ),
				__( 'Team accounts', 'bwc-gallery' ) => __( 'Left out unless you show them', 'bwc-gallery' ),
				__( 'Updated', 'bwc-gallery' )       => __( 'Every 10 minutes, or when you refresh', 'bwc-gallery' ),
			),
		),
	);
}

/**
 * The Members tab: a table with sparklines, the cards for each coupon.
 *
 * @param array<string, mixed> $view The view.
 * @return list<array<string, mixed>>
 */
function report_demo_members_blocks( array $view ): array {
	$screen  = settings_demo_shell()->screens()['report'];
	$members = report_demo_filter( $view );

	return array(
		array(
			'type'        => 'table',
			'title'       => __( 'Members', 'bwc-gallery' ),
			/* translators: %d: members shown. */
			'description' => sprintf( _n( '%d member', '%d members', count( $members ), 'bwc-gallery' ), count( $members ) ),
			'empty'       => __( 'No members match these filters.', 'bwc-gallery' ),
			'fields'      => array(
				array(
					'id'     => 'name',
					'label'  => __( 'Member', 'bwc-gallery' ),
					'render' => static fn( array $row ): string => '<a class="bwc-table__primary" href="' . esc_url( $screen->link( $view, array( 'member' => (string) $row['id'] ) ) ) . '">' . esc_html( (string) $row['name'] ) . '</a>',
				),
				array(
					'id'       => 'coupon',
					'label'    => __( 'Coupon', 'bwc-gallery' ),
					'type'     => 'enum',
					'elements' => array(
						'velo50' => 'VELO50',
						'launch' => 'LAUNCH',
						'friend' => 'FRIEND',
						'none'   => '—',
					),
				),
				array(
					'id'     => 'weeks',
					'label'  => __( 'Last 12 weeks', 'bwc-gallery' ),
					'render' => static fn( array $row ): string => Chart::spark(
						array(
							'points' => $row['weeks'],
							/* translators: %s: member name. */
							'label'  => sprintf( __( 'Activity of %s over the last 12 weeks', 'bwc-gallery' ), $row['name'] ),
						)
					),
				),
				array(
					'id'     => 'progress',
					'label'  => __( 'Progress', 'bwc-gallery' ),
					'type'   => 'integer',
					'render' => static fn( array $row ): string => esc_html( (int) $row['progress'] . '%' ),
					'align'  => 'end',
				),
				array(
					'id'    => 'last_seen',
					'label' => __( 'Last seen', 'bwc-gallery' ),
					'type'  => 'date',
				),
			),
			'items'       => $members,
		),
	);
}

/**
 * A member's detail view.
 *
 * @param array<string, mixed> $row  The member.
 * @param array<string, mixed> $view The report's view.
 * @return list<array<string, mixed>>
 */
function report_demo_member_blocks( array $row, array $view ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the kit passes the view; this demo doesn't need it.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the timeline's page; nothing is changed.
	$page   = isset( $_GET['events'] ) ? max( 1, (int) $_GET['events'] ) : 1;
	$total  = 60;
	$per    = 10;
	$days   = array();
	$first  = ( $page - 1 ) * $per;
	$kinds  = array( 'video', 'quiz', 'question' );
	$labels = array(
		'video'    => __( 'Watched a lesson video', 'bwc-gallery' ),
		'quiz'     => __( 'Sent a quiz', 'bwc-gallery' ),
		'question' => __( 'Asked a question', 'bwc-gallery' ),
	);

	$last = min( $total, $first + $per );
	for ( $n = $first; $n < $last; $n++ ) {
		$time = time() - $n * 9 * HOUR_IN_SECONDS;
		$day  = wp_date( 'l j F', $time );
		$kind = $kinds[ $n % 3 ];

		$days[ $day ]['label']    = $day;
		$days[ $day ]['events'][] = array(
			'time' => wp_date( (string) get_option( 'time_format' ), $time ),
			/* translators: 1: what they did, 2: lesson number. */
			'text' => sprintf( __( '%1$s in lesson %2$d', 'bwc-gallery' ), $labels[ $kind ], 12 - intdiv( $n, 5 ) ),
			'kind' => $kind,
			'icon' => $kind,
		);
	}

	return array(
		array(
			'type'   => 'profile',
			'avatar' => (string) $row['name'],
			/* translators: %s: date. */
			'line'   => sprintf( __( 'Member since %s', 'bwc-gallery' ), wp_date( (string) get_option( 'date_format' ), time() - 90 * DAY_IN_SECONDS ) ),
			'facts'  => array(
				__( 'Email', 'bwc-gallery' )     => (string) $row['email'],
				__( 'Coupon', 'bwc-gallery' )    => strtoupper( (string) $row['coupon'] ),
				/* translators: %s: time ago. */
				__( 'Last seen', 'bwc-gallery' ) => sprintf( __( '%s ago', 'bwc-gallery' ), human_time_diff( (int) $row['last_seen'] ) ),
			),
		),
		array(
			'type'  => 'progress',
			'title' => __( 'Course progress', 'bwc-gallery' ),
			'items' => array(
				array(
					'label' => __( 'Lessons opened', 'bwc-gallery' ),
					'value' => (int) $row['progress'],
					'note'  => __( 'of 24', 'bwc-gallery' ),
				),
				array(
					'label' => __( 'Quizzes passed', 'bwc-gallery' ),
					'value' => max( 0, (int) $row['progress'] - 20 ),
					'tone'  => 'success',
				),
			),
		),
		array(
			'type' => 'html',
			'html' => '<div class="bwc-report__block"><div class="bwc-card"><div class="bwc-card__body"><p class="bwc-gallery-help">' . esc_html__( 'Lesson 3 video, watched parts', 'bwc-gallery' ) . '</p>'
				. Chart::strip(
					array(
						'map'    => '1111111111100000011111111111111100000000',
						'label'  => __( 'Watched 65%: from the start to 2:10, and 3:40 to 6:20', 'bwc-gallery' ),
						'marker' => 30,
					)
				) . '</div></div></div>',
		),
		array(
			'type'    => 'timeline',
			'title'   => __( 'What they did', 'bwc-gallery' ),
			'days'    => array_values( $days ),
			'param'   => 'events',
			'perPage' => $per,
			'total'   => $total,
			'page'    => $page,
		),
	);
}
