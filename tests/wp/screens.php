<?php
/**
 * Notices, Actions, the Shell and the settings screen. Loaded
 * by run.php. Writes only throwaway data (bwc_test_ options and the test
 * user's notices transient), each removed again.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Screen\SettingsScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Runs code that should call _doing_it_wrong(), without the notice, and
 * returns how many times it was called.
 *
 * @param callable $body Code to run.
 */
function bwc_doing_it_wrong( callable $body ): int {
	$count    = 0;
	$count_it = static function () use ( &$count ): void {
		++$count;
	};
	add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	add_action( 'doing_it_wrong_run', $count_it );
	$body();
	remove_action( 'doing_it_wrong_run', $count_it );
	remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );

	return $count;
}

/**
 * Settings groups for the screen tests: two groups, switches, a requirement,
 * a path into another bucket, an advanced field.
 *
 * @return array<string, array<string, mixed>>
 */
function bwc_test_settings_groups(): array {
	return array(
		'g1' => array(
			'label'    => 'Group one',
			'intro'    => 'First group.',
			'sections' => array(
				's1' => array(
					'label'       => 'S1',
					'description' => 'The first section.',
					'switch'      => array( 'default' => true ),
					'fields'      => array(
						array(
							'id'    => 'a',
							'label' => 'Alpha label',
							'type'  => 'text',
						),
						array(
							'id'      => 'n',
							'label'   => 'N',
							'type'    => 'number',
							'default' => 3,
							'path'    => 'other.n',
						),
					),
				),
				's2' => array(
					'label'    => 'S2',
					'switch'   => array( 'default' => false ),
					'requires' => array( array( 'section' => 's1' ) ),
					'fields'   => array(
						array(
							'id'    => 'c',
							'label' => 'C',
							'type'  => 'checkbox',
						),
						array(
							'id'       => 'adv',
							'label'    => 'Expert number',
							'type'     => 'number',
							'default'  => 1,
							'advanced' => true,
						),
					),
				),
			),
		),
		'g2' => array(
			'label'    => 'Group two',
			'sections' => array(
				's3' => array(
					'label'    => 'S3',
					'requires' => array( array( 'check' => static fn(): ?string => 'Needs a key.' ) ),
					'fields'   => array(
						array(
							'id'    => 'e',
							'label' => 'E',
							'type'  => 'email',
						),
					),
				),
			),
		),
	);
}

/**
 * A settings screen over bwc_test_screen, registered.
 *
 * @param array<string, mixed> $config Config over the defaults.
 */
function bwc_test_settings_screen( array $config = array() ): SettingsScreen {
	return Kit::instance()->settings_screen(
		$config + array(
			'id'     => 'settings',
			'option' => 'bwc_test_screen',
			'groups' => bwc_test_settings_groups(),
			'danger' => array(
				'label'       => 'Remove data on delete',
				'description' => 'Careful.',
				'checkbox'    => 'Also delete the data',
			),
		)
	);
}

/**
 * Stores the test option as it is, past any test screen's sanitize callback.
 *
 * @param array<string, mixed> $value Option value.
 */
function bwc_test_store( array $value ): void {
	remove_all_filters( 'sanitize_option_bwc_test_screen' );
	update_option( 'bwc_test_screen', $value );
}

/**
 * Forgets what the screen tests stored.
 */
function bwc_test_screen_cleanup(): void {
	delete_option( 'bwc_test_screen' );
	delete_transient( Kit::instance()->name( 'notices_' . get_current_user_id() ) );
	remove_all_actions( 'admin_post_bwc_test_bwc_save_settings' );
	unregister_setting( 'bwc_test_screen', 'bwc_test_screen' );
}

