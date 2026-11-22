<?php
/**
 * The report screen: tabs of tiles, charts, tables and notes.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Component\Button;
use BlissWebConcept\AdminKit\Component\Chips;
use BlissWebConcept\AdminKit\Component\Freshness;
use BlissWebConcept\AdminKit\Component\Segmented;
use BlissWebConcept\AdminKit\Component\Select;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Shell\Nav;
use BlissWebConcept\AdminKit\Shell\PageHead;

defined( 'ABSPATH' ) || exit;

/**
 * ReportScreen. Each tab's `blocks` callback
 * gets the view and returns typed blocks (Blocks); the kit draws them, the
 * tab's filters, the header actions and the freshness line.
 *
 * URL: `tab`, the tab's filters (as in ListScreen), the `persist` params, and
 * a tab's detail parameter.
 */
final class ReportScreen extends BaseScreen {

	/**
	 * Tabs by key, the first is the default.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $tabs = array();

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config id, label, title, intro, tabs, persist, data, header_actions, capability.
	 */
	public function __construct( Kit $kit, array $config ) {
		parent::__construct( $kit, $config );

		foreach ( (array) ( $config['tabs'] ?? array() ) as $key => $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			$fields = ListView::fields( (array) ( $tab['filters'] ?? array() ) );

			$this->tabs[ sanitize_key( (string) $key ) ] = array(
				'label'    => (string) ( $tab['label'] ?? $key ),
				'blocks'   => isset( $tab['blocks'] ) && is_callable( $tab['blocks'] ) ? $tab['blocks'] : null,
				'fields'   => $fields,
				'defaults' => ListView::defaults( (array) ( $tab['view'] ?? array() ), $fields, array( 20 ) ),
				'export'   => isset( $tab['export'] ) && is_array( $tab['export'] ) ? $tab['export'] : null,
				'detail'   => ( $tab['detail'] ?? null ) instanceof DetailScreen ? $tab['detail'] : null,
			);
		}
	}

	/**
	 * Registers the export action, when a tab has one.
	 */
	public function register(): void {
		if ( ! array_filter( $this->tabs, static fn( array $tab ): bool => null !== $tab['export'] ) ) {
			return;
		}

		$input = array(
			'tab'     => 'key',
			'search'  => 'text',
			'filters' => 'array',
		);
		foreach ( $this->persisted_names() as $name ) {
			$input[ $name ] = 'text';
		}

		$this->kit->actions()->register(
			'export_' . $this->hook(),
			array( $this, 'run_export' ),
			array(
				'capability' => $this->capability(),
				'method'     => 'get',
				'input'      => $input,
			)
		);
	}

