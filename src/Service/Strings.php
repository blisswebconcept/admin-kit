<?php
/**
 * The words the kit prints itself.
 *
 * @package BlissWebConcept\AdminKit
 */

namespace BlissWebConcept\AdminKit\Service;

use BlissWebConcept\AdminKit\Kit;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults are translatable in the kit's own text domain. A plugin overrides
 * any key through Kit::boot( array( 'strings' => … ) ) or the filter
 * "{hook}_bwc_strings".
 */
final class Strings {

	/**
	 * Resolved strings, built on first use. A plural is WordPress's nooped
	 * plural (from _n_noop()) or, when a plugin overrides it, [singular, plural].
	 *
	 * @var array<string, string|array<int|string, mixed>>|null
	 */
	private ?array $strings = null;

	/**
	 * Constructor.
	 *
	 * @param Kit $kit Kit.
	 */
	public function __construct( private Kit $kit ) {}

	/**
	 * The kit's defaults. Plurals are nooped plurals, translated when used.
	 *
	 * @return array<string, string|array<int|string, mixed>>
	 */
	public static function defaults(): array {
		return array(
			'save'                      => __( 'Save changes', 'bwc-admin-kit' ),
			'discard'                   => __( 'Discard', 'bwc-admin-kit' ),
			'saved'                     => __( 'All changes saved', 'bwc-admin-kit' ),
			'unsaved'                   => __( 'You have unsaved changes', 'bwc-admin-kit' ),
			'settings_saved'            => __( 'Settings saved.', 'bwc-admin-kit' ),
			'find_setting'              => __( 'Find a setting', 'bwc-admin-kit' ),
			'no_setting_match'          => __( 'No setting matches that. Try another word, or pick a group on the left.', 'bwc-admin-kit' ),
			/* translators: 1: features turned on, 2: features in the group. */
			'on_count'                  => __( '%1$d of %2$d on', 'bwc-admin-kit' ),
			/* translators: %d: number of settings. */
			'turn_on_to_change'         => _n_noop( 'Turn on to change its %d setting.', 'Turn on to change its %d settings.', 'bwc-admin-kit' ),
			'paused'                    => __( 'Paused', 'bwc-admin-kit' ),
			'advanced'                  => __( 'Advanced', 'bwc-admin-kit' ),
			/* translators: 1: first row shown, 2: last row shown, 3: rows in all. */
			'showing_range'             => __( 'Showing %1$s–%2$s of %3$s', 'bwc-admin-kit' ),
			'nothing_to_show'           => __( 'Nothing to show.', 'bwc-admin-kit' ),
			'clear_filters'             => __( 'Clear filters', 'bwc-admin-kit' ),
			'filter'                    => __( 'Filter', 'bwc-admin-kit' ),
			'previous_page'             => __( 'Previous page', 'bwc-admin-kit' ),
			'next_page'                 => __( 'Next page', 'bwc-admin-kit' ),
			'first_page'                => __( 'First page', 'bwc-admin-kit' ),
			'last_page'                 => __( 'Last page', 'bwc-admin-kit' ),
			'current_page'              => __( 'Current page', 'bwc-admin-kit' ),
			/* translators: 1: the box with the current page number, 2: number of pages. */
			'page_of'                   => _x( '%1$s of %2$s', 'paging', 'bwc-admin-kit' ),
			/* translators: 1: current page, 2: number of pages. */
			'page_n_of'                 => __( 'Page %1$s of %2$s', 'bwc-admin-kit' ),
			/* translators: %d: rows per page. */
			'per_page'                  => __( '%d per page', 'bwc-admin-kit' ),
			'export_csv'                => __( 'Export CSV', 'bwc-admin-kit' ),
			'apply'                     => __( 'Apply', 'bwc-admin-kit' ),
			'bulk_actions'              => __( 'Bulk actions', 'bwc-admin-kit' ),
			'copy'                      => __( 'Copy', 'bwc-admin-kit' ),
			'copied'                    => __( 'Copied', 'bwc-admin-kit' ),
			'stop'                      => __( 'Stop', 'bwc-admin-kit' ),
			'retrying'                  => __( 'Lost touch with the server. Trying again.', 'bwc-admin-kit' ),
			'job_done'                  => __( 'Done. Loading the results.', 'bwc-admin-kit' ),
			/* translators: %d: number of items that failed. */
			'retry_failed'              => _n_noop( 'Try the %d that failed again', 'Try the %d that failed again', 'bwc-admin-kit' ),
			/* translators: %s: how long ago, such as "5 minutes". */
			'data_read'                 => __( 'Data read %s ago', 'bwc-admin-kit' ),
			'refresh'                   => __( 'Refresh data', 'bwc-admin-kit' ),
			'secret_unreadable'         => __( 'Saved, but can\'t be read since the site\'s security keys changed. Enter it again.', 'bwc-admin-kit' ),
			'remove'                    => __( 'Remove', 'bwc-admin-kit' ),
			'close'                     => __( 'Close', 'bwc-admin-kit' ),
			'cancel'                    => __( 'Cancel', 'bwc-admin-kit' ),
			'confirm_delete'            => __( 'Delete this? It can\'t be undone.', 'bwc-admin-kit' ),
			'back'                      => __( 'Back', 'bwc-admin-kit' ),
			'no_longer_exists'          => __( 'That item no longer exists.', 'bwc-admin-kit' ),
			'badge_attention'           => __( 'to check', 'bwc-admin-kit' ),
			'badge_waiting'             => __( 'waiting', 'bwc-admin-kit' ),
			'paused_prefix'             => __( 'Paused:', 'bwc-admin-kit' ),
			'hide_notice'               => __( 'Hide this notice', 'bwc-admin-kit' ),
			/* translators: %s: version number. */
			'version'                   => __( 'Version %s', 'bwc-admin-kit' ),
			/* translators: %s: version number, shown short in the screen's header bar. */
			'version_short'             => __( 'v%s', 'bwc-admin-kit' ),
			'on'                        => __( 'On', 'bwc-admin-kit' ),
			'off'                       => __( 'Off', 'bwc-admin-kit' ),
			'pages'                     => __( 'Pages', 'bwc-admin-kit' ),
			'newer'                     => __( 'Newer', 'bwc-admin-kit' ),
			'older'                     => __( 'Older', 'bwc-admin-kit' ),
			'select_all'                => __( 'Select all', 'bwc-admin-kit' ),
			/* translators: %s: what the row is, such as a link or offer name. */
			'select_row'                => __( 'Select %s', 'bwc-admin-kit' ),
			/* translators: %s: column name. */
			'sort_ascending'            => __( 'Sort by %s, ascending', 'bwc-admin-kit' ),
			/* translators: %s: column name. */
			'sort_descending'           => __( 'Sort by %s, descending', 'bwc-admin-kit' ),
			'expand_all'                => __( 'Expand all', 'bwc-admin-kit' ),
			'collapse_all'              => __( 'Collapse all', 'bwc-admin-kit' ),
			/* translators: %s: what's folded, such as "14 offer links". */
			'show_more'                 => __( 'Show %s', 'bwc-admin-kit' ),
			/* translators: %s: what's folded, such as "14 offer links". */
			'hide_more'                 => __( 'Hide %s', 'bwc-admin-kit' ),
			'copy_failed'               => __( 'Couldn\'t copy. Select the text and copy it yourself.', 'bwc-admin-kit' ),
			/* translators: %d: number of entries still shown after filtering. */
			'filter_shown'              => __( '%d shown', 'bwc-admin-kit' ),
			'field_required'            => __( 'This can\'t be left empty.', 'bwc-admin-kit' ),
			'field_required_check'      => __( 'This needs to be ticked.', 'bwc-admin-kit' ),
			/* translators: %s: the lowest number allowed. */
			'field_min'                 => __( 'Use %s or more.', 'bwc-admin-kit' ),
			/* translators: %s: the highest number allowed. */
			'field_max'                 => __( 'Use %s or less.', 'bwc-admin-kit' ),
			/* translators: %d: fewest characters allowed. */
			'field_min_length'          => _n_noop( 'Use at least %d character.', 'Use at least %d characters.', 'bwc-admin-kit' ),
			/* translators: %d: most characters allowed. */
			'field_max_length'          => _n_noop( 'Use %d character or fewer.', 'Use %d characters or fewer.', 'bwc-admin-kit' ),
			'field_pattern'             => __( 'That isn\'t in the format this needs.', 'bwc-admin-kit' ),
			'field_not_number'          => __( 'Enter a number.', 'bwc-admin-kit' ),
			/* translators: %s: the lowest number allowed. */
			'field_clamped_min'         => __( 'Set to %s, the least allowed.', 'bwc-admin-kit' ),
			/* translators: %s: the highest number allowed. */
			'field_clamped_max'         => __( 'Set to %s, the most allowed.', 'bwc-admin-kit' ),
			/* translators: %s: the units allowed, such as "px, rem or %". */
			'field_length'              => __( 'Use a number and one of these units: %s.', 'bwc-admin-kit' ),
			/* translators: %s: the units allowed, such as "px, rem or %". */
			'field_length_help'         => __( 'A number and %s.', 'bwc-admin-kit' ),
			'field_email'               => __( 'That isn\'t an email address.', 'bwc-admin-kit' ),
			'field_url'                 => __( 'That isn\'t a web address.', 'bwc-admin-kit' ),
			'field_url_local'           => __( 'Use an address on this site.', 'bwc-admin-kit' ),
			/* translators: %s: the lines that weren't saved. */
			'field_lines_rejected'      => __( 'These lines weren\'t saved: %s.', 'bwc-admin-kit' ),
			/* translators: %d: how many were kept. */
			'field_max_items'           => _n_noop( 'Only the first %d was kept.', 'Only the first %d were kept.', 'bwc-admin-kit' ),
			'field_choice'              => __( 'That choice isn\'t available. Pick one from the list.', 'bwc-admin-kit' ),
			'field_time'                => __( 'Use a time like 09:30.', 'bwc-admin-kit' ),
			/* translators: %s: the site's timezone, such as "America/New_York" or "UTC+1". */
			'field_timezone'            => __( 'In the site\'s timezone (%s).', 'bwc-admin-kit' ),
			/* translators: 1: what the message is about, such as "One", 2: the message. */
			'field_part'                => __( '%1$s: %2$s', 'bwc-admin-kit' ),
			/* translators: 1: all but the last item, joined with commas, 2: the last item. */
			'list_or'                   => __( '%1$s or %2$s', 'bwc-admin-kit' ),
			/* translators: %d: number of settings not saved. */
			'fields_not_saved'          => _n_noop( '%d setting wasn\'t saved. See the message below.', '%d settings weren\'t saved. See the messages below.', 'bwc-admin-kit' ),
			'choose'                    => __( 'Choose…', 'bwc-admin-kit' ),
			'action_unknown'            => __( 'That action isn\'t available any more. Go back and try again.', 'bwc-admin-kit' ),
			'action_not_allowed'        => __( 'Your account isn\'t allowed to do that.', 'bwc-admin-kit' ),
			'settings_not_allowed'      => __( 'Your account isn\'t allowed to change these settings, so nothing was changed.', 'bwc-admin-kit' ),
			'action_expired'            => __( 'That page was open too long, so the action didn\'t run. Go back, reload the page and try again.', 'bwc-admin-kit' ),
			'confirm_title'             => __( 'Are you sure?', 'bwc-admin-kit' ),
			'confirm_yes'               => __( 'Yes, go ahead', 'bwc-admin-kit' ),
			/* translators: %d: number of settings that weren't saved. */
			'settings_not_saved'        => _n_noop( 'Settings saved, except %d that wasn\'t valid. It kept its old value.', 'Settings saved, except %d that weren\'t valid. They kept their old values.', 'bwc-admin-kit' ),
			'settings_groups'           => __( 'Setting groups', 'bwc-admin-kit' ),
			'settings_search_hint'      => __( 'Type a word…', 'bwc-admin-kit' ),
			/* translators: %s: name of another feature, such as "Voting". */
			'requires_section'          => __( 'Needs %s to be on.', 'bwc-admin-kit' ),
			'section_paused'            => __( 'It\'s switched on but won\'t run until this is fixed:', 'bwc-admin-kit' ),
			/* translators: %s: the plugin's name. */
			'sections_label'            => __( '%s sections', 'bwc-admin-kit' ),
			'reference'                 => __( 'Reference', 'bwc-admin-kit' ),
			'search_list'               => __( 'Search', 'bwc-admin-kit' ),
			'all'                       => __( 'All', 'bwc-admin-kit' ),
			/* translators: %s: what the list is filtered by, such as "Status". */
			'filter_all'                => __( '%s: All', 'bwc-admin-kit' ),
			'sort_by'                   => __( 'Sort by', 'bwc-admin-kit' ),
			'rows_per_page'             => __( 'Rows per page', 'bwc-admin-kit' ),
			'nothing_matches'           => __( 'Nothing matches these filters.', 'bwc-admin-kit' ),
			'bulk_choose'               => __( 'Tick at least one row and choose what to do with them.', 'bwc-admin-kit' ),
			/* translators: %d: number of fields that weren't valid. */
			'record_not_saved'          => _n_noop( 'Not saved: %d field needs fixing.', 'Not saved: %d fields need fixing.', 'bwc-admin-kit' ),
			'record_saved'              => __( 'Saved.', 'bwc-admin-kit' ),
			'record_deleted'            => __( 'Deleted.', 'bwc-admin-kit' ),
			'record_not_deleted'        => __( 'It couldn\'t be deleted. Reload the page and try again.', 'bwc-admin-kit' ),
			'delete'                    => __( 'Delete', 'bwc-admin-kit' ),
			/* translators: %d: number of rows folded away. */
			'more_rows'                 => _n_noop( '%d more', '%d more', 'bwc-admin-kit' ),
			'progress'                  => __( 'Progress', 'bwc-admin-kit' ),
			'job_finished'              => __( 'Finished.', 'bwc-admin-kit' ),
			'job_stopped'               => __( 'Stopped. What was done so far is kept.', 'bwc-admin-kit' ),
			'job_skipped'               => __( 'Some items were skipped because working on them kept stopping the request.', 'bwc-admin-kit' ),
			'runs'                      => __( 'Runs', 'bwc-admin-kit' ),
			'not_known'                 => __( 'Not known', 'bwc-admin-kit' ),
			/* translators: %s: the table's caption. Names the box a wide table scrolls in. */
			'table_region'              => __( '%s, table', 'bwc-admin-kit' ),
			/* translators: %s: the run's title. */
			'run_totals'                => __( '%s: totals', 'bwc-admin-kit' ),
			'no_runs'                   => __( 'Nothing has run yet.', 'bwc-admin-kit' ),
			'pick_run'                  => __( 'Pick a run to see what it did.', 'bwc-admin-kit' ),
			'nothing_in_filter'         => __( 'Nothing of this kind in this run.', 'bwc-admin-kit' ),
			'today'                     => __( 'Today', 'bwc-admin-kit' ),
			'yesterday'                 => __( 'Yesterday', 'bwc-admin-kit' ),
			'summary'                   => __( 'Summary', 'bwc-admin-kit' ),
			'show_numbers'              => __( 'Show the numbers', 'bwc-admin-kit' ),
			'estimated'                 => __( '(estimated)', 'bwc-admin-kit' ),
			'chart_when'                => __( 'When', 'bwc-admin-kit' ),
			'chart_value'               => __( 'Count', 'bwc-admin-kit' ),
			'chart_group'               => __( 'Group', 'bwc-admin-kit' ),
			'total'                     => __( 'Total', 'bwc-admin-kit' ),
			/* translators: %s: name of a wp-config.php constant. */
			'secret_in_config'          => __( 'Set in wp-config.php (%s).', 'bwc-admin-kit' ),
			'secret_remove'             => __( 'Remove the saved key', 'bwc-admin-kit' ),
			'secret_keep'               => __( 'Leave it empty to keep the saved key.', 'bwc-admin-kit' ),
			'secret_no_sodium'          => __( 'This site can\'t store keys safely: PHP\'s sodium extension is missing. Ask your host to turn it on.', 'bwc-admin-kit' ),
			/* translators: %s: name of a wp-config.php constant. */
			'secret_no_sodium_constant' => __( 'This site can\'t store keys safely. Add it to wp-config.php as %s instead.', 'bwc-admin-kit' ),
			'picker_search'             => __( 'Search…', 'bwc-admin-kit' ),
			/* translators: %d: number of matches. */
			'picker_found'              => _n_noop( '%d found', '%d found', 'bwc-admin-kit' ),
			'picker_none'               => __( 'Nothing found. Try another word.', 'bwc-admin-kit' ),
			/* translators: %d: how many results are shown. */
			'picker_more'               => __( 'First %d shown. Type to narrow the list.', 'bwc-admin-kit' ),
			'picker_failed'             => __( 'The search didn\'t answer. Try again.', 'bwc-admin-kit' ),
			/* translators: %s: name of a chosen item. */
			'picker_remove'             => __( 'Remove %s', 'bwc-admin-kit' ),
			'picker_chosen'             => __( 'Chosen', 'bwc-admin-kit' ),
			/* translators: %d: number of items listed. */
			'picker_first'              => __( 'Showing the first %d. Turn on JavaScript to search them all.', 'bwc-admin-kit' ),
			/* translators: %s: number of posts. */
			'term_posts'                => _n_noop( '%s post', '%s posts', 'bwc-admin-kit' ),
			'picker_no_title'           => __( '(no title)', 'bwc-admin-kit' ),
			/* translators: 1: item, 2: its new position. */
			'ordered_moved'             => __( '%1$s moved to position %2$d', 'bwc-admin-kit' ),
			/* translators: 1: item, 2: position. */
			'ordered_handle'            => __( 'Move %1$s, position %2$d. Use the arrow keys.', 'bwc-admin-kit' ),
			'ordered_add'               => __( 'Add…', 'bwc-admin-kit' ),
			'ordered_hint'              => __( 'Drag to change the order, or use the arrow keys on a handle.', 'bwc-admin-kit' ),
			/* translators: %s: the choices, joined with commas. */
			'ordered_fallback'          => __( 'One per line, in order. You can use: %s.', 'bwc-admin-kit' ),
			'repeater_add'              => __( 'Add a row', 'bwc-admin-kit' ),
			/* translators: %d: row number. */
			'repeater_row'              => __( 'Row %d', 'bwc-admin-kit' ),
			/* translators: %s: the row's new title, such as "Rule 2". */
			'repeater_moved'            => __( 'Moved. It is now %s.', 'bwc-admin-kit' ),
			/* translators: %s: what was added, such as "Rule 3". */
			'added'                     => __( 'Added %s.', 'bwc-admin-kit' ),
			/* translators: %s: what was removed, such as "Rule 3" or a page title. */
			'removed'                   => __( 'Removed %s.', 'bwc-admin-kit' ),
			'move_up'                   => __( 'Move up', 'bwc-admin-kit' ),
			'move_down'                 => __( 'Move down', 'bwc-admin-kit' ),
			/* translators: %d: most rows allowed. */
			'repeater_max'              => _n_noop( 'Only the first %d row was kept.', 'Only the first %d rows were kept.', 'bwc-admin-kit' ),
			/* translators: 1: row title, 2: field label, 3: message. */
			'repeater_problem'          => __( '%1$s, %2$s: %3$s', 'bwc-admin-kit' ),
			/* translators: %s: the lines, joined with commas. */
			'field_users_unknown'       => __( 'No account matches these, so they weren\'t saved: %s.', 'bwc-admin-kit' ),
			'yes'                       => __( 'Yes', 'bwc-admin-kit' ),
			'no'                        => __( 'No', 'bwc-admin-kit' ),
			/* translators: %s: name of a settings group, such as "Offers". */
			'in_group'                  => __( 'In %s', 'bwc-admin-kit' ),
		);
	}