bwc_test(
	'notices: flashed once, field messages per form, text escaped',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		$notices = $kit->notices();
		$notices->take();

		$notices->flash( 'success', 'Settings saved.' );
		$notices->flash(
			'danger',
			'Two <b>lines</b> failed.',
			array(
				'title' => 'Careful',
				'links' => array(
					array(
						'label' => 'Emails',
						'url'   => '#bwc-test-a-emails',
					),
					array( 'label' => 'No URL' ),
				),
			)
		);
		$notices->flash( 'nonsense', 'Unknown tone becomes info.' );
		$notices->fields( 'settings:x', array( 'a.n' => 'Enter a number.' ), array() );

		$html = $notices->markup();
		bwc_same( 3, substr_count( $html, 'class="bwc-notice ' ), 'three notices' );
		bwc_same( true, str_contains( $html, 'Two &lt;b&gt;lines&lt;/b&gt; failed.' ), 'text escaped' );
		bwc_same( true, str_contains( $html, '<a href="#bwc-test-a-emails">Emails</a>' ), 'link kept' );
		bwc_same( false, str_contains( $html, 'No URL' ), 'link without a URL dropped' );
		bwc_same( true, str_contains( $html, 'bwc-notice--info' ), 'unknown tone' );
		bwc_same( '', $notices->markup(), 'shown once' );

		bwc_same(
			array(
				'errors' => array( 'a.n' => 'Enter a number.' ),
				'notes'  => array(),
			),
			$notices->take_fields( 'settings:x' ),
			'field messages'
		);
		bwc_same( array(), $notices->take_fields( 'settings:x' )['errors'], 'field messages read once' );
		bwc_same( false, get_transient( $kit->name( 'notices_1' ) ), 'nothing left behind' );

		wp_set_current_user( 0 );
		$notices->flash( 'success', 'Nobody to show it to.' );
		$notices->fields( 'settings:x', array( 'a.n' => 'Enter a number.' ), array() );
		bwc_same( false, get_transient( $kit->name( 'notices_0' ) ), 'no notice or field message without a user' );
		wp_set_current_user( 1 );
	}
);

