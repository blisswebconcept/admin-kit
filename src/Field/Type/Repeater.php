<?php
/**
 * Rows of a few simple fields each, added, removed and reordered.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Icon;
use BlissWebConcept\AdminKit\Field\BaseType;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;
use BlissWebConcept\AdminKit\Field\Validator;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "repeater". Each row is a few simple
 * sub-fields plus a stable `_row` id. Without scripts the server prints the
 * saved rows and three empty ones, each with a Remove box; empty rows are
 * dropped on save. The repeater script adds rows from a template, removes
 * them, and moves them up and down with buttons. Rows are saved in the order
 * the browser sends them, so moving a row needs no renumbering.
 */
class Repeater extends BaseType {

	/**
	 * Types a sub-field may be.
	 */
	private const SIMPLE = array( 'text', 'number', 'length', 'email', 'url', 'select', 'checkbox', 'switch' );

	/**
	 * Empty rows printed for browsers without the script.
	 */
	private const BLANK_ROWS = 3;

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'repeater';
	}

	/**
	 * Extra keys: fields, max_rows, row_label ("Rule %d", or a sub-field id), add_label.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'   => array(),
			'fields'    => array(),
			'max_rows'  => 0,
			'row_label' => '',
			'add_label' => '',
		);
	}

	/**
	 * A fieldset of rows.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function kind( array $field ): string {
		return 'group';
	}

	/**
	 * The sub-fields, normalised; types that don't fit a row are left out.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return list<array<string, mixed>>
	 */
	public static function columns( array $field ): array {
		$columns = array();
		foreach ( (array) $field['fields'] as $sub ) {
			if ( is_array( $sub ) && in_array( $sub['type'] ?? 'text', self::SIMPLE, true ) ) {
				$normal = Kit::instance()->fields()->normalize( $sub );
				if ( $normal ) {
					$columns[] = $normal;
				}
			}
		}

		return $columns;
	}

	/**
	 * The rows, the template for new ones, and Add.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field   = $context->field;
		$rows    = array_values( array_filter( (array) $context->value, 'is_array' ) );
		$columns = self::columns( $field );
		$html    = '';

		foreach ( $rows as $i => $row ) {
			$html .= $this->row( $context, $columns, (string) $i, $row, $i + 1, false );
		}
		for ( $blank = 0; $blank < self::BLANK_ROWS; $blank++ ) {
			$index = count( $rows ) + $blank;
			$html .= $this->row( $context, $columns, (string) $index, array(), $index + 1, true );
		}

		$template = $this->row( $context, $columns, '__i__', array(), 0, true );
		$add      = '' !== (string) $field['add_label'] ? (string) $field['add_label'] : self::say( 'repeater_add' );

		return '<div' . Html::attributes(
			array(
				'class'                   => 'bwc-repeater',
				'data-bwc-repeater'       => true,
				'data-bwc-repeater-max'   => (string) (int) $field['max_rows'],
				'data-bwc-repeater-label' => self::label_pattern( $field ),
			)
		) . '><ol class="bwc-repeater__rows" data-bwc-repeater-rows>' . $html . '</ol>'
			. '<template data-bwc-repeater-template>' . $template . '</template>'
			. '<button type="button" class="bwc-button bwc-button--outline bwc-button--small" data-bwc-repeater-add hidden>' . Icon::markup( array( 'name' => 'plus' ) ) . '<span class="bwc-button__label">' . esc_html( $add ) . '</span></button>'
			. '</div>';
	}

	/**
	 * One row.
	 *
	 * @param Context                    $context The repeater's context.
	 * @param list<array<string, mixed>> $columns Sub-fields.
	 * @param string                     $index   Row index in the names ("__i__" in the template).
	 * @param array<string, mixed>       $row     Its values.
	 * @param int                        $number  Its number, for the title (0 in the template).
	 * @param bool                       $blank   An empty row the script removes.
	 */
	private function row( Context $context, array $columns, string $index, array $row, int $number, bool $blank ): string {
		$registry = $context->kit->fields();
		$name     = $context->name . '[' . $index . ']';
		$id       = $context->id . '-' . $index;
		$title    = self::title( $context->field, $row, $number );
		$fields   = '';

		foreach ( $columns as $sub ) {
			$type = $registry->get( (string) $sub['type'] );
			if ( null === $type ) {
				continue;
			}
			$sid     = (string) $sub['id'];
			$sub_ctx = new Context( $context->kit, $sub, $row[ $sid ] ?? $sub['default'], $name . '[' . $sid . ']', $id . '-' . $sid, '', false, $context->read_only, $context->storage );
			$label   = 'check' === $type->kind( $sub ) ? '' : '<label class="bwc-repeater__label" for="' . esc_attr( $sub_ctx->id ) . '">' . esc_html( (string) $sub['label'] ) . '</label>';
			$fields .= '<div class="' . esc_attr( Html::classes( 'bwc-repeater__field', array( 'check' => 'check' === $type->kind( $sub ) ) ) ) . '">' . $label . $type->control( $sub_ctx ) . '</div>';
		}

		$button = static fn( string $hook, string $icon, string $label ): string => '<button type="button" class="bwc-button bwc-button--icon bwc-button--small" data-bwc-repeater-' . $hook . ' aria-label="' . esc_attr( $label ) . '">' . Icon::markup( array( 'name' => $icon ) ) . '</button>';

		return '<li class="bwc-repeater__row" data-bwc-repeater-row' . ( $blank ? ' data-bwc-repeater-blank' : '' ) . '>'
			. '<div class="bwc-repeater__head"><span class="bwc-repeater__title" data-bwc-repeater-title>' . esc_html( $title ) . '</span>'
			. '<span class="bwc-repeater__actions" data-bwc-repeater-actions hidden>'
			. $button( 'up', 'arrow-up', self::say( 'move_up' ) . ': ' . $title )
			. $button( 'down', 'arrow-down', self::say( 'move_down' ) . ': ' . $title )
			. $button( 'remove', 'trash', self::say( 'remove' ) . ': ' . $title )
			. '</span>'
			. '<label class="bwc-repeater__remove" data-bwc-repeater-remove-box><input type="checkbox" name="' . esc_attr( $name . '[_remove]' ) . '" value="1"> ' . esc_html( self::say( 'remove' ) ) . '<span class="bwc-visually-hidden">: ' . esc_html( $title ) . '</span></label>'
			. '</div>'
			. '<div class="bwc-repeater__fields">' . $fields . '</div>'
			. '<input type="hidden" name="' . esc_attr( $name . '[_row]' ) . '" value="' . esc_attr( sanitize_key( (string) ( $row['_row'] ?? '' ) ) ) . '">'
			. '</li>';
	}

	/**
	 * The title pattern the script numbers rows with ("Rule %d").
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	private static function label_pattern( array $field ): string {
		$label = (string) $field['row_label'];

		// Without arguments the kit string comes back as written: "Row %d".
		return str_contains( $label, '%d' ) ? $label : self::say( 'repeater_row' );
	}

	/**
	 * A row's title: "Rule 2", or a sub-field's value when row_label names one.
	 *
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $row    Row values.
	 * @param int                  $number Row number.
	 */
	private static function title( array $field, array $row, int $number ): string {
		$label = (string) $field['row_label'];

		if ( '' !== $label && ! str_contains( $label, '%d' ) && '' !== trim( (string) ( $row[ $label ] ?? '' ) ) ) {
			return (string) $row[ $label ];
		}

		return str_contains( $label, '%d' ) ? sprintf( $label, max( 1, $number ) ) : self::say( 'repeater_row', max( 1, $number ) );
	}

	/**
	 * Removing every row posts nothing.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return true;
	}

	/**
	 * Each row through its sub-fields. Removed and empty rows are dropped; a
	 * bad value keeps the row's saved value and is named ("Rule 2, Needle: …").
	 *
	 * @param mixed                $raw    Submitted rows.
	 * @param mixed                $stored Stored rows.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$registry = Kit::instance()->fields();
		$columns  = self::columns( $field );
		$before   = array();
		foreach ( (array) $stored as $row ) {
			if ( is_array( $row ) && ! empty( $row['_row'] ) ) {
				$before[ (string) $row['_row'] ] = $row;
			}
		}

		$rows     = array();
		$problems = array();
		foreach ( is_array( $raw ) ? $raw : array() as $posted ) {
			$is_new = is_array( $posted ) && empty( $posted['_row'] );
			if ( ! is_array( $posted ) || ! empty( $posted['_remove'] ) || self::empty_row( $posted, $columns, $is_new ) ) {
				continue;
			}

			$row_id = sanitize_key( (string) ( $posted['_row'] ?? '' ) );
			$row_id = '' === $row_id ? strtolower( wp_generate_password( 8, false, false ) ) : $row_id;
			$old    = $before[ $row_id ] ?? array();
			$clean  = array();
			$title  = self::title( $field, $posted, count( $rows ) + 1 );

			foreach ( $columns as $sub ) {
				$sid    = (string) $sub['id'];
				$type   = $registry->get( (string) $sub['type'] );
				$kept   = $old[ $sid ] ?? $sub['default'];
				$result = null === $type ? Result::rejected( $kept, '' ) : $type->sanitize( $posted[ $sid ] ?? ( $type->needs_marker( $sub ) || 'check' === $type->kind( $sub ) ? null : '' ), $kept, $sub, $posted );

				if ( $result->saved() ) {
					$problem = Validator::check( $sub, $result->value, $posted );
					if ( null !== $problem ) {
						$result = Result::rejected( $kept, $problem );
					}
				}

				$clean[ $sid ] = $result->value;
				if ( $result->error && '' !== $result->message ) {
					$problems[] = self::say( 'repeater_problem', $title, (string) $sub['label'], $result->message );
				}
			}

			$clean['_row'] = $row_id;
			$rows[]        = $clean;
		}

		$max = (int) $field['max_rows'];
		if ( $max > 0 && count( $rows ) > $max ) {
			$rows       = array_slice( $rows, 0, $max );
			$problems[] = Kit::instance()->strings()->count( 'repeater_max', $max );
		}

		return $problems ? Result::partial( $rows, implode( ' ', $problems ) ) : Result::ok( $rows );
	}

	/**
	 * Whether a posted row has nothing in it (an unused empty row). A new
	 * row (no row id yet) also counts as unused while every value is still
	 * what an empty row starts with: the sub-field's default, or a select's
	 * first choice when it has no placeholder. Without that, the empty rows
	 * printed for browsers without scripts would save as junk rows.
	 *
	 * @param array<string, mixed>       $posted  Posted row.
	 * @param list<array<string, mixed>> $columns Sub-fields.
	 * @param bool                       $is_new  Whether the row has no id yet.
	 */
	private static function empty_row( array $posted, array $columns, bool $is_new ): bool {
		foreach ( $columns as $sub ) {
			$value = $posted[ (string) $sub['id'] ] ?? '';
			if ( is_array( $value ) ? ! array_filter( $value ) : '' === trim( (string) $value ) ) {
				continue;
			}
			$value = is_array( $value ) ? array_map( 'strval', array_values( $value ) ) : (string) $value;
			if ( ! $is_new || ! in_array( $value, self::starting_values( $sub ), true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * What a sub-field holds in an empty row before anyone types: its
	 * default, and a select's first choice when it has no placeholder.
	 *
	 * @param array<string, mixed> $sub Sub-field.
	 * @return list<mixed>
	 */
	private static function starting_values( array $sub ): array {
		$values = array();
		if ( isset( $sub['default'] ) && '' !== $sub['default'] && array() !== $sub['default'] ) {
			$values[] = is_array( $sub['default'] ) ? array_map( 'strval', array_values( $sub['default'] ) ) : (string) $sub['default'];
		}
		if ( in_array( (string) $sub['type'], array( 'select', 'radio' ), true ) && empty( $sub['placeholder'] ) && is_array( $sub['options'] ?? null ) && $sub['options'] ) {
			$values[] = (string) array_key_first( $sub['options'] );
		}

		return $values;
	}

	/**
	 * A list of row objects.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		$registry   = Kit::instance()->fields();
		$properties = array( '_row' => array( 'type' => 'string' ) );
		foreach ( self::columns( $field ) as $sub ) {
			$type = $registry->get( (string) $sub['type'] );
			if ( null !== $type ) {
				$properties[ (string) $sub['id'] ] = (array) $type->schema( $sub );
			}
		}

		return array(
			'type'  => 'array',
			'items' => array(
				'type'       => 'object',
				'properties' => $properties,
			),
		);
	}

	/**
	 * The repeater script.
	 *
	 * @return list<string>
	 */
	public function scripts(): array {
		return array( 'repeater' );
	}
}
