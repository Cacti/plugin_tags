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

	print "<link type='text/css' href='" . $config['url_path'] . "plugins/tags/themes/common.css' rel='stylesheet'>";
}
