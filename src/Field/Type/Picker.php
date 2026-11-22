<?php
/**
 * Base of the searchable pickers: posts, users, terms.
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
 * Searchable picker. Without scripts the
 * server prints a plain control (a select of the first 200, or for users a
 * list of usernames); the picker script swaps in a search box with chips.
 * The search request names the field by a token, and the server uses that
 * field's own query, so a request can't widen it.
 *
 * Stored: an id (one) or a list of ids (multiple).
 */
abstract class Picker extends BaseType {

	/**
	 * Items in the no-script select.
	 */
	public const FALLBACK = 200;

	/**
	 * Items per search page.
	 */
	public const PAGE = 20;

	/**
	 * Most items a tree is put in parent order for; past this the list is flat.
	 */
	public const TREE = 500;

	/**
	 * The capability a user needs to search this field.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	abstract public function capability( array $field ): string;

	/**
	 * Whether the current user may search this field: its capability by default.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function can_search( array $field ): bool {
		return current_user_can( $this->capability( $field ) );
	}

	/**
	 * One page of matches.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param string               $q     Search text ('' for the first page of everything).
	 * @param int                  $page  Page, from 1.
	 * @return array{items: list<array{value: int, label: string, detail: string, depth: int}>, more: bool}
	 */
	abstract public function search( array $field, string $q, int $page ): array;

	/**
	 * Labels of known ids, for chips.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param list<int>            $ids   Ids.
	 * @return array<int, string> Id => label, only for ids that exist and match the field's query.
	 */
	abstract public function labels( array $field, array $ids ): array;

	/**
	 * Extra keys: multiple, max_items, query.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'   => 0,
			'multiple'  => false,
			'max_items' => 0,
			'query'     => array(),
		);
	}

	/**
	 * The token a search request names the field by: the type, the field id
	 * and its query, hashed.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function token( array $field ): string {
		return substr( md5( $this->name() . '|' . (string) ( $field['id'] ?? '' ) . '|' . (string) wp_json_encode( $field['query'] ?? array() ) ), 0, 12 );
	}

	/**
	 * Ids in tree order: each parent followed by its children, with their
	 * depth in this set. An item whose parent isn't in the set starts a
	 * branch of its own. Siblings keep the order they came in.
	 *
	 * @param array<int, int> $parents Id => parent id, in sibling order.
	 * @return array<int, int> Id => depth, in tree order.
	 */
	protected static function tree_order( array $parents ): array {
		$children = array();
		$roots    = array();
		foreach ( $parents as $id => $parent ) {
			if ( $parent > 0 && isset( $parents[ $parent ] ) && $parent !== $id ) {
				$children[ $parent ][] = $id;
			} else {
				$roots[] = $id;
			}
		}

		$order = array();
		$stack = array_map( static fn( int $id ): array => array( $id, 0 ), array_reverse( $roots ) );
		while ( $stack ) {
			list( $id, $depth ) = array_pop( $stack );
			if ( isset( $order[ $id ] ) ) {
				continue;
			}
			$order[ $id ] = $depth;
			foreach ( array_reverse( $children[ $id ] ?? array() ) as $child ) {
				$stack[] = array( $child, $depth + 1 );
			}
		}

		// Parents that loop back on each other never reach a root; keep them, flat.
		foreach ( array_diff_key( $parents, $order ) as $id => $parent ) {
			$order[ $id ] = 0;
		}

		return $order;
	}

