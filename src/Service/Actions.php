<?php
/**
 * One way to run anything a button does.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Component\Button;
use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Actions. Every action is an admin-post handler
 * named "{hook}_bwc_{name}": capability, then nonce, then the declared input,
 * then the handler, then a redirect back to the same view with a flash notice.
 *
 * A destructive action sets "confirm". With the script, the button asks first
 * (confirm.js) and marks the form as confirmed; without it, the handler shows
 * a page that asks before going ahead.
 */
final class Actions {

	/**
	 * Input types an action can declare.
	 */
	private const TYPES = array( 'key', 'key[]', 'text', 'text[]', 'textarea', 'int', 'int[]', 'bool', 'array' );

	/**
	 * Arguments the kit adds to an action's request and strips from the redirect.
	 */
	private const OWN_ARGS = array( 'action', '_wpnonce', '_wp_http_referer', '_bwc_confirmed' );

	/**
	 * Registered actions, by name.
	 *
	 * @var array<string, array{handler: callable, capability: ?string, method: string, confirm: string, confirm_when: ?callable, redirect: ?callable, input: array<string, string>}>
	 */
	private array $actions = array();

	/**
	 * Forms of buttons that sit inside another form, printed at the end of the screen.
	 *
	 * @var array<string, string>
	 */
	private array $deferred = array();

	/**
	 * Constructor.
	 *
	 * @param Kit $kit The kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * Prints forms no screen printed (a button on someone else's screen).
	 */
	public function register_footer(): void {
		add_action( 'admin_footer', array( $this, 'print_forms' ) );
	}

	/**
	 * Registers an action.
	 *
	 * @param string                                       $name    Action name: lowercase letters, digits and underscores.
	 * @param callable(array<string, mixed>): mixed        $handler Gets the declared input; returns a notice array (tone, text, title, links), a WP_Error, or null.
	 * @param array<string, mixed>                         $args    capability, method ("post" or "get"), confirm, confirm_when (callable( array $input ): string, a question that depends on the request, asked on the server's page), redirect (callable( array $input, mixed $result, string $back ): string, where $back is the page it came from), input (key => type).
	 * @throws \InvalidArgumentException When the name or an input type is wrong.
	 */
	public function register( string $name, callable $handler, array $args = array() ): void {
		if ( ! preg_match( '/^[a-z][a-z0-9_]*$/', $name ) ) {
			throw new \InvalidArgumentException( esc_html( "Admin Kit: the action name \"{$name}\" must be lowercase letters, digits and underscores." ) );
		}

		$input = array();
		foreach ( (array) ( $args['input'] ?? array() ) as $key => $type ) {
			if ( ! in_array( $type, self::TYPES, true ) ) {
				throw new \InvalidArgumentException( esc_html( "Admin Kit: action \"{$name}\" declares \"{$key}\" as \"{$type}\"; use one of " . implode( ', ', self::TYPES ) . '.' ) );
			}
			$input[ (string) $key ] = (string) $type;
		}

		$this->actions[ $name ] = array(
			'handler'      => $handler,
			'capability'   => isset( $args['capability'] ) ? (string) $args['capability'] : null,
			'method'       => 'get' === ( $args['method'] ?? 'post' ) ? 'get' : 'post',
			'confirm'      => isset( $args['confirm'] ) ? (string) $args['confirm'] : '',
			'confirm_when' => isset( $args['confirm_when'] ) && is_callable( $args['confirm_when'] ) ? $args['confirm_when'] : null,
			'redirect'     => isset( $args['redirect'] ) && is_callable( $args['redirect'] ) ? $args['redirect'] : null,
			'input'        => $input,
		);

		add_action( 'admin_post_' . $this->hook_name( $name ), fn() => $this->handle( $name ) );
	}

	/**
	 * Whether an action is registered.
	 *
	 * @param string $name Action name.
	 */
	public function has( string $name ): bool {
		return isset( $this->actions[ $name ] );
	}

