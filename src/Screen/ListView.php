<?php
/**
 * A list's view (search, filters, sort, page) to and from the URL.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * The view in the URL. URL names are
 * WordPress's (search, filters[…], orderby, order, paged, per_page); the view
 * keeps DataViews' (search, filters, sort, page, perPage).
 *
 * A filter the URL doesn't mention keeps its default; `filters[{field}]=`
 * (empty) turns a default off. Anything unknown or not offered is dropped.
 *
 * @phpstan-type View array{search: string, filters: list<array{field: string, operator: string, value: mixed}>, sort: array{field: string, direction: string}, page: int, perPage: int}
 */
final class ListView {

	/**
	 * Operators by field type.
	 */
	public const OPERATORS = array(
		'enum'    => array( 'is', 'isNot', 'isAny', 'isNone' ),
		'boolean' => array( 'is', 'isNot' ),
		'text'    => array( 'is', 'isNot', 'contains' ),
	);

	/**
	 * Normalised list fields, with the defaults for every key.
	 *
	 * @param array<int|string, mixed> $fields List field descriptions.
	 * @return array<string, array<string, mixed>> By id.
	 */
	public static function fields( array $fields ): array {
		$out = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) ) {
				continue;
			}

			$type   = in_array( $field['type'] ?? 'text', array( 'text', 'integer', 'number', 'datetime', 'date', 'enum', 'boolean' ), true ) ? (string) ( $field['type'] ?? 'text' ) : 'text';
			$filter = isset( $field['filterBy'] ) && is_array( $field['filterBy'] ) ? $field['filterBy'] : null;

			if ( null !== $filter ) {
				$allowed = self::OPERATORS[ $type ] ?? array();
				$ops     = array_values( array_intersect( (array) ( $filter['operators'] ?? array( 'is' ) ), $allowed ) );
				$filter  = $ops ? array(
					'operators'  => $ops,
					'control'    => in_array( $filter['control'] ?? '', array( 'chips', 'select', 'segmented', 'checkbox' ), true ) ? (string) $filter['control'] : ( 'boolean' === $type ? 'checkbox' : 'select' ),
					'hide_empty' => ! empty( $filter['hide_empty'] ),
				) : null;
			}

			$elements  = array();
			$tones     = array();
			$described = $field['elements'] ?? array();
			foreach ( (array) ( is_callable( $described ) ? $described() : $described ) as $key => $element ) {
				if ( is_array( $element ) && isset( $element['value'] ) ) {
					$elements[ (string) $element['value'] ] = (string) ( $element['label'] ?? $element['value'] );
					if ( isset( $element['tone'] ) ) {
						$tones[ (string) $element['value'] ] = (string) $element['tone'];
					}
				} elseif ( is_scalar( $element ) ) {
					$elements[ (string) $key ] = (string) $element;
				}
			}

			$id         = sanitize_key( (string) $field['id'] );
			$out[ $id ] = array(
				'id'            => $id,
				'label'         => (string) ( $field['label'] ?? $id ),
				'type'          => $type,
				'elements'      => $elements,
				'tones'         => $tones,
				'filterBy'      => $filter,
				'enableSorting' => ! empty( $field['enableSorting'] ),
				'column'        => ! isset( $field['column'] ) || (bool) $field['column'],
				'align'         => 'end' === ( $field['align'] ?? ( in_array( $type, array( 'integer', 'number' ), true ) ? 'end' : 'start' ) ) ? 'end' : 'start',
				'width'         => in_array( $field['width'] ?? 'auto', array( 'auto', 'narrow', 'medium', 'wide' ), true ) ? (string) ( $field['width'] ?? 'auto' ) : 'auto',
				'render'        => isset( $field['render'] ) && is_callable( $field['render'] ) ? $field['render'] : null,
				'getValue'      => isset( $field['getValue'] ) && is_callable( $field['getValue'] ) ? $field['getValue'] : null,
				'csv'           => $field['csv'] ?? null,
				'label_all'     => isset( $field['label_all'] ) ? (string) $field['label_all'] : '',
				'hide_label'    => ! empty( $field['hide_label'] ),
			);
		}

		return $out;
	}

	/**
	 * The view the request asks for, checked against the fields.
	 *
	 * @param array<string, mixed>                $args     Query arguments, unslashed.
	 * @param array<string, array<string, mixed>> $fields   Normalised fields.
	 * @param array<string, mixed>                $defaults The list's default view.
	 * @param list<int>                           $sizes    Allowed page sizes.
	 * @return array<string, mixed> The view.
	 * @phpstan-return View
	 */
	public static function parse( array $args, array $fields, array $defaults, array $sizes ): array {
		$defaults = self::defaults( $defaults, $fields, $sizes );
		$given    = isset( $args['filters'] ) && is_array( $args['filters'] ) ? $args['filters'] : array();
		$filters  = array();

		foreach ( $fields as $id => $field ) {
			if ( null === $field['filterBy'] ) {
				continue;
			}

			if ( array_key_exists( $id, $given ) ) {
				$filter = self::filter( $field, $given[ $id ] );
			} else {
				$filter = null;
				foreach ( $defaults['filters'] as $default ) {
					if ( $default['field'] === $id ) {
						$filter = $default;
					}
				}
			}

			if ( null !== $filter ) {
				$filters[] = $filter;
			}
		}

		$sort = $defaults['sort'];
		if ( isset( $args['sort'] ) && is_string( $args['sort'] ) && str_contains( $args['sort'], ':' ) ) {
			// The sort select posts "field:direction".
			[ $args['orderby'], $args['order'] ] = explode( ':', $args['sort'], 2 );
		}
		if ( isset( $args['orderby'] ) && is_scalar( $args['orderby'] ) ) {
			$by = sanitize_key( (string) $args['orderby'] );
			if ( isset( $fields[ $by ] ) && $fields[ $by ]['enableSorting'] ) {
				$sort = array(
					'field'     => $by,
					'direction' => 'asc' === strtolower( (string) ( $args['order'] ?? 'asc' ) ) ? 'asc' : 'desc',
				);
			}
		}

		$per_page = isset( $args['per_page'] ) && in_array( (int) $args['per_page'], $sizes, true ) ? (int) $args['per_page'] : $defaults['perPage'];

		return array(
			'search'  => isset( $args['search'] ) && is_scalar( $args['search'] ) ? trim( sanitize_text_field( (string) $args['search'] ) ) : $defaults['search'],
			'filters' => $filters,
			'sort'    => $sort,
			'page'    => isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1,
			'perPage' => $per_page,
		);
	}

	/**
	 * The default view, checked like a request.
	 *
	 * @param array<string, mixed>                $defaults Described defaults.
	 * @param array<string, array<string, mixed>> $fields   Normalised fields.
	 * @param list<int>                           $sizes    Allowed page sizes.
	 * @return array<string, mixed>
	 * @phpstan-return View
	 */
	public static function defaults( array $defaults, array $fields, array $sizes ): array {
		$filters = array();
		foreach ( (array) ( $defaults['filters'] ?? array() ) as $filter ) {
			$id = isset( $filter['field'] ) ? (string) $filter['field'] : '';
			if ( isset( $fields[ $id ] ) && null !== $fields[ $id ]['filterBy'] ) {
				$checked = self::filter(
					$fields[ $id ],
					array(
						'op'    => $filter['operator'] ?? '',
						'value' => $filter['value'] ?? '',
					)
				);
				if ( null !== $checked ) {
					$filters[] = $checked;
				}
			}
		}

		$sort  = (array) ( $defaults['sort'] ?? array() );
		$by    = (string) ( $sort['field'] ?? '' );
		$first = $sizes[0] ?? 20;

		return array(
			'search'  => (string) ( $defaults['search'] ?? '' ),
			'filters' => $filters,
			'sort'    => array(
				'field'     => isset( $fields[ $by ] ) ? $by : '',
				'direction' => 'asc' === ( $sort['direction'] ?? 'desc' ) ? 'asc' : 'desc',
			),
			'page'    => 1,
			'perPage' => in_array( (int) ( $defaults['perPage'] ?? 0 ), $sizes, true ) ? (int) $defaults['perPage'] : $first,
		);
	}

	/**
	 * The URL arguments for a view: only what differs from the defaults.
	 *
	 * @param array<string, mixed>                $view     The view.
	 * @param array<string, mixed>                $defaults The default view (from defaults()).
	 * @param array<string, array<string, mixed>> $fields   Normalised fields.
	 * @return array<string, mixed>
	 */
	public static function args( array $view, array $defaults, array $fields ): array {
		$args = array();

		if ( '' !== $view['search'] ) {
			$args['search'] = $view['search'];
		}

		$mine = array();
		foreach ( $view['filters'] as $filter ) {
			$mine[ $filter['field'] ] = $filter;
		}
		$theirs = array();
		foreach ( $defaults['filters'] as $filter ) {
			$theirs[ $filter['field'] ] = $filter;
		}

		foreach ( $fields as $id => $field ) {
			if ( null === $field['filterBy'] ) {
				continue;
			}
			$filter  = $mine[ $id ] ?? null;
			$default = $theirs[ $id ] ?? null;

			if ( $filter === $default ) {
				continue;
			}
			if ( null === $filter ) {
				$args['filters'][ $id ] = '';
				continue;
			}

			$first = $field['filterBy']['operators'][0];
			if ( 'isAny' === $filter['operator'] ) {
				$args['filters'][ $id ] = array_values( (array) $filter['value'] );
			} elseif ( $filter['operator'] === $first && ! is_array( $filter['value'] ) ) {
				$args['filters'][ $id ] = self::scalar( $filter['value'] );
			} else {
				$args['filters'][ $id ] = array(
					'op'    => $filter['operator'],
					'value' => is_array( $filter['value'] ) ? array_values( $filter['value'] ) : self::scalar( $filter['value'] ),
				);
			}
		}

		if ( $view['sort'] !== $defaults['sort'] && '' !== $view['sort']['field'] ) {
			$args['orderby'] = $view['sort']['field'];
			$args['order']   = $view['sort']['direction'];
		}
		if ( $view['page'] > 1 ) {
			$args['paged'] = $view['page'];
		}
		if ( $view['perPage'] !== $defaults['perPage'] ) {
			$args['per_page'] = $view['perPage'];
		}

		return $args;
	}

	/**
	 * Whether a view filters or searches anything beyond the defaults.
	 *
	 * @param array<string, mixed> $view     The view.
	 * @param array<string, mixed> $defaults The default view.
	 */
	public static function filtered( array $view, array $defaults ): bool {
		return '' !== $view['search'] || $view['filters'] !== $defaults['filters'];
	}

	/**
	 * The filter on a field, or null.
	 *
	 * @param array<string, mixed> $view  The view.
	 * @param string               $field Field id.
	 * @return array{field: string, operator: string, value: mixed}|null
	 */
	public static function find( array $view, string $field ): ?array {
		foreach ( $view['filters'] as $filter ) {
			if ( $filter['field'] === $field ) {
				return $filter;
			}
		}

		return null;
	}

	/**
	 * One field's filter from the URL, or null when it's off or not valid.
	 *
	 * @param array<string, mixed> $field Normalised field.
	 * @param mixed                $raw   filters[{field}] from the URL.
	 * @return array{field: string, operator: string, value: mixed}|null
	 */
	private static function filter( array $field, mixed $raw ): ?array {
		$ops = $field['filterBy']['operators'];

		if ( is_array( $raw ) && array_key_exists( 'op', $raw ) ) {
			$op    = (string) $raw['op'];
			$value = $raw['value'] ?? '';
		} elseif ( is_array( $raw ) ) {
			$op    = in_array( 'isAny', $ops, true ) ? 'isAny' : $ops[0];
			$value = array_values( $raw );
		} else {
			$op    = $ops[0];
			$value = $raw;
		}

		if ( ! in_array( $op, $ops, true ) ) {
			return null;
		}

		$multiple = in_array( $op, array( 'isAny', 'isNone' ), true );
		$values   = array_values( array_filter( array_map( static fn( $one ): string => is_scalar( $one ) ? sanitize_text_field( (string) $one ) : '', (array) $value ), static fn( string $one ): bool => '' !== $one ) );

		if ( 'enum' === $field['type'] ) {
			$values = array_values( array_intersect( $values, array_map( 'strval', array_keys( $field['elements'] ) ) ) );
		}

		if ( ! $values ) {
			return null;
		}

		if ( 'boolean' === $field['type'] ) {
			return array(
				'field'    => $field['id'],
				'operator' => $op,
				'value'    => rest_sanitize_boolean( $values[0] ),
			);
		}

		return array(
			'field'    => $field['id'],
			'operator' => $op,
			'value'    => $multiple ? $values : $values[0],
		);
	}

	/**
	 * A filter value as it goes in the URL.
	 *
	 * @param mixed $value Value.
	 */
	private static function scalar( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
