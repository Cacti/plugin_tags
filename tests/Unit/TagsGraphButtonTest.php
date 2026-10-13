<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_tags_graph_button() and the tag-draw gate in
 * plugin_tags_rrd_graph_graph_options() (include/functions.php): the
 * 'graph_buttons' hook prints a drilldown toggle that enables/disables
 * drawing this plugin's tag legend lines on graphs for the session.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../include/functions.php';
});

beforeEach(function () {
	$GLOBALS['__test_request'] = array();
	$_SESSION                  = array();
	$_SERVER['REQUEST_URI']    = '/cacti/graph_view.php?action=preview';
});

it('shows the Disable Tags toggle by default with tags drawn', function () {
	ob_start();
	plugin_tags_graph_button(array(1 => array('local_graph_id' => 42)));
	$html = ob_get_clean();

	expect($html)->toContain('class="iconLink tagsDraw"')
		->and($html)->toContain('fas fa-tags')
		->and($html)->toContain('title="Disable Tags"')
		->and($html)->toContain('tags_draw=on')
		->and($html)->not->toContain('Show tags for this graph');

	expect($_SESSION['sess_config_array']['tags_draw'])->toBe('on');
});

it('flips an on request to off and shows Enable Tags, prefixing ? when the url has none', function () {
	test_set_request(array('tags_draw' => 'on'));
	$_SERVER['REQUEST_URI'] = '/cacti/graph_view.php';

	ob_start();
	plugin_tags_graph_button(array(1 => array('local_graph_id' => 42)));
	$html = ob_get_clean();

	expect($html)->toContain('title="Enable Tags"')
		->and($html)->toContain('?tags_draw=off');

	expect($_SESSION['sess_config_array']['tags_draw'])->toBe('off');
});

it('flips an off request back to on and shows Disable Tags', function () {
	test_set_request(array('tags_draw' => 'off'));

	ob_start();
	plugin_tags_graph_button(array(1 => array('local_graph_id' => 42)));
	$html = ob_get_clean();

	expect($html)->toContain('title="Disable Tags"')
		->and($html)->toContain('tags_draw=on');

	expect($_SESSION['sess_config_array']['tags_draw'])->toBe('on');
});

it('reads the draw state from the session when no request var is set', function () {
	$_SESSION['sess_config_array']['tags_draw'] = 'off';

	ob_start();
	plugin_tags_graph_button(array(1 => array('local_graph_id' => 42)));
	$html = ob_get_clean();

	expect($html)->toContain('title="Enable Tags"')
		->and($html)->toContain('tags_draw=off');
});

it('replaces an existing first-parameter tags_draw instead of duplicating it', function () {
	test_set_request(array('tags_draw' => 'on'));
	$_SERVER['REQUEST_URI'] = '/cacti/graph_view.php?tags_draw=on';

	ob_start();
	plugin_tags_graph_button(array(1 => array('local_graph_id' => 42)));
	$html = ob_get_clean();

	expect($html)->toContain('?tags_draw=off')
		->and($html)->not->toContain('tags_draw=on');
});

it('skips drawing tag legends when the session toggle is off', function () {
	$_SESSION['sess_config_array']['tags_draw'] = 'off';

	$data   = array('graph_id' => 10, 'start' => 0, 'end' => 100, 'txt_graph_items' => 'ORIGINAL');
	$result = plugin_tags_rrd_graph_graph_options($data);

	expect($result)->toBe($data);
});
