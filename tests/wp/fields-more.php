<?php
/**
 * Secrets and the harder fields. Loaded by run.php. Creates a
 * few throwaway posts, a term and a user, and removes them again.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Field\PickerSearch;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Storage\OptionStorage;
use BlissWebConcept\AdminKit\Storage\RecordStorage;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'BWC_TEST_SECRET_CONST' ) ) {
	define( 'BWC_TEST_SECRET_CONST', 'from-config' );
}

bwc_test(
	'secrets: encrypted at rest, masked, a constant wins, unreadable after the salts change',
	static function (): void {
		$secrets = bwc_test_kit()->secrets();
		$secrets->delete( 'test_key' );

		bwc_same( 'none', $secrets->source( 'test_key' ), 'nothing yet' );
		bwc_same( true, $secrets->set( 'test_key', 'xkeysib-abcdef1234' ), 'saved' );
		$stored = (string) get_option( 'bwc_test_bwc_secret_test_key' );
		bwc_same( true, str_starts_with( $stored, 'sodium:' ) && ! str_contains( $stored, 'abcdef' ), 'stored encrypted' );
		bwc_same( array( 'saved', 'xkeysib-abcdef1234' ), array( $secrets->source( 'test_key' ), $secrets->get( 'test_key' ) ), 'read back' );
		bwc_same( '••••••••1234', $secrets->masked( 'test_key' ), 'masked to its last four' );
		bwc_same( 'off', $GLOBALS['wpdb']->get_var( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'bwc_test_bwc_secret_test_key'" ) === 'off' ? 'off' : 'on-or-other', 'not autoloaded' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- checking how it was stored.

		$salt = static fn(): string => 'a new salt';
		add_filter( 'salt', $salt );
		bwc_same( array( 'unreadable', '' ), array( $secrets->source( 'test_key' ), $secrets->get( 'test_key' ) ), 'after the salts change' );
		remove_filter( 'salt', $salt );

		$secrets->define( 'from_config', array( 'constant' => 'BWC_TEST_SECRET_CONST' ) );
		$secrets->set( 'from_config', 'saved-one' );
		bwc_same( array( 'config', 'from-config' ), array( $secrets->source( 'from_config' ), $secrets->get( 'from_config' ) ), 'the constant wins' );

		$secrets->delete( 'test_key' );
		$secrets->delete( 'from_config' );
		bwc_same( 'none', $secrets->source( 'test_key' ), 'deleted' );
	}
);

bwc_test(
	'secret field: empty keeps the key, Remove deletes it, never echoed, never in REST',
	static function (): void {
		$kit   = bwc_test_kit();
		$type  = $kit->fields()->get( 'secret' );
		$field = $kit->fields()->normalize(
			array(
				'id'    => 'api_key',
				'label' => 'API key',
				'type'  => 'secret',
			)
		);
		$kit->secrets()->delete( 'api_key' );

		bwc_same( true, $type->sanitize( 'abc-1234567890', false, $field, array() )->value, 'a typed key is saved; the field stores only that one is set' );
		bwc_same( 'abc-1234567890', $kit->secrets()->get( 'api_key' ), 'the key is in Secrets' );
		bwc_same( true, $type->sanitize( '', true, $field, array() )->value, 'empty keeps it' );
		bwc_same( 'abc-1234567890', $kit->secrets()->get( 'api_key' ), 'still there' );

		$html = Form::markup(
			array(
				'fields'  => array( $field ),
				'storage' => new OptionStorage( $kit, 'x', array( 'conn' => array( $field ) ) ),
				'set'     => 'conn',
			)
		);
		bwc_same( false, str_contains( $html, 'abc-1234567890' ), 'never on the page' );
		bwc_same( true, str_contains( $html, 'type="password"' ) && str_contains( $html, 'placeholder="••••••••7890"' ) && str_contains( $html, 'name="x[conn][api_key__remove]"' ), 'masked placeholder and a Remove box' );
		bwc_same( true, str_contains( $html, 'Leave it empty to keep the saved key.' ), 'says so' );

		bwc_same( false, $type->sanitize( '', true, $field, array( 'api_key__remove' => '1' ) )->value, 'Remove' );
		bwc_same( 'none', $kit->secrets()->source( 'api_key' ), 'deleted' );
		bwc_same( null, $type->schema( $field ), 'not in REST' );

		$config = $kit->fields()->normalize(
			array(
				'id'       => 'cfg',
				'label'    => 'Key',
				'type'     => 'secret',
				'constant' => 'BWC_TEST_SECRET_CONST',
			)
		);
		$html   = Form::markup(
			array(
				'fields'  => array( $config ),
				'storage' => new OptionStorage( $kit, 'x', array( 'conn' => array( $config ) ) ),
				'set'     => 'conn',
			)
		);
		bwc_same( true, str_contains( $html, 'Set in wp-config.php (BWC_TEST_SECRET_CONST).' ) && ! str_contains( $html, 'type="password"' ), 'set in wp-config.php: no input' );
	}
);

bwc_test(
	'pickers: search by the field\'s own query and capability, only existing matches saved, plain fallbacks',
	static function (): void {
		$kit = bwc_test_kit();
		wp_set_current_user( 1 );
		$parent = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'BWC Test Parent',
			)
		);
		$child  = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'BWC Test Child',
				'post_parent' => $parent,
			)
		);
		$post   = wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'BWC Test Post',
			)
		);
		$user   = wp_insert_user(
			array(
				'user_login'   => 'bwc_test_person',
				'user_email'   => 'bwc-test-person@example.com',
				'user_pass'    => wp_generate_password(),
				'display_name' => 'BWC Test Person',
			)
		);
		$term   = wp_insert_term( 'BWC Test Topic', 'category' );
		$term   = is_array( $term ) ? (int) $term['term_id'] : 0;

		try {
			bwc_test_pickers( $kit, (int) $parent, (int) $child, (int) $post, (int) $user, $term );
		} finally {
			wp_delete_post( $child, true );
			wp_delete_post( $parent, true );
			wp_delete_post( $post, true );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( (int) $user );
			wp_delete_term( $term, 'category' );
		}
	}
);

/**
 * The picker checks, run between creating and removing their posts.
 *
 * @param Kit $kit    The test kit.
 * @param int $top    A page.
 * @param int $child  A page under it.
 * @param int $post   A post.
 * @param int $user   A user.
 * @param int $term   A category.
 */
