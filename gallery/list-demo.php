<?php
/**
 * Gallery: a list screen over sample /go/ links, with filters, sorting,
 * bulk actions, CSV export, a side-panel form and a grouped tab.
 *
 * @package BWCGallery
 */

namespace BWCGallery;

use BWCGallery\Vendor\BlissWebConcept\AdminKit\Component\Pill;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Kit;
use BWCGallery\Vendor\BlissWebConcept\AdminKit\Screen\ListScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Option holding the demo's edits: changed rows, deleted ids, added rows.
 */
const LIST_DEMO_OPTION = 'bwc_gallery_links';

/**
 * The sample links, with the demo's edits applied. Same rows every time.
 *
 * @return array<int, array<string, mixed>> By id.
 */
function list_demo_rows(): array {
	static $base = null;

	if ( null === $base ) {
		$brands = array( 'Northwind', 'Contoso', 'Tailspin', 'Litware', 'Fabrikam', 'Wingtip', 'Proseware', 'Lucerne', 'Adatum', 'Woodgrove', 'Relecloud', 'Coho', 'Alpine', 'Wideworld', 'Fourth Coffee', 'Bellows', 'Lamna', 'Vanarsdel', 'Trey', 'Margie' );
		$base   = array();
		$id     = 1;
		$now    = time();

		foreach ( $brands as $index => $brand ) {
			$slug   = sanitize_title( $brand );
			$offers = 4 + ( $index * 7 ) % 11;

			for ( $n = 0; $n <= $offers; $n++ ) {
				$kind = 0 === $n ? 'main' : ( $n > $offers - 2 && 0 === $index % 3 ? 'old' : 'offer' );
				$path = 0 === $n ? $slug : $slug . '-' . ( 'old' === $kind ? 'old-' : '' ) . $n;

				$base[ $id ] = array(
					'id'          => $id,
					'brand'       => $brand,
					'slug'        => 'go/' . $path,
					'destination' => 'https://' . $slug . '.example/' . ( 0 === $n ? '' : 'offer/' . $n ),
					'kind'        => $kind,
					'status'      => 0 === ( $id * 13 ) % 17 ? 'broken' : 'ok',
					'redirect'    => 0 === $n % 4 ? '301' : '307',
					'clicks'      => ( $id * 37 ) % 900 + ( 0 === $n ? 400 : 0 ),
					'updated'     => $now - ( ( $id * 7919 ) % ( 120 * DAY_IN_SECONDS ) ),
					'notes'       => '',
				);
				++$id;
			}
		}
	}

	$edits = get_option( LIST_DEMO_OPTION, array() );
	$edits = is_array( $edits ) ? $edits : array();
	$rows  = $base;

	foreach ( (array) ( $edits['changed'] ?? array() ) as $id => $values ) {
		if ( isset( $rows[ (int) $id ] ) ) {
			$rows[ (int) $id ] = array_merge( $rows[ (int) $id ], (array) $values );
		}
	}
	foreach ( (array) ( $edits['added'] ?? array() ) as $id => $values ) {
		$rows[ (int) $id ] = (array) $values + array(
			'id'      => (int) $id,
			'clicks'  => 0,
			'status'  => 'ok',
			'kind'    => 'offer',
			'updated' => time(),
		);
	}
	foreach ( (array) ( $edits['deleted'] ?? array() ) as $id ) {
		unset( $rows[ (int) $id ] );
	}

	return $rows;
}

/**
 * Saves part of the demo's edits.
 *
 * @param string $part  changed, added or deleted.
 * @param mixed  $value The part's new value.
 */
function list_demo_store( string $part, mixed $value ): void {
	$edits          = get_option( LIST_DEMO_OPTION, array() );
	$edits          = is_array( $edits ) ? $edits : array();
	$edits[ $part ] = $value;
	update_option( LIST_DEMO_OPTION, $edits, false );
}

/**
 * The query callback: filters, counts, sorts and slices the sample rows,
 * as a plugin with a few hundred rows in PHP would.
 *
 * @param array<string, mixed> $view The view.
 * @return array<string, mixed>
 */
