<?php
/**
 * Plugin Name:       Admin Kit Gallery
 * Description:       Dev only: every Admin Kit component, field and screen in every state. Never released.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Bliss Web Concept
 * Author URI:        https://blisswebconcept.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       bwc-gallery
 *
 * @package BWCGallery
 */

namespace BWCGallery;

// First, before the imports: Plugin Check only looks near the top of the file.
defined( 'ABSPATH' ) || exit;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Avatar;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Button;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Callout;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Card;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Chips;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Cluster;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Collapsible;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\CopyButton;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\EmptyState;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\ExpandAll;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Facts;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Features;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Feed;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Grid;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\GroupedList;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Icon;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Input;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Legend;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Notice;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Pagination;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Panel;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Pill;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Placeholder;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Prefixed;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Reference;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\RunList;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Search;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Segmented;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Select;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\SplitView;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Stack;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\StatusCard;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\StatusDot;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Table;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Tiles;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Timeline;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Toggle;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Kit;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Shell\Nav;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Shell\PageHead;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Shell\Screen;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Storage\OptionStorage;


if ( ! is_readable( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Admin Kit Gallery: run composer install in the gallery folder.', 'bwc-gallery' ) . '</p></div>';
		}
	);
	return;
}

require_once __DIR__ . '/vendor-prefixed/autoload.php';
require_once __DIR__ . '/settings-demo.php';
require_once __DIR__ . '/list-demo.php';
require_once __DIR__ . '/class-demo-check-job.php';
require_once __DIR__ . '/class-demo-fetch-job.php';
require_once __DIR__ . '/jobs-demo.php';
require_once __DIR__ . '/report-demo.php';
require_once __DIR__ . '/axe-check.php';

/**
 * Boots the kit.
 */
function boot(): Kit {
	return Kit::boot(
		array(
			'id'          => 'bwc-gallery',
			'file'        => __FILE__,
			'version'     => '0.1.0',
			'text_domain' => 'bwc-gallery',
		)
	);
}

/**
 * WordPress's admin colour schemes and their theme colours (wp-base-styles),
 * so the gallery can preview the accent in each without changing the profile.
 *
 * @return array<string, array{ label: string, colour: string }>
 */
function schemes(): array {
	return array(
		'modern'    => array(
			'label'  => __( 'Modern', 'bwc-gallery' ),
			'colour' => '#3858e9',
		),
		'fresh'     => array(
			'label'  => __( 'Default', 'bwc-gallery' ),
			'colour' => '#007cba',
		),
		'blue'      => array(
			'label'  => __( 'Blue', 'bwc-gallery' ),
			'colour' => '#437aa8',
		),
		'coffee'    => array(
			'label'  => __( 'Coffee', 'bwc-gallery' ),
			'colour' => '#916745',
		),
		'ectoplasm' => array(
			'label'  => __( 'Ectoplasm', 'bwc-gallery' ),
			'colour' => '#646c3e',
		),
		'midnight'  => array(
			'label'  => __( 'Midnight', 'bwc-gallery' ),
			'colour' => '#cf4339',
		),
		'ocean'     => array(
			'label'  => __( 'Ocean', 'bwc-gallery' ),
			'colour' => '#567958',
		),
		'sunrise'   => array(
			'label'  => __( 'Sunrise', 'bwc-gallery' ),
			'colour' => '#ad631e',
		),
	);
}

/**
 * The colour scheme being previewed, or '' for the user's own.
 */
function previewed_scheme(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch on a dev screen.
	$scheme = isset( $_GET['bwc_scheme'] ) ? sanitize_key( wp_unslash( $_GET['bwc_scheme'] ) ) : '';
	return isset( schemes()[ $scheme ] ) ? $scheme : '';
}

add_action(
	'plugins_loaded',
	static function (): void {
		$kit = boot();
		// Field labels are translated, so describe fields at init, not earlier.
		add_action( 'init', static fn() => register_demo_fields( $kit ) );
		add_action( 'init', static fn() => register_settings_demo( $kit ) );

		add_action(
			'admin_menu',
			static function () use ( $kit ): void {
				$hook = add_menu_page(
					__( 'Admin Kit Gallery', 'bwc-gallery' ),
					__( 'Admin Kit', 'bwc-gallery' ),
					$kit->capability( 'gallery' ),
					'bwc-gallery',
					__NAMESPACE__ . '\\render',
					'dashicons-layout',
					81
				);
				$kit->assets()->load_on( (string) $hook );
			}
		);

		add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\demo_styles', 20 );
	}
);

/**
 * Styles for the gallery's own stand-in boxes and icon labels. Not part of the kit.
 */
function demo_styles(): void {
	$handle = Kit::instance()->assets()->handle();
	if ( ! wp_style_is( $handle ) ) {
		return;
	}
	wp_add_inline_style(
		$handle,
		'.bwc-gallery-box{padding:var(--bwc-space-2) var(--bwc-space-3);border:1px dashed var(--bwc-color-border-strong);border-radius:var(--bwc-radius-sm);background:var(--bwc-color-surface-soft);color:var(--bwc-color-text-muted);font-size:var(--bwc-font-size-sm)}'
		. '.bwc-gallery-icon{display:flex;align-items:center;gap:var(--bwc-space-2);color:var(--bwc-color-text)}'
		. '.bwc-gallery-pad{padding:var(--bwc-space-6)}.bwc-gallery-pad p{margin:0}'
		. '.bwc-gallery-switch{display:flex;align-items:center;justify-content:space-between;max-inline-size:360px;gap:var(--bwc-space-3)}'
		. '.bwc-gallery-row{display:flex;flex-wrap:wrap;align-items:center;gap:var(--bwc-space-2)}.bwc-gallery-row--between{justify-content:space-between}'
		. '.bwc-gallery-gap{margin-block-start:var(--bwc-space-4)}'
		. '.bwc-scope .bwc-gallery-help{margin:var(--bwc-space-1) 0 0;color:var(--bwc-color-text-muted);font-size:var(--bwc-font-size-md)}'
		. '.bwc-scope .bwc-gallery-tools{margin:0;color:var(--bwc-color-text-muted);font-size:var(--bwc-font-size-sm);text-align:end}'
		. '.bwc-gallery-schemes{font-size:var(--bwc-font-size-sm);color:var(--bwc-color-text-muted)}'
		. '.bwc-gallery-schemes a{margin-inline-start:var(--bwc-space-2);color:var(--bwc-color-accent)}'
		. '.bwc-gallery-schemes a[aria-current]{color:var(--bwc-color-text);font-weight:var(--bwc-font-weight-strong);text-decoration:none}'
		. '.bwc-gallery-icon code{font-size:var(--bwc-font-size-sm);background:none;padding:0}'
	);

	$scheme = previewed_scheme();
	if ( '' !== $scheme ) {
		wp_add_inline_style( $handle, '.bwc-scope--bwc-gallery{--wp-admin-theme-color:' . schemes()[ $scheme ]['colour'] . '}' );
	}
}

/**
 * Dismissing a gallery notice: nothing to remember on a dev screen, so it
 * only checks the request and answers.
 */
add_action(
	'wp_ajax_bwc_gallery_dismiss',
	static function (): void {
		check_ajax_referer( 'bwc_gallery_dismiss' );
		wp_send_json_success();
	}
);

/**
 * Gallery sections, in tab order.
 *
 * @return array<string, array{ label: string, render: callable(): string }>
 */
