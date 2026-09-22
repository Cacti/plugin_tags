<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_tags_poller_bottom() in setup.php.
 *
 * It require_once()s $config['library_path'] . '/database.php', so that is
 * pointed at a throwaway empty stub file for the duration of this test:
 * library_path (unlike base_path/lib) is fully test-controlled.
 *
 * config['poller_id'] is kept at something other than 1 so this test does
 * not also pull in include/functions.php's plugin_tags_check_poller_events()
 * - a larger, DB-heavier function outside this test's scope.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tags-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
	$GLOBALS['config']['poller_id']    = 2;
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('dispatches the background poller with the php binary from config', function () {
	plugin_tags_poller_bottom();

	$execCalls = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'exec_background';
	}));

	expect($execCalls)->toHaveCount(1);
	expect($execCalls[0]['command'])->toBe('php');
	expect($execCalls[0]['args'])->toContain('poller_tags.php');
});
