<?php
/**
 * Pick terms, searching.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "term". query: taxonomy (default "category"), hide_empty (default false),
 * tree. Searching needs the taxonomy's assign_terms capability.
 */
class TermPicker extends Picker {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'term';
	}

	/**
	 * The taxonomy.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	private static function taxonomy( array $field ): string {
		return (string) ( $field['query']['taxonomy'] ?? 'category' );
	}

	/**
	 * Assigning terms of the taxonomy.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function capability( array $field ): string {
		$taxonomy = get_taxonomy( self::taxonomy( $field ) );

		return $taxonomy ? (string) $taxonomy->cap->assign_terms : 'manage_categories';
	}

	/**
	 * One page of matches.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param string               $q     Search text.
	 * @param int                  $page  Page.
	 * @return array{items: list<array{value: int, label: string, detail: string, depth: int}>, more: bool}
	 */
	public function search( array $field, string $q, int $page ): array {
		$taxonomy = self::taxonomy( $field );
		$args     = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => ! empty( $field['query']['hide_empty'] ),
			'number'     => self::PAGE + 1,
			'offset'     => ( max( 1, $page ) - 1 ) * self::PAGE,
			'orderby'    => 'name',
		);
		if ( '' !== $q ) {
			$args['search'] = $q;
		}

		// Browsing a tree: everything in parent order, a page at a time.
		$depths = '' === $q && ! empty( $field['query']['tree'] ) ? self::tree_depths( $args ) : null;
		if ( null !== $depths ) {
			$ids   = array_slice( array_keys( $depths ), ( max( 1, $page ) - 1 ) * self::PAGE, self::PAGE );
			$terms = $ids ? get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'include'    => $ids,
					'orderby'    => 'include',
					'hide_empty' => false,
				)
			) : array();
			$items = array();
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				if ( $term instanceof \WP_Term ) {
					$items[] = self::item( $term, $depths[ (int) $term->term_id ] ?? 0 );
				}
			}

			return array(
				'items' => $items,
				'more'  => count( $depths ) > max( 1, $page ) * self::PAGE,
			);
		}

		$terms = get_terms( $args );
		$terms = is_array( $terms ) ? $terms : array();
		$items = array();
		foreach ( array_slice( $terms, 0, self::PAGE ) as $term ) {
			if ( $term instanceof \WP_Term ) {
				$items[] = self::item( $term, 0 );
			}
		}

		return array(
			'items' => $items,
			'more'  => count( $terms ) > self::PAGE,
		);
	}

	/**
	 * Every match in parent order with its depth, or null when there are too
	 * many to put in order (the list is then flat).
	 *
	 * @param array<string, mixed> $args Term query arguments.
	 * @return array<int, int>|null Id => depth.
	 */
	private static function tree_depths( array $args ): ?array {
		$parents = get_terms(
			array_merge(
				$args,
				array(
					'fields' => 'id=>parent',
					'number' => self::TREE + 1,
					'offset' => 0,
				)
			)
		);
		if ( ! is_array( $parents ) || count( $parents ) > self::TREE ) {
			return null;
		}

		return self::tree_order( array_map( 'intval', $parents ) );
	}

	/**
	 * A term as a search result.
	 *
	 * @param \WP_Term $term  Term.
	 * @param int      $depth How far to indent it.
	 * @return array{value: int, label: string, detail: string, depth: int}
	 */
	private static function item( \WP_Term $term, int $depth ): array {
		return array(
			'value'  => (int) $term->term_id,
			'label'  => html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' ),
			'detail' => \BlissWebConcept\AdminKit\Kit::instance()->strings()->count( 'term_posts', (int) $term->count ),
			'depth'  => $depth,
		);
	}

	/**
	 * Names of chosen terms that still exist in the taxonomy.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param list<int>            $ids   Ids.
	 * @return array<int, string>
	 */
	public function labels( array $field, array $ids ): array {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::taxonomy( $field ),
				'include'    => $ids,
				'hide_empty' => false,
				'orderby'    => 'include',
			)
		);

		$labels = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$labels[ (int) $term->term_id ] = html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' );
			}
		}

		return $labels;
	}
}