function list_demo_query( array $view ): array {
	$rows   = array_values( list_demo_rows() );
	$search = mb_strtolower( (string) $view['search'] );

	$matches = static function ( array $row, ?string $skip = null ) use ( $view, $search ): bool {
		if ( '' !== $search && ! str_contains( mb_strtolower( $row['brand'] . ' ' . $row['slug'] . ' ' . $row['destination'] ), $search ) ) {
			return false;
		}
		foreach ( $view['filters'] as $filter ) {
			if ( $filter['field'] === $skip ) {
				continue;
			}
			$value = $row[ $filter['field'] ] ?? null;
			$want  = (array) $filter['value'];
			$hit   = in_array( (string) $value, array_map( 'strval', $want ), true );
			if ( in_array( $filter['operator'], array( 'is', 'isAny' ), true ) ? ! $hit : $hit ) {
				return false;
			}
		}
		return true;
	};

	// Counts per value, with every other filter applied, for the chips.
	$counts = array();
	foreach ( array( 'kind', 'status' ) as $field ) {
		foreach ( $rows as $row ) {
			if ( $matches( $row, $field ) ) {
				$counts[ $field ][ $row[ $field ] ] = ( $counts[ $field ][ $row[ $field ] ] ?? 0 ) + 1;
			}
		}
	}

	$found = array_values( array_filter( $rows, $matches ) );

	if ( '' !== $view['sort']['field'] ) {
		$by  = $view['sort']['field'];
		$dir = 'asc' === $view['sort']['direction'] ? 1 : -1;
		usort( $found, static fn( array $a, array $b ): int => $dir * ( array( $a[ $by ], $a['id'] ) <=> array( $b[ $by ], $b['id'] ) ) );
	}

	if ( 'brands' === ( $view['view'] ?? '' ) ) {
		// Grouped by brand: keep each brand's rows together, main link first.
		usort( $found, static fn( array $a, array $b ): int => array( $a['brand'], 'main' === $a['kind'] ? 0 : 1, $a['id'] ) <=> array( $b['brand'], 'main' === $b['kind'] ? 0 : 1, $b['id'] ) );
	}

	return array(
		'items'  => array_slice( $found, ( $view['page'] - 1 ) * $view['perPage'], $view['perPage'] ),
		'total'  => count( $found ),
		'counts' => $counts,
	);
}

/**
 * The list fields.
 *
 * @return list<array<string, mixed>>
 */
function list_demo_fields(): array {
	return array(
		array(
			'id'    => 'slug',
			'label' => __( 'Link', 'bwc-gallery' ),
			'type'  => 'text',
			'width' => 'medium',
			'csv'   => static fn( array $row ): string => '/' . $row['slug'],
		),
		array(
			'id'    => 'brand',
			'label' => __( 'Brand', 'bwc-gallery' ),
			'type'  => 'text',
		),
		array(
			'id'       => 'kind',
			'label'    => __( 'Kind', 'bwc-gallery' ),
			'type'     => 'enum',
			'elements' => array(
				'main'  => __( 'Brand link', 'bwc-gallery' ),
				'offer' => __( 'Offer link', 'bwc-gallery' ),
				'old'   => __( 'Old address', 'bwc-gallery' ),
			),
			'filterBy' => array(
				'operators' => array( 'is', 'isNot' ),
				'control'   => 'chips',
			),
		),
		array(
			'id'       => 'status',
			'label'    => __( 'Status', 'bwc-gallery' ),
			'type'     => 'enum',
			'elements' => array(
				'ok'     => __( 'Working', 'bwc-gallery' ),
				'broken' => __( 'Broken', 'bwc-gallery' ),
			),
			'filterBy' => array(
				'operators' => array( 'is' ),
				'control'   => 'select',
			),
			'render'   => static fn( array $row ): string => Pill::markup(
				'broken' === $row['status'] ? array(
					'text' => __( 'Broken', 'bwc-gallery' ),
					'tone' => 'danger',
				) : array(
					'text' => __( 'Working', 'bwc-gallery' ),
					'tone' => 'success',
				)
			),
			'csv'      => static fn( array $row ): string => $row['status'],
		),
		array(
			'id'            => 'clicks',
			'label'         => __( 'Clicks', 'bwc-gallery' ),
			'type'          => 'integer',
			'enableSorting' => true,
			'width'         => 'narrow',
		),
		array(
			'id'            => 'updated',
			'label'         => __( 'Changed', 'bwc-gallery' ),
			'type'          => 'date',
			'enableSorting' => true,
			'csv'           => static fn( array $row ): string => wp_date( 'Y-m-d', (int) $row['updated'] ),
		),
		array(
			'id'     => 'destination',
			'label'  => __( 'Goes to', 'bwc-gallery' ),
			'type'   => 'text',
			'column' => false,
			'csv'    => true,
		),
	);
}

