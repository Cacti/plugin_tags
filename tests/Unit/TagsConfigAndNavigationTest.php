<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_tags_config_arrays(),
 * plugin_tags_draw_navigation_text(), plugin_tags_config_settings(), and
 * plugin_tags_page_head() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']                      = array();
	$GLOBALS['__test_current_page']                  = 'graphs.php';
	$GLOBALS['__test_db_fetch_cell_return']          = '';
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = '';
});

it('adds the Tags menu entry and skips the version check off relevant pages', function () {
	global $menu;

	$menu = array(__('Management') => array());

	plugin_tags_config_arrays();

	expect($menu[__('Management')])->toHaveKey('plugins/tags/tags.php');

	$writes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared';
	});

	expect($writes)->toBeEmpty();
});

it('runs the version check when on a relevant page', function () {
	global $menu;

	$menu                            = array(__('Management') => array());
	$GLOBALS['__test_current_page']  = 'tags.php';

	plugin_tags_config_arrays();

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	});

	expect($updates)->not->toBeEmpty();
});

it('adds the tags breadcrumb entries without disturbing existing ones', function () {
	$nav = plugin_tags_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('tags.php:');
	expect($nav['tags.php:']['url'])->toBe('tags.php');
});

it('registers the Tags settings tab', function () {
	global $tabs, $settings;

	$tabs     = array();
	$settings = array();

	plugin_tags_config_settings();

	expect($tabs['tags'])->toBe('Tags');
	expect($settings)->toHaveKey('tags');
});

it('prints the common stylesheet in the page head', function () {
	ob_start();
	plugin_tags_page_head();
	$output = ob_get_clean();

	expect($output)->toContain('common.css');
});
