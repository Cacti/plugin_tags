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
	// Define plugin_tags_upgrade()/plugin_tags_setup_state_table() from the
	// real checkout so check_config() runs while base_path is sandboxed below.
	require_once __DIR__ . '/../../include/database.php';
	require_once __DIR__ . '/../../include/functions.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']                      = array();
	$GLOBALS['__test_registered_hooks']              = array();
	$GLOBALS['__test_db_fetch_cell_return']          = '';
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = '';

	// Sandbox base_path so plugin_tags_upgrade()'s version-drift prune runs
	// against a throwaway tree with no manifest.json (prune no-ops), never the
	// real checkout. The temp tree carries a copy of the real INFO (so
	// plugin_tags_version() still matches) and empty include/ stubs the
	// check_config()/upgrade() include_once()s can load harmlessly.
	$GLOBALS['__tags_base_restore'] = $GLOBALS['config']['base_path'];
	$base = sys_get_temp_dir() . '/tags-test-' . uniqid();
	mkdir($base . '/plugins/tags/include', 0777, true);
	copy(__DIR__ . '/../../INFO', $base . '/plugins/tags/INFO');
	file_put_contents($base . '/plugins/tags/include/database.php', "<?php\n");
	file_put_contents($base . '/plugins/tags/include/functions.php', "<?php\n");
	$GLOBALS['config']['base_path'] = $base;
});

afterEach(function () {
	if (isset($GLOBALS['__tags_base_restore'])) {
		$GLOBALS['config']['base_path'] = $GLOBALS['__tags_base_restore'];
	}
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