	/**
	 * The admin-post action: "{hook}_bwc_{name}". Also the nonce action.
	 *
	 * @param string $name Action name.
	 */
	public function hook_name( string $name ): string {
		return $this->kit->name( $name );
	}

	/**
	 * Hidden fields every form for this action needs: the action, the nonce,
	 * where to come back to, and the confirmation flag.
	 *
	 * @param string $name Action name.
	 */
	public function fields( string $name ): string {
		$html = '<input type="hidden" name="action" value="' . esc_attr( $this->hook_name( $name ) ) . '">';
		// Not wp_nonce_field(): its id="_wpnonce" would repeat with every button on the page.
		$html .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $this->hook_name( $name ) ) ) . '">';
		$html .= wp_referer_field( false );

		if ( '' !== ( $this->actions[ $name ]['confirm'] ?? '' ) ) {
			$html .= '<input type="hidden" name="_bwc_confirmed" value="" data-bwc-confirmed>';
		}

		return $html;
	}

	/**
	 * A button that runs the action: a small form with the nonce and a
	 * Button. Inside another form, pass "inside_form" => true: the form is
	 * printed at the end of the screen and the button points at it.
	 *
	 * @param string               $name  Action name.
	 * @param string               $label Button text.
	 * @param array<string, mixed> $args  variant, size, icon, input (hidden key => value), inside_form, class, disabled, title (shown on hover).
	 */
	public function button( string $name, string $label, array $args = array() ): string {
		if ( ! $this->has( $name ) ) {
			_doing_it_wrong( __METHOD__, esc_html( "Admin Kit: the action \"{$name}\" isn't registered." ), '0.1.0' );
			return '';
		}

		$action  = $this->actions[ $name ];
		$form_id = $this->kit->id() . '-action-' . str_replace( '_', '-', $name ) . '-' . ( count( $this->deferred ) + 1 );
		$hidden  = $this->fields( $name );

		foreach ( (array) ( $args['input'] ?? array() ) as $key => $value ) {
			foreach ( (array) $value as $one ) {
				$hidden .= '<input type="hidden" name="' . esc_attr( (string) $key . ( is_array( $value ) ? '[]' : '' ) ) . '" value="' . esc_attr( (string) $one ) . '">';
			}
		}

		$inside = ! empty( $args['inside_form'] );
		$button = Button::markup(
			array(
				'label'    => $label,
				'type'     => 'submit',
				'variant'  => (string) ( $args['variant'] ?? ( '' === $action['confirm'] ? 'secondary' : 'danger' ) ),
				'size'     => (string) ( $args['size'] ?? 'regular' ),
				'icon'     => (string) ( $args['icon'] ?? '' ),
				'confirm'  => '' === $action['confirm'] ? null : $action['confirm'],
				'form'     => $inside ? $form_id : null,
				'class'    => (string) ( $args['class'] ?? '' ),
				'disabled' => ! empty( $args['disabled'] ),
				'title'    => (string) ( $args['title'] ?? '' ),
			)
		);

		$form = '<form' . Html::attributes(
			array(
				'class'  => 'bwc-action',
				'id'     => $form_id,
				'method' => $action['method'],
				'action' => esc_url( admin_url( 'admin-post.php' ) ),
			)
		) . '>' . $hidden;

		if ( $inside ) {
			$this->deferred[ $form_id ] = $form . '</form>';
			return $button;
		}

		return $form . $button . '</form>';
	}

	/**
	 * A link that runs a GET action (an export). Never for writes.
	 *
	 * @param string               $name  Action name.
	 * @param array<string, mixed> $input Query arguments the action declared.
	 */
	public function url( string $name, array $input = array() ): string {
		if ( 'get' !== ( $this->actions[ $name ]['method'] ?? '' ) ) {
			_doing_it_wrong( __METHOD__, esc_html( "Admin Kit: \"{$name}\" isn't a GET action, so it can't be a link. Use button()." ), '0.1.0' );
			return '';
		}

		// add_query_arg() doesn't encode values, and the input can come from the request.
		return add_query_arg(
			array_merge(
				rawurlencode_deep( $input ),
				array(
					'action'   => $this->hook_name( $name ),
					'_wpnonce' => wp_create_nonce( $this->hook_name( $name ) ),
				)
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The deferred forms, removed once taken. Screen prints them at its end.
	 */
	public function forms(): string {
		$html           = implode( '', $this->deferred );
		$this->deferred = array();

		return $html;
	}

	/**
	 * Prints the deferred forms no screen printed.
	 */
	public function print_forms(): void {
		echo $this->forms(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in button().
	}

	/**
	 * Runs an action for this request and redirects, or asks first.
	 *
	 * @param string $name Action name.
	 */
	public function handle( string $name ): void {
		// phpcs:disable WordPress.Security.NonceVerification -- dispatch() checks the nonce before anything is read for use.
		$request = 'get' === ( $this->actions[ $name ]['method'] ?? 'post' ) ? $_GET : $_POST;
		$request = (array) wp_unslash( $request );
		// phpcs:enable WordPress.Security.NonceVerification

		if ( empty( $request['_wp_http_referer'] ) ) {
			$request['_wp_http_referer'] = (string) wp_get_raw_referer();
		}

		$outcome = $this->dispatch( $name, $request );

		if ( isset( $outcome['error'] ) ) {
			wp_die( esc_html( $outcome['error'] ), '', array( 'response' => (int) $outcome['status'] ) );
		}

		if ( isset( $outcome['confirm'] ) ) {
			$this->confirm_page( $name, $request, (string) $outcome['confirm'] );
			return;
		}

		wp_safe_redirect( (string) $outcome['redirect'] );
		exit;
	}

	/**
	 * Checks and runs an action. Separate from handle() so it can be tested.
	 *
	 * @param string               $name    Action name.
	 * @param array<string, mixed> $request The request, unslashed.
	 * @return array{redirect?: string, result?: mixed, confirm?: string, error?: string, status?: int}
	 */
	public function dispatch( string $name, array $request ): array {
		$strings = $this->kit->strings();

		if ( ! isset( $this->actions[ $name ] ) ) {
			return array(
				'error'  => $strings->get( 'action_unknown' ),
				'status' => 404,
			);
		}

		$action     = $this->actions[ $name ];
		$capability = $this->kit->capability( $name, isset( $action['capability'] ) ? (string) $action['capability'] : null );

		if ( ! current_user_can( $capability ) ) {
			return array(
				'error'  => $strings->get( 'action_not_allowed' ),
				'status' => 403,
			);
		}

		if ( ! wp_verify_nonce( (string) ( $request['_wpnonce'] ?? '' ), $this->hook_name( $name ) ) ) {
			return array(
				'error'  => $strings->get( 'action_expired' ),
				'status' => 403,
			);
		}

		$input = self::input( $action['input'], $request );

		// A question that depends on the request (the chosen bulk action) is asked on the server's page.
		$question = null === $action['confirm_when'] ? $action['confirm'] : (string) ( $action['confirm_when'] )( $input );
		if ( '' !== $question && '1' !== (string) ( $request['_bwc_confirmed'] ?? '' ) ) {
			return array( 'confirm' => $question );
		}

		$result = ( $action['handler'] )( $input );

		if ( is_wp_error( $result ) ) {
			$this->kit->notices()->flash( 'danger', $result->get_error_message() );
		} elseif ( is_array( $result ) && ! empty( $result['text'] ) ) {
			$this->kit->notices()->flash( (string) ( $result['tone'] ?? 'success' ), (string) $result['text'], $result );
		}

		$back     = $this->back( (string) ( $request['_wp_http_referer'] ?? '' ) );
		$redirect = null === $action['redirect'] ? '' : (string) ( $action['redirect'] )( $input, $result, $back );

		return array(
			'redirect' => '' === $redirect ? $back : (string) wp_validate_redirect( $redirect, $back ),
			'result'   => $result,
		);
	}

	/**
	 * Where to go after an action: the page it came from, without the kit's
	 * own arguments. The dashboard when there's no usable referer.
	 *
	 * @param string $referer The referring URL or path.
	 */
	public function back( string $referer ): string {
		$url = '' === $referer ? '' : remove_query_arg( self::OWN_ARGS, $referer );

		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			$url = home_url( $url );
		}

		return (string) wp_validate_redirect( $url, admin_url() );
	}

	/**
	 * The declared input, read from the request and sanitized by type.
	 * Anything not declared is ignored.
	 *
	 * @param array<string, string> $schema  Key => type.
	 * @param array<string, mixed>  $request The request, unslashed.
	 * @return array<string, mixed>
	 */
	public static function input( array $schema, array $request ): array {
		$input = array();
		foreach ( $schema as $key => $type ) {
			$raw = $request[ $key ] ?? null;

			$input[ $key ] = match ( $type ) {
				'key'      => is_scalar( $raw ) ? sanitize_key( (string) $raw ) : '',
				'text'     => is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '',
				'textarea' => is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '',
				'int'      => is_scalar( $raw ) ? (int) $raw : 0,
				'int[]'    => array_values( array_map( 'intval', array_filter( (array) $raw, 'is_scalar' ) ) ),
				'key[]'    => array_values( array_filter( array_map( static fn( $one ): string => sanitize_key( (string) $one ), array_filter( (array) $raw, 'is_scalar' ) ) ) ),
				'text[]'   => array_values( array_filter( array_map( static fn( $one ): string => sanitize_text_field( (string) $one ), array_filter( (array) $raw, 'is_scalar' ) ), static fn( string $one ): bool => '' !== $one ) ),
				'bool'     => is_scalar( $raw ) && rest_sanitize_boolean( (string) $raw ),
				// Passed on as posted, for a form whose fields sanitize it (settings).
				'array'    => is_array( $raw ) ? $raw : array(),
				default    => null,
			};
		}

		return $input;
	}

	/**
	 * The "are you sure?" page for a destructive action posted without the
	 * script: the question, the same request again with the confirmation, and
	 * a way back.
	 *
	 * @param string               $name     Action name.
	 * @param array<string, mixed> $request  The request, unslashed.
	 * @param string               $question The action's confirm text.
	 */
	private function confirm_page( string $name, array $request, string $question ): void {
		$strings = $this->kit->strings();
		$fields  = '';

		foreach ( array_keys( $this->actions[ $name ]['input'] ) as $key ) {
			if ( isset( $request[ $key ] ) ) {
				$fields .= self::hidden( $key, $request[ $key ] );
			}
		}

		$back = $this->back( (string) ( $request['_wp_http_referer'] ?? '' ) );
		$html = '<p>' . esc_html( $question ) . '</p>'
			. '<form method="' . esc_attr( $this->actions[ $name ]['method'] ) . '" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( $this->hook_name( $name ) ) . '">'
			. '<input type="hidden" name="_wpnonce" value="' . esc_attr( (string) ( $request['_wpnonce'] ?? '' ) ) . '">'
			. '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( (string) ( $request['_wp_http_referer'] ?? '' ) ) . '">'
			. '<input type="hidden" name="_bwc_confirmed" value="1">'
			. $fields
			. '<p><button type="submit" class="button button-primary">' . esc_html( $strings->get( 'confirm_yes' ) ) . '</button> '
			. '<a class="button" href="' . esc_url( $back ) . '">' . esc_html( $strings->get( 'cancel' ) ) . '</a></p></form>';

		wp_die( $html, esc_html( $strings->get( 'confirm_title' ) ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * Hidden inputs that post a value again, arrays included.
	 *
	 * @param string $name  Input name.
	 * @param mixed  $value Value.
	 */
	private static function hidden( string $name, mixed $value ): string {
		if ( is_array( $value ) ) {
			$html = '';
			foreach ( $value as $key => $one ) {
				$html .= self::hidden( $name . '[' . $key . ']', $one );
			}
			return $html;
		}

		return is_scalar( $value ) ? '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">' : '';
	}
}
