<?php
/**
 * ListScreen, the view in the URL, drawer forms, CSV export and Cache
 *. Loaded by run.php. Writes only throwaway data (the test
 * user's transients, bwc_test_ options), each removed again.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Screen\ListScreen;
use BlissWebConcept\AdminKit\Screen\ListView;

defined( 'ABSPATH' ) || exit;

/**
 * List fields for the tests.
 *
 * @return list<array<string, mixed>>
 */
function bwc_test_list_fields(): array {
	return array(
		array(
			'id'    => 'name',
			'label' => 'Name',
			'type'  => 'text',
		),
		array(
			'id'       => 'kind',
			'label'    => 'Kind',
			'type'     => 'enum',
			'elements' => array(
				'main'  => 'Main',
				'offer' => 'Offer',
				'old'   => 'Old',
			),
			'filterBy' => array(
				'operators' => array( 'is', 'isNot' ),
				'control'   => 'chips',
			),
		),
		array(
			'id'       => 'status',
			'label'    => 'Status',
			'type'     => 'enum',
			'elements' => array(
				'ok'     => 'Working',
				'broken' => 'Broken',
			),
			'filterBy' => array(
				'operators' => array( 'is' ),
				'control'   => 'select',
			),
		),
		array(
			'id'       => 'flag',
			'label'    => 'Flagged',
			'type'     => 'boolean',
			'filterBy' => array( 'operators' => array( 'is' ) ),
			'column'   => false,
		),
		array(
			'id'            => 'clicks',
			'label'         => 'Clicks',
			'type'          => 'integer',
			'enableSorting' => true,
		),
		array(
			'id'     => 'secret',
			'label'  => 'Secret',
			'type'   => 'text',
			'column' => false,
			'csv'    => true,
		),
	);
}

/**
 * The tests' default view.
 *
 * @return array<string, mixed>
 */
function bwc_test_list_defaults(): array {
	return array(
		'filters' => array(
			array(
				'field'    => 'kind',
				'operator' => 'isNot',
				'value'    => 'old',
			),
		),
		'sort'    => array(
			'field'     => 'clicks',
			'direction' => 'desc',
		),
		'perPage' => 20,
	);
}

/**
 * Sample rows: id 1…n, every third old, every fifth broken.
 *
 * @param int $count Rows.
 * @return list<array<string, mixed>>
 */
function bwc_test_list_rows( int $count ): array {
	$rows = array();
	for ( $id = 1; $id <= $count; $id++ ) {
		$rows[] = array(
			'id'     => $id,
			'name'   => 'Row ' . $id,
			'kind'   => 0 === $id % 3 ? 'old' : ( 1 === $id ? 'main' : 'offer' ),
			'status' => 0 === $id % 5 ? 'broken' : 'ok',
			'flag'   => 0 === $id % 2,
			'clicks' => $id * 10,
			'secret' => '=cmd',
		);
	}

	return $rows;
}

/**
 * A query over sample rows, the way a plugin with rows in PHP writes one.
 *
 * @param list<array<string, mixed>> $rows Rows.
 */
function bwc_test_list_query( array $rows ): callable {
	return static function ( array $view ) use ( $rows ): array {
		$found  = array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $view ): bool {
					if ( '' !== $view['search'] && ! str_contains( $row['name'], $view['search'] ) ) {
						return false;
					}
					foreach ( $view['filters'] as $filter ) {
						$hit = in_array( $row[ $filter['field'] ], (array) $filter['value'], true );
						if ( 'isNot' === $filter['operator'] ? $hit : ! $hit ) {
							return false;
						}
					}
					return true;
				}
			)
		);
		$counts = array();
		foreach ( $found as $row ) {
			$counts['status'][ $row['status'] ] = ( $counts['status'][ $row['status'] ] ?? 0 ) + 1;
		}

		return array(
			'items'  => array_slice( $found, ( $view['page'] - 1 ) * $view['perPage'], $view['perPage'] ),
			'total'  => count( $found ),
			'counts' => $counts,
		);
	};
}

