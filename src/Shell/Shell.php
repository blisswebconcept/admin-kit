<?php
/**
 * The plugin's admin pages: menu, header and tabs around its screens.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Shell;

use BlissWebConcept\AdminKit\Component\Icon;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Screen\BaseScreen;

defined( 'ABSPATH' ) || exit;

/**
 * Shell. Registers a page per screen, loads the kit's
 * assets there, and draws the Screen frame: header bar with the screens as
 * tabs, the notices, then the current screen's body.
 *
 * Under a top-level menu every visible screen is a submenu item. Under
 * another menu (Settings, or another plugin's page), only the first screen
 * is a menu item and the others are reached through the tabs.
 */
final class Shell {

	/**
	 * Menu settings: title, menu_title, icon, position, parent, slug.
	 *
	 * @var array{title: string, menu_title: string, icon: string, position: ?int, parent: ?string, slug: string}
	 */
	private array $menu;

	/**
	 * Header settings: title, subtitle, version, icon_html, actions.
	 *
	 * @var array<string, mixed>
	 */
	private array $header;

	/**
	 * Screens in tab order, by id.
	 *
	 * @var array<string, BaseScreen>
	 */
	private array $screens = array();

	/**
	 * Show the screens as tabs.
	 */
	private bool $tabs;

	/**
	 * Page slugs of the screens left out of the menu, by screen id.
	 *
	 * @var array<string, string>
	 */
	private array $off_menu = array();

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config menu, header, screens, tabs.
	 * @throws \InvalidArgumentException When there's no menu slug or no screen.
	 */
	public function __construct( private Kit $kit, array $config ) {
		$menu = (array) ( $config['menu'] ?? array() );
		$slug = isset( $menu['slug'] ) ? sanitize_key( (string) $menu['slug'] ) : '';

		if ( '' === $slug ) {
			throw new \InvalidArgumentException( 'Admin Kit Shell: "menu.slug" is required.' );
		}

		$title      = (string) ( $menu['title'] ?? $slug );
		$this->menu = array(
			'title'      => $title,
			'menu_title' => (string) ( $menu['menu_title'] ?? $title ),
			'icon'       => (string) ( $menu['icon'] ?? '' ),
			'position'   => isset( $menu['position'] ) ? (int) $menu['position'] : null,
			'parent'     => empty( $menu['parent'] ) ? null : (string) $menu['parent'],
			'slug'       => $slug,
		);

		$this->header = (array) ( $config['header'] ?? array() ) + array( 'title' => $title );
		$this->tabs   = (bool) ( $config['tabs'] ?? true );

		foreach ( (array) ( $config['screens'] ?? array() ) as $screen ) {
			if ( $screen instanceof BaseScreen ) {
				$this->screens[ $screen->id() ] = $screen;
				$screen->attach( $this );
			}
		}

		if ( ! $this->screens ) {
			throw new \InvalidArgumentException( 'Admin Kit Shell: "screens" needs at least one screen.' );
		}
	}

