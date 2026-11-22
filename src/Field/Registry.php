<?php
/**
 * The field types this plugin's kit knows.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Field type registry. Names are per kit, so two
 * plugins can each have their own "brand" type.
 */
final class Registry {

	/**
	 * Keys every field description has.
	 */
	public const COMMON = array(
		'description' => '',
		'placeholder' => '',
		'elements'    => null,
		'isValid'     => array(),
		'dependsOn'   => null,
		'advanced'    => false,
		'unit'        => '',
		'readOnly'    => false,
		'wide'        => false,
		'monospace'   => false,
		'inline_of'   => '',
	);

	/**
	 * Registered types, by name.
	 *
	 * @var array<string, Type>
	 */
	private array $types = array();

	/**
	 * Constructor: the built-in types.
	 */
	public function __construct() {
		foreach (
			array(
				new Type\Text(),
				new Type\Textarea(),
				new Type\Lines(),
				new Type\Number(),
				new Type\Length(),
				new Type\Email(),
				new Type\Url(),
				new Type\Checkbox(),
				new Type\OnOff(),
				new Type\Checkboxes(),
				new Type\Radio(),
				new Type\Select(),
				new Type\Hour(),
				new Type\Time(),
				new Type\Role(),
				new Type\Pair(),
				new Type\Secret(),
				new Type\PostPicker(),
				new Type\UserPicker(),
				new Type\TermPicker(),
				new Type\Ordered(),
				new Type\Repeater(),
			) as $type
		) {
			$this->register( $type );
		}
	}

	/**
	 * Picker fields described in this request, by token, for the search request.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $pickers = array();

	/**
	 * A picker field by its token, if one was described in this request.
	 *
	 * @param string $token Token.
	 * @return array<string, mixed>|null
	 */
	public function picker( string $token ): ?array {
		return $this->pickers[ $token ] ?? null;
	}

	/**
	 * Who may search each picker, by token: the tests of the forms it sits in.
	 *
	 * @var array<string, list<callable(): bool>>
	 */
	private array $searchers = array();

	/**
	 * Lets the users who pass a test search the pickers among these fields
	 * (repeater rows and a radio option's own field included). A storage
	 * calls it with its own test, so a picker on a settings screen can only
	 * be searched by those who may open that screen.
	 *
	 * @param list<array<string, mixed>> $fields Field descriptions.
	 * @param callable(): bool           $can    Whether the current user may use the form.
	 */
	public function allow_search( array $fields, callable $can ): void {
		foreach ( Form::normalize( $fields ) as $field ) {
			$type = $this->get( (string) $field['type'] );
			if ( $type instanceof Type\Picker ) {
				$this->searchers[ $type->token( $field ) ][] = $can;
			} elseif ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$this->allow_search( $field['fields'], $can );
			}
		}
	}

	/**
	 * Whether the current user may search a picker: they pass the test of
	 * one form it sits in, or the kit's capability (context "search") when
	 * no storage claimed it.
	 *
	 * @param string $token Token.
	 */
	public function may_search( string $token ): bool {
		$tests = $this->searchers[ $token ] ?? array();
		if ( ! $tests ) {
			return Kit::instance()->can( 'search' );
		}

		foreach ( $tests as $can ) {
			if ( $can() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Adds a type, or replaces one with the same name.
	 *
	 * @param Type $type The type.
	 */
	public function register( Type $type ): void {
		$this->types[ $type->name() ] = $type;
	}

	/**
	 * A type by name.
	 *
	 * @param string $name Type name.
	 */
	public function get( string $name ): ?Type {
		return $this->types[ $name ] ?? null;
	}

	/**
	 * Every registered type name.
	 *
	 * @return list<string>
	 */
	public function names(): array {
		return array_keys( $this->types );
	}

	/**
	 * A field description with every key filled in: common keys, then the
	 * type's own, then the field's. An empty array when the description can't
	 * be used (a message is raised: an exception while debugging).
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 * @throws \InvalidArgumentException While debugging, for a field that can't be used.
	 */
	public function normalize( array $field ): array {
		$id   = isset( $field['id'] ) ? (string) $field['id'] : '';
		$type = isset( $field['type'] ) ? $this->get( (string) $field['type'] ) : null;

		$problem = '';
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $id ) ) {
			$problem = "the id \"{$id}\" must be lowercase letters, digits and underscores";
		} elseif ( empty( $field['label'] ) ) {
			$problem = "\"{$id}\" needs a label";
		} elseif ( null === $type ) {
			$problem = "\"{$id}\" has an unknown type \"" . (string) ( $field['type'] ?? '' ) . '"';
		}

		if ( '' !== $problem ) {
			$message = 'Admin Kit field: ' . $problem . '.';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				throw new \InvalidArgumentException( esc_html( $message ) );
			}
			_doing_it_wrong( 'Admin Kit field', esc_html( $message ), esc_html( Kit::VERSION ) );
			return array();
		}

		$normal = array_merge( self::COMMON, $type->defaults(), $field );

		// Remembered so the search request can find it (and only it) by token.
		if ( $type instanceof Type\Picker ) {
			$this->pickers[ $type->token( $normal ) ] = $normal;
		}

		return $normal;
	}
}
