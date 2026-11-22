<?php
/**
 * Renders a set of fields and turns what was posted into values to save.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

use BlissWebConcept\AdminKit\Component\Notice;
use BlissWebConcept\AdminKit\Kit;
use BlissWebConcept\AdminKit\Storage\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Form. Names: {base}[{set}][{id}];
 * markers: {base}[_shown][{set}][{id}]; ids: {kit id}-{set}-{field id}.
 */
final class Form {

	/**
	 * A set of fields with their values and any messages.
	 *
	 * @param array<string, mixed> $props fields, storage (Storage), set, errors, notes, layout ("stack" or "table"), scoped (add the kit's root classes), summary (false when the screen lists the errors itself), summary_text (the summary's own words, such as a drawer's "Not saved: 1 field needs fixing.").
	 */
	public static function markup( array $props ): string {
		$kit     = Kit::instance();
		$storage = $props['storage'] ?? null;

		if ( ! $storage instanceof Storage || empty( $props['set'] ) ) {
			return '';
		}

		$set    = sanitize_key( (string) $props['set'] );
		$fields = self::normalize( (array) ( $props['fields'] ?? array() ) );
		$values = $storage->values( $set, $fields );
		$errors = (array) ( $props['errors'] ?? array() );
		$notes  = (array) ( $props['notes'] ?? array() );
		$layout = 'table' === ( $props['layout'] ?? 'stack' ) ? 'table' : 'stack';
		$scope  = empty( $props['scoped'] ) ? '' : $kit->root_classes();

		$html = '';
		foreach ( $fields as $field ) {
			// A radio option's own field is printed inside that option, by the radio.
			if ( '' !== (string) $field['inline_of'] ) {
				continue;
			}
			$html .= self::field( $kit, $field, $values, $storage, $set, $errors, $notes, $layout, 'table' === $layout ? $scope : '' );
		}

		if ( 'table' === $layout ) {
			return $html;
		}

		$summary = false === ( $props['summary'] ?? true ) ? '' : self::summary( array_intersect_key( $errors, self::labels( $fields, $values ) ), self::labels( $fields, $values ), $kit->id() . '-' . $set, (string) ( $props['summary_text'] ?? '' ) );

		return '<div class="' . esc_attr( trim( 'bwc-fields ' . $scope ) ) . '" data-bwc-fields="' . esc_attr( $set ) . '">' . $summary . $html . '</div>';
	}

	/**
	 * "2 settings weren't saved. See the messages below.", with a link to each.
	 *
	 * @param array<string, string> $errors  Field id => message.
	 * @param array<string, string> $labels  Field id => label.
	 * @param string                $id_base "{kit id}-{set}".
	 * @param string                $text    Its own words instead of "N settings weren't saved…".
	 */
	public static function summary( array $errors, array $labels, string $id_base, string $text = '' ): string {
		if ( ! $errors ) {
			return '';
		}

		$links = array();
		foreach ( array_keys( $errors ) as $field ) {
			$links[] = '<a href="#' . esc_attr( $id_base . '-' . $field ) . '">' . esc_html( $labels[ $field ] ?? (string) $field ) . '</a>';
		}

		return Notice::markup(
			array(
				'tone'         => 'danger',
				'text'         => '' !== $text ? $text : Kit::instance()->strings()->count( 'fields_not_saved', count( $errors ) ),
				'actions_html' => '<span class="bwc-fields__jump">' . implode( ' ', $links ) . '</span>',
			)
		);
	}

	/**
	 * Labels of the active fields, by id (an inactive field reports no error).
	 *
	 * @param list<array<string, mixed>> $fields Normalised descriptions.
	 * @param array<string, mixed>       $values The set's values.
	 * @return array<string, string>
	 */
	private static function labels( array $fields, array $values ): array {
		$labels = array();
		foreach ( $fields as $field ) {
			if ( self::active( $field, $values ) ) {
				$labels[ (string) $field['id'] ] = (string) $field['label'];
			}
		}

		return $labels;
	}

