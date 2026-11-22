<?php
/**
 * Markup snapshots for the components. Loaded by run.php.
 *
 * A change to any expected string here is a change to the kit's HTML and
 * should be reviewed as one.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Component\Avatar;
use BlissWebConcept\AdminKit\Component\Callout;
use BlissWebConcept\AdminKit\Component\Card;
use BlissWebConcept\AdminKit\Component\Cluster;
use BlissWebConcept\AdminKit\Component\EmptyState;
use BlissWebConcept\AdminKit\Component\Facts;
use BlissWebConcept\AdminKit\Component\Features;
use BlissWebConcept\AdminKit\Component\Grid;
use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Icon;
use BlissWebConcept\AdminKit\Component\Legend;
use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Component\Panel;
use BlissWebConcept\AdminKit\Component\Pill;
use BlissWebConcept\AdminKit\Component\Placeholder;
use BlissWebConcept\AdminKit\Component\SplitView;
use BlissWebConcept\AdminKit\Component\Stack;
use BlissWebConcept\AdminKit\Component\StatusCard;
use BlissWebConcept\AdminKit\Component\StatusDot;
use BlissWebConcept\AdminKit\Component\Tile;
use BlissWebConcept\AdminKit\Component\Tiles;
use BlissWebConcept\AdminKit\Shell\Nav;
use BlissWebConcept\AdminKit\Shell\PageHead;

defined( 'ABSPATH' ) || exit;

/**
 * Boots a fresh test kit and resets ids so snapshots are stable.
 */
function bwc_component_kit(): void {
	bwc_test_kit();
	Html::reset_ids();
}

bwc_test(
	'a missing required prop throws while debugging',
	static function (): void {
		bwc_component_kit();
		bwc_throws( static fn() => Pill::markup( array() ), 'pill without text' );
		bwc_throws( static fn() => Tile::markup( array( 'label' => '' ) ), 'tile with empty label' );
	}
);

bwc_test(
	'icon: decorative by default, labelled when asked, nothing for an unknown name',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<svg class="bwc-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>',
			Icon::markup(
				array(
					'name' => 'close',
					'size' => 14,
				)
			),
			'decorative'
		);
		bwc_same(
			true,
			str_contains(
				Icon::markup(
					array(
						'name'  => 'check',
						'label' => 'Works',
					)
				),
				'role="img" aria-label="Works"'
			),
			'labelled'
		);
		bwc_same( '', Icon::markup( array( 'name' => 'no-such-icon' ) ), 'unknown' );
		Icon::register( 'star', '<path d="M12 3l3 6 6 1-4.5 4 1 6-5.5-3-5.5 3 1-6L3 10l6-1z"/>' );
		bwc_same( true, str_contains( Icon::markup( array( 'name' => 'star' ) ), '<path d="M12 3l3 6' ), 'registered by a plugin' );
		bwc_same( 27, count( Icon::names() ), 'kit icons plus the registered one' );
	}
);

bwc_test(
	'pill: tones, dot, square, and an unknown tone falls back to neutral',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<span class="bwc-pill bwc-pill--success"><span class="bwc-pill__dot" aria-hidden="true"></span>Connected</span>',
			Pill::markup(
				array(
					'text' => 'Connected',
					'tone' => 'success',
					'dot'  => true,
				)
			),
			'success with dot'
		);
		bwc_same(
			'<span class="bwc-pill bwc-pill--neutral bwc-pill--square">Old &amp; new</span>',
			Pill::markup(
				array(
					'text'  => 'Old & new',
					'tone'  => 'sparkly',
					'shape' => 'square',
				)
			),
			'square, unknown tone'
		);
	}
);

bwc_test(
	'nav: tabs with the current item, badges with a spoken phrase, zero badges hidden',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<nav class="bwc-nav bwc-nav--tabs" aria-label="Sections" data-bwc-scroll-current>'
			. '<a class="bwc-nav__item is-current" href="https://example.test/a" aria-current="page"><span class="bwc-nav__label">Settings</span></a>'
			. '<a class="bwc-nav__item" href="https://example.test/b"><span class="bwc-nav__label">Links</span><span class="bwc-nav__badge bwc-nav__badge--danger">1<span class="bwc-visually-hidden"> to check</span></span></a>'
			. '<a class="bwc-nav__item" href="https://example.test/c"><span class="bwc-nav__label">Networks</span><span class="bwc-nav__badge">1,365<span class="bwc-visually-hidden"> waiting</span></span></a>'
			. '<a class="bwc-nav__item" href="https://example.test/d"><span class="bwc-nav__label">Activity</span></a>'
			. '</nav>',
			Nav::markup(
				array(
					'label'   => 'Sections',
					'current' => 'settings',
					'items'   => array(
						'settings' => array(
							'label' => 'Settings',
							'url'   => 'https://example.test/a',
						),
						'links'    => array(
							'label'      => 'Links',
							'url'        => 'https://example.test/b',
							'badge'      => 1,
							'badge_tone' => 'danger',
						),
						'networks' => array(
							'label' => 'Networks',
							'url'   => 'https://example.test/c',
							'badge' => 1365,
						),
						'activity' => array(
							'label' => 'Activity',
							'url'   => 'https://example.test/d',
							'badge' => 0,
						),
					),
				)
			),
			'tabs'
		);
	}
);

