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

	$expectedHooks = array(
		'draw_navigation_text'     => array('plugin_tags_draw_navigation_text',    'setup.php'),
		'config_arrays'            => array('plugin_tags_config_arrays',           'setup.php'),
		'config_settings'          => array('plugin_tags_config_settings',         'setup.php'),
		'page_head'                => array('plugin_tags_page_head',               'setup.php'),
		'poller_bottom'            => array('plugin_tags_poller_bottom',           'setup.php'),
		'device_remove'            => array('plugin_tags_device_remove',           'include/functions.php'),
		'rrd_graph_graph_options'  => array('plugin_tags_rrd_graph_graph_options', 'include/functions.php'),
		'graph_buttons'            => array('plugin_tags_graph_button',            'include/functions.php'),
		'graph_buttons_thumbnails' => array('plugin_tags_graph_button',            'include/functions.php'),
		'api_device_save'         => array('plugin_tags_device_save',             'include/functions.php'),
		'run_data_query'           => array('plugin_tags_data_query_reindexed',     'include/functions.php'),
	);

	foreach ($expectedHooks as $expected => list($expectedFunction, $expectedFile)) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['plugin'])->toBe('tags');
		expect($hooks[$expected]['function'])->toBe($expectedFunction);
		expect($hooks[$expected]['file'])->toBe($expectedFile);
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(1);
	expect($GLOBALS['__test_registered_realms'][0]['file'])->toBe('tags.php');

	$createdTables = array_values(array_unique(array_map(function ($call) {
		return $call['table'];
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}))));

	expect($createdTables)->toEqualCanonicalizing(array(
		'plugin_tags_event',
		'plugin_tags_event_archive',
		'plugin_tags_uptime',
		'plugin_tags_state',
	));
});
