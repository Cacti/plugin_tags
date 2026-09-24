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
 * Compares the plugin's INFO-file version against the version recorded in
 * plugin_config, updates the stored value, and ensures the state table
 * exists. Invoked by Cacti's plugin architecture (via
 * plugin_tags_check_config()) to bring an existing installation up to
 * date.
 *
 * @return bool Always returns true.
 *
 * @global array $config Cacti global configuration array; used to load
 *                        this plugin's functions.php.
 */
function plugin_tags_upgrade() {
	global $config;

	include_once($config['base_path'] . '/plugins/tags/include/functions.php');

	$info = plugin_tags_version();
	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="tags"');

	db_execute_prepared('UPDATE plugin_config SET version = ? WHERE directory = ?', [$new, 'tags']);

	plugin_tags_setup_state_table();

	return true;
}

/**
 * Creates the plugin_tags_event, plugin_tags_event_archive, and
 * plugin_tags_uptime database tables used to store active/archived tag
 * events and device uptime, then ensures the state table exists. Called
 * once from plugin_tags_install() when the plugin is first installed.
 *
 * @return void
 */
function plugin_tags_setup_table() {

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'type', 'type' => 'varchar(32)', 'NULL' => false, 'default' => 'manual'];
	$data['columns'][] = ['name' => 'description', 'type' => 'varchar(64)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'tag_time', 'type' => 'int(22)', 'NULL' => false];
	$data['columns'][] = ['name' => 'target', 'type' => "enum('all','site','device','graph','primary')", 'NULL' => false, 'default' => 'all'];
	$data['columns'][] = ['name' => 'host_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'graph_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'site_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'color', 'type' => 'varchar(6)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on'];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds events';
	api_plugin_db_table_create('tags', 'plugin_tags_event', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'type', 'type' => 'varchar(32)', 'NULL' => false, 'default' => 'manual'];
	$data['columns'][] = ['name' => 'description', 'type' => 'varchar(64)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'tag_time', 'type' => 'int(22)', 'NULL' => false];
	$data['columns'][] = ['name' => 'target', 'type' => "enum('all','site','device','graph','primary')", 'NULL' => false, 'default' => 'all'];
	$data['columns'][] = ['name' => 'host_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'graph_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'site_id', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'color', 'type' => 'varchar(6)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on'];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Events archive';
	api_plugin_db_table_create('tags', 'plugin_tags_event_archive', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'host_id', 'type' => 'int(11)', 'NULL' => false];
	$data['columns'][] = ['name' => 'uptime', 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'host_id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds device uptime';
	api_plugin_db_table_create ('tags', 'plugin_tags_uptime', $data);

	plugin_tags_setup_state_table();
}

/**
 * Creates the plugin_tags_state database table, if it doesn't already
 * exist, used to persist state-transition detection data between poller
 * runs. Called from plugin_tags_setup_table() and plugin_tags_upgrade().
 *
 * @return void
 */
function plugin_tags_setup_state_table() {
	if (db_table_exists('plugin_tags_state')) {
		return;
	}

	$data              = [];
	$data['columns'][] = ['name' => 'state_key', 'type' => 'varchar(128)', 'NULL' => false];
	$data['columns'][] = ['name' => 'state_value', 'type' => 'text', 'NULL' => false];
	$data['columns'][] = ['name' => 'updated', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'state_key';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Tags plugin state transitions';

	api_plugin_db_table_create('tags', 'plugin_tags_state', $data);
}