bwc_test(
	'nav: side menu with meta, and in-page items marked for the script',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<nav class="bwc-nav bwc-nav--side" aria-label="Setting groups"><a class="bwc-nav__item is-current" href="#offers" aria-current="true" data-bwc-nav-item="offers"><span class="bwc-nav__label">Offers</span><span class="bwc-nav__meta" data-bwc-nav-meta>3 of 4 on</span></a></nav>',
			Nav::markup(
				array(
					'label'   => 'Setting groups',
					'layout'  => 'side',
					'current' => 'offers',
					'items'   => array(
						'offers' => array(
							'label' => 'Offers',
							'url'   => '#offers',
							'meta'  => '3 of 4 on',
							'hook'  => 'offers',
						),
					),
				)
			),
			'side'
		);
	}
);

bwc_test(
	'page head: title, description, meta and actions',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<div class="bwc-head"><div class="bwc-head__text"><h3 class="bwc-head__title">Networks</h3><p class="bwc-head__description">Coupons your networks offer you.</p></div><div class="bwc-head__actions"><span class="bwc-head__meta">Next fetch: tomorrow</span><a href="#">Fetch now</a></div></div>',
			PageHead::markup(
				array(
					'title'        => 'Networks',
					'description'  => 'Coupons your networks offer you.',
					'level'        => 3,
					'meta'         => 'Next fetch: tomorrow',
					'actions_html' => '<a href="#">Fetch now</a>',
				)
			),
			'head'
		);
	}
);

bwc_test(
	'layout helpers: stack, cluster, grid and split pick from fixed sizes',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<section class="bwc-stack bwc-stack--gap-6"><p>a</p><p>b</p></section>',
			Stack::markup(
				array(
					'items_html' => array( '<p>a</p>', '<p>b</p>' ),
					'gap'        => 6,
					'tag'        => 'section',
				)
			),
			'stack'
		);
		bwc_same(
			'<div class="bwc-stack bwc-stack--gap-4"><p>a</p></div>',
			Stack::markup(
				array(
					'items_html' => array( '<p>a</p>' ),
					'gap'        => 99,
				)
			),
			'stack falls back to gap 4'
		);
		bwc_same(
			'<div class="bwc-cluster bwc-cluster--gap-2 bwc-cluster--align-baseline bwc-cluster--between"><b>a</b></div>',
			Cluster::markup(
				array(
					'items_html' => array( '<b>a</b>' ),
					'align'      => 'baseline',
					'justify'    => 'between',
				)
			),
			'cluster'
		);
		bwc_same(
			'<div class="bwc-grid bwc-grid--min-narrow bwc-grid--gap-3 bwc-grid--six"><i>a</i></div>',
			Grid::markup(
				array(
					'items_html' => array( '<i>a</i>' ),
					'min'        => 'narrow',
					'columns'    => 'six',
				)
			),
			'grid'
		);
		bwc_same(
			'<div class="bwc-split bwc-split--side-narrow"><aside class="bwc-split__side" aria-label="Runs">S</aside><div class="bwc-split__main">M</div></div>',
			SplitView::markup(
				array(
					'side_html'  => 'S',
					'main_html'  => 'M',
					'side_label' => 'Runs',
					'side_width' => 'narrow',
				)
			),
			'split'
		);
	}
);