/**
 * A registered list screen over sample rows.
 *
 * @param array<string, mixed> $config Config over the defaults.
 * @param int                  $count  Sample rows.
 */
function bwc_test_list_screen( array $config = array(), int $count = 45 ): ListScreen {
	return Kit::instance()->list_screen(
		$config + array(
			'id'     => 'list',
			'label'  => 'Links',
			'title'  => 'Links',
			'fields' => bwc_test_list_fields(),
			'query'  => bwc_test_list_query( bwc_test_list_rows( $count ) ),
			'view'   => bwc_test_list_defaults(),
		)
	);
}

bwc_test(
	'list view: the URL checked against the fields, defaults kept unless the URL says, and back',
	static function (): void {
		bwc_test_kit();
		$fields   = ListView::fields( bwc_test_list_fields() );
		$sizes    = array( 20, 50, 100 );
		$defaults = ListView::defaults( bwc_test_list_defaults(), $fields, $sizes );
		$parse    = static fn( array $args ): array => ListView::parse( $args, $fields, bwc_test_list_defaults(), $sizes );

		bwc_same( $defaults, $parse( array() ), 'nothing in the URL: the defaults' );
		bwc_same( array(), $parse( array( 'filters' => array( 'kind' => '' ) ) )['filters'], 'an empty value turns a default off' );

		$view = $parse(
			array(
				'filters'  => array(
					'status' => 'broken',
					'kind'   => array(
						'op'    => 'is',
						'value' => 'main',
					),
					'flag'   => '1',
					'nope'   => 'x',
				),
				'search'   => ' <b>row</b> ',
				'orderby'  => 'clicks',
				'order'    => 'asc',
				'paged'    => '-3',
				'per_page' => '50',
			)
		);
		bwc_same(
			array(
				array(
					'field'    => 'kind',
					'operator' => 'is',
					'value'    => 'main',
				),
				array(
					'field'    => 'status',
					'operator' => 'is',
					'value'    => 'broken',
				),
				array(
					'field'    => 'flag',
					'operator' => 'is',
					'value'    => true,
				),
			),
			$view['filters'],
			'filters in field order; unknown fields dropped'
		);
		bwc_same( array( 'row', 1, 50 ), array( $view['search'], $view['page'], $view['perPage'] ), 'search cleaned, page at least 1, an offered page size' );
		bwc_same(
			array(
				'field'     => 'clicks',
				'direction' => 'asc',
			),
			$view['sort'],
			'sort'
		);
		bwc_same( $view, $parse( ListView::args( $view, $defaults, $fields ) ), 'a view survives the round trip through the URL' );

		bwc_same( $defaults['filters'], $parse( array( 'filters' => array( 'status' => 'nope' ) ) )['filters'], 'a value not offered is dropped; other fields keep their defaults' );
		bwc_same(
			array(),
			$parse(
				array(
					'filters' => array(
						'kind' => array(
							'op'    => 'contains',
							'value' => 'm',
						),
					),
				)
			)['filters'],
			'an operator not offered is dropped'
		);
		bwc_same( $defaults['sort'], $parse( array( 'orderby' => 'name' ) )['sort'], 'a field that can\'t sort is ignored' );
		bwc_same( 'asc', $parse( array( 'sort' => 'clicks:asc' ) )['sort']['direction'], 'the sort select\'s "field:direction"' );
		bwc_same( 20, $parse( array( 'per_page' => '33' ) )['perPage'], 'a page size not offered' );

		bwc_same( array(), ListView::args( $defaults, $defaults, $fields ), 'the default view has a clean URL' );
		bwc_same( false, ListView::filtered( $defaults, $defaults ), 'defaults are not "filtered"' );
		bwc_same( true, ListView::filtered( $parse( array( 'search' => 'x' ) ), $defaults ), 'a search is' );
	}
);

