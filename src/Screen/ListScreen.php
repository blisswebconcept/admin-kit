<?php
/**
 * The list screen: records with search, filters, sorting and pages.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Component\Button;
use BlissWebConcept\AdminKit\Component\Chips;
use BlissWebConcept\AdminKit\Component\Drawer;
use BlissWebConcept\AdminKit\Component\EmptyState;
use BlissWebConcept\AdminKit\Component\GroupedList;
use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Component\Pagination;
use BlissWebConcept\AdminKit\Component\Panel;
use BlissWebConcept\AdminKit\Component\Search;
use BlissWebConcept\AdminKit\Component\Segmented;
use BlissWebConcept\AdminKit\Component\Select;
use BlissWebConcept\AdminKit\Component\Table;
use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Shell\PageHead;
use BlissWebConcept\AdminKit\Storage\RecordStorage;

defined( 'ABSPATH' ) || exit;

/**
 * ListScreen. The plugin describes its fields
 * and supplies one query callback; the kit reads the view from the URL, draws
 * the toolbar, the table or grouped list, the pages, bulk actions, the CSV
 * link and the side-panel form, and runs the actions behind them.
 *
 * Several lists can share one screen as panel tabs (`lists`); each list's
 * keys fall back to the screen's.
 */
final class ListScreen extends BaseScreen {

	/**
	 * Page sizes offered when the list doesn't say.
	 */
	private const SIZES = array( 20, 50, 100 );

	/**
	 * Rows read per query while exporting.
	 */
	private const EXPORT_BATCH = 500;

	/**
	 * Lists by key as described, the first is the default. Each is read
	 * (fields normalised, defaults worked out) only when it's first used, so
	 * a list whose filter options come from the database costs nothing on
	 * the admin pages that don't show it.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $lists = array();

	/**
	 * Lists already read, by key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $read = array();

	/**
	 * The side-panel form, or null.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $drawer = null;

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config See Kit::list_screen().
	 * @throws \InvalidArgumentException When a list has no query callback.
	 */
	public function __construct( Kit $kit, array $config ) {
		parent::__construct( $kit, $config );

		$lists = isset( $config['lists'] ) && is_array( $config['lists'] ) ? $config['lists'] : array( 'default' => array() );
		foreach ( $lists as $key => $def ) {
			$key = sanitize_key( (string) $key );
			$def = (array) $def + $config;

			if ( ! isset( $def['query'] ) || ! is_callable( $def['query'] ) ) {
				throw new \InvalidArgumentException( esc_html( "Admin Kit ListScreen \"{$this->id}\": the list \"{$key}\" needs a query callback." ) );
			}

			$def['key']          = $key;
			$this->lists[ $key ] = $def;
		}

		if ( isset( $config['drawer'] ) && is_array( $config['drawer'] ) && isset( $config['drawer']['load'], $config['drawer']['save'] ) ) {
			$this->drawer           = $config['drawer'] + array(
				'param'  => 'edit',
				'fields' => array(),
			);
			$this->drawer['fields'] = Form::without_secrets( (array) $this->drawer['fields'], 'a list drawer' );
		}
	}

	/**
	 * Registers the actions the lists use: bulk, export, drawer save and delete.
	 */
	public function register(): void {
		$actions = $this->kit->actions();
		$hook    = $this->hook();
		$cap     = $this->capability();

		$bulk = array_filter( $this->lists, static fn( array $def ): bool => (bool) self::bulk_actions( $def ) );
		if ( $bulk ) {
			$actions->register(
				'bulk_' . $hook,
				array( $this, 'run_bulk' ),
				array(
					'capability'   => $cap,
					'input'        => array(
						'ids'        => 'text[]',
						'ids_action' => 'key',
						'_bwc_list'  => 'key',
					),
					'confirm_when' => fn( array $input ): string => (string) ( $this->list_def( (string) $input['_bwc_list'] )['bulk'][ (string) $input['ids_action'] ]['confirm'] ?? '' ),
				)
			);
		}

		if ( array_filter( $this->lists, static fn( array $def ): bool => isset( $def['export'] ) && is_array( $def['export'] ) ) ) {
			$actions->register(
				'export_' . $hook,
				array( $this, 'run_export' ),
				array(
					'capability' => $cap,
					'method'     => 'get',
					'input'      => array(
						'search'   => 'text',
						'filters'  => 'array',
						'orderby'  => 'key',
						'order'    => 'key',
						'per_page' => 'int',
						'view'     => 'key',
					),
				)
			);
		}

		if ( null !== $this->drawer ) {
			$this->kit->fields()->allow_search( (array) $this->drawer['fields'], array( $this, 'can' ) );

			$param = (string) $this->drawer['param'];
			$close = static fn( array $input, mixed $result, string $back ): string => is_array( $result ) && ! empty( $result['keep_open'] ) ? $back : remove_query_arg( array( $param, 'new' ), $back );

			$actions->register(
				'drawer_' . $hook,
				array( $this, 'run_save' ),
				array(
					'capability' => $cap,
					'input'      => array(
						$this->drawer_name() => 'array',
						'_bwc_id'            => 'text',
					),
					'redirect'   => $close,
				)
			);

			if ( isset( $this->drawer['delete'] ) && is_callable( $this->drawer['delete'] ) ) {
				$actions->register(
					'drawer_delete_' . $hook,
					array( $this, 'run_delete' ),
					array(
						'capability' => $cap,
						'confirm'    => (string) ( $this->drawer['delete_confirm'] ?? $this->kit->strings()->get( 'confirm_delete' ) ),
						'input'      => array( '_bwc_id' => 'text' ),
						'redirect'   => $close,
					)
				);
			}
		}
	}

	/**
	 * A list by key, or the first.
	 *
	 * @param string $key List key.
	 * @return array<string, mixed>
	 */
	public function list_def( string $key = '' ): array {
		$key = isset( $this->lists[ $key ] ) ? $key : (string) array_key_first( $this->lists );

		if ( ! isset( $this->read[ $key ] ) ) {
			$this->read[ $key ] = $this->read_list( $this->lists[ $key ] );
		}

		return $this->read[ $key ];
	}