bwc_test(
	'tiles: label before value, numbers formatted, tones, links and soft tiles',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<section class="bwc-tiles" aria-label="Last 30 days"><div class="bwc-tile"><span class="bwc-tile__label">Clicks, all time</span><span class="bwc-tile__value">28,660</span></div><a class="bwc-tile bwc-tile--danger bwc-tile--link" href="https://example.test/x"><span class="bwc-tile__label">Need attention</span><span class="bwc-tile__value">2</span><span class="bwc-tile__note">1 broken</span></a></section>',
			Tiles::markup(
				array(
					'label' => 'Last 30 days',
					'tiles' => array(
						array(
							'label' => 'Clicks, all time',
							'value' => 28660,
						),
						array(
							'label' => 'Need attention',
							'value' => 2,
							'note'  => '1 broken',
							'tone'  => 'danger',
							'url'   => 'https://example.test/x',
						),
					),
				)
			),
			'tiles'
		);
		bwc_same(
			'<div class="bwc-tile bwc-tile--soft"><span class="bwc-tile__label">Added</span><span class="bwc-tile__value">25 of 27</span></div>',
			Tile::markup(
				array(
					'label' => 'Added',
					'value' => '25 of 27',
					'soft'  => true,
				)
			),
			'soft tile, text value'
		);
	}
);

bwc_test(
	'status dot: visible label, or a label for screen readers only',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<span class="bwc-dot bwc-dot--success"><span class="bwc-dot__mark" aria-hidden="true"></span><span class="bwc-dot__label">Works</span></span>',
			StatusDot::markup(
				array(
					'tone'  => 'success',
					'label' => 'Works',
				)
			),
			'visible'
		);
		bwc_same(
			'<span class="bwc-dot bwc-dot--neutral vi-slipping"><span class="bwc-dot__mark" aria-hidden="true"></span><span class="bwc-visually-hidden">Slipping</span></span>',
			StatusDot::markup(
				array(
					'tone'       => 'none',
					'label'      => 'Slipping',
					'hide_label' => true,
					'class'      => 'vi-slipping',
				)
			),
			'hidden label, plugin class'
		);
	}
);

bwc_test(
	'notice: tone icon, paused prefix, actions, dismiss link, live roles',
	static function (): void {
		bwc_component_kit();
		$paused = Notice::markup(
			array(
				'tone' => 'paused',
				'text' => 'Turn on Short links to use this.',
			)
		);
		bwc_same( true, str_contains( $paused, '<div class="bwc-notice bwc-notice--paused">' ), 'paused tone, no role at load' );
		bwc_same( true, str_contains( $paused, '<p><strong class="bwc-notice__title">Paused:</strong> Turn on Short links to use this.</p>' ), 'paused prefix' );
		bwc_same( true, str_contains( $paused, '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>' ), 'pause icon' );

		$danger = Notice::markup(
			array(
				'tone'         => 'danger',
				'title'        => '/go/nexcess is broken.',
				'text'         => 'Visitors get an error.',
				'actions_html' => '<a href="#">Edit link</a>',
				'dismiss_url'  => 'https://example.test/dismiss?n=1&x=2',
				'live'         => true,
			)
		);
		bwc_same( true, str_contains( $danger, '<div class="bwc-notice bwc-notice--danger" role="alert" data-bwc-notice>' ), 'live danger is an alert' );
		bwc_same( true, str_contains( $danger, '<div class="bwc-notice__actions"><a href="#">Edit link</a></div>' ), 'actions' );
		bwc_same( true, str_contains( $danger, '<a class="bwc-notice__close" href="https://example.test/dismiss?n=1&#038;x=2" data-bwc-dismiss><span class="bwc-visually-hidden">Hide this notice</span>' ), 'dismiss link' );
		bwc_same(
			true,
			str_contains(
				Notice::markup(
					array(
						'tone' => 'success',
						'text' => 'Saved.',
						'live' => true,
					)
				),
				'role="status"'
			),
			'live success is a status'
		);
	}
);

bwc_test(
	'card: titled cards are labelled sections, untitled ones are divs',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<section class="bwc-card bwc-card--danger" id="danger-zone" aria-labelledby="bwc-test-card-title-1"><header class="bwc-card__head"><div class="bwc-card__titles"><h3 class="bwc-card__title" id="bwc-test-card-title-1">Remove data</h3><p class="bwc-card__subtitle">Can&#039;t be undone.</p></div><div class="bwc-card__actions"><a href="#">Help</a></div></header><div class="bwc-card__body"><p>Body</p></div><footer class="bwc-card__foot">Kept: offers</footer></section>',
			Card::markup(
				array(
					'title'             => 'Remove data',
					'level'             => 3,
					'subtitle'          => "Can't be undone.",
					'head_actions_html' => '<a href="#">Help</a>',
					'content_html'      => '<p>Body</p>',
					'foot'              => 'Kept: offers',
					'tone'              => 'danger',
					'id'                => 'danger-zone',
				)
			),
			'titled'
		);
		bwc_same( '<div class="bwc-card"><div class="bwc-card__body">B</div></div>', Card::markup( array( 'content_html' => 'B' ) ), 'untitled' );
	}
);

