<?php
/**
 * Where a form's values live.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Storage adapter. One field description renders
 * and saves the same way on a settings screen, a meta box, a term form and a
 * profile; the adapter says where the values live and who may change them.
 * A "set" is a settings bucket, a meta box, a term field set or a profile section.
 */
interface Storage {

	/**
	 * "settings", "post", "term" or "user".
	 */
	public function context(): string;

	/**
	 * Base of every input name: the option name, or "{hook}_bwc".
	 */
	public function name_base(): string;

	/**
	 * The set's values: stored ones over the fields' defaults.
	 *
	 * @param string                           $set    Set id.
	 * @param list<array<string, mixed>>       $fields Field descriptions.
	 * @return array<string, mixed>
	 */
	public function values( string $set, array $fields ): array;

	/**
	 * Saves values, merged over what's stored: keys not given are untouched.
	 *
	 * @param string               $set    Set id.
	 * @param array<string, mixed> $values Clean values by field id.
	 */
	public function write( string $set, array $values ): bool;

	/**
	 * Whether the current user may change these values.
	 */
	public function can_edit(): bool;
}
