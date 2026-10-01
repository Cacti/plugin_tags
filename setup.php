<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_tags_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

/**
 * Installs the tags plugin: registers its Cacti hooks (draw_navigation_text,
 * config_arrays, config_settings, page_head, poller_bottom, device_remove,
 * rrd_graph_graph_options, graph_buttons, graph_buttons_thumbnails,
 * api_device_save, run_data_query), adds its tags.php realm, and creates
 * its database tables. Invoked by Cacti's plugin architecture when an
 * administrator installs this plugin from Console > Plugin Management.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to load
 *                        this plugin's database.php.
 */
function plugin_tags_install(): void {
	global $config;

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

	include_once($config['base_path'] . '/plugins/tags/include/database.php');

	plugin_tags_setup_table();
}

/**
 * Uninstalls the tags plugin. Invoked by Cacti's plugin architecture when
 * an administrator uninstalls this plugin from Console > Plugin
 * Management; currently a no-op (tables and settings are intentionally
 * left in place unless plugin_tags_remove_data() is called separately).
 *
 * @return bool Always returns true.
 */
function plugin_tags_uninstall(): bool {
	return true;
}

/**
 * Reports that this plugin owns persistent data (its database tables and
 * settings) that can be cleaned up separately from uninstallation. Invoked
 * by Cacti's plugin architecture on the Plugin Management page to decide
 * whether to offer a "Remove Data" action.
 *
 * @return bool Always returns true.
 */
function plugin_tags_has_data(): bool {
	return true;
}

/**
 * Drops all of this plugin's database tables and deletes its settings.
 * Invoked by Cacti's plugin architecture when an administrator chooses
 * "Remove Data" for this plugin on the Plugin Management page.
 *
 * @return bool Always returns true.
 */
function plugin_tags_remove_data(): bool {
	db_execute('DROP TABLE IF EXISTS plugin_tags_event');
	db_execute('DROP TABLE IF EXISTS plugin_tags_event_archive');
	db_execute('DROP TABLE IF EXISTS plugin_tags_uptime');
	db_execute('DROP TABLE IF EXISTS plugin_tags_state');
	db_execute("DELETE FROM settings WHERE name LIKE 'plugin_tags%'");
	db_execute("DELETE FROM settings WHERE name LIKE 'tags_%'");

	return true;
}

/**
 * Verifies the plugin's configuration is up to date by loading the
 * database helpers and triggering plugin_tags_upgrade(), then ensures the
 * 'run_data_query' hook is registered (added after initial release, so
 * older installs may be missing it). Invoked by Cacti's plugin architecture
 * on relevant page loads, and from plugin_tags_config_arrays().
 *
 * @return bool Always returns true.
 *
 * @global array $config Cacti global configuration array; used to load
 *                        this plugin's database.php.
 */
function plugin_tags_check_config(): bool {
	global $config;

	include_once($config['base_path'] . '/plugins/tags/include/database.php');
	plugin_tags_upgrade();

	$hook_exists = db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND hook = ?', ['tags', 'run_data_query']);

	if (!$hook_exists) {
		api_plugin_register_hook('tags', 'run_data_query', 'plugin_tags_data_query_reindexed', 'include/functions.php', true);
	}

	return true;
}

/**
 * Reads this plugin's INFO file and returns its [info] section. Used by
 * Cacti's plugin architecture via the api_plugin_version hook, and
 * internally by plugin_tags_upgrade()/display_version() to detect/report
 * the plugin's version.
 *
 * @return array The parsed [info] section of the plugin's INFO file (keys
 *               such as name, version, author, description).
 *
 * @global array $config Cacti global configuration array; used to locate
 *                        the plugin's base path.
 */
function plugin_tags_version(): array {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/tags/INFO', true);
	$info = is_array($info) ? $info : [];

	return $info['info'];
}

/**
 * Hook implementation for Cacti's 'poller_bottom' filter. On the primary
 * poller, runs the poller/data-collector event checks inline, then always
 * launches poller_tags.php as a background process to perform the
 * remainder of this plugin's per-cycle work (host checks, version checks,
 * archiving). Called by Cacti's poller via
 * api_plugin_hook('poller_bottom', ...) at the end of each polling cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to locate
 *                        the PHP binary and this plugin's poller script,
 *                        and to check the current poller_id.
 */
function plugin_tags_poller_bottom(): void {
	global $config;

	require_once($config['library_path'] . '/database.php');

	if ((int) $config['poller_id'] === 1) {
		require_once($config['base_path'] . '/plugins/tags/include/functions.php');
		plugin_tags_check_poller_events();
	}

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . $config['base_path'] . '/plugins/tags/poller_tags.php';

	exec_background($command_string, $extra_args);
}

