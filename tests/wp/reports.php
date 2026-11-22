<?php
/**
 * Charts, ReportScreen and DetailScreen. Loaded by run.php.
 * Writes only a throwaway cache entry, removed again.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Component\Chart;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

bwc_test(
	'charts: line with an estimated start, axis rounded to fours, hover zones, the numbers',
	static function (): void {
		bwc_test_kit();
		$points = array();
		foreach ( array( 2, 5, 9, 7, 13 ) as $i => $value ) {
			$points[] = array(
				'label'     => 'D' . ( $i + 1 ),
				'value'     => $value,
				'estimated' => $i < 2,
			);
		}

		$html = Chart::line(
			array(
				'title'   => 'Active members',
				'summary' => '13 today, at most 13.',
				'points'  => $points,
				'area'    => true,
			)
		);

		bwc_same( 1, preg_match( '/<figure class="bwc-chart bwc-chart--line" aria-labelledby="(bwc-test-chart-\d+)-title" aria-describedby="\1-summary">/', $html ), 'a figure named by its title and described by its summary' );
		bwc_same( true, str_contains( $html, '<p class="bwc-visually-hidden" id="' ) && str_contains( $html, '13 today, at most 13.</p>' ), 'the summary for screen readers' );
		bwc_same( true, str_contains( $html, '<span class="bwc-chart__tick bwc-chart__tick--top">16</span>' ) && str_contains( $html, 'bwc-chart__tick--middle">8<' ), 'axis top rounded up to a multiple of four' );
		bwc_same( true, str_contains( $html, '<path class="bwc-chart__line bwc-chart__line--dashed" vector-effect="non-scaling-stroke" d="M10,87.5 L30,68.75 L50,43.75"/>' ), 'dashed up to the first recorded point, points in the middle of their slots' );
		bwc_same( true, str_contains( $html, '<path class="bwc-chart__line" vector-effect="non-scaling-stroke" d="M50,43.75 L70,56.25 L90,18.75"/>' ), 'solid from there' );
		bwc_same( true, str_contains( $html, '<path class="bwc-chart__area" d="M10,100 L10,87.5' ) && str_contains( $html, 'L90,100 Z"/>' ), 'area under the line' );
		bwc_same( true, str_contains( $html, 'd="M90,18.75 L90,18.75"' ), 'end marker' );
		bwc_same( 5, substr_count( $html, '<span class="bwc-chart__hit">' ), 'a hover zone per point' );
		bwc_same( true, str_contains( $html, 'bwc-chart__tip bwc-chart__tip--start">D1: 2 (estimated)</span>' ), 'tooltips say estimated, and open inwards at the edges' );
		bwc_same( true, str_contains( $html, '<details class="bwc-chart__data"><summary class="bwc-chart__data-summary">Show the numbers</summary>' ), 'the numbers on request' );
		bwc_same( 5, substr_count( $html, '<tr class="bwc-table__row' ), 'a row per point' );
		bwc_same( false, str_contains( $html, 'style=' ), 'no inline style' );
		bwc_same(
			'',
			Chart::line(
				array(
					'title'   => 'x',
					'summary' => 'x',
					'points'  => array(),
				)
			),
			'no points, no chart'
		);
	}
);

bwc_test(
	'charts: stacked bars focusable when few, grouped percentages, funnel meters, sparkline, strip',
	static function (): void {
		bwc_test_kit();
		$bar  = static fn( string $label, int $a, int $b ): array => array(
			'label' => $label,
			'parts' => array(
				array(
					'series' => 1,
					'value'  => $a,
				),
				array(
					'series' => 2,
					'value'  => $b,
				),
			),
		);
		$html = Chart::bars(
			array(
				'title'   => 'New members',
				'summary' => 'Two weeks.',
				'legend'  => array(
					array(
						'label'  => 'Coupon',
						'series' => 1,
					),
					array(
						'label'  => 'None',
						'series' => 2,
					),
				),
				'bars'    => array( $bar( 'W1', 3, 1 ), $bar( 'W2', 6, 2 ) ),
			)
		);
		bwc_same( true, str_contains( $html, '<span class="bwc-chart__column" tabindex="0" role="img" aria-label="W2: 8 (Coupon 6, None 2)">' ), 'a few bars: focusable, named by their split' );
		bwc_same( true, str_contains( $html, '<rect class="bwc-chart__part bwc-chart__part--s1" x="0" y="25" width="1" height="75"/><rect class="bwc-chart__part bwc-chart__part--s2" x="0" y="0" width="1" height="25"/>' ), 'parts stacked to the top of the axis (8)' );
		bwc_same( true, str_contains( $html, '>Coupon</th>' ) && str_contains( $html, '>Total</th>' ), 'table columns per series, then the total' );

		$many = array();
		for ( $i = 1; $i <= 13; $i++ ) {
			$many[] = $bar( 'W' . $i, 1, 1 );
		}
		$html = Chart::bars(
			array(
				'title'   => 'x',
				'summary' => 'x',
				'bars'    => $many,
			)
		);
		bwc_same( true, str_contains( $html, '<div class="bwc-chart__plot" aria-hidden="true">' ) && ! str_contains( $html, 'bwc-chart__column" tabindex' ), 'many bars: no tab stops; the table carries them' );

		$html = Chart::grouped(
			array(
				'title'   => 'Reached',
				'summary' => 'x',
				'percent' => true,
				'groups'  => array(
					array(
						'label' => 'Lesson 1',
						'bars'  => array(
							array(
								'series' => 1,
								'value'  => 9,
								'max'    => 10,
							),
							array(
								'series' => 2,
								'value'  => 1,
								'max'    => 4,
							),
						),
					),
				),
			)
		);
		bwc_same( true, str_contains( $html, 'bwc-chart__tick--top">100%<' ) && str_contains( $html, 'y="10" width="1" height="90"' ) && str_contains( $html, 'y="75" width="1" height="25"' ), 'grouped: each bar its share of its own max' );

		$html = Chart::funnel(
			array(
				'title'   => 'Funnel',
				'summary' => 'x',
				'steps'   => array(
					array(
						'label' => 'Signed up',
						'value' => 20,
					),
					array(
						'label' => 'Finished',
						'value' => 5,
					),
					array(
						'label'   => 'Reviewed',
						'value'   => 0,
						'unknown' => true,
					),
				),
			)
		);
		bwc_same( true, str_contains( $html, '<meter class="bwc-bar__meter" min="0" max="100" value="25" aria-label="Finished">' ) && str_contains( $html, '5 · 25%' ), 'funnel: a meter and a share per step' );
		bwc_same( true, str_contains( $html, '<span class="bwc-funnel__count"><span aria-hidden="true">—</span><span class="bwc-visually-hidden">Not known</span></span>' ) && ! str_contains( $html, 'bwc-chart__data' ), 'unknown shows a dash; no table needed' );

		bwc_same(
			'<svg class="bwc-chart bwc-chart--spark" viewBox="0 0 100 20" preserveAspectRatio="none" role="img" aria-label="Rising" focusable="false"><path class="bwc-chart__line" vector-effect="non-scaling-stroke" d="M0,19 L50,10 L100,1"/></svg>',
			Chart::spark(
				array(
					'points' => array( 0, 2, 4 ),
					'label'  => 'Rising',
				)
			),
			'sparkline'
		);
		bwc_same(
			'',
			Chart::spark(
				array(
					'points' => array( 1 ),
					'label'  => 'x',
				)
			),
			'one point, no line'
		);

		$strip = Chart::strip(
			array(
				'map'    => '1100111',
				'label'  => 'Watched most of it',
				'marker' => 4,
			)
		);
		bwc_same( true, str_contains( $strip, 'viewBox="0 0 7 1"' ) && str_contains( $strip, '<rect class="bwc-chart__covered" x="0" y="0" width="2" height="1"/><rect class="bwc-chart__covered" x="4" y="0" width="3" height="1"/>' ) && str_contains( $strip, 'd="M4,0 L4,1"' ), 'strip: covered runs and the marker' );
	}
);

/**
 * A report screen over a few blocks, for the tests.
 *
 * @param array<string, mixed> $config Config over the defaults.
 */