	/**
	 * The list this request shows: ?view=, or the first.
	 */
	public function current_list(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which list to show; nothing is changed.
		$key = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';

		return isset( $this->lists[ $key ] ) ? $key : (string) array_key_first( $this->lists );
	}

	/**
	 * The view this request asks for.
	 *
	 * @param string $key List key.
	 * @return array<string, mixed>
	 */
	public function view( string $key ): array {
		$def = $this->list_def( $key );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the view is read-only state in the URL; ListView checks every part.
		$args = wp_unslash( $_GET );

		return ListView::parse( (array) $args, $def['fields'], $def['defaults'], $def['sizes'] );
	}

	/**
	 * Link to a list with a view; the panel tab is left out for the first list.
	 *
	 * @param string               $key   List key.
	 * @param array<string, mixed> $view  The view.
	 * @param array<string, mixed> $extra Extra query arguments (the drawer's).
	 */
	public function link( string $key, array $view, array $extra = array() ): string {
		$def  = $this->list_def( $key );
		$args = ListView::args( $view, $def['defaults'], $def['fields'] );

		if ( array_key_first( $this->lists ) !== $def['key'] ) {
			$args['view'] = $def['key'];
		}

		// add_query_arg() doesn't encode values, and the view's come from the request.
		return add_query_arg( rawurlencode_deep( array_merge( $args, $extra ) ), $this->url() );
	}

	/**
	 * Link that opens the side-panel form for a record, keeping the view.
	 *
	 * @param int|string|null $id Record id, or null for a new one.
	 */
	public function drawer_url( int|string|null $id ): string {
		if ( null === $this->drawer ) {
			return '';
		}

		$key = $this->current_list();

		return $this->link( $key, $this->view( $key ), null === $id ? array( 'new' => '1' ) : array( (string) $this->drawer['param'] => (string) $id ) );
	}

	/**
	 * Runs the query for a view, with the page clamped to the last one.
	 *
	 * @param string               $key  List key.
	 * @param array<string, mixed> $view The view; its page may change.
	 * @return array{items: list<array<string, mixed>>, total: int, counts: array<string, array<string, int>>, totals: array<string, int>}
	 */
	public function query( string $key, array &$view ): array {
		$def    = $this->list_def( $key );
		$result = $this->run_query( $def, $view );
		$pages  = (int) max( 1, ceil( $result['total'] / max( 1, $view['perPage'] ) ) );

		if ( $view['page'] > $pages ) {
			$view['page'] = $pages;
			$result       = $this->run_query( $def, $view );
		}

		return $result;
	}

	/**
	 * The screen's body.
	 */
	public function render(): string {
		$key    = $this->current_list();
		$def    = $this->list_def( $key );
		$view   = $this->view( $key );
		$detail = $this->config['detail'] ?? null;

		// A record opened in place: its detail view, with a back link that keeps the filters.
		if ( $detail instanceof DetailScreen && null !== $detail->requested() ) {
			return $detail->render(
				(string) $detail->requested(),
				$view,
				function ( array $changes ) use ( $key, $view, $detail ): string {
					$args = array( $detail->param() => (string) $detail->requested() );
					foreach ( $changes as $name => $value ) {
						if ( null === $value ) {
							unset( $args[ $name ] );
						} else {
							$args[ $name ] = $value;
						}
					}
					return $this->link( $key, $view, $args );
				}
			);
		}

		$result = $this->query( $key, $view );

		$filtered = ListView::filtered( $view, $def['defaults'] );
		$before   = $this->config['before'] ?? null;
		$html     = '';

		$head = '';
		if ( ! empty( $this->config['title'] ) ) {
			$meta = $this->config['meta'] ?? '';
			$head = PageHead::markup(
				array(
					'title'        => (string) $this->config['title'],
					'description'  => (string) ( $this->config['intro'] ?? '' ),
					'meta'         => is_callable( $meta ) ? (string) $meta( $view ) : (string) $meta,
					'actions_html' => $this->head_actions( $key, $view ),
				)
			);
		}

		$tabs = array();
		if ( count( $this->lists ) > 1 ) {
			foreach ( array_keys( $this->lists ) as $tab_key ) {
				$tab              = $this->list_def( (string) $tab_key );
				$tabs[ $tab_key ] = array(
					'label' => $tab['label'],
					'url'   => $this->link( (string) $tab_key, $tab['defaults'] ),
				);
				if ( null !== $tab['count'] ) {
					$tabs[ $tab_key ]['count'] = ( $tab['count'] )();
				}
			}
		}

		$html .= '<div class="' . esc_attr( Html::classes( 'bwc-list', array(), $filtered ? 'is-filtered' : '' ) ) . '" id="' . esc_attr( $this->kit->id() . '-' . $this->id . '-list' ) . '">'
			. $head
			// Tiles, notices and cards that belong to the whole screen, between its title and the list.
			. ( is_callable( $before ) ? (string) $before( $view ) : '' )
			. Panel::markup(
				array(
					'tabs'         => $tabs,
					'current_tab'  => $key,
					'tabs_label'   => (string) ( $this->config['title'] ?? $this->label ),
					'toolbar_html' => $this->toolbar( $key, $view, $result, $filtered ),
					'content_html' => $this->content( $key, $view, $result, $filtered ),
					'footer_html'  => $result['total'] > 0 ? Pagination::markup(
						array(
							'page'     => $view['page'],
							'per_page' => $view['perPage'],
							'total'    => $result['total'],
							'url'      => fn( int $page ): string => $this->link( $key, array( 'page' => $page ) + $view ),
						)
					) : '',
					'note'         => is_callable( $def['note'] ) ? (string) $def['note']( $view ) : (string) $def['note'],
				)
			)
			. '</div>';

		return $html . $this->drawer_markup( $key, $view );
	}

