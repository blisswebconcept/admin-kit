<?php
/**
 * The settings screen: groups of sections of fields.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Component\Panel;
use BlissWebConcept\AdminKit\Component\Pill;
use BlissWebConcept\AdminKit\Component\Search;
use BlissWebConcept\AdminKit\Component\Toggle;
use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Shell\Nav;
use BlissWebConcept\AdminKit\Storage\OptionStorage;

defined( 'ABSPATH' ) || exit;

/**
 * SettingsScreen.
 *
 * A field's storage path is "{section}.{id}" unless it sets "path"; the
 * first part is the option bucket, the second the key. A section switch and
 * the danger checkbox are fields too, so they save, sanitize and show in the
 * REST schema like the others.
 *
 * The whole screen is one form posted to the kit's save action; every group
 * is in it, and the script shows one at a time.
 *
 * @phpstan-type Section array{
 *     key: string,
 *     group: string,
 *     label: string,
 *     description: string,
 *     switch: array{bucket: string, key: string, default: bool}|null,
 *     requires: list<array<string, mixed>>,
 *     link: array{label: string, url: string}|null,
 *     fields: list<array{bucket: string, field: array<string, mixed>}>,
 *     reference: callable|null,
 *     reference_label: string,
 *     render: callable|null,
 *     danger: bool,
 * }
 */
final class SettingsScreen extends BaseScreen {

	/**
	 * Option name.
	 */
	private string $option;

	/**
	 * "side", "tabs" or "none".
	 */
	private string $navigation;

	/**
	 * Show the "Find a setting" box.
	 */
	private bool $search;

	/**
	 * Groups: key => label and intro, in order.
	 *
	 * @var array<string, array{label: string, intro: string}>
	 */
	private array $groups = array();

	/**
	 * Sections by key, in order.
	 *
	 * @var array<string, Section>
	 */
	private array $sections = array();

	/**
	 * Field descriptions by bucket, for the storage.
	 *
	 * @var array<string, list<array<string, mixed>>>
	 */
	private array $sets = array();

	/**
	 * Field labels by "{bucket}.{key}", for the error links.
	 *
	 * @var array<string, string>
	 */
	private array $labels = array();

	/**
	 * Where the values live.
	 */
	private OptionStorage $storage;

	/**
	 * Sections being checked by enabled(), so requirements can't loop.
	 *
	 * @var array<string, true>
	 */
	private array $checking = array();

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config id, label, option, autoload, migrations, navigation, search, intro, groups, danger, capability.
	 * @throws \InvalidArgumentException When the option is missing.
	 */
	public function __construct( Kit $kit, array $config ) {
		parent::__construct( $kit, $config );

		$this->option = isset( $config['option'] ) ? (string) $config['option'] : '';
		if ( '' === $this->option ) {
			throw new \InvalidArgumentException( 'Admin Kit SettingsScreen: "option" (the option name) is required.' );
		}

		/**
		 * Filters a settings screen's groups, so other code can add a group, a
		 * section or fields. The kit renders, sanitizes and saves them like its own.
		 *
		 * @param array<string, array<string, mixed>> $groups Group key => label, intro, sections.
		 * @param SettingsScreen                      $screen The screen.
		 */
		$groups = (array) apply_filters( $kit->name( 'settings_' . $this->hook() . '_groups' ), (array) ( $config['groups'] ?? array() ), $this );

		$this->read_groups( $groups );

		if ( ! empty( $config['danger'] ) && is_array( $config['danger'] ) && $this->groups ) {
			$this->add_danger( $config['danger'] );
		}

		$navigation       = (string) ( $config['navigation'] ?? 'side' );
		$this->navigation = count( $this->groups ) < 2 ? 'none' : ( in_array( $navigation, array( 'side', 'tabs', 'none' ), true ) ? $navigation : 'side' );
		$this->search     = (bool) ( $config['search'] ?? count( $this->groups ) >= 3 );

		$this->storage = new OptionStorage(
			$kit,
			$this->option,
			$this->sets,
			$this->option,
			(bool) ( $config['autoload'] ?? true ),
			(array) ( $config['migrations'] ?? array() ),
			$this->capability,
			$this->id
		);
	}