	/**
	 * One field through its type and the wrapper.
	 *
	 * @param Kit                   $kit     The kit.
	 * @param array<string, mixed>  $field   Normalised description.
	 * @param array<string, mixed>  $values  The set's values.
	 * @param Storage               $storage Storage.
	 * @param string                $set     Set id.
	 * @param array<string, string> $errors  Errors by field id.
	 * @param array<string, string> $notes   Notes by field id.
	 * @param string                $layout  stack or table.
	 * @param string                $scope   Root classes for table rows.
	 */
	private static function field( Kit $kit, array $field, array $values, Storage $storage, string $set, array $errors, array $notes, string $layout, string $scope ): string {
		$type = $kit->fields()->get( (string) $field['type'] );
		if ( null === $type ) {
			return '';
		}

		$fid      = (string) $field['id'];
		$id       = $kit->id() . '-' . $set . '-' . $fid;
		$name     = $storage->name_base() . '[' . $set . '][' . $fid . ']';
		$readonly = ! empty( $field['readOnly'] );
		$active   = self::active( $field, $values );
		$error    = $active ? (string) ( $errors[ $fid ] ?? '' ) : '';
		$note     = (string) ( $notes[ $fid ] ?? '' );
		$help     = array(
			is_string( $field['readOnly'] ) ? $field['readOnly'] : '',
			(string) $field['description'],
			$type->help( $field ),
		);
		$has_help = '' !== implode( '', $help );

		$describedby = implode(
			' ',
			array_filter(
				array(
					'' === (string) $field['unit'] ? '' : $id . '-unit',
					$has_help ? $id . '-help' : '',
					'' === $error ? '' : $id . '-error',
					'' === $note ? '' : $id . '-note',
				)
			)
		);

		$context = new Context( $kit, $field, $values[ $fid ] ?? $field['default'], $name, $id, $describedby, '' !== $error, $readonly, $storage->context(), $values );

		$depends = array();
		$rule    = self::rule( $field );
		if ( null !== $rule ) {
			$depends['data-bwc-depends'] = $set . '.' . $rule['field'];
			if ( isset( $rule['is'] ) ) {
				$depends['data-bwc-depends-is'] = (string) wp_json_encode( array_map( 'strval', $rule['is'] ) );
			}
			if ( isset( $rule['not'] ) ) {
				$depends['data-bwc-depends-not'] = (string) wp_json_encode( array_map( 'strval', $rule['not'] ) );
			}
		}

		return Wrapper::markup(
			$context,
			$type->kind( $field ),
			array(
				'control'  => $type->control( $context ),
				'help'     => $help,
				'error'    => $error,
				'note'     => $note,
				'inactive' => ! $active,
				'depends'  => $depends,
				'marker'   => $type->needs_marker( $field ) && ! $readonly ? '<input type="hidden" name="' . esc_attr( $storage->name_base() . '[_shown][' . $set . '][' . $fid . ']' ) . '" value="1">' : '',
				'layout'   => $layout,
				'path'     => $set . '.' . $fid,
				'classes'  => $scope,
			)
		);
	}