function bwc_test_report( array $config = array() ) {
	$kit    = Kit::instance();
	$detail = $kit->detail_screen(
		array(
			'param'   => 'member',
			'load'    => static fn( $id ): ?array => '7' === (string) $id ? array( 'name' => 'Ama' ) : null,
			'title'   => static fn( array $row ): string => $row['name'],
			'back'    => 'All members',
			'missing' => 'That member no longer exists.',
			'blocks'  => static fn( array $row ): array => array(
				array(
					'type'   => 'profile',
					'avatar' => $row['name'],
					'facts'  => array( 'Coupon' => 'VELO50' ),
				),
			),
		)
	);

	return $kit->report_screen(
		$config + array(
			'id'      => 'insights',
			'label'   => 'Insights',
			'title'   => 'Insights',
			'persist' => array( 'team' ),
			'data'    => array( 'test_report' ),
			'tabs'    => array(
				'overview' => array(
					'label'  => 'Overview',
					'blocks' => static fn( array $view ): array => array(
						array(
							'type'  => 'tiles',
							'items' => array(
								array(
									'label' => 'Members',
									'value' => '24',
								),
							),
						),
						array(
							'type'  => 'notes',
							'items' => array(
								array(
									'tone' => 'warning',
									'text' => '4 are stuck.',
									'link' => array(
										'label' => 'See them',
										'url'   => 'https://example.com/stuck',
									),
								),
							),
						),
						array(
							'type' => 'html',
							'half' => true,
							'html' => '<p>Left</p>',
						),
						array(
							'type' => 'html',
							'half' => true,
							'html' => '<p>Right</p>',
						),
						array(
							'type'   => 'section',
							'title'  => 'More',
							'blocks' => array(
								array(
									'type'  => 'facts',
									'items' => array( 'Team' => empty( $view['persist']['team'] ) ? 'hidden' : 'shown' ),
								),
							),
						),
					),
				),
				'members'  => array(
					'label'   => 'Members',
					'filters' => array(
						array(
							'id'       => 'segment',
							'label'    => 'Segment',
							'type'     => 'enum',
							'elements' => array(
								'active' => 'Active',
								'stuck'  => 'Stuck',
							),
							'filterBy' => array(
								'operators' => array( 'is' ),
								'control'   => 'chips',
							),
						),
					),
					'blocks'  => static fn(): array => array(
						array(
							'type'   => 'table',
							'title'  => 'Members',
							'fields' => array(
								array(
									'id'    => 'name',
									'label' => 'Name',
								),
								array(
									'id'    => 'n',
									'label' => 'Lessons',
									'type'  => 'integer',
								),
							),
							'items'  => array(
								array(
									'name' => 'Ama',
									'n'    => 1200,
								),
							),
						),
					),
					'detail'  => $detail,
				),
			),
		)
	);
}

