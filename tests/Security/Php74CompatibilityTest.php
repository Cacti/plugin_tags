<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify plugin source files do not use PHP 8.0+ syntax.
 * Cacti 1.2.x plugins must remain compatible with PHP 7.4.
 */

	// Discovered recursively so new production PHP files are covered automatically.
	$pluginRoot = realpath(__DIR__ . '/../..');
	$testsDir   = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
	$files      = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		// Compare absolute paths so this test never matches its own source files,
		// regardless of how many directory levels $pluginRoot happens to resolve to.
		if (strpos($file->getPathname(), $testsDir) === 0) {
			continue;
		}

		$relativeFile = ltrim(str_replace($pluginRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
		$relativeFile = str_replace(DIRECTORY_SEPARATOR, '/', $relativeFile);

		$files[] = $relativeFile;
	}

	sort($files);

	it('does not use str_contains (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bstr_contains\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses str_contains() which requires PHP 8.0"
			);
		}
	});

	it('does not use str_starts_with (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bstr_starts_with\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses str_starts_with() which requires PHP 8.0"
			);
		}
	});

	it('does not use str_ends_with (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bstr_ends_with\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses str_ends_with() which requires PHP 8.0"
			);
		}
	});

	it('does not use nullsafe operator (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\?->/', $contents))->toBe(0,
				"{$relativeFile} uses nullsafe operator which requires PHP 8.0"
			);
		}
	});

	it('does not use match expressions (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bmatch\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses match expression which requires PHP 8.0"
			);
		}
	});

	it('does not use constructor property promotion (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/function\s+__construct\s*\([^)]*\b(public|protected|private)\b/s', $contents))->toBe(0,
				"{$relativeFile} uses constructor property promotion which requires PHP 8.0"
			);
		}
	});

	it('does not use attribute syntax (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/#\[\s*[A-Za-z_]/', $contents))->toBe(0,
				"{$relativeFile} uses attribute syntax which requires PHP 8.0"
			);
		}
	});

	it('does not use enum declarations (PHP 8.1)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/(^|\s)enum\s+[A-Za-z_][A-Za-z0-9_]*/', $contents))->toBe(0,
				"{$relativeFile} uses enum declaration which requires PHP 8.1"
			);
		}
	});

	it('does not use readonly properties (PHP 8.1)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\breadonly\b/', $contents))->toBe(0,
				"{$relativeFile} uses readonly property which requires PHP 8.1"
			);
		}
	});

	it('does not use first-class callable syntax (PHP 8.1)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\b[A-Za-z_][A-Za-z0-9_]*\s*\(\s*\.\.\.\s*\)/', $contents))->toBe(0,
				"{$relativeFile} uses first-class callable syntax which requires PHP 8.1"
			);
		}
	});