bwc_test(
	'actions: capability, then nonce, then the declared input; a notice, and back to the same page',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		$actions = $kit->actions();
		$seen    = array();

		$actions->register(
			'test_run',
			static function ( array $input ) use ( &$seen ): array {
				$seen = $input;
				return array(
					'tone' => 'success',
					'text' => 'Ran.',
				);
			},
			array(
				'input' => array(
					'word' => 'text',
					'ids'  => 'int[]',
					'on'   => 'bool',
					'slug' => 'key',
				),
			)
		);

		$referer = '/wp-admin/admin.php?page=bwc-test&group=b&_wpnonce=old&action=old';
		$request = array(
			'_wpnonce'         => wp_create_nonce( 'bwc_test_bwc_test_run' ),
			'_wp_http_referer' => $referer,
			'word'             => ' <b>hi</b> ',
			'ids'              => array( '3', '4x', array( 'nested' ) ),
			'on'               => '1',
			'slug'             => 'Hello World',
			'extra'            => 'not declared',
		);

		$outcome = $actions->dispatch( 'test_run', $request );
		bwc_same( admin_url( 'admin.php?page=bwc-test&group=b' ), $outcome['redirect'], 'back to the same view, without the kit\'s arguments' );
		bwc_same(
			array(
				'word' => 'hi',
				'ids'  => array( 3, 4 ),
				'on'   => true,
				'slug' => 'helloworld',
			),
			$seen,
			'only the declared input, sanitized'
		);
		bwc_same( 'Ran.', $kit->notices()->take()[0]['text'], 'notice flashed' );

		bwc_same( 403, $actions->dispatch( 'test_run', array( '_wpnonce' => 'wrong' ) + $request )['status'], 'bad nonce' );
		bwc_same( 404, $actions->dispatch( 'nope', $request )['status'], 'unknown action' );
		bwc_same( admin_url(), $actions->dispatch( 'test_run', array( '_wp_http_referer' => 'https://evil.example/x' ) + $request )['redirect'], 'off-site referer goes to the dashboard' );
		$kit->notices()->take();

		wp_set_current_user( 0 );
		bwc_same( 403, $actions->dispatch( 'test_run', $request )['status'], 'no capability, checked before the nonce' );
		wp_set_current_user( 1 );

		$actions->register( 'test_fail', static fn(): WP_Error => new WP_Error( 'x', 'It broke.' ) );
		$actions->dispatch( 'test_fail', array( '_wpnonce' => wp_create_nonce( 'bwc_test_bwc_test_fail' ) ) );
		$taken = $kit->notices()->take();
		bwc_same( array( 'danger', 'It broke.' ), array( $taken[0]['tone'], $taken[0]['text'] ), 'a WP_Error is a danger notice' );

		$ran = false;
		$actions->register(
			'test_clear',
			static function () use ( &$ran ): ?array {
				$ran = true;
				return null;
			},
			array(
				'confirm'  => 'Sure?',
				'redirect' => static fn( array $input, mixed $result, string $back ): string => add_query_arg( 'cleared', '1', $back ),
			)
		);
		$clear = array(
			'_wpnonce'         => wp_create_nonce( 'bwc_test_bwc_test_clear' ),
			'_wp_http_referer' => '/wp-admin/admin.php?page=bwc-test',
		);
		bwc_same( array( 'confirm' => 'Sure?' ), $actions->dispatch( 'test_clear', $clear ), 'asks first without the script\'s confirmation' );
		bwc_same( false, $ran, 'not run before the answer' );
		$outcome = $actions->dispatch( 'test_clear', array( '_bwc_confirmed' => '1' ) + $clear );
		bwc_same( true, $ran, 'run once confirmed' );
		bwc_same( admin_url( 'admin.php?page=bwc-test&cleared=1' ), $outcome['redirect'], 'own redirect, built on the way back' );
		bwc_same( array(), $kit->notices()->take(), 'null result: no notice' );

		$inline = $actions->button(
			'test_run',
			'Run',
			array(
				'input' => array(
					'word' => 'x',
					'ids'  => array( 1, 2 ),
				),
			)
		);
		bwc_same( true, str_contains( $inline, '<form class="bwc-action" id="bwc-test-action-test-run-1" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="bwc_test_bwc_test_run">' ), 'form posts to the action' );
		bwc_same( true, str_contains( $inline, 'name="_wpnonce"' ) && str_contains( $inline, '<input type="hidden" name="ids[]" value="2">' ), 'nonce and input' );
		bwc_same( true, str_contains( $inline, '<button type="submit" class="bwc-button"><span class="bwc-button__label">Run</span></button></form>' ), 'button inside its form' );

		$inside = $actions->button( 'test_clear', 'Clear', array( 'inside_form' => true ) );
		bwc_same( true, str_contains( $inside, 'form="bwc-test-action-test-clear-1"' ) && str_contains( $inside, 'data-bwc-confirm="Sure?"' ) && str_contains( $inside, 'bwc-button--danger' ), 'inside another form: points at its own, asks first, red' );
		$forms = $actions->forms();
		bwc_same( true, str_contains( $forms, 'id="bwc-test-action-test-clear-1"' ) && str_contains( $forms, 'data-bwc-confirmed' ), 'its form comes later, with the confirmation flag' );
		bwc_same( '', $actions->forms(), 'forms printed once' );

		$actions->register( 'test_export', static fn(): ?array => null, array( 'method' => 'get' ) );
		$url = $actions->url( 'test_export', array( 'tab' => 'members' ) );
		bwc_same( true, str_contains( $url, 'action=bwc_test_bwc_test_export' ) && str_contains( $url, 'tab=members' ) && str_contains( $url, '_wpnonce=' ), 'GET link' );
		bwc_same( 1, bwc_doing_it_wrong( static fn() => $actions->url( 'test_run' ) ), 'no link for a POST action' );
		bwc_throws( static fn() => $actions->register( 'Bad Name', '__return_null' ), 'bad name' );
		bwc_throws( static fn() => $actions->register( 'x', '__return_null', array( 'input' => array( 'a' => 'html' ) ) ), 'unknown input type' );

		foreach ( array( 'test_run', 'test_fail', 'test_clear', 'test_export' ) as $name ) {
			remove_all_actions( 'admin_post_bwc_test_bwc_' . $name );
		}
	}
);

