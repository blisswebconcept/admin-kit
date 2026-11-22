# Changelog

Admin Kit follows [semantic versioning](https://semver.org/). Until 1.0, a minor version (0.x.0) may change the PHP API; each one says what changed here.

## 0.2.0 (2026-11-22)

Found while moving a first real plugin onto the kit. A minor release: new options and two fixes; nothing a 0.1 plugin uses was removed, though `ListScreen`'s `before` now prints under the page title.

- **Jobs:** an item can take more than one batch. `work()` puts it in the state's `again` list, and it stays first in line, not counted as done, until a batch finishes it.
- **Jobs:** `lock_seconds()` can be overridden by a job whose batches may take longer than the runner's 60-second lock (a feed page plus a logo download).
- **Settings storage (bug fix):** the sanitize callback remembered its first result for the rest of the request, so a second `update_option()` of the same option in one request (cron, WP-CLI, a plugin saving twice) stored the first value again. Now only WordPress's second pass over the value it just returned (`add_option()` after `update_option()`) gets it back; any other write is sanitized on its own.
- **Notices:** field messages from a save with no user (cron, WP-CLI) are dropped, as `flash()` already did, instead of waiting in a transient shared by every logged-out request.
- **LogScreen:** the filter chips show how many entries each one has; a run can give its notes a heading (`notes_title`); `run_empty` is shown for a run with no entries.
- **ListScreen:**
  - `before` now prints between the page title and the list, not above the title, so tiles and notices sit under the heading they belong to.
  - `note` puts a line under the list (the Panel's note).
  - `sorts` names the orders the sort select offers (`array( 'clicks:desc' => 'Most clicks first' )`); only those are offered.
  - A chip filter's "All" shows a count when the query returns one under the empty value (`counts[field]['']`); an element can have `'tone' => 'danger'`.
  - A checkbox filter with `'hide_empty' => true` isn't offered while its total is 0, unless it's ticked.
  - An export can have columns of its own: `headings( $view )` and `rows( $view )`, whose rows are already cells (clicks per month).
  - A drawer can show the plugin's own notice about the record at its top: `notice( ?array $record )`, escaped HTML.
  - A list is read (fields normalised, defaults worked out) only when it's first shown, and `fields` and a field's `elements` may be callables, so filter options from the database cost nothing on other admin pages.
  - "All" on a chip or segmented filter turns off a default that picks a value ("Active"); a default that hides something ("not old") still stays under "All".
  - `note` may be a callable given the view; `meta` puts a line by the title (string or callable given the view).
  - A list's `layout` can set `min_width` (`medium`, `wide`, or `none` for tables): on narrow screens a wide list scrolls sideways at 900px instead of squeezing its columns. Grouped lists take it too.
  - Codes in table cells stay on one line, and a grouped list's fixed columns cut what doesn't fit at the cell's edge instead of drawing it over the next cell.
  - A list field with `hide_label` keeps its column heading for screen readers only (an actions column), in tables and grouped lists.
- **Buttons:** `title` (shown on hover); `Actions::button()` passes `disabled` and `title` on, so a "Run the check" button can be off while the check runs.
- **Discard (bug fix):** Discard now puts back the fields scripts build too: ordered chips' order and choices, a picker's chips, and a repeater's rows (added ones go, removed ones come back, in their first order). Each restores itself on the form's `reset` event. The save bar also takes its "nothing changed" snapshot once every behaviour has started, so a field whose plain control a script replaced can read as unchanged again, and it ends at "no changes" after Discard.
- **LogScreen:** the run list comes a page at a time (20 runs unless `per_page` says), with Newer and Older under it; the page is `runs_page` in the URL, kept when a run is opened, and the chosen run stays open when paging. `runs( $days, $offset, $limit )` can return `array( 'runs' => …, 'total' => … )` to read one page itself; a plain list still works and the kit takes the page from it. The side column is no taller than the window and scrolls on its own.
- **Header bar:** on wide screens (from 783px, WordPress's desktop admin) the screen's header bar (title, tabs with their badges, actions, version) stays under the admin bar while the page scrolls; once stuck, its top corners go square and a soft shadow shows under it (`--bwc-shadow-stuck`). On narrower screens it scrolls away as before. Its height goes into `--bwc-screen-bar-height` (a small script, `screen-bar`, with a ResizeObserver, so a wrapped bar counts), and `--bwc-sticky-offset` on the screen says where sticky parts stop: the settings side menu, the log's run list and jumps to an element with an id now stop under the bar instead of behind it. Without the script the bar's height is taken as 72px. The drawer (a modal dialog) still covers it.
- **Status dots and pill dots** use the fill colours (`--bwc-color-success-fill`, `--bwc-color-warning-fill`): WordPress 7.1's success text colour is so dark that a "Works" or "Connected" dot read as black.
- **Stack:** its gap is the only space between its children; a paragraph or list inside it drops WordPress's margins.
- **Cards:** a long address or error code wraps inside the card instead of running out of it.
- **Fields:** a text, URL or email field can show a `prefix` inside the box before the value ("example.com/"), read by screen readers as the input's description.

## 0.1.0 (2026-11-20)

The first release.

- **Kit and build:** `Kit::boot()`, one kit per plugin, tokens that follow WordPress's admin colour scheme, the Strings and Assets services, and `bin/bwc-build`, which scopes the kit's CSS and scripts to one plugin.
- **Shell and layout:** header bar, tabs and side menu with badges, page head; stack, row, grid, side-and-main.
- **Content:** tile, pill, status dot, notice, card, panel, empty state, placeholder, status card, callout, key-value list, legend, avatar, features, icons, live region.
- **Controls and data:** buttons, switch, search, chips, segmented control, prefixed input, pagination like WordPress's list tables, copy button; table with sortable headers and bulk select, grouped list, collapsible group, feed, timeline, run list, reference table.
- **Fields and storage:** the field wrapper and registry, 18 field types, dependent fields, validation messages; option, post, term and user meta storage; REST schemas.
- **More fields:** secret (encrypted with sodium, a `wp-config.php` constant wins), searchable post, user and term pickers, ordered chips, the repeater, a radio option with its own field, lists of users.
- **Screens:** SettingsScreen (side menu or tabs, module switches, search, save bar, Discard, leave-page warning, Advanced, delete-data card), ListScreen (view state in the URL, filters, sorting, bulk actions, CSV export of the current view, drawer forms), ReportScreen (tabs of typed blocks, freshness and Refresh), DetailScreen, LogScreen.
- **Services:** Actions, Notices, CSV export (safe from formula injection), Secrets, Cache with refresh, background Jobs with a lock, Stop and "do the failed ones again".
- **Charts:** line and area, stacked and grouped bars, funnel, spark and strip, each with a "Show the numbers" table.
- **Gallery:** a dev-only plugin that shows every part in every state and runs axe over every page.
- **Security:** a review before release (2026-11-20) closed the paths around the kit's own forms. Meta keys follow their form's whole test, and read-only fields and `single_key` can't be changed from outside. Post and term meta are out of the REST API unless a box or set asks, and then only for editors. Settings are held to their screen's capability on `options.php` and REST too. Pickers are searched only by users who may use the form they sit in, with the post type's own capability, and items of non-public post types are only listed for their editors. Secret fields are kept to settings, and links built from the request are encoded.
- **Release review:** every part was reviewed before release, and axe finds nothing on any gallery page or settings group at 320, 768 and 1440px in six colour schemes. The accent is held to 50% lightness at most, so text and buttons stay readable in light schemes such as Sunrise.

**Planned, not in 0.1.0:** the `class` and `attrs` props on every component; the save bar and Advanced fold as components of their own; a multi-select over 12 options turning into the picker; Undo after removing a repeater row and `Repeater::parse_lines()`; warnings for unknown field keys; `number`'s `step` on save and in REST; `single_key` in the REST API; an error summary on term and profile forms.