	/**
	 * A list as described, normalised: its fields (a callable is called now),
	 * page sizes, default view, layout, search, bulk actions, export and the rest.
	 *
	 * @param array<string, mixed> $def The list's description, over the screen's.
	 * @return array<string, mixed>
	 */
	private function read_list( array $def ): array {
		$described = $def['fields'] ?? array();
		$fields    = ListView::fields( is_callable( $described ) ? (array) $described() : (array) $described );
		$sizes     = array_values( array_filter( array_map( 'intval', (array) ( $def['per_page'] ?? self::SIZES ) ) ) );
		$sizes     = $sizes ? $sizes : self::SIZES;
		$search    = $def['search'] ?? true;

		return array(
			'key'      => (string) $def['key'],
			'label'    => (string) ( $def['label'] ?? $this->label ),
			'count'    => isset( $def['count'] ) && is_callable( $def['count'] ) ? $def['count'] : null,
			'fields'   => $fields,
			'query'    => $def['query'],
			'sizes'    => $sizes,
			'defaults' => ListView::defaults( (array) ( $def['view'] ?? array() ), $fields, $sizes ),
			'layout'   => (array) ( $def['layout'] ?? array( 'type' => 'table' ) ),
			'search'   => false === $search ? null : ( is_array( $search ) ? $search : array() ),
			'bulk'     => self::bulk_actions( $def ),
			'export'   => isset( $def['export'] ) && is_array( $def['export'] ) ? $def['export'] : null,
			'empty'    => (array) ( $def['empty'] ?? array() ),
			'sorts'    => array_map( 'strval', array_filter( (array) ( $def['sorts'] ?? array() ), 'is_scalar' ) ),
			'note'     => $def['note'] ?? '',
		);
	}

	/**
	 * A list's bulk actions that have a handler.
	 *
	 * @param array<string, mixed> $def The list's description.
	 * @return array<string, array<string, mixed>>
	 */
	private static function bulk_actions( array $def ): array {
		return array_filter( (array) ( $def['bulk'] ?? array() ), static fn( $one ): bool => is_array( $one ) && isset( $one['handler'] ) && is_callable( $one['handler'] ) );
	}

	/**
	 * The bulk action's handler.
	 *
	 * @param array<string, mixed> $input ids, ids_action, _bwc_list.
	 * @return array<string, mixed>|\WP_Error|null
	 */
	public function run_bulk( array $input ): array|\WP_Error|null {
		$bulk = $this->list_def( (string) $input['_bwc_list'] )['bulk'][ (string) $input['ids_action'] ] ?? null;

		if ( null === $bulk || ! $input['ids'] ) {
			return array(
				'tone' => 'info',
				'text' => $this->kit->strings()->get( 'bulk_choose' ),
			);
		}

		$result = ( $bulk['handler'] )( $input['ids'] );

		return is_array( $result ) || is_wp_error( $result ) ? $result : null;
	}

	/**
	 * The export action's handler: the current view as CSV. Ends the request.
	 *
	 * @param array<string, mixed> $input The view's URL arguments.
	 */
	public function run_export( array $input ): void {
		$key                 = isset( $this->lists[ (string) $input['view'] ] ) ? (string) $input['view'] : (string) array_key_first( $this->lists );
		$def                 = $this->list_def( $key );
		$view                = ListView::parse( $input, $def['fields'], $def['defaults'], $def['sizes'] );
		$export              = (array) $def['export'];
		$file                = isset( $export['filename'] ) && is_callable( $export['filename'] ) ? (string) $export['filename']( $view ) : $this->kit->id() . '-' . $this->id . '-' . wp_date( 'Y-m-d' );
		[ $headings, $rows ] = $this->export_rows( $key, $view );

		$this->kit->csv()->download( $file, $headings, $rows );
	}

	/**
	 * The CSV headings and rows for a view.
	 *
	 * @param string               $key  List key.
	 * @param array<string, mixed> $view The view.
	 * @return array{0: list<string>, 1: iterable<array<int|string, mixed>>}
	 */
	public function export_rows( string $key, array $view ): array {
		$def     = $this->list_def( $key );
		$columns = array_filter( $def['fields'], static fn( array $field ): bool => false !== $field['csv'] && ( $field['column'] || null !== $field['csv'] ) );
		$export  = (array) $def['export'];

		// An export with columns of its own (clicks per month): its rows are already cells.
		if ( isset( $export['headings'], $export['rows'] ) && is_callable( $export['headings'] ) && is_callable( $export['rows'] ) ) {
			return array( array_values( array_map( 'strval', (array) $export['headings']( $view ) ) ), $export['rows']( $view ) );
		}

		$items = isset( $export['rows'] ) && is_callable( $export['rows'] ) ? $export['rows']( $view ) : $this->every_item( $def, $view );

		$rows = ( static function () use ( $items, $columns ): \Generator {
			foreach ( $items as $item ) {
				$row = array();
				foreach ( $columns as $field ) {
					$row[] = is_callable( $field['csv'] ) ? ( $field['csv'] )( $item ) : self::value( $field, $item );
				}
				yield $row;
			}
		} )();

		return array( array_values( array_map( static fn( array $field ): string => $field['label'], $columns ) ), $rows );
	}

