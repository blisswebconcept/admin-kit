<?php
/**
 * Gallery: whole screens in their own Shell (two settings screens and a list),
 * plus the Actions and Notices demos.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Cluster;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Icon;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Kit;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Shell\Shell;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the demo actions, the two settings screens and their Shell.
 * Runs at init: the descriptions hold translated labels.
 *
 * @param Kit $kit The kit.
 */
function register_settings_demo( Kit $kit ): void {
	register_demo_actions( $kit );

	// Another module or add-on adding a section through the extension filter.
	add_filter( $kit->name( 'settings_settings_groups' ), __NAMESPACE__ . '\\add_extension_section' );

	// Reacting to a save through the extension action.
	add_action(
		$kit->name( 'settings_settings_saved' ),
		static function ( array $after, array $before ) use ( $kit ): void {
			$changed = 0;
			foreach ( $after as $bucket => $values ) {
				$changed += count( array_diff_assoc( array_map( 'wp_json_encode', (array) $values ), array_map( 'wp_json_encode', (array) ( $before[ $bucket ] ?? array() ) ) ) );
			}
			$kit->notices()->flash(
				'info',
				/* translators: %d: number of values that changed. */
				sprintf( _n( 'The "saved" hook ran: %d value changed.', 'The "saved" hook ran: %d values changed.', $changed, 'bwc-gallery' ), $changed )
			);
		},
		10,
		2
	);

	$danger = array(
		'path'        => 'general.delete_data',
		'label'       => __( 'Remove data on delete', 'bwc-gallery' ),
		'description' => __( 'Turn on only when retiring the plugin for good, because it can\'t be undone.', 'bwc-gallery' ),
		'checkbox'    => __( 'When the plugin is deleted, also delete its votes, links, activity log and these settings.', 'bwc-gallery' ),
	);

	$side = $kit->settings_screen(
		array(
			'id'         => 'settings',
			'label'      => __( 'Side menu', 'bwc-gallery' ),
			'option'     => 'bwc_gallery_side',
			'navigation' => 'side',
			'groups'     => settings_demo_groups(),
			'danger'     => $danger,
		)
	);

	$tabs = $kit->settings_screen(
		array(
			'id'         => 'settings-tabs',
			'label'      => __( 'Tabs', 'bwc-gallery' ),
			'option'     => 'bwc_gallery_tabs',
			'navigation' => 'tabs',
			'intro'      => __( 'The same settings with tabs instead of a side menu, as a plugin with many groups would show them. Saved in their own option.', 'bwc-gallery' ),
			'groups'     => settings_demo_groups(),
			'danger'     => $danger,
		)
	);

	$shell = $kit->shell(
		array(
			'menu'    => array(
				'title'      => __( 'Admin Kit Gallery: screens', 'bwc-gallery' ),
				'menu_title' => __( 'Screens', 'bwc-gallery' ),
				'parent'     => 'bwc-gallery',
				'slug'       => 'bwc-gallery-settings',
			),
			'header'  => array(
				'title'     => __( 'Screens', 'bwc-gallery' ),
				'subtitle'  => __( 'Whole screens, described in a few arrays; the kit draws and saves them.', 'bwc-gallery' ),
				'version'   => Kit::VERSION,
				'icon_html' => Icon::markup(
					array(
						'name' => 'settings',
						'size' => 20,
					)
				),
				'actions'   => static fn(): string => '<a class="bwc-button bwc-button--outline bwc-button--small" href="' . esc_url( admin_url( 'admin.php?page=bwc-gallery' ) ) . '"><span class="bwc-button__label">' . esc_html__( 'Back to the gallery', 'bwc-gallery' ) . '</span></a>',
			),
			'screens' => array_merge( array( $side, $tabs, register_list_demo( $kit ) ), register_jobs_demo( $kit ), array( register_report_demo( $kit ) ) ),
		)
	);
	settings_demo_shell( $shell );
}

/**
 * The demo actions: one that works, one that fails, one that asks first.
 *
 * @param Kit $kit The kit.
 */