bwc_test(
	'report screen: tabs, blocks in order and in pairs, filters, persisted params, freshness with Refresh',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		$kit->cache()->forget_all();
		$kit->cache()->remember( 'test_report', 1, 60, static fn(): array => array( 1 ) );
		$report = bwc_test_report();

		$_GET = array( 'team' => '1' );
		$html = $report->render();
		bwc_same( true, str_contains( $html, '<nav class="bwc-nav bwc-nav--tabs" aria-label="Insights"' ), 'report tabs' );
		bwc_same( true, str_contains( $html, 'tab=members' ) && (bool) preg_match( '/href="[^"]*team=1[^"]*tab=members|href="[^"]*tab=members[^"]*team=1/', $html ), 'tab links keep the persisted team switch' );
		bwc_same( true, str_contains( $html, '<section class="bwc-tiles"' ) && strpos( $html, 'bwc-tiles' ) < strpos( $html, '4 are stuck.' ), 'blocks in their order' );
		bwc_same( true, str_contains( $html, '<a href="https://example.com/stuck">See them</a>' ), 'a note with its link' );
		bwc_same( true, str_contains( $html, '<div class="bwc-report__row"><p>Left</p><p>Right</p></div>' ), 'two half blocks share a row' );
		bwc_same( true, str_contains( $html, '<h2 class="bwc-report__section-title">More</h2>' ) && str_contains( $html, 'shown' ), 'a section, and the view reached the blocks' );
		bwc_same( true, str_contains( $html, '<time class="bwc-freshness__text"' ) && str_contains( $html, 'Data read ' ) && str_contains( $html, 'value="bwc_test_bwc_refresh"' ), 'freshness and Refresh' );

		$_GET = array( 'tab' => 'members' );
		$html = $report->render();
		bwc_same( true, str_contains( $html, 'filters%5Bsegment%5D=stuck' ) && str_contains( $html, '>1,200<' ), 'chips filter the tab; table cells formatted as in lists' );

		$_GET = array(
			'tab'     => 'members',
			'filters' => array( 'segment' => 'stuck' ),
			'member'  => '7',
		);
		$html = $report->render();
		bwc_same( true, str_contains( $html, '<h2 class="bwc-detail__title">Ama</h2>' ) && str_contains( $html, 'bwc-avatar' ), 'the detail view, in place' );
		bwc_same( 1, preg_match( '/<a class="bwc-detail__back" href="([^"]*)">/', $html, $back ), 'a back link' );
		bwc_same( true, str_contains( $back[1], 'filters%5Bsegment%5D=stuck' ) && ! str_contains( $back[1], 'member=' ), 'which keeps the filters and drops the record' );

		$_GET = array(
			'tab'    => 'members',
			'member' => '99',
		);
		bwc_same( true, str_contains( $report->render(), 'That member no longer exists.' ), 'a record that\'s gone' );

		$_GET = array();
		$kit->cache()->forget_all();
	}
);