/**
 * Registers the list screen. Runs at init.
 *
 * @param Kit $kit The kit.
 */
function register_list_demo( Kit $kit ): ListScreen {
	$fields = list_demo_fields();
	$view   = array(
		'filters' => array(
			array(
				'field'    => 'kind',
				'operator' => 'isNot',
				'value'    => 'old',
			),
		),
		'sort'    => array(
			'field'     => 'clicks',
			'direction' => 'desc',
		),
		'perPage' => 20,
	);

	return $kit->list_screen(
		array(
			'id'     => 'list',
			'label'  => __( 'List', 'bwc-gallery' ),
			'title'  => __( 'Links', 'bwc-gallery' ),
			'intro'  => __( 'Sample /go/ links. Search, filter, sort, tick rows for bulk actions, export what you see, or open one to edit it. Edits are saved for the gallery only.', 'bwc-gallery' ),
			'lists'  => array(
				'all'    => array(
					'label'  => __( 'All links', 'bwc-gallery' ),
					'count'  => static fn(): int => count( list_demo_rows() ),
					'fields' => $fields,
					'view'   => $view,
					'search' => array( 'placeholder' => __( 'Search brands, links or addresses', 'bwc-gallery' ) ),
					'bulk'   => array(
						'mark_checked' => array(
							'label'   => __( 'Mark as working', 'bwc-gallery' ),
							'handler' => __NAMESPACE__ . '\\list_demo_mark_working',
						),
						'delete'       => array(
							'label'   => __( 'Delete', 'bwc-gallery' ),
							'confirm' => __( 'Delete the ticked links? Visitors who follow them will get a "not found" page.', 'bwc-gallery' ),
							'handler' => __NAMESPACE__ . '\\list_demo_delete_many',
						),
					),
					'export' => array(
						'filename' => static fn(): string => 'gallery-links-' . wp_date( 'Y-m-d' ),
					),
					'empty'  => array(
						'none'    => __( 'No links yet.', 'bwc-gallery' ),
						'nomatch' => __( 'No links match these filters.', 'bwc-gallery' ),
					),
				),
				'brands' => array(
					'label'  => __( 'By brand', 'bwc-gallery' ),
					'fields' => array_map(
						static fn( array $field ): array => array( 'enableSorting' => false ) + $field,
						array_values( array_filter( $fields, static fn( array $field ): bool => 'brand' !== $field['id'] ) )
					),
					'view'   => array(
						'filters' => $view['filters'],
						'perPage' => 50,
					),
					'layout' => array(
						'type'    => 'grouped',
						'group'   => static fn( array $row ): array => array(
							'key'   => $row['brand'],
							'label' => $row['brand'],
						),
						/* translators: %s: number of clicks. */
						'summary' => static fn( array $rows ): string => sprintf( __( '%s clicks on this page', 'bwc-gallery' ), number_format_i18n( array_sum( array_column( $rows, 'clicks' ) ) ) ),
						'lead'    => static fn( array $row ): bool => 'main' === $row['kind'],
						/* translators: %d: number of offer links. */
						'fold'    => static fn( array $rest ): string => sprintf( _n( '%d offer link', '%d offer links', count( $rest ), 'bwc-gallery' ), count( $rest ) ),
					),
					'search' => array( 'placeholder' => __( 'Search brands, links or addresses', 'bwc-gallery' ) ),
				),
			),
			'query'  => __NAMESPACE__ . '\\list_demo_query',
			'drawer' => array(
				'param'     => 'edit',
				'add_label' => __( 'Add link', 'bwc-gallery' ),
				'title'     => static fn( ?array $row ): string => null === $row ? __( 'Add link', 'bwc-gallery' ) : '/' . $row['slug'],
				'fields'    => list_demo_drawer_fields(),
				'load'      => static fn( int|string $id ): ?array => list_demo_rows()[ (int) $id ] ?? null,
				'save'      => __NAMESPACE__ . '\\list_demo_save',
				'delete'    => __NAMESPACE__ . '\\list_demo_delete',
				/* translators: 1: number of clicks, 2: date. */
				'aside'     => static fn( array $row ): string => esc_html( sprintf( __( '%1$s clicks. Last changed %2$s.', 'bwc-gallery' ), number_format_i18n( (int) $row['clicks'] ), wp_date( (string) get_option( 'date_format' ), (int) $row['updated'] ) ) ),
			),
		)
	);
}

/**
 * The drawer's fields.
 *
 * @return list<array<string, mixed>>
 */