function bwc_test_pickers( Kit $kit, int $top, int $child, int $post, int $user, int $term ): void {

		$pages = $kit->fields()->normalize(
			array(
				'id'       => 'pages',
				'label'    => 'Pages',
				'type'     => 'post',
				'multiple' => true,
				'query'    => array(
					'post_type' => 'page',
					'tree'      => true,
				),
			)
		);
		$type  = $kit->fields()->get( 'post' );
		$token = $type->token( $pages );

		$found = PickerSearch::answer( $token, 'BWC Test', 1 );
		$by    = array_column( $found['items'], null, 'value' );
		bwc_same( true, isset( $by[ $top ], $by[ $child ] ) && ! isset( $by[ $post ] ), 'pages only, as the field asks' );
		bwc_same( 0, $by[ $child ]['depth'], 'search results are flat' );
		bwc_same( true, str_starts_with( $by[ $child ]['detail'], 'Page · /' ), 'type and address' );

		// Browsing: every page in parent order, a page of results at a time.
		$browsed = array();
	for ( $page = 1, $more = true; $more && $page <= 30; $page++ ) {
		$answer  = PickerSearch::answer( $token, '', $page );
		$browsed = array_merge( $browsed, $answer['items'] );
		$more    = $answer['more'];
	}
		$at = array_search( $top, array_column( $browsed, 'value' ), true );
		bwc_same( array( $child, 1 ), false === $at ? null : array( $browsed[ $at + 1 ]['value'], $browsed[ $at + 1 ]['depth'] ), 'browsing: the child right after its parent, indented' );

		$order = ( static fn( array $parents ): array => \BlissWebConcept\AdminKit\Field\Type\Picker::tree_order( $parents ) )->bindTo( null, \BlissWebConcept\AdminKit\Field\Type\Picker::class );
		bwc_same(
			array(
				5 => 0,
				7 => 1,
				8 => 2,
				6 => 1,
				9 => 0,
				3 => 0,
				4 => 0,
			),
			$order(
				array(
					5 => 0,
					9 => 77,
					7 => 5,
					3 => 4,
					4 => 3,
					6 => 5,
					8 => 7,
				)
			),
			'tree order: siblings as given, a missing parent starts a branch, a loop kept flat'
		);
		bwc_same( true, is_wp_error( PickerSearch::answer( 'nope', '', 1 ) ), 'an unknown field can\'t be searched' );

		wp_set_current_user( 0 );
		bwc_same( 'bwc_search_cap', PickerSearch::answer( $token, '', 1 )->get_error_code(), 'nor without the capability' );
		wp_set_current_user( 1 );

		bwc_same( array( $child, $top ), $type->sanitize( array( (string) $child, $post, 999999, $top, $child ), array(), $pages, array() )->value, 'existing matches, once each, in order' );
		$single = $kit->fields()->normalize(
			array(
				'id'    => 'one',
				'label' => 'One',
				'type'  => 'post',
			)
		);
		bwc_same( $child, $type->sanitize( (string) $child, 0, $single, array() )->value, 'one: an id' );
		bwc_same( 0, $type->sanitize( (string) $post, 0, $single, array() )->value, 'a post that isn\'t a page is refused' );

		$html = Form::markup(
			array(
				'fields'  => array( $pages ),
				'storage' => new RecordStorage( 'x', array( 'pages' => array( $child ) ) ),
				'set'     => 's',
			)
		);
		bwc_same( true, str_contains( $html, 'data-bwc-picker="' . $token . '"' ) && str_contains( $html, 'data-bwc-picker-fallback' ), 'a picker over a plain select' );
		bwc_same( true, (bool) preg_match( '/<option value="' . $child . '" selected>— BWC Test Child<\/option>/', $html ), 'the select has the choice, indented' );
		bwc_same( true, str_contains( $html, '&quot;label&quot;:&quot;BWC Test Child&quot;' ), 'and the script gets the chips' );

		$users = $kit->fields()->normalize(
			array(
				'id'       => 'users',
				'label'    => 'Users',
				'type'     => 'user',
				'multiple' => true,
			)
		);
		$utype = $kit->fields()->get( 'user' );
		bwc_same( array( (int) $user ), array_values( array_intersect( array( (int) $user ), array_column( PickerSearch::answer( $utype->token( $users ), 'bwc-test-person', 1 )['items'], 'value' ) ) ), 'users by email' );
		$result = $utype->sanitize( "bwc_test_person\nnobody-here", array(), $users, array() );
		bwc_same( array( (int) $user ), $result->value, 'the no-script list: usernames to ids' );
		bwc_same( true, str_contains( $result->message, 'nobody-here' ), 'unknown ones named' );
		bwc_same(
			true,
			str_contains(
				Form::markup(
					array(
						'fields'  => array( $users ),
						'storage' => new RecordStorage( 'x', array( 'users' => array( $user ) ) ),
						'set'     => 's',
					)
				),
				'>bwc_test_person</textarea>'
			),
			'without the script: usernames, not a select of every user'
		);

		$topics = $kit->fields()->normalize(
			array(
				'id'    => 'topic',
				'label' => 'Topic',
				'type'  => 'term',
				'query' => array( 'taxonomy' => 'category' ),
			)
		);
		$ttype  = $kit->fields()->get( 'term' );
		bwc_same( true, in_array( $term, array_column( PickerSearch::answer( $ttype->token( $topics ), 'BWC Test Topic', 1 )['items'], 'value' ), true ), 'terms' );
		bwc_same( $term, $ttype->sanitize( (string) $term, 0, $topics, array() )->value, 'a term id' );
}

