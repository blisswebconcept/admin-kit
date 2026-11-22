<?php
/**
 * Fields on a taxonomy's add and edit forms.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Term meta. TermMeta::fields() adds the fields to the
 * add form (stacked) and the edit form (table rows), saves them on
 * created_{taxonomy} and edited_{taxonomy}, and registers each key with the
 * same test (edit_term and the set's capability). Keys are only in the REST
 * API when the set asks (show_in_rest, off by default), and then only for editors.
 */
final class TermMeta extends MetaStorage {

	/**
	 * Term meta.
	 */
	protected function meta_type(): string {
		return 'term';
	}

	/**
	 * Allowed to edit this term.
	 */
	public function can_edit(): bool {
		return current_user_can( 'edit_term', $this->object_id );
	}

	/**
	 * Adds fields to a taxonomy's forms.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config id, taxonomy, fields, capability, prefix, show_in_rest.
	 */
	public static function fields( Kit $kit, array $config ): void {
		$set           = array_merge(
			array(
				'capability'   => '',
				'prefix'       => $kit->hook(),
				'show_in_rest' => false,
			),
			$config
		);
		$set['id']     = sanitize_key( (string) $set['id'] );
		$set['fields'] = Form::without_secrets( (array) $set['fields'], 'a term form' );
		$taxonomy      = (string) $set['taxonomy'];

		add_action( $taxonomy . '_add_form_fields', static fn() => self::render( $kit, $set, 0, 'stack' ) );
		add_action( $taxonomy . '_edit_form_fields', static fn( $term ) => self::render( $kit, $set, (int) $term->term_id, 'table' ) );
		add_action( 'created_' . $taxonomy, static fn( $term_id ) => self::save( $kit, $set, (int) $term_id ) );
		add_action( 'edited_' . $taxonomy, static fn( $term_id ) => self::save( $kit, $set, (int) $term_id ) );

		$kit->assets()->load_when(
			static function ( string $hook ) use ( $taxonomy ): bool {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				return in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) && null !== $screen && $taxonomy === $screen->taxonomy;
			}
		);

		self::on_init(
			static function () use ( $kit, $set, $taxonomy ): void {
				// The same test as the form: edit this term, and the set's own capability.
				self::register_keys( $kit, 'term', $taxonomy, (array) $set['fields'], (string) $set['prefix'], (bool) $set['show_in_rest'], static fn( $allowed, $key, $term_id ): bool => current_user_can( 'edit_term', (int) $term_id ) && self::set_allowed( $set ) );

				$kit->fields()->allow_search(
					(array) $set['fields'],
					static function () use ( $set, $taxonomy ): bool {
						$object = get_taxonomy( $taxonomy );
						return $object && current_user_can( $object->cap->edit_terms ) && self::set_allowed( $set );
					}
				);
			}
		);
	}

	/**
	 * Whether the current user passes the set's own capability (true when it has none).
	 *
	 * @param array<string, mixed> $set Configuration.
	 */
	private static function set_allowed( array $set ): bool {
		return '' === $set['capability'] || current_user_can( (string) $set['capability'] );
	}

	/**
	 * The fields: stacked on the add form, table rows on the edit form.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $set     Configuration.
	 * @param int                  $term_id Term ID, 0 on the add form.
	 * @param string               $layout  stack or table.
	 */
	private static function render( Kit $kit, array $set, int $term_id, string $layout ): void {
		if ( ! self::set_allowed( $set ) ) {
			return;
		}

		$messages = $term_id > 0 ? Kit::instance()->notices()->take_fields( 'term:' . $set['id'] . ':' . $term_id ) : array(
			'errors' => array(),
			'notes'  => array(),
		);

		$nonce = wp_nonce_field( $kit->name( 'term_' . $set['id'] ), $kit->name( 'nonce_' . $set['id'] ), true, false );
		$form  = Form::markup(
			array(
				'fields'  => $set['fields'],
				'storage' => new self( $kit, $term_id, (array) $set['fields'], (string) $set['prefix'] ),
				'set'     => $set['id'],
				'errors'  => $messages['errors'],
				'notes'   => $messages['notes'],
				'layout'  => $layout,
				'scoped'  => true,
			)
		);

		// A table row can't hold a bare input, so on the edit form the nonce rides in the first row's cell.
		echo 'table' === $layout ? preg_replace( '#<td>#', '<td>' . $nonce, $form, 1 ) : $nonce . $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the form and by wp_nonce_field.
	}

	/**
	 * Saves the fields: nonce and edit rights first.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $set     Configuration.
	 * @param int                  $term_id Term ID.
	 */
	private static function save( Kit $kit, array $set, int $term_id ): void {
		$nonce = $kit->name( 'nonce_' . $set['id'] );

		if ( ! isset( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) ), $kit->name( 'term_' . $set['id'] ) ) ) {
			return;
		}

		$storage = new self( $kit, $term_id, (array) $set['fields'], (string) $set['prefix'] );
		if ( ! $storage->can_edit() || ! self::set_allowed( $set ) ) {
			return;
		}

		$storage->save_posted( (string) $set['id'], 'term:' . $set['id'] . ':' . $term_id );
	}
}