/**
 * Hook implementation for Cacti's 'config_arrays' filter. Adds the "Tags"
 * entry under the Management section of Cacti's menu, and triggers a
 * configuration check on the pages where it matters (index.php,
 * plugins.php, tags.php). Called by Cacti core via
 * api_plugin_hook('config_arrays', ...) while building the navigation
 * menu.
 *
 * @return void
 *
 * @global array $menu                      Cacti's main navigation menu
 *                                           array, extended here with this
 *                                           plugin's entry.
 * @global array $user_auth_realms          Cacti's registered realm map
 *                                           (unused directly; declared for
 *                                           parity with other config_arrays
 *                                           hook implementations).
 * @global array $user_auth_realm_filenames Cacti's realm-to-filename map
 *                                           (unused directly; declared for
 *                                           parity with other config_arrays
 *                                           hook implementations).
 */
function plugin_tags_config_arrays(): void {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/tags/tags.php'] = __('Tags', 'tags');

	$files = ['index.php', 'plugins.php', 'tags.php'];

	if (in_array(get_current_page(), $files, true)) {
		plugin_tags_check_config();
	}
}

/**
 * Hook implementation for Cacti's 'draw_navigation_text' filter. Adds
 * breadcrumb entries for tags.php's default, edit, and save views. Called
 * by Cacti core via api_plugin_hook('draw_navigation_text', ...) while
 * rendering the page breadcrumb trail.
 *
 * @param array $nav The existing breadcrumb map contributed by Cacti core
 *                   and other plugins.
 *
 * @return array The $nav array with this plugin's breadcrumb entries
 *               added.
 */
function plugin_tags_draw_navigation_text($nav) {
	$nav['tags.php:'] = [
		'title'   => __('Tags', 'tags'),
		'mapping' => 'index.php:',
		'url'     => 'tags.php',
		'level'   => '1'
	];

	$nav['tags.php:edit'] = [
		'title'   => __('Tags Edit', 'tags'),
		'mapping' => 'index.php:',
		'url'     => 'tags.php',
		'level'   => '1'
	];

	$nav['tags.php:save'] = [
		'title'   => __('Tags Save', 'tags'),
		'mapping' => 'index.php:',
		'url'     => 'tags.php',
		'level'   => '1'
	];

	return $nav;
}

/**
 * Hook implementation for Cacti's 'config_settings' filter. Loads this
 * plugin's option arrays and registers the "Tags" settings tab. Called by
 * Cacti core via api_plugin_hook('config_settings', ...) while building
 * the Settings page.
 *
 * @return void
 *
 * @global array $config   Cacti global configuration array; used to load
 *                          this plugin's include/arrays.php.
 * @global array $tabs     Cacti's registered Settings page tabs, extended
 *                          here with the 'tags' tab label.
 * @global array $settings Cacti's registered Settings page fields (unused
 *                          directly here; populated via include/arrays.php).
 */
function plugin_tags_config_settings(): void {
	global $config, $tabs, $settings;

	include_once($config['base_path'] . '/plugins/tags/include/arrays.php');

	$tabs['tags'] = __('Tags', 'tags');
}

/**
 * Hook implementation for Cacti's 'page_head' filter. Includes this
 * plugin's stylesheet on every page. Called by Cacti core via
 * api_plugin_hook('page_head', ...) while rendering the page <head>
 * section.
 *
 * @return void Outputs HTML directly.
 *
 * @global array $config Cacti global configuration array; used to build
 *                        the stylesheet's URL.
 */
function plugin_tags_page_head(): void {
	global $config;

	print get_md5_include_css('plugins/tags/css/common.css');
}

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called on a plugin version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function tags_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/tags';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: tags manifest.json could not be parsed; skipping file prune', false, 'TAGS');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry
				|| strncmp($rel, $entry . '/', strlen($entry) + 1) === 0
				|| strncmp($entry, $rel . '/', strlen($rel) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		// A tombstone must never contain '.'/'..' segments; a tampered manifest
		// could use them to escape the plugin directory or target its root.
		$segments = explode('/', $rel);

		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			cacti_log(sprintf('WARNING: tags prune refused to remove %s: path contains a traversal segment (tampered manifest.json?)', $rel), false, 'TAGS');

			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: tags prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'TAGS');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = tags_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: tags upgrade could not remove %s (check file/directory permissions)', $rel), false, 'TAGS');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: tags upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'TAGS');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for tags_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function tags_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!tags_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}