bwc_test(
	'ordered: by value or label, once each, in order, capped; a plain list without the script',
	static function (): void {
		$kit   = bwc_test_kit();
		$field = $kit->fields()->normalize(
			array(
				'id'        => 'brands',
				'label'     => 'Brands first',
				'type'      => 'ordered',
				'max_items' => 3,
				'elements'  => array(
					'g' => 'Northwind',
					'b' => 'Contoso',
					's' => 'Fabrikam',
					'c' => 'Tailspin',
				),
			)
		);
		$type  = $kit->fields()->get( 'ordered' );

		bwc_same( array( 'b', 'g' ), $type->sanitize( array( 'b', 'g', 'b' ), array(), $field, array() )->value, 'chips post values in order' );
		$lines = $type->sanitize( "fabrikam\nNorthwind\nAcme", array(), $field, array() );
		bwc_same( array( 's', 'g' ), $lines->value, 'the list matches labels' );
		bwc_same( true, $lines->error && str_contains( $lines->message, 'Acme' ), 'and names what it doesn\'t know' );
		$capped = $type->sanitize( array( 'g', 'b', 's', 'c' ), array(), $field, array() );
		bwc_same( array( array( 'g', 'b', 's' ), false ), array( $capped->value, $capped->error ), 'capped, with a note' );

		$html = Form::markup(
			array(
				'fields'  => array( $field ),
				'storage' => new RecordStorage( 'x', array( 'brands' => array( 's', 'g' ) ) ),
				'set'     => 's',
			)
		);
		bwc_same( true, str_contains( $html, ">Fabrikam\nNorthwind</textarea>" ) && str_contains( $html, 'You can use: Northwind, Contoso, Fabrikam, Tailspin.' ), 'without the script: labels, one per line, and the choices' );
		bwc_same( true, str_contains( $html, 'data-bwc-ordered-chosen="[&quot;s&quot;,&quot;g&quot;]"' ) && str_contains( $html, 'name="x[_shown][s][brands]"' ), 'the script gets the order; a marker for an empty list' );
	}
);

