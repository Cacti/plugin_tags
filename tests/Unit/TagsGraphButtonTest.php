<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_tags_graph_button() in include/functions.php: the
 * 'graph_buttons'/'graph_buttons_thumbnails' hook prints the drilldown action
 * icon that links a graph to its host's tags page.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../include/functions.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']                      = array();
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = '';
});

it('prints the iconLink button with the shortened Tags title and host link', function () {
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = 7;

	ob_start();
	plugin_tags_graph_button(array(1 => array('local_graph_id' => 42)));
	$html = ob_get_clean();

	expect($html)->toContain('class="iconLink"')
		->and($html)->toContain('fas fa-tags')
		->and($html)->toContain('title="Tags"')
		->and($html)->toContain('plugins/tags/tags.php?host_id=7')
		->and($html)->not->toContain('Show tags for this graph');
});