	/**
	 * Hooks the menu.
	 */
	public function register(): void {
		if ( did_action( 'admin_menu' ) ) {
			$this->menu();
			return;
		}

		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * The screens, by id.
	 *
	 * @return array<string, BaseScreen>
	 */
	public function screens(): array {
		return $this->screens;
	}

	/**
	 * A screen's page slug: the menu slug for the first, "{slug}-{id}" for the rest.
	 *
	 * @param string $id Screen id.
	 */
	public function slug( string $id ): string {
		return array_key_first( $this->screens ) === $id ? $this->menu['slug'] : $this->menu['slug'] . '-' . $id;
	}

	/**
	 * A screen's address.
	 *
	 * @param string $id Screen id.
	 */
	public function url( string $id ): string {
		$parent = $this->menu['parent'];
		$base   = null !== $parent && str_ends_with( $parent, '.php' ) ? $parent : 'admin.php';

		return add_query_arg( 'page', $this->slug( $id ), admin_url( $base ) );
	}

	/**
	 * Registers a page per screen and loads the kit's assets on them.
	 */
	public function menu(): void {
		$render = array( $this, 'render' );
		$first  = reset( $this->screens );
		$hooks  = array();
		$parent = $this->menu['parent'] ?? $this->menu['slug'];

		if ( null === $this->menu['parent'] ) {
			$hooks[] = (string) add_menu_page( $this->menu['title'], $this->menu['menu_title'], $first->capability(), $this->menu['slug'], $render, $this->menu['icon'], $this->menu['position'] );
		}

		foreach ( $this->screens as $id => $screen ) {
			$first_one = $screen === $first;
			// Under another menu only the first screen is an item; the others are reached by the tabs.
			$listed = ! $screen->hidden() && ( null === $this->menu['parent'] || $first_one );

			if ( ! $listed ) {
				/*
				 * A real menu item with WordPress's "hidden" class: the page works
				 * and keeps its title and open menu. (Removing the item instead
				 * leaves a page WordPress refuses: "The requested page does not
				 * exist"; a page with no parent loses its menu and title.)
				 */
				$hooks[]               = (string) add_submenu_page( $parent, $this->menu['title'], $screen->label(), $screen->capability(), $this->slug( $id ), $render );
				$this->off_menu[ $id ] = $this->slug( $id );
				$this->hide_item( $parent, $this->slug( $id ) );
				continue;
			}

			$label   = null !== $this->menu['parent'] && $first_one ? $this->menu['menu_title'] : $screen->label();
			$hooks[] = (string) add_submenu_page( $parent, $this->menu['title'], $label, $screen->capability(), $this->slug( $id ), $render, null !== $this->menu['parent'] && $first_one ? $this->menu['position'] : null );
		}

		if ( $this->off_menu ) {
			// On a page left out of the menu, the first screen's item shows as current.
			add_filter( 'submenu_file', fn( $file ) => $this->is_off_menu() ? $this->slug( (string) array_key_first( $this->screens ) ) : $file );
		}

		$this->kit->assets()->load_on( ...array_filter( $hooks ) );
	}

	/**
	 * Adds WordPress's "hidden" class to a submenu item.
	 *
	 * @param string $menu Parent menu slug.
	 * @param string $slug The item's page slug.
	 */
	private function hide_item( string $menu, string $slug ): void {
		global $submenu;

		foreach ( (array) ( $submenu[ $menu ] ?? array() ) as $index => $item ) {
			if ( ( $item[2] ?? '' ) === $slug ) {
				$submenu[ $menu ][ $index ][4] = trim( ( $item[4] ?? '' ) . ' hidden' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- adds a class to the plugin's own menu item.
			}
		}
	}

	/**
	 * Whether this request shows one of the screens left out of the menu.
	 */
	private function is_off_menu(): bool {
		$page = isset( $GLOBALS['plugin_page'] ) ? (string) $GLOBALS['plugin_page'] : '';

		return '' !== $page && in_array( $page, $this->off_menu, true );
	}

	/**
	 * The screen this request shows.
	 */
	public function current(): BaseScreen {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which page to show; nothing is changed.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		foreach ( $this->screens as $id => $screen ) {
			if ( $this->slug( $id ) === $page ) {
				return $screen;
			}
		}

		return reset( $this->screens );
	}

	/**
	 * Prints the current screen in the frame.
	 */
	public function render(): void {
		echo $this->markup( $this->current() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in markup().
	}

	/**
	 * A screen in the frame: header, tabs, notices, body.
	 *
	 * @param BaseScreen $screen The screen.
	 */
	public function markup( BaseScreen $screen ): string {
		if ( ! $screen->can() ) {
			return '<div class="wrap"><p>' . esc_html( $this->kit->strings()->get( 'action_not_allowed' ) ) . '</p></div>';
		}

		$items = array();
		foreach ( $this->screens as $id => $one ) {
			if ( $one->hidden() || ! $one->can() ) {
				continue;
			}

			$item  = array(
				'label' => $one->label(),
				'url'   => $this->url( $id ),
			);
			$badge = $one->badge();
			if ( null !== $badge ) {
				$item['badge']      = $badge['count'];
				$item['badge_tone'] = $badge['tone'];
			}
			$items[ $id ] = $item;
		}

		$actions = $this->header['actions'] ?? null;
		$props   = array(
			'title'        => (string) $this->header['title'],
			'icon_html'    => (string) ( $this->header['icon_html'] ?? Icon::markup(
				array(
					'name' => 'settings',
					'size' => 20,
				)
			) ),
			'content_html' => $screen->render(),
		);

		foreach ( array( 'subtitle', 'version' ) as $key ) {
			if ( ! empty( $this->header[ $key ] ) ) {
				$props[ $key ] = (string) $this->header[ $key ];
			}
		}

		if ( is_callable( $actions ) ) {
			$props['actions_html'] = (string) $actions( $screen );
		}

		if ( $this->tabs && count( $items ) > 1 ) {
			$props['nav'] = array(
				'label'   => $this->kit->strings()->get( 'sections_label', (string) $this->header['title'] ),
				'current' => $screen->id(),
				'items'   => $items,
			);
		}

		return Screen::markup( $props );
	}
}
