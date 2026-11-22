<?php
/**
 * What every screen has: an id, a label, a capability, a place in a Shell.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Screen;

use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Shell\Shell;

defined( 'ABSPATH' ) || exit;

/**
 * A screen is an object made from a plain description; it does nothing until
 * the Shell renders it.
 */
abstract class BaseScreen {

	/**
	 * Screen id: lowercase letters, digits and hyphens.
	 */
	protected string $id;

	/**
	 * Tab and submenu label.
	 */
	protected string $label;

	/**
	 * Capability, or null for the kit's.
	 */
	protected ?string $capability;

	/**
	 * Count on the tab.
	 *
	 * @var callable|null
	 */
	protected $badge;

	/**
	 * Registered but not in the menu or tabs.
	 */
	protected bool $hidden;

	/**
	 * The Shell that shows this screen.
	 */
	protected ?Shell $shell = null;

	/**
	 * Constructor.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config id, label, capability, badge, hidden.
	 * @throws \InvalidArgumentException When the id is missing or malformed.
	 */
	public function __construct( protected Kit $kit, protected array $config ) {
		$id = isset( $config['id'] ) ? (string) $config['id'] : '';
		if ( ! preg_match( '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $id ) ) {
			throw new \InvalidArgumentException( esc_html( "Admin Kit: the screen id \"{$id}\" must be lowercase letters and digits, words joined by single hyphens." ) );
		}

		$this->id         = $id;
		$this->label      = isset( $config['label'] ) ? (string) $config['label'] : $id;
		$this->capability = isset( $config['capability'] ) ? (string) $config['capability'] : null;
		$this->badge      = isset( $config['badge'] ) && is_callable( $config['badge'] ) ? $config['badge'] : null;
		$this->hidden     = ! empty( $config['hidden'] );
	}

	/**
	 * The screen's body as escaped HTML; the Shell puts it in the frame.
	 */
	abstract public function render(): string;

	/**
	 * Screen id.
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * The id with underscores, for hook and action names.
	 */
	public function hook(): string {
		return str_replace( '-', '_', $this->id );
	}

	/**
	 * Tab and submenu label.
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * The capability this screen needs.
	 */
	public function capability(): string {
		return $this->kit->capability( $this->id, $this->capability );
	}

	/**
	 * Whether the current user may see it.
	 */
	public function can(): bool {
		return current_user_can( $this->capability() );
	}

	/**
	 * Whether it's left out of the menu and tabs.
	 */
	public function hidden(): bool {
		return $this->hidden;
	}

	/**
	 * The tab's count, or null for none.
	 *
	 * @return array{count: int, tone: string}|null
	 */
	public function badge(): ?array {
		if ( null === $this->badge ) {
			return null;
		}

		$badge = ( $this->badge )();
		if ( ! is_array( $badge ) || empty( $badge['count'] ) ) {
			return null;
		}

		return array(
			'count' => (int) $badge['count'],
			'tone'  => 'danger' === ( $badge['tone'] ?? 'info' ) ? 'danger' : 'info',
		);
	}

	/**
	 * Sets the Shell that shows this screen. Called by the Shell.
	 *
	 * @param Shell $shell The Shell.
	 */
	public function attach( Shell $shell ): void {
		$this->shell = $shell;
	}

	/**
	 * Link to this screen with query arguments; null drops an argument.
	 *
	 * @param array<string, scalar|null> $args Query arguments.
	 */
	public function url( array $args = array() ): string {
		$base = null === $this->shell ? admin_url( 'admin.php' ) : $this->shell->url( $this->id );
		$keep = array_filter( $args, static fn( $value ): bool => null !== $value );
		$drop = array_keys( array_diff_key( $args, $keep ) );

		// add_query_arg() doesn't encode values, and these come from the request.
		return add_query_arg( rawurlencode_deep( $keep ), remove_query_arg( $drop, $base ) );
	}

	/**
	 * Hidden inputs for a value, arrays included.
	 *
	 * @param string $name  Input name.
	 * @param mixed  $value Value.
	 */
	protected static function hidden_inputs( string $name, mixed $value ): string {
		if ( is_array( $value ) ) {
			$html = '';
			foreach ( $value as $key => $one ) {
				$html .= self::hidden_inputs( $name . '[' . ( is_int( $key ) ? '' : $key ) . ']', $one );
			}
			return $html;
		}

		return is_scalar( $value ) ? '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">' : '';
	}
}