	/**
	 * Turns a posted set into values to save, with messages.
	 *
	 * Fields not on the page are left alone: a field with no posted value is
	 * only read when its _shown marker is there (an unticked checkbox). Read-only
	 * fields keep their stored value. Rejected values keep the stored value.
	 * Inactive fields are saved as typed but report no errors.
	 *
	 * @param list<array<string, mixed>> $fields  Field descriptions.
	 * @param string                     $set     Set id.
	 * @param array<string, mixed>       $posted  Everything posted under the name base, unslashed.
	 * @param array<string, mixed>       $current The set's values now, with defaults.
	 * @return array{values: array<string, mixed>, errors: array<string, string>, notes: array<string, string>}
	 */
	public static function sanitize( array $fields, string $set, array $posted, array $current ): array {
		$registry  = Kit::instance()->fields();
		$submitted = isset( $posted[ $set ] ) && is_array( $posted[ $set ] ) ? $posted[ $set ] : array();
		$markers   = isset( $posted['_shown'][ $set ] ) && is_array( $posted['_shown'][ $set ] ) ? $posted['_shown'][ $set ] : array();
		$results   = array();

		foreach ( self::normalize( $fields ) as $field ) {
			$fid  = (string) $field['id'];
			$type = $registry->get( (string) $field['type'] );

			if ( null === $type || ! empty( $field['readOnly'] ) ) {
				continue;
			}

			if ( array_key_exists( $fid, $submitted ) ) {
				$raw = $submitted[ $fid ];
			} elseif ( $type->needs_marker( $field ) && ! empty( $markers[ $fid ] ) ) {
				$raw = null;
			} else {
				continue;
			}

			$results[ $fid ] = array( $field, $type->sanitize( $raw, $current[ $fid ] ?? $field['default'], $field, $submitted ) );
		}

		$clean = $current;
		foreach ( $results as $fid => [ , $result ] ) {
			$clean[ $fid ] = $result->value;
		}

		$out = array(
			'values' => array(),
			'errors' => array(),
			'notes'  => array(),
		);

		foreach ( $results as $fid => [ $field, $result ] ) {
			$active = self::active( $field, $clean );
			$kept   = $current[ $fid ] ?? $field['default'];

			if ( $result->saved() && $active ) {
				$problem = Validator::check( $field, $result->value, $clean );
				if ( null !== $problem ) {
					$result = Result::rejected( $kept, $problem );
				}
			}

			if ( $result->saved() ) {
				$out['values'][ $fid ] = $result->value;
			}

			if ( '' !== $result->message && $active ) {
				$out[ $result->error ? 'errors' : 'notes' ][ $fid ] = $result->message;
			}
		}

		return $out;
	}

	/**
	 * The fields without the secret ones, where a secret can't work: the
	 * Secrets service keeps one key per name for the whole site, so in a
	 * meta box, a term or profile form, a list's drawer or a repeater row
	 * every post, term, user or row would share it.
	 * Each one left out raises a message (an exception while debugging).
	 *
	 * @param array<int|string, mixed> $fields   Field descriptions.
	 * @param string                   $where    Where they are, for the message: "a meta box".
	 * @param bool                     $settings True for settings, where a secret field may stand on its own.
	 * @return list<mixed>
	 */
	public static function without_secrets( array $fields, string $where, bool $settings = false ): array {
		$registry = Kit::instance()->fields();
		$out      = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				$out[] = $field;
				continue;
			}

			$type = $registry->get( (string) ( $field['type'] ?? '' ) );
			if ( $type instanceof Type\Secret && ! $settings ) {
				self::refuse_secret( $field, $where );
				continue;
			}

			// Every row of a repeater would share the one secret, wherever the repeater is.
			if ( $type instanceof Type\Repeater && isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$field['fields'] = self::without_secrets( $field['fields'], 'a repeater' );
			}

			if ( $type instanceof Type\Radio && ! $settings && isset( $field['elements'] ) && is_array( $field['elements'] ) ) {
				foreach ( $field['elements'] as $index => $element ) {
					if ( is_array( $element ) && is_array( $element['field'] ?? null ) && $registry->get( (string) ( $element['field']['type'] ?? '' ) ) instanceof Type\Secret ) {
						self::refuse_secret( $element['field'], $where );
						unset( $field['elements'][ $index ]['field'] );
					}
				}
			}