	/**
	 * Registers the setting and the save action.
	 */
	public function register(): void {
		$this->storage->register();

		$this->kit->actions()->register(
			$this->action(),
			array( $this, 'save' ),
			array(
				'capability' => $this->capability(),
				'input'      => array(
					$this->option => 'array',
					'_bwc_group'  => 'key',
				),
				'redirect'   => function ( array $input, mixed $result, string $back ): string {
					$group = (string) $input['_bwc_group'];
					$arg   = 'tabs' === $this->navigation ? 'tab' : 'group';

					return isset( $this->groups[ $group ] ) ? add_query_arg( $arg, $group, $back ) : remove_query_arg( $arg, $back );
				},
			)
		);
	}

	/**
	 * Name of the save action: "save_{screen}".
	 */
	public function action(): string {
		return 'save_' . $this->hook();
	}

	/**
	 * The storage, for code that reads values by bucket.
	 */
	public function storage(): OptionStorage {
		return $this->storage;
	}

	/**
	 * A value for the plugin's code: the stored one or the field's default.
	 *
	 * @param string $path "{bucket}.{key}", such as "expiry.age_days".
	 */
	public function get( string $path ): mixed {
		[ $bucket, $key ] = array_pad( explode( '.', $path, 2 ), 2, '' );

		return $this->storage->get( $bucket, $key );
	}

	/**
	 * Whether a section is switched on and its requirements are met. A
	 * section without a switch counts as on.
	 *
	 * @param string $section Section key.
	 */
	public function enabled( string $section ): bool {
		return $this->switched_on( $section ) && ! $this->reasons( $section );
	}

	/**
	 * Whether a section's switch is on (true when it has none).
	 *
	 * @param string $section Section key.
	 */
	public function switched_on( string $section ): bool {
		$switch = $this->sections[ $section ]['switch'] ?? null;
		if ( ! isset( $this->sections[ $section ] ) ) {
			return false;
		}

		return null === $switch || (bool) $this->storage->get( $switch['bucket'], $switch['key'] );
	}

	/**
	 * Why a switched-on section won't run: its unmet requirements.
	 *
	 * @param string $section Section key.
	 * @return list<string>
	 */
	public function reasons( string $section ): array {
		if ( ! isset( $this->sections[ $section ] ) || isset( $this->checking[ $section ] ) ) {
			return array();
		}

		$this->checking[ $section ] = true;
		$reasons                    = array();

		foreach ( $this->sections[ $section ]['requires'] as $need ) {
			if ( isset( $need['section'] ) ) {
				$other = (string) $need['section'];
				if ( ! $this->enabled( $other ) ) {
					$reasons[] = isset( $need['reason'] ) ? (string) $need['reason'] : $this->kit->strings()->get( 'requires_section', $this->sections[ $other ]['label'] ?? $other );
				}
			} elseif ( isset( $need['check'] ) && is_callable( $need['check'] ) ) {
				$reason = $need['check']();
				if ( is_string( $reason ) && '' !== $reason ) {
					$reasons[] = $reason;
				}
			}
		}

		unset( $this->checking[ $section ] );

		return $reasons;
	}

	/**
	 * The save action's handler: saves, keeps the field messages for the
	 * page after the redirect, tells other code, and says how it went.
	 *
	 * @param array<string, mixed> $input The declared input.
	 * @return array{tone: string, text: string, links?: list<array{label: string, url: string}>}
	 */
	public function save( array $input ): array {
		$before = $this->storage->stored();
		$result = $this->storage->save( (array) ( $input[ $this->option ] ?? array() ) );
		$after  = $this->storage->stored();

		$this->kit->notices()->fields( $this->storage->messages_key(), $result['errors'], $result['notes'] );

		/**
		 * Fires after a settings screen saved: clear a cache, reschedule an event.
		 *
		 * @param array<string, mixed> $after  The option now.
		 * @param array<string, mixed> $before The option before.
		 * @param SettingsScreen       $screen The screen.
		 */
		do_action( $this->kit->name( 'settings_' . $this->hook() . '_saved' ), $after, $before, $this );

		$errors = $result['errors'];
		$notes  = $result['notes'];

		if ( ! $errors && ! $notes ) {
			return array(
				'tone' => 'success',
				'text' => $this->kit->strings()->get( 'settings_saved' ),
			);
		}

		// A link to each field with a message: it may be in a group that isn't open.
		$links = array();
		foreach ( array_keys( $errors + $notes ) as $path ) {
			$links[] = array(
				'label' => $this->labels[ $path ] ?? (string) $path,
				'url'   => '#' . $this->field_id( (string) $path ),
			);
		}

		if ( ! $errors ) {
			return array(
				'tone'  => 'info',
				'text'  => $this->kit->strings()->count( 'settings_adjusted', count( $notes ) ),
				'links' => $links,
			);
		}

		$text = $this->kit->strings()->count( 'settings_not_saved', count( $errors ) );
		if ( $notes ) {
			$text .= ' ' . $this->kit->strings()->count( 'settings_also_adjusted', count( $notes ) );
		}

		return array(
			'tone'  => 'warning',
			'text'  => $text,
			'links' => $links,
		);
	}

