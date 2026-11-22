<?php
/**
 * An API key, token or password: stored encrypted, never shown back.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Service\Secrets;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "secret". The value goes to the Secrets
 * service; the field's own storage keeps only whether one is set (true or
 * false). Empty input keeps the saved secret; "Remove" deletes it; a
 * wp-config.php constant replaces the input with a line saying so.
 * Never in REST schemas, messages or the page. Settings only: a meta box,
 * term or profile form, drawer or repeater leaves it out (Form::without_secrets()).
 */
class Secret extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'secret';
	}

	/**
	 * Extra keys: secret (the Secrets name; the field id by default),
	 * constant (wp-config.php constant that wins), mask (callable).
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'  => false,
			'secret'   => '',
			'constant' => '',
			'mask'     => null,
		);
	}

	/**
	 * The Secrets name, with the secret described for the service.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public static function secret_name( array $field ): string {
		$name = sanitize_key( '' !== (string) ( $field['secret'] ?? '' ) ? (string) $field['secret'] : (string) ( $field['id'] ?? '' ) );

		Kit::instance()->secrets()->define(
			$name,
			array(
				'constant' => (string) ( $field['constant'] ?? '' ),
				'mask'     => $field['mask'] ?? null,
			)
		);

		return $name;
	}

	/**
	 * An input, unless wp-config.php sets the key: then there's only a line
	 * of text, so the field is a group (a legend, not a label pointing at text).
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'config' === Kit::instance()->secrets()->source( self::secret_name( $field ) ) ? 'group' : 'input';
	}

	/**
	 * A password input, or a line when the constant is set.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		// One key for the whole site: never on a post, term, user or record form,
		// where it would show one record's key on another (Form::without_secrets()).
		if ( 'settings' !== $context->storage ) {
			return '';
		}

		$secrets = $context->kit->secrets();
		$name    = self::secret_name( $context->field );
		$source  = $secrets->source( $name );

		if ( 'config' === $source ) {
			return '<p class="bwc-secret__config" id="' . esc_attr( $context->id ) . '">' . esc_html( self::say( 'secret_in_config', $secrets->constant( $name ) ) ) . '</p>';
		}

		$input = '<input' . Html::attributes(
			array_merge(
				array(
					'class'        => 'bwc-control bwc-control--mono',
					'type'         => 'password',
					'autocomplete' => 'new-password',
					'spellcheck'   => 'false',
					'value'        => '',
					'placeholder'  => 'saved' === $source ? $secrets->masked( $name ) : null,
					'disabled'     => ! Secrets::available() || $context->read_only,
				),
				$context->attributes()
			)
		) . '>';

		if ( 'saved' === $source || 'unreadable' === $source ) {
			$remove = $context->id . '-remove';
			$input .= '<label class="bwc-secret__remove" for="' . esc_attr( $remove ) . '"><input type="checkbox" id="' . esc_attr( $remove ) . '" name="' . esc_attr( self::remove_name( $context->name ) ) . '" value="1"> '
				. esc_html( self::say( 'secret_remove' ) ) . '</label>';
		}

		return '<div class="bwc-secret">' . $input . '</div>';
	}

	/**
	 * Help for the state the secret is in.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function help( array $field ): string {
		if ( ! Secrets::available() ) {
			$constant = (string) ( $field['constant'] ?? '' );
			return '' === $constant ? self::say( 'secret_no_sodium' ) : self::say( 'secret_no_sodium_constant', $constant );
		}

		return match ( Kit::instance()->secrets()->source( self::secret_name( $field ) ) ) {
			'saved'      => self::say( 'secret_keep' ),
			'unreadable' => self::say( 'secret_unreadable' ),
			default      => '',
		};
	}

	/**
	 * Keeps, removes or saves the secret; returns whether one is set.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored flag.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values (the Remove box).
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$secrets = Kit::instance()->secrets();
		$name    = self::secret_name( $field );
		$value   = is_scalar( $raw ) ? trim( (string) $raw ) : '';

		if ( 'config' === $secrets->source( $name ) ) {
			return Result::ok( true );
		}

		if ( '' === $value ) {
			if ( ! empty( $values[ (string) $field['id'] . '__remove' ] ) ) {
				$secrets->delete( $name );
				return Result::ok( false );
			}

			return Result::ok( 'saved' === $secrets->source( $name ) );
		}

		if ( ! $secrets->set( $name, $value ) ) {
			return Result::rejected( (bool) $stored, self::say( 'secret_no_sodium' ) );
		}

		return Result::ok( true );
	}

	/**
	 * Never in a REST schema.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function schema( array $field ): ?array {
		return null;
	}

	/**
	 * Name of the Remove box: the field's name with "__remove" on its key.
	 *
	 * @param string $name The field's input name.
	 */
	private static function remove_name( string $name ): string {
		return (string) preg_replace( '/\]$/', '__remove]', $name );
	}
}
