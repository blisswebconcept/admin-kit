<?php
/**
 * Free multi-line text.
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
 * Field type "textarea". One item per line is "list".
 */
class Textarea extends BaseType {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'textarea';
	}

	/**
	 * Extra keys.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default' => '',
			'rows'    => 4,
		);
	}

	/**
	 * The textarea.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field = $context->field;
		$valid = (array) $field['isValid'];

		return '<textarea' . Html::attributes(
			array_merge(
				array(
					'class' => Html::classes(
						'bwc-control',
						array(
							'textarea',
							'full',
							'mono' => ! empty( $field['monospace'] ),
						)
					),
					'rows'  => (int) $field['rows'],
				),
				$context->attributes(),
				array(
					'placeholder' => '' === (string) $field['placeholder'] || true === $field['placeholder'] ? null : (string) $field['placeholder'],
					'minlength'   => $valid['minLength'] ?? null,
					'maxlength'   => $valid['maxLength'] ?? null,
				)
			)
		) . '>' . esc_textarea( (string) $context->value ) . '</textarea>';
	}

	/**
	 * Plain text, line breaks kept.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		return Result::ok( sanitize_textarea_field( is_scalar( $raw ) ? (string) $raw : '' ) );
	}

	/**
	 * A string.
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
			),
			static fn( $value ): bool => null !== $value
		);
	}
}
