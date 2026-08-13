<?php
/* vim: ts=4
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Inc.                           |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | https://github.com/xmacan/                                              |
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

pcntl_async_signals(true);

ini_set('output_buffering', 'Off');
ini_set('max_runtime', '-1');
ini_set('memory_limit', '-1');

ini_set('max_execution_time', '-1');

set_time_limit(0);
ob_implicit_flush();

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
	pcntl_signal(SIGUSR1, 'sig_handler');
}

$dir = __DIR__;
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
        chdir('../../');
}

require('./include/cli_check.php');
require_once($config['base_path'] . '/plugins/tags/include/functions.php');
require_once($config['base_path'] . '/lib/poller.php');

error_reporting(E_ALL);

/* record the start time */
$poller_start = microtime(true);
$start_date   = date('Y-m-d H:i:s');
$force        = false;
$debug        = false;
$tags         = 0;

global $config, $database_default;

$run_from_poller = true;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch($arg) {

			case '--force':
				$force = true;

				break;
			case '--debug':
				$debug = true;

				break;
			case '--version':
			case '--V':
			case '--v':
				display_version();
				exit(0);
			case '--help':
			case '--H':
			case '--h':
				display_help();
				exit(0);

			default:
				print "ERROR: Invalid Argument: ($arg)" . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

tags_check_debug();

plugin_tags_settings_update();

/* silently end if the registered process is still running, or process table missing */
if (function_exists('register_process_start')) {
	if (!register_process_start('tags', 'master', $config['poller_id'])) {
		tags_debug('Another Tags Process Still Running');
		exit(0);
	}
}

plugin_tags_archive();

// check hosts for up/down
$tags += plugin_tags_check_hosts();

if (read_config_option('tags_cacti_version') == 'on') {
	if (plugin_tags_check_version()) {
		tags_debug('Cacti version change detected, tags created');
		$tags++;
	}
}

if (read_config_option("tags_poller_overrun") == 'on') {
	$tags += plugin_tags_check_poller_events();
}

if (read_config_option("tags_plugin_state") == 'on') {
	$tags += plugin_tags_check_plugin_events();
}

$poller_end = microtime(true);

$pstats = 'Time:' . round($poller_end-$poller_start, 2) . ', Tags:' . $tags;

cacti_log('TAGS STATS: ' . $pstats, false, 'SYSTEM');
tags_debug($pstats);
set_config_option('plugin_tags_stats', $pstats);

if (function_exists('unregister_process')) {
	unregister_process('tags', 'master', $config['poller_id']);
}

exit(0);


/**
 * Display the Tags poller version.
 *
 * @return void
 */
function display_version() {
	global $config;

	if (!function_exists('plugin_tags_version')) {
		include_once($config['base_path'] . '/plugins/tags/setup.php');
	}

	$info = plugin_tags_version();
	print 'Cacti Tags Poller, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * Display command-line usage information.
 *
 * @return void
 */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: poller_tags.php [--force] [--debug]' . PHP_EOL . PHP_EOL;
	print '  --force       - force execution, e.g. for testing' . PHP_EOL;
	print '  --debug       - debug execution, e.g. for testing' . PHP_EOL . PHP_EOL;
}


/**
 * Handle a process signal and terminate the poller cleanly.
 *
 * @param int $signo The signal that was thrown by the interface.
 *
 * @return void
 */
function sig_handler($signo) {
	global $force, $poller_id, $taskname;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
		case SIGUSR1:
			cacti_log("WARNING: Tags Poller 'master' is shutting down by signal!", false, 'TAGS');

			if (!$force) {
				unregister_process('task', 'master', $poller_id, getmypid());
			}

			$processes = db_fetch_assoc_prepared('SELECT *
				FROM processes
				WHERE tasktype = "task"
				AND taskname = ?',
				[$taskname]);

			cacti_log('Signaling ' . cacti_sizeof($processes), false, 'TAGS');

			if (cacti_sizeof($processes)) {
				foreach ($processes as $p) {
					posix_kill($p['pid'], SIGINT);

					db_execute_prepared('DELETE FROM processes
						WHERE pid = ?
						AND tasktype = "tags"
						AND taskname = ?',
						[$p['pid'], "child:$poller_id"]);
				}
			}

			exit(1);
		default:
			// ignore all other signals
	}
}


