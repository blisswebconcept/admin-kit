<?php
/**
 * One line of text.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "text". With 'placeholder' => true the
 * default shows as the placeholder and an empty value means "use the default".
 */
class Text extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'text';
	}

	/**
	 * Extra keys: prefix (text shown before the value inside the box, such as "example.com/").
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default' => '',
			'prefix'  => '',
		);
	}

	/**
	 * The input's type attribute.
	 */
	protected function input_type(): string {
		return 'text';
	}

	/**
	 * The input.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field  = $context->field;
		$valid  = (array) $field['isValid'];
		$prefix = (string) ( $field['prefix'] ?? '' );
		$attrs  = $context->attributes();

		// Text shown before the value, inside the box ("example.com/"), read as the input's description.
		if ( '' !== $prefix ) {
			$attrs['aria-describedby'] = trim( $context->id . '-prefix ' . (string) ( $attrs['aria-describedby'] ?? '' ) );
		}

		$input = '<input' . Html::attributes(
			array_merge(
				array(
					'type'  => $this->input_type(),
					'class' => '' === $prefix ? Html::classes(
						'bwc-control',
						array(
							'mono' => ! empty( $field['monospace'] ),
							'full' => true,
						)
					) : 'bwc-prefixed__input',
					'value' => (string) $context->value,
				),
				$attrs,
				array(
					'placeholder' => self::placeholder( $field ),
					'minlength'   => $valid['minLength'] ?? null,
					'maxlength'   => $valid['maxLength'] ?? null,
					'pattern'     => $valid['pattern'] ?? null,
				)
			)
		) . '>';

		if ( '' === $prefix ) {
			return $input;
		}

		return '<div class="bwc-prefixed"><span class="bwc-prefixed__prefix" id="' . esc_attr( $context->id . '-prefix' ) . '" aria-hidden="true">' . esc_html( $prefix ) . '</span>'
			. $input . '</div>';
	}

	/**
	 * The placeholder: its own text, or the default when placeholder is true.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	protected static function placeholder( array $field ): ?string {
		if ( true === $field['placeholder'] ) {
			return '' === (string) $field['default'] ? null : (string) $field['default'];
		}

		return '' === (string) $field['placeholder'] ? null : (string) $field['placeholder'];
	}

	/**
	 * Plain text, tags and line breaks removed.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		return Result::ok( sanitize_text_field( is_scalar( $raw ) ? (string) $raw : '' ) );
	}

	/**
	 * A string, with its length and pattern limits.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		$valid = (array) $field['isValid'];

		return array_filter(
			array(
				'type'      => 'string',
				'minLength' => $valid['minLength'] ?? null,
				'maxLength' => $valid['maxLength'] ?? null,
				'pattern'   => isset( $valid['pattern'] ) ? '^(?:' . $valid['pattern'] . ')$' : null,
			),
			static fn( $value ): bool => null !== $value
		);
	}
}