bwc_test(
	'shell: a page per screen, tabs with badges, hidden screens reached by link only',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$saved = array();
		foreach ( array( 'menu', 'submenu', '_registered_pages', 'admin_page_hooks', '_parent_pages' ) as $global ) {
			$saved[ $global ] = $GLOBALS[ $global ] ?? array();
		}

		$a     = $kit->screen(
			array(
				'id'     => 'overview',
				'label'  => 'Overview',
				'render' => static fn(): string => '<p>Overview body</p>',
			)
		);
		$b     = $kit->screen(
			array(
				'id'    => 'links',
				'label' => 'Links',
				'badge' => static fn(): array => array(
					'count' => 3,
					'tone'  => 'danger',
				),
			)
		);
		$c     = $kit->screen(
			array(
				'id'     => 'detail',
				'label'  => 'Detail',
				'hidden' => true,
			)
		);
		$shell = new BlissWebConcept\AdminKit\Shell\Shell(
			$kit,
			array(
				'menu'    => array(
					'title' => 'Test Plugin',
					'slug'  => 'bwc-test',
				),
				'header'  => array( 'version' => '1.2.3' ),
				'screens' => array( $a, $b, $c ),
			)
		);

		bwc_same( array( 'bwc-test', 'bwc-test-links', 'bwc-test-detail' ), array( $shell->slug( 'overview' ), $shell->slug( 'links' ), $shell->slug( 'detail' ) ), 'slugs' );
		bwc_same( admin_url( 'admin.php?page=bwc-test-links' ), $b->url(), 'screen URL' );
		bwc_same( admin_url( 'admin.php?page=bwc-test-links&status=broken' ), $b->url( array( 'status' => 'broken' ) ), 'with arguments' );

		$shell->menu();
		$shown = array();
		foreach ( $GLOBALS['submenu']['bwc-test'] ?? array() as $item ) {
			$shown[ $item[2] ] = ! str_contains( (string) ( $item[4] ?? '' ), 'hidden' );
		}
		bwc_same(
			array(
				'bwc-test'        => true,
				'bwc-test-links'  => true,
				'bwc-test-detail' => false,
			),
			$shown,
			'menu items; the hidden screen\'s is there but hidden'
		);
		bwc_same( true, isset( $GLOBALS['_registered_pages'][ get_plugin_page_hookname( 'bwc-test-detail', get_admin_page_parent( 'bwc-test' ) ) ] ), 'the hidden screen opens' );
		$GLOBALS['plugin_page'] = 'bwc-test-detail'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- stands in for WordPress during the test, unset below.
		bwc_same( 'bwc-test', apply_filters( 'submenu_file', 'x', 'bwc-test' ), 'the first item shows as current on it' );
		unset( $GLOBALS['plugin_page'] );
		bwc_same( true, $kit->assets()->is_kit_screen( (string) get_plugin_page_hookname( 'bwc-test-links', 'bwc-test' ) ), 'assets load there' );

		$html = $shell->markup( $a );
		bwc_same( true, str_contains( $html, '<h1 class="bwc-screen__title">Test Plugin</h1>' ), 'header title from the menu title' );
		bwc_same( 2, substr_count( $html, 'class="bwc-nav__item' ), 'two tabs' );
		bwc_same( true, str_contains( $html, 'aria-current="page"' ) && str_contains( $html, 'bwc-nav__badge--danger">3' ), 'current tab and badge' );
		bwc_same( true, str_contains( $html, '<p>Overview body</p>' ) && str_contains( $html, '>v1.2.3</span>' ), 'body and version' );

		wp_set_current_user( 0 );
		bwc_same( false, str_contains( $shell->markup( $a ), 'Overview body' ), 'not shown without the capability' );
		wp_set_current_user( 1 );

		$under = new BlissWebConcept\AdminKit\Shell\Shell(
			$kit,
			array(
				'menu'    => array(
					'title'  => 'Small plugin',
					'parent' => 'options-general.php',
					'slug'   => 'small-plugin',
				),
				'screens' => array( $kit->screen( array( 'id' => 'settings' ) ) ),
			)
		);
		bwc_same( admin_url( 'options-general.php?page=small-plugin' ), $under->url( 'settings' ), 'under Settings' );
		bwc_same( false, str_contains( $under->markup( $under->current() ), 'bwc-nav' ), 'one screen: no tabs' );

		$two = new BlissWebConcept\AdminKit\Shell\Shell(
			$kit,
			array(
				'menu'    => array(
					'title'  => 'Under another',
					'parent' => 'bwc-test',
					'slug'   => 'bwc-test-sub',
				),
				'screens' => array( $kit->screen( array( 'id' => 'one' ) ), $kit->screen( array( 'id' => 'two' ) ) ),
			)
		);
		$two->menu();
		$sub = array();
		foreach ( $GLOBALS['submenu']['bwc-test'] as $item ) {
			$sub[ $item[2] ] = ! str_contains( (string) ( $item[4] ?? '' ), 'hidden' );
		}
		bwc_same( array( true, false ), array( $sub['bwc-test-sub'], $sub['bwc-test-sub-two'] ), 'under another menu: only the first shows' );
		bwc_same( true, isset( $GLOBALS['_registered_pages'][ get_plugin_page_hookname( 'bwc-test-sub-two', 'bwc-test' ) ] ), 'the second still opens (the reported bug)' );
		remove_all_filters( 'parent_file' );
		remove_all_filters( 'submenu_file' );

		foreach ( $saved as $global => $value ) {
			$GLOBALS[ $global ] = $value;
		}
	}
);