bwc_test(
	'list screen: pages clamped, chips and select with counts, sort links, drawer links, empty states',
	static function (): void {
		bwc_test_kit();
		wp_set_current_user( 1 );
		$screen = bwc_test_list_screen(
			array(
				'drawer' => array(
					'load' => static fn( $id ): ?array => array( 'name' => 'Row ' . $id ),
					'save' => static fn(): int => 1,
				),
			)
		);

		$_GET = array( 'paged' => '9' );
		$html = $screen->render();
		bwc_same( true, str_contains( $html, 'Showing 21–30 of 30' ), 'past the last page: the last page (45 rows, 15 old hidden by default)' );
		bwc_same( 10, substr_count( $html, '<tr class="bwc-table__row' ), 'ten rows on it' );
		bwc_same( true, str_contains( $html, 'aria-sort="descending"' ), 'the sorted column says so' );
		bwc_same( true, str_contains( $html, 'filters%5Bkind%5D=main' ), 'a chip links to its filter' );
		bwc_same( true, str_contains( $html, '<option value="broken">Broken (6)</option>' ) && str_contains( $html, '<option value="" selected=\'selected\'>Status: All</option>' ), 'select options carry their counts' );
		bwc_same( true, (bool) preg_match( '/<a class="bwc-table__primary" href="[^"]*edit=\d+/', $html ), 'the first column opens the side panel' );
		bwc_same( false, str_contains( $html, 'is-filtered' ), 'the defaults aren\'t "filtered"' );

		$_GET = array( 'filters' => array( 'kind' => 'main' ) );
		$html = $screen->render();
		bwc_same( true, str_contains( $html, '<input type="hidden" name="filters[kind]" value="main">' ), 'the toolbar carries a chip filter, so searching keeps it' );
		bwc_same( true, str_contains( $html, 'is-filtered' ) && str_contains( $html, 'Clear filters' ), 'filtered, with Clear filters' );

		$_GET = array( 'search' => 'zzz' );
		bwc_same( true, str_contains( $screen->render(), 'Nothing matches these filters.' ), 'no match' );

		$_GET  = array();
		$empty = bwc_test_list_screen(
			array(
				'id'    => 'empty',
				'query' => static fn(): array => array(
					'items' => array(),
					'total' => 0,
				),
				'empty' => array( 'none' => 'No links yet.' ),
			)
		);
		$html  = $empty->render();
		bwc_same( true, str_contains( $html, 'No links yet.' ) && ! str_contains( $html, 'bwc-pagination' ), 'nothing at all: the plugin\'s words, no pages' );
		$_GET = array();
	}
);

