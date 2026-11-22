<?php
/**
 * Pick one or several user roles.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "role": a select (or boxes with multiple)
 * of the site's roles, minus the ones the field excludes.
 */
class Role extends Select {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'role';
	}

	/**
	 * Extra keys: multiple, exclude.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'default'  => '',
			'multiple' => false,
			'exclude'  => array(),
		);
	}

	/**
	 * The site's roles, translated.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array<int|string, string>
	 */
	protected function options( array $field ): array {
		$roles = array();
		foreach ( wp_roles()->get_names() as $slug => $name ) {
			if ( ! in_array( $slug, (array) $field['exclude'], true ) ) {
				$roles[ (string) $slug ] = translate_user_role( (string) $name );
			}
		}

		return $roles;
	}
}
