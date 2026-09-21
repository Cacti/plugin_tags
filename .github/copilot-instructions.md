# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`tags`, version 0.1) targeting Cacti 1.2.17+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions
- **Platform**: Cacti Plugin Architecture — colour tags on devices/graphs/sites, manual or automatic (e.g. restarts, reindexing, plugin state changes, version changes)
- **Database**: MySQL/MariaDB

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`)
- `include/` shared logic; `themes/` CSS overlays

## Project Structure

```
tags/                 # Repository root (install to plugins/tags/ in Cacti)
├── images/             # UI icons (including tags_example.png used in README)
├── include/               # Shared functions/database helpers
├── themes/                  # CSS theme overlays
├── tags.php                    # Main tag administration/list UI
├── poller_tags.php               # Background poller entry point (CLI)
├── INFO                            # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                          # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
Unlike some other Cacti plugins, **this plugin uses the `plugin_tags_` prefix consistently for essentially all functions**, including hook callbacks that other plugins would give a shorter domain prefix (`plugin_tags_config_arrays()`, `plugin_tags_poller_bottom()`, `plugin_tags_page_head()`, `plugin_tags_device_remove()`). Match this repo's actual convention — do not split hook vs. internal functions into two different prefixes here, since that is not the pattern this codebase uses.

### Database Tables
All plugin tables are prefixed `plugin_tags_`:

```
plugin_tags_event, plugin_tags_event_archive, plugin_tags_uptime, plugin_tags_state
```

### Documentation Comments
Functions in this repository consistently use PHPDoc-style block comments (`/** ... * @return ... */`) above each function — follow this existing convention for new functions rather than omitting doc comments.

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
ALL PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group".

## Security Standards

### SQL Query Security
Use prepared statements for anything involving variable input:

```php
// CORRECT
db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND hook = ?', ['tags', 'run_data_query']);

// WRONG - never do this with request-derived values
db_execute("DELETE FROM plugin_tags_event WHERE id = $id");
```

### Input Validation
Use `get_filter_request_var()` / `get_nfilter_request_var()` for request input; never read `$_GET`/`$_POST` directly.

`get_filter_request_var()` (and its `gfrv()` shorthand, where available) called with only the
`$name` argument (no regex/filter as the 2nd/3rd argument) already validates the value as numeric
and returns it as a **string** -- it does not return an int, and it halts execution if the request
value is not numeric. Because of this, do NOT cast its output to `(int)` when the result is only
used for string output (e.g. `print`/`echo`, string concatenation, embedding in HTML/JS); the cast
is redundant. Only cast when the value is genuinely used in an integer/numeric context (e.g.
arithmetic, strict `===` comparisons).

## Database Operations

### Data Ownership Hooks
This plugin implements the `plugin_tags_has_data()` / `plugin_tags_remove_data()` pair (used by Cacti's plugin management to offer "remove data on uninstall"). Keep both in sync: if new plugin-owned tables/settings are added, add their cleanup to `plugin_tags_remove_data()` as well.

### Upgrade Handling
`plugin_tags_check_config()` re-registers the `run_data_query` hook if missing (self-healing pattern) in addition to the normal `plugin_tags_upgrade()` version check — follow this same self-healing approach for any hook that might be silently missing after an upgrade.

## Internationalization

ALL user-facing strings MUST use `__()` with the `'tags'` text domain.

## Plugin Architecture

### Plugin Hooks
Register all plugin hooks in `plugin_tags_install()` (`setup.php`):

```php
api_plugin_register_hook('tags', 'draw_navigation_text',     'plugin_tags_draw_navigation_text',    'setup.php');
api_plugin_register_hook('tags', 'config_arrays',            'plugin_tags_config_arrays',           'setup.php');
api_plugin_register_hook('tags', 'config_settings',          'plugin_tags_config_settings',         'setup.php');
api_plugin_register_hook('tags', 'page_head',                'plugin_tags_page_head',               'setup.php');
api_plugin_register_hook('tags', 'poller_bottom',            'plugin_tags_poller_bottom',           'setup.php');
api_plugin_register_hook('tags', 'device_remove',            'plugin_tags_device_remove',           'include/functions.php');
api_plugin_register_hook('tags', 'rrd_graph_graph_options',  'plugin_tags_rrd_graph_graph_options', 'include/functions.php');
api_plugin_register_hook('tags', 'graph_buttons',            'plugin_tags_graph_button',            'include/functions.php');
api_plugin_register_hook('tags', 'graph_buttons_thumbnails', 'plugin_tags_graph_button',            'include/functions.php');
api_plugin_register_hook('tags', 'api_device_save',          'plugin_tags_device_save',             'include/functions.php');
api_plugin_register_hook('tags', 'run_data_query',           'plugin_tags_data_query_reindexed',    'include/functions.php');

api_plugin_register_realm('tags', 'tags.php', __('Plugin Tags - view', 'tags'), 1);
```

### Automatic Tag Events
Automatic tags (device restart, reindex, plugin enable/disable/update, Cacti version change) are recorded as timestamped events in `plugin_tags_event`/`plugin_tags_event_archive`; keep new automatic-tag triggers consistent with this event-log pattern rather than storing derived state ad hoc.

## Best Practices

1. Keep the `plugin_tags_` prefix for new functions in this repo, including hook callbacks — do not introduce a shorter internal prefix.
2. Add PHPDoc blocks to new functions, matching existing style.
3. Keep `plugin_tags_has_data()`/`plugin_tags_remove_data()` in sync with any new plugin-owned storage.
4. Wrap all user-facing strings with `__('text', 'tags')`.

## Common Pitfalls to Avoid

```php
// WRONG - forgetting to also clean up new data in plugin_tags_remove_data()
// (adding a new table but never removing it on uninstall)

// CORRECT - mirror new tables/settings in plugin_tags_remove_data()
function plugin_tags_remove_data() {
	db_execute('DROP TABLE IF EXISTS plugin_tags_event');
	db_execute('DROP TABLE IF EXISTS plugin_tags_event_archive');
	db_execute('DROP TABLE IF EXISTS plugin_tags_uptime');
	db_execute('DROP TABLE IF EXISTS plugin_tags_state');
	// add new tables here too
	return true;
}
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