bwc_test(
	'list screen: content under the title, a note, the list\'s own sort names, chip counts and tones, an empty box left out',
	static function (): void {
		bwc_test_kit();
		wp_set_current_user( 1 );

		$fields = bwc_test_list_fields();
		foreach ( $fields as &$field ) {
			if ( 'kind' === $field['id'] ) {
				$field['elements'] = array(
					array(
						'value' => 'main',
						'label' => 'Main',
					),
					array(
						'value' => 'offer',
						'label' => 'Offer',
						'tone'  => 'danger',
					),
					array(
						'value' => 'old',
						'label' => 'Old',
					),
				);
			}
			if ( 'flag' === $field['id'] ) {
				$field['filterBy']['hide_empty'] = true;
			}
			if ( 'clicks' === $field['id'] ) {
				$field['column'] = false;
			}
		}
		unset( $field );

		$query  = bwc_test_list_query( bwc_test_list_rows( 12 ) );
		$screen = bwc_test_list_screen(
			array(
				'id'     => 'list-extras',
				'fields' => $fields,
				'before' => static fn(): string => '<p class="before-marker">Tiles</p>',
				'note'   => 'Clicks before the move are included.',
				'sorts'  => array( 'clicks:desc' => 'Most clicks first' ),
				'query'  => static function ( array $view ) use ( $query ): array {
					$result                 = $query( $view );
					$result['counts']['kind'] = array(
						''      => 8,
						'offer' => 7,
					);
					$result['totals']['flag'] = 0;
					return $result;
				},
			)
		);

		$_GET = array();
		$html = $screen->render();
		bwc_same( true, strpos( $html, 'before-marker' ) > strpos( $html, 'bwc-page-head' ) && strpos( $html, 'before-marker' ) < strpos( $html, 'bwc-panel' ), 'before sits between the title and the list' );
		bwc_same( true, str_contains( $html, '<p class="bwc-panel__note">Clicks before the move are included.</p>' ), 'the note under the list' );
		bwc_same( true, str_contains( $html, '>Most clicks first</option>' ) && ! str_contains( $html, 'clicks:asc' ), 'only the orders the list names, by its names' );
		bwc_same( true, (bool) preg_match( '/>All <span class="bwc-chip__count">8<\/span>/', $html ), 'the All chip counted' );
		bwc_same( true, (bool) preg_match( '/class="[^"]*bwc-chip--danger[^"]*"[^>]*>Offer/', $html ), 'a chip in its tone' );
		bwc_same( false, str_contains( $html, 'name="filters[flag]"' ), 'a box with nothing to show isn\'t offered' );

		$_GET = array( 'filters' => array( 'flag' => '1' ) );
		bwc_same( true, str_contains( $screen->render(), 'name="filters[flag]"' ), 'unless it\'s ticked' );
		$_GET = array();
	}
);

bwc_test(
	'list screen: fields read only when the list is shown; "All" turns off a default that picks a value; a note and a line by the title from the view',
	static function (): void {
		bwc_test_kit();
		wp_set_current_user( 1 );

		$reads  = 0;
		$screen = bwc_test_list_screen(
			array(
				'id'     => 'list-lazy',
				'fields' => static function () use ( &$reads ): array {
					++$reads;
					return bwc_test_list_fields();
				},
				'view'   => array(
					'filters' => array(
						array(
							'field'    => 'status',
							'operator' => 'is',
							'value'    => 'ok',
						),
					),
				),
				'note'   => static fn( array $view ): string => 'Showing ' . count( $view['filters'] ) . ' filter.',
				'meta'   => static fn(): string => 'Next fetch: tomorrow at 5:00 am',
			)
		);
		bwc_same( 0, $reads, 'describing the screen doesn\'t read its fields' );

		$_GET = array();
		$html = $screen->render();
		bwc_same( 1, $reads, 'showing it reads them once' );
		bwc_same( true, str_contains( $html, 'Showing 1 filter.' ), 'the note from the view' );
		bwc_same( true, str_contains( $html, '<span class="bwc-head__meta">Next fetch: tomorrow at 5:00 am</span>' ), 'the line by the title' );
		bwc_same( true, str_contains( $html, '<option value="" >Status: All</option>' ) || str_contains( $html, 'filters%5Bstatus%5D=' ) || str_contains( $html, '<option value="">Status: All' ), '"All" is offered' );

		$view = $screen->view( 'default' );
		bwc_same( 'ok', ListView::find( $view, 'status' )['value'] ?? null, 'the default picks "ok"' );

		$_GET = array( 'filters' => array( 'status' => '' ) );
		bwc_same( null, ListView::find( $screen->view( 'default' ), 'status' ), '"All" (an empty value) turns it off' );
		$_GET = array();

		$kinds = bwc_test_list_screen(
			array(
				'id'     => 'list-chip-all',
				'fields' => array(
					array(
						'id'       => 'kind',
						'label'    => 'Kind',
						'type'     => 'enum',
						'elements' => static fn(): array => array(
							'main'  => 'Main',
							'offer' => 'Offer',
						),
						'filterBy' => array(
							'operators' => array( 'is' ),
							'control'   => 'segmented',
						),
					),
				),
				'view'   => array(
					'filters' => array(
						array(
							'field'    => 'kind',
							'operator' => 'is',
							'value'    => 'main',
						),
					),
				),
			)
		);
		preg_match_all( '/<a[^>]*href="([^"]*)"[^>]*>\s*(?:<[^>]+>)*All/', $kinds->render(), $all );
		bwc_same( true, isset( $all[1][0] ) && (bool) preg_match( '/filters%5Bkind%5D(=|$|&)/', $all[1][0] ) && ! str_contains( $all[1][0], 'kind%5D=main' ), '"All" links to no filter, not back to "Main"' );
	}
);