function register_demo_actions( Kit $kit ): void {
	$actions = $kit->actions();

	$actions->register(
		'demo_ping',
		static fn( array $input ): array => array(
			'tone'  => 'success',
			'title' => __( 'Done.', 'bwc-gallery' ),
			/* translators: %s: the word typed into the demo. */
			'text'  => '' === $input['word'] ? __( 'The action ran and came back to the same place.', 'bwc-gallery' ) : sprintf( __( 'The action ran with "%s" and came back to the same place.', 'bwc-gallery' ), $input['word'] ),
		),
		array( 'input' => array( 'word' => 'text' ) )
	);

	$actions->register(
		'demo_fail',
		static fn(): \WP_Error => new \WP_Error( 'bwc_gallery_demo', __( 'The network didn\'t answer, so nothing changed. Try again in a few minutes.', 'bwc-gallery' ) )
	);

	$actions->register(
		'demo_clear',
		static function (): array {
			delete_option( 'bwc_gallery_side' );
			delete_option( 'bwc_gallery_tabs' );

			return array(
				'tone' => 'success',
				'text' => __( 'Both demo screens are back to their defaults.', 'bwc-gallery' ),
			);
		},
		array( 'confirm' => __( 'Put both demo settings screens back to their defaults? What you saved on them is lost.', 'bwc-gallery' ) )
	);
}

/**
 * Adds a section to the Team group, as another module would.
 *
 * @param array<string, array<string, mixed>> $groups Groups.
 * @return array<string, array<string, mixed>>
 */
function add_extension_section( array $groups ): array {
	if ( isset( $groups['team'] ) ) {
		$groups['team']['sections']['addon'] = array(
			'label'       => __( 'Added by an add-on', 'bwc-gallery' ),
			'description' => __( 'This section comes from the extension filter. It saves like the rest.', 'bwc-gallery' ),
			'fields'      => array(
				array(
					'id'          => 'signature',
					'label'       => __( 'Email signature', 'bwc-gallery' ),
					'type'        => 'text',
					'placeholder' => __( 'The team at Acme Deals', 'bwc-gallery' ),
				),
			),
		);
	}

	return $groups;
}

/**
 * The demo's groups: every built field type, module switches, a paused
 * section, advanced fields, a reference, a rendered block and action buttons.
 *
 * @return array<string, array<string, mixed>>
 */
function settings_demo_groups(): array {
	$fields = array();
	foreach ( demo_sets() as $set ) {
		foreach ( $set as $field ) {
			$fields[ $field['id'] ] = $field;
		}
	}

	$pick = static function ( string ...$ids ) use ( $fields ): array {
		return array_values( array_intersect_key( $fields, array_flip( $ids ) ) );
	};

	$advanced = static fn( array $chosen ): array => array_map(
		static fn( array $field ): array => $field + array( 'advanced' => true ),
		$chosen
	);

	return array(
		'offers'   => array(
			'label'    => __( 'Offers', 'bwc-gallery' ),
			'intro'    => __( 'How offers look, how visitors vote on them and when they expire.', 'bwc-gallery' ),
			'sections' => array(
				'buttons' => array(
					'label'       => __( 'Offer buttons', 'bwc-gallery' ),
					'description' => __( 'The words and links on every offer card.', 'bwc-gallery' ),
					'fields'      => $pick( 'button_text', 'fallback_url', 'intro' ),
				),
				'voting'  => array(
					'label'       => __( 'Voting', 'bwc-gallery' ),
					'description' => __( 'Visitors say whether a code worked. Offers show their success rate.', 'bwc-gallery' ),
					'switch'      => array( 'default' => true ),
					'link'        => array(
						'label' => __( 'See the votes', 'bwc-gallery' ),
						'url'   => admin_url( 'admin.php?page=bwc-gallery&section=data' ),
					),
					'fields'      => $pick( 'votes_per_hour', 'wording' ),
				),
				'expiry'  => array(
					'label'       => __( 'Expiry', 'bwc-gallery' ),
					'description' => __( 'Marks an offer expired when its end date passes or visitors say it stopped working.', 'bwc-gallery' ),
					'switch'      => array( 'default' => false ),
					'requires'    => array( array( 'section' => 'voting' ) ),
					'fields'      => array_merge( $pick( 'mode', 'min_votes', 'age_rule', 'expire_after' ), $advanced( $pick( 'card_gap', 'skip_paths' ) ) ),
				),
			),
		),
		'networks' => array(
			'label'    => __( 'Networks', 'bwc-gallery' ),
			'intro'    => __( 'Fetching offers from affiliate networks, and the short links they use.', 'bwc-gallery' ),
			'sections' => array(
				'fetch' => array(
					'label'       => __( 'Network fetch', 'bwc-gallery' ),
					'description' => __( 'Brings in new offers from your networks every morning, for you to review.', 'bwc-gallery' ),
					'switch'      => array( 'default' => true ),
					'requires'    => array(
						array(
							'check' => static fn(): ?string => defined( 'BWC_GALLERY_NETWORK_KEY' ) ? null : __( 'Needs a network key in wp-config.php (BWC_GALLERY_NETWORK_KEY).', 'bwc-gallery' ),
						),
					),
					'fields'      => $pick( 'daily_sync', 'sync_hour', 'digest_time', 'networks', 'api_base' ),
				),
				'links' => array(
					'label'       => __( 'Short links', 'bwc-gallery' ),
					'description' => __( 'Gives every brand a /go/ link, so changing where it points updates every offer.', 'bwc-gallery' ),
					'switch'      => array( 'default' => true ),
					'fields'      => $pick( 'redirect', 'post_types' ),
					'reference'   => __NAMESPACE__ . '\\settings_demo_reference',
				),
			),
		),
		'team'     => array(
			'label'    => __( 'Team', 'bwc-gallery' ),
			'sections' => array(
				'review' => array(
					'label'       => __( 'Reviewing offers', 'bwc-gallery' ),
					'description' => __( 'Who reviews new offers, and where the daily email goes.', 'bwc-gallery' ),
					'fields'      => $pick( 'reviewers', 'report_to', 'test_emails', 'price_range' ),
				),
			),
		),
		'more'     => array(
			'label'    => __( 'More fields', 'bwc-gallery' ),
			'intro'    => __( 'Keys stored safely, searchable pickers, ordered choices and rows you can add. Try dragging a choice, removing a chip and adding a row, then Discard: everything goes back to how the page loaded.', 'bwc-gallery' ),
			'sections' => more_fields_sections(),
		),
		'advanced' => array(
			'label'    => __( 'Advanced', 'bwc-gallery' ),
			'intro'    => __( 'Tools and settings you rarely need.', 'bwc-gallery' ),
			'sections' => array(
				'tools' => array(
					'label'       => __( 'Actions', 'bwc-gallery' ),
					'description' => __( 'Buttons that run something and come back here with a message. They sit inside the settings form, so their own forms are printed at the end of the screen.', 'bwc-gallery' ),
					'render'      => __NAMESPACE__ . '\\settings_demo_tools',
				),
			),
		),
	);
}