	/**
	 * The drawer's save handler.
	 *
	 * @param array<string, mixed> $input The posted record and its id.
	 * @return array<string, mixed>
	 */
	public function run_save( array $input ): array {
		$strings = $this->kit->strings();
		$id      = (string) $input['_bwc_id'];
		$record  = '' === $id ? array() : ( $this->drawer['load'] )( $id );

		if ( null === $record ) {
			return array(
				'tone' => 'danger',
				'text' => $strings->get( 'no_longer_exists' ),
			);
		}

		$fields = Form::normalize( (array) $this->drawer['fields'] );
		$posted = (array) $input[ $this->drawer_name() ];
		$values = array_merge( Form::defaults( $fields ), (array) $record );
		$result = Form::sanitize( $fields, 'record', $posted, $values );

		if ( $result['errors'] ) {
			// The drawer reopens and says it; no page notice hidden behind it.
			$this->keep_draft( $id, (array) ( $posted['record'] ?? array() ), $result['errors'], $strings->count( 'record_not_saved', count( $result['errors'] ) ) );

			return array( 'keep_open' => true );
		}

		$saved = ( $this->drawer['save'] )( array_merge( $values, $result['values'] ), '' === $id ? null : $id );

		if ( is_wp_error( $saved ) ) {
			// The plugin can name the field its message is about ( 'field' in the
			// error's data ): it's shown on that field, like the kit's own checks.
			$data  = $saved->get_error_data();
			$field = is_array( $data ) && isset( $data['field'] ) ? (string) $data['field'] : '';

			if ( '' !== $field && in_array( $field, array_map( static fn( array $one ): string => (string) $one['id'], $fields ), true ) ) {
				$this->keep_draft( $id, (array) ( $posted['record'] ?? array() ), array( $field => $saved->get_error_message() ), $strings->count( 'record_not_saved', 1 ) );
			} else {
				$this->keep_draft( $id, (array) ( $posted['record'] ?? array() ), array(), $saved->get_error_message() );
			}

			return array( 'keep_open' => true );
		}

		return array(
			'tone' => 'success',
			'text' => (string) ( $this->drawer['saved_text'] ?? $strings->get( 'record_saved' ) ),
		);
	}

	/**
	 * The drawer's delete handler.
	 *
	 * @param array<string, mixed> $input The record's id.
	 * @return array<string, mixed>
	 */
	public function run_delete( array $input ): array {
		$strings = $this->kit->strings();
		$deleted = '' !== (string) $input['_bwc_id'] && (bool) ( $this->drawer['delete'] )( (string) $input['_bwc_id'] );

		return $deleted ? array(
			'tone' => 'success',
			'text' => (string) ( $this->drawer['deleted_text'] ?? $strings->get( 'record_deleted' ) ),
		) : array(
			'tone'      => 'danger',
			'text'      => $strings->get( 'record_not_deleted' ),
			'keep_open' => true,
		);
	}

	/**
	 * The query callback's result, made safe to use.
	 *
	 * @param array<string, mixed> $def List.
	 * @param array<string, mixed> $view The view.
	 * @return array{items: list<array<string, mixed>>, total: int, counts: array<string, array<string, int>>, totals: array<string, int>}
	 */
	private function run_query( array $def, array $view ): array {
		$result = (array) ( $def['query'] )( $view + array( 'view' => $def['key'] ) );

		return array(
			'items'  => array_values( array_filter( (array) ( $result['items'] ?? array() ), 'is_array' ) ),
			'total'  => max( 0, (int) ( $result['total'] ?? 0 ) ),
			'counts' => (array) ( $result['counts'] ?? array() ),
			'totals' => (array) ( $result['totals'] ?? array() ),
		);
	}

	/**
	 * Every row of a view, page by page, for the export.
	 *
	 * @param array<string, mixed> $def List.
	 * @param array<string, mixed> $view The view.
	 */
	private function every_item( array $def, array $view ): \Generator {
		$view['perPage'] = self::EXPORT_BATCH;
		$view['page']    = 1;

		do {
			$result = $this->run_query( $def, $view );
			foreach ( $result['items'] as $item ) {
				yield $item;
			}
			++$view['page'];
		} while ( $result['items'] && ( $view['page'] - 1 ) * self::EXPORT_BATCH < $result['total'] );
	}

	/**
	 * A field's value for an item: getValue, or the item's key.
	 *
	 * @param array<string, mixed> $field Normalised field.
	 * @param array<string, mixed> $item  Row.
	 */
	public static function value( array $field, array $item ): mixed {
		return null === $field['getValue'] ? ( $item[ $field['id'] ] ?? '' ) : ( $field['getValue'] )( $item );
	}

	/**
	 * A cell's escaped HTML: the field's render, or its value formatted by type.
	 *
	 * @param array<string, mixed> $field Normalised field.
	 * @param array<string, mixed> $item  Row.
	 */
	public static function cell( array $field, array $item ): string {
		if ( null !== $field['render'] ) {
			return (string) ( $field['render'] )( $item );
		}

		$value = self::value( $field, $item );

		$text = match ( $field['type'] ) {
			'integer', 'number' => is_numeric( $value ) ? number_format_i18n( (float) $value, 'number' === $field['type'] && floor( (float) $value ) !== (float) $value ? 2 : 0 ) : '',
			'enum'              => (string) ( $field['elements'][ (string) ( is_scalar( $value ) ? $value : '' ) ] ?? ( is_scalar( $value ) ? $value : '' ) ),
			'boolean'           => $value ? Kit::instance()->strings()->get( 'yes' ) : Kit::instance()->strings()->get( 'no' ),
			'date', 'datetime'  => self::date( $value, 'datetime' === $field['type'] ),
			default             => is_array( $value ) ? implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) : ( is_scalar( $value ) ? (string) $value : '' ),
		};

