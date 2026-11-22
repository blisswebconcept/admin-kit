<?php
/**
 * Fields in a section of the user profile screen.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;
use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * User meta. UserMeta::fields() adds a titled section to
 * profile screens and saves it. "who": self (people edit their own), admins
 * (users who can edit_users, on any profile) or both. Not in the REST API
 * unless asked: user data is personal.
 */
final class UserMeta extends MetaStorage {

	/**
	 * User meta.
	 */
	protected function meta_type(): string {
		return 'user';
	}

	/**
	 * Allowed to edit this user.
	 */
	public function can_edit(): bool {
		return current_user_can( 'edit_user', $this->object_id );
	}

	/**
	 * Adds a section to profile screens.
	 *
	 * @param Kit                  $kit    The kit.
	 * @param array<string, mixed> $config id, title, fields, who (self|admins|both), capability, prefix, show_in_rest.
	 */
	public static function fields( Kit $kit, array $config ): void {
		$set           = array_merge(
			array(
				'who'          => 'admins',
				'capability'   => '',
				'prefix'       => $kit->hook(),
				'show_in_rest' => false,
			),
			$config
		);
		$set['id']     = sanitize_key( (string) $set['id'] );
		$set['fields'] = Form::without_secrets( (array) $set['fields'], 'a profile form' );

		add_action( 'show_user_profile', static fn( $user ) => self::render( $kit, $set, (int) $user->ID ) );
		add_action( 'edit_user_profile', static fn( $user ) => self::render( $kit, $set, (int) $user->ID ) );
		add_action( 'personal_options_update', static fn( $user_id ) => self::save( $kit, $set, (int) $user_id ) );
		add_action( 'edit_user_profile_update', static fn( $user_id ) => self::save( $kit, $set, (int) $user_id ) );

		$kit->assets()->load_when( static fn( string $hook ): bool => in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) );

		self::on_init(
			static function () use ( $kit, $set ): void {
				// The same test as the form: who may change it (self, admins, both) and the set's capability.
				self::register_keys( $kit, 'user', '', (array) $set['fields'], (string) $set['prefix'], (bool) $set['show_in_rest'], static fn( $allowed, $key, $user_id ): bool => self::allowed( $set, (int) $user_id ) );

				$kit->fields()->allow_search(
					(array) $set['fields'],
					static fn(): bool => ( 'admins' === (string) $set['who'] ? current_user_can( 'edit_users' ) : is_user_logged_in() )
						&& ( '' === $set['capability'] || current_user_can( (string) $set['capability'] ) )
				);
			}
		);
	}

	/**
	 * Whether the current user may see and change this section on this profile.
	 *
	 * @param array<string, mixed> $set     Configuration.
	 * @param int                  $user_id Profile being edited.
	 */
	public static function allowed( array $set, int $user_id ): bool {
		$own = get_current_user_id() === $user_id;

		$who = match ( (string) $set['who'] ) {
			'self'  => $own,
			'both'  => $own || current_user_can( 'edit_users' ),
			default => current_user_can( 'edit_users' ),
		};

		return $who
			&& current_user_can( 'edit_user', $user_id )
			&& ( '' === $set['capability'] || current_user_can( (string) $set['capability'] ) );
	}

	/**
	 * The section: a heading and a form table.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $set     Configuration.
	 * @param int                  $user_id Profile user.
	 */
	private static function render( Kit $kit, array $set, int $user_id ): void {
		if ( ! self::allowed( $set, $user_id ) ) {
			return;
		}

		$messages = Kit::instance()->notices()->take_fields( 'user:' . $set['id'] . ':' . $user_id );

		echo '<h2>' . esc_html( (string) $set['title'] ) . '</h2>';
		wp_nonce_field( $kit->name( 'user_' . $set['id'] ), $kit->name( 'nonce_' . $set['id'] ) );
		$rows = Form::markup(
			array(
				'fields'  => $set['fields'],
				'storage' => new self( $kit, $user_id, (array) $set['fields'], (string) $set['prefix'] ),
				'set'     => $set['id'],
				'errors'  => $messages['errors'],
				'notes'   => $messages['notes'],
				'layout'  => 'table',
				'scoped'  => true,
			)
		);
		echo '<table class="form-table" role="presentation"><tbody>' . $rows . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the form.
	}

	/**
	 * Saves the section: nonce and rights first.
	 *
	 * @param Kit                  $kit     The kit.
	 * @param array<string, mixed> $set     Configuration.
	 * @param int                  $user_id Profile user.
	 */
	private static function save( Kit $kit, array $set, int $user_id ): void {
		$nonce = $kit->name( 'nonce_' . $set['id'] );

		if ( ! isset( $_POST[ $nonce ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce ] ) ), $kit->name( 'user_' . $set['id'] ) ) ) {
			return;
		}

		if ( ! self::allowed( $set, $user_id ) ) {
			return;
		}

		( new self( $kit, $user_id, (array) $set['fields'], (string) $set['prefix'] ) )->save_posted( (string) $set['id'], 'user:' . $set['id'] . ':' . $user_id );
	}
}