function list_demo_drawer_fields(): array {
	return array(
		array(
			'id'          => 'slug',
			'label'       => __( 'Short link', 'bwc-gallery' ),
			'type'        => 'text',
			'description' => __( 'The address after the site name, such as go/proseware.', 'bwc-gallery' ),
			'isValid'     => array(
				'required' => true,
				'pattern'  => '[a-z0-9/-]+',
			),
		),
		array(
			'id'      => 'brand',
			'label'   => __( 'Brand', 'bwc-gallery' ),
			'type'    => 'text',
			'isValid' => array( 'required' => true ),
		),
		array(
			'id'      => 'destination',
			'label'   => __( 'Goes to', 'bwc-gallery' ),
			'type'    => 'url',
			'isValid' => array( 'required' => true ),
		),
		array(
			'id'       => 'redirect',
			'label'    => __( 'Redirect type', 'bwc-gallery' ),
			'type'     => 'radio',
			'default'  => '307',
			'elements' => array(
				'301' => __( 'Permanent (301)', 'bwc-gallery' ),
				'307' => __( 'Temporary (307)', 'bwc-gallery' ),
			),
		),
		array(
			'id'    => 'notes',
			'label' => __( 'Notes', 'bwc-gallery' ),
			'type'  => 'textarea',
		),
	);
}

/**
 * Saves a row from the drawer.
 *
 * @param array<string, mixed> $clean Clean values.
 * @param int|string|null      $id    Row id, or null for a new one.
 * @return int|\WP_Error The row's id.
 */
function list_demo_save( array $clean, int|string|null $id ): int|\WP_Error {
	$rows = list_demo_rows();
	foreach ( $rows as $row ) {
		if ( $row['slug'] === $clean['slug'] && (int) $row['id'] !== (int) $id ) {
			return new \WP_Error( 'bwc_gallery_taken', __( 'Another link already uses that address. Choose a different one.', 'bwc-gallery' ) );
		}
	}

	$values = array_intersect_key( $clean, array_flip( array( 'slug', 'brand', 'destination', 'redirect', 'notes' ) ) ) + array( 'updated' => time() );
	$edits  = get_option( LIST_DEMO_OPTION, array() );
	$edits  = is_array( $edits ) ? $edits : array();

	if ( null === $id ) {
		$id                    = max( array_merge( array( 10000 ), array_keys( $rows ) ) ) + 1;
		$edits['added'][ $id ] = $values;
		list_demo_store( 'added', $edits['added'] );
		return (int) $id;
	}

	$edits['changed'][ (int) $id ] = array_merge( (array) ( $edits['changed'][ (int) $id ] ?? array() ), $values );
	list_demo_store( 'changed', $edits['changed'] );

	return (int) $id;
}

/**
 * Deletes one row.
 *
 * @param int|string $id Row id.
 */
function list_demo_delete( int|string $id ): bool {
	return 'success' === list_demo_delete_many( array( (string) $id ) )['tone'];
}

/**
 * Bulk: deletes the ticked rows.
 *
 * @param list<string> $ids Row ids.
 * @return array<string, string>
 */
function list_demo_delete_many( array $ids ): array {
	$edits   = get_option( LIST_DEMO_OPTION, array() );
	$deleted = array_values( array_unique( array_merge( (array) ( is_array( $edits ) ? ( $edits['deleted'] ?? array() ) : array() ), array_map( 'intval', $ids ) ) ) );
	list_demo_store( 'deleted', $deleted );

	return array(
		'tone' => 'success',
		/* translators: %d: number of links. */
		'text' => sprintf( _n( '%d link deleted.', '%d links deleted.', count( $ids ), 'bwc-gallery' ), count( $ids ) ),
	);
}

/**
 * Bulk: marks the ticked rows as working.
 *
 * @param list<string> $ids Row ids.
 * @return array<string, string>
 */
function list_demo_mark_working( array $ids ): array {
	$edits   = get_option( LIST_DEMO_OPTION, array() );
	$changed = (array) ( is_array( $edits ) ? ( $edits['changed'] ?? array() ) : array() );
	foreach ( $ids as $id ) {
		$changed[ (int) $id ] = array( 'status' => 'ok' ) + (array) ( $changed[ (int) $id ] ?? array() );
	}
	list_demo_store( 'changed', $changed );

	return array(
		'tone' => 'success',
		/* translators: %d: number of links. */
		'text' => sprintf( _n( '%d link marked as working.', '%d links marked as working.', count( $ids ), 'bwc-gallery' ), count( $ids ) ),
	);
}