			$out[] = $field;
		}

		return $out;
	}

	/**
	 * Says a secret field was left out: an exception while debugging.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @param string               $where Where it was described.
	 * @throws \InvalidArgumentException While debugging.
	 */
	private static function refuse_secret( array $field, string $where ): void {
		$message = 'Admin Kit field: the secret field "' . (string) ( $field['id'] ?? '' ) . "\" can't go in {$where}: a secret is one key for the whole site, so put it on a settings screen. It was left out.";
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			throw new \InvalidArgumentException( esc_html( $message ) );
		}
		_doing_it_wrong( 'Admin Kit field', esc_html( $message ), esc_html( Kit::VERSION ) );
	}

	/**
	 * Normalised descriptions; unusable ones left out.
	 *
	 * @param array<int|string, mixed> $fields Field descriptions.
	 * @return list<array<string, mixed>>
	 */
	public static function normalize( array $fields ): array {
		$registry = Kit::instance()->fields();
		$out      = array();

		foreach ( $fields as $field ) {
			$normal = is_array( $field ) ? $registry->normalize( $field ) : array();
			if ( ! $normal ) {
				continue;
			}
			$out[] = $normal;

			// A radio option's own field ("Give partial credit from [80] %") is a field of
			// its own, saved under its own key, active only while that option is chosen.
			if ( 'radio' === $normal['type'] && is_array( $normal['elements'] ) ) {
				foreach ( $normal['elements'] as $element ) {
					if ( is_array( $element ) && isset( $element['value'], $element['field'] ) && is_array( $element['field'] ) ) {
						$inline = $registry->normalize(
							$element['field'] + array(
								'label'     => (string) ( $element['label'] ?? $element['value'] ),
								'dependsOn' => array(
									'field' => (string) $normal['id'],
									'is'    => array( (string) $element['value'] ),
								),
								'inline_of' => (string) $normal['id'],
							)
						);
						if ( $inline ) {
							$out[] = $inline;
						}
					}
				}
			}
		}

		return $out;
	}

	/**
	 * The fields' defaults, by id.
	 *
	 * @param list<array<string, mixed>> $fields Field descriptions.
	 * @return array<string, mixed>
	 */
	public static function defaults( array $fields ): array {
		$defaults = array();
		foreach ( self::normalize( $fields ) as $field ) {
			$defaults[ (string) $field['id'] ] = $field['default'];
		}

		return $defaults;
	}

	/**
	 * A field's dependency as field / is / not, or null.
	 *
	 * @param array<string, mixed> $field Field description.
	 * @return array{field: string, is?: list<mixed>, not?: list<mixed>}|null
	 */
	private static function rule( array $field ): ?array {
		$depends = $field['dependsOn'] ?? null;

		if ( is_string( $depends ) && '' !== $depends ) {
			return array( 'field' => $depends );
		}

		if ( is_array( $depends ) && ! empty( $depends['field'] ) ) {
			$rule = array( 'field' => (string) $depends['field'] );
			if ( isset( $depends['is'] ) ) {
				$rule['is'] = array_values( (array) $depends['is'] );
			}
			if ( isset( $depends['not'] ) ) {
				$rule['not'] = array_values( (array) $depends['not'] );
			}
			return $rule;
		}

		return null;
	}

	/**
	 * Whether a field is active: no dependency, or its condition holds.
	 * A plain dependency means "while that box or switch is on".
	 *
	 * @param array<string, mixed> $field  Field description.
	 * @param array<string, mixed> $values The set's values.
	 */
	public static function active( array $field, array $values ): bool {
		$rule = self::rule( $field );
		if ( null === $rule ) {
			return true;
		}

		$value = $values[ $rule['field'] ] ?? null;
		$have  = array_map( 'strval', is_array( $value ) ? $value : array( is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value ) );

		if ( isset( $rule['is'] ) ) {
			return (bool) array_intersect( $have, array_map( 'strval', $rule['is'] ) );
		}

		if ( isset( $rule['not'] ) ) {
			return ! array_intersect( $have, array_map( 'strval', $rule['not'] ) );
		}

		return ! empty( $value );
	}
}