bwc_test(
	'settings screen: paths and buckets, switches and the danger box are fields, requirements pause a section',
	static function (): void {
		bwc_test_kit();
		wp_set_current_user( 1 );
		delete_option( 'bwc_test_screen' );
		$screen = bwc_test_settings_screen();
		$schema = $screen->storage()->schema()['properties'];

		bwc_same( array( 'modules', 's1', 'other', 's2', 's3', 'general' ), array_keys( $schema ), 'buckets' );
		bwc_same( array( 's1', 's2' ), array_keys( $schema['modules']['properties'] ), 'switches in modules' );
		bwc_same( 'boolean', $schema['modules']['properties']['s1']['type'], 'switch is a boolean' );
		bwc_same( array( 'n' ), array_keys( $schema['other']['properties'] ), 'a path into another bucket' );
		bwc_same( array( 'delete_data' ), array_keys( $schema['general']['properties'] ), 'danger box' );

		bwc_same( 3, $screen->get( 'other.n' ), 'default on read' );
		bwc_same( true, $screen->enabled( 's1' ), 'switch on by default' );
		bwc_same( false, $screen->enabled( 's2' ), 'switch off by default' );

		bwc_test_store( array( 'modules' => array( 's2' => true ) ) );
		$screen = bwc_test_settings_screen();
		bwc_same( true, $screen->enabled( 's2' ), 'on, and its requirement is on' );

		bwc_test_store(
			array(
				'modules' => array(
					's1' => false,
					's2' => true,
				),
			)
		);
		$screen = bwc_test_settings_screen();
		bwc_same( true, $screen->switched_on( 's2' ), 'still switched on' );
		bwc_same( false, $screen->enabled( 's2' ), 'but paused' );
		bwc_same( array( 'Needs S1 to be on.' ), $screen->reasons( 's2' ), 'the reason names the other section' );
		bwc_same( array( 'Needs a key.' ), $screen->reasons( 's3' ), 'a check\'s reason' );
		bwc_same( false, $screen->enabled( 's3' ), 'no switch, but a requirement' );

		$groups                                     = bwc_test_settings_groups();
		$groups['g2']['sections']['s3']['fields'][] = array(
			'id'    => 'n',
			'label' => 'Clash',
			'type'  => 'text',
			'path'  => 'other.n',
		);
		$groups['g2']['sections']['s1']             = array( 'label' => 'Twice' );
		$groups['g2']['sections']['s4']['fields'][] = array(
			'id'    => 'x',
			'label' => 'X',
			'type'  => 'text',
			'path'  => 'nobucket',
		);
		$wrong                                      = bwc_doing_it_wrong(
			static function () use ( $groups ): void {
				bwc_test_settings_screen( array( 'groups' => $groups ) );
			}
		);
		bwc_same( 3, $wrong, 'a taken path, a section twice and a path without a key are each refused' );

		$filter = static function ( array $groups ): array {
			$groups['g2']['sections']['extra'] = array(
				'label'  => 'From the filter',
				'fields' => array(
					array(
						'id'    => 'z',
						'label' => 'Zed',
						'type'  => 'text',
					),
				),
			);
			return $groups;
		};
		add_filter( 'bwc_test_bwc_settings_settings_groups', $filter );
		$screen = bwc_test_settings_screen();
		remove_filter( 'bwc_test_bwc_settings_settings_groups', $filter );
		bwc_same( true, isset( $screen->storage()->schema()['properties']['extra']['properties']['z'] ), 'the groups filter adds fields that save like the rest' );

		bwc_test_screen_cleanup();
	}
);