bwc_test(
	'list screen: a wide list scrolls at 900px, as a table or grouped',
	static function (): void {
		bwc_test_kit();
		wp_set_current_user( 1 );
		$_GET = array();

		$table = bwc_test_list_screen(
			array(
				'id'     => 'list-wide-table',
				'layout' => array(
					'type'      => 'table',
					'min_width' => 'wide',
				),
			)
		);
		bwc_same( true, str_contains( $table->render(), 'bwc-table--wide' ), 'a wide table' );

		$grouped = bwc_test_list_screen(
			array(
				'id'     => 'list-wide-grouped',
				'layout' => array(
					'type'      => 'grouped',
					'min_width' => 'wide',
					'group'     => static fn( array $item ): array => array(
						'key'   => (string) $item['kind'],
						'label' => (string) $item['kind'],
					),
				),
			)
		);
		bwc_same( true, str_contains( $grouped->render(), 'bwc-grouped bwc-grouped--wide' ), 'a wide grouped list' );
	}
);

bwc_test(
	'list screen: an export with columns of its own; a drawer notice; a text field with a prefix',
	static function (): void {
		bwc_test_kit();
		wp_set_current_user( 1 );

		$screen = bwc_test_list_screen(
			array(
				'id'     => 'list-own-export',
				'export' => array(
					'headings' => static fn(): array => array( 'Link', '2026-10', '2026-11' ),
					'rows'     => static fn(): array => array( array( '/go/a', 3, 4 ) ),
				),
				'drawer' => array(
					'param'  => 'edit',
					'fields' => array(
						array(
							'id'     => 'slug',
							'label'  => 'Short link',
							'type'   => 'text',
							'prefix' => 'example.com/',
						),
					),
					'load'   => static fn(): ?array => array( 'slug' => 'go/a' ),
					'save'   => static fn(): int => 1,
					'notice' => static fn( ?array $record ): string => null === $record ? '' : '<p class="drawer-notice">This link is broken.</p>',
				),
			)
		);

		$kit = Kit::instance();
		$kit->actions()->register( 'bwc_test_tool', static fn(): ?array => null );
		$tool = $kit->actions()->button(
			'bwc_test_tool',
			'Test every link',
			array(
				'disabled' => true,
				'title'    => 'Opens each short link.',
			)
		);
		bwc_same( true, str_contains( $tool, 'title="Opens each short link."' ) && str_contains( $tool, ' disabled' ), 'an action button can be disabled and say what it does on hover' );

		[ $headings, $rows ] = $screen->export_rows( 'default', $screen->view( 'default' ) );
		bwc_same( array( 'Link', '2026-10', '2026-11' ), $headings, 'its own headings' );
		bwc_same( array( array( '/go/a', 3, 4 ) ), is_array( $rows ) ? $rows : iterator_to_array( $rows ), 'its rows as they are' );

		$_GET = array( 'edit' => '1' );
		$html = $screen->render();
		bwc_same( true, str_contains( $html, '<p class="drawer-notice">This link is broken.</p>' ), 'the plugin\'s notice in the drawer' );
		bwc_same( true, (bool) preg_match( '/<div class="bwc-prefixed"><span class="bwc-prefixed__prefix" id="([^"]+)-prefix" aria-hidden="true">example\.com\/<\/span><input[^>]*class="bwc-prefixed__input"[^>]*aria-describedby="\1-prefix/', $html ), 'the prefix before the value, read as its description' );
		$_GET = array();
	}
);