bwc_test(
	'repeater: rows through their sub-fields, removed and empty rows dropped, problems named, a template',
	static function (): void {
		$kit   = bwc_test_kit();
		$field = $kit->fields()->normalize(
			array(
				'id'        => 'rules',
				'label'     => 'Rules',
				'type'      => 'repeater',
				'row_label' => 'Rule %d',
				'max_rows'  => 2,
				'fields'    => array(
					array(
						'id'      => 'key',
						'label'   => 'Key',
						'type'    => 'text',
						'isValid' => array( 'required' => true ),
					),
					array(
						'id'      => 'limit',
						'label'   => 'Limit',
						'type'    => 'number',
						'default' => 10,
						'isValid' => array( 'max' => 50 ),
					),
					array(
						'id'    => 'on',
						'label' => 'On',
						'type'  => 'checkbox',
					),
				),
			)
		);
		$type  = $kit->fields()->get( 'repeater' );
		$saved = array(
			array(
				'key'   => 'old',
				'limit' => 5,
				'on'    => true,
				'_row'  => 'abc12345',
			),
		);

		$result = $type->sanitize(
			array(
				'2'  => array(
					'key'   => 'lesson',
					'limit' => '7',
					'on'    => '1',
					'_row'  => '',
				),
				'0'  => array(
					'key'   => '',
					'limit' => '8',
					'_row'  => 'abc12345',
				),
				// An empty row as a browser without scripts posts it: the default still in the number box.
				'n1' => array(
					'key'   => '',
					'limit' => '10',
					'_row'  => '',
				),
				'5'  => array(
					'key'     => 'gone',
					'_remove' => '1',
				),
			),
			$saved,
			$field,
			array()
		);
		bwc_same( 2, count( $result->value ), 'the empty and removed rows dropped' );
		bwc_same( array( 'lesson', 7, true ), array( $result->value[0]['key'], $result->value[0]['limit'], $result->value[0]['on'] ), 'rows in the order sent' );
		bwc_same( 8, strlen( $result->value[0]['_row'] ), 'a new row gets an id' );
		bwc_same( array( 'old', 8, false, 'abc12345' ), array( $result->value[1]['key'], $result->value[1]['limit'], $result->value[1]['on'], $result->value[1]['_row'] ), 'a bad value keeps the row\'s saved one; an unticked box is off' );
		bwc_same( true, $result->error && str_contains( $result->message, 'Rule 2, Key: This can\'t be left empty.' ), 'the problem is named by row and field' );

		$three = array_fill(
			0,
			3,
			array(
				'key'   => 'k',
				'limit' => '1',
			)
		);
		bwc_same( 2, count( $type->sanitize( $three, array(), $field, array() )->value ), 'no more than max_rows' );

		$html = Form::markup(
			array(
				'fields'  => array( $field ),
				'storage' => new RecordStorage( 'x', array( 'rules' => $saved ) ),
				'set'     => 's',
			)
		);
		bwc_same( 4, substr_count( $html, '<li class="bwc-repeater__row" data-bwc-repeater-row' ) - 1, 'the saved row and three empty ones (plus the template)' );
		bwc_same( true, str_contains( $html, 'value="old" id="bwc-test-s-rules-0-key" name="x[s][rules][0][key]"' ) && str_contains( $html, 'name="x[s][rules][0][_row]" value="abc12345"' ), 'names by row' );
		bwc_same( true, str_contains( $html, '<template data-bwc-repeater-template>' ) && str_contains( $html, 'x[s][rules][__i__][key]' ), 'a template for new rows' );
		bwc_same( true, str_contains( $html, 'name="x[s][rules][0][_remove]"' ), 'a Remove box for browsers without the script' );
	}
);

