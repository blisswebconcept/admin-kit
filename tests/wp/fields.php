<?php
/**
 * Field types, the form and the storage adapters. Loaded by run.php.
 * Writes only throwaway data (a bwc_test_ option, a test post, term and user
 * meta), each removed again.
 *
 * @package BlissWebConcept\AdminKit
 */

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Field\Result;
use BlissWebConcept\AdminKit\Field\Validator;
use BlissWebConcept\AdminKit\Storage\PostMeta;
use BlissWebConcept\AdminKit\Storage\TermMeta;
use BlissWebConcept\AdminKit\Storage\UserMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes a raw value with a field's type (description normalised first).
 *
 * @param array<string, mixed> $field  Field description.
 * @param mixed                $raw    Submitted value.
 * @param mixed                $stored Stored value.
 */
function bwc_sanitize( array $field, mixed $raw, mixed $stored = null ): Result {
	$kit   = bwc_test_kit();
	$field = $kit->fields()->normalize( $field + array( 'label' => 'Test' ) );

	return $kit->fields()->get( (string) $field['type'] )->sanitize( $raw, $stored ?? $field['default'], $field, array() );
}

bwc_test(
	'registry: the built types, and descriptions that can\'t be used',
	static function (): void {
		$kit = bwc_test_kit();
		bwc_same( array( 'text', 'textarea', 'list', 'number', 'length', 'email', 'url', 'checkbox', 'switch', 'checkboxes', 'radio', 'select', 'hour', 'time', 'role', 'pair', 'secret', 'post', 'user', 'term', 'ordered', 'repeater' ), $kit->fields()->names(), 'type names' );
		bwc_throws(
			static fn() => $kit->fields()->normalize(
				array(
					'id'    => 'Bad-Id',
					'label' => 'X',
					'type'  => 'text',
				)
			),
			'bad id'
		);
		bwc_throws(
			static fn() => $kit->fields()->normalize(
				array(
					'id'   => 'a',
					'type' => 'text',
				)
			),
			'no label'
		);
		bwc_throws(
			static fn() => $kit->fields()->normalize(
				array(
					'id'    => 'a',
					'label' => 'A',
					'type'  => 'nope',
				)
			),
			'unknown type'
		);
		$field = $kit->fields()->normalize(
			array(
				'id'    => 'a',
				'label' => 'A',
				'type'  => 'number',
			)
		);
		bwc_same( array( 0, 0, false, '' ), array( $field['default'], $field['decimals'], $field['wide'], $field['unit'] ), 'common keys, type keys and defaults filled in' );
	}
);

bwc_test(
	'text, textarea, email, url: cleaned, bad ones kept out',
	static function (): void {
		bwc_same(
			'Get Code',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'text',
				),
				" <b>Get</b> Code\n"
			)->value,
			'text: tags and line breaks gone'
		);
		bwc_same(
			"Line one\nLine two",
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'textarea',
				),
				"Line one\nLine two<script>x</script>"
			)->value,
			'textarea keeps lines'
		);
		bwc_same(
			'ada@example.com',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'email',
				),
				' ada@example.com '
			)->value,
			'email'
		);
		$bad = bwc_sanitize(
			array(
				'id'   => 'a',
				'type' => 'email',
			),
			'not an email',
			'kept@example.com'
		);
		bwc_same( array( false, 'kept@example.com', 'That isn\'t an email address.' ), array( $bad->saved(), $bad->value, $bad->message ), 'bad email keeps the stored one and says why' );
		bwc_same(
			'',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'email',
				),
				''
			)->value,
			'empty email allowed'
		);
		bwc_same(
			'https://example.com/a?b=1',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'url',
				),
				'https://example.com/a?b=1'
			)->value,
			'url'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'url',
				),
				'javascript:alert(1)'
			)->saved(),
			'other schemes rejected'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'url',
				),
				'not a url'
			)->saved(),
			'not a url rejected'
		);
		bwc_same(
			'Use an address on this site.',
			bwc_sanitize(
				array(
					'id'    => 'a',
					'type'  => 'url',
					'local' => true,
				),
				'https://elsewhere.example/x'
			)->message,
			'local only'
		);
		bwc_same(
			true,
			bwc_sanitize(
				array(
					'id'    => 'a',
					'type'  => 'url',
					'local' => true,
				),
				home_url( '/login/' )
			)->saved(),
			'this site is fine'
		);
	}
);