bwc_test(
	'detail screen: a list opens one in place, and the timeline pages keep the record',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		$detail = $kit->detail_screen(
			array(
				'param'  => 'member',
				'load'   => static fn( $id ): array => array( 'name' => 'Row ' . $id ),
				'title'  => static fn( array $row ): string => $row['name'],
				'blocks' => static fn(): array => array(
					array(
						'type'    => 'timeline',
						'title'   => 'What they did',
						'days'    => array(
							array(
								'label'  => 'Monday',
								'events' => array(
									array(
										'time' => '9:00',
										'text' => 'Opened lesson 1',
									),
								),
							),
						),
						'param'   => 'events',
						'perPage' => 10,
						'total'   => 30,
						'page'    => 2,
					),
				),
			)
		);
		$list   = bwc_test_list_screen( array( 'detail' => $detail ) );

		$_GET = array(
			'member'  => '3',
			'filters' => array( 'kind' => 'main' ),
		);
		$html = $list->render();
		bwc_same( true, str_contains( $html, '<h2 class="bwc-detail__title">Row 3</h2>' ) && ! str_contains( $html, 'bwc-list__toolbar' ), 'the detail instead of the list' );
		bwc_same( true, str_contains( $html, 'id="bwc-timeline"' ) && (bool) preg_match( '/href="[^"]*member=3[^"]*events=3[^"]*#bwc-timeline"/', $html ), 'the next timeline page keeps the record and the reader\'s place' );
		$_GET = array();
	}
);
