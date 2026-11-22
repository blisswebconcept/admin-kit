<?php
/**
 * Fields in a meta box on the post edit screen.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta. PostMeta::box() adds the meta box, saves
 * it on save_post (nonce, autosaves and revisions skipped, edit_post checked),
 * and registers each key with the same test, so the REST API and the Custom
 * Fields panel can't skip the box's capability. Keys are only in the REST API
 * when the box asks (show_in_rest, off by default), and then only for editors.
 */
final class PostMeta extends MetaStorage {

	/**
	 * Post meta.
	 */
	protected function meta_type(): string {
		return 'post';
	}

	/**
	 * Allowed to edit this post (and the box's own capability, when set).
	 */
	public function can_edit(): bool {
		return current_user_can( 'edit_post', $this->object_id );
	}

	/**
	 * Adds a meta box.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config id, title, post_types, fields, context (normal|side|advanced), priority, capability, prefix, single_key, show_in_rest.
	 */
	public static function box( Kit $kit, array $config ): void {
		$box           = array_merge(
			array(
				'context'      => 'advanced',
				'priority'     => 'default',
				'capability'   => '',
				'prefix'       => $kit->hook(),
				'single_key'   => '',
				'post_types'   => array( 'post' ),
				'show_in_rest' => false,
			),
			$config
		);
		$box['id']     = sanitize_key( (string) $box['id'] );
		$box['fields'] = Form::without_secrets( (array) $box['fields'], 'a meta box' );

		add_action(
			'add_meta_boxes',
			static function () use ( $kit, $box ): void {
				if ( ! self::box_allowed( $box ) ) {
					return;
				}
				add_meta_box( $kit->id() . '-' . $box['id'], (string) $box['title'], static fn( $post ) => self::render( $kit, $box, (int) $post->ID ), (array) $box['post_types'], (string) $box['context'], (string) $box['priority'] );
			}
		);

		add_action( 'save_post', static fn( $post_id ) => self::save( $kit, $box, (int) $post_id ) );

		$kit->assets()->load_when(
			static function ( string $hook ) use ( $box ): bool {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				return in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && null !== $screen && in_array( $screen->post_type, (array) $box['post_types'], true );
			}
		);

		self::on_init(
			static function () use ( $kit, $box ): void {
				// The same test as the box: edit this post, and the box's own capability.
				$auth = static fn( $allowed, $key, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id ) && self::box_allowed( $box );

				foreach ( (array) $box['post_types'] as $post_type ) {
					if ( '' === $box['single_key'] ) {
						self::register_keys( $kit, 'post', (string) $post_type, (array) $box['fields'], (string) $box['prefix'], (bool) $box['show_in_rest'], $auth );
					} else {
						self::register_single_key( 'post', (string) $post_type, (string) $box['single_key'] );
					}
				}

				$kit->fields()->allow_search(
					(array) $box['fields'],
					static function () use ( $box ): bool {
						foreach ( (array) $box['post_types'] as $post_type ) {
							$type = get_post_type_object( (string) $post_type );
							if ( $type && current_user_can( $type->cap->edit_posts ) ) {
								return self::box_allowed( $box );
							}
						}
						return false;
					}
				);
			}
		);
	}

	/**
	 * Whether the current user passes the box's own capability (true when it has none).
	 *
	 * @param array<string, mixed> $box Box configuration.
	 */
	private static function box_allowed( array $box ): bool {
		return '' === $box['capability'] || current_user_can( (string) $box['capability'] );
	}

	/**
	 * The box's storage for a post.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $box     Box configuration.
	 * @param int                  $post_id Post ID.
	 */
	private static function storage( Kit $kit, array $box, int $post_id ): self {
		return new self( $kit, $post_id, (array) $box['fields'], (string) $box['prefix'], (string) $box['single_key'] );
	}

	/**
	 * The box's fields.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $box     Box configuration.
	 * @param int                  $post_id Post ID.
	 */
	private static function render( Kit $kit, array $box, int $post_id ): void {
		$messages = Kit::instance()->notices()->take_fields( 'post:' . $box['id'] . ':' . $post_id );

		wp_nonce_field( $kit->name( 'meta_' . $box['id'] ), $kit->name( 'nonce_' . $box['id'] ) );
		$form = Form::markup(
			array(
				'fields'  => $box['fields'],
				'storage' => self::storage( $kit, $box, $post_id ),
				'set'     => $box['id'],
				'errors'  => $messages['errors'],
				'notes'   => $messages['notes'],
				'scoped'  => true,
			)
		);
		echo $form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the form.
	}

	/**
	 * Saves the box: nonce, no autosave or revision, edit rights, then the fields.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $box     Box configuration.
	 * @param int                  $post_id Post ID.
	 */
	private static function save( Kit $kit, array $box, int $post_id ): void {
		$nonce = $kit->name( 'nonce_' . $box['id'] );

		if ( ! isset( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) ), $kit->name( 'meta_' . $box['id'] ) ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! in_array( get_post_type( $post_id ), (array) $box['post_types'], true ) ) {
			return;
		}

		$storage = self::storage( $kit, $box, $post_id );
		if ( ! $storage->can_edit() || ! self::box_allowed( $box ) ) {
			return;
		}

		$storage->save_posted( (string) $box['id'], 'post:' . $box['id'] . ':' . $post_id );
	}
}
