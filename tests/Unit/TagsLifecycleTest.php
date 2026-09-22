<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_tags_version() and the plugin lifecycle
 * contract wrappers (plugin_tags_uninstall/has_data/remove_data) in
 * setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('parses the plugin INFO file into an info array', function () {
	$info = plugin_tags_version();

	expect($info)->toBeArray();
	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('tags');
});

it('reports uninstall as always successful', function () {
	expect(plugin_tags_uninstall())->toBeTrue();
});

it('reports that the plugin always owns persistent data', function () {
	expect(plugin_tags_has_data())->toBeTrue();
});

it('drops every owned table and settings row when removing data', function () {
	expect(plugin_tags_remove_data())->toBeTrue();

	$drops = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	});

	expect($drops)->toHaveCount(4);

	$settingsDeletes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DELETE FROM settings') !== false;
	});

	expect($settingsDeletes)->toHaveCount(2);
});