	/**
	 * Every string, with the plugin's overrides and the filter applied.
	 *
	 * @return array<string, string|array<int|string, mixed>>
	 */
	public function all(): array {
		if ( null === $this->strings ) {
			$strings = array_merge( self::defaults(), $this->kit->string_overrides() );

			/**
			 * Filters the kit's strings for one plugin.
			 *
			 * @param array<string, string|array<int|string, mixed>> $strings Key => text; plurals as a nooped plural or [singular, plural].
			 */
			$filtered = apply_filters( $this->kit->name( 'strings' ), $strings );

			// A filter can return anything; keep the defaults when it doesn't return an array.
			$this->strings = is_array( $filtered ) ? $filtered : $strings;
		}

		return $this->strings;
	}

	/**
	 * One string, with sprintf() arguments filled in.
	 *
	 * @param string           $key  Key.
	 * @param string|int|float ...$args Values for its placeholders.
	 */
	public function get( string $key, string|int|float ...$args ): string {
		$text = $this->all()[ $key ] ?? null;

		if ( null === $text ) {
			$this->unknown( $key );
			return $key;
		}

		if ( is_array( $text ) ) {
			$text = self::plural_form( $text, 2 );
		}

		return $args ? vsprintf( $text, $args ) : $text;
	}

	/**
	 * A string with a singular and plural form, picked by a count.
	 *
	 * @param string           $key   Key.
	 * @param int              $count Count that picks the form, also the first placeholder.
	 * @param string|int|float ...$args More placeholder values after the count.
	 */
	public function count( string $key, int $count, string|int|float ...$args ): string {
		$text = $this->all()[ $key ] ?? null;

		if ( null === $text ) {
			$this->unknown( $key );
			return $key;
		}

		$form = is_array( $text ) ? self::plural_form( $text, $count ) : $text;

		return vsprintf( $form, array_merge( array( $count ), $args ) );
	}

	/**
	 * The right form of a plural for a count.
	 *
	 * @param array<int|string, mixed> $plural Nooped plural, or [singular, plural] from an override.
	 * @param int                      $count  Count.
	 */
	private static function plural_form( array $plural, int $count ): string {
		if ( isset( $plural['singular'], $plural['plural'] ) ) {
			// Uses the language's own plural rules, which may have more than two forms.
			return translate_nooped_plural( $plural, $count, 'bwc-admin-kit' );
		}

		return (string) ( 1 === $count ? ( $plural[0] ?? '' ) : ( $plural[1] ?? $plural[0] ?? '' ) );
	}

	/**
	 * The strings a script needs, as key => text.
	 *
	 * @param string[] $keys Keys.
	 * @return array<string, string>
	 */
	public function for_script( array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = $this->get( $key );
		}

		return $out;
	}

	/**
	 * Flags a key that doesn't exist, while debugging.
	 *
	 * @param string $key Key.
	 */
	private function unknown( string $key ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			_doing_it_wrong( __METHOD__, esc_html( "Admin Kit has no string \"{$key}\"." ), esc_html( Kit::VERSION ) );
		}
	}
}
