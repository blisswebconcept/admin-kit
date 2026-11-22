<?php
/**
 * Pick users, searching.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field\Type;

use BlissWebConcept\AdminKit\Component\Html;
use BlissWebConcept\AdminKit\Field\Context;
use BlissWebConcept\AdminKit\Field\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Field type "user". Searches login, email and
 * display name; needs list_users. Without scripts it's a list of usernames
 * or emails, one per line (never a select of every user).
 */
class UserPicker extends Picker {

	/**
	 * Type name.
	 */
	public function name(): string {
		return 'user';
	}

	/**
	 * Listing users.
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function capability( array $field ): string {
		return 'list_users';
	}

	/**
	 * One page of matches.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param string               $q     Search text.
	 * @param int                  $page  Page.
	 * @return array{items: list<array{value: int, label: string, detail: string, depth: int}>, more: bool}
	 */
	public function search( array $field, string $q, int $page ): array {
		$args = array(
			'number'      => self::PAGE,
			'paged'       => max( 1, $page ),
			'orderby'     => 'display_name',
			'count_total' => true,
		);
		if ( '' !== $q ) {
			$args['search']         = '*' . $q . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$roles = (array) ( $field['query']['role__in'] ?? array() );
		if ( $roles ) {
			$args['role__in'] = array_map( 'strval', $roles );
		}

		$found = new \WP_User_Query( $args );
		$items = array();
		foreach ( (array) $found->get_results() as $user ) {
			if ( $user instanceof \WP_User ) {
				$items[] = array(
					'value'  => (int) $user->ID,
					'label'  => (string) $user->display_name,
					'detail' => current_user_can( 'list_users' ) ? (string) $user->user_email : '',
					'depth'  => 0,
				);
			}
		}

		return array(
			'items' => $items,
			'more'  => $found->get_total() > $page * self::PAGE,
		);
	}

	/**
	 * Display names of chosen users who still exist.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param list<int>            $ids   Ids.
	 * @return array<int, string>
	 */
	public function labels( array $field, array $ids ): array {
		$labels = array();
		foreach ( $ids as $id ) {
			$user = get_userdata( (int) $id );
			if ( $user ) {
				$labels[ (int) $user->ID ] = (string) $user->display_name;
			}
		}

		return $labels;
	}

	/**
	 * Without scripts: usernames, one per line.
	 *
	 * @param Context   $context Field context.
	 * @param list<int> $chosen  Chosen ids.
	 */
	protected function fallback( Context $context, array $chosen ): string {
		$lines = array();
		foreach ( $chosen as $id ) {
			$user = get_userdata( $id );
			if ( $user ) {
				$lines[] = (string) $user->user_login;
			}
		}

		return '<textarea' . Html::attributes(
			array_merge(
				array(
					'class'                    => 'bwc-control bwc-control--textarea bwc-control--list bwc-control--full bwc-control--mono',
					'rows'                     => empty( $context->field['multiple'] ) ? '1' : '4',
					'data-bwc-picker-fallback' => true,
				),
				$context->attributes()
			)
		) . '>' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
	}

	/**
	 * Ids from the search box, or usernames and emails from the list.
	 *
	 * @param mixed                $raw    Submitted value.
	 * @param mixed                $stored Stored value.
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values Other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result {
		if ( ! is_string( $raw ) || ctype_digit( $raw ) || '' === $raw ) {
			return parent::sanitize( $raw, $stored, $field, $values );
		}

		[ $ids, $unknown ] = self::from_lines( $raw );
		$result            = parent::sanitize( $ids, $stored, $field, $values );

		return $unknown ? Result::partial( $result->value, self::say( 'field_users_unknown', implode( ', ', $unknown ) ) ) : $result;
	}

	/**
	 * Users named by login or email, one per line or comma.
	 *
	 * @param string $text Lines.
	 * @return array{0: list<int>, 1: list<string>} Ids found, lines with no account.
	 */
	public static function from_lines( string $text ): array {
		$ids     = array();
		$unknown = array();
		foreach ( (array) preg_split( '/[\r\n,]+/', $text ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$user = is_email( $line ) ? get_user_by( 'email', $line ) : get_user_by( 'login', $line );
			if ( $user ) {
				$ids[] = (int) $user->ID;
			} else {
				$unknown[] = $line;
			}
		}

		return array( array_values( array_unique( $ids ) ), $unknown );
	}
}
