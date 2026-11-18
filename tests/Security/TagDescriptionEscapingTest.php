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
 * tags.php is a page script, so these checks scan its source for the
 * output sites that print a stored tag description or host description.
 * Each stored value must be wrapped in html_escape() before it reaches
 * the page.
 */

$source = file_get_contents(__DIR__ . '/../../tags.php');

/**
 * Returns the source lines of tags.php that mention the given needle.
 *
 * @param string $source Full file contents.
 * @param string $needle Text to look for.
 *
 * @return array<int, string> Matching lines.
 */
function tags_test_lines_with(string $source, string $needle): array {
	return array_values(array_filter(explode("\n", $source), static fn ($l) => str_contains($l, $needle)));
}

it('escapes the tag description in the list link', function () use ($source) {
	$lines = tags_test_lines_with($source, "linkEditMain");

	expect($lines)->not->toBeEmpty();

	foreach ($lines as $line) {
		expect($line)->toContain("html_escape(\$row['description'])");
		expect($line)->not->toContain("\" . \$row['description'] . '</a>'");
	}
});

it('escapes descriptions in the delete confirmation list', function () use ($source) {
	$lines = tags_test_lines_with($source, '$item_list .=');

	expect($lines)->not->toBeEmpty();

	foreach ($lines as $line) {
		expect($line)->toContain('html_escape(db_fetch_cell_prepared(');
	}
});

it('escapes the description in the edit header and host column', function () use ($source) {
	expect($source)->toContain("html_escape(\$data['description']");
	expect($source)->toContain("html_escape(\$row['host_description']");
});

it('detects an unescaped output site (negative control)', function () {
	$bad = "\$x .= '<li>' . db_fetch_cell_prepared('SELECT description', [1]) . '</li>';";

	expect(str_contains($bad, 'html_escape(db_fetch_cell_prepared('))->toBeFalse();
});