bwc_test(
	'list: one item per line, bad lines named, duplicates and the cap',
	static function (): void {
		bwc_same(
			array( 'a@example.com', 'b@example.com' ),
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'list',
					'item' => 'email',
				),
				"a@example.com\r\n\n b@example.com \na@example.com"
			)->value,
			'trimmed, empty and repeated lines dropped'
		);
		$bad = bwc_sanitize(
			array(
				'id'   => 'a',
				'type' => 'list',
				'item' => 'path',
			),
			"/ok\nno-slash\n/also ok\n/fine"
		);
		bwc_same( array( true, true, array( '/ok', '/fine' ) ), array( $bad->saved(), $bad->error, $bad->value ), 'good lines saved, shown as an error' );
		bwc_same( 'These lines weren\'t saved: no-slash, /also ok.', $bad->message, 'bad lines named' );
		$cap = bwc_sanitize(
			array(
				'id'        => 'a',
				'type'      => 'list',
				'max_items' => 2,
			),
			"a\nb\nc"
		);
		bwc_same( array( array( 'a', 'b' ), false, 'Only the first 2 were kept.' ), array( $cap->value, $cap->error, $cap->message ), 'cap is a note, not an error' );
		bwc_same(
			array( 'x', 'y' ),
			bwc_sanitize(
				array(
					'id'         => 'a',
					'type'       => 'list',
					'separators' => "\n,",
				),
				'x, y'
			)->value,
			'commas too when asked'
		);
		bwc_same(
			array( 'main-nav' ),
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'list',
					'item' => 'id',
				),
				"main-nav\n#bad"
			)->value,
			'html ids'
		);
	}
);

bwc_test(
	'number: whole or decimal, clamped with a note, not a number rejected, empty is the default',
	static function (): void {
		$field = array(
			'id'      => 'days',
			'type'    => 'number',
			'default' => 30,
			'isValid' => array(
				'min' => 1,
				'max' => 365,
			),
		);
		bwc_same( 90, bwc_sanitize( $field, '90' )->value, 'whole number' );
		$high = bwc_sanitize( $field, '900' );
		bwc_same( array( true, 365, false, 'Set to 365, the most allowed.' ), array( $high->saved(), $high->value, $high->error, $high->message ), 'clamped to the most' );
		bwc_same( 'Set to 1, the least allowed.', bwc_sanitize( $field, '-4' )->message, 'clamped to the least' );
		$text = bwc_sanitize( $field, 'ten', 45 );
		bwc_same( array( false, 45, 'Enter a number.' ), array( $text->saved(), $text->value, $text->message ), 'not a number keeps the stored one' );
		bwc_same( 30, bwc_sanitize( $field, '' )->value, 'empty goes back to the default' );
		bwc_same(
			2.35,
			bwc_sanitize(
				array(
					'id'       => 'p',
					'type'     => 'number',
					'decimals' => 2,
				),
				'2.349'
			)->value,
			'decimals'
		);
	}
);

bwc_test(
	'length, hour, time: patterns and ranges',
	static function (): void {
		bwc_same(
			'1.5rem',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'length',
				),
				' 1.5REM '
			)->value,
			'unit lowercased'
		);
		bwc_same(
			'0',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'length',
				),
				'0'
			)->value,
			'zero allowed'
		);
		bwc_same(
			'Use a number and one of these units: px or %.',
			bwc_sanitize(
				array(
					'id'    => 'a',
					'type'  => 'length',
					'units' => array( 'px', '%' ),
				),
				'12em'
			)->message,
			'unit not allowed'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'length',
				),
				'-4px'
			)->saved(),
			'negative only when allowed'
		);
		bwc_same(
			'-4px',
			bwc_sanitize(
				array(
					'id'             => 'a',
					'type'           => 'length',
					'allow_negative' => true,
				),
				'-4px'
			)->value,
			'negative allowed'
		);
		bwc_same(
			17,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'hour',
				),
				'17'
			)->value,
			'hour'
		);
		bwc_same(
			23,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'hour',
				),
				'24'
			)->value,
			'hour 24 becomes 23'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'hour',
				),
				'noon',
				8
			)->saved(),
			'an hour that isn\'t a number is rejected'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'      => 'a',
					'type'    => 'number',
					'default' => 3,
					'isValid' => array( 'required' => true ),
				),
				''
			)->saved(),
			'a required number can\'t be left empty'
		);
		bwc_same(
			'09:30',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'time',
				),
				'09:30:00'
			)->value,
			'time, seconds dropped'
		);
		bwc_same(
			'Use a time like 09:30.',
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'time',
				),
				'25:00'
			)->message,
			'bad time'
		);
	}
);

