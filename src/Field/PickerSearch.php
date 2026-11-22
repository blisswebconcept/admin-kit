<?php
/**
 * The search request behind the post, user and term pickers.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Field\Type\Picker;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * One admin-ajax action per plugin, {hook}_bwc_search, for logged-in users
 * only. The request names a picker field by its token; the server uses that
 * field's own query and capability, so a request can't widen either. Only
 * fields the plugin described in this request (at init) can be searched, and
 * only by users who may use a form the field sits in (Registry::allow_search()).
 */
final class PickerSearch {

	/**
	 * Hooks the request and gives the script its nonce.
	 *
	 * @param Kit $kit The kit.
	 */
	public static function register( Kit $kit ): void {
		add_action( 'wp_ajax_' . $kit->name( 'search' ), array( self::class, 'handle' ) );
		$kit->assets()->add_script_config(
			'search',
			static fn(): array => array(
				'action' => $kit->name( 'search' ),
				'nonce'  => wp_create_nonce( $kit->name( 'search' ) ),
			)
		);
	}

	/**
	 * Answers a search.
	 */
	public static function handle(): void {
		$kit = Kit::instance();
		check_ajax_referer( $kit->name( 'search' ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$answer = self::answer(
			isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '',
			isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '',
			isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( is_wp_error( $answer ) ) {
			wp_send_json_error( array( 'message' => $answer->get_error_message() ), (int) ( $answer->get_error_data()['status'] ?? 400 ) );
		}

		wp_send_json_success( $answer );
	}

	/**
	 * The answer to a search, or why there is none. Separate so it can be tested.
	 *
	 * @param string $token The field's token.
	 * @param string $q     Search text.
	 * @param int    $page  Page, from 1.
	 * @return array{items: list<array{value: int, label: string, detail: string, depth: int}>, more: bool}|\WP_Error
	 */
	public static function answer( string $token, string $q, int $page ): array|\WP_Error {
		$kit   = Kit::instance();
		$field = $kit->fields()->picker( $token );
		$type  = null === $field ? null : $kit->fields()->get( (string) $field['type'] );

		if ( null === $field || ! $type instanceof Picker ) {
			return new \WP_Error( 'bwc_search_field', $kit->strings()->get( 'action_unknown' ), array( 'status' => 400 ) );
		}

		// The type's own capability (editing that post type, listing users…) and the form's.
		if ( ! $type->can_search( $field ) || ! $kit->fields()->may_search( $token ) ) {
			return new \WP_Error( 'bwc_search_cap', $kit->strings()->get( 'action_not_allowed' ), array( 'status' => 403 ) );
		}

		return $type->search( $field, $q, max( 1, $page ) );
	}
}