bwc_test(
	'list actions: bulk with a confirm for one action, the export, the drawer\'s save, draft and delete',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		$got     = array();
		$saved   = array();
		$gone    = array();
		$screen  = bwc_test_list_screen(
			array(
				'bulk'   => array(
					'flag'   => array(
						'label'   => 'Flag',
						'handler' => static function ( array $ids ) use ( &$got ): array {
							$got = $ids;
							return array(
								'tone' => 'success',
								'text' => 'Flagged.',
							);
						},
					),
					'remove' => array(
						'label'   => 'Remove',
						'confirm' => 'Remove them?',
						'handler' => static fn(): array => array(
							'tone' => 'success',
							'text' => 'Removed.',
						),
					),
				),
				'export' => array(),
				'drawer' => array(
					'fields' => array(
						array(
							'id'      => 'name',
							'label'   => 'Name',
							'type'    => 'text',
							'isValid' => array( 'required' => true ),
						),
						array(
							'id'    => 'url',
							'label' => 'Goes to',
							'type'  => 'url',
						),
					),
					'load'   => static fn( $id ): ?array => '404' === (string) $id ? null : array(
						'name' => 'Row ' . $id,
						'url'  => 'https://example.com/',
					),
					'save'   => static function ( array $clean, $id ) use ( &$saved ): int|WP_Error {
						if ( 'Taken' === $clean['name'] ) {
							return new WP_Error( 'taken', 'That name is taken.', array( 'field' => 'name' ) );
						}
						if ( 'Broken' === $clean['name'] ) {
							return new WP_Error( 'db', 'The database said no.', array( 'field' => 'nope' ) );
						}
						$saved = array( $clean, $id );
						return 7;
					},
					'delete' => static function ( $id ) use ( &$gone ): bool {
						$gone[] = $id;
						return true;
					},
				),
			),
			1100
		);
		$actions = $kit->actions();
		$referer = '/wp-admin/admin.php?page=bwc-test-list&edit=5&paged=2';
		$nonce   = static fn( string $name ): string => wp_create_nonce( 'bwc_test_bwc_' . $name );

		$bulk = array(
			'_wpnonce'         => $nonce( 'bulk_list' ),
			'_wp_http_referer' => $referer,
			'ids'              => array( '1', '2', '' ),
			'ids_action'       => 'flag',
		);
		$actions->dispatch( 'bulk_list', $bulk );
		bwc_same( array( '1', '2' ), $got, 'the ticked ids reach the handler' );
		bwc_same( 'Flagged.', $kit->notices()->take()[0]['text'], 'its notice' );
		bwc_same( array( 'confirm' => 'Remove them?' ), $actions->dispatch( 'bulk_list', array( 'ids_action' => 'remove' ) + $bulk ), 'a destructive bulk action asks first' );
		$actions->dispatch( 'bulk_list', array( 'ids' => array() ) + $bulk );
		bwc_same( 'info', $kit->notices()->take()[0]['tone'], 'nothing ticked: a hint, nothing run' );

		$_GET            = array( 'filters' => array( 'kind' => '' ) );
		[ $head, $rows ] = $screen->export_rows( 'default', $screen->view( 'default' ) );
		$rows            = iterator_to_array( $rows, false );
		bwc_same( array( 'Name', 'Kind', 'Status', 'Clicks', 'Secret' ), $head, 'export columns: the table\'s and those marked csv' );
		bwc_same( 1100, count( $rows ), 'every row of the view, read in batches' );
		bwc_same( array( 'Row 1', 'main', 'ok', 10, '=cmd' ), $rows[0], 'raw values; the CSV service guards them' );
		$_GET = array();

		$post = array(
			'_wpnonce'            => $nonce( 'drawer_list' ),
			'_wp_http_referer'    => $referer,
			'_bwc_id'             => '5',
			'bwc_test_bwc_drawer' => array(
				'record' => array(
					'name' => '',
					'url'  => 'not a url',
				),
			),
		);
		$out  = $actions->dispatch( 'drawer_list', $post );
		bwc_same( admin_url( 'admin.php?page=bwc-test-list&edit=5&paged=2' ), $out['redirect'], 'errors: the drawer stays open' );
		bwc_same( array(), $kit->notices()->take(), 'no page notice: the drawer says it' );
		bwc_same( array(), $saved, 'nothing saved' );

		$_GET = array( 'edit' => '5' );
		$html = $screen->render();
		bwc_same( true, str_contains( $html, 'value="not a url"' ), 'the drawer reopens with what was typed' );
		bwc_same( true, str_contains( $html, 'Not saved: 2 fields need fixing.' ), 'and says how many need fixing' );
		bwc_same( true, str_contains( $html, 'That isn&#039;t a web address.' ) || str_contains( $html, 'That isn\'t a web address.' ), 'and the error' );
		bwc_same( true, str_contains( $html, '<dialog class="bwc-drawer"' ) && str_contains( $html, 'name="_bwc_id" value="5"' ), 'a dialog posting the record\'s id' );
		bwc_same( false, str_contains( $html, 'See the message' ), 'one notice, in the drawer\'s words, not a second one about settings' );
		bwc_same( true, str_contains( $html, 'data-bwc-drawer-unsaved' ), 'closing asks first: what was typed would be lost' );

		// The plugin's own check can name its field: shown there, like the kit's.
		$post['bwc_test_bwc_drawer']['record'] = array(
			'name' => 'Taken',
			'url'  => 'https://example.org/',
		);
		$actions->dispatch( 'drawer_list', $post );
		$html = $screen->render();
		bwc_same( array( true, true, 1 ), array( str_contains( $html, 'Not saved: 1 field needs fixing.' ), (bool) preg_match( '/id="bwc-test-record-name"[^>]*aria-invalid="true"|aria-invalid="true"[^>]*id="bwc-test-record-name"/', $html ), substr_count( $html, 'That name is taken.' ) ), 'a save error with a field: on that field, once' );

		$post['bwc_test_bwc_drawer']['record']['name'] = 'Broken';
		$actions->dispatch( 'drawer_list', $post );
		$html = $screen->render();
		bwc_same( array( true, false ), array( str_contains( $html, 'The database said no.' ), str_contains( $html, 'needs fixing' ) ), 'a field it doesn\'t have: the message on its own' );
		bwc_same( array(), $saved, 'still nothing saved' );

		$post['bwc_test_bwc_drawer']['record'] = array(
			'name' => 'Renamed',
			'url'  => 'https://example.org/',
		);
		$out                                   = $actions->dispatch( 'drawer_list', $post );
		bwc_same( array( 'Renamed', 'https://example.org/', '5' ), array( $saved[0]['name'], $saved[0]['url'], $saved[1] ), 'saved through the plugin\'s callback' );
		bwc_same( admin_url( 'admin.php?page=bwc-test-list&paged=2' ), $out['redirect'], 'saved: back to the list, same page' );
		$kit->notices()->take();

		$_GET = array( 'edit' => '404' );
		bwc_same( true, str_contains( $screen->render(), 'That item no longer exists.' ), 'a record that\'s gone' );

		$delete = array(
			'_wpnonce'         => $nonce( 'drawer_delete_list' ),
			'_wp_http_referer' => $referer,
			'_bwc_id'          => '5',
		);
		bwc_same( true, isset( $actions->dispatch( 'drawer_delete_list', $delete )['confirm'] ), 'delete asks first' );
		$actions->dispatch( 'drawer_delete_list', array( '_bwc_confirmed' => '1' ) + $delete );
		bwc_same( array( '5' ), $gone, 'then deletes' );
		$kit->notices()->take();
		$_GET = array();

		foreach ( array( 'bulk_list', 'export_list', 'drawer_list', 'drawer_delete_list' ) as $name ) {
			remove_all_actions( 'admin_post_bwc_test_bwc_' . $name );
		}
	}
);