bwc_test(
	'settings save: one form for every group, markers, bad values keep the old one, back to the group, the saved hook',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		delete_option( 'bwc_test_screen' );
		bwc_test_store( array( 'other' => array( 'n' => 7 ) ) );
		$screen = bwc_test_settings_screen();
		$hooked = array();
		$saved  = static function ( array $after, array $before ) use ( &$hooked ): void {
			$hooked = array( $after, $before );
		};
		add_action( 'bwc_test_bwc_settings_settings_saved', $saved, 10, 2 );

		bwc_same( 'save_settings', $screen->action(), 'action name' );
		bwc_same( true, $kit->actions()->has( 'save_settings' ), 'registered' );

		$request = array(
			'_wpnonce'         => wp_create_nonce( 'bwc_test_bwc_save_settings' ),
			'_wp_http_referer' => '/wp-admin/admin.php?page=bwc-test&group=g1',
			'_bwc_group'       => 'g2',
			'bwc_test_screen'  => array(
				's1'     => array( 'a' => ' Hello ' ),
				'other'  => array( 'n' => 'abc' ),
				's3'     => array( 'e' => 'ann@example.com' ),
				'_shown' => array(
					'modules' => array( 's1' => '1' ),
					's2'      => array( 'c' => '1' ),
				),
			),
		);
		$outcome = $kit->actions()->dispatch( 'save_settings', $request );

		bwc_same( admin_url( 'admin.php?page=bwc-test&group=g2' ), $outcome['redirect'], 'back to the group that was open' );
		bwc_same(
			array(
				'other'   => array( 'n' => 7 ),
				'modules' => array( 's1' => false ),
				's1'      => array( 'a' => 'Hello' ),
				's2'      => array( 'c' => false ),
				's3'      => array( 'e' => 'ann@example.com' ),
			),
			get_option( 'bwc_test_screen' ),
			'switched off by its marker, the bad number kept its old value, the rest saved'
		);
		bwc_same( array( 'n' => 7 ), $hooked[1]['other'], 'the saved hook gets the option before' );
		bwc_same( false, $hooked[0]['modules']['s1'], 'and after' );

		$notice = $kit->notices()->take()[0];
		bwc_same( 'warning', $notice['tone'], 'a warning when something wasn\'t saved' );
		bwc_same( 'Settings saved, except 1 that wasn\'t valid. It kept its old value.', $notice['text'], 'says so' );
		bwc_same(
			array(
				array(
					'label' => 'N',
					'url'   => '#bwc-test-other-n',
				),
			),
			$notice['links'],
			'links to the field'
		);
		bwc_same( array( 'other.n' => 'Enter a number.' ), $kit->notices()->take_fields( 'settings:bwc_test_screen' )['errors'], 'the field\'s message waits for the page' );

		$kit->actions()->dispatch(
			'save_settings',
			array(
				'bwc_test_screen' => array( 's1' => array( 'a' => 'Again' ) ),
				'_bwc_group'      => 'nope',
			) + $request
		);
		$stored = get_option( 'bwc_test_screen' );
		bwc_same( array( 'Again', false, 'ann@example.com' ), array( $stored['s1']['a'], $stored['modules']['s1'], $stored['s3']['e'] ), 'only what was posted changes' );
		bwc_same( 'Settings saved.', $kit->notices()->take()[0]['text'], 'all good' );

		remove_action( 'bwc_test_bwc_settings_settings_saved', $saved );
		bwc_test_screen_cleanup();

		$tabs = bwc_test_settings_screen( array( 'navigation' => 'tabs' ) );
		$back = $kit->actions()->dispatch( 'save_settings', array( 'bwc_test_screen' => array() ) + $request );
		bwc_same( admin_url( 'admin.php?page=bwc-test&group=g1&tab=g2' ), $back['redirect'], 'tabs use ?tab=' );
		unset( $tabs );
		bwc_test_screen_cleanup();
	}
);

