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
 * Upgrade the tags plugin
 *
 * @return void
 */
function plugin_tags_upgrade() {
	global $config;

	include_once($config['base_path'] . '/plugins/tags/include/functions.php');

	$info = plugin_tags_version();
	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="tags"');


	if (version_compare($old, '0.2', '<')) {
/*
		foreach (['plugin_tags_event', 'plugin_tags_event_archive'] as $table) {
			$column = db_fetch_row("SHOW COLUMNS FROM $table LIKE 'type'");

			if (isset($column['Type']) && stripos($column['Type'], 'varchar') !== 0) {
				db_execute("ALTER TABLE $table MODIFY type varchar(32) NOT NULL DEFAULT 'manual'");
			}

			db_execute("UPDATE $table SET type = 'auto_other' WHERE type = 'automatic'");
		}
*/
	}

	db_execute_prepared('UPDATE plugin_config SET version = ? WHERE directory = ?', [$new, 'tags']);

	plugin_tags_setup_state_table();

	return true;
}

/**
 * Setup the database tables for the tags plugin
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


	$data = [];
	$data['columns'][] = ['name' => 'host_id', 'type' => 'int(11)', 'NULL' => false];
	$data['columns'][] = ['name' => 'uptime', 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'host_id';
	$data['type'] = 'InnoDB';
	$data['comment'] = 'Holds device uptime';
	api_plugin_db_table_create ('tags', 'plugin_tags_uptime', $data);

	plugin_tags_setup_state_table();
}

/** Create the state table used for transition detection.
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
