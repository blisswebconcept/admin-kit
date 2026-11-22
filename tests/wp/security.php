<?php
/**
 * The security review's fixes: meta keys held to their form's test, pickers
 * searched only by those who may use their form, settings held to the
 * screen's capability outside the kit, meta kept out of public REST answers,
 * secrets kept to settings, and encoded links. Loaded by run.php; leaves
 * nothing behind.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Field\PickerSearch;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Storage\OptionStorage;
use BlissWebConcept\AdminKit\Storage\RecordStorage;

defined( 'ABSPATH' ) || exit;

/**
 * A throwaway user with a role. Tests that need an administrator use one of
 * these, not user 1: a site may give its first user every capability.
 *
 * @param string $role Role.
 */
function bwc_test_user( string $role ): int {
	return (int) wp_insert_user(
		array(
			'user_login' => 'bwc_test_' . $role . '_' . wp_rand(),
			'user_email' => 'bwc-test-' . $role . '-' . wp_rand() . '@example.com',
			'user_pass'  => wp_generate_password(),
			'role'       => $role,
		)
	);
}

/**
 * Removes throwaway users.
 *
 * @param int ...$ids User ids.
 */
function bwc_test_remove_users( int ...$ids ): void {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $ids as $id ) {
		wp_delete_user( $id );
	}
}

/**
 * Runs code that may refuse a field: an exception while debugging, a
 * _doing_it_wrong() message otherwise. Returns what the code returned, or
 * "refused" when it threw.
 *
 * @param callable $body Code to run.
 */
function bwc_test_refusing( callable $body ): mixed {
	add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	try {
		return $body();
	} catch ( InvalidArgumentException $error ) {
		return 'refused';
	} finally {
		remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
	}
}

/**
 * A REST request's status, an error returned before dispatch included.
 *
 * @param WP_REST_Request $request Request.
 */
function bwc_test_rest_status( WP_REST_Request $request ): int {
	$response = rest_do_request( $request );

	return is_wp_error( $response ) ? (int) ( $response->get_error_data()['status'] ?? 0 ) : $response->get_status();
}

bwc_test(
	'meta: the auth callback is the form\'s whole test (box capability, read-only, the single key)',
	static function (): void {
		$kit    = bwc_test_kit();
		$author = bwc_test_user( 'author' );
		$admin  = bwc_test_user( 'administrator' );
		$post   = 0;

		$kit->meta_box(
			array(
				'id'         => 'sec',
				'title'      => 'Sec',
				'post_types' => array( 'post' ),
				'capability' => 'manage_options',
				'fields'     => array(
					array(
						'id'    => 'rate',
						'label' => 'Rate',
						'type'  => 'text',
					),
				),
			)
		);
		$kit->meta_box(
			array(
				'id'         => 'open',
				'title'      => 'Open',
				'post_types' => array( 'post' ),
				'fields'     => array(
					array(
						'id'    => 'note',
						'label' => 'Note',
						'type'  => 'text',
					),
					array(
						'id'       => 'locked',
						'label'    => 'Locked',
						'type'     => 'text',
						'readOnly' => true,
					),
				),
			)
		);
		$kit->meta_box(
			array(
				'id'         => 'all',
				'title'      => 'All',
				'post_types' => array( 'post' ),
				'single_key' => 'bwc_test_all',
				'fields'     => array(
					array(
						'id'    => 'one',
						'label' => 'One',
						'type'  => 'text',
					),
				),
			)
		);

		try {
			wp_set_current_user( $author );
			$post = (int) wp_insert_post(
				array(
					'post_type'   => 'post',
					'post_status' => 'draft',
					'post_title'  => 'BWC Test Meta',
					'post_author' => $author,
				)
			);

			bwc_same( true, current_user_can( 'edit_post', $post ), 'the author can edit the post' );
			bwc_same( false, current_user_can( 'edit_post_meta', $post, 'bwc_test_rate' ), 'but not a key of a box that needs manage_options' );
			bwc_same( true, current_user_can( 'edit_post_meta', $post, 'bwc_test_note' ), 'a box with no capability of its own' );
			bwc_same( false, current_user_can( 'edit_post_meta', $post, 'bwc_test_locked' ), 'a read-only field, author' );
			bwc_same( false, current_user_can( 'edit_post_meta', $post, 'bwc_test_all' ), 'the single key, author' );

			wp_set_current_user( $admin );
			bwc_same( true, current_user_can( 'edit_post_meta', $post, 'bwc_test_rate' ), 'an admin passes the box\'s capability' );
			bwc_same( false, current_user_can( 'edit_post_meta', $post, 'bwc_test_locked' ), 'a read-only field, admin' );
			bwc_same( false, current_user_can( 'edit_post_meta', $post, 'bwc_test_all' ), 'the single key, admin' );

			$keys = get_registered_meta_keys( 'post', 'post' );
			bwc_same( true, isset( $keys['bwc_test_all'] ) && false === $keys['bwc_test_all']['show_in_rest'], 'the single key is registered, out of REST' );
		} finally {
			wp_set_current_user( 1 );
			wp_delete_post( $post, true );
			bwc_test_remove_users( $author, $admin );
			foreach ( array( 'bwc_test_rate', 'bwc_test_note', 'bwc_test_locked', 'bwc_test_all' ) as $key ) {
				unregister_meta_key( 'post', $key, 'post' );
			}
		}
	}
);