/**
 * A reference table under the Short links section.
 */
function settings_demo_reference(): string {
	$rows = array(
		array( '301', __( 'Permanent. Browsers remember it.', 'bwc-gallery' ) ),
		array( '302', __( 'Temporary. Checked every time.', 'bwc-gallery' ) ),
		array( '307', __( 'Temporary, and keeps the request as it was.', 'bwc-gallery' ) ),
	);

	$html = '<table class="bwc-reference"><thead><tr><th scope="col">' . esc_html__( 'Code', 'bwc-gallery' ) . '</th><th scope="col">' . esc_html__( 'What it means', 'bwc-gallery' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$html .= '<tr><td><code>' . esc_html( $row[0] ) . '</code></td><td>' . esc_html( $row[1] ) . '</td></tr>';
	}

	return $html . '</tbody></table>';
}

/**
 * The action buttons in the Advanced group.
 */
function settings_demo_tools(): string {
	$actions = Kit::instance()->actions();

	return Cluster::markup(
		array(
			'items_html' => array(
				$actions->button( 'demo_ping', __( 'Run an action', 'bwc-gallery' ), array( 'inside_form' => true ) ),
				$actions->button(
					'demo_ping',
					__( 'Run it with a word', 'bwc-gallery' ),
					array(
						'inside_form' => true,
						'input'       => array( 'word' => 'coupon' ),
						'variant'     => 'outline',
					)
				),
				$actions->button(
					'demo_fail',
					__( 'Run one that fails', 'bwc-gallery' ),
					array(
						'inside_form' => true,
						'variant'     => 'outline',
					)
				),
				$actions->button( 'demo_clear', __( 'Reset both demo screens', 'bwc-gallery' ), array( 'inside_form' => true ) ),
			),
		)
	);
}

/**
 * The settings Shell, kept for the static preview script.
 *
 * @param Shell|null $set The Shell, when registering it.
 */
function settings_demo_shell( ?Shell $set = null ): ?Shell {
	static $shell = null;
	if ( null !== $set ) {
		$shell = $set;
	}

	return $shell;
}

/**
 * Sections for the "More fields" group: the searchable and ordered field types.
 *
 * @return array<string, array<string, mixed>>
 */