	/**
	 * The tab this request shows: ?tab=, or the first.
	 */
	public function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which tab to show; nothing is changed.
		$key = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		return isset( $this->tabs[ $key ] ) ? $key : (string) array_key_first( $this->tabs );
	}

	/**
	 * The view: the tab's filters from the URL, the tab, and the persisted params.
	 *
	 * @param string               $tab  Tab key.
	 * @param array<string, mixed> $args Query arguments; the request's when null.
	 * @return array<string, mixed>
	 */
	public function view( string $tab, ?array $args = null ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the view is read-only state in the URL; ListView checks every part.
		$args = null === $args ? (array) wp_unslash( $_GET ) : $args;
		$def  = $this->tabs[ $tab ] ?? reset( $this->tabs );
		$view = ListView::parse( $args, $def['fields'], $def['defaults'], array( 20 ) );

		$view['tab']     = $tab;
		$view['persist'] = array();
		foreach ( $this->persisted_names() as $name ) {
			if ( isset( $args[ $name ] ) && is_scalar( $args[ $name ] ) && '' !== (string) $args[ $name ] ) {
				$view['persist'][ $name ] = sanitize_text_field( (string) $args[ $name ] );
			}
		}

		return $view;
	}

	/**
	 * Link to a tab with a view, keeping the persisted params; null drops an argument.
	 *
	 * @param array<string, mixed>       $view    The view.
	 * @param array<string, scalar|null> $changes Arguments to add or (null) remove.
	 */
	public function link( array $view, array $changes = array() ): string {
		$tab  = (string) ( $view['tab'] ?? $this->current_tab() );
		$def  = $this->tabs[ $tab ] ?? reset( $this->tabs );
		$args = ListView::args( $view, $def['defaults'], $def['fields'] ) + (array) ( $view['persist'] ?? array() );

		if ( array_key_first( $this->tabs ) !== $tab ) {
			$args['tab'] = $tab;
		}

		return $this->url( array_merge( $args, $changes ) );
	}

	/**
	 * The screen's body.
	 */
	public function render(): string {
		if ( ! $this->tabs ) {
			return '';
		}

		$tab  = $this->current_tab();
		$def  = $this->tabs[ $tab ];
		$view = $this->view( $tab );
		$url  = function ( array $changes ) use ( $view ): string {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- links keep the open record and pages; nothing is changed.
			$keep = array_diff_key( array_map( static fn( $value ) => is_scalar( $value ) ? sanitize_text_field( (string) $value ) : null, (array) wp_unslash( $_GET ) ), array_flip( array( 'page', 'tab', 'search', 'filters', 'orderby', 'order', 'paged', 'per_page' ) ) );
			return $this->link( $view, array_merge( array_filter( $keep, static fn( $value ): bool => null !== $value ), $changes ) );
		};

		$detail = $def['detail'];
		$id     = null === $detail ? null : $detail->requested();

		if ( null !== $detail && null !== $id ) {
			$body = $detail->render( $id, $view, $url );
		} else {
			$blocks = null === $def['blocks'] ? array() : (array) ( $def['blocks'] )( $view );
			$body   = $this->toolbar( $view ) . '<div class="bwc-report__body">' . Blocks::render( array_values( array_filter( $blocks, 'is_array' ) ), $url ) . '</div>';
		}

		$nav = '';
		if ( count( $this->tabs ) > 1 ) {
			$items = array();
			foreach ( $this->tabs as $key => $one ) {
				$items[ $key ] = array(
					'label' => $one['label'],
					'url'   => $this->link(
						array(
							'tab'     => $key,
							'persist' => $view['persist'],
						) + $one['defaults']
					),
				);
			}
			$nav = '<div class="bwc-report__tabs">' . Nav::markup(
				array(
					'label'   => (string) ( $this->config['title'] ?? $this->label ),
					'current' => $tab,
					'items'   => $items,
				)
			) . '</div>';
		}

		// The header comes first, then the report tabs; a record opened in place has its own title.
		$head = null !== $detail && null !== $id ? '' : $this->head( $view );

		return '<div class="bwc-report">' . $head . $nav . $body . $this->freshness() . '</div>';
	}

	/**
	 * The export action's handler: the tab's rows as CSV. Ends the request.
	 *
	 * @param array<string, mixed> $input The view's URL arguments.
	 */
	public function run_export( array $input ): void {
		$tab    = isset( $this->tabs[ (string) $input['tab'] ] ) ? (string) $input['tab'] : (string) array_key_first( $this->tabs );
		$export = (array) $this->tabs[ $tab ]['export'];
		$view   = $this->view( $tab, $input );
		$fields = ListView::fields( (array) ( $export['fields'] ?? array() ) );
		$items  = is_callable( $export['rows'] ?? null ) ? $export['rows']( $view ) : array();
		$file   = is_callable( $export['filename'] ?? null ) ? (string) $export['filename']( $view ) : $this->kit->id() . '-' . $this->id . '-' . $tab . '-' . wp_date( 'Y-m-d' );

		$rows = ( static function () use ( $items, $fields ): \Generator {
			foreach ( $items as $item ) {
				$row = array();
				foreach ( $fields as $field ) {
					$row[] = is_callable( $field['csv'] ) ? ( $field['csv'] )( $item ) : ListScreen::value( $field, (array) $item );
				}
				yield $row;
			}
		} )();

		$this->kit->csv()->download( $file, array_values( array_map( static fn( array $field ): string => $field['label'], $fields ) ), $rows );
	}

	/**
	 * Title, intro and header actions (the plugin's, then Export).
	 *
	 * @param array<string, mixed> $view The view.
	 */
	private function head( array $view ): string {
		$actions = is_callable( $this->config['header_actions'] ?? null ) ? (string) $this->config['header_actions']( $view, $this ) : '';
		$export  = $this->tabs[ $view['tab'] ]['export'];

		if ( null !== $export ) {
			$def         = $this->tabs[ $view['tab'] ];
			$args        = ListView::args( $view, $def['defaults'], $def['fields'] ) + $view['persist'];
			$args['tab'] = $view['tab'];
			$actions    .= Button::markup(
				array(
					'label'   => (string) ( $export['label'] ?? $this->kit->strings()->get( 'export_csv' ) ),
					'url'     => $this->kit->actions()->url( 'export_' . $this->hook(), $args ),
					'variant' => 'outline',
					'icon'    => 'download',
				)
			);
		}

		if ( empty( $this->config['title'] ) && '' === $actions ) {
			return '';
		}

		return PageHead::markup(
			array(
				'title'        => (string) ( $this->config['title'] ?? $this->tabs[ $view['tab'] ]['label'] ),
				'description'  => (string) ( $this->config['intro'] ?? '' ),
				'actions_html' => $actions,
			)
		);
	}

	/**
	 * The tab's filters: chips and segmented controls as links, selects that
	 * submit themselves (a Filter button without scripts).
	 *
	 * @param array<string, mixed> $view The view.
	 */
	private function toolbar( array $view ): string {
		$def     = $this->tabs[ $view['tab'] ];
		$strings = $this->kit->strings();
		$links   = '';
		$selects = '';
		$in_form = array();

		foreach ( $def['fields'] as $id => $field ) {
			if ( null === $field['filterBy'] ) {
				continue;
			}

			$filter  = ListView::find( $view, $id );
			$current = null !== $filter && in_array( $filter['operator'], array( 'is', 'isAny' ), true ) && is_scalar( $filter['value'] ) ? (string) $filter['value'] : '';
			$control = $field['filterBy']['control'];

			if ( 'select' === $control ) {
				$in_form[] = $id;
				$selects  .= Select::markup(
					array(
						'id'          => $this->kit->id() . '-' . $this->id . '-filter-' . $id,
						'name'        => 'filters[' . $id . ']',
						'label'       => $field['label'],
						'value'       => $current,
						'options'     => array( '' => '' !== $field['label_all'] ? $field['label_all'] : $strings->get( 'filter_all', $field['label'] ) ) + $field['elements'],
						'auto_submit' => true,
					)
				);
				continue;
			}

			$without = $view;

			$without['filters'] = array_values( array_filter( $view['filters'], static fn( array $one ): bool => $one['field'] !== $id ) );

			$items = array(
				'' => array(
					'label' => '' !== $field['label_all'] ? $field['label_all'] : $strings->get( 'all' ),
					'url'   => $this->link( $without ),
				),
			);
			foreach ( $field['elements'] as $value => $label ) {
				$with                     = $without;
				$with['filters'][]        = array(
					'field'    => $id,
					'operator' => $field['filterBy']['operators'][0],
					'value'    => (string) $value,
				);
				$items[ (string) $value ] = array(
					'label' => $label,
					'url'   => $this->link( $with ),
				);
			}

			$props  = array(
				'label'   => $field['label'],
				'current' => $current,
				'items'   => $items,
			);
			$links .= 'segmented' === $control ? Segmented::markup( $props ) : Chips::markup( $props );
		}

		if ( '' === $links && '' === $selects ) {
			return '';
		}

		$form = '';
		if ( '' !== $selects ) {
			$url = wp_parse_url( $this->link( $view ) );
			parse_str( (string) ( $url['query'] ?? '' ), $hidden );
			$inputs = '';
			foreach ( $hidden as $name => $value ) {
				if ( 'filters' !== $name && is_scalar( $value ) ) {
					$inputs .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '">';
				}
			}
			// Filters chosen with chips or segments ride along, so changing a select keeps them.
			foreach ( (array) ( $hidden['filters'] ?? array() ) as $name => $value ) {
				if ( ! in_array( (string) $name, $in_form, true ) ) {
					$inputs .= self::hidden_inputs( 'filters[' . $name . ']', $value );
				}
			}
			$form = '<form class="bwc-report__filters" method="get" action="' . esc_url( (string) strtok( $this->link( $view ), '?' ) ) . '" role="search" aria-label="' . esc_attr( $strings->get( 'filter' ) ) . '">' . $inputs . $selects
				. '<button type="submit" class="bwc-button bwc-button--outline" data-bwc-filter-submit><span class="bwc-button__label">' . esc_html( $strings->get( 'filter' ) ) . '</span></button></form>';
		}

		return '<div class="bwc-report__toolbar">' . $links . $form . '</div>';
	}

	/**
	 * "Data read 4 minutes ago · Refresh data", for the Cache entries the screen names.
	 */
	private function freshness(): string {
		$names = array_values( array_filter( array_map( 'strval', (array) ( $this->config['data'] ?? array() ) ) ) );
		if ( ! $names ) {
			return '';
		}

		$cache = $this->kit->cache();
		$built = array_filter( array_map( static fn( string $name ): ?int => $cache->built( $name ), $names ) );

		return Freshness::markup(
			array(
				'built'        => $built ? min( $built ) : null,
				'refresh_html' => $cache->refresh_button( $names ),
			)
		);
	}

	/**
	 * Params carried on every link (a team switch, a date range).
	 *
	 * @return list<string>
	 */
	private function persisted_names(): array {
		return array_values( array_filter( array_map( 'sanitize_key', array_map( 'strval', (array) ( $this->config['persist'] ?? array() ) ) ) ) );
	}
}
