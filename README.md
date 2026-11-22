# Admin Kit

Admin screens for WordPress plugins that look and behave like WordPress itself. You describe your settings, lists and reports in PHP arrays; the kit draws the screens, saves the values, and handles notices, exports, secrets and background jobs.

- **Looks like WordPress.** It uses WordPress's design tokens, and the accent follows the colour scheme each user picks in their profile.
- **Works without JavaScript.** Everything is rendered on the server. The small scripts only add polish: searching settings, drag-to-reorder, drawers, a sticky header.
- **Accessible.** Real labels and landmarks, keyboard support throughout, and announcements for changes. The demo plugin runs axe over every screen.
- **Safe defaults.** Every write checks a capability and a nonce, and output is escaped. Secrets are encrypted with sodium, and post and term meta stay out of the REST API unless you ask.
- **One copy per plugin.** With [Strauss](https://github.com/BrianHenryIE/strauss), each plugin bundles its own copy of the kit, so two plugins carrying different versions never clash.

Requires PHP 8.2+ and WordPress 6.9+.

## What's inside

| | |
|---|---|
| **Screens** | Settings (side menu or tabs, module switches, search, save bar with Discard), lists (search, filters, sorting, bulk actions, CSV export, edit drawers), reports (tabs of charts, tiles and tables), detail views, activity logs |
| **Fields** | Text, textarea, lists, number, length, email, URL, checkbox, switch, checkboxes, radio, select, hour, time, role, pair, secret, searchable post, user and term pickers, ordered chips, repeater, with dependent fields and validation |
| **Storage** | One option for a settings screen; post meta boxes; fields on terms and user profiles. All from the same field descriptions, with REST schemas |
| **Components** | Cards, panels, tiles, notices, pills, status dots, tables, grouped lists, feeds, timelines, pagination, charts (line, bars, funnel, sparkline, strip), and more |
| **Services** | Actions (forms that post back to the same page), notices, CSV export, encrypted secrets, a cache with Refresh, background jobs that run in short steps |

## Install

1. **Require the kit** with Composer:

   ```bash
   composer require bliss-web-concept/admin-kit
   ```

   If Composer can't find the package, add the repository to your plugin's `composer.json` first:

   ```json
   "repositories": [ { "type": "vcs", "url": "https://github.com/blisswebconcept/admin-kit" } ]
   ```

2. **Give your plugin its own copy with Strauss** (0.31.1 or later, run as `strauss.phar`). It renames the kit's namespace to yours:

   ```json
   "extra": {
       "strauss": {
           "target_directory": "vendor-prefixed",
           "namespace_prefix": "MyPlugin\\Vendor\\",
           "classmap_prefix": "MyPlugin_Vendor_",
           "constant_prefix": "MYPLUGIN_",
           "packages": [ "bliss-web-concept/admin-kit" ]
       }
   }
   ```

3. **Build your copy of the stylesheet and script** before Strauss runs. `bwc-build` scopes every rule to your plugin and writes `{id}-bwc.css` and `{id}-bwc.js`:

   ```json
   "scripts": {
       "post-install-cmd": [
           "@php vendor/bliss-web-concept/admin-kit/bin/bwc-build --id=my-plugin --out=assets/bwc",
           "@php tools/strauss.phar"
       ],
       "post-update-cmd": [
           "@php vendor/bliss-web-concept/admin-kit/bin/bwc-build --id=my-plugin --out=assets/bwc",
           "@php tools/strauss.phar"
       ]
   }
   ```

   Ship `assets/bwc/` and `vendor-prefixed/` with your plugin. Leave `tools/strauss.phar` out of release zips.

## Use

**Boot the kit once**, early (on `plugins_loaded`, for example):

```php
use MyPlugin\Vendor\BlissWebConcept\AdminKit\Kit;

$kit = Kit::boot(
	array(
		'id'          => 'my-plugin',      // Lowercase and hyphenated: asset handles, scope class, hooks.
		'file'        => MY_PLUGIN_FILE,
		'version'     => MY_PLUGIN_VERSION,
		'text_domain' => 'my-plugin',
		// 'capability' => 'manage_options', // Who may use the screens; filterable per screen.
		// 'accent'     => 'oklch(55% 0.15 30)', // Optional. Without it, the accent follows each user's colour scheme.
	)
);
```

`Kit::instance()` returns it anywhere in your plugin.

**Describe your screens** at `init` (their labels are translated) and hand them to the shell. It adds the menu pages and the header with its tabs, and loads the kit's assets on those pages:

```php
add_action( 'init', static function () use ( $kit ): void {
	$settings = $kit->settings_screen(
		array(
			'id'     => 'settings',
			'label'  => __( 'Settings', 'my-plugin' ),
			'option' => 'my_plugin_settings',
			'groups' => array(
				'general' => array(
					'label'    => __( 'General', 'my-plugin' ),
					'sections' => array(
						'buttons' => array(
							'label'  => __( 'Buttons', 'my-plugin' ),
							'fields' => array(
								array(
									'id'      => 'text',
									'label'   => __( 'Button text', 'my-plugin' ),
									'type'    => 'text',
									'default' => __( 'Get the deal', 'my-plugin' ),
								),
							),
						),
					),
				),
			),
		)
	);

	$kit->shell(
		array(
			'menu'    => array( 'title' => 'My Plugin', 'menu_title' => 'My Plugin', 'slug' => 'my-plugin' ),
			'header'  => array( 'title' => 'My Plugin', 'version' => MY_PLUGIN_VERSION ),
			'screens' => array( $settings /* , $kit->list_screen( … ), $kit->report_screen( … ), $kit->log_screen( … ) */ ),
		)
	);
} );

// Anywhere later: a section's field is "{section}.{field id}".
$text = $settings->get( 'buttons.text' );
```

**Fields on posts, terms and profiles** use the same descriptions:

```php
$kit->meta_box(
	array(
		'id'         => 'offer',
		'title'      => __( 'Offer details', 'my-plugin' ),
		'post_types' => array( 'post' ),
		'fields'     => array(
			array( 'id' => 'code', 'label' => __( 'Coupon code', 'my-plugin' ), 'type' => 'text' ),
		),
	)
);
```

Each field gets its own meta key (`{prefix}_{id}`), so `WP_Query`, WP-CLI and other code can read it.

**Components** work on any admin page. Each has `markup( $props )`, which returns HTML, and `render( $props )`, which prints it:

```php
use MyPlugin\Vendor\BlissWebConcept\AdminKit\Component\Notice;

echo Notice::markup( array( 'tone' => 'success', 'text' => __( 'Saved.', 'my-plugin' ) ) );
```

On a page you add yourself, call `$kit->assets()->load_on( $hook_suffix )` so the styles load there.

## The gallery

`gallery/` is a demo plugin with every component, field and screen in every state, using sample data from a made-up coupon site. It installs the kit exactly as a real plugin does (Composer from this folder, then `bwc-build`, then Strauss), so it doubles as a working example. Copy from its `settings-demo.php`, `list-demo.php`, `jobs-demo.php` and `report-demo.php`.

```bash
cd gallery
gh release download 0.31.1 --repo BrianHenryIE/strauss --pattern strauss.phar --dir tools
composer install
```

Symlink `gallery/` into a local site's `wp-content/plugins/` as `bwc-gallery`, activate **Admin Kit Gallery**, and open **Admin Kit** in wp-admin. After changing kit files, run `composer refresh` in `gallery/` to rebuild.

The gallery's **Accessibility** tab runs axe over every page at the width and colour scheme you pick. Copy axe in once from the kit folder:

```bash
npm ci && npm run gallery:axe
```

## Developing the kit

```bash
composer install && npm ci
vendor/bin/phpcs -q -d memory_limit=1G       # WordPress Coding Standards
vendor/bin/phpstan analyse --memory-limit=1G # Static analysis
npx stylelint "assets/css/**/*.css"          # BEM classes, logical properties, colours only in tokens.css
php tests/build/run.php                      # bwc-build tests, no WordPress needed
wp eval-file tests/wp/run.php                # Kit tests, inside any WordPress site
```

GitHub runs the first four on every push. The WordPress tests need a site, so they run locally.

## Versions

The kit follows [semantic versioning](https://semver.org/). Until 1.0, a minor version (0.x.0) may change the PHP API, and [`CHANGELOG.md`](CHANGELOG.md) says what changed. Require a minor range, such as `^0.2`.

## Security

Please report security issues privately with GitHub's **Report a vulnerability** button on the Security tab, not in a public issue.

## Licence

GPL-2.0-or-later. © Bliss Web Concept.
