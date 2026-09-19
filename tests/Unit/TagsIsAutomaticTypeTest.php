<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for tags_is_automatic_type() in include/functions.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../include/functions.php';
});

it('identifies auto_ prefixed types as automatic', function () {
	expect(tags_is_automatic_type('auto_reindex'))->toBeTrue();
});

it('identifies auto_ as automatic even with nothing following it', function () {
	expect(tags_is_automatic_type('auto_'))->toBeTrue();
});

it('does not treat a type merely containing auto_ later on as automatic', function () {
	expect(tags_is_automatic_type('manual_auto_thing'))->toBeFalse();
});

it('does not treat an unrelated type as automatic', function () {
	expect(tags_is_automatic_type('manual'))->toBeFalse();
});

it('casts non-string input to a string before checking', function () {
	expect(tags_is_automatic_type(0))->toBeFalse();
});
