<?php
/**
 * One record's values, for a drawer form.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

use BlissWebConcept\AdminKit\Field\Form;

defined( 'ABSPATH' ) || exit;

/**
 * Holds a record's values (or a draft of what was typed) so the Form can
 * print them. Saving goes through the list's own save callback, not here.
 */
final class RecordStorage implements Storage {

	/**
	 * Constructor.
	 *
	 * @param string               $name_base Base of the input names.
	 * @param array<string, mixed> $record    Values by field id.
	 * @param bool                 $editable  Whether the user may change them.
	 */
	public function __construct( private string $name_base, private array $record, private bool $editable = true ) {}

	/**
	 * "record".
	 */
	public function context(): string {
		return 'record';
	}

	/**
	 * Base of every input name.
	 */
	public function name_base(): string {
		return $this->name_base;
	}

	/**
	 * The record's values over the fields' defaults.
	 *
	 * @param string                     $set    Set id (unused: one record).
	 * @param list<array<string, mixed>> $fields Field descriptions.
	 * @return array<string, mixed>
	 */
	public function values( string $set, array $fields ): array {
		return array_merge( Form::defaults( $fields ), $this->record );
	}

	/**
	 * Keeps values in memory; the list's save callback stores them.
	 *
	 * @param string               $set    Set id.
	 * @param array<string, mixed> $values Clean values.
	 */
	public function write( string $set, array $values ): bool {
		$this->record = array_merge( $this->record, $values );

		return true;
	}

	/**
	 * Whether the current user may change the record.
	 */
	public function can_edit(): bool {
		return $this->editable;
	}
}