		return esc_html( $text );
	}

	/**
	 * A date in the site's format and timezone; '' when there is none.
	 *
	 * @param mixed $value Unix time or a date string in the site's timezone.
	 * @param bool  $time  Add the time.
	 */
	private static function date( mixed $value, bool $time ): string {
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$stamp = (int) $value;
		} elseif ( is_string( $value ) && '' !== $value ) {
			$date  = date_create_immutable( $value, wp_timezone() );
			$stamp = false === $date ? 0 : $date->getTimestamp();
		} else {
			$stamp = 0;
		}

		if ( $stamp <= 0 ) {
			return '';
		}

		$format = (string) get_option( 'date_format' ) . ( $time ? ' ' . (string) get_option( 'time_format' ) : '' );

		return (string) wp_date( $format, $stamp );
	}

	/**
	 * Header buttons: the plugin's, Add (drawer) and Export CSV.
	 *
	 * @param string               $key  List key.
	 * @param array<string, mixed> $view The view.
	 */
	private function head_actions( string $key, array $view ): string {
		$def     = $this->list_def( $key );
		$actions = $this->config['actions'] ?? null;
		$html    = is_callable( $actions ) ? (string) $actions( $this, $view ) : '';

		if ( null !== $def['export'] ) {
			$args = ListView::args( $view, $def['defaults'], $def['fields'] );
			unset( $args['paged'] );
			$args['view'] = $key;
			$html        .= Button::markup(
				array(
					'label'   => (string) ( $def['export']['label'] ?? $this->kit->strings()->get( 'export_csv' ) ),
					'url'     => $this->kit->actions()->url( 'export_' . $this->hook(), $args ),
					'variant' => 'outline',
					'icon'    => 'download',
				)
			);
		}

		if ( null !== $this->drawer && ! empty( $this->drawer['add_label'] ) ) {
			$html .= Button::markup(
				array(
					'label'   => (string) $this->drawer['add_label'],
					'url'     => $this->link( $key, $view, array( 'new' => '1' ) ),
					'variant' => 'primary',
				)
			);
		}

		return $html;
	}

	/**
	 * The toolbar: a GET form with search, filters, sort and page size.
	 *
	 * @param string               $key      List key.
	 * @param array<string, mixed> $view     The view.
	 * @param array<string, mixed> $result   The query's result.
	 * @param bool                 $filtered Whether filters or search are on.
	 */
	private function toolbar( string $key, array $view, array $result, bool $filtered ): string {
		$def     = $this->list_def( $key );
		$strings = $this->kit->strings();
		$base    = $this->kit->id() . '-' . $this->id . '-' . $key;
		$hidden  = array();
		$fields  = '';
		$links   = '';
		$args    = ListView::args( $view, $def['defaults'], $def['fields'] );

		// Keep the page slug and anything else the screen's URL carries.
		$url = wp_parse_url( $this->url() );
		parse_str( (string) ( $url['query'] ?? '' ), $page_args );
		foreach ( $page_args as $name => $value ) {
			$hidden[ (string) $name ] = $value;
		}
		if ( array_key_first( $this->lists ) !== $key ) {
			$hidden['view'] = $key;
		}

		if ( null !== $def['search'] ) {
			$fields .= Search::markup(
				array(
					'id'          => $base . '-search',
					'name'        => 'search',
					'value'       => $view['search'],
					'label'       => (string) ( $def['search']['label'] ?? $strings->get( 'search_list' ) ),
					'placeholder' => (string) ( $def['search']['placeholder'] ?? '' ),
				)
			);
		}

		$sortable_column = false;
		foreach ( $def['fields'] as $field ) {
			$sortable_column = $sortable_column || ( $field['enableSorting'] && $field['column'] );
		}

		foreach ( $def['fields'] as $id => $field ) {
			if ( null === $field['filterBy'] ) {
				continue;
			}

			$filter  = ListView::find( $view, $id );
			$current = null === $filter ? '' : ( is_array( $filter['value'] ) ? implode( ',', $filter['value'] ) : ( is_bool( $filter['value'] ) ? ( $filter['value'] ? '1' : '0' ) : (string) $filter['value'] ) );
			$control = $field['filterBy']['control'];
			$counts  = (array) ( $result['counts'][ $id ] ?? array() );
			$all     = '' !== $field['label_all'] ? $field['label_all'] : $strings->get( 'all' );

			// A control shows a value only when the filter picks it ("is"), not when it hides it ("isNot").
			$chosen = null !== $filter && in_array( $filter['operator'], array( 'is', 'isAny' ), true ) ? $current : '';

			if ( 'chips' === $control || 'segmented' === $control ) {
				$items = array(
					'' => array(
						'label' => $all,
						'url'   => $this->link( $key, $this->reset_field( $key, $view, $id ) ),
					),
				);
				// The query may count "All" too, under the empty value.
				if ( isset( $counts[''] ) ) {
					$items['']['count'] = (int) $counts[''];
				}
				foreach ( $field['elements'] as $value => $label ) {
					$items[ (string) $value ] = array(
						'label' => $label,
						'url'   => $this->link( $key, $this->with( $view, $field, (string) $value ) ),
					);
					if ( isset( $counts[ (string) $value ] ) ) {
						$items[ (string) $value ]['count'] = (int) $counts[ (string) $value ];
					}
					if ( 'danger' === ( $field['tones'][ (string) $value ] ?? '' ) ) {
						$items[ (string) $value ]['tone'] = 'danger';
					}
				}
				$links .= 'chips' === $control
					? Chips::markup(
						array(
							'label'   => $field['label'],
							'current' => $chosen,
							'items'   => $items,
						)
					)
					: Segmented::markup(
						array(
							'label'   => $field['label'],
							'current' => $chosen,
							'items'   => $items,
						)
					);

				// Searching keeps this filter: the form carries it.
				if ( isset( $args['filters'][ $id ] ) ) {
					$hidden['filters'][ $id ] = $args['filters'][ $id ];
				}
				continue;
			}

			if ( 'select' === $control ) {
				$fields .= Select::markup(
					array(
						'id'          => $base . '-filter-' . $id,
						'name'        => 'filters[' . $id . ']',
						'label'       => $field['label'],
						'value'       => $chosen,
						'options'     => $this->options( '' !== $field['label_all'] ? $all : $strings->get( 'filter_all', $field['label'] ), $field['elements'], $counts ),
						'auto_submit' => true,
					)
				);
				continue;
			}

			// Checkbox (a boolean): ticked filters to "yes"; the hidden empty value turns it off when unticked.
			// With hide_empty, a box with nothing to show isn't offered (unless it's ticked).
			if ( ! empty( $field['filterBy']['hide_empty'] ) && isset( $result['totals'][ $id ] ) && 0 === (int) $result['totals'][ $id ] && '1' !== $current ) {
				continue;
			}

			$total   = isset( $result['totals'][ $id ] ) ? ' (' . number_format_i18n( (int) $result['totals'][ $id ] ) . ')' : '';
			$fields .= '<label class="bwc-list__check"><input type="hidden" name="filters[' . esc_attr( $id ) . ']" value="">'
				. '<input type="checkbox" name="filters[' . esc_attr( $id ) . ']" value="1"' . checked( '1', $current, false ) . ' data-bwc-auto-submit> '
				. esc_html( $field['label'] . $total ) . '</label>';
		}

		if ( ! $sortable_column ) {
			$options = array();
			foreach ( $def['fields'] as $id => $field ) {
				if ( $field['enableSorting'] ) {
					$options[ $id . ':asc' ]  = $strings->get( 'sort_ascending', $field['label'] );
					$options[ $id . ':desc' ] = $strings->get( 'sort_descending', $field['label'] );
				}
			}
			// The list's own names for the orders it offers ("Most clicks first"), and only those.
			if ( $def['sorts'] ) {
				$options = array_intersect_key( $def['sorts'], $options );
			}
			if ( $options ) {
				$fields .= Select::markup(
					array(
						'id'          => $base . '-sort',
						'name'        => 'sort',
						'label'       => $strings->get( 'sort_by' ),
						'value'       => $view['sort']['field'] . ':' . $view['sort']['direction'],
						'options'     => $options,
						'auto_submit' => true,
					)
				);
			}
		} elseif ( '' !== $view['sort']['field'] && $view['sort'] !== $def['defaults']['sort'] ) {
			$hidden['orderby'] = $view['sort']['field'];
			$hidden['order']   = $view['sort']['direction'];
		}

		if ( count( $def['sizes'] ) > 1 ) {
			$sizes = array();
			foreach ( $def['sizes'] as $size ) {
				$sizes[ (string) $size ] = $strings->get( 'per_page', $size );
			}
			$fields .= Select::markup(
				array(
					'id'          => $base . '-per-page',
					'name'        => 'per_page',
					'label'       => $strings->get( 'rows_per_page' ),
					'value'       => (string) $view['perPage'],
					'options'     => $sizes,
					'auto_submit' => true,
				)
			);
		}

		$fields .= '<button type="submit" class="bwc-button bwc-button--outline" data-bwc-filter-submit><span class="bwc-button__label">' . esc_html( $strings->get( 'filter' ) ) . '</span></button>';

		if ( $filtered ) {
			$fields .= '<a class="bwc-list__clear" href="' . esc_url( $this->link( $key, $this->cleared( $key, $view ) ) ) . '">' . esc_html( $strings->get( 'clear_filters' ) ) . '</a>';
		}

		$inputs = '';
		foreach ( $hidden as $name => $value ) {
			$inputs .= self::hidden_inputs( (string) $name, $value );
		}

		return '<form class="bwc-list__toolbar" method="get" action="' . esc_url( (string) strtok( $this->url(), '?' ) ) . '" role="search" aria-label="' . esc_attr( (string) ( $this->config['title'] ?? $this->label ) ) . '">'
			. $inputs
			. ( '' === $links ? '' : '<div class="bwc-list__filters">' . $links . '</div>' )
			. '<div class="bwc-list__controls">' . $fields . '</div></form>';
	}

	/**
	 * The rows: the table or the grouped list, with bulk actions; or the empty state.
	 *
	 * @param string               $key      List key.
	 * @param array<string, mixed> $view     The view.
	 * @param array<string, mixed> $result   The query's result.
	 * @param bool                 $filtered Whether filters or search are on.
	 */
	private function content( string $key, array $view, array $result, bool $filtered ): string {
		$def     = $this->list_def( $key );
		$strings = $this->kit->strings();

		if ( ! $result['items'] ) {
			return '<div class="bwc-list__empty">' . EmptyState::markup(
				$filtered ? array(
					'text'        => (string) ( $def['empty']['nomatch'] ?? $strings->get( 'nothing_matches' ) ),
					'action_html' => '<a href="' . esc_url( $this->link( $key, $this->cleared( $key, $view ) ) ) . '">' . esc_html( $strings->get( 'clear_filters' ) ) . '</a>',
				) : array( 'text' => (string) ( $def['empty']['none'] ?? $strings->get( 'nothing_to_show' ) ) )
			) . '</div>';
		}

		$columns = array();
		$first   = '';
		foreach ( $def['fields'] as $id => $field ) {
			if ( ! $field['column'] ) {
				continue;
			}
			$first          = '' === $first ? $id : $first;
			$columns[ $id ] = array(
				'label'      => $field['label'],
				'align'      => $field['align'],
				'width'      => $field['width'],
				'hide_label' => $field['hide_label'],
			);
			if ( $field['enableSorting'] ) {
				$columns[ $id ]['sort'] = array(
					'url_asc'  => $this->link(
						$key,
						array(
							'sort' => array(
								'field'     => $id,
								'direction' => 'asc',
							),
							'page' => 1,
						) + $view
					),
					'url_desc' => $this->link(
						$key,
						array(
							'sort' => array(
								'field'     => $id,
								'direction' => 'desc',
							),
							'page' => 1,
						) + $view
					),
				);
				if ( $view['sort']['field'] === $id ) {
					$columns[ $id ]['sort']['current'] = $view['sort']['direction'];
				}
			}
		}

		$rows = array();
		foreach ( $result['items'] as $item ) {
			$cells = array();
			foreach ( array_keys( $columns ) as $id ) {
				$cell = self::cell( $def['fields'][ $id ], $item );
				// The first column opens the side-panel form, unless the plugin draws it.
				if ( $id === $first && null !== $this->drawer && null === $def['fields'][ $id ]['render'] && isset( $item['id'] ) ) {
					$cell = '<a class="bwc-table__primary" href="' . esc_url( $this->drawer_url( (string) $item['id'] ) ) . '">' . $cell . '</a>';
				}
				$cells[ $id ] = $cell;
			}

			$row = array( 'cells' => $cells );
			if ( isset( $item['id'] ) ) {
				$row['id'] = (string) $item['id'];
			}
			if ( isset( $item['_state'] ) && in_array( $item['_state'], array( 'current', 'quiet' ), true ) ) {
				$row['state'] = $item['_state'];
			}
			$rows[] = array( 'item' => $item ) + $row;
		}

		$caption = (string) ( $this->config['title'] ?? $def['label'] );

		if ( 'grouped' === ( $def['layout']['type'] ?? 'table' ) ) {
			$body = $this->grouped( $def, $rows, $columns, $caption, $filtered );
		} else {
			$props = array(
				'caption'   => $caption,
				'min_width' => (string) Html::pick( $def['layout']['min_width'] ?? 'medium', array( 'none', 'medium', 'wide' ), 'medium' ),
				'columns'   => $columns,
				'rows'      => array_map( static fn( array $row ): array => array_diff_key( $row, array( 'item' => true ) ), $rows ),
			);
			if ( $def['bulk'] ) {
				$props['bulk'] = array(
					'name'    => 'ids',
					'label'   => $strings->get( 'bulk_actions' ),
					'actions' => array_map( static fn( array $bulk ): string => (string) ( $bulk['label'] ?? '' ), $def['bulk'] ),
				);
			}
			$body = Table::markup( $props );
		}

		if ( ! $def['bulk'] || 'grouped' === ( $def['layout']['type'] ?? 'table' ) ) {
			return $body;
		}

		return '<form class="bwc-list__bulk" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. $this->kit->actions()->fields( 'bulk_' . $this->hook() )
			. '<input type="hidden" name="_bwc_list" value="' . esc_attr( $key ) . '">'
			. $body . '</form>';
	}

	/**
	 * The grouped layout: rows grouped in the order returned, every group
	 * sharing one column template.
	 *
	 * @param array<string, mixed>                $def     List.
	 * @param list<array<string, mixed>>          $rows     Rows with their items.
	 * @param array<string, array<string, mixed>> $columns  Columns.
	 * @param string                              $caption  Caption.
	 * @param bool                                $filtered Whether filters or search are on.
	 */
	private function grouped( array $def, array $rows, array $columns, string $caption, bool $filtered ): string {
		$layout = $def['layout'];
		$groups = array();

		foreach ( $rows as $row ) {
			$group = is_callable( $layout['group'] ?? null ) ? (array) $layout['group']( $row['item'] ) : array(
				'key'   => '',
				'label' => '',
			);
			$gkey  = (string) ( $group['key'] ?? '' );

			if ( ! isset( $groups[ $gkey ] ) ) {
				$groups[ $gkey ] = array(
					'label' => (string) ( $group['label'] ?? $gkey ),
					'lead'  => array(),
					'rest'  => array(),
					'items' => array(),
				);
			}

			$lead = ! is_callable( $layout['lead'] ?? null ) || (bool) $layout['lead']( $row['item'] );
			$groups[ $gkey ][ $lead ? 'lead' : 'rest' ][] = array( 'cells' => $row['cells'] );
			$groups[ $gkey ]['items'][]                   = $row['item'];
			if ( ! $lead ) {
				$groups[ $gkey ]['rest_items'][] = $row['item'];
			}
		}

		$out = array();
		foreach ( $groups as $group ) {
			$one = array(
				'title' => $group['label'],
				'lead'  => $group['lead'],
			);
			if ( is_callable( $layout['summary'] ?? null ) ) {
				$one['meta'] = (string) $layout['summary']( $group['items'] );
			}
			if ( $group['rest'] ) {
				$one['rest']       = $group['rest'];
				$one['rest_label'] = is_callable( $layout['fold'] ?? null ) ? (string) $layout['fold']( $group['rest_items'] ?? array() ) : $this->kit->strings()->count( 'more_rows', count( $group['rest'] ) );
				$one['open']       = $filtered && 'filtered' === ( $layout['unfold'] ?? 'filtered' );
			}
			$out[] = $one;
		}

		return GroupedList::markup(
			array(
				'caption'   => $caption,
				'columns'   => array_map( static fn( array $column ): array => array_intersect_key( $column, array_flip( array( 'label', 'align', 'width', 'hide_label' ) ) ), $columns ),
				'groups'    => $out,
				'min_width' => 'wide' === ( $layout['min_width'] ?? 'medium' ) ? 'wide' : 'medium',
			)
		);
	}

	/**
	 * The side-panel form, when its parameter is in the URL.
	 *
	 * @param string               $key  List key.
	 * @param array<string, mixed> $view The view.
	 */
	private function drawer_markup( string $key, array $view ): string {
		if ( null === $this->drawer ) {
			return '';
		}

		$param = (string) $this->drawer['param'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- which record to show; saving checks its own nonce.
		$id  = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
		$new = isset( $_GET['new'] ) && '1' === $_GET['new'];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $id && ! $new ) {
			return '';
		}

		$strings = $this->kit->strings();
		$record  = '' === $id ? null : ( $this->drawer['load'] )( $id );
		$close   = $this->link( $key, $view );
		$title   = is_callable( $this->drawer['title'] ?? null ) ? (string) $this->drawer['title']( $record ) : $this->label;
		$alert   = '';
		$draft   = null;
		$fields  = '';

		if ( '' !== $id && null === $record ) {
			$alert = Notice::markup(
				array(
					'tone' => 'warning',
					'text' => $strings->get( 'no_longer_exists' ),
				)
			);
		} else {
			$draft  = $this->take_draft( $id );
			$values = null === $draft ? (array) $record : array_merge( (array) $record, $draft['values'] );

			// Field messages come with the fields' own summary (with a link to each),
			// in the drawer's words; a message about the whole record stands alone.
			if ( null !== $draft && '' !== $draft['message'] && ! $draft['errors'] ) {
				$alert = Notice::markup(
					array(
						'tone' => 'danger',
						'text' => $draft['message'],
					)
				);
			}

			// The plugin's own notices about this record (it's broken, a feature is off): escaped HTML.
			if ( is_callable( $this->drawer['notice'] ?? null ) ) {
				$alert .= (string) $this->drawer['notice']( $record );
			}

			$fields = Form::markup(
				array(
					'fields'       => (array) $this->drawer['fields'],
					'storage'      => new RecordStorage( $this->drawer_name(), $values ),
					'set'          => 'record',
					'errors'       => null === $draft ? array() : $draft['errors'],
					'summary_text' => null === $draft ? '' : $draft['message'],
				)
			);

			if ( null !== $record && is_callable( $this->drawer['aside'] ?? null ) ) {
				$fields .= '<div class="bwc-drawer__aside">' . (string) $this->drawer['aside']( $record ) . '</div>';
			}
		}

		$editable = '' === $id || null !== $record;
		$delete   = '';
		if ( null !== $record && $this->kit->actions()->has( 'drawer_delete_' . $this->hook() ) ) {
			$delete = $this->kit->actions()->button(
				'drawer_delete_' . $this->hook(),
				(string) ( $this->drawer['delete_label'] ?? $strings->get( 'delete' ) ),
				array(
					'inside_form' => true,
					'input'       => array( '_bwc_id' => $id ),
					'variant'     => 'danger',
				)
			);
		}

		return Drawer::markup(
			array(
				'id'                => $this->kit->id() . '-' . $this->id . '-drawer',
				'title'             => $title,
				'close_url'         => $close,
				'form'              => array(
					'action'      => admin_url( 'admin-post.php' ),
					'fields_html' => $fields,
					'hidden_html' => $editable ? $this->kit->actions()->fields( 'drawer_' . $this->hook() ) . '<input type="hidden" name="_bwc_id" value="' . esc_attr( $id ) . '">' : '',
				),
				'alert_html'        => $alert,
				'unsaved'           => null !== $draft,
				'footer_start_html' => $delete,
				'footer_end_html'   => '<a class="bwc-button bwc-button--outline" href="' . esc_url( $close ) . '" data-bwc-drawer-close><span class="bwc-button__label">' . esc_html( $strings->get( 'cancel' ) ) . '</span></a>'
					. ( $editable ? '<button type="submit" class="bwc-button bwc-button--primary"><span class="bwc-button__label">' . esc_html( (string) ( $this->drawer['save_label'] ?? $strings->get( 'save' ) ) ) . '</span></button>' : '' ),
			)
		);
	}

	/**
	 * Base of the drawer's input names: "{hook}_bwc_drawer".
	 */
	private function drawer_name(): string {
		return $this->kit->name( 'drawer' );
	}

	/**
	 * Keeps what was typed after a failed save, for the drawer that reopens.
	 *
	 * @param string                $id      Record id, '' for a new one.
	 * @param array<string, mixed>  $values  Posted values.
	 * @param array<string, string> $errors  Field errors.
	 * @param string                $message A message for the whole record.
	 */
	private function keep_draft( string $id, array $values, array $errors, string $message = '' ): void {
		set_transient(
			$this->kit->name( 'drawer_' . $this->hook() . '_' . get_current_user_id() ),
			array(
				'id'      => $id,
				'values'  => $values,
				'errors'  => $errors,
				'message' => $message,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * The draft for a record, removed once read; null when there is none.
	 *
	 * @param string $id Record id, '' for a new one.
	 * @return array{values: array<string, mixed>, errors: array<string, string>, message: string}|null
	 */
	private function take_draft( string $id ): ?array {
		$name  = $this->kit->name( 'drawer_' . $this->hook() . '_' . get_current_user_id() );
		$draft = get_transient( $name );

		if ( ! is_array( $draft ) || (string) ( $draft['id'] ?? '' ) !== $id ) {
			return null;
		}

		delete_transient( $name );

		return array(
			'values'  => (array) ( $draft['values'] ?? array() ),
			'errors'  => array_map( 'strval', (array) ( $draft['errors'] ?? array() ) ),
			'message' => (string) ( $draft['message'] ?? '' ),
		);
	}

	/**
	 * The view with one field's filter set to a value.
	 *
	 * @param array<string, mixed> $view  The view.
	 * @param array<string, mixed> $field Normalised field.
	 * @param string               $value Value.
	 * @return array<string, mixed>
	 */
	private function with( array $view, array $field, string $value ): array {
		$view              = $this->without( $view, $field['id'] );
		$view['filters'][] = array(
			'field'    => $field['id'],
			'operator' => $field['filterBy']['operators'][0],
			'value'    => $value,
		);
		$view['page']      = 1;

		return $view;
	}

	/**
	 * The view with one field's filter back to its default (or none), page one.
	 *
	 * @param string               $key   List key.
	 * @param array<string, mixed> $view  The view.
	 * @param string               $field Field id.
	 * @return array<string, mixed>
	 */
	private function reset_field( string $key, array $view, string $field ): array {
		$view    = $this->without( $view, $field );
		$default = ListView::find( $this->list_def( $key )['defaults'], $field );

		// A default that hides something ("not old") stays under "All"; one
		// that picks a value ("Active") is what "All" turns off.
		if ( null !== $default && ! in_array( $default['operator'], array( 'is', 'isAny' ), true ) ) {
			$view['filters'][] = $default;
		}

		return $view;
	}

	/**
	 * The view without a field's filter, back on page one.
	 *
	 * @param array<string, mixed> $view  The view.
	 * @param string               $field Field id.
	 * @return array<string, mixed>
	 */
	private function without( array $view, string $field ): array {
		$view['filters'] = array_values( array_filter( $view['filters'], static fn( array $filter ): bool => $filter['field'] !== $field ) );
		$view['page']    = 1;

		return $view;
	}

	/**
	 * The view with search and filters back to the defaults; sort and page size kept.
	 *
	 * @param string               $key  List key.
	 * @param array<string, mixed> $view The view.
	 * @return array<string, mixed>
	 */
	private function cleared( string $key, array $view ): array {
		$defaults = $this->list_def( $key )['defaults'];

		return array(
			'search'  => $defaults['search'],
			'filters' => $defaults['filters'],
			'page'    => 1,
		) + $view;
	}

	/**
	 * Select options: "All", then each element with its count.
	 *
	 * @param string                $all      Label of the "no filter" option.
	 * @param array<string, string> $elements Value => label.
	 * @param array<string, int>    $counts   Value => rows.
	 * @return array<string, string>
	 */
	private function options( string $all, array $elements, array $counts ): array {
		$options = array( '' => $all );
		foreach ( $elements as $value => $label ) {
			$options[ (string) $value ] = isset( $counts[ (string) $value ] ) ? $label . ' (' . number_format_i18n( (int) $counts[ (string) $value ] ) . ')' : $label;
		}

		return $options;
	}
}
