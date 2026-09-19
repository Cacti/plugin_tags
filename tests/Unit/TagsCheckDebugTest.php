<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for tags_check_debug() in include/functions.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../include/functions.php';
});

beforeEach(function () {
	$GLOBALS['__test_config_options'] = array();
});

it('enables debug when the plugin is the only entry in selective_plugin_debug', function () {
	$GLOBALS['debug'] = false;
	test_set_config_option('selective_plugin_debug', 'tags');

	tags_check_debug();

	expect($GLOBALS['debug'])->toBeTrue();
});

it('enables debug when the plugin is listed among others', function () {
	$GLOBALS['debug'] = false;
	test_set_config_option('selective_plugin_debug', 'grid, tags, thold');

	tags_check_debug();

	expect($GLOBALS['debug'])->toBeTrue();
});

it('leaves debug disabled when the plugin is not listed', function () {
	$GLOBALS['debug'] = false;
	test_set_config_option('selective_plugin_debug', 'grid, thold');

	tags_check_debug();

	expect($GLOBALS['debug'])->toBeFalse();
});

it('does not re-evaluate the config option when debug is already enabled', function () {
	$GLOBALS['debug'] = true;
	test_set_config_option('selective_plugin_debug', '');

	tags_check_debug();

	expect($GLOBALS['debug'])->toBeTrue();
});
