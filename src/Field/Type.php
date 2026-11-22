<?php
/**
 * What every field type does.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Field type. Built-in types extend BaseType; a
 * plugin's own types implement this and register with $kit->fields()->register().
 */
interface Type {

	/**
	 * The `type` string fields use, such as "length".
	 */
	public function name(): string;

	/**
	 * Extra description keys this type accepts, with their defaults, including "default".
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array;

	/**
	 * How the wrapper lays the field out: "input" (a label for one control),
	 * "group" (a fieldset with a legend) or "check" (the control holds its own label).
	 *
	 * @param array<string, mixed> $field Field description, normalised.
	 */
	public function kind( array $field ): string;

	/**
	 * The control's escaped HTML. The wrapper adds the label, unit, help, error and marker.
	 *
	 * @param Context $context The field, its value, name, id and state.
	 */
	public function control( Context $context ): string;

	/**
	 * Help the type adds after the field's own description ("In the site's timezone (UTC).").
	 *
	 * @param array<string, mixed> $field Field description.
	 */
	public function help( array $field ): string;

	/**
	 * True when the empty value posts nothing (checkboxes), so the form adds a _shown marker.
	 *
	 * @param array<string, mixed> $field Field description, normalised.
	 */
	public function needs_marker( array $field ): bool;

	/**
	 * Turns submitted input into the stored value. $raw is null when the field
	 * had a marker but nothing was posted (an unticked checkbox).
	 *
	 * @param mixed                $raw    Submitted value, unslashed.
	 * @param mixed                $stored Value stored now, with its default.
	 * @param array<string, mixed> $field  Field description, normalised.
	 * @param array<string, mixed> $values The form's other submitted values.
	 */
	public function sanitize( mixed $raw, mixed $stored, array $field, array $values ): Result;

	/**
	 * JSON Schema for the REST API, or null to keep the field out of it.
	 *
	 * @param array<string, mixed> $field Field description, normalised.
	 * @return array<string, mixed>|null
	 */
	public function schema( array $field ): ?array;

	/**
	 * Script modules this type needs on the page.
	 *
	 * @return list<string>
	 */
	public function scripts(): array;
}
