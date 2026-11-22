<?php
/**
 * The box every field sits in: label, control, unit, help, error.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Component\Html;

defined( 'ABSPATH' ) || exit;

/**
 * Field wrapper. Types print only their control;
 * the form calls this. Stack layout prints a div (a fieldset for groups);
 * table layout prints a form-table row, as term edit forms and profiles expect.
 */
final class Wrapper {

	/**
	 * The wrapped field.
	 *
	 * @param Context              $context Field context (its describedby already lists the lines below).
	 * @param string               $kind    The type's kind: input, group or check.
	 * @param array<string, mixed> $state   control, help (list), error, note, inactive, depends (attributes), marker, layout, path, classes.
	 */
	public static function markup( Context $context, string $kind, array $state ): string {
		$field   = $context->field;
		$id      = $context->id;
		$label   = esc_html( (string) $field['label'] );
		$table   = 'table' === ( $state['layout'] ?? 'stack' );
		$classes = Html::classes(
			'bwc-field',
			array(
				'wide'     => ! empty( $field['wide'] ),
				'advanced' => ! empty( $field['advanced'] ),
			),
			trim(
				implode(
					' ',
					array_filter(
						array(
							$table ? 'form-field bwc-field-row' : '',
							empty( $state['inactive'] ) ? '' : 'is-inactive',
							'' === (string) ( $state['error'] ?? '' ) ? '' : 'is-invalid',
							$context->read_only ? 'is-readonly' : '',
							(string) ( $state['classes'] ?? '' ),
						)
					)
				)
			)
		);

		$attributes = array_merge(
			array(
				'class'          => $classes,
				'data-bwc-field' => (string) ( $state['path'] ?? $field['id'] ),
				'inert'          => ! empty( $state['inactive'] ),
			),
			(array) ( $state['depends'] ?? array() )
		);

		$unit  = '' === (string) $field['unit'] ? '' : ' <span class="bwc-field__unit" id="' . esc_attr( $id . '-unit' ) . '">' . esc_html( (string) $field['unit'] ) . '</span>';
		$body  = '<div class="bwc-field__control">' . $state['control'] . $unit . '</div>';
		$help  = array_values( array_filter( (array) ( $state['help'] ?? array() ), static fn( $line ): bool => '' !== (string) $line ) );
		$body .= $help ? '<p class="bwc-field__help" id="' . esc_attr( $id . '-help' ) . '">' . implode( '', array_map( static fn( $line ): string => '<span class="bwc-field__help-line">' . esc_html( (string) $line ) . '</span>', $help ) ) . '</p>' : '';
		$body .= '' === (string) ( $state['error'] ?? '' ) ? '' : '<p class="bwc-field__error" id="' . esc_attr( $id . '-error' ) . '">' . esc_html( (string) $state['error'] ) . '</p>';
		$body .= '' === (string) ( $state['note'] ?? '' ) ? '' : '<p class="bwc-field__note" id="' . esc_attr( $id . '-note' ) . '">' . esc_html( (string) $state['note'] ) . '</p>';
		$body .= (string) ( $state['marker'] ?? '' );

		// A group's own help, error and note describe the whole fieldset (inputs describe themselves).
		$about = array();
		foreach ( array(
			'help'  => (bool) $help,
			'error' => '' !== (string) ( $state['error'] ?? '' ),
			'note'  => '' !== (string) ( $state['note'] ?? '' ),
		) as $part => $has ) {
			if ( $has ) {
				$about[] = $id . '-' . $part;
			}
		}
		$about = $about ? ' aria-describedby="' . esc_attr( implode( ' ', $about ) ) . '"' : '';

		if ( $table ) {
			$head = 'input' === $kind ? '<label for="' . esc_attr( $id ) . '">' . $label . '</label>' : '<span id="' . esc_attr( $id . '-label' ) . '">' . $label . '</span>';
			if ( 'group' === $kind ) {
				$body = '<fieldset class="bwc-field__group" aria-labelledby="' . esc_attr( $id . '-label' ) . '"' . $about . '>' . $body . '</fieldset>';
			}

			return '<tr' . Html::attributes( $attributes ) . '><th scope="row">' . $head . '</th><td>' . $body . '</td></tr>';
		}

		if ( 'group' === $kind ) {
			return '<fieldset' . Html::attributes( $attributes ) . $about . '><legend class="bwc-field__label">' . $label . '</legend>' . $body . '</fieldset>';
		}

		$head = 'input' === $kind ? '<label class="bwc-field__label" for="' . esc_attr( $id ) . '">' . $label . '</label>' : '';

		return '<div' . Html::attributes( $attributes ) . '>' . $head . $body . '</div>';
	}
}