	/**
	 * The ids a value holds.
	 *
	 * @param mixed $value Stored value.
	 * @return list<int>
	 */
	protected static function ids( mixed $value ): array {
		$ids = array();
		foreach ( (array) $value as $one ) {
			$id = is_scalar( $one ) ? absint( $one ) : 0;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * The plain control printed for browsers without the script: a select of
	 * the first 200 plus what's chosen. Users override this with a list.
	 *
	 * @param Context   $context Field context.
	 * @param list<int> $chosen  Chosen ids.
	 */
	protected function fallback( Context $context, array $chosen ): string {
		$field    = $context->field;
		$options  = array();
		$page     = 0;
		$multiple = ! empty( $field['multiple'] );

		// Pages of the search until the select is full or nothing is left.
		do {
			$found = $this->search( $field, '', ++$page );
			foreach ( $found['items'] as $item ) {
				$options[ $item['value'] ] = str_repeat( '— ', $item['depth'] ) . $item['label'];
			}
			$full = count( $options ) >= self::FALLBACK;
		} while ( $found['more'] && ! $full );
		foreach ( $this->labels( $field, array_diff( $chosen, array_keys( $options ) ) ) as $id => $label ) {
			$options[ $id ] = $label;
		}

		$html = '<select' . Html::attributes(
			array_merge(
				array(
					'class'                    => 'bwc-control bwc-control--full',
					'multiple'                 => $multiple,
					'size'                     => $multiple ? '6' : null,
					'data-bwc-picker-fallback' => true,
				),
				$context->attributes( false ),
				array(
					'name'     => $context->name . ( $multiple ? '[]' : '' ),
					'disabled' => $context->read_only,
				)
			)
		) . '>' . ( $multiple ? '' : '<option value="">' . esc_html( self::say( 'choose' ) ) . '</option>' );

		foreach ( $options as $id => $label ) {
			$html .= '<option value="' . esc_attr( (string) $id ) . '"' . ( in_array( (int) $id, $chosen, true ) ? ' selected' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select>';

		if ( $found['more'] ) {
			$html .= '<p class="bwc-picker__more" data-bwc-picker-more>' . esc_html( self::say( 'picker_first', self::FALLBACK ) ) . '</p>';
		}

		return $html;
	}

	/**
	 * The picker: the plain control, and what the script needs to replace it.
	 *
	 * @param Context $context Field context.
	 */
	public function control( Context $context ): string {
		$field  = $context->field;
		$chosen = self::ids( $context->value );
		$labels = $this->labels( $field, $chosen );
		$items  = array();
		foreach ( $chosen as $id ) {
			if ( isset( $labels[ $id ] ) ) {
				$items[] = array(
					'value' => $id,
					'label' => $labels[ $id ],
				);
			}
		}

		return '<div' . Html::attributes(
			array(
				'class'                    => 'bwc-picker',
				'data-bwc-picker'          => $this->token( $field ),
				'data-bwc-picker-name'     => $context->name . ( empty( $field['multiple'] ) ? '' : '[]' ),
				'data-bwc-picker-max'      => empty( $field['multiple'] ) ? '1' : (string) (int) $field['max_items'],
				'data-bwc-picker-chosen'   => (string) wp_json_encode( $items ),
				'data-bwc-picker-label'    => (string) $field['label'],
				'data-bwc-picker-id'       => $context->id,
				'data-bwc-picker-readonly' => $context->read_only ? '1' : null,
			)
		) . '>' . $this->fallback( $context, $chosen ) . '</div>';
	}

	/**
	 * An empty choice posts nothing, so the form needs a marker.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function needs_marker( array $field ): bool {
		return true;
	}

	/**
	 * Ids that exist and match the field's query; one or a list.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		$ids   = self::ids( $raw );
		$known = array_keys( $this->labels( $field, $ids ) );
		$ids   = array_values( array_filter( $ids, static fn( int $id ): bool => in_array( $id, $known, true ) ) );

		if ( empty( $field['multiple'] ) ) {
			return Result::ok( $ids[0] ?? 0 );
		}

		$max = (int) $field['max_items'];
		if ( $max > 0 && count( $ids ) > $max ) {
			return Result::adjusted( array_slice( $ids, 0, $max ), self::count_say( 'field_max_items', $max ) );
		}

		return Result::ok( $ids );
	}

	/**
	 * An integer, or a list of them.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<string, mixed>
	 */
	public function schema( array $field ): ?array {
		return empty( $field['multiple'] ) ? array( 'type' => 'integer' ) : array(
			'type'  => 'array',
			'items' => array( 'type' => 'integer' ),
		);
	}

	/**
	 * The picker script.
	 *
	 * @return list<string>
	 */
	public function scripts(): array {
		return array( 'picker' );
	}

	/**
	 * A plural kit string.
	 *
	 * @param string $key   Key.
	 * @param int    $count Count.
	 */
	protected static function count_say( string $key, int $count ): string {
		return \BlissWebConcept\AdminKit\Kit::instance()->strings()->count( $key, $count );
	}
}