bwc_test(
	'settings markup: side menu counts, switches, paused section, advanced fold, danger last',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		bwc_test_store(
			array(
				'modules' => array(
					's1' => false,
					's2' => true,
				),
			)
		);
		$kit->notices()->fields( 'settings:bwc_test_screen', array( 's2.adv' => 'Use 1 or more.' ), array() );
		$_GET['group'] = 'g2';
		$html          = bwc_test_settings_screen()->render();
		unset( $_GET['group'] );

		bwc_same( true, str_contains( $html, '<form class="bwc-settings bwc-settings--side" id="bwc-test-settings" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" autocomplete="off"' ), 'one form' );
		bwc_same( true, str_contains( $html, '<input type="hidden" name="action" value="bwc_test_bwc_save_settings">' ) && str_contains( $html, 'name="_bwc_group" value="g2"' ), 'saves through the kit\'s action, remembering the group' );
		bwc_same( true, str_contains( $html, 'data-bwc-nav-item="g1"><span class="bwc-nav__label">Group one</span><span class="bwc-nav__meta" data-bwc-nav-meta>1 of 2 on</span>' ), 'side menu count' );
		bwc_same( true, str_contains( $html, 'class="bwc-settings__group is-current" id="bwc-test-settings-group-g2"' ), 'the group from the URL is open' );
		bwc_same( true, str_contains( $html, 'class="bwc-settings__group" id="bwc-test-settings-group-g1"' ), 'the other is printed too' );
		bwc_same( true, str_contains( $html, '<input class="bwc-switch__input" type="checkbox" role="switch" id="bwc-test-modules-s1" name="bwc_test_screen[modules][s1]" value="1" aria-labelledby="bwc-test-settings-section-s1-name" aria-describedby="bwc-test-settings-section-s1-description" data-bwc-section-switch>' ), 'switch, labelled by its section' );
		bwc_same( true, str_contains( $html, '<input type="hidden" name="bwc_test_screen[_shown][modules][s1]" value="1">' ), 'switch marker' );
		bwc_same( true, str_contains( $html, 'class="bwc-section has-switch is-paused"' ) && str_contains( $html, 'Needs S1 to be on.' ), 'paused section and its reason' );
		bwc_same( true, str_contains( $html, 'Turn on to change its 2 settings.' ), 'off note' );
		bwc_same( true, str_contains( $html, '<details class="bwc-advanced" open><summary class="bwc-advanced__summary">Advanced</summary>' ), 'advanced fold, open for its error' );
		bwc_same( true, str_contains( $html, 'id="bwc-test-s2-adv"' ) && str_contains( $html, 'Use 1 or more.' ), 'the error is on its field' );
		bwc_same( true, str_contains( $html, 'data-bwc-search="s1 the first section. group one alpha label n"' ), 'search text' );
		bwc_same( true, strrpos( $html, 'bwc-section--danger' ) > strrpos( $html, 'section-s3' ), 'danger last' );
		bwc_same( true, str_contains( $html, 'name="bwc_test_screen[general][delete_data]"' ), 'danger box' );
		bwc_same( false, str_contains( $html, 'data-bwc-settings-search' ), 'no search for two groups' );
		bwc_same( false, str_contains( $html, 'class="bwc-notice bwc-notice--danger"' ), 'no summary per section: the flash notice lists the errors' );
		bwc_test_screen_cleanup();

		$tabs = bwc_test_settings_screen(
			array(
				'navigation' => 'tabs',
				'search'     => true,
			)
		)->render();
		bwc_same( true, str_contains( $tabs, 'class="bwc-panel__tab is-current" href="#bwc-test-settings-group-g1" aria-current="true" data-bwc-nav-item="g1"' ), 'panel tabs switch in the page' );
		bwc_same( true, str_contains( $tabs, '<div class="bwc-settings__search" data-bwc-settings-search-box hidden>' ), 'search box waits for the script' );
		bwc_test_screen_cleanup();

		$one = bwc_test_settings_screen(
			array(
				'navigation' => 'side',
				'groups'     => array( 'g1' => bwc_test_settings_groups()['g1'] ),
			)
		)->render();
		bwc_same( true, str_contains( $one, 'bwc-settings--none' ) && ! str_contains( $one, 'bwc-nav' ), 'one group: no navigation' );
		bwc_test_screen_cleanup();
	}
);