bwc_test(
	'checkbox and switch: posted is on, marker only is off',
	static function (): void {
		bwc_same(
			true,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'checkbox',
				),
				'1'
			)->value,
			'ticked'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'checkbox',
				),
				null,
				true
			)->value,
			'unticked (marker only)'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'switch',
				),
				null,
				true
			)->value,
			'switch off'
		);
		bwc_same(
			false,
			bwc_sanitize(
				array(
					'id'   => 'a',
					'type' => 'checkbox',
				),
				false,
				true
			)->value,
			'false from the REST API is off'
		);
	}
);

bwc_test(
	'choices: checkboxes, radio, select, multiple select, roles keep offered values only',
	static function (): void {
		$elements = array(
			array(
				'value' => 'post',
				'label' => 'Posts',
			),
			array(
				'value' => 'page',
				'label' => 'Pages',
			),
			array(
				'value' => 'offers',
				'label' => 'Offers',
			),
		);
		bwc_same(
			array( 'post', 'offers' ),
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'checkboxes',
					'elements' => $elements,
				),
				array( 'offers', 'post', 'evil' )
			)->value,
			'checkboxes: offered, in offered order'
		);
		bwc_same(
			array( 'page' ),
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'checkboxes',
					'elements' => $elements,
					'store'    => 'unchecked',
				),
				array( 'post', 'offers' )
			)->value,
			'store unchecked saves what wasn\'t ticked'
		);
		bwc_same(
			array(),
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'checkboxes',
					'elements' => $elements,
				),
				null
			)->value,
			'none ticked'
		);
		bwc_same(
			'page',
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'radio',
					'elements' => $elements,
				),
				'page'
			)->value,
			'radio'
		);
		bwc_same(
			'That choice isn\'t available. Pick one from the list.',
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'radio',
					'elements' => $elements,
				),
				'evil'
			)->message,
			'radio rejects others'
		);
		bwc_same(
			'b',
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'select',
					'elements' => array(
						'a' => 'A',
						'b' => 'B',
					),
				),
				'b'
			)->value,
			'select, from a plain map'
		);
		bwc_same(
			array( 'b' ),
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'select',
					'multiple' => true,
					'elements' => array(
						'a' => 'A',
						'b' => 'B',
					),
				),
				array( 'b', 'z' )
			)->value,
			'multiple select'
		);
		bwc_same(
			array( 'impact' ),
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'select',
					'multiple' => true,
					'elements' => static fn(): array => array( 'impact' => 'impact.com' ),
				),
				array( 'impact' )
			)->value,
			'elements from a callable'
		);
		bwc_same(
			array( 'editor' ),
			bwc_sanitize(
				array(
					'id'       => 'a',
					'type'     => 'role',
					'multiple' => true,
					'exclude'  => array( 'administrator' ),
				),
				array( 'editor', 'administrator', 'nobody' )
			)->value,
			'roles: real and not excluded'
		);
	}
);

