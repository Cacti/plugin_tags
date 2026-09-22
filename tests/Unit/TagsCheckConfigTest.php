<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_tags_check_config()/plugin_tags_upgrade() (the
 * latter defined in include/database.php, included by the former) in
 * setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']                      = array();
	$GLOBALS['__test_registered_hooks']              = array();
	$GLOBALS['__test_db_fetch_cell_return']          = '';
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = '';
});

it('always reports success and updates plugin_config to the current version', function () {
	$info = plugin_tags_version();

	expect(plugin_tags_check_config())->toBeTrue();

	$updates = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	}));

	expect($updates)->toHaveCount(1);
	expect($updates[0]['params'])->toBe(array($info['version'], 'tags'));
});

it('re-registers the run_data_query hook when it is missing', function () {
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = 0;

	plugin_tags_check_config();

	$hooks = array_column($GLOBALS['__test_registered_hooks'], 'hook');

	expect($hooks)->toContain('run_data_query');
});

it('does not re-register the run_data_query hook when it already exists', function () {
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = 1;

	plugin_tags_check_config();

	expect($GLOBALS['__test_registered_hooks'])->toBeEmpty();
});