	/**
	 * The open group: from the URL, or the first.
	 */
	public function current_group(): string {
		$arg = 'tabs' === $this->navigation ? 'tab' : 'group';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which group to show; nothing is changed.
		$group = isset( $_GET[ $arg ] ) ? sanitize_key( wp_unslash( $_GET[ $arg ] ) ) : '';

		return isset( $this->groups[ $group ] ) ? $group : (string) array_key_first( $this->groups );
	}

	/**
	 * The screen's body.
	 */
	public function render(): string {
		$kit      = $this->kit;
		$strings  = $kit->strings();
		$messages = $this->storage->messages();
		$current  = $this->current_group();
		$base     = $kit->id() . '-' . $this->id;

		$groups = '';
		foreach ( $this->groups as $key => $group ) {
			$groups .= $this->group_markup( $key, $group, $key === $current, $messages );
		}

		$groups .= '<p class="bwc-settings__empty" data-bwc-settings-empty hidden>' . esc_html( $strings->get( 'no_setting_match' ) ) . '</p>';

		$search = ! $this->search ? '' : '<div class="bwc-settings__search" data-bwc-settings-search-box hidden>' . Search::markup(
			array(
				'id'            => $base . '-search',
				'name'          => '_bwc_search',
				'label'         => $strings->get( 'find_setting' ),
				'label_visible' => 'side' === $this->navigation,
				'placeholder'   => $strings->get( 'settings_search_hint' ),
				'hook'          => 'settings-search',
			)
		) . '</div>';

		$body = match ( $this->navigation ) {
			'side'  => '<div class="bwc-settings__layout"><div class="bwc-settings__side">' . $search . $this->side_nav( $current ) . '</div>'
				. '<div class="bwc-settings__main">' . $groups . $this->savebar() . '</div></div>',
			'tabs'  => Panel::markup(
				array(
					'tabs'         => $this->tabs(),
					'current_tab'  => $current,
					'tabs_label'   => $strings->get( 'settings_groups' ),
					'toolbar_html' => $search,
					'content_html' => $groups,
				)
			) . $this->savebar(),
			default => $search . $groups . $this->savebar(),
		};

		$intro = empty( $this->config['intro'] ) ? '' : '<p class="bwc-settings__intro">' . esc_html( (string) $this->config['intro'] ) . '</p>';

		return '<form' . Html::attributes(
			array(
				'class'                => Html::classes( 'bwc-settings', array( $this->navigation ) ),
				'id'                   => $base,
				'method'               => 'post',
				'action'               => esc_url( admin_url( 'admin-post.php' ) ),
				// The browser mustn't refill fields on reload: the page would show
				// unsaved values while the save bar says nothing changed.
				'autocomplete'         => 'off',
				'data-bwc-settings'    => $this->id,
				'data-bwc-settings-by' => 'tabs' === $this->navigation ? 'tab' : 'group',
				'data-bwc-dirty'       => true,
			)
		) . '>'
			. $kit->actions()->fields( $this->action() )
			. '<input type="hidden" name="_bwc_group" value="' . esc_attr( $current ) . '" data-bwc-settings-current>'
			. $intro . $body . '</form>';
	}