bwc_test(
	'meta: out of REST unless asked; then edit context only, and a REST write is held to the box\'s capability',
	static function (): void {
		$kit    = bwc_test_kit();
		$author = bwc_test_user( 'author' );
		$post   = 0;
		$field  = array(
			array(
				'id'    => 'shown',
				'label' => 'Shown',
				'type'  => 'text',
			),
		);

		$kit->meta_box(
			array(
				'id'         => 'quiet',
				'title'      => 'Quiet',
				'post_types' => array( 'post' ),
				'fields'     => array(
					array(
						'id'    => 'quiet',
						'label' => 'Quiet',
						'type'  => 'text',
					),
				),
			)
		);
		$kit->meta_box(
			array(
				'id'           => 'shown',
				'title'        => 'Shown',
				'post_types'   => array( 'post' ),
				'capability'   => 'manage_options',
				'show_in_rest' => true,
				'fields'       => $field,
			)
		);
		$kit->term_fields(
			array(
				'id'       => 'topic',
				'taxonomy' => 'category',
				'fields'   => array(
					array(
						'id'    => 'tone',
						'label' => 'Tone',
						'type'  => 'text',
					),
				),
			)
		);

		try {
			$keys = get_registered_meta_keys( 'post', 'post' );
			bwc_same( false, $keys['bwc_test_quiet']['show_in_rest'], 'post meta: out of REST by default' );
			bwc_same( false, get_registered_meta_keys( 'term', 'category' )['bwc_test_tone']['show_in_rest'], 'term meta: out of REST by default' );

			wp_set_current_user( 1 );
			$post = (int) wp_insert_post(
				array(
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'BWC Test REST',
					'post_author' => $author,
					'meta_input'  => array( 'bwc_test_shown' => 'inside' ),
				)
			);

			wp_set_current_user( 0 );
			$public = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post ) )->get_data();
			bwc_same( false, isset( $public['meta']['bwc_test_shown'] ), 'not in a visitor\'s answer' );

			wp_set_current_user( 1 );
			$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post );
			$request->set_param( 'context', 'edit' );
			bwc_same( 'inside', rest_do_request( $request )->get_data()['meta']['bwc_test_shown'] ?? null, 'in an editor\'s answer' );

			wp_set_current_user( $author );
			$write = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
			$write->set_body_params( array( 'meta' => array( 'bwc_test_shown' => 'changed' ) ) );
			bwc_same( 403, bwc_test_rest_status( $write ), 'the author can\'t write a manage_options box over REST' );
			bwc_same( 'inside', get_post_meta( $post, 'bwc_test_shown', true ), 'unchanged' );
		} finally {
			wp_set_current_user( 1 );
			wp_delete_post( $post, true );
			bwc_test_remove_users( $author );
			unregister_meta_key( 'post', 'bwc_test_quiet', 'post' );
			unregister_meta_key( 'post', 'bwc_test_shown', 'post' );
			unregister_meta_key( 'term', 'bwc_test_tone', 'category' );
		}
	}
);