bwc_test(
	'panel: tabs with counts, toolbar, body, footer and note',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<section class="bwc-panel"><nav class="bwc-panel__tabs" aria-label="What to show" data-bwc-scroll-current><a class="bwc-panel__tab is-current" href="https://example.test/o" aria-current="page">Offers to review <span class="bwc-panel__count">365</span></a><a class="bwc-panel__tab" href="https://example.test/p">Programs <span class="bwc-panel__count">40 active</span></a></nav><div class="bwc-panel__toolbar">T</div><div class="bwc-panel__body">B</div><footer class="bwc-panel__footer">F</footer><p class="bwc-panel__note">N</p></section>',
			Panel::markup(
				array(
					'tabs'         => array(
						'offers'   => array(
							'label' => 'Offers to review',
							'url'   => 'https://example.test/o',
							'count' => 365,
						),
						'programs' => array(
							'label' => 'Programs',
							'url'   => 'https://example.test/p',
							'count' => '40 active',
						),
					),
					'current_tab'  => 'offers',
					'tabs_label'   => 'What to show',
					'toolbar_html' => 'T',
					'content_html' => 'B',
					'footer_html'  => 'F',
					'note'         => 'N',
				)
			),
			'panel'
		);
	}
);

bwc_test(
	'empty state and placeholder',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<p class="bwc-empty bwc-empty--boxed">No links match these filters.</p>',
			EmptyState::markup(
				array(
					'text'    => 'No links match these filters.',
					'variant' => 'boxed',
				)
			),
			'boxed'
		);
		bwc_same(
			'<div class="bwc-empty"><p class="bwc-empty__text">None yet.</p><div class="bwc-empty__action"><a href="#">Fetch now</a></div></div>',
			EmptyState::markup(
				array(
					'text'        => 'None yet.',
					'action_html' => '<a href="#">Fetch now</a>',
				)
			),
			'with action'
		);
		bwc_same(
			'<section class="bwc-placeholder"><p class="bwc-placeholder__lead">No coupons yet.</p><p class="bwc-placeholder__text">They show here once members join with one.</p></section>',
			Placeholder::markup(
				array(
					'lead' => 'No coupons yet.',
					'text' => 'They show here once members join with one.',
				)
			),
			'placeholder'
		);
	}
);

bwc_test(
	'status card: state pill, stats as a list, folded details, picked and dashed',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<section class="bwc-status-card is-current" aria-labelledby="bwc-test-status-card-1"><div class="bwc-status-card__head"><h3 class="bwc-status-card__title" id="bwc-test-status-card-1">impact.com</h3><span class="bwc-pill bwc-pill--success"><span class="bwc-pill__dot" aria-hidden="true"></span>Connected</span></div><dl class="bwc-status-card__stats"><div class="bwc-status-card__stat"><dt class="bwc-status-card__label">offers to review</dt><dd class="bwc-status-card__number">365</dd></div></dl><details class="bwc-status-card__details"><summary class="bwc-status-card__text">Last fetch today · OK</summary><p>1 new, 0 changed.</p></details><div class="bwc-status-card__actions"><a href="#">Fetch now</a></div></section>',
			StatusCard::markup(
				array(
					'title'        => 'impact.com',
					'state'        => array(
						'text' => 'Connected',
						'tone' => 'success',
					),
					'stats'        => array(
						array(
							'value' => 365,
							'label' => 'offers to review',
						),
					),
					'text'         => 'Last fetch today · OK',
					'details'      => '1 new, 0 changed.',
					'actions_html' => '<a href="#">Fetch now</a>',
					'picked'       => true,
				)
			),
			'connected'
		);
		bwc_same(
			true,
			str_contains(
				StatusCard::markup(
					array(
						'title'   => 'CJ',
						'state'   => array(
							'text' => 'Not set up',
							'tone' => 'neutral',
						),
						'variant' => 'dashed',
					)
				),
				'class="bwc-status-card bwc-status-card--dashed"'
			),
			'dashed'
		);
	}
);