bwc_test(
	'pair: each half by its own type; a bad half keeps its value and is named',
	static function (): void {
		$field = array(
			'id'     => 'range',
			'type'   => 'pair',
			'fields' => array(
				array(
					'id'      => 'low',
					'label'   => 'Lowest',
					'type'    => 'number',
					'isValid' => array( 'min' => 0 ),
				),
				array(
					'id'    => 'email',
					'label' => 'Email',
					'type'  => 'email',
				),
			),
		);
		bwc_same(
			array(
				'low'   => 5,
				'email' => 'a@example.com',
			),
			bwc_sanitize(
				$field,
				array(
					'low'   => '5',
					'email' => 'a@example.com',
				)
			)->value,
			'both halves'
		);
		$half = bwc_sanitize(
			$field,
			array(
				'low'   => '7',
				'email' => 'nope',
			),
			array(
				'low'   => 1,
				'email' => 'old@example.com',
			)
		);
		bwc_same( array( 7, 'old@example.com' ), array( $half->value['low'], $half->value['email'] ), 'good half saved, bad half kept' );
		bwc_same( array( true, 'Email: That isn\'t an email address.' ), array( $half->error, $half->message ), 'named by its label' );
	}
);

bwc_test(
	'validator: required, lengths, pattern, custom',
	static function (): void {
		$kit = bwc_test_kit();
		$f   = static fn( array $valid, string $type = 'text' ): array => $kit->fields()->normalize(
			array(
				'id'      => 'a',
				'label'   => 'A',
				'type'    => $type,
				'isValid' => $valid,
			)
		);
		bwc_same( 'This can\'t be left empty.', Validator::check( $f( array( 'required' => true ) ), '', array() ), 'required' );
		bwc_same( 'This needs to be ticked.', Validator::check( $f( array( 'required' => true ), 'checkbox' ), false, array() ), 'required box' );
		bwc_same( 'Use at least 3 characters.', Validator::check( $f( array( 'minLength' => 3 ) ), 'ab', array() ), 'min length' );
		bwc_same( 'Use 1 character or fewer.', Validator::check( $f( array( 'maxLength' => 1 ) ), 'ab', array() ), 'max length, singular' );
		bwc_same( 'That isn\'t in the format this needs.', Validator::check( $f( array( 'pattern' => '[A-Z0-9]+' ) ), 'abc', array() ), 'pattern is anchored' );
		bwc_same( null, Validator::check( $f( array( 'pattern' => '[A-Z0-9]+' ) ), 'ABC1', array() ), 'pattern passes' );
		bwc_same( 'Not on Sundays.', Validator::check( $f( array( 'custom' => static fn( $value ) => 'sun' === $value ? new WP_Error( 'x', 'Not on Sundays.' ) : true ) ), 'sun', array() ), 'custom' );
		bwc_same( null, Validator::check( $f( array( 'minLength' => 3 ) ), '', array() ), 'empty and not required passes' );
	}
);

bwc_test(
	'form: markers, read-only, rejected values kept, dependent fields',
	static function (): void {
		bwc_test_kit();
		$fields  = array(
			array(
				'id'    => 'name',
				'label' => 'Name',
				'type'  => 'text',
			),
			array(
				'id'    => 'on',
				'label' => 'On',
				'type'  => 'checkbox',
			),
			array(
				'id'        => 'days',
				'label'     => 'Days',
				'type'      => 'number',
				'default'   => 30,
				'isValid'   => array( 'max' => 90 ),
				'dependsOn' => 'on',
			),
			array(
				'id'       => 'locked',
				'label'    => 'Locked',
				'type'     => 'text',
				'readOnly' => 'Set in wp-config.php',
			),
			array(
				'id'    => 'mail',
				'label' => 'Mail',
				'type'  => 'email',
			),
			array(
				'id'    => 'other',
				'label' => 'Not on this page',
				'type'  => 'checkbox',
			),
		);
		$current = array(
			'name'   => 'Old',
			'on'     => true,
			'days'   => 30,
			'locked' => 'keep',
			'mail'   => 'old@example.com',
			'other'  => true,
		);
		$posted  = array(
			'set'    => array(
				'name'   => 'New',
				'days'   => '500',
				'locked' => 'hacked',
				'mail'   => 'bad',
			),
			'_shown' => array( 'set' => array( 'on' => '1' ) ),
		);
		$result  = Form::sanitize( $fields, 'set', $posted, $current );
		bwc_same(
			array(
				'name' => 'New',
				'on'   => false,
				'days' => 90,
			),
			$result['values'],
			'saved: text, the unticked box (marker), the clamped number; not the read-only, the bad email or the field not on the page'
		);
		bwc_same( array( 'mail' => 'That isn\'t an email address.' ), $result['errors'], 'the bad email is reported' );
		bwc_same( array(), $result['notes'], 'the clamp note is dropped: days became inactive when the box was unticked' );

		$on = Form::sanitize(
			$fields,
			'set',
			array(
				'set' => array(
					'on'   => '1',
					'days' => '500',
				),
			),
			$current
		);
		bwc_same( array( 'days' => 'Set to 90, the most allowed.' ), $on['notes'], 'with the box ticked, the clamp is reported' );

		bwc_same(
			false,
			Form::active(
				array(
					'dependsOn' => array(
						'field' => 'mode',
						'is'    => array( 'votes' ),
					),
				),
				array( 'mode' => 'date' )
			),
			'is'
		);
		bwc_same(
			true,
			Form::active(
				array(
					'dependsOn' => array(
						'field' => 'mode',
						'not'   => array( 'off' ),
					),
				),
				array( 'mode' => 'date' )
			),
			'not'
		);
	}
);