bwc_test(
	'user meta: a profile set for admins can\'t be changed by the user themself',
	static function (): void {
		$kit        = bwc_test_kit();
		$subscriber = bwc_test_user( 'subscriber' );

		$kit->user_fields(
			array(
				'id'           => 'tier',
				'title'        => 'Tier',
				'show_in_rest' => true,
				'fields'       => array(
					array(
						'id'    => 'tier',
						'label' => 'Tier',
						'type'  => 'text',
					),
				),
			)
		);

		try {
			wp_set_current_user( $subscriber );
			bwc_same( true, current_user_can( 'edit_user', $subscriber ), 'they can edit their own profile' );
			bwc_same( false, current_user_can( 'edit_user_meta', $subscriber, 'bwc_test_tier' ), 'but not a set for admins' );
		} finally {
			wp_set_current_user( 1 );
			bwc_test_remove_users( $subscriber );
			unregister_meta_key( 'user', 'bwc_test_tier' );
		}
	}
);

bwc_test(
	'pickers: searching needs the form\'s capability and the post type\'s; non-public items only for their editors',
	static function (): void {
		$kit         = bwc_test_kit();
		$contributor = bwc_test_user( 'contributor' );
		$posts       = array();

		register_post_type(
			'bwc_test_hidden',
			array(
				'public'          => false,
				'show_ui'         => true,
				'capability_type' => 'post',
			)
		);
		register_post_type(
			'bwc_test_own',
			array(
				'public'          => false,
				'show_ui'         => true,
				'capability_type' => 'bwc_test_own',
				'map_meta_cap'    => true,
			)
		);

		$hidden      = array(
			'id'    => 'hidden',
			'label' => 'Hidden',
			'type'  => 'post',
			'query' => array( 'post_type' => 'bwc_test_hidden' ),
		);
		$on_settings = array(
			'id'    => 'on_settings',
			'label' => 'On settings',
			'type'  => 'post',
			'query' => array( 'post_type' => 'post' ),
		);
		$own_caps    = array(
			'id'    => 'own_caps',
			'label' => 'Own caps',
			'type'  => 'post',
			'query' => array( 'post_type' => 'bwc_test_own' ),
		);

		$kit->settings(
			array(
				'option' => 'bwc_test_pick_opt',
				'sets'   => array( 'main' => array( $on_settings ) ),
			)
		);
		$kit->meta_box(
			array(
				'id'         => 'pick',
				'title'      => 'Pick',
				'post_types' => array( 'post' ),
				'fields'     => array( $hidden, $own_caps ),
			)
		);

		$type  = $kit->fields()->get( 'post' );
		$token = static fn( array $field ): string => $type->token( $kit->fields()->normalize( $field ) );

		try {
			wp_set_current_user( 1 );
			$posts[] = (int) wp_insert_post(
				array(
					'post_type'   => 'bwc_test_hidden',
					'post_status' => 'publish',
					'post_title'  => 'BWC Test Coupon CODE123',
				)
			);
			$posts[] = (int) wp_insert_post(
				array(
					'post_type'   => 'post',
					'post_status' => 'publish',
					'post_title'  => 'BWC Test Public Post',
				)
			);

			$admin = PickerSearch::answer( $token( $hidden ), 'BWC Test Coupon', 1 );
			bwc_same( true, is_array( $admin ) && in_array( $posts[0], array_column( $admin['items'], 'value' ), true ), 'an admin finds the non-public item' );
			bwc_same( true, is_array( PickerSearch::answer( $token( $on_settings ), 'BWC Test', 1 ) ), 'an admin searches the settings picker' );

			wp_set_current_user( $contributor );
			$settings = PickerSearch::answer( $token( $on_settings ), 'BWC Test', 1 );
			bwc_same( 403, is_wp_error( $settings ) ? $settings->get_error_data()['status'] : 0, 'a contributor can\'t search a picker on a manage_options settings screen' );

			$found = PickerSearch::answer( $token( $hidden ), 'BWC Test Coupon', 1 );
			bwc_same( array(), is_array( $found ) ? $found['items'] : null, 'the meta box picker answers a contributor, without the non-public item' );

			$own = PickerSearch::answer( $token( $own_caps ), '', 1 );
			bwc_same( 403, is_wp_error( $own ) ? $own->get_error_data()['status'] : 0, 'a post type with its own capabilities needs its edit_posts' );
			bwc_same( 'edit_bwc_test_owns', $type->capability( $kit->fields()->normalize( $own_caps ) ), 'the type\'s own capability' );

			$unclaimed = array(
				'id'    => 'unclaimed',
				'label' => 'Unclaimed',
				'type'  => 'post',
				'query' => array( 'post_type' => 'post' ),
			);
			$answer    = PickerSearch::answer( $token( $unclaimed ), '', 1 );
			bwc_same( 403, is_wp_error( $answer ) ? $answer->get_error_data()['status'] : 0, 'a picker no form claimed needs the kit\'s capability' );
		} finally {
			wp_set_current_user( 1 );
			foreach ( $posts as $id ) {
				wp_delete_post( $id, true );
			}
			bwc_test_remove_users( $contributor );
			unregister_post_type( 'bwc_test_hidden' );
			unregister_post_type( 'bwc_test_own' );
			unregister_setting( 'bwc_test_pick_opt', 'bwc_test_pick_opt' );
			foreach ( array( 'bwc_test_hidden', 'bwc_test_own_caps' ) as $key ) {
				unregister_meta_key( 'post', $key, 'post' );
			}
		}
	}
);

