<?php
/**
 * Markup snapshots for the controls and data. Loaded by run.php
 * after components.php, whose bwc_component_kit() these use.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Component\Button;
use BlissWebConcept\AdminKit\Component\Chips;
use BlissWebConcept\AdminKit\Component\Collapsible;
use BlissWebConcept\AdminKit\Component\CopyButton;
use BlissWebConcept\AdminKit\Component\ExpandAll;
use BlissWebConcept\AdminKit\Component\Feed;
use BlissWebConcept\AdminKit\Component\GroupedList;
use BlissWebConcept\AdminKit\Component\Input;
use BlissWebConcept\AdminKit\Component\Pagination;
use BlissWebConcept\AdminKit\Component\Prefixed;
use BlissWebConcept\AdminKit\Component\Reference;
use BlissWebConcept\AdminKit\Component\RunList;
use BlissWebConcept\AdminKit\Component\Search;
use BlissWebConcept\AdminKit\Component\Segmented;
use BlissWebConcept\AdminKit\Component\Select;
use BlissWebConcept\AdminKit\Component\Table;
use BlissWebConcept\AdminKit\Component\Timeline;
use BlissWebConcept\AdminKit\Component\Toggle;

defined( 'ABSPATH' ) || exit;

bwc_test(
	'button: button or link, variants, icon-only names, disabled links lose their href',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<button type="submit" class="bwc-button bwc-button--primary" name="save" value="1"><span class="bwc-button__label">Save changes</span></button>',
			Button::markup(
				array(
					'label'   => 'Save changes',
					'variant' => 'primary',
					'type'    => 'submit',
					'name'    => 'save',
					'value'   => '1',
				)
			),
			'submit button'
		);
		bwc_same(
			'<a class="bwc-button bwc-button--danger" data-bwc-confirm="Delete this link?" href="https://example.test/d?a=1&#038;b=2"><span class="bwc-button__label">Delete &amp; go</span></a>',
			Button::markup(
				array(
					'label'   => 'Delete & go',
					'variant' => 'danger',
					'url'     => 'https://example.test/d?a=1&b=2',
					'confirm' => 'Delete this link?',
				)
			),
			'danger link that asks first'
		);
		bwc_same(
			'<a class="bwc-button" aria-disabled="true"><span class="bwc-button__label">Next</span></a>',
			Button::markup(
				array(
					'label'    => 'Next',
					'url'      => 'https://example.test/n',
					'disabled' => true,
				)
			),
			'disabled link'
		);
		bwc_same(
			true,
			str_starts_with(
				Button::markup(
					array(
						'label'     => 'Remove',
						'icon'      => 'trash',
						'icon_only' => true,
						'size'      => 'small',
						'pressed'   => false,
						'hook'      => 'discard',
						'disabled'  => true,
					)
				),
				'<button type="button" class="bwc-button bwc-button--small bwc-button--icon" aria-pressed="false" data-bwc-discard disabled><span class="bwc-button__icon" aria-hidden="true">'
			),
			'icon-only, pressed state, hook, disabled'
		);
		bwc_same(
			true,
			str_ends_with(
				Button::markup(
					array(
						'label'     => 'Remove',
						'icon'      => 'trash',
						'icon_only' => true,
					)
				),
				'</svg></span><span class="bwc-visually-hidden">Remove</span></button>'
			),
			'icon-only keeps its label for screen readers'
		);
		bwc_same(
			'<button type="button" class="bwc-button"><span class="bwc-button__label">X</span></button>',
			Button::markup(
				array(
					'label'   => 'X',
					'variant' => 'sparkly',
				)
			),
			'unknown variant falls back'
		);
	}
);

bwc_test(
	'switch: a checkbox with role switch, labelled from outside, on/off text hidden',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<label class="bwc-switch"><input class="bwc-switch__input" type="checkbox" role="switch" id="acme-links" name="modules[links]" value="1" checked aria-labelledby="acme-links-label" aria-describedby="acme-links-help"><span class="bwc-switch__state" aria-hidden="true"><span class="bwc-switch__on">On</span><span class="bwc-switch__off">Off</span></span><span class="bwc-switch__track" aria-hidden="true"></span></label>',
			Toggle::markup(
				array(
					'id'          => 'acme-links',
					'name'        => 'modules[links]',
					'checked'     => true,
					'labelledby'  => 'acme-links-label',
					'describedby' => 'acme-links-help',
				)
			),
			'checked switch'
		);
		bwc_same(
			true,
			str_contains(
				Toggle::markup(
					array(
						'id'         => 'a',
						'name'       => 'a',
						'checked'    => false,
						'labelledby' => 'b',
						'disabled'   => true,
					)
				),
				'value="1" disabled aria-labelledby="b">'
			),
			'off and disabled'
		);
		bwc_throws(
			static fn() => Toggle::markup(
				array(
					'id'      => 'a',
					'name'    => 'a',
					'checked' => true,
				)
			),
			'a switch needs a label'
		);
	}
);

bwc_test(
	'search, select, input and prefixed input: labels, states, auto-submit',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			true,
			str_starts_with(
				Search::markup(
					array(
						'id'          => 'q',
						'name'        => 'q',
						'label'       => 'Search links',
						'placeholder' => 'Brands or links',
					)
				),
				'<div class="bwc-search"><label class="bwc-visually-hidden" for="q">Search links</label><span class="bwc-search__field"><span class="bwc-search__icon" aria-hidden="true"><svg'
			),
			'hidden label'
		);
		bwc_same(
			true,
			str_ends_with(
				Search::markup(
					array(
						'id'            => 'q',
						'name'          => 'q',
						'label'         => 'Find',
						'label_visible' => true,
						'hook'          => 'search',
						'value'         => 'a"b',
					)
				),
				'<input class="bwc-search__input" type="search" id="q" name="q" value="a&quot;b" data-bwc-search></span></div>'
			),
			'visible label, hook, escaped value'
		);

		bwc_same(
			'<label class="bwc-visually-hidden" for="prog">Program</label><select class="bwc-control bwc-control--full" id="prog" name="program" data-bwc-auto-submit><option value="">All</option><option value="g" selected=\'selected\'>Northwind</option></select>',
			Select::markup(
				array(
					'id'          => 'prog',
					'name'        => 'program',
					'label'       => 'Program',
					'options'     => array(
						''  => 'All',
						'g' => 'Northwind',
					),
					'value'       => 'g',
					'width'       => 'full',
					'auto_submit' => true,
				)
			),
			'select'
		);
		bwc_same(
			'<label class="bwc-label" for="d">Days</label><input class="bwc-control bwc-control--number" id="d" name="days" required aria-invalid="true" type="number" value="90">',
			Input::markup(
				array(
					'id'            => 'd',
					'name'          => 'days',
					'label'         => 'Days',
					'label_visible' => true,
					'type'          => 'number',
					'value'         => '90',
					'required'      => true,
					'invalid'       => true,
				)
			),
			'number input'
		);
		bwc_same(
			'<label class="bwc-visually-hidden" for="n">Notes</label><textarea class="bwc-control bwc-control--textarea bwc-control--mono" id="n" name="notes" rows="3">&lt;b&gt;</textarea>',
			Input::markup(
				array(
					'id'    => 'n',
					'name'  => 'notes',
					'label' => 'Notes',
					'type'  => 'textarea',
					'value' => '<b>',
					'mono'  => true,
					'rows'  => 3,
				)
			),
			'textarea'
		);

		bwc_same(
			'<label class="bwc-visually-hidden" for="slug">Short link</label><div class="bwc-prefixed"><span class="bwc-prefixed__prefix" id="slug-prefix" aria-hidden="true">example.com/</span><input class="bwc-prefixed__input" type="text" id="slug" name="slug" value="go/nexcess" required aria-describedby="slug-prefix slug-help"></div>',
			Prefixed::markup(
				array(
					'id'          => 'slug',
					'name'        => 'slug',
					'label'       => 'Short link',
					'prefix'      => 'example.com/',
					'value'       => 'go/nexcess',
					'required'    => true,
					'describedby' => 'slug-help',
				)
			),
			'prefixed'
		);
	}
);

bwc_test(
	'chips and segmented control: links say which is current, filter buttons wait for the script',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<div class="bwc-chips" role="group" aria-label="Filter links"><a class="bwc-chip is-current" href="https://example.test/all" aria-current="true">All <span class="bwc-chip__count">1,256</span></a><a class="bwc-chip bwc-chip--danger" href="https://example.test/b">Need attention <span class="bwc-chip__count">2</span></a></div>',
			Chips::markup(
				array(
					'label'   => 'Filter links',
					'current' => 'all',
					'items'   => array(
						'all'    => array(
							'label' => 'All',
							'count' => 1256,
							'url'   => 'https://example.test/all',
						),
						'broken' => array(
							'label' => 'Need attention',
							'count' => 2,
							'tone'  => 'danger',
							'url'   => 'https://example.test/b',
						),
					),
				)
			),
			'link chips'
		);
		bwc_same(
			'<div class="bwc-chips" role="group" aria-label="Show" data-bwc-filters="feed" hidden><button type="button" class="bwc-chip" aria-pressed="true" data-bwc-filter="all">All</button><button type="button" class="bwc-chip" aria-pressed="false" data-bwc-filter="offers">Offers <span class="bwc-chip__count">3</span></button></div>',
			Chips::markup(
				array(
					'label'   => 'Show',
					'kind'    => 'buttons',
					'target'  => 'feed',
					'current' => 'all',
					'items'   => array(
						'all'    => array( 'label' => 'All' ),
						'offers' => array(
							'label' => 'Offers',
							'count' => 3,
						),
					),
				)
			),
			'filter buttons, hidden until the script'
		);
		bwc_same(
			'<div class="bwc-segmented" role="group" aria-label="Contract"><a class="bwc-segmented__option is-current" href="https://example.test/a" aria-current="true">Active <span class="bwc-segmented__count">40</span></a><a class="bwc-segmented__option" href="https://example.test/e">Ended</a></div>',
			Segmented::markup(
				array(
					'label'   => 'Contract',
					'current' => 'active',
					'items'   => array(
						'active' => array(
							'label' => 'Active',
							'count' => 40,
							'url'   => 'https://example.test/a',
						),
						'ended'  => array(
							'label' => 'Ended',
							'url'   => 'https://example.test/e',
						),
					),
				)
			),
			'segmented'
		);
	}
);

bwc_test(
	'pagination: the range, first/previous/next/last with spoken names, newer and older, the ends disabled',
	static function (): void {
		bwc_component_kit();
		$url  = static fn( int $page ): string => 'https://example.test/?paged=' . $page;
		$html = Pagination::markup(
			array(
				'page'     => 1,
				'per_page' => 20,
				'total'    => 245,
				'url'      => $url,
				'anchor'   => 'list',
			)
		);
		bwc_same( true, str_starts_with( $html, '<div class="bwc-pagination"><span class="bwc-pagination__summary">Showing 1–20 of 245</span><nav class="bwc-pagination__nav" aria-label="Pages"><a class="bwc-button bwc-button--small bwc-button--icon bwc-pagination__first" aria-disabled="true">' ), 'first page: first disabled' );
		bwc_same( true, str_contains( $html, '<a class="bwc-button bwc-button--small bwc-button--icon bwc-pagination__prev" aria-disabled="true">' ), 'and previous disabled' );
		bwc_same( true, str_contains( $html, '<a class="bwc-button bwc-button--small bwc-button--icon bwc-pagination__next" href="https://example.test/?paged=2#list">' ), 'next goes to page 2, with the anchor' );
		bwc_same( true, str_contains( $html, '<a class="bwc-button bwc-button--small bwc-button--icon bwc-pagination__last" href="https://example.test/?paged=13#list">' ), 'last goes to page 13' );
		bwc_same( true, str_contains( $html, '<span class="bwc-visually-hidden">First page</span>' ) && str_contains( $html, '<span class="bwc-visually-hidden">Last page</span>' ), 'first and last are named' );
		bwc_same(
			true,
			str_contains( $html, '<form class="bwc-pagination__jump" method="get" action="https://example.test/#list"><label class="bwc-visually-hidden" for="bwc-test-page-1">Current page</label><input class="bwc-control bwc-pagination__page" type="number" id="bwc-test-page-1" name="paged" value="1" min="1" max="13" inputmode="numeric"> of <span class="bwc-pagination__total">13</span></form>' ),
			'page box: a GET form for the paged argument, labelled, "of 13"'
		);

		$last = Pagination::markup(
			array(
				'page'     => 99,
				'per_page' => 20,
				'total'    => 245,
				'url'      => $url,
			)
		);
		bwc_same( true, str_contains( $last, 'Showing 241–245 of 245' ), 'a page past the end shows the last page' );
		bwc_same( true, str_contains( $last, 'bwc-pagination__next" aria-disabled="true"' ), 'last page: next disabled' );

		$time = Pagination::markup(
			array(
				'page'     => 2,
				'per_page' => 25,
				'total'    => 60,
				'url'      => $url,
				'labels'   => 'time',
			)
		);
		bwc_same( true, str_contains( $time, '<a class="bwc-button bwc-button--small bwc-pagination__prev" href="https://example.test/?paged=1"><span class="bwc-button__label">Newer</span></a>' ), 'newer' );
		bwc_same( true, str_contains( $time, '<span class="bwc-button__label">Older</span>' ), 'older' );
		bwc_same( false, str_contains( $time, 'bwc-pagination__jump' ), 'newer and older have no page box' );

		$admin = Pagination::markup(
			array(
				'page'     => 3,
				'per_page' => 20,
				'total'    => 71,
				'url'      => static fn( int $page ): string => 'https://example.test/wp-admin/admin.php?page=acme-links&show=broken&f%5Bbrand%5D=northwind&paged=' . $page,
			)
		);
		bwc_same( true, str_contains( $admin, 'action="https://example.test/wp-admin/admin.php"><input type="hidden" name="page" value="acme-links"><input type="hidden" name="show" value="broken"><input type="hidden" name="f[brand]" value="northwind"><label' ), 'the rest of the URL rides along, nested arguments too' );
		bwc_same( true, str_contains( $admin, 'name="paged" value="3" min="1" max="4"' ), 'current page and the last one' );

		$pretty = Pagination::markup(
			array(
				'page'     => 2,
				'per_page' => 20,
				'total'    => 71,
				'url'      => static fn( int $page ): string => 'https://example.test/links/page/' . $page . '/',
			)
		);
		bwc_same( true, str_contains( $pretty, '<span class="bwc-pagination__jump">Page 2 of 4</span>' ), 'URLs without a page argument get plain text' );

		$one = Pagination::markup(
			array(
				'page'     => 1,
				'per_page' => 20,
				'total'    => 7,
				'url'      => $url,
			)
		);
		bwc_same( '<div class="bwc-pagination"><span class="bwc-pagination__summary">Showing 1–7 of 7</span></div>', $one, 'one page: just the count' );
		bwc_same(
			true,
			str_contains(
				Pagination::markup(
					array(
						'page'     => 1,
						'per_page' => 20,
						'total'    => 0,
						'url'      => $url,
					)
				),
				'Nothing to show.'
			),
			'empty'
		);
	}
);

bwc_test(
	'copy button: hidden until the script, named by its label, carries the value',
	static function (): void {
		bwc_component_kit();
		$icon = CopyButton::markup(
			array(
				'value' => 'https://x.test/go/a?b=1&c=2',
				'label' => 'Copy https://x.test/go/a',
			)
		);
		bwc_same( true, str_starts_with( $icon, '<button type="button" class="bwc-button bwc-button--small bwc-button--icon bwc-copy" data-bwc-copy="https://x.test/go/a?b=1&amp;c=2" aria-label="Copy https://x.test/go/a" hidden><span class="bwc-copy__icon" aria-hidden="true">' ), 'icon button' );
		bwc_same( true, str_contains( $icon, '<span class="bwc-copy__done" aria-hidden="true">' ), 'the check it turns into' );
		$text = CopyButton::markup(
			array(
				'value'     => 'a@x.test',
				'label'     => 'Copy emails',
				'icon_only' => false,
				'size'      => 'regular',
			)
		);
		bwc_same( true, str_starts_with( $text, '<button type="button" class="bwc-button bwc-copy" data-bwc-copy="a@x.test" hidden>' ), 'text button has no aria-label' );
		bwc_same( true, str_ends_with( $text, '<span class="bwc-copy__text" data-bwc-copy-text>Copy emails</span></button>' ), 'visible label' );
	}
);

bwc_test(
	'table: caption, sort links say the new direction, bulk boxes are named, states, empty',
	static function (): void {
		bwc_component_kit();
		$html = Table::markup(
			array(
				'caption' => 'Offers to review',
				'columns' => array(
					'offer'  => array(
						'label' => 'Offer',
						'sort'  => array(
							'url_asc'  => 'https://example.test/?o=asc',
							'url_desc' => 'https://example.test/?o=desc',
							'current'  => 'asc',
						),
					),
					'clicks' => array(
						'label' => 'Clicks',
						'align' => 'end',
						'width' => 'narrow',
					),
					'edit'   => array(
						'label'      => 'Actions',
						'hide_label' => true,
					),
				),
				'rows'    => array(
					array(
						'id'    => '7',
						'state' => 'current',
						'cells' => array(
							'offer'  => '<strong>$50 bonus</strong>',
							'clicks' => '1,342',
							'edit'   => '',
						),
					),
					array(
						'id'    => '8',
						'label' => 'Spring sale',
						'state' => 'quiet',
						'cells' => array(
							'offer'  => 'Spring',
							'clicks' => '3',
						),
					),
				),
				'bulk'    => array(
					'name'    => 'offers',
					'label'   => 'Action for the selected offers',
					'actions' => array( 'publish' => 'Publish' ),
				),
			)
		);
		bwc_same( true, str_starts_with( $html, '<div class="bwc-table-bulk"><label class="bwc-visually-hidden" for="bwc-test-bulk-1">Action for the selected offers</label><select class="bwc-control" id="bwc-test-bulk-1" name="offers_action"><option value="">Bulk actions</option><option value="publish">Publish</option></select><button type="submit" class="bwc-button bwc-button--small"><span class="bwc-button__label">Apply</span></button></div>' ), 'bulk action picker' );
		bwc_same( true, str_contains( $html, '<div class="bwc-table-wrap" tabindex="0" role="region" data-bwc-table-scroll aria-label="Offers to review, table"><table class="bwc-table"><caption class="bwc-visually-hidden">Offers to review</caption><colgroup><col class="bwc-table__col bwc-table__col--check"><col class="bwc-table__col"><col class="bwc-table__col bwc-table__col--narrow"><col class="bwc-table__col"></colgroup>' ), 'scrolling box, caption, column widths' );
		bwc_same( true, str_contains( $html, '<th scope="col" class="bwc-table__check"><input type="checkbox" data-bwc-select-all hidden aria-label="Select all"></th>' ), 'select all waits for the script' );
		bwc_same( 1, preg_match( '#<th scope="col" aria-sort="ascending"><a class="bwc-table__sort is-current" href="https://example\\.test/\\?o=desc" aria-describedby="(bwc-test-sort-hint-\\d+)"><span>Offer</span>.*?<span id="\\1" hidden>Sort by Offer, descending</span></a></th>#', $html ), 'sorted column: named by its label, links to the other direction and says so in its description' );
		bwc_same( true, str_contains( $html, '<th scope="col" class="bwc-table__number">Clicks</th><th scope="col"><span class="bwc-visually-hidden">Actions</span></th>' ), 'number column and hidden label' );
		bwc_same( true, str_contains( $html, '<tr class="bwc-table__row is-current"><td class="bwc-table__check"><input type="checkbox" name="offers[]" value="7" aria-label="Select $50 bonus"></td><td><strong>$50 bonus</strong></td><td class="bwc-table__number">1,342</td>' ), 'row box named from the first cell' );
		bwc_same( true, str_contains( $html, '<tr class="bwc-table__row is-quiet"><td class="bwc-table__check"><input type="checkbox" name="offers[]" value="8" aria-label="Select Spring sale">' ), 'or from its label' );

		$empty = Table::markup(
			array(
				'caption'   => 'Programs',
				'columns'   => array(
					'a' => array( 'label' => 'A' ),
					'b' => array( 'label' => 'B' ),
				),
				'rows'      => array(),
				'compact'   => true,
				'min_width' => 'none',
			)
		);
		bwc_same( true, str_contains( $empty, '<table class="bwc-table bwc-table--compact bwc-table--fluid">' ), 'compact, no minimum width' );
		bwc_same( true, str_contains( $empty, '<td class="bwc-table__empty" colspan="2">Nothing to show.</td>' ), 'empty row spans the columns' );
	}
);

bwc_test(
	'grouped list: one colgroup everywhere, a hidden header per table, the rest folded',
	static function (): void {
		bwc_component_kit();
		$html     = GroupedList::markup(
			array(
				'caption' => 'Links',
				'columns' => array(
					'link'   => array(
						'label' => 'Link',
						'width' => 'wide',
					),
					'clicks' => array(
						'label' => 'Clicks',
						'align' => 'end',
					),
				),
				'groups'  => array(
					array(
						'title'      => 'Northwind',
						'meta'       => '1,709 clicks',
						'lead'       => array(
							array(
								'cells' => array(
									'link'   => 'go/northwind',
									'clicks' => '686',
								),
							),
						),
						'rest'       => array(
							array(
								'cells' => array(
									'link'   => 'go/northwind-50',
									'clicks' => '177',
								),
							),
						),
						'rest_label' => '1 offer link',
					),
				),
			)
		);
		$colgroup = '<colgroup><col class="bwc-table__col bwc-table__col--wide"><col class="bwc-table__col"></colgroup>';
		bwc_same( 3, substr_count( $html, $colgroup ), 'the visible column row and both tables share the colgroup' );
		bwc_same( true, str_contains( $html, '<table class="bwc-grouped__columns" aria-hidden="true">' ), 'the visible column row is decorative' );
		bwc_same( true, str_contains( $html, '<section class="bwc-grouped__group" aria-labelledby="bwc-test-group-1"><div class="bwc-grouped__head"><h3 class="bwc-grouped__title" id="bwc-test-group-1">Northwind</h3><p class="bwc-grouped__meta">1,709 clicks</p></div>' ), 'group head' );
		bwc_same( 2, substr_count( $html, '<thead class="bwc-visually-hidden"><tr><th scope="col">Link</th><th scope="col" class="bwc-table__number">Clicks</th></tr></thead>' ), 'each table has its own hidden header' );
		bwc_same( true, str_contains( $html, '<span class="bwc-grouped__more-show">Show 1 offer link</span><span class="bwc-grouped__more-hide">Hide 1 offer link</span>' ), 'show and hide labels' );
	}
);

bwc_test(
	'collapsible group, expand all and feed: filter hooks, tags, details',
	static function (): void {
		bwc_component_kit();
		$group = Collapsible::markup(
			array(
				'title'        => 'Tribesigns',
				'open'         => true,
				'hook'         => 'filter-group',
				'tags'         => array(
					array(
						'text' => '2 added',
						'tone' => 'success',
					),
				),
				'content_html' => 'B',
				'more'         => '3 more',
			)
		);
		bwc_same( true, str_starts_with( $group, '<details class="bwc-group" open data-bwc-filter-group><summary class="bwc-group__summary"><span class="bwc-group__chevron" aria-hidden="true"><svg' ), 'open, takes part in filtering' );
		bwc_same( true, str_ends_with( $group, '<span class="bwc-group__title">Tribesigns</span><span class="bwc-group__tags"><span class="bwc-pill bwc-pill--success bwc-pill--square">2 added</span></span></summary><div class="bwc-group__body">B</div><p class="bwc-group__more">3 more</p></details>' ), 'tags, body, more' );

		bwc_same( '<button type="button" class="bwc-button bwc-button--small bwc-button--link" data-bwc-toggle-details="feed" aria-controls="feed" hidden><span class="bwc-button__label" data-bwc-toggle-details-label>Expand all</span></button>', ExpandAll::markup( array( 'target' => 'feed' ) ), 'expand all waits for the script' );

		bwc_same(
			'<ul class="bwc-feed"><li class="bwc-feed__item" data-bwc-kind="problems"><span class="bwc-feed__time">11:15 am</span><span class="bwc-feed__kind"><span class="bwc-pill bwc-pill--danger bwc-pill--square">Rejected</span></span><span class="bwc-feed__text">One offer &amp; one brand <span class="bwc-feed__link"><a href="#">Review</a></span><ul class="bwc-feed__details"><li>Wrong site</li></ul></span></li></ul>',
			Feed::markup(
				array(
					'items' => array(
						array(
							'time'      => '11:15 am',
							'kind'      => array(
								'text' => 'Rejected',
								'tone' => 'danger',
							),
							'text'      => 'One offer & one brand',
							'link_html' => '<a href="#">Review</a>',
							'details'   => array( 'Wrong site' ),
							'filter'    => 'problems',
						),
					),
				)
			),
			'feed'
		);
	}
);

bwc_test(
	'timeline, run list and reference table',
	static function (): void {
		bwc_component_kit();
		$timeline = Timeline::markup(
			array(
				'title' => 'Timeline',
				'days'  => array(
					array(
						'label'  => 'Today',
						'events' => array(
							array(
								'time' => '14:02',
								'text' => 'Watched lesson 3',
								'meta' => '6 min',
								'kind' => 'video',
								'icon' => 'video',
							),
						),
					),
				),
				'foot'  => 'Site time.',
			)
		);
		bwc_same( true, str_starts_with( $timeline, '<section class="bwc-timeline" aria-labelledby="bwc-test-timeline-1"><h3 class="bwc-timeline__title" id="bwc-test-timeline-1">Timeline</h3><ol class="bwc-timeline__list"><li class="bwc-timeline__day"><h4 class="bwc-timeline__day-label">Today</h4><ol class="bwc-timeline__events"><li class="bwc-timeline__event bwc-timeline__event--video"><span class="bwc-timeline__icon" aria-hidden="true"><svg' ), 'days are list items with headings' );
		bwc_same( true, str_ends_with( $timeline, '<span class="bwc-timeline__text">Watched lesson 3<span class="bwc-timeline__meta">14:02 · 6 min</span></span></li></ol></li></ol><p class="bwc-timeline__foot">Site time.</p></section>' ), 'event text and meta' );
		bwc_same(
			true,
			str_contains(
				Timeline::markup(
					array(
						'title' => 'T',
						'days'  => array(),
						'empty' => 'Nothing yet.',
					)
				),
				'<p class="bwc-empty bwc-empty--inline">Nothing yet.</p>'
			),
			'empty timeline'
		);

		bwc_same(
			'<nav class="bwc-runs" aria-label="Runs"><h3 class="bwc-runs__heading">Today</h3><ul class="bwc-runs__list"><li><a class="bwc-run is-current" href="https://example.test/r1" aria-current="page"><span class="bwc-run__top"><span class="bwc-run__title">Daily run</span><span class="bwc-run__time">11:12 am</span></span><span class="bwc-run__line">29 added</span><span class="bwc-run__problem"><span class="bwc-run__dot" aria-hidden="true"></span>1 problem</span></a></li></ul></nav>',
			RunList::markup(
				array(
					'label'    => 'Runs',
					'sections' => array(
						array(
							'heading' => 'Today',
							'items'   => array(
								array(
									'title'   => 'Daily run',
									'time'    => '11:12 am',
									'line'    => '29 added',
									'problem' => '1 problem',
									'url'     => 'https://example.test/r1',
									'current' => true,
								),
							),
						),
					),
				)
			),
			'run list'
		);

		$reference = Reference::markup(
			array(
				'summary' => 'Dynamic tags (1)',
				'columns' => array(
					'tag'  => 'Tag',
					'what' => 'What it shows',
				),
				'mono'    => array( 'tag' ),
				'rows'    => array(
					array(
						'tag'  => '{echo:bc_percent_off}',
						'what' => 'The <discount>',
					),
				),
			)
		);
		bwc_same( true, str_starts_with( $reference, '<details class="bwc-reference"><summary class="bwc-reference__summary">' ), 'folded' );
		bwc_same( true, str_contains( $reference, '<span class="bwc-reference__code"><code>{echo:bc_percent_off}</code><button type="button" class="bwc-button bwc-button--small bwc-button--icon bwc-copy" data-bwc-copy="{echo:bc_percent_off}" aria-label="Copy {echo:bc_percent_off}" hidden>' ), 'mono cell with a copy button' );
		bwc_same( true, str_contains( $reference, '<td>The &lt;discount&gt;</td>' ), 'other cells escaped' );
	}
);