bwc_test(
	'form markup: names, ids, described-by, inert dependent fields, markers, errors',
	static function (): void {
		$kit     = bwc_test_kit();
		$storage = $kit->settings(
			array(
				'option' => 'bwc_test_settings',
				'sets'   => array(),
			)
		);
		$html    = Form::markup(
			array(
				'storage' => $storage,
				'set'     => 'net',
				'fields'  => array(
					array(
						'id'             => 'daily',
						'label'          => 'Daily fetch',
						'type'           => 'checkbox',
						'checkbox_label' => 'Once a day',
					),
					array(
						'id'          => 'hour',
						'label'       => 'Fetch at',
						'type'        => 'number',
						'unit'        => 'h',
						'description' => 'Pick an hour.',
						'dependsOn'   => 'daily',
					),
				),
				'errors'  => array( 'hour' => 'Too late.' ),
			)
		);
		bwc_same( true, str_contains( $html, '<input type="checkbox" value="1" id="bwc-test-net-daily" name="bwc_test_settings[net][daily]">' ), 'checkbox name and id' );
		bwc_same( true, str_contains( $html, '<input type="hidden" name="bwc_test_settings[_shown][net][daily]" value="1">' ), 'its marker' );
		bwc_same( true, str_contains( $html, '<div class="bwc-field is-inactive" data-bwc-field="net.hour" inert data-bwc-depends="net.daily">' ), 'the dependent field is inert while the box is off' );
		bwc_same( true, str_contains( $html, 'aria-describedby="bwc-test-net-hour-unit bwc-test-net-hour-help"' ), 'unit and help describe the input' );
		bwc_same( false, str_contains( $html, 'Too late.' ), 'an inactive field shows no error' );
		bwc_same( '', Form::summary( array(), array(), 'x' ), 'no summary without errors' );
		bwc_same( true, str_contains( Form::summary( array( 'a' => 'm' ), array( 'a' => 'Days' ), 'bwc-test-net' ), '<a href="#bwc-test-net-a">Days</a>' ), 'the summary links each field by label' );
	}
);

