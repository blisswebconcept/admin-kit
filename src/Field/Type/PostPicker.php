<?php
/**
 * Pick posts, pages or custom posts, searching.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "post". query: post_type (default
 * "page"), post_status (default "publish"), tree (indent by parent),
 * exclude, parent. Searching needs the edit_posts capability of every listed
 * post type; items of a type that isn't public are only listed for users who
 * can edit them, and private items for users who can read them.
 */
class PostPicker extends Picker {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'post';
	}

	/**
	 * The field's query with its defaults.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array{post_type: list<string>, post_status: list<string>, tree: bool, exclude: list<int>, parent: int|null}
	 */
	private static function query( array $field ): array {
		$query = (array) ( $field['query'] ?? array() );

		return array(
			'post_type'   => array_values( array_map( 'strval', (array) ( $query['post_type'] ?? 'page' ) ) ),
			'post_status' => array_values( array_map( 'strval', (array) ( $query['post_status'] ?? 'publish' ) ) ),
			'tree'        => ! empty( $query['tree'] ),
			'exclude'     => array_values( array_map( 'absint', (array) ( $query['exclude'] ?? array() ) ) ),
			'parent'      => isset( $query['parent'] ) ? absint( $query['parent'] ) : null,
		);
	}

	/**
	 * Editing posts of the field's (first) post type: "edit_posts" for posts
	 * and pages, the type's own capability for others ("edit_shop_coupons").
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function capability( array $field ): string {
		$type = get_post_type_object( self::query( $field )['post_type'][0] ?? 'page' );

		return $type ? (string) $type->cap->edit_posts : 'edit_posts';
	}

	/**
	 * Editing posts of every post type the field lists. An unknown type
	 * can't be searched; "any" needs edit_posts, and each item is still
	 * checked on its own.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function can_search( array $field ): bool {
		foreach ( self::query( $field )['post_type'] as $name ) {
			$type = 'any' === $name ? null : get_post_type_object( $name );
			$cap  = 'any' === $name ? 'edit_posts' : ( $type ? (string) $type->cap->edit_posts : '' );
			if ( '' === $cap || ! current_user_can( $cap ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One page of matches, by title.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param string               $q     Search text.
	 * @param int                  $page  Page.
	 * @return array{items: list<array{value: int, label: string, detail: string, depth: int}>, more: bool}
	 */
	public function search( array $field, string $q, int $page ): array {
		$query = self::query( $field );
		$args  = array(
			'post_type'              => $query['post_type'],
			'post_status'            => $query['post_status'],
			'posts_per_page'         => self::PAGE,
			'paged'                  => max( 1, $page ),
			'orderby'                => '' === $q ? array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			) : 'relevance',
			'post__not_in'           => $query['exclude'],
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'suppress_filters'       => false,
		);
		if ( '' !== $q ) {
			$args['s'] = $q;
		}
		if ( null !== $query['parent'] ) {
			$args['post_parent'] = $query['parent'];
		}

		// Browsing a tree: everything in parent order, a page at a time.
		$depths = '' === $q && $query['tree'] ? $this->tree_depths( $args ) : null;
		if ( null !== $depths ) {
			$ids   = array_slice( array_keys( $depths ), ( max( 1, $page ) - 1 ) * self::PAGE, self::PAGE );
			$items = array();
			if ( $ids ) {
				foreach ( get_posts(
					array(
						'post__in'       => $ids,
						'post_type'      => $query['post_type'],
						'post_status'    => $query['post_status'],
						'posts_per_page' => count( $ids ),
						'orderby'        => 'post__in',
					)
				) as $post ) {
					if ( $this->readable( $post ) ) {
						$items[] = $this->item( $post, $depths[ (int) $post->ID ] ?? 0 );
					}
				}
			}

			return array(
				'items' => $items,
				'more'  => count( $depths ) > max( 1, $page ) * self::PAGE,
			);
		}

		$found = new \WP_Query( $args );
		$items = array();
		foreach ( $found->posts as $post ) {
			if ( $post instanceof \WP_Post && $this->readable( $post ) ) {
				$items[] = $this->item( $post, 0 );
			}
		}

		return array(
			'items' => $items,
			'more'  => $found->max_num_pages > $page,
		);
	}

	/**
	 * Every match in parent order with its depth, or null when there are too
	 * many to put in order (the list is then flat).
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, int>|null Id => depth.
	 */
	private function tree_depths( array $args ): ?array {
		$found = new \WP_Query(
			array_merge(
				$args,
				array(
					'fields'         => 'id=>parent',
					'posts_per_page' => self::TREE + 1,
					'paged'          => 1,
					'no_found_rows'  => true,
				)
			)
		);
		if ( count( $found->posts ) > self::TREE ) {
			return null;
		}

		$parents = array();
		foreach ( $found->posts as $post ) {
			// "id=>parent" rows are plain objects with ID and post_parent.
			$row = (array) $post;
			if ( ! empty( $row['ID'] ) ) {
				$parents[ (int) $row['ID'] ] = (int) ( $row['post_parent'] ?? 0 );
			}
		}

		return self::tree_order( $parents );
	}

	/**
	 * Titles of chosen posts that still match the query.
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

		$query  = self::query( $field );
		$labels = array();
		foreach ( get_posts(
			array(
				'post__in'       => $ids,
				'post_type'      => $query['post_type'],
				'post_status'    => $query['post_status'],
				'posts_per_page' => count( $ids ),
				'orderby'        => 'post__in',
			)
		) as $post ) {
			if ( ! in_array( (int) $post->ID, $query['exclude'], true ) ) {
				$labels[ (int) $post->ID ] = $this->title( $post );
			}
		}

		return $labels;
	}

	/**
	 * A post as a search result.
	 *
	 * @param \WP_Post $post  Post.
	 * @param int      $depth How far to indent it.
	 * @return array{value: int, label: string, detail: string, depth: int}
	 */
	private function item( \WP_Post $post, int $depth ): array {
		$type = get_post_type_object( $post->post_type );
		$link = get_permalink( $post );

		return array(
			'value'  => (int) $post->ID,
			'label'  => $this->title( $post ),
			'detail' => trim( ( $type ? (string) $type->labels->singular_name : '' ) . ( $link ? ' · ' . wp_make_link_relative( $link ) : '' ), ' ·' ),
			'depth'  => $depth,
		);
	}

	/**
	 * Its title, or "(no title)".
	 *
	 * @param \WP_Post $post Post.
	 */
	private function title( \WP_Post $post ): string {
		$title = trim( wp_strip_all_tags( get_the_title( $post ) ) );

		return '' === $title ? self::say( 'picker_no_title' ) : html_entity_decode( $title, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Published posts of a public type for everyone. A post of a type that
	 * isn't public (coupons, orders) only for users who can edit it, since
	 * WordPress lets anyone "read" a published one; a private or draft post
	 * of a public type for users who can read it.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function readable( \WP_Post $post ): bool {
		$status = get_post_status_object( (string) $post->post_status );
		$type   = get_post_type_object( (string) $post->post_type );
		$public = null !== $type && $type->public;

		if ( $public && $status && $status->public ) {
			return true;
		}

		return current_user_can( 'edit_post', $post->ID ) || ( $public && current_user_can( 'read_post', $post->ID ) );
	}
}