function sections(): array {
	return array(
		'overview'   => array(
			'label'  => __( 'Overview', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_overview',
		),
		'shell'      => array(
			'label'  => __( 'Shell', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_shell',
		),
		'layout'     => array(
			'label'  => __( 'Layout', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_layout',
		),
		'labels'     => array(
			'label'  => __( 'Labels and icons', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_labels',
		),
		'status'     => array(
			'label'  => __( 'Numbers and status', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_status',
		),
		'containers' => array(
			'label'  => __( 'Cards and panels', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_containers',
		),
		'fields'     => array(
			'label'  => __( 'Fields', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_fields',
		),
		'controls'   => array(
			'label'  => __( 'Controls', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_controls',
		),
		'data'       => array(
			'label'  => __( 'Data', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_data',
		),
		'axe'        => array(
			'label'  => __( 'Accessibility', 'bwc-gallery' ),
			'render' => __NAMESPACE__ . '\\section_axe',
		),
	);
}

/**
 * The URL of a gallery section, keeping the previewed colour scheme.
 *
 * @param string $section Section key.
 * @param string $scheme  Colour scheme to preview; null keeps the current one.
 */
function section_url( string $section, ?string $scheme = null ): string {
	$scheme = $scheme ?? previewed_scheme();
	$args   = array( 'section' => $section );
	if ( '' !== $scheme ) {
		$args['bwc_scheme'] = $scheme;
	}
	return add_query_arg( $args, admin_url( 'admin.php?page=bwc-gallery' ) );
}

/**
 * The colour scheme links.
 *
 * @param string $current Current section.
 */
function gallery_tools( string $current ): string {
	$scheme = previewed_scheme();
	$links  = array( '<a href="' . esc_url( section_url( $current, '' ) ) . '"' . ( '' === $scheme ? ' aria-current="true"' : '' ) . '>' . esc_html__( 'Yours', 'bwc-gallery' ) . '</a>' );
	foreach ( schemes() as $key => $info ) {
		$links[] = '<a href="' . esc_url( section_url( $current, $key ) ) . '"' . ( $key === $scheme ? ' aria-current="true"' : '' ) . '>' . esc_html( $info['label'] ) . '</a>';
	}

	return '<p class="bwc-gallery-tools"><span class="bwc-gallery-schemes">' . esc_html__( 'Colour scheme:', 'bwc-gallery' ) . ' ' . implode( ' ', $links ) . '</span></p>';
}

/**
 * One example: a section card titled with what it shows.
 *
 * @param string $title Example name.
 * @param string $html  Example markup.
 */
function example( string $title, string $html ): string {
	return Card::markup(
		array(
			'title'        => $title,
			'level'        => 3,
			'size'         => 'large',
			'content_html' => $html,
		)
	);
}

/**
 * A run of examples under a page head.
 *
 * @param string       $title       Section title.
 * @param string       $description Section description.
 * @param list<string> $examples    Example markup.
 */
function section( string $title, string $description, array $examples ): string {
	return Stack::markup(
		array(
			'gap'        => 6,
			'tag'        => 'section',
			'items_html' => array_merge(
				array(
					PageHead::markup(
						array(
							'title'       => $title,
							'description' => $description,
						)
					),
				),
				$examples
			),
		)
	);
}

/**
 * A grey box standing in for content in the layout examples.
 *
 * @param string $text Box text.
 */
function box( string $text ): string {
	return '<div class="bwc-gallery-box">' . esc_html( $text ) . '</div>';
}

/**
 * Shell: navigation and page heads.
 */
function section_shell(): string {
	$items = array(
		'settings' => array(
			'label' => __( 'Settings', 'bwc-gallery' ),
			'url'   => '#settings',
		),
		'links'    => array(
			'label'      => __( 'Links', 'bwc-gallery' ),
			'url'        => '#links',
			'badge'      => 2,
			'badge_tone' => 'danger',
		),
		'networks' => array(
			'label' => __( 'Networks', 'bwc-gallery' ),
			'url'   => '#networks',
			'badge' => 1365,
		),
		'activity' => array(
			'label' => __( 'Activity', 'bwc-gallery' ),
			'url'   => '#activity',
			'badge' => 0,
		),
	);

	$side = array(
		'offers'  => array(
			'label' => __( 'Offers', 'bwc-gallery' ),
			'url'   => '#offers',
			'meta'  => __( '3 of 4 on', 'bwc-gallery' ),
			'hook'  => 'offers',
		),
		'brands'  => array(
			'label' => __( 'Brands', 'bwc-gallery' ),
			'url'   => '#brands',
			'meta'  => __( '2 of 2 on', 'bwc-gallery' ),
			'hook'  => 'brands',
		),
		'agent'   => array(
			'label' => __( 'Daily agent', 'bwc-gallery' ),
			'url'   => '#agent',
			'meta'  => __( 'Off', 'bwc-gallery' ),
			'hook'  => 'agent',
		),
		'cleanup' => array(
			'label'      => __( 'Clean-up', 'bwc-gallery' ),
			'url'        => '#cleanup',
			'badge'      => 4,
			'badge_tone' => 'info',
			'hook'       => 'cleanup',
		),
	);

	return section(
		__( 'Shell', 'bwc-gallery' ),
		__( 'The screen frame, page navigation and section heads. The tabs at the top of this screen are the tabs navigation.', 'bwc-gallery' ),
		array(
			example(
				__( 'Tabs, with a warning badge, a count and an empty badge', 'bwc-gallery' ),
				Nav::markup(
					array(
						'label'   => __( 'Example tabs', 'bwc-gallery' ),
						'current' => 'links',
						'items'   => $items,
					)
				)
			),
			example(
				__( 'Side menu, with notes', 'bwc-gallery' ),
				SplitView::markup(
					array(
						'side_label' => __( 'Setting groups', 'bwc-gallery' ),
						'side_width' => 'narrow',
						'side_html'  => Nav::markup(
							array(
								'label'   => __( 'Setting groups', 'bwc-gallery' ),
								'layout'  => 'side',
								'current' => 'offers',
								'items'   => $side,
							)
						),
						'main_html'  => box( __( 'The chosen group shows here.', 'bwc-gallery' ) ),
					)
				)
			),
			example(
				__( 'Section head with a description, a note and an action', 'bwc-gallery' ),
				PageHead::markup(
					array(
						'title'        => __( 'Networks', 'bwc-gallery' ),
						'description'  => __( 'Coupons your affiliate networks offer you. Pick the ones to publish.', 'bwc-gallery' ),
						'meta'         => __( 'Next fetch: tomorrow at 5:00 am', 'bwc-gallery' ),
						'actions_html' => Button::markup(
							array(
								'label'   => __( 'Fetch now', 'bwc-gallery' ),
								'url'     => '#',
								'variant' => 'secondary',
							)
						),
					)
				)
			),
			example(
				__( 'Section head, title only', 'bwc-gallery' ),
				PageHead::markup(
					array(
						'title' => __( 'Activity', 'bwc-gallery' ),
						'level' => 3,
					)
				)
			),
		)
	);
}

/**
 * Layout helpers.
 */
function section_layout(): string {
	$boxes = static fn( int $n ): array => array_map(
		/* translators: %d: box number. */
		static fn( int $i ): string => box( sprintf( __( 'Item %d', 'bwc-gallery' ), $i ) ),
		range( 1, $n )
	);

	$examples = array();
	foreach ( array( 1, 3, 6 ) as $gap ) {
		$examples[] = example(
			/* translators: %d: gap size. */
			sprintf( __( 'Stack, gap %d', 'bwc-gallery' ), $gap ),
			Stack::markup(
				array(
					'gap'        => $gap,
					'items_html' => $boxes( 3 ),
				)
			)
		);
	}

	$examples[] = example(
		__( 'Cluster, wrapping, gap 2', 'bwc-gallery' ),
		Cluster::markup( array( 'items_html' => $boxes( 8 ) ) )
	);
	$examples[] = example(
		__( 'Cluster, space between', 'bwc-gallery' ),
		Cluster::markup(
			array(
				'items_html' => $boxes( 2 ),
				'justify'    => 'between',
			)
		)
	);
	$examples[] = example(
		__( 'Cluster, pushed to the end', 'bwc-gallery' ),
		Cluster::markup(
			array(
				'items_html' => $boxes( 2 ),
				'justify'    => 'end',
			)
		)
	);

	foreach ( array( 'narrow', 'medium', 'wide' ) as $min ) {
		$examples[] = example(
			/* translators: %s: column size. */
			sprintf( __( 'Grid, %s columns', 'bwc-gallery' ), $min ),
			Grid::markup(
				array(
					'items_html' => $boxes( 6 ),
					'min'        => $min,
				)
			)
		);
	}
	$examples[] = example(
		__( 'Grid, six across (three, then two on narrow screens)', 'bwc-gallery' ),
		Grid::markup(
			array(
				'items_html' => $boxes( 6 ),
				'columns'    => 'six',
			)
		)
	);

	foreach ( array( 'narrow', 'medium' ) as $width ) {
		$examples[] = example(
			/* translators: %s: side width. */
			sprintf( __( 'Split view, %s side (stacks on narrow screens)', 'bwc-gallery' ), $width ),
			SplitView::markup(
				array(
					'side_html'  => box( __( 'Side', 'bwc-gallery' ) ),
					'main_html'  => box( __( 'Main', 'bwc-gallery' ) ),
					/* translators: %s: side width. */
					'side_label' => sprintf( __( 'Example side, %s', 'bwc-gallery' ), $width ),
					'side_width' => $width,
				)
			)
		);
	}

	return section(
		__( 'Layout', 'bwc-gallery' ),
		__( 'Stack, Cluster, Grid and Split view. Each takes a fixed set of sizes so screens space out the same way.', 'bwc-gallery' ),
		$examples
	);
}

/**
 * Pills, icons, dots, avatars, legends, key-value lists and callouts.
 */
function section_labels(): string {
	$pills = array();
	foreach ( array( 'neutral', 'success', 'warning', 'danger', 'info', 'purple' ) as $tone ) {
		$pills[] = Pill::markup(
			array(
				'text' => ucfirst( $tone ),
				'tone' => $tone,
			)
		);
	}
	$dotted = array_map(
		static fn( array $p ): string => Pill::markup( $p + array( 'dot' => true ) ),
		array(
			array(
				'text' => __( 'Connected', 'bwc-gallery' ),
				'tone' => 'success',
			),
			array(
				'text' => __( 'Needs a key', 'bwc-gallery' ),
				'tone' => 'warning',
			),
			array(
				'text' => __( 'Failed', 'bwc-gallery' ),
				'tone' => 'danger',
			),
			array(
				'text' => __( 'Not set up', 'bwc-gallery' ),
				'tone' => 'neutral',
			),
		)
	);
	$square = array(
		Pill::markup(
			array(
				'text'  => '307',
				'shape' => 'square',
			)
		),
		Pill::markup(
			array(
				'text'  => __( 'Code', 'bwc-gallery' ),
				'tone'  => 'info',
				'shape' => 'square',
			)
		),
	);

	$icons = array();
	foreach ( Icon::names() as $name ) {
		$icons[] = '<span class="bwc-gallery-icon">' . Icon::markup(
			array(
				'name' => $name,
				'size' => 20,
			)
		) . '<code>' . esc_html( $name ) . '</code></span>';
	}

	$dots = array();
	foreach (
		array(
			'success' => __( 'Works', 'bwc-gallery' ),
			'warning' => __( 'Slow', 'bwc-gallery' ),
			'danger'  => __( 'Broken', 'bwc-gallery' ),
			'info'    => __( 'Checking', 'bwc-gallery' ),
			'neutral' => __( 'Not checked', 'bwc-gallery' ),
		) as $tone => $label
	) {
		$dots[] = StatusDot::markup(
			array(
				'tone'  => $tone,
				'label' => $label,
			)
		);
	}
	$dots[] = StatusDot::markup(
		array(
			'tone'       => 'danger',
			'label'      => __( 'Broken', 'bwc-gallery' ),
			'hide_label' => true,
		)
	) . ' ' . esc_html__( '(label for screen readers only)', 'bwc-gallery' );

	$avatars = array(
		Avatar::markup( array( 'name' => 'Jordan Rivera' ) ),
		Avatar::markup( array( 'name' => 'émile zola' ) ),
		Avatar::markup( array( 'name' => 'jane.doe@example.com' ) ),
		Avatar::markup( array( 'name' => 'Ada' ) ),
		Avatar::markup(
			array(
				'name' => 'Grace Hopper',
				'size' => 'large',
			)
		),
		Avatar::markup(
			array(
				'name' => 'Ada Lovelace',
				'size' => 'small',
			)
		),
	);

	$series = array();
	foreach ( range( 1, 6 ) as $n ) {
		$series[] = array(
			/* translators: %d: series number. */
			'label'  => sprintf( __( 'Series %d', 'bwc-gallery' ), $n ),
			'series' => $n,
		);
	}
	$series[] = array(
		'label'  => __( 'Estimated', 'bwc-gallery' ),
		'series' => 1,
		'line'   => 'dashed',
	);

	$facts = array(
		array(
			'label' => __( 'Joined', 'bwc-gallery' ),
			'value' => '12 March 2026',
		),
		array(
			'label' => __( 'Plan', 'bwc-gallery' ),
			'value' => __( 'Yearly', 'bwc-gallery' ),
		),
		array(
			'label'      => __( 'Coupon', 'bwc-gallery' ),
			'value_html' => '<code>SPRING25</code>',
		),
		array(
			'label' => __( 'Last seen', 'bwc-gallery' ),
			'value' => __( '2 days ago', 'bwc-gallery' ),
		),
	);

	return section(
		__( 'Labels and icons', 'bwc-gallery' ),
		__( 'Small parts that sit inside rows, cards and headings.', 'bwc-gallery' ),
		array(
			example( __( 'Pills, every tone', 'bwc-gallery' ), Cluster::markup( array( 'items_html' => $pills ) ) ),
			example( __( 'Pills with a dot, for states', 'bwc-gallery' ), Cluster::markup( array( 'items_html' => $dotted ) ) ),
			example( __( 'Square pills, for codes and types', 'bwc-gallery' ), Cluster::markup( array( 'items_html' => $square ) ) ),
			example(
				__( 'Icons', 'bwc-gallery' ),
				Grid::markup(
					array(
						'items_html' => $icons,
						'min'        => 'narrow',
						'gap'        => 2,
					)
				)
			),
			example(
				__( 'Status dots', 'bwc-gallery' ),
				Cluster::markup(
					array(
						'items_html' => $dots,
						'gap'        => 3,
					)
				)
			),
			example(
				__( 'Avatars: two names, an accent, an email, one word, large and small', 'bwc-gallery' ),
				Cluster::markup(
					array(
						'items_html' => $avatars,
						'align'      => 'center',
					)
				)
			),
			example( __( 'Legend, six series and a dashed line', 'bwc-gallery' ), Legend::markup( array( 'items' => $series ) ) ),
			example( __( 'Key-value list, columns', 'bwc-gallery' ), Facts::markup( array( 'items' => $facts ) ) ),
			example(
				__( 'Key-value list, rows', 'bwc-gallery' ),
				Facts::markup(
					array(
						'items'  => $facts,
						'layout' => 'rows',
					)
				)
			),
			example(
				__( 'Callouts', 'bwc-gallery' ),
				Stack::markup(
					array(
						'gap'        => 2,
						'items_html' => array(
							Callout::markup( array( 'text' => __( 'Numbers before 1 March come from the old tracker and may be low.', 'bwc-gallery' ) ) ),
							Callout::markup(
								array(
									'text' => __( 'This brand has no short link yet, so its offers send visitors straight to the store.', 'bwc-gallery' ),
									'mark' => 'warning',
								)
							),
						),
					)
				)
			),
		)
	);
}

/**
 * Tiles, notices and status cards.
 */
function section_status(): string {
	$dismiss = wp_nonce_url( admin_url( 'admin-ajax.php?action=bwc_gallery_dismiss' ), 'bwc_gallery_dismiss' );

	$notices = array(
		Notice::markup(
			array(
				'tone' => 'info',
				'text' => __( 'The next fetch runs tomorrow at 5:00 am.', 'bwc-gallery' ),
			)
		),
		Notice::markup(
			array(
				'tone'        => 'success',
				'title'       => __( 'Settings saved.', 'bwc-gallery' ),
				'text'        => __( 'Changes show on the site after the next cache clear.', 'bwc-gallery' ),
				'dismiss_url' => $dismiss,
			)
		),
		Notice::markup(
			array(
				'tone'         => 'warning',
				'title'        => __( 'The impact.com key expires in 6 days.', 'bwc-gallery' ),
				'text'         => __( 'Fetching stops when it does.', 'bwc-gallery' ),
				'actions_html' => Button::markup(
					array(
						'label'   => __( 'Add a new key', 'bwc-gallery' ),
						'url'     => '#',
						'variant' => 'secondary',
					)
				),
			)
		),
		Notice::markup(
			array(
				'tone'         => 'danger',
				'title'        => __( '/go/adatum is broken.', 'bwc-gallery' ),
				'text'         => __( 'Visitors who click it get an error page.', 'bwc-gallery' ),
				'actions_html' => Button::markup(
					array(
						'label'   => __( 'Edit link', 'bwc-gallery' ),
						'url'     => '#',
						'variant' => 'secondary',
					)
				),
				'dismiss_url'  => $dismiss,
			)
		),
		Notice::markup(
			array(
				'tone' => 'paused',
				'text' => __( 'Turn on Short links to use this page.', 'bwc-gallery' ),
			)
		),
	);

	$tiles = array(
		array(
			'label' => __( 'Clicks, all time', 'bwc-gallery' ),
			'value' => 28660,
		),
		array(
			'label' => __( 'Clicks, 30 days', 'bwc-gallery' ),
			'value' => 1342,
			'note'  => __( 'Up 12% on the month before', 'bwc-gallery' ),
			'tone'  => 'success',
		),
		array(
			'label' => __( 'Need attention', 'bwc-gallery' ),
			'value' => 2,
			'note'  => __( '1 broken, 1 slow', 'bwc-gallery' ),
			'tone'  => 'danger',
			'url'   => '#',
		),
		array(
			'label' => __( 'Waiting', 'bwc-gallery' ),
			'value' => 7,
			'tone'  => 'warning',
		),
		array(
			'label' => __( 'Brands', 'bwc-gallery' ),
			'value' => '19 of 25',
		),
		array(
			'label' => __( 'Average', 'bwc-gallery' ),
			'value' => 4.6,
		),
	);

	$soft = array_map(
		static fn( array $t ): array => $t + array( 'soft' => true ),
		array_slice( $tiles, 0, 3 )
	);

	$cards = array(
		StatusCard::markup(
			array(
				'title'        => 'impact.com',
				'state'        => array(
					'text' => __( 'Connected', 'bwc-gallery' ),
					'tone' => 'success',
				),
				'stats'        => array(
					array(
						'value' => 365,
						'label' => __( 'offers to review', 'bwc-gallery' ),
					),
					array(
						'value' => 40,
						'label' => __( 'programs', 'bwc-gallery' ),
					),
				),
				'text'         => __( 'Last fetch today at 5:02 am · OK', 'bwc-gallery' ),
				'details'      => __( '1 new offer, 0 changed, 3 ended.', 'bwc-gallery' ),
				'actions_html' => Button::markup(
					array(
						'label'   => __( 'Fetch now', 'bwc-gallery' ),
						'url'     => '#',
						'variant' => 'secondary',
					)
				),
				'picked'       => true,
			)
		),
		StatusCard::markup(
			array(
				'title' => 'CJ',
				'state' => array(
					'text' => __( 'Failed', 'bwc-gallery' ),
					'tone' => 'danger',
				),
				'stats' => array(
					array(
						'value' => 0,
						'label' => __( 'offers to review', 'bwc-gallery' ),
					),
				),
				'text'  => __( 'Last fetch yesterday · The key was refused', 'bwc-gallery' ),
			)
		),
		StatusCard::markup(
			array(
				'title'        => 'Awin',
				'state'        => array(
					'text' => __( 'Not set up', 'bwc-gallery' ),
					'tone' => 'neutral',
				),
				'text'         => __( 'Add your key to fetch Awin offers.', 'bwc-gallery' ),
				'actions_html' => Button::markup(
					array(
						'label'   => __( 'Set up', 'bwc-gallery' ),
						'url'     => '#',
						'variant' => 'secondary',
					)
				),
				'variant'      => 'dashed',
			)
		),
	);

	return section(
		__( 'Numbers and status', 'bwc-gallery' ),
		__( 'Tiles for headline numbers, notices for things to know or fix, status cards for connections.', 'bwc-gallery' ),
		array(
			example(
				__( 'Tiles: plain, with a note, as a link, warning, text value, decimal', 'bwc-gallery' ),
				Tiles::markup(
					array(
						'label' => __( 'Example numbers', 'bwc-gallery' ),
						'tiles' => $tiles,
					)
				)
			),
			example(
				__( 'Tiles, six across', 'bwc-gallery' ),
				Tiles::markup(
					array(
						'label'   => __( 'Example numbers, six across', 'bwc-gallery' ),
						'tiles'   => $tiles,
						'columns' => 'six',
					)
				)
			),
			example(
				__( 'Soft tiles, for inside cards', 'bwc-gallery' ),
				Tiles::markup(
					array(
						'label' => __( 'Example soft numbers', 'bwc-gallery' ),
						'tiles' => $soft,
					)
				)
			),
			example(
				__( 'Notices: info, success (can hide), warning with an action, danger (can hide), paused', 'bwc-gallery' ),
				Stack::markup(
					array(
						'gap'        => 3,
						'items_html' => $notices,
					)
				)
			),
			example(
				__( 'Status cards: picked, failed, not set up', 'bwc-gallery' ),
				Grid::markup( array( 'items_html' => $cards ) )
			),
		)
	);
}

/**
 * Cards, panels, empty states and placeholders.
 */
function section_containers(): string {
	$tabs = array(
		'offers'   => array(
			'label' => __( 'Offers to review', 'bwc-gallery' ),
			'url'   => '#offers',
			'count' => 365,
		),
		'programs' => array(
			'label' => __( 'Programs', 'bwc-gallery' ),
			'url'   => '#programs',
			'count' => __( '40 active', 'bwc-gallery' ),
		),
		'ended'    => array(
			'label' => __( 'Ended', 'bwc-gallery' ),
			'url'   => '#ended',
		),
	);

	return section(
		__( 'Cards and panels', 'bwc-gallery' ),
		__( 'Boxes that hold a group of settings, a table or a message.', 'bwc-gallery' ),
		array(
			Card::markup(
				array(
					'title'             => __( 'Large card: a screen section', 'bwc-gallery' ),
					'subtitle'          => __( 'With a subtitle, a head action and a foot. Sections of a screen sit in these.', 'bwc-gallery' ),
					'head_actions_html' => '<a href="#">' . esc_html__( 'Help', 'bwc-gallery' ) . '</a>',
					'size'              => 'large',
					'content_html'      => '<p>' . esc_html__( 'Fetch every day at 5:00 am.', 'bwc-gallery' ) . '</p>',
					'foot'              => __( 'Last run today at 5:02 am.', 'bwc-gallery' ),
				)
			),
			example(
				__( 'Cards inside a large card turn grey', 'bwc-gallery' ),
				Grid::markup(
					array(
						'items_html' => array(
							Card::markup(
								array(
									'title'        => __( 'Daily fetch', 'bwc-gallery' ),
									'level'        => 4,
									'subtitle'     => __( 'When Acme Deals asks your networks for new offers.', 'bwc-gallery' ),
									'content_html' => '<p>' . esc_html__( 'Fetch every day at 5:00 am.', 'bwc-gallery' ) . '</p>',
									'foot'         => __( 'Last run today at 5:02 am.', 'bwc-gallery' ),
								)
							),
							Card::markup( array( 'content_html' => '<p>' . esc_html__( 'A card with no head.', 'bwc-gallery' ) . '</p>' ) ),
							Card::markup(
								array(
									'title'        => __( 'Remove data', 'bwc-gallery' ),
									'level'        => 4,
									'subtitle'     => __( "This can't be undone.", 'bwc-gallery' ),
									'content_html' => '<p>' . esc_html__( 'Deletes votes, ratings and the activity log when you delete the plugin.', 'bwc-gallery' ) . '</p>',
									'tone'         => 'danger',
								)
							),
						),
					)
				)
			),
			Panel::markup(
				array(
					'title'        => __( 'Settings', 'bwc-gallery' ),
					'tabs'         => array(
						'viewer'  => array(
							'label' => __( 'Viewer', 'bwc-gallery' ),
							'url'   => '#viewer',
							'icon'  => 'settings',
						),
						'cleanup' => array(
							'label' => __( 'Log clean-up', 'bwc-gallery' ),
							'url'   => '#cleanup',
							'icon'  => 'trash',
						),
						'alerts'  => array(
							'label' => __( 'Notifications', 'bwc-gallery' ),
							'url'   => '#alerts',
							'icon'  => 'alert',
						),
						'people'  => array(
							'label' => __( 'Who can see it', 'bwc-gallery' ),
							'url'   => '#people',
							'icon'  => 'user',
						),
					),
					'current_tab'  => 'alerts',
					'tabs_label'   => __( 'Setting groups', 'bwc-gallery' ),
					'content_html' => '<div class="bwc-gallery-pad"><p>' . esc_html__( 'Panel with a title and tabs with icons: email notifications for errors and warnings go here.', 'bwc-gallery' ) . '</p></div>',
				)
			),
			Panel::markup(
				array(
					'tabs'         => $tabs,
					'current_tab'  => 'offers',
					'tabs_label'   => __( 'What to show', 'bwc-gallery' ),
					'toolbar_html' => Cluster::markup(
						array(
							'items_html' => array(
								box( __( 'Search', 'bwc-gallery' ) ),
								box( __( 'Filter', 'bwc-gallery' ) ),
							),
							'justify'    => 'between',
						)
					),
					'content_html' => EmptyState::markup( array( 'text' => __( 'Panel without a title, with counts on its tabs, a toolbar, a footer and a note. No offers match these filters.', 'bwc-gallery' ) ) ),
					'footer_html'  => esc_html__( 'Showing 0 of 365', 'bwc-gallery' ),
					'note'         => __( 'Offers older than 90 days are hidden.', 'bwc-gallery' ),
				)
			),
			example(
				__( 'Empty states: plain (centred), boxed, inline (left, no padding), with an action', 'bwc-gallery' ),
				Stack::markup(
					array(
						'gap'        => 3,
						'items_html' => array(
							EmptyState::markup( array( 'text' => __( 'Nothing here yet.', 'bwc-gallery' ) ) ),
							EmptyState::markup(
								array(
									'text'    => __( 'No links match these filters.', 'bwc-gallery' ),
									'variant' => 'boxed',
								)
							),
							EmptyState::markup(
								array(
									'text'    => __( 'No runs yet.', 'bwc-gallery' ),
									'variant' => 'inline',
								)
							),
							EmptyState::markup(
								array(
									'text'        => __( 'No offers fetched yet.', 'bwc-gallery' ),
									'variant'     => 'boxed',
									'action_html' => Button::markup(
										array(
											'label'   => __( 'Fetch now', 'bwc-gallery' ),
											'url'     => '#',
											'variant' => 'secondary',
										)
									),
								)
							),
						),
					)
				)
			),
			example(
				__( 'Placeholder, for a whole screen with nothing to show', 'bwc-gallery' ),
				Placeholder::markup(
					array(
						'lead'        => __( 'No coupons yet.', 'bwc-gallery' ),
						'text'        => __( 'They show here once members join with one.', 'bwc-gallery' ),
						'action_html' => Button::markup(
							array(
								'label'   => __( 'Add a coupon', 'bwc-gallery' ),
								'url'     => '#',
								'variant' => 'primary',
							)
						),
					)
				)
			),
		)
	);
}

/**
 * The gallery screen: one tab per group of parts.
 */
function render(): void {
	$sections = sections();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch on a dev screen.
	$current = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'overview';
	$current = isset( $sections[ $current ] ) ? $current : 'overview';

	$items = array();
	foreach ( $sections as $key => $section ) {
		$items[ $key ] = array(
			'label' => $section['label'],
			'url'   => section_url( $key ),
		);
	}

	Screen::render(
		array(
			'title'        => __( 'Admin Kit Gallery', 'bwc-gallery' ),
			'subtitle'     => __( 'Every part of the kit, in every state.', 'bwc-gallery' ),
			'version'      => Kit::VERSION,
			'icon_html'    => Icon::markup(
				array(
					'name' => 'settings',
					'size' => 20,
				)
			),
			'nav'          => array(
				'label'   => __( 'Gallery sections', 'bwc-gallery' ),
				'current' => $current,
				'items'   => $items,
			),
			'content_html' => gallery_tools( $current ) . ( $sections[ $current ]['render'] )(),
		)
	);
}

/**
 * Overview: the feature list, as a plugin's overview or "how it works" tab would use it.
 */
function section_overview(): string {
	$features = array(
		array( 'refresh', __( 'Daily fetch', 'bwc-gallery' ), __( 'Every morning Acme Deals asks your affiliate networks for new offers and lists them for you to review. Nothing goes live until you publish it.', 'bwc-gallery' ) ),
		array( 'external', __( 'Short links', 'bwc-gallery' ), __( 'Each brand gets a /go/ link. Change where it points once and every offer that uses it follows.', 'bwc-gallery' ) ),
		array( 'check', __( 'Link checks', 'bwc-gallery' ), __( 'Links are checked every day. A broken one shows a red badge on the Links tab and in the morning email.', 'bwc-gallery' ) ),
		array( 'user', __( 'Votes and ratings', 'bwc-gallery' ), __( 'Visitors vote on coupons and rate brands. Coupons with more down votes than up votes are marked as expired.', 'bwc-gallery' ) ),
	);

	$items = array();
	foreach ( $features as [ $icon, $title, $text ] ) {
		$items[] = array(
			'icon'  => $icon,
			'title' => $title,
			'text'  => $text,
		);
	}

	$screens = Notice::markup(
		array(
			'tone'         => 'info',
			'title'        => __( 'Screens', 'bwc-gallery' ),
			'text'         => __( 'Whole screens built from descriptions: settings with a side menu or tabs, a list with filters, bulk actions, CSV export and a side-panel form, background jobs, an activity log, and a report with charts and a detail view.', 'bwc-gallery' ),
			'actions_html' => '<a href="' . esc_url( admin_url( 'admin.php?page=bwc-gallery-settings' ) ) . '">' . esc_html__( 'Open the screens', 'bwc-gallery' ) . '</a>',
		)
	);

	return $screens . section(
		__( 'Overview', 'bwc-gallery' ),
		__( 'A feature list, as a plugin\'s overview or "how it works" tab would use it: what each part does, in a sentence or two. Example copy only.', 'bwc-gallery' ),
		array(
			example(
				__( 'How Acme Deals works', 'bwc-gallery' ),
				Features::markup(
					array(
						'items' => $items,
						'level' => 4,
					)
				)
			),
		)
	);
}

/**
 * A gallery URL with extra query args, for the controls' example links.
 *
 * @param array<string, string|int> $args Query args.
 */
function demo_url( array $args ): string {
	return add_query_arg( $args, section_url( 'controls' ) );
}

/**
 * Controls: buttons, switches, search, chips, segmented control, form
 * controls, prefixed input, pagination and copy buttons.
 */
function section_controls(): string {
	$variants = array();
	foreach (
		array(
			'secondary' => __( 'Secondary', 'bwc-gallery' ),
			'primary'   => __( 'Save changes', 'bwc-gallery' ),
			'outline'   => __( 'Fetch now', 'bwc-gallery' ),
			'link'      => __( 'Edit', 'bwc-gallery' ),
			'danger'    => __( 'Delete link', 'bwc-gallery' ),
		) as $variant => $label
	) {
		$variants[] = Button::markup(
			array(
				'label'   => $label,
				'variant' => $variant,
			)
		);
	}

	$small = array(
		Button::markup(
			array(
				'label' => __( 'Small', 'bwc-gallery' ),
				'size'  => 'small',
			)
		),
		Button::markup(
			array(
				'label'   => __( 'Small primary', 'bwc-gallery' ),
				'variant' => 'primary',
				'size'    => 'small',
			)
		),
		Button::markup(
			array(
				'label' => __( 'With an icon', 'bwc-gallery' ),
				'icon'  => 'download',
			)
		),
		Button::markup(
			array(
				'label'     => __( 'Settings', 'bwc-gallery' ),
				'icon'      => 'settings',
				'icon_only' => true,
			)
		),
		Button::markup(
			array(
				'label'     => __( 'Remove', 'bwc-gallery' ),
				'icon'      => 'trash',
				'icon_only' => true,
				'size'      => 'small',
				'variant'   => 'danger',
			)
		),
	);

	$states = array(
		Button::markup(
			array(
				'label'   => __( 'Pressed toggle', 'bwc-gallery' ),
				'pressed' => true,
			)
		),
		Button::markup(
			array(
				'label'   => __( 'Not pressed', 'bwc-gallery' ),
				'pressed' => false,
			)
		),
		Button::markup(
			array(
				'label'    => __( 'Save changes', 'bwc-gallery' ),
				'variant'  => 'primary',
				'disabled' => true,
			)
		),
		Button::markup(
			array(
				'label'    => __( 'Disabled link', 'bwc-gallery' ),
				'url'      => demo_url( array( 'x' => 1 ) ),
				'disabled' => true,
			)
		),
		Button::markup(
			array(
				'label'   => __( 'Delete (asks first)', 'bwc-gallery' ),
				'variant' => 'danger',
				'confirm' => __( 'Delete this link? Visitors who click it will get an error page.', 'bwc-gallery' ),
			)
		),
	);

	$switch = static function ( string $id, string $label, bool $checked, bool $disabled = false ): string {
		return '<div class="bwc-gallery-switch"><span id="' . esc_attr( $id . '-label' ) . '">' . esc_html( $label ) . '</span>' . Toggle::markup(
			array(
				'id'         => $id,
				'name'       => $id,
				'checked'    => $checked,
				'disabled'   => $disabled,
				'labelledby' => $id . '-label',
			)
		) . '</div>';
	};

	$chips = Chips::markup(
		array(
			'label'   => __( 'Filter links', 'bwc-gallery' ),
			'current' => 'all',
			'items'   => array(
				'all'    => array(
					'label' => __( 'All', 'bwc-gallery' ),
					'count' => 256,
					'url'   => demo_url( array( 'show' => 'all' ) ),
				),
				'brand'  => array(
					'label' => __( 'Brand links', 'bwc-gallery' ),
					'count' => 25,
					'url'   => demo_url( array( 'show' => 'brand' ) ),
				),
				'offer'  => array(
					'label' => __( 'Offer links', 'bwc-gallery' ),
					'count' => 231,
					'url'   => demo_url( array( 'show' => 'offer' ) ),
				),
				'broken' => array(
					'label' => __( 'Need attention', 'bwc-gallery' ),
					'count' => 2,
					'tone'  => 'danger',
					'url'   => demo_url( array( 'show' => 'broken' ) ),
				),
			),
		)
	);

	$segmented = Segmented::markup(
		array(
			'label'   => __( 'Contract', 'bwc-gallery' ),
			'current' => 'active',
			'items'   => array(
				'active' => array(
					'label' => __( 'Active', 'bwc-gallery' ),
					'count' => 40,
					'url'   => demo_url( array( 'contract' => 'active' ) ),
				),
				'ended'  => array(
					'label' => __( 'Ended', 'bwc-gallery' ),
					'count' => 30,
					'url'   => demo_url( array( 'contract' => 'ended' ) ),
				),
				'all'    => array(
					'label' => __( 'All', 'bwc-gallery' ),
					'count' => 70,
					'url'   => demo_url( array( 'contract' => 'all' ) ),
				),
			),
		)
	);

	$programs = array(
		''          => __( 'All programs', 'bwc-gallery' ),
		'northwind' => 'Northwind',
		'contoso'   => 'Contoso',
		'proseware' => 'Proseware',
	);

	$form_controls = Grid::markup(
		array(
			'min'        => 'medium',
			'items_html' => array(
				'<div>' . Select::markup(
					array(
						'id'            => 'bwc-gallery-program',
						'name'          => 'program',
						'label'         => __( 'Program', 'bwc-gallery' ),
						'label_visible' => true,
						'options'       => $programs,
						'width'         => 'full',
					)
				) . '</div>',
				'<div>' . Input::markup(
					array(
						'id'            => 'bwc-gallery-name',
						'name'          => 'name',
						'label'         => __( 'Brand name', 'bwc-gallery' ),
						'label_visible' => true,
						'value'         => 'Northwind',
						'width'         => 'full',
					)
				) . '</div>',
				'<div>' . Input::markup(
					array(
						'id'            => 'bwc-gallery-days',
						'name'          => 'days',
						'type'          => 'number',
						'label'         => __( 'Days to keep', 'bwc-gallery' ),
						'label_visible' => true,
						'value'         => '90',
					)
				) . '</div>',
				'<div>' . Input::markup(
					array(
						'id'            => 'bwc-gallery-selector',
						'name'          => 'selector',
						'label'         => __( 'CSS selector (monospace)', 'bwc-gallery' ),
						'label_visible' => true,
						'value'         => '.offer-card__button',
						'mono'          => true,
						'width'         => 'full',
					)
				) . '</div>',
				'<div>' . Input::markup(
					array(
						'id'            => 'bwc-gallery-bad',
						'name'          => 'bad',
						'label'         => __( 'Invalid', 'bwc-gallery' ),
						'label_visible' => true,
						'value'         => 'not a url',
						'invalid'       => true,
						'width'         => 'full',
					)
				) . '</div>',
				'<div>' . Input::markup(
					array(
						'id'            => 'bwc-gallery-off',
						'name'          => 'off',
						'label'         => __( 'Disabled', 'bwc-gallery' ),
						'label_visible' => true,
						'value'         => 'Set in wp-config.php',
						'disabled'      => true,
						'width'         => 'full',
					)
				) . '</div>',
			),
		)
	) . '<div class="bwc-gallery-gap">' . Input::markup(
		array(
			'id'            => 'bwc-gallery-notes',
			'name'          => 'notes',
			'type'          => 'textarea',
			'label'         => __( 'Notes (textarea)', 'bwc-gallery' ),
			'label_visible' => true,
			'value'         => __( 'One domain per line.', 'bwc-gallery' ),
			'width'         => 'full',
			'rows'          => 3,
		)
	) . '</div>';

	$auto = '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="bwc-gallery-row">'
		. '<input type="hidden" name="page" value="bwc-gallery"><input type="hidden" name="section" value="controls">'
		. Select::markup(
			array(
				'id'          => 'bwc-gallery-auto',
				'name'        => 'program',
				'label'       => __( 'Program', 'bwc-gallery' ),
				'options'     => $programs,
				'auto_submit' => true,
			)
		)
		. '<button type="submit" class="bwc-button bwc-button--small" data-bwc-filter-submit>' . esc_html__( 'Filter', 'bwc-gallery' ) . '</button>'
		. '</form>';

	$prefixed = Prefixed::markup(
		array(
			'id'            => 'bwc-gallery-slug',
			'name'          => 'slug',
			'label'         => __( 'Short link', 'bwc-gallery' ),
			'label_visible' => true,
			'prefix'        => 'example.com/',
			'value'         => 'go/adatum',
			'describedby'   => 'bwc-gallery-slug-help',
		)
	) . '<p class="bwc-gallery-help" id="bwc-gallery-slug-help">' . esc_html__( 'The link visitors see: example.com/ followed by this.', 'bwc-gallery' ) . '</p>';

	$url        = static fn( int $page ): string => demo_url( array( 'paged' => $page ) );
	$pagination = Stack::markup(
		array(
			'gap'        => 4,
			'items_html' => array(
				Pagination::markup(
					array(
						'page'     => 1,
						'per_page' => 20,
						'total'    => 245,
						'url'      => $url,
						'label'    => sprintf( /* translators: %d: example number. */ __( 'Pages, example %d', 'bwc-gallery' ), 1 ),
					)
				),
				Pagination::markup(
					array(
						'page'     => 7,
						'per_page' => 20,
						'total'    => 245,
						'url'      => $url,
						'label'    => sprintf( /* translators: %d: example number. */ __( 'Pages, example %d', 'bwc-gallery' ), 2 ),
					)
				),
				Pagination::markup(
					array(
						'page'     => 4,
						'per_page' => 20,
						'total'    => 71,
						'url'      => $url,
						'label'    => sprintf( /* translators: %d: example number. */ __( 'Pages, example %d', 'bwc-gallery' ), 3 ),
					)
				),
				Pagination::markup(
					array(
						'page'     => 1,
						'per_page' => 20,
						'total'    => 7,
						'url'      => $url,
						'label'    => sprintf( /* translators: %d: example number. */ __( 'Pages, example %d', 'bwc-gallery' ), 4 ),
					)
				),
				Pagination::markup(
					array(
						'page'     => 2,
						'per_page' => 25,
						'total'    => 60,
						'url'      => $url,
						'label'    => sprintf( /* translators: %d: example number. */ __( 'Pages, example %d', 'bwc-gallery' ), 5 ),
						'labels'   => 'time',
					)
				),
				Pagination::markup(
					array(
						'page'     => 1,
						'per_page' => 20,
						'total'    => 0,
						'url'      => $url,
						'label'    => sprintf( /* translators: %d: example number. */ __( 'Pages, example %d', 'bwc-gallery' ), 6 ),
					)
				),
			),
		)
	);

	$copy = Stack::markup(
		array(
			'gap'        => 3,
			'items_html' => array(
				'<div class="bwc-gallery-row"><code>https://example.com/go/northwind</code>' . CopyButton::markup(
					array(
						'value' => 'https://example.com/go/northwind',
						'label' => __( 'Copy https://example.com/go/northwind', 'bwc-gallery' ),
					)
				) . '</div>',
				'<div class="bwc-gallery-row">' . CopyButton::markup(
					array(
						'value'     => 'ada@example.com, grace@example.com',
						'label'     => __( 'Copy emails', 'bwc-gallery' ),
						'icon_only' => false,
						'size'      => 'regular',
					)
				) . '</div>',
			),
		)
	);

	return section(
		__( 'Controls', 'bwc-gallery' ),
		__( 'Buttons, switches and the boxes people type into or pick from. Every one works without scripts; copy buttons and filter buttons only appear when scripts run.', 'bwc-gallery' ),
		array(
			example( __( 'Buttons: secondary, primary, outline, link, danger', 'bwc-gallery' ), Cluster::markup( array( 'items_html' => $variants ) ) ),
			example(
				__( 'Small, with an icon, icon only', 'bwc-gallery' ),
				Cluster::markup(
					array(
						'items_html' => $small,
						'align'      => 'center',
					)
				)
			),
			example( __( 'States: pressed, not pressed, disabled button and link, asks before deleting', 'bwc-gallery' ), Cluster::markup( array( 'items_html' => $states ) ) ),
			example(
				__( 'Switches: on, off, disabled', 'bwc-gallery' ),
				Stack::markup(
					array(
						'gap'        => 1,
						'items_html' => array(
							$switch( 'bwc-gallery-sw-links', __( 'Short links', 'bwc-gallery' ), true ),
							$switch( 'bwc-gallery-sw-votes', __( 'Voting', 'bwc-gallery' ), false ),
							$switch( 'bwc-gallery-sw-email', __( 'Daily email (needs FluentSMTP)', 'bwc-gallery' ), false, true ),
						),
					)
				)
			),
			example(
				__( 'Search: hidden label, visible label', 'bwc-gallery' ),
				Grid::markup(
					array(
						'items_html' => array(
							Search::markup(
								array(
									'id'          => 'bwc-gallery-q',
									'name'        => 'q',
									'label'       => __( 'Search links', 'bwc-gallery' ),
									'placeholder' => __( 'Search brands, links or destinations', 'bwc-gallery' ),
								)
							),
							Search::markup(
								array(
									'id'            => 'bwc-gallery-find',
									'name'          => 'find',
									'label'         => __( 'Find a setting', 'bwc-gallery' ),
									'label_visible' => true,
								)
							),
						),
					)
				)
			),
			example( __( 'Chips: links with counts, one needing attention', 'bwc-gallery' ), $chips ),
			example( __( 'Segmented control', 'bwc-gallery' ), $segmented ),
			example( __( 'Form controls: select, text, number, monospace, invalid, disabled, textarea', 'bwc-gallery' ), $form_controls ),
			example( __( 'A filter that submits on change (its Filter button hides when scripts run)', 'bwc-gallery' ), $auto ),
			example( __( 'Prefixed input, with the prefix repeated in its help text', 'bwc-gallery' ), $prefixed ),
			example( __( 'Pagination: first page, middle page, last page, one page, newer and older, nothing to show. Type a page number and press Enter to jump.', 'bwc-gallery' ), $pagination ),
			example( __( 'Copy buttons: icon next to the value, text button', 'bwc-gallery' ), $copy ),
		)
	);
}

/**
 * Data: tables, grouped list, activity groups with filter chips, timeline,
 * run list and reference table.
 */
function section_data(): string {
	$sort = static function ( string $key, ?string $current = null ): array {
		return array(
			'url_asc'  => demo_url(
				array(
					'orderby' => $key,
					'order'   => 'asc',
				)
			),
			'url_desc' => demo_url(
				array(
					'orderby' => $key,
					'order'   => 'desc',
				)
			),
			'current'  => $current,
		);
	};

	$offer   = static fn( string $title, string $brand ): string => '<span class="bwc-table__primary">' . esc_html( $title ) . '</span><span class="bwc-table__sub">' . esc_html( $brand ) . '</span>';
	$actions = static fn( string $name ): string => '<span class="bwc-table__actions">' . Button::markup(
		array(
			'label'   => __( 'Edit', 'bwc-gallery' ),
			'variant' => 'link',
			'size'    => 'small',
			'url'     => demo_url( array( 'edit' => $name ) ),
		)
	) . '</span>';

	$table = Table::markup(
		array(
			'caption' => __( 'Offers to review', 'bwc-gallery' ),
			'columns' => array(
				'offer'   => array(
					'label' => __( 'Offer', 'bwc-gallery' ),
					'sort'  => $sort( 'offer' ),
				),
				'ends'    => array(
					'label' => __( 'Ends', 'bwc-gallery' ),
					'width' => 'medium',
					'sort'  => $sort( 'ends', 'asc' ),
				),
				'clicks'  => array(
					'label' => __( 'Clicks', 'bwc-gallery' ),
					'align' => 'end',
					'width' => 'narrow',
					'sort'  => $sort( 'clicks' ),
				),
				'actions' => array(
					'label'      => __( 'Actions', 'bwc-gallery' ),
					'hide_label' => true,
					'width'      => 'narrow',
				),
			),
			'rows'    => array(
				array(
					'id'    => '101',
					'label' => '$50 off your first order',
					'cells' => array(
						'offer'   => $offer( '$50 off your first order', 'Northwind' ),
						'ends'    => esc_html( 'Nov 30, 2026' ),
						'clicks'  => esc_html( number_format_i18n( 1342 ) ),
						'actions' => $actions( '101' ),
					),
				),
				array(
					'id'    => '102',
					'label' => 'Free delivery for your first month',
					'state' => 'current',
					'cells' => array(
						'offer'   => $offer( 'Free delivery for your first month', 'Contoso' ),
						'ends'    => esc_html__( 'No end date', 'bwc-gallery' ),
						'clicks'  => esc_html( number_format_i18n( 451 ) ),
						'actions' => $actions( '102' ),
					),
				),
				array(
					'id'    => '103',
					'label' => '20% off .com domains',
					'state' => 'quiet',
					'cells' => array(
						'offer'   => $offer( '20% off .com domains', 'Proseware' ),
						'ends'    => esc_html__( 'Ended Oct 1', 'bwc-gallery' ),
						'clicks'  => esc_html( '12' ),
						'actions' => $actions( '103' ),
					),
				),
			),
			'bulk'    => array(
				'name'    => 'offers',
				'label'   => __( 'Action for the selected offers', 'bwc-gallery' ),
				'actions' => array(
					'publish' => __( 'Publish', 'bwc-gallery' ),
					'skip'    => __( 'Skip', 'bwc-gallery' ),
				),
			),
		)
	);

	$compact = Table::markup(
		array(
			'caption'         => __( 'Clicks by day', 'bwc-gallery' ),
			'caption_visible' => true,
			'compact'         => true,
			'min_width'       => 'none',
			'columns'         => array(
				'day'    => array( 'label' => __( 'Day', 'bwc-gallery' ) ),
				'clicks' => array(
					'label' => __( 'Clicks', 'bwc-gallery' ),
					'align' => 'end',
				),
			),
			'rows'            => array(
				array(
					'cells' => array(
						'day'    => esc_html__( 'Today', 'bwc-gallery' ),
						'clicks' => '48',
					),
				),
				array(
					'cells' => array(
						'day'    => esc_html__( 'Yesterday', 'bwc-gallery' ),
						'clicks' => '61',
					),
				),
			),
		)
	);

	$empty = Table::markup(
		array(
			'caption'    => __( 'Programs', 'bwc-gallery' ),
			'columns'    => array(
				'name'   => array( 'label' => __( 'Program', 'bwc-gallery' ) ),
				'status' => array( 'label' => __( 'Contract', 'bwc-gallery' ) ),
			),
			'rows'       => array(),
			'min_width'  => 'none',
			'empty_html' => esc_html__( 'No programs match these filters.', 'bwc-gallery' ),
		)
	);

	$link_cells = static fn( string $slug, string $to, int $clicks ): array => array(
		'cells' => array(
			'link'   => '<code>' . esc_html( $slug ) . '</code>',
			'to'     => esc_html( $to ),
			'clicks' => esc_html( number_format_i18n( $clicks ) ),
		),
	);

	$grouped = GroupedList::markup(
		array(
			'caption' => __( 'Short links by brand', 'bwc-gallery' ),
			'columns' => array(
				'link'   => array(
					'label' => __( 'Link', 'bwc-gallery' ),
					'width' => 'wide',
				),
				'to'     => array( 'label' => __( 'Goes to', 'bwc-gallery' ) ),
				'clicks' => array(
					'label' => __( 'Clicks, 30 days', 'bwc-gallery' ),
					'align' => 'end',
					'width' => 'medium',
				),
			),
			'groups'  => array(
				array(
					'title'      => 'Northwind',
					'meta'       => __( '1,709 clicks all time across its 4 links', 'bwc-gallery' ),
					'lead'       => array( $link_cells( 'go/northwind', 'northwind.example', 686 ) ),
					'rest'       => array(
						$link_cells( 'go/northwind-50-bonus', 'northwind.example/promotions', 177 ),
						$link_cells( 'go/northwind-new-customer', 'northwind.example/new-customers', 64 ),
						$link_cells( 'go/northwind-referral', 'northwind.example/refer', 9 ),
					),
					'rest_label' => __( '3 offer links', 'bwc-gallery' ),
				),
				array(
					'title' => 'Proseware',
					'meta'  => __( '83 clicks all time', 'bwc-gallery' ),
					'lead'  => array( $link_cells( 'go/proseware', 'proseware.example', 4 ) ),
				),
			),
		)
	);

	$feed     = static fn( array $items ): string => Feed::markup( array( 'items' => $items ) );
	$activity = '<div class="bwc-gallery-row bwc-gallery-row--between">'
		. Chips::markup(
			array(
				'label'   => __( 'Show', 'bwc-gallery' ),
				'kind'    => 'buttons',
				'target'  => 'bwc-gallery-activity',
				'current' => 'all',
				'items'   => array(
					'all'      => array(
						'label' => __( 'All', 'bwc-gallery' ),
						'count' => 5,
					),
					'offers'   => array(
						'label' => __( 'Offers', 'bwc-gallery' ),
						'count' => 3,
					),
					'brands'   => array(
						'label' => __( 'Brands', 'bwc-gallery' ),
						'count' => 1,
					),
					'problems' => array(
						'label' => __( 'Problems', 'bwc-gallery' ),
						'count' => 1,
						'tone'  => 'danger',
					),
				),
			)
		)
		. ExpandAll::markup( array( 'target' => 'bwc-gallery-activity' ) )
		. '</div><div id="bwc-gallery-activity" class="bwc-gallery-gap">'
		. Stack::markup(
			array(
				'gap'        => 2,
				'items_html' => array(
					Collapsible::markup(
						array(
							'title'        => 'Northwind',
							'open'         => true,
							'hook'         => 'filter-group',
							'tags'         => array(
								array(
									'text' => __( '2 added', 'bwc-gallery' ),
									'tone' => 'success',
								),
								array(
									'text' => __( '1 problem', 'bwc-gallery' ),
									'tone' => 'danger',
								),
							),
							'content_html' => $feed(
								array(
									array(
										'time'   => '11:14 am',
										'kind'   => array(
											'text' => __( 'Added', 'bwc-gallery' ),
											'tone' => 'success',
										),
										'text'   => __( '$50 off your first order', 'bwc-gallery' ),
										'filter' => 'offers',
									),
									array(
										'time'   => '11:14 am',
										'kind'   => array(
											'text' => __( 'Added', 'bwc-gallery' ),
											'tone' => 'success',
										),
										'text'   => __( 'Refer a friend, get $25', 'bwc-gallery' ),
										'filter' => 'offers',
									),
									array(
										'time'    => '11:15 am',
										'kind'    => array(
											'text' => __( 'Rejected', 'bwc-gallery' ),
											'tone' => 'danger',
										),
										'text'    => __( 'One offer was turned down.', 'bwc-gallery' ),
										'details' => array( __( 'The link goes to a site that isn\'t the brand\'s.', 'bwc-gallery' ) ),
										'filter'  => 'problems',
									),
								)
							),
						)
					),
					Collapsible::markup(
						array(
							'title'        => 'Tribesigns',
							'hook'         => 'filter-group',
							'tags'         => array(
								array(
									'text' => __( 'New brand', 'bwc-gallery' ),
									'tone' => 'info',
								),
							),
							'content_html' => $feed(
								array(
									array(
										'time'      => '11:20 am',
										'kind'      => array(
											'text' => __( 'Brand', 'bwc-gallery' ),
											'tone' => 'info',
										),
										'text'      => __( 'Tribesigns was added as a draft.', 'bwc-gallery' ),
										'link_html' => '<a href="#">' . esc_html__( 'Review it', 'bwc-gallery' ) . '</a>',
										'filter'    => 'brands',
									),
									array(
										'time'   => '11:21 am',
										'kind'   => array(
											'text' => __( 'Added', 'bwc-gallery' ),
											'tone' => 'success',
										),
										'text'   => __( '15% off standing desks', 'bwc-gallery' ),
										'filter' => 'offers',
									),
								)
							),
							'more'         => __( 'Closed groups open when a filter finds something in them.', 'bwc-gallery' ),
						)
					),
					'<p class="bwc-gallery-help" data-bwc-filter-empty hidden>' . esc_html__( 'Nothing of that kind in this run.', 'bwc-gallery' ) . '</p>',
				),
			)
		)
		. '</div>';

	$timeline = Timeline::markup(
		array(
			'title'      => __( 'Timeline', 'bwc-gallery' ),
			'days'       => array(
				array(
					'label'  => __( 'Today', 'bwc-gallery' ),
					'events' => array(
						array(
							'time' => '14:02',
							'text' => __( 'Watched lesson 3', 'bwc-gallery' ),
							'meta' => __( '6 min', 'bwc-gallery' ),
							'kind' => 'video',
							'icon' => 'video',
						),
						array(
							'time' => '13:40',
							'text' => __( 'Passed the module 2 quiz', 'bwc-gallery' ),
							'meta' => __( '9 of 10', 'bwc-gallery' ),
							'kind' => 'quiz',
							'icon' => 'quiz',
						),
					),
				),
				array(
					'label'  => __( 'Yesterday', 'bwc-gallery' ),
					'events' => array(
						array(
							'time' => '09:12',
							'text' => __( 'Joined with the coupon SPRING25', 'bwc-gallery' ),
							'kind' => 'join',
							'icon' => 'user',
						),
					),
				),
			),
			'pagination' => array(
				'page'     => 1,
				'per_page' => 25,
				'total'    => 60,
				'url'      => static fn( int $page ): string => demo_url( array( 'events' => $page ) ),
				'anchor'   => 'bwc-gallery-timeline',
			),
			'foot'       => __( 'Times are in the site\'s time zone.', 'bwc-gallery' ),
		)
	);

	$runs = SplitView::markup(
		array(
			'side_label' => __( 'Runs', 'bwc-gallery' ),
			'side_width' => 'narrow',
			'side_html'  => RunList::markup(
				array(
					'label'    => __( 'Runs', 'bwc-gallery' ),
					'sections' => array(
						array(
							'heading' => __( 'Today · Nov 1', 'bwc-gallery' ),
							'items'   => array(
								array(
									'title'   => __( 'Daily run', 'bwc-gallery' ),
									'time'    => '11:12 am',
									'line'    => __( '29 added · 4 re-checked', 'bwc-gallery' ),
									'problem' => __( '1 problem', 'bwc-gallery' ),
									'url'     => demo_url( array( 'run' => 1 ) ),
									'current' => true,
								),
								array(
									'title' => __( 'Link check', 'bwc-gallery' ),
									'time'  => '6:00 am',
									'line'  => __( '25 links, all working', 'bwc-gallery' ),
									'url'   => demo_url( array( 'run' => 2 ) ),
								),
							),
						),
						array(
							'heading' => __( 'Yesterday · Oct 31', 'bwc-gallery' ),
							'items'   => array(
								array(
									'title' => __( 'Daily run', 'bwc-gallery' ),
									'time'  => '11:09 am',
									'line'  => __( '12 added', 'bwc-gallery' ),
									'url'   => demo_url( array( 'run' => 3 ) ),
								),
							),
						),
					),
				)
			),
			'main_html'  => box( __( 'The chosen run shows here.', 'bwc-gallery' ) ),
		)
	);

	$reference = Reference::markup(
		array(
			'summary' => __( 'Dynamic tags for this feature (3)', 'bwc-gallery' ),
			'columns' => array(
				'tag'  => __( 'Tag', 'bwc-gallery' ),
				'what' => __( 'What it shows', 'bwc-gallery' ),
			),
			'mono'    => array( 'tag' ),
			'rows'    => array(
				array(
					'tag'  => '{echo:acme_offer_status}',
					'what' => __( '"Ends in 3 days", "Ongoing" or "Expired"', 'bwc-gallery' ),
				),
				array(
					'tag'  => '{echo:acme_percent_off}',
					'what' => __( 'The discount, such as 25', 'bwc-gallery' ),
				),
				array(
					'tag'  => '{echo:acme_button_text}',
					'what' => __( '"Get Code" or "Get Offer"', 'bwc-gallery' ),
				),
			),
		)
	);

	return section(
		__( 'Data', 'bwc-gallery' ),
		__( 'Tables and lists of records. Sorting and paging are links, so they work without scripts; selecting all, filtering in place and Expand all need them.', 'bwc-gallery' ),
		array(
			example( __( 'Table: sortable headers, bulk actions, numbers, a highlighted row and a faded row', 'bwc-gallery' ), $table ),
			example(
				__( 'Compact table with a visible caption, and an empty table', 'bwc-gallery' ),
				Grid::markup(
					array(
						'min'        => 'wide',
						'items_html' => array( $compact, $empty ),
					)
				)
			),
			example( __( 'Grouped list: one row per brand shown, the rest folded', 'bwc-gallery' ), $grouped ),
			example( __( 'Activity: filter buttons, Expand all, collapsible groups with feeds', 'bwc-gallery' ), $activity ),
			'<div id="bwc-gallery-timeline">' . example( __( 'Timeline with newer and older pages', 'bwc-gallery' ), $timeline ) . '</div>',
			example( __( 'Run list in a side column', 'bwc-gallery' ), $runs ),
			example( __( 'Reference table with copy buttons', 'bwc-gallery' ), $reference ),
		)
	);
}

/**
 * The gallery's settings storage, set up once.
 *
 * @param OptionStorage|null $set Storage to keep (on boot).
 */
function demo_settings( ?OptionStorage $set = null ): ?OptionStorage {
	static $storage = null;
	if ( null !== $set ) {
		$storage = $set;
	}

	return $storage;
}

/**
 * The gallery's settings fields, two sets, every type that's built.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function demo_sets(): array {
	return array(
		'offers'   => array(
			array(
				'id'          => 'button_text',
				'label'       => __( 'Button text', 'bwc-gallery' ),
				'type'        => 'text',
				'default'     => __( 'Get Code', 'bwc-gallery' ),
				'placeholder' => true,
				'description' => __( 'Shown on offer cards. Leave it empty to use the default.', 'bwc-gallery' ),
				'isValid'     => array( 'maxLength' => 20 ),
			),
			array(
				'id'          => 'report_to',
				'label'       => __( 'Send the daily email to', 'bwc-gallery' ),
				'type'        => 'email',
				'placeholder' => __( 'The site admin email', 'bwc-gallery' ),
			),
			array(
				'id'      => 'votes_per_hour',
				'label'   => __( 'Votes per visitor, per hour', 'bwc-gallery' ),
				'type'    => 'number',
				'default' => 20,
				'unit'    => __( 'votes', 'bwc-gallery' ),
				'isValid' => array(
					'min' => 1,
					'max' => 100,
				),
			),
			array(
				'id'      => 'card_gap',
				'label'   => __( 'Space between cards', 'bwc-gallery' ),
				'type'    => 'length',
				'default' => '16px',
			),
			array(
				'id'             => 'age_rule',
				'label'          => __( 'Age rule', 'bwc-gallery' ),
				'type'           => 'checkbox',
				'checkbox_label' => __( 'Expire offers that haven\'t been re-checked for a while', 'bwc-gallery' ),
				// The reason the next field is off goes here: an inert field's own help isn't read out.
				'description'    => __( 'Turn it on to choose how many days.', 'bwc-gallery' ),
			),
			array(
				'id'          => 'expire_after',
				'label'       => __( 'Expire after', 'bwc-gallery' ),
				'type'        => 'number',
				'default'     => 30,
				'unit'        => __( 'days', 'bwc-gallery' ),
				'dependsOn'   => 'age_rule',
				'description' => __( 'Counted from the last time the offer was checked.', 'bwc-gallery' ),
				'isValid'     => array(
					'min' => 1,
					'max' => 365,
				),
			),
			array(
				'id'      => 'fallback_url',
				'label'   => __( 'Fallback link', 'bwc-gallery' ),
				'type'    => 'url',
				'default' => '',
				'wide'    => true,
			),
			array(
				'id'       => 'api_base',
				'label'    => __( 'Network address', 'bwc-gallery' ),
				'type'     => 'text',
				'default'  => 'https://api.impact.com',
				'readOnly' => __( 'Set in wp-config.php', 'bwc-gallery' ),
			),
			array(
				'id'          => 'intro',
				'label'       => __( 'Intro', 'bwc-gallery' ),
				'type'        => 'textarea',
				'description' => __( 'A sentence or two above the offers.', 'bwc-gallery' ),
				'wide'        => true,
			),
			array(
				'id'          => 'test_emails',
				'label'       => __( 'Send test emails to', 'bwc-gallery' ),
				'type'        => 'list',
				'item'        => 'email',
				'description' => __( 'One address per line.', 'bwc-gallery' ),
			),
			array(
				'id'          => 'skip_paths',
				'label'       => __( 'Pages to skip', 'bwc-gallery' ),
				'type'        => 'list',
				'item'        => 'path',
				'description' => __( 'One path per line, starting with /.', 'bwc-gallery' ),
			),
		),
		'schedule' => array(
			array(
				'id'             => 'daily_sync',
				'label'          => __( 'Daily fetch', 'bwc-gallery' ),
				'type'           => 'switch',
				'checkbox_label' => __( 'Fetch from every network once a day', 'bwc-gallery' ),
				'description'    => __( 'Turn it on to choose a time.', 'bwc-gallery' ),
				'default'        => true,
			),
			array(
				'id'          => 'sync_hour',
				'label'       => __( 'Fetch at', 'bwc-gallery' ),
				'type'        => 'hour',
				'default'     => 5,
				'dependsOn'   => 'daily_sync',
				'description' => __( 'The fetch takes a few minutes.', 'bwc-gallery' ),
			),
			array(
				'id'      => 'digest_time',
				'label'   => __( 'Send the digest at', 'bwc-gallery' ),
				'type'    => 'time',
				'default' => '09:30',
			),
			array(
				'id'       => 'mode',
				'label'    => __( 'Expiry rule', 'bwc-gallery' ),
				'type'     => 'select',
				'default'  => 'both',
				'elements' => array(
					array(
						'value' => 'date',
						'label' => __( 'By end date only', 'bwc-gallery' ),
					),
					array(
						'value' => 'votes',
						'label' => __( 'By votes only', 'bwc-gallery' ),
					),
					array(
						'value' => 'both',
						'label' => __( 'By end date or votes', 'bwc-gallery' ),
					),
				),
			),
			array(
				'id'        => 'min_votes',
				'label'     => __( 'Votes before expiring', 'bwc-gallery' ),
				'type'      => 'number',
				'default'   => 3,
				'dependsOn' => array(
					'field' => 'mode',
					'is'    => array( 'votes', 'both' ),
				),
				'isValid'   => array( 'min' => 1 ),
			),
			array(
				'id'       => 'redirect',
				'label'    => __( 'Redirect type', 'bwc-gallery' ),
				'type'     => 'radio',
				'default'  => '307',
				'elements' => array(
					array(
						'value'       => '301',
						'label'       => __( 'Permanent (301)', 'bwc-gallery' ),
						'description' => __( 'Browsers remember it. Hard to change later.', 'bwc-gallery' ),
					),
					array(
						'value'       => '302',
						'label'       => __( 'Temporary (302)', 'bwc-gallery' ),
						'description' => __( 'Checked every time.', 'bwc-gallery' ),
					),
					array(
						'value'       => '307',
						'label'       => __( 'Temporary (307)', 'bwc-gallery' ),
						'description' => __( 'Like 302. The usual choice for affiliate links.', 'bwc-gallery' ),
					),
				),
			),
			array(
				'id'         => 'post_types',
				'label'      => __( 'Show votes on', 'bwc-gallery' ),
				'type'       => 'checkboxes',
				'default'    => array( 'post' ),
				'show_value' => true,
				'columns'    => 3,
				'elements'   => static fn(): array => array_map(
					static fn( $type ): array => array(
						'value' => $type->name,
						'label' => $type->labels->singular_name,
					),
					array_values( get_post_types( array( 'public' => true ), 'objects' ) )
				),
			),
			array(
				'id'       => 'networks',
				'label'    => __( 'Networks', 'bwc-gallery' ),
				'type'     => 'select',
				'multiple' => true,
				'default'  => array( 'impact' ),
				'elements' => array(
					'impact' => 'impact.com',
					'cj'     => 'CJ',
					'awin'   => 'Awin',
				),
			),
			array(
				'id'       => 'reviewers',
				'label'    => __( 'Who can review offers', 'bwc-gallery' ),
				'type'     => 'role',
				'multiple' => true,
				'exclude'  => array( 'administrator' ),
				'default'  => array( 'editor' ),
			),
			array(
				'id'     => 'wording',
				'label'  => __( 'Offer wording', 'bwc-gallery' ),
				'type'   => 'pair',
				'fields' => array(
					array(
						'id'      => 'one',
						'label'   => __( 'One', 'bwc-gallery' ),
						'type'    => 'text',
						'default' => 'offer',
					),
					array(
						'id'      => 'many',
						'label'   => __( 'Many', 'bwc-gallery' ),
						'type'    => 'text',
						'default' => 'offers',
					),
				),
			),
			array(
				'id'     => 'price_range',
				'label'  => __( 'Price range', 'bwc-gallery' ),
				'type'   => 'pair',
				'fields' => array(
					array(
						'id'      => 'low',
						'label'   => __( 'Lowest', 'bwc-gallery' ),
						'type'    => 'number',
						'default' => 0,
						'unit'    => '$',
						'isValid' => array( 'min' => 0 ),
					),
					array(
						'id'      => 'high',
						'label'   => __( 'Highest', 'bwc-gallery' ),
						'type'    => 'number',
						'default' => 500,
						'unit'    => '$',
						'isValid' => array( 'min' => 0 ),
					),
				),
			),
		),
	);
}

/**
 * The gallery's settings, meta box, category fields and profile section.
 *
 * @param Kit $kit The kit.
 */
function register_demo_fields( Kit $kit ): void {
	demo_settings(
		$kit->settings(
			array(
				'option' => 'bwc_gallery_settings',
				'sets'   => demo_sets(),
			)
		)
	);

	$kit->meta_box(
		array(
			'id'         => 'offer',
			'title'      => __( 'Offer details (Admin Kit Gallery)', 'bwc-gallery' ),
			'post_types' => array( 'post' ),
			'context'    => 'normal',
			'fields'     => array(
				array(
					'id'        => 'coupon_code',
					'label'     => __( 'Coupon code', 'bwc-gallery' ),
					'type'      => 'text',
					'monospace' => true,
					'isValid'   => array(
						'maxLength' => 40,
						'pattern'   => '[A-Za-z0-9_-]*',
					),
				),
				array(
					'id'       => 'offer_type',
					'label'    => __( 'Kind of offer', 'bwc-gallery' ),
					'type'     => 'select',
					'default'  => 'code',
					'elements' => array(
						'code' => __( 'Code', 'bwc-gallery' ),
						'deal' => __( 'Deal', 'bwc-gallery' ),
						'sale' => __( 'Sale', 'bwc-gallery' ),
					),
				),
				array(
					'id'    => 'deal_url',
					'label' => __( 'Where it goes', 'bwc-gallery' ),
					'type'  => 'url',
					'wide'  => true,
				),
				array(
					'id'             => 'verified',
					'label'          => __( 'Verified', 'bwc-gallery' ),
					'type'           => 'checkbox',
					'checkbox_label' => __( 'Someone checked that it works', 'bwc-gallery' ),
				),
				array(
					'id'      => 'votes',
					'label'   => __( 'Net votes', 'bwc-gallery' ),
					'type'    => 'number',
					'isValid' => array( 'min' => -1000 ),
				),
			),
		)
	);

	$kit->term_fields(
		array(
			'id'       => 'brand',
			'taxonomy' => 'category',
			'fields'   => array(
				array(
					'id'          => 'merchant_url',
					'label'       => __( 'Brand website', 'bwc-gallery' ),
					'type'        => 'url',
					'description' => __( 'Admin Kit Gallery example field.', 'bwc-gallery' ),
				),
				array(
					'id'       => 'status',
					'label'    => __( 'Brand status', 'bwc-gallery' ),
					'type'     => 'select',
					'default'  => 'draft',
					'elements' => array(
						'draft'  => __( 'Draft', 'bwc-gallery' ),
						'active' => __( 'Active', 'bwc-gallery' ),
						'paused' => __( 'Paused', 'bwc-gallery' ),
					),
				),
				array(
					'id'             => 'featured',
					'label'          => __( 'Featured', 'bwc-gallery' ),
					'type'           => 'checkbox',
					'checkbox_label' => __( 'List this brand first', 'bwc-gallery' ),
				),
			),
		)
	);

	$kit->user_fields(
		array(
			'id'     => 'team',
			'title'  => __( 'Admin Kit Gallery', 'bwc-gallery' ),
			'who'    => 'both',
			'fields' => array(
				array(
					'id'             => 'weekly_digest',
					'label'          => __( 'Weekly digest', 'bwc-gallery' ),
					'type'           => 'switch',
					'checkbox_label' => __( 'Email me a summary every Monday', 'bwc-gallery' ),
				),
				array(
					'id'          => 'cc',
					'label'       => __( 'Copy the digest to', 'bwc-gallery' ),
					'type'        => 'list',
					'item'        => 'email',
					'dependsOn'   => 'weekly_digest',
					'description' => __( 'One address per line.', 'bwc-gallery' ),
				),
			),
		)
	);
}

/**
 * Fields: every built field type in a working settings form.
 */
function section_fields(): string {
	$storage = demo_settings();
	if ( null === $storage ) {
		return '';
	}

	ob_start();
	settings_fields( $storage->group() );
	$hidden = (string) ob_get_clean();

	$where = sprintf(
		/* translators: 1: link to a post, 2: link to categories, 3: link to the profile. */
		esc_html__( 'The same fields also run in a meta box on %1$s, on %2$s and on %3$s.', 'bwc-gallery' ),
		'<a href="' . esc_url( admin_url( 'post-new.php' ) ) . '">' . esc_html__( 'new posts', 'bwc-gallery' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=category' ) ) . '">' . esc_html__( 'categories', 'bwc-gallery' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'profile.php' ) ) . '">' . esc_html__( 'your profile', 'bwc-gallery' ) . '</a>'
	);

	$form = '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">' . $hidden
		. Stack::markup(
			array(
				'gap'        => 6,
				'items_html' => array(
					example( __( 'Offers: text, email, number, length, checkbox with a dependent number, url, read-only, textarea, lists', 'bwc-gallery' ), $storage->form( 'offers' ) ),
					example( __( 'Schedule: switch with a dependent hour, time, select with a dependent number, radio, checkboxes, multiple select, roles, pairs', 'bwc-gallery' ), $storage->form( 'schedule' ) ),
					'<p>' . Button::markup(
						array(
							'label'   => __( 'Save changes', 'bwc-gallery' ),
							'variant' => 'primary',
							'type'    => 'submit',
						)
					) . '</p>',
				),
			)
		)
		. '</form>';

	return section(
		__( 'Fields', 'bwc-gallery' ),
		__( 'Every field type, saved to the bwc_gallery_settings option. Try saving a bad email or a number past its limit: bad values keep what was saved and say why.', 'bwc-gallery' ),
		array( '<p class="bwc-gallery-help">' . $where . '</p>', $form )
	);
}