bwc_test(
	'option storage: defaults on read, merge on write, sanitize once, REST, migrations',
	static function (): void {
		$kit = bwc_test_kit();
		delete_option( 'bwc_test_settings' );
		delete_option( 'bwc_test_settings_schema' );
		$sets    = array(
			'a' => array(
				array(
					'id'      => 'n',
					'label'   => 'N',
					'type'    => 'number',
					'default' => 5,
				),
				array(
					'id'          => 'label',
					'label'       => 'Label',
					'type'        => 'text',
					'default'     => 'Get Code',
					'placeholder' => true,
				),
			),
			'b' => array(
				array(
					'id'    => 'on',
					'label' => 'On',
					'type'  => 'checkbox',
				),
			),
		);
		$storage = $kit->settings(
			array(
				'option'     => 'bwc_test_settings',
				'sets'       => $sets,
				'migrations' => array(
					1 => static fn( array $stored ): array => array_merge( $stored, array( 'b' => array( 'on' => true ) ) ),
				),
			)
		);

		bwc_same(
			array(
				'n'     => 5,
				'label' => 'Get Code',
			),
			$storage->values( 'a', $sets['a'] ),
			'defaults when nothing is stored'
		);
		$storage->write( 'a', array( 'n' => 9 ) );
		bwc_same( array( 'a' => array( 'n' => 9 ) ), get_option( 'bwc_test_settings' ), 'defaults are never written' );
		$storage->write( 'b', array( 'on' => true ) );
		bwc_same(
			array(
				'a' => array( 'n' => 9 ),
				'b' => array( 'on' => true ),
			),
			get_option( 'bwc_test_settings' ),
			'buckets merge'
		);

		$first  = $storage->sanitize(
			array(
				'a' => array(
					'n'     => '12',
					'label' => '',
				),
			)
		);
		$second = $storage->sanitize( $first );
		bwc_same( array( 12, '' ), array( $first['a']['n'], $first['a']['label'] ), 'REST-style input: the keys sent count as shown' );
		bwc_same( $first, $second, 'sanitizing the clean value again (add_option() after update_option()) returns it unchanged' );
		update_option( 'bwc_test_settings', $first );
		bwc_same( 'Get Code', $storage->get( 'a', 'label' ), 'empty with placeholder default reads as the default' );

		update_option( 'bwc_test_settings', array( 'a' => array( 'n' => 1 ) ) );
		$storage->migrate();
		bwc_same( array( true, 1 ), array( get_option( 'bwc_test_settings' )['b']['on'] ?? null, (int) get_option( 'bwc_test_settings_schema' ) ), 'migration ran once and was recorded' );

		$schema = get_registered_settings()['bwc_test_settings']['show_in_rest']['schema'] ?? array();
		bwc_same(
			array(
				'type'        => 'integer',
				'description' => 'N',
				'default'     => 5,
			),
			$schema['properties']['a']['properties']['n'] ?? null,
			'REST schema from the fields'
		);

		delete_option( 'bwc_test_settings' );
		delete_option( 'bwc_test_settings_schema' );
		unregister_setting( 'bwc_test_settings', 'bwc_test_settings' );
	}
);

bwc_test(
	'option storage: each write in one request is sanitized on its own',
	static function (): void {
		$kit = bwc_test_kit();
		delete_option( 'bwc_test_twice' );
		$kit->settings(
			array(
				'option' => 'bwc_test_twice',
				'sets'   => array(
					'a' => array(
						array(
							'id'      => 'n',
							'label'   => 'N',
							'type'    => 'number',
							'default' => 5,
							'isValid' => array( 'max' => 50 ),
						),
					),
				),
			)
		);

		update_option( 'bwc_test_twice', array( 'a' => array( 'n' => '7' ) ) );
		bwc_same( 7, get_option( 'bwc_test_twice' )['a']['n'] ?? null, 'the first write, added as a new option' );

		update_option( 'bwc_test_twice', array( 'a' => array( 'n' => '900' ) ) );
		bwc_same( 50, get_option( 'bwc_test_twice' )['a']['n'] ?? null, 'a second write isn\'t answered with the first one\'s result' );

		delete_option( 'bwc_test_twice' );
		unregister_setting( 'bwc_test_twice', 'bwc_test_twice' );
	}
);

bwc_test(
	'option storage over REST: keys the request leaves out keep what is stored',
	static function (): void {
		$kit = bwc_test_kit();
		delete_option( 'bwc_test_rest' );
		$sets    = array(
			'a' => array(
				array(
					'id'    => 'on',
					'label' => 'On',
					'type'  => 'checkbox',
				),
				array(
					'id'    => 'tags',
					'label' => 'Tags',
					'type'  => 'list',
				),
				array(
					'id'      => 'n',
					'label'   => 'N',
					'type'    => 'number',
					'default' => 5,
				),
			),
		);
		$storage = $kit->settings(
			array(
				'option' => 'bwc_test_rest',
				'sets'   => $sets,
			)
		);
		// Written directly, as the plugin's own code does.
		$storage->write(
			'a',
			array(
				'on'   => true,
				'tags' => array( 'x' ),
			)
		);
		$clean = $storage->sanitize( array( 'a' => array( 'n' => '7' ) ) );
		bwc_same( array( true, array( 'x' ), 7 ), array( $clean['a']['on'] ?? null, $clean['a']['tags'] ?? null, $clean['a']['n'] ?? null ), 'only "n" changes; the box and the list stay' );

		delete_option( 'bwc_test_rest' );
		unregister_setting( 'bwc_test_rest', 'bwc_test_rest' );
	}
);