bwc_test(
	'callout, key-value list, legend and avatar',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<div class="bwc-callout bwc-callout--warning" role="note"><span class="bwc-callout__mark" aria-hidden="true">!</span><div class="bwc-callout__body"><p>Numbers may be low.</p></div></div>',
			Callout::markup(
				array(
					'text' => 'Numbers may be low.',
					'mark' => 'warning',
				)
			),
			'callout'
		);
		bwc_same(
			'<dl class="bwc-facts bwc-facts--rows"><div class="bwc-facts__item"><dt class="bwc-facts__label">Joined</dt><dd class="bwc-facts__value">12 March 2026</dd></div><div class="bwc-facts__item"><dt class="bwc-facts__label">Coupon</dt><dd class="bwc-facts__value"><code>SPRING</code></dd></div></dl>',
			Facts::markup(
				array(
					'items'  => array(
						array(
							'label' => 'Joined',
							'value' => '12 March 2026',
						),
						array(
							'label'      => 'Coupon',
							'value_html' => '<code>SPRING</code>',
						),
					),
					'layout' => 'rows',
				)
			),
			'facts'
		);
		bwc_same(
			'<ul class="bwc-legend"><li class="bwc-legend__item"><span class="bwc-legend__swatch bwc-legend__swatch--series-1" aria-hidden="true"></span>Opened</li><li class="bwc-legend__item"><span class="bwc-legend__swatch bwc-legend__swatch--series-2 bwc-legend__swatch--dashed" aria-hidden="true"></span>Estimated</li></ul>',
			Legend::markup(
				array(
					'items' => array(
						array( 'label' => 'Opened' ),
						array(
							'label'  => 'Estimated',
							'series' => 2,
							'line'   => 'dashed',
						),
					),
				)
			),
			'legend'
		);
		bwc_same(
			'<span class="bwc-avatar bwc-avatar--large" aria-hidden="true">ÉK</span>',
			Avatar::markup(
				array(
					'name' => 'émile kowalski',
					'size' => 'large',
				)
			),
			'avatar, multibyte'
		);
		bwc_same( 'JD', Avatar::initials( 'john.doe@example.com' ), 'initials from an email' );
		bwc_same( '?', Avatar::initials( '   ' ), 'no name' );
	}
);

bwc_test(
	'large card, and the panel title and tab icons',
	static function (): void {
		bwc_component_kit();
		bwc_same(
			'<div class="bwc-card bwc-card--large"><div class="bwc-card__body">B</div></div>',
			Card::markup(
				array(
					'content_html' => 'B',
					'size'         => 'large',
				)
			),
			'large card'
		);

		$panel = Panel::markup(
			array(
				'title'        => 'Settings',
				'tabs'         => array(
					'alerts' => array(
						'label' => 'Notifications',
						'url'   => 'https://example.test/n',
						'icon'  => 'alert',
					),
				),
				'current_tab'  => 'alerts',
				'tabs_label'   => 'Setting groups',
				'content_html' => 'B',
			)
		);
		bwc_same( true, str_starts_with( $panel, '<section class="bwc-panel" aria-labelledby="bwc-test-panel-title-1"><h2 class="bwc-panel__title" id="bwc-test-panel-title-1">Settings</h2><nav class="bwc-panel__tabs" aria-label="Setting groups" data-bwc-scroll-current><a class="bwc-panel__tab is-current" href="https://example.test/n" aria-current="page"><svg class="bwc-icon"' ), 'titled panel, icon first in the tab' );
		bwc_same( true, str_contains( $panel, '</svg>Notifications</a></nav>' ), 'label after the icon' );
	}
);

bwc_test(
	'features: icon, title at the asked level, text; the icon is optional',
	static function (): void {
		bwc_component_kit();
		$html = Features::markup(
			array(
				'level' => 4,
				'items' => array(
					array(
						'icon'  => 'refresh',
						'title' => 'Daily fetch',
						'text'  => 'Every morning & night.',
					),
					array(
						'title'     => 'Short links',
						'text_html' => 'Each brand gets a <code>/go/</code> link.',
					),
				),
			)
		);
		bwc_same( true, str_starts_with( $html, '<ul class="bwc-features"><li class="bwc-features__item"><div class="bwc-features__head"><span class="bwc-features__icon" aria-hidden="true"><svg' ), 'icon square' );
		bwc_same( true, str_contains( $html, '<h4 class="bwc-features__title">Daily fetch</h4></div><p class="bwc-features__text">Every morning &amp; night.</p></li>' ), 'title and escaped text' );
		bwc_same( true, str_contains( $html, '<li class="bwc-features__item"><div class="bwc-features__head"><h4 class="bwc-features__title">Short links</h4></div><p class="bwc-features__text">Each brand gets a <code>/go/</code> link.</p></li></ul>' ), 'no icon, trusted text' );
		bwc_throws( static fn() => Features::markup( array() ), 'items required' );
	}
);