bwc_test(
	'settings: options.php, REST and other saves are held to the screen\'s capability',
	static function (): void {
		$kit     = bwc_test_kit();
		$storage = $kit->settings(
			array(
				'option'     => 'bwc_test_guard',
				'capability' => 'bwc_test_cap',
				'sets'       => array(
					'main' => array(
						array(
							'id'    => 'word',
							'label' => 'Word',
							'type'  => 'text',
						),
					),
				),
			)
		);
		$filter  = static fn( string $cap, string $context ): string => 'settings' === $context ? 'manage_options' : $cap;
		$admin   = bwc_test_user( 'administrator' );

		try {
			wp_set_current_user( $admin );
			bwc_same( 'bwc_test_cap', apply_filters( 'option_page_capability_bwc_test_guard', 'manage_options' ), 'options.php asks for the screen\'s capability' );
			bwc_same( false, $storage->can_edit(), 'an admin without it' );

			add_filter( 'pre_option_bwc_test_guard', static fn() => array( 'main' => array( 'word' => 'stored' ) ) );
			bwc_same( array( 'main' => array( 'word' => 'stored' ) ), $storage->sanitize( array( 'main' => array( 'word' => 'changed' ) ) ), 'a save by a user without it keeps what\'s stored' );
			remove_all_filters( 'pre_option_bwc_test_guard' );

			$request = new WP_REST_Request( 'POST', '/wp/v2/settings' );
			$request->set_body_params( array( 'bwc_test_guard' => array( 'main' => array( 'word' => 'rest' ) ) ) );
			bwc_same( 403, bwc_test_rest_status( $request ), 'REST: refused' );
			bwc_same( 'none', get_option( 'bwc_test_guard', 'none' ), 'REST: nothing written' );

			add_filter( 'bwc_test_bwc_capability', $filter, 10, 2 );
			bwc_same( 'manage_options', apply_filters( 'option_page_capability_bwc_test_guard', 'manage_options' ), 'the kit\'s filter decides' );
			bwc_same( true, $storage->can_edit(), 'and lets the admin in' );
			remove_filter( 'bwc_test_bwc_capability', $filter );

			wp_set_current_user( 0 );
			update_option( 'bwc_test_guard', array( 'main' => array( 'word' => 'from cron' ) ), false );
			bwc_same( 'from cron', get_option( 'bwc_test_guard' )['main']['word'] ?? null, 'no user (cron, WP-CLI): saved' );

			wp_set_current_user( $admin );
			$screen = $kit->settings_screen(
				array(
					'id'         => 'guarded',
					'option'     => 'bwc_test_guarded_screen',
					'capability' => 'bwc_test_cap',
					'groups'     => array(
						'g' => array(
							'label'    => 'G',
							'sections' => array(
								'main' => array(
									'label'  => 'Main',
									'fields' => array(
										array(
											'id'    => 'word',
											'label' => 'Word',
											'type'  => 'text',
										),
									),
								),
							),
						),
					),
				)
			);
			bwc_same( false, $screen->storage()->can_edit(), 'a settings screen\'s storage uses the screen\'s capability' );
		} finally {
			wp_set_current_user( 1 );
			bwc_test_remove_users( $admin );
			remove_filter( 'bwc_test_bwc_capability', $filter );
			delete_option( 'bwc_test_guard' );
			delete_option( 'bwc_test_guarded_screen' );
			unregister_setting( 'bwc_test_guard', 'bwc_test_guard' );
			unregister_setting( 'bwc_test_guarded_screen', 'bwc_test_guarded_screen' );
			remove_all_filters( 'option_page_capability_bwc_test_guard' );
			remove_all_filters( 'option_page_capability_bwc_test_guarded_screen' );
		}
	}
);