function more_fields_sections(): array {
	return array(
		'connection' => array(
			'label'       => __( 'Email service', 'bwc-gallery' ),
			'description' => __( 'The key is stored encrypted and never shown again. A key in wp-config.php wins.', 'bwc-gallery' ),
			'fields'      => array(
				array(
					'id'       => 'api_key',
					'label'    => __( 'API key', 'bwc-gallery' ),
					'type'     => 'secret',
					'secret'   => 'demo_api_key',
					'constant' => 'BWC_GALLERY_API_KEY',
				),
			),
		),
		'pages'      => array(
			'label'       => __( 'Pages and people', 'bwc-gallery' ),
			'description' => __( 'Searchable pickers. Without JavaScript they fall back to a plain list.', 'bwc-gallery' ),
			'fields'      => array(
				array(
					'id'    => 'login_page',
					'label' => __( 'Login page', 'bwc-gallery' ),
					'type'  => 'post',
					'query' => array(
						'post_type' => 'page',
						'tree'      => true,
					),
				),
				array(
					'id'        => 'featured',
					'label'     => __( 'Featured posts', 'bwc-gallery' ),
					'type'      => 'post',
					'multiple'  => true,
					'max_items' => 5,
					'query'     => array( 'post_type' => 'post' ),
				),
				array(
					'id'       => 'team',
					'label'    => __( 'Team accounts', 'bwc-gallery' ),
					'type'     => 'user',
					'multiple' => true,
				),
				array(
					'id'       => 'topics',
					'label'    => __( 'Topics', 'bwc-gallery' ),
					'type'     => 'term',
					'multiple' => true,
					'query'    => array( 'taxonomy' => 'category' ),
				),
				array(
					'id'          => 'testers',
					'label'       => __( 'Testers', 'bwc-gallery' ),
					'type'        => 'list',
					'item'        => 'user',
					'description' => __( 'One username or email per line.', 'bwc-gallery' ),
				),
			),
		),
		'order'      => array(
			'label'  => __( 'Order and rules', 'bwc-gallery' ),
			'fields' => array(
				array(
					'id'        => 'brands_first',
					'label'     => __( 'Brands to list first', 'bwc-gallery' ),
					'type'      => 'ordered',
					'max_items' => 6,
					'default'   => array( 'northwind', 'contoso' ),
					'elements'  => array(
						'northwind' => 'Northwind',
						'contoso'   => 'Contoso',
						'tailspin'  => 'Tailspin',
						'fabrikam'  => 'Fabrikam',
						'wingtip'   => 'Wingtip',
						'proseware' => 'Proseware',
					),
				),
				array(
					'id'       => 'verdict',
					'label'    => __( 'Quiz verdict', 'bwc-gallery' ),
					'type'     => 'radio',
					'default'  => 'strict',
					'elements' => array(
						array(
							'value' => 'strict',
							'label' => __( 'Pass only with every answer right', 'bwc-gallery' ),
						),
						array(
							'value'       => 'partial',
							'label'       => __( 'Give partial credit from', 'bwc-gallery' ),
							'description' => __( 'Members pass once they reach this score.', 'bwc-gallery' ),
							'field'       => array(
								'id'      => 'partial_from',
								'type'    => 'number',
								'default' => 80,
								'unit'    => '%',
								'isValid' => array(
									'min' => 1,
									'max' => 100,
								),
							),
						),
					),
				),
				array(
					'id'        => 'rules',
					'label'     => __( 'Matching rules', 'bwc-gallery' ),
					'type'      => 'repeater',
					/* translators: %d: rule number. */
					'row_label' => __( 'Rule %d', 'bwc-gallery' ),
					'add_label' => __( 'Add a rule', 'bwc-gallery' ),
					'max_rows'  => 6,
					'wide'      => true,
					'fields'    => array(
						array(
							'id'      => 'key',
							'label'   => __( 'Key', 'bwc-gallery' ),
							'type'    => 'text',
							'isValid' => array( 'required' => true ),
						),
						array(
							'id'    => 'needle',
							'label' => __( 'Address contains', 'bwc-gallery' ),
							'type'  => 'text',
						),
						array(
							'id'      => 'limit',
							'label'   => __( 'Limit', 'bwc-gallery' ),
							'type'    => 'number',
							'default' => 10,
							'isValid' => array(
								'min' => 1,
								'max' => 50,
							),
						),
						array(
							'id'             => 'active',
							'label'          => __( 'On', 'bwc-gallery' ),
							'type'           => 'checkbox',
							'checkbox_label' => __( 'Use this rule', 'bwc-gallery' ),
						),
					),
				),
			),
		),
	);
}
