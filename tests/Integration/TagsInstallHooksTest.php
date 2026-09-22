<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_tags_install(): verifies every hook and
 * the realm the plugin depends on at runtime are actually registered,
 * together with its full table set, in a single end-to-end pass.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']          = array();
	$GLOBALS['__test_registered_hooks']  = array();
	$GLOBALS['__test_registered_realms'] = array();
});

it('registers every hook tags depends on, its realm, and provisions its tables', function () {
	plugin_tags_install();

	$hooks = array();
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	foreach (array(
		'draw_navigation_text',
		'config_arrays',
		'config_settings',
		'page_head',
		'poller_bottom',
		'device_remove',
		'rrd_graph_graph_options',
		'graph_buttons',
		'graph_buttons_thumbnails',
		'api_device_save',
		'run_data_query',
	) as $expected) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['plugin'])->toBe('tags');
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(1);
	expect($GLOBALS['__test_registered_realms'][0]['file'])->toBe('tags.php');

	$createdTables = array_values(array_unique(array_map(function ($call) {
		return $call['table'];
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}))));

	expect($createdTables)->toContain('plugin_tags_event');
	expect($createdTables)->toContain('plugin_tags_uptime');
	expect($createdTables)->toContain('plugin_tags_state');
});