bwc_test(
	'csv: formula guard on text, numbers as they are, byte-order mark, streamed rows',
	static function (): void {
		$csv = bwc_test_kit()->csv();
		bwc_same( "'=SUM(A1)", $csv->cell( '=SUM(A1)' ), 'formula' );
		bwc_same( "'@x", $csv->cell( '@x' ), 'at sign' );
		bwc_same( "'+1 555", $csv->cell( '+1 555' ), 'a plus that isn\'t a number' );
		bwc_same( '-5', $csv->cell( '-5' ), 'a negative number stays' );
		bwc_same( -5, $csv->cell( -5 ), 'a real number stays' );
		bwc_same( 1, $csv->cell( true ), 'boolean' );
		bwc_same( 'a, b', $csv->cell( array( 'a', 'b' ) ), 'list' );

		$out = fopen( 'php://memory', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream for the test.
		$n   = $csv->write(
			$out,
			array( 'Name', 'Note' ),
			( static function (): Generator {
				yield array( 'Ann', '=1+1' );
				yield array( 'Bo, "B"', '' );
			} )()
		);
		rewind( $out );
		$text = stream_get_contents( $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- the test's stream.
		bwc_same( 2, $n, 'rows written' );
		bwc_same( "\xEF\xBB\xBFName,Note\nAnn,'=1+1\n\"Bo, \"\"B\"\"\",\n", $text, 'the file' );
	}
);

bwc_test(
	'cache: kept, rebuilt for a new version, one entry per variant, failures kept briefly, forgotten',
	static function (): void {
		$kit   = bwc_test_kit();
		$cache = $kit->cache();
		$cache->forget_all();
		$runs  = 0;
		$build = static function () use ( &$runs ): array {
			++$runs;
			return array( 'n' => $runs );
		};

		bwc_same( array( 'n' => 1 ), $cache->remember( 't', 1, 60, $build ), 'built' );
		bwc_same( array( 'n' => 1 ), $cache->remember( 't', 1, 60, $build ), 'kept' );
		bwc_same( true, is_int( $cache->built( 't' ) ), 'build time' );
		bwc_same( array( 'n' => 2 ), $cache->remember( 't', 2, 60, $build ), 'a new version rebuilds' );
		bwc_same( array( 'n' => 3 ), $cache->remember( 't', 2, 60, $build, array( 'vary' => array( 'team' => true ) ) ), 'a variant has its own entry' );
		bwc_same( array( 'n' => 2 ), $cache->remember( 't', 2, 60, $build ), 'the other is still there' );

		$fails = 0;
		$bad   = static function () use ( &$fails ): never {
			++$fails;
			throw new RuntimeException( 'Source down.' );
		};
		bwc_same( 'Source down.', $cache->remember( 'f', 1, 60, $bad )->get_error_message(), 'a throw is a WP_Error' );
		bwc_same( true, is_wp_error( $cache->remember( 'f', 1, 60, $bad ) ), 'and is kept' );
		bwc_same( 1, $fails, 'so the source isn\'t asked again' );

		wp_set_current_user( 1 );
		$kit->actions()->dispatch(
			'refresh',
			array(
				'_wpnonce' => wp_create_nonce( 'bwc_test_bwc_refresh' ),
				'cache'    => array( 't' ),
			)
		);
		bwc_same( null, $cache->built( 't' ), 'Refresh forgets every variant' );
		bwc_same( array( 'n' => 4 ), $cache->remember( 't', 2, 60, $build ), 'and the next read rebuilds' );

		$cache->forget_all();
		bwc_same( false, get_option( 'bwc_test_bwc_cache_index' ), 'forget_all clears the index' );
		bwc_same( null, $cache->built( 'f' ), 'and every entry' );
		remove_all_actions( 'admin_post_bwc_test_bwc_refresh' );
	}
);