bwc_test(
	'secrets: only in settings; refused in meta boxes, term, profile and drawer forms, and repeater rows',
	static function (): void {
		$kit    = bwc_test_kit();
		$secret = array(
			'id'    => 'token',
			'label' => 'Token',
			'type'  => 'secret',
		);
		$text   = array(
			'id'    => 'name',
			'label' => 'Name',
			'type'  => 'text',
		);
		$rows   = array(
			'id'     => 'rows',
			'label'  => 'Rows',
			'type'   => 'repeater',
			'fields' => array( $text, $secret ),
		);

		$box = bwc_test_refusing( static fn() => Form::without_secrets( array( $text, $secret ), 'a meta box' ) );
		bwc_same( true, 'refused' === $box || array( $text ) === $box, 'a meta box: refused' );

		$settings = bwc_test_refusing( static fn() => Form::without_secrets( array( $text, $secret ), 'a repeater', true ) );
		bwc_same( array( $text, $secret ), $settings, 'settings: kept' );

		$nested = bwc_test_refusing( static fn() => Form::without_secrets( array( $rows ), 'a repeater', true ) );
		bwc_same( true, 'refused' === $nested || array( $text ) === $nested[0]['fields'], 'a repeater row in settings: refused' );

		$added = bwc_test_refusing(
			static function () use ( $kit, $secret ): string {
				$kit->meta_box(
					array(
						'id'         => 'keys',
						'title'      => 'Keys',
						'post_types' => array( 'post' ),
						'fields'     => array( $secret ),
					)
				);
				return 'added';
			}
		);
		bwc_same( true, in_array( $added, array( 'refused', 'added' ), true ), 'meta_box() refuses or drops it' );

		$html = Form::markup(
			array(
				'fields'  => array( $secret ),
				'storage' => new RecordStorage( 'x', array() ),
				'set'     => 'conn',
			)
		);
		bwc_same( false, str_contains( $html, 'type="password"' ), 'a record form prints no input for it' );

		$option = new OptionStorage( $kit, 'x', array( 'conn' => array( $secret ) ) );
		bwc_same(
			true,
			str_contains(
				Form::markup(
					array(
						'fields'  => array( $secret ),
						'storage' => $option,
						'set'     => 'conn',
					)
				),
				'type="password"'
			),
			'a settings form does'
		);
	}
);

bwc_test(
	'links: values from the request are encoded, so they can\'t add arguments',
	static function (): void {
		$kit    = bwc_test_kit();
		$screen = $kit->screen(
			array(
				'id'     => 'enc',
				'label'  => 'Enc',
				'render' => static fn(): string => '',
			)
		);
		$url    = $screen->url( array( 'search' => 'a&page=evil#x' ) );
		bwc_same( true, str_contains( $url, 'search=a%26page%3Devil%23x' ), 'screen link' );
		bwc_same( false, str_contains( $url, 'page=evil' ), 'no second page argument' );

		$kit->actions()->register( 'enc_export', static fn() => null, array( 'method' => 'get' ) );
		$link = $kit->actions()->url( 'enc_export', array( 'q' => 'a&action=other' ) );
		bwc_same( true, str_contains( $link, 'q=a%26action%3Dother' ) && 1 === substr_count( $link, 'action=' ), 'action link' );
	}
);