	/**
	 * Reads the described groups and sections into the screen's own shape,
	 * and the fields into buckets.
	 *
	 * @param array<string, array<string, mixed>> $groups Group key => label, intro, sections.
	 */
	private function read_groups( array $groups ): void {
		foreach ( $groups as $group_key => $group ) {
			$group_key = sanitize_key( (string) $group_key );
			if ( '' === $group_key || ! is_array( $group ) ) {
				continue;
			}

			$this->groups[ $group_key ] = array(
				'label' => (string) ( $group['label'] ?? '' ),
				'intro' => (string) ( $group['intro'] ?? '' ),
			);

			foreach ( (array) ( $group['sections'] ?? array() ) as $key => $section ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key || ! is_array( $section ) ) {
					continue;
				}
				if ( isset( $this->sections[ $key ] ) ) {
					_doing_it_wrong( __METHOD__, esc_html( "Admin Kit: two settings sections are called \"{$key}\"; the second is left out." ), '0.1.0' );
					continue;
				}

				$this->add_section( $group_key, $key, $section );
			}
		}
	}

	/**
	 * Adds one section and its fields.
	 *
	 * @param string               $group   Group key.
	 * @param string               $key     Section key.
	 * @param array<string, mixed> $section Description.
	 */
	private function add_section( string $group, string $key, array $section ): void {
		$label  = (string) ( $section['label'] ?? '' );
		$switch = null;

		if ( ! empty( $section['switch'] ) ) {
			$described = is_array( $section['switch'] ) ? $section['switch'] : array();
			$path      = $this->split( (string) ( $described['path'] ?? 'modules.' . $key ) );
			$field     = array(
				'id'      => $path[1],
				'type'    => 'switch',
				'label'   => '' === $label ? $key : $label,
				'default' => (bool) ( $described['default'] ?? false ),
			);

			if ( $this->claim( $path, $field ) ) {
				$switch = array(
					'bucket'  => $path[0],
					'key'     => $path[1],
					'default' => (bool) $field['default'],
				);
			}
		}

		$fields = array();
		foreach ( (array) ( $section['fields'] ?? array() ) as $field ) {
			if ( ! is_array( $field ) || empty( $field['id'] ) ) {
				continue;
			}

			$path        = $this->split( (string) ( $field['path'] ?? $key . '.' . $field['id'] ) );
			$field['id'] = $path[1];
			unset( $field['path'] );

			if ( $this->claim( $path, $field ) ) {
				$fields[] = array(
					'bucket' => $path[0],
					'field'  => $field,
				);
			}
		}

		$link = $section['link'] ?? null;

		$this->sections[ $key ] = array(
			'key'             => $key,
			'group'           => $group,
			'label'           => $label,
			'description'     => (string) ( $section['description'] ?? '' ),
			'switch'          => $switch,
			'requires'        => array_values( array_filter( (array) ( $section['requires'] ?? array() ), 'is_array' ) ),
			'link'            => is_array( $link ) && ! empty( $link['label'] ) && ! empty( $link['url'] ) ? array(
				'label' => (string) $link['label'],
				'url'   => (string) $link['url'],
			) : null,
			'fields'          => $fields,
			'reference'       => isset( $section['reference'] ) && is_callable( $section['reference'] ) ? $section['reference'] : null,
			'reference_label' => (string) ( $section['reference_label'] ?? $this->kit->strings()->get( 'reference' ) ),
			'render'          => isset( $section['render'] ) && is_callable( $section['render'] ) ? $section['render'] : null,
			'danger'          => false,
		);
	}

	/**
	 * The "Remove data on delete" card, last in the last group.
	 *
	 * @param array<string, mixed> $danger path, label, description, checkbox.
	 */
	private function add_danger( array $danger ): void {
		$path  = $this->split( (string) ( $danger['path'] ?? 'general.delete_data' ) );
		$label = (string) ( $danger['label'] ?? '' );
		$box   = (string) ( $danger['checkbox'] ?? '' );
		// The card's title is the heading; the box's own text is the checkbox label.
		$field = array(
			'id'    => $path[1],
			'type'  => 'checkbox',
			'label' => '' !== $box ? $box : ( '' === $label ? $path[1] : $label ),
		);

		if ( ! $this->claim( $path, $field ) ) {
			return;
		}

		$key = '_danger';

		$this->sections[ $key ] = array(
			'key'             => $key,
			'group'           => (string) array_key_last( $this->groups ),
			'label'           => $label,
			'description'     => (string) ( $danger['description'] ?? '' ),
			'switch'          => null,
			'requires'        => array(),
			'link'            => null,
			'fields'          => array(
				array(
					'bucket' => $path[0],
					'field'  => $field,
				),
			),
			'reference'       => null,
			'reference_label' => '',
			'render'          => null,
			'danger'          => true,
		);
	}

	/**
	 * "bucket.key" as its two parts.
	 *
	 * @param string $path Storage path.
	 * @return array{0: string, 1: string}
	 */
	private function split( string $path ): array {
		[ $bucket, $key ] = array_pad( explode( '.', $path, 2 ), 2, '' );

		return array( sanitize_key( $bucket ), sanitize_key( $key ) );
	}

	/**
	 * Puts a field in its bucket, unless the path is malformed or taken.
	 *
	 * @param array{0: string, 1: string} $path  Bucket and key.
	 * @param array<string, mixed>        $field Field description, with the key as its id.
	 */
	private function claim( array $path, array $field ): bool {
		[ $bucket, $key ] = $path;
		$where            = $bucket . '.' . $key;

		if ( '' === $bucket || '' === $key || '_shown' === $bucket ) {
			_doing_it_wrong( __METHOD__, esc_html( "Admin Kit: the setting path \"{$where}\" needs a bucket and a key, like \"offers.button_text\"." ), '0.1.0' );
			return false;
		}

		if ( isset( $this->labels[ $where ] ) ) {
			_doing_it_wrong( __METHOD__, esc_html( "Admin Kit: two settings use the path \"{$where}\"; the second is left out." ), '0.1.0' );
			return false;
		}

		$this->sets[ $bucket ][] = $field;
		$this->labels[ $where ]  = (string) ( $field['label'] ?? $key );

		return true;
	}

	/**
	 * HTML id of a field (the Form's scheme), for links to it.
	 *
	 * @param string $path "{bucket}.{key}".
	 */
	private function field_id( string $path ): string {
		return $this->kit->id() . '-' . str_replace( '.', '-', $path );
	}

	/**
	 * HTML id of a group.
	 *
	 * @param string $group Group key.
	 */
	private function group_id( string $group ): string {
		return $this->kit->id() . '-' . $this->id . '-group-' . $group;
	}

	/**
	 * The sections of a group.
	 *
	 * @param string $group Group key.
	 * @return array<string, Section>
	 */
	private function sections_in( string $group ): array {
		return array_filter( $this->sections, static fn( array $section ): bool => $section['group'] === $group );
	}

	/**
	 * "3 of 4 on" for a group with switches; '' without.
	 *
	 * @param string $group Group key.
	 */
	private function on_count( string $group ): string {
		$switches = array_filter( $this->sections_in( $group ), static fn( array $section ): bool => null !== $section['switch'] );
		if ( ! $switches ) {
			return '';
		}

		$on = count( array_filter( array_keys( $switches ), array( $this, 'switched_on' ) ) );

		return $this->kit->strings()->get( 'on_count', $on, count( $switches ) );
	}

	/**
	 * The side menu of groups.
	 *
	 * @param string $current Open group.
	 */
	private function side_nav( string $current ): string {
		$items = array();
		foreach ( $this->groups as $key => $group ) {
			$items[ $key ] = array(
				'label' => $group['label'],
				'url'   => '#' . $this->group_id( $key ),
				'meta'  => $this->on_count( $key ),
				'hook'  => $key,
			);
		}

		return Nav::markup(
			array(
				'label'   => $this->kit->strings()->get( 'settings_groups' ),
				'layout'  => 'side',
				'current' => $current,
				'items'   => $items,
			)
		);
	}

	/**
	 * The groups as panel tabs.
	 *
	 * @return array<string, array{label: string, url: string, hook: string}>
	 */
	private function tabs(): array {
		$tabs = array();
		foreach ( $this->groups as $key => $group ) {
			$tabs[ $key ] = array(
				'label' => $group['label'],
				'url'   => '#' . $this->group_id( $key ),
				'hook'  => $key,
			);
		}

		return $tabs;
	}

	/**
	 * One group: its heading, intro and section cards.
	 *
	 * @param string                                                             $key      Group key.
	 * @param array{label: string, intro: string}                                $group    Group.
	 * @param bool                                                               $current  Whether it's open.
	 * @param array{errors: array<string, string>, notes: array<string, string>} $messages Field messages by path.
	 */
	private function group_markup( string $key, array $group, bool $current, array $messages ): string {
		$id   = $this->group_id( $key );
		$html = '<section' . Html::attributes(
			array(
				'class'                   => 'bwc-settings__group' . ( $current ? ' is-current' : '' ),
				'id'                      => $id,
				'data-bwc-settings-group' => $key,
				'aria-labelledby'         => $id . '-title',
			)
		) . '>';

		$html .= '<h2 class="bwc-settings__group-title" id="' . esc_attr( $id . '-title' ) . '">' . esc_html( $group['label'] ) . '</h2>';
		$html .= '' === $group['intro'] ? '' : '<p class="bwc-settings__group-intro">' . esc_html( $group['intro'] ) . '</p>';

		foreach ( $this->sections_in( $key ) as $section ) {
			$html .= $this->section_markup( $section, $group['label'], $messages );
		}

		return $html . '</section>';
	}

	/**
	 * One section card: title, description, switch, paused notice, fields.
	 *
	 * @param Section                                                            $section     Section.
	 * @param string                                                             $group_label Its group's name, shown while searching.
	 * @param array{errors: array<string, string>, notes: array<string, string>} $messages    Field messages by path.
	 */
	private function section_markup( array $section, string $group_label, array $messages ): string {
		$kit     = $this->kit;
		$strings = $kit->strings();
		$sid     = $kit->id() . '-' . $this->id . '-section-' . trim( $section['key'], '_' );
		$switch  = $section['switch'];
		$on      = null === $switch || $this->switched_on( $section['key'] );
		$reasons = null === $switch ? $this->reasons( $section['key'] ) : ( $on ? $this->reasons( $section['key'] ) : array() );
		$paused  = $on && (bool) $reasons;
		$search  = array( $section['label'], $section['description'], $group_label );

		foreach ( $section['fields'] as $entry ) {
			foreach ( array( 'label', 'checkbox_label', 'description', 'unit' ) as $part ) {
				if ( isset( $entry['field'][ $part ] ) && is_string( $entry['field'][ $part ] ) ) {
					$search[] = $entry['field'][ $part ];
				}
			}
		}

		$html = '<section' . Html::attributes(
			array(
				'class'           => Html::classes(
					'bwc-section',
					array(
						'danger' => $section['danger'],
						'plain'  => '' === $section['label'],
					),
					trim( ( null === $switch ? '' : 'has-switch' ) . ( $paused ? ' is-paused' : '' ) )
				),
				'id'              => $sid,
				'data-bwc-search' => mb_strtolower( implode( ' ', array_filter( $search ) ) ),
				'aria-labelledby' => '' === $section['label'] ? null : $sid . '-name',
				'aria-label'      => '' === $section['label'] ? $group_label : null,
			)
		) . '>';

		if ( '' !== $section['label'] ) {
			$html .= '<div class="bwc-section__head"><div class="bwc-section__text">';
			$html .= '<h3 class="bwc-section__title" id="' . esc_attr( $sid . '-title' ) . '"><span id="' . esc_attr( $sid . '-name' ) . '">' . esc_html( $section['label'] ) . '</span>'
				. ' <span class="bwc-section__group">' . esc_html( $strings->get( 'in_group', $group_label ) ) . '</span>'
				. ( $paused ? ' ' . Pill::markup(
					array(
						'text' => $strings->get( 'paused' ),
						'tone' => 'paused',
					)
				) : '' )
				. '</h3>';
			$html .= '' === $section['description'] ? '' : '<p class="bwc-section__description" id="' . esc_attr( $sid . '-description' ) . '">' . esc_html( $section['description'] ) . '</p>';
			$html .= null === $section['link'] ? '' : '<a class="bwc-section__link" href="' . esc_url( $section['link']['url'] ) . '">' . esc_html( $section['link']['label'] ) . ' <span aria-hidden="true">→</span></a>';
			$html .= '</div>';

			if ( null !== $switch ) {
				$name  = $this->option . '[' . $switch['bucket'] . '][' . $switch['key'] . ']';
				$html .= '<div class="bwc-section__switch">' . Toggle::markup(
					array(
						'id'          => $this->field_id( $switch['bucket'] . '.' . $switch['key'] ),
						'name'        => $name,
						'checked'     => $on,
						// The section's name alone, not the "Paused" pill or the group it's in.
						'labelledby'  => $sid . '-name',
						'describedby' => '' === $section['description'] ? null : $sid . '-description',
						'hook'        => 'section-switch',
					)
				) . '<input type="hidden" name="' . esc_attr( $this->option . '[_shown][' . $switch['bucket'] . '][' . $switch['key'] . ']' ) . '" value="1"></div>';
			}

			$html .= '</div>';
		}

		if ( $paused ) {
			$html .= '<div class="bwc-section__paused">' . Notice::markup(
				array(
					'tone' => 'paused',
					'text' => $strings->get( 'section_paused' ) . ' ' . implode( ' ', $reasons ),
				)
			) . '</div>';
		}

		$body = $this->fields_markup( $section, $messages );

		if ( null !== $section['reference'] ) {
			$body .= '<details class="bwc-advanced bwc-section__reference"><summary class="bwc-advanced__summary">' . esc_html( $section['reference_label'] ) . '</summary><div class="bwc-advanced__body">' . (string) ( $section['reference'] )() . '</div></details>';
		}

		if ( null !== $section['render'] ) {
			$body .= (string) ( $section['render'] )();
		}

		$html .= '' === $body ? '' : '<div class="bwc-section__body">' . $body . '</div>';

		$count = count( $section['fields'] );
		if ( null !== $switch && $count > 0 ) {
			$html .= '<p class="bwc-section__off">' . esc_html( $strings->count( 'turn_on_to_change', $count ) ) . '</p>';
		}

		return $html . '</section>';
	}

	/**
	 * A section's fields: runs of the same bucket through the Form, then the
	 * advanced ones in a closed fold (open when one of them has an error).
	 *
	 * @param Section                                                            $section  Section.
	 * @param array{errors: array<string, string>, notes: array<string, string>} $messages Field messages by path.
	 */
	private function fields_markup( array $section, array $messages ): string {
		$plain    = array();
		$advanced = array();
		foreach ( $section['fields'] as $entry ) {
			if ( ! empty( $entry['field']['advanced'] ) ) {
				$advanced[] = $entry;
			} else {
				$plain[] = $entry;
			}
		}

		$html = $this->runs( $plain, $messages );

		if ( $advanced ) {
			$open = false;
			foreach ( $advanced as $entry ) {
				$open = $open || isset( $messages['errors'][ $entry['bucket'] . '.' . $entry['field']['id'] ] );
			}

			$html .= '<details class="bwc-advanced"' . ( $open ? ' open' : '' ) . '><summary class="bwc-advanced__summary">' . esc_html( $this->kit->strings()->get( 'advanced' ) ) . '</summary>'
				. '<div class="bwc-advanced__body">' . $this->runs( $advanced, $messages ) . '</div></details>';
		}

		return $html;
	}

	/**
	 * Fields through the Form, one call per run of the same bucket.
	 *
	 * @param list<array{bucket: string, field: array<string, mixed>}>           $entries  Fields with their buckets.
	 * @param array{errors: array<string, string>, notes: array<string, string>} $messages Field messages by path.
	 */
	private function runs( array $entries, array $messages ): string {
		$runs = array();
		foreach ( $entries as $entry ) {
			$last = array_key_last( $runs );
			if ( null === $last || $runs[ $last ]['bucket'] !== $entry['bucket'] ) {
				$runs[] = array(
					'bucket' => $entry['bucket'],
					'fields' => array(),
				);
				$last   = array_key_last( $runs );
			}
			$runs[ $last ]['fields'][] = $entry['field'];
		}

		$html = '';
		foreach ( $runs as $run ) {
			$mine = array(
				'errors' => array(),
				'notes'  => array(),
			);
			foreach ( array_keys( $mine ) as $kind ) {
				foreach ( $messages[ $kind ] as $path => $message ) {
					[ $in, $key ] = array_pad( explode( '.', (string) $path, 2 ), 2, '' );
					if ( $in === $run['bucket'] ) {
						$mine[ $kind ][ $key ] = $message;
					}
				}
			}

			$html .= Form::markup(
				array(
					'fields'  => $run['fields'],
					'storage' => $this->storage,
					'set'     => $run['bucket'],
					'errors'  => $mine['errors'],
					'notes'   => $mine['notes'],
					'summary' => false,
				)
			);
		}

		return $html;
	}

	/**
	 * The save bar: status, Discard, Save.
	 */
	private function savebar(): string {
		$strings = $this->kit->strings();

		return '<div class="bwc-savebar">'
			. '<span class="bwc-savebar__status" role="status" data-bwc-dirty-text></span>'
			. '<button type="button" class="bwc-button bwc-button--outline" data-bwc-discard hidden><span class="bwc-button__label">' . esc_html( $strings->get( 'discard' ) ) . '</span></button>'
			. '<button type="submit" class="bwc-button bwc-button--primary" data-bwc-save><span class="bwc-button__label">' . esc_html( $strings->get( 'save' ) ) . '</span></button>'
			. '</div>';
	}
}
