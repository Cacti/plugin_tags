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

/** Install the Tags plugin and register its hooks.
 *
 * @return void
 */
function plugin_tags_install() {
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
	api_plugin_register_hook('tags', 'run_data_query',           'plugin_tags_data_query_reindexed',     'include/functions.php');

	api_plugin_register_realm('tags', 'tags.php', __('Plugin Tags - view', 'tags'), 1);

	include_once($config['base_path'] . '/plugins/tags/include/database.php');

	plugin_tags_setup_table();
}

/** Handle plugin uninstallation.
 *
 * @return bool
 */
function plugin_tags_uninstall() {
	return true;
}

/** Report whether the plugin owns persistent data.
 *
 * @return bool
 */
function plugin_tags_has_data() {
	return true;
}

/** Remove all plugin-owned data.
 *
 * @return bool
 */
function plugin_tags_remove_data() {
	db_execute('DROP TABLE IF EXISTS plugin_tags_event');
	db_execute('DROP TABLE IF EXISTS plugin_tags_event_archive');
	db_execute('DROP TABLE IF EXISTS plugin_tags_uptime');
	db_execute('DROP TABLE IF EXISTS plugin_tags_state');
	db_execute("DELETE FROM settings WHERE name LIKE '%plugin_tags%'");
	return true;
}

/** Check and update the plugin configuration.
 *
 * @return bool
 */
function plugin_tags_check_config() {
	global $config;

	include_once($config['base_path'] . '/plugins/tags/include/database.php');
	plugin_tags_upgrade();

	$hook_exists = db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_hooks WHERE name = ? AND hook = ?', ['tags', 'run_data_query']);

	if (!$hook_exists) {
		api_plugin_register_hook('tags', 'run_data_query', 'plugin_tags_data_query_reindexed', 'include/functions.php', true);
	}

	return true;
}

/** Return plugin metadata from INFO.
 *
 * @return array Plugin metadata.
 */
function plugin_tags_version() {
	global $config;

	$info = parse_ini_file($config['base_path'] . '/plugins/tags/INFO', true);

	return $info['info'];
}

/** Process tag events at the end of a polling cycle.
 *
 * @return void
 */
function plugin_tags_poller_bottom() {
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

/** Add Tags entries to Cacti configuration arrays.
 *
 * @return void
 */
function plugin_tags_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/tags/tags.php'] = __('Tags', 'tags');

	$files = ['index.php', 'plugins.php', 'tags.php'];

	if (in_array(get_current_page(), $files, true)) {
		plugin_tags_check_config();
	}
}

/** Add Tags pages to the navigation hierarchy.
 *
 * @param array $nav Navigation definitions.
 *
 * @return array Updated navigation definitions.
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


/** Register Tags settings with Cacti.
 *
 * @return void
 */
function plugin_tags_config_settings() {
	global $config, $tabs, $settings;

	include_once($config['base_path'] . '/plugins/tags/include/arrays.php');

	$tabs['tags'] = __('Tags', 'tags');
}

/** Add plugin assets to the page header.
 *
 * @return void
 */
function plugin_tags_page_head() {
	global $config;

	print "<link type='text/css' href='" . $config['url_path'] . "plugins/tags/themes/common.css' rel='stylesheet'>";
}