bwc_test(
	'radio with an option\'s own field: its own key, active only while chosen, printed inside the option',
	static function (): void {
		bwc_test_kit();
		$fields = array(
			array(
				'id'       => 'verdict',
				'label'    => 'Verdict',
				'type'     => 'radio',
				'default'  => 'strict',
				'elements' => array(
					array(
						'value' => 'strict',
						'label' => 'All right',
					),
					array(
						'value' => 'partial',
						'label' => 'Partial from',
						'field' => array(
							'id'      => 'partial_from',
							'type'    => 'number',
							'default' => 80,
							'isValid' => array( 'max' => 100 ),
						),
					),
				),
			),
		);

		$normal = Form::normalize( $fields );
		bwc_same( array( 'verdict', 'partial_from' ), array_column( $normal, 'id' ), 'the option\'s field is a field of its own' );
		bwc_same( 80, Form::defaults( $fields )['partial_from'], 'with its default' );

		$html = Form::markup(
			array(
				'fields'  => $fields,
				'storage' => new RecordStorage( 'x', array() ),
				'set'     => 's',
			)
		);
		bwc_same( 1, substr_count( $html, 'name="x[s][partial_from]"' ), 'printed once, inside the option' );
		bwc_same( true, (bool) preg_match( '/<span class="bwc-choices__inline" data-bwc-depends="s\.verdict" data-bwc-depends-is="\[&quot;partial&quot;\]" inert><input aria-label="Partial from"/', $html ), 'inert until its option is chosen, named by the option' );

		$posted = array(
			's' => array(
				'verdict'      => 'partial',
				'partial_from' => '120',
			),
		);
		$clean  = Form::sanitize( $fields, 's', $posted, Form::defaults( $fields ) );
		bwc_same( array( 'partial', 100 ), array( $clean['values']['verdict'], $clean['values']['partial_from'] ), 'saved under its own key, through its own checks' );
		$off = Form::sanitize(
			$fields,
			's',
			array(
				's' => array(
					'verdict'      => 'strict',
					'partial_from' => '500',
				),
			),
			Form::defaults( $fields )
		);
		bwc_same( array(), $off['notes'] + $off['errors'], 'no messages while its option isn\'t chosen' );
	}
);

bwc_test(
	'list of users: usernames or emails to ids, unknown lines named, ids back to usernames',
	static function (): void {
		$kit   = bwc_test_kit();
		$user  = wp_insert_user(
			array(
				'user_login' => 'bwc_test_lines',
				'user_email' => 'bwc-test-lines@example.com',
				'user_pass'  => wp_generate_password(),
			)
		);
		$field = $kit->fields()->normalize(
			array(
				'id'    => 'team',
				'label' => 'Team',
				'type'  => 'list',
				'item'  => 'user',
			)
		);
		$type  = $kit->fields()->get( 'list' );

		$result = $type->sanitize( "bwc-test-lines@example.com\nghost", array(), $field, array() );
		bwc_same( array( (int) $user ), $result->value, 'stored as ids' );
		bwc_same( true, str_contains( $result->message, 'ghost' ), 'unknown lines named' );
		bwc_same( 'integer', $type->schema( $field )['items']['type'], 'REST: integers' );
		bwc_same(
			true,
			str_contains(
				Form::markup(
					array(
						'fields'  => array( $field ),
						'storage' => new RecordStorage( 'x', array( 'team' => array( $user ) ) ),
						'set'     => 's',
					)
				),
				'>bwc_test_lines</textarea>'
			),
			'shown as usernames'
		);

		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $user );
	}
);