bwc_test(
	'post, term and user meta: one key per field, values cast back, REST writes sanitized',
	static function (): void {
		$kit    = bwc_test_kit();
		$fields = array(
			array(
				'id'    => 'on',
				'label' => 'On',
				'type'  => 'checkbox',
			),
			array(
				'id'      => 'votes',
				'label'   => 'Votes',
				'type'    => 'number',
				'default' => 3,
			),
			array(
				'id'    => 'tags',
				'label' => 'Tags',
				'type'  => 'list',
			),
		);

		$post_id = wp_insert_post(
			array(
				'post_title'  => 'bwc test',
				'post_status' => 'draft',
			)
		);
		$post    = new PostMeta( $kit, $post_id, $fields, 'bwc_test' );
		bwc_same(
			array(
				'on'    => false,
				'votes' => 3,
				'tags'  => array(),
			),
			$post->values( 'box', $fields ),
			'defaults before anything is saved'
		);
		$post->write(
			'box',
			array(
				'on'    => false,
				'votes' => 7,
				'tags'  => array( 'a', 'b' ),
			)
		);
		bwc_same( array( '', '7' ), array( get_post_meta( $post_id, 'bwc_test_on', true ), get_post_meta( $post_id, 'bwc_test_votes', true ) ), 'one key per field, as WordPress stores them' );
		bwc_same(
			array(
				'on'    => false,
				'votes' => 7,
				'tags'  => array( 'a', 'b' ),
			),
			$post->values( 'box', $fields ),
			'cast back to their types: a stored false stays false'
		);
		wp_delete_post( $post_id, true );

		$term = wp_insert_term( 'bwc test term ' . wp_rand(), 'category' );
		$tid  = (int) $term['term_id'];
		$tm   = new TermMeta( $kit, $tid, $fields, 'bwc_test' );
		$tm->write( 'set', array( 'on' => true ) );
		bwc_same( true, $tm->values( 'set', $fields )['on'], 'term meta' );
		wp_delete_term( $tid, 'category' );

		wp_set_current_user( 1 );
		$uid = 1;
		$um  = new UserMeta( $kit, $uid, $fields, 'bwc_test' );
		$um->write( 'set', array( 'votes' => 4 ) );
		bwc_same( 4, $um->values( 'set', $fields )['votes'], 'user meta' );
		delete_user_meta( $uid, 'bwc_test_votes' );

		bwc_same(
			true,
			UserMeta::allowed(
				array(
					'who'        => 'self',
					'capability' => '',
				),
				$uid
			),
			'self: your own profile'
		);
		bwc_same(
			false,
			UserMeta::allowed(
				array(
					'who'        => 'self',
					'capability' => '',
				),
				$uid + 1000
			),
			'self: not someone else\'s'
		);

		$kit->meta_box(
			array(
				'id'         => 'rest',
				'title'      => 'Test',
				'post_types' => array( 'post' ),
				'prefix'     => 'bwc_test',
				'fields'     => $fields,
			)
		);
		bwc_same( false, sanitize_meta( 'bwc_test_on', 'false', 'post', 'post' ), 'a REST write of "false" to a checkbox is off' );
		bwc_same( 3, sanitize_meta( 'bwc_test_votes', 'many', 'post', 'post' ), 'a REST write that isn\'t a number falls back to the default' );
		bwc_same( array( 'x', 'y' ), sanitize_meta( 'bwc_test_tags', "x\ny", 'post', 'post' ), 'a list written over REST is split into lines' );
		foreach ( array( 'on', 'votes', 'tags' ) as $id ) {
			unregister_meta_key( 'post', 'bwc_test_' . $id, 'post' );
		}
	}
);
