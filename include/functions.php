<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Checks whether selective plugin debugging is enabled for 'tags' via the
 * 'selective_plugin_debug' setting and updates the $debug flag used by
 * tags_debug(). Called once near the top of poller_tags.php's main flow.
 *
 * @return void
 *
 * @global bool $debug Set to true when debugging is enabled for this
 *                      plugin.
 */
function tags_check_debug() {
	global $debug;

	if (!$debug) {
		$plugin_debug = read_config_option('selective_plugin_debug');

		if (preg_match('/(^|[, ]+)(tags)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'tags');
		}
	}
}

/**
 * Determines whether a tag event's type represents an automatically
 * generated tag (types prefixed 'auto_') as opposed to a manually created
 * one. Called from plugin_tags_rrd_graph_graph_options() while grouping
 * tags for the graph legend, and from tags.php's list view.
 *
 * @param string $type The tag event's type value.
 *
 * @return bool True for automatic event types.
 */
function tags_is_automatic_type($type) {
	return strpos((string) $type, 'auto_') === 0;
}

/**
 * Writes $message to the Cacti log, prefixed 'DEBUG:', when selective
 * debugging is enabled for this plugin. Called throughout poller_tags.php
 * wherever verbose diagnostic output is useful.
 *
 * @param string $message The message to log when debugging is enabled.
 *
 * @return void
 *
 * @global bool $debug Whether debugging is enabled, set by
 *                      tags_check_debug().
 */
function tags_debug($message = '') {
	global $debug;

	if ($debug) {
		cacti_log('DEBUG: ' . trim($message), true, 'TAGS');
	}
}

/**
 * Reads a persisted state value from the plugin_tags_state table, used to
 * detect changes between poller runs (e.g. reindex hashes, poller/plugin
 * status flags). Called throughout this file's change-detection functions
 * before comparing against a newly computed state.
 *
 * @param string $key The state_key to look up.
 *
 * @return string|null The stored state_value, or null when the state
 *                      table doesn't exist or the key has no value.
 */
function tags_state_get($key) {
	if (!db_table_exists('plugin_tags_state')) {
		return null;
	}

	$value = db_fetch_cell_prepared('SELECT state_value FROM plugin_tags_state WHERE state_key = ?', [$key]);

	return $value === false ? null : $value;
}

/**
 * Persists a state value to the plugin_tags_state table (inserting or
 * updating as needed), used to detect changes between poller runs. Called
 * throughout this file's change-detection functions after evaluating the
 * current state.
 *
 * @param string $key   The state_key to store.
 * @param string $value The state_value to store.
 *
 * @return void
 */
function tags_state_set($key, $value) {
	if (!db_table_exists('plugin_tags_state')) {
		return;
	}

	db_execute_prepared('INSERT INTO plugin_tags_state (state_key, state_value, updated)
		VALUES (?, ?, UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE 
		state_value = VALUES(state_value), updated = VALUES(updated)',
		[$key, $value]);
}

/**
 * Hook implementation for Cacti's 'run_data_query' filter. Compares the
 * current set of SNMP indexes for a data query against the previously
 * recorded state and, when they differ, creates an
 * "auto_data_source_reindexed" tag noting the index count change. Called
 * by Cacti core via api_plugin_hook('run_data_query', ...) after a data
 * query re-index completes.
 *
 * @param array $data Hook payload; must include 'host_id' and
 *                     'snmp_query_id' for this function to act.
 *
 * @return array The unmodified $data array (this hook does not modify its
 *               payload).
 */
function plugin_tags_data_query_reindexed($data) {
	if (read_config_option('tags_data_source_reindexed') != 'on' ||
		empty($data['host_id']) || empty($data['snmp_query_id'])) {
		return $data;
	}

	$indexes = db_fetch_assoc_prepared('SELECT DISTINCT snmp_index FROM host_snmp_cache WHERE host_id = ? AND snmp_query_id = ? ORDER BY snmp_index', [$data['host_id'], $data['snmp_query_id']]);
	$indexes = array_column($indexes, 'snmp_index');
	$state   = ['hash' => sha1(implode("\n", $indexes)), 'count' => cacti_sizeof($indexes)];
	$key     = 'reindex:' . $data['host_id'] . ':' . $data['snmp_query_id'];
	$old_raw = tags_state_get($key);

	if ($old_raw !== null) {
		$old = json_decode($old_raw, true);

		if (is_array($old) && isset($old['hash']) && $old['hash'] !== $state['hash']) {
			$query_name  = db_fetch_cell_prepared('SELECT name FROM snmp_query WHERE id = ?', [$data['snmp_query_id']]);
			$description = sprintf('Reindex %s: %d -> %d indexes', $query_name, (int) ($old['count'] ?? 0), $state['count']);

			plugin_tags_create_tag(substr($description, 0, 64), 'device', (int) $data['host_id'], 0, read_config_option('tags_data_source_reindexed_color'), 'auto_data_source_reindexed');
		}
	}

	tags_state_set($key, json_encode($state));

	return $data;
}

/**
 * Detects poller-interval overruns and data-collector up/down state
 * transitions for every enabled poller, creating tags for newly-detected
 * transitions. Runs only on the primary poller (poller_id 1) and only when
 * a primary device is configured. Called from poller_tags.php's main flow
 * when the 'tags_poller_overrun' or 'tags_data_collector_status' settings
 * are enabled.
 *
 * @return int The number of tags created during this run.
 *
 * @global array $config Cacti global configuration array; used to check
 *                        the current poller_id.
 */
function plugin_tags_check_poller_events() {
	global $config;

	$tags = 0;

	if ((int) $config['poller_id'] !== 1) {
		return $tags;
	}

	$primary_device = (int) read_config_option('tags_primary_device');

	if ($primary_device <= 0) {
		return $tags;
	}

	$poller_interval = read_config_option('poller_interval');

	$pollers = db_fetch_assoc('SELECT id, name, status, total_time, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(last_status) AS heartbeat FROM poller WHERE disabled = "" ORDER BY id');

	foreach ($pollers as $poller) {
		$poller_id = (int) $poller['id'];

		if (read_config_option('tags_poller_overrun') == 'on') {
			$key      = 'poller_overrun:' . $poller_id;
			$overrun  = (float) $poller['total_time'] >= ($poller_interval - 1);
			$was_over = tags_state_get($key) === '1';

			if ($overrun && !$was_over) {
				$description = sprintf('Poller %s overrun: %.1fs / %ds', $poller['name'], $poller['total_time'], $poller_interval);
				plugin_tags_create_tag(substr($description, 0, 64), 'primary', $primary_device, 0, read_config_option('tags_poller_overrun_color'), 'auto_poller_overrun');
				$tags++;
			}

			tags_state_set($key, $overrun ? '1' : '0');
		}

		if ($poller_id > 1 && read_config_option('tags_data_collector_status') == 'on') {
			$key      = 'collector_down:' . $poller_id;
			$old_raw  = tags_state_get($key);
			$old      = $old_raw === null ? null : json_decode($old_raw, true);
			$down     = (int) $poller['heartbeat'] > $poller_interval * 2 || in_array((int) $poller['status'], [3, 5, 6], true);
			$was_down = is_array($old) && !empty($old['down']);
			$since    = $was_down ? (int) $old['since'] : time();

			if ($down && !$was_down) {
				$description = sprintf('Poller down: %s', $poller['name']);
				plugin_tags_create_tag(substr($description, 0, 64), 'primary', $primary_device, 0, read_config_option('tags_data_collector_status_color'), 'auto_data_collector_down');
				$tags++;
			} elseif (!$down && $was_down) {
				$description = sprintf('Poller recovered: %s, outage %s', $poller['name'], get_daysfromtime(time() - $since));
				plugin_tags_create_tag(substr($description, 0, 64), 'primary', $primary_device, 0, read_config_option('tags_data_collector_status_color'), 'auto_data_collector_recovered');
				$tags++;
			}

			tags_state_set($key, json_encode(['down' => $down, 'since' => $down ? $since : 0]));
		}
	}
	return $tags;
}

/**
 * Monitors all installed plugins and generates tag events when a plugin is
 * enabled, disabled, or has its version changed.
 *
 * Monitors all installed plugins and generates tag events when:
 *   - a plugin is enabled (status changes to 1),
 *   - a plugin is disabled (status changes from 1),
 *   - a plugin version changes.
 *
 * Previous plugin states are stored in the plugin_tags_state table. Runs
 * only on the primary poller (poller_id 1) and only when a primary device
 * is configured. Called from poller_tags.php's main flow when the
 * 'tags_plugin_state' setting is enabled.
 *
 * @return int Number of generated tags.
 *
 * @global array $config Cacti global configuration array; used to check
 *                        the current poller_id.
 */

function plugin_tags_check_plugin_events() {
	global $config;

	$tags = 0;

	if ((int)$config['poller_id'] !== 1) {
		return $tags;
	}

	$primary_device = (int)read_config_option('tags_primary_device');

	if ($primary_device <= 0) {
		return $tags;
	}

	$plugins = db_fetch_assoc('SELECT directory, status, version
		FROM plugin_config
		ORDER BY directory');

	foreach ($plugins as $plugin) {

		$key = 'plugin:' . $plugin['directory'];

		$old_raw = tags_state_get($key);
		$old     = $old_raw === null ? null : json_decode($old_raw, true);

		$old_status  = is_array($old) ? (int)$old['status'] : null;
		$old_version = is_array($old) ? $old['version'] : null;

		$new_status  = (int)$plugin['status'];
		$new_version = $plugin['version'];

		/* Plugin enabled */
		if ($old_status !== null && $old_status != 1 && $new_status == 1) {

			$description = sprintf('Plugin enabled: %s %s', $plugin['directory'], $new_version);

			plugin_tags_create_tag(substr($description, 0, 64), 'primary', $primary_device, 0, read_config_option('tags_plugin_state_color'), 'auto_plugin_enabled');

			$tags++;

		/* Plugin disabled */
		} elseif ($old_status !== null && $old_status == 1 && $new_status != 1) {

			$description = sprintf('Plugin disabled: %s',$plugin['directory']);

			plugin_tags_create_tag(substr($description, 0, 64), 'primary', $primary_device, 0, read_config_option('tags_plugin_state_color'), 'auto_plugin_disabled');

			$tags++;

		/* Plugin version changed */
		} elseif ($old_version !== null && version_compare($old_version, $new_version, '!=')) {

			$description = sprintf('Plugin updated: %s %s → %s', $plugin['directory'], $old_version, $new_version);

			plugin_tags_create_tag(substr($description, 0, 64), 'primary', $primary_device, 0, read_config_option('tags_plugin_state_color'), 'auto_plugin_updated');

			$tags++;
		}

		tags_state_set($key, json_encode([
			'status'  => $new_status,
			'version' => $new_version
		]));
	}

	return $tags;
}


/**
 * Hook implementation for Cacti's 'api_device_save' filter. Creates an
 * "auto_device_changed" tag for the saved device when the
 * 'tags_device_save' setting is enabled. Called by Cacti core via
 * api_plugin_hook('api_device_save', ...) after a device is saved.
 *
 * @param array $device The device values being saved; must include 'id'.
 *
 * @return array The unmodified $device array (this hook does not modify
 *               its payload).
 */

function plugin_tags_device_save($device) {

	if (read_config_option('tags_device_save') && $device['id'] > 0) {
		plugin_tags_create_tag(__('Device save/changed, id %s', $device['id'], 'tags'), 'device', $device['id'], 0, read_config_option("tags_device_save_color"), 'auto_device_changed');
	}

	return $device;
}


/**
 * Compares the running Cacti version against the previously recorded
 * version and creates an "auto_cacti_version_changed" tag when they
 * differ, then records the current version for next time. Called from
 * poller_tags.php's main flow when the 'tags_cacti_version' setting is
 * enabled.
 *
 * @return bool True if a tag was created; false otherwise.
 */
function plugin_tags_check_version() {

	$current_version  = get_cacti_version();
	$previous_version = read_config_option('plugin_tags_cacti_version');

	if (isset($previous_version) && $current_version != $previous_version) {
		plugin_tags_create_tag('Cacti version changed: ' . $current_version, 'all', 0, 0, read_config_option('tags_cacti_version_color'), 'auto_cacti_version_changed');
		set_config_option('plugin_tags_cacti_version', $current_version);
		return true;
	} else {
		set_config_option('plugin_tags_cacti_version', $current_version);
	}

	return false;
}


/**
 * Checks every enabled host for newly-added devices and SNMP uptime
 * restarts, creating "auto_device_added"/"auto_device_restart" tags as
 * appropriate, then records each host's current uptime for the next
 * comparison. Called from poller_tags.php's main flow on every run.
 *
 * @return int The number of tags created during this run.
 *
 * @global array $config Cacti global configuration array (unused directly
 *                        here; declared for parity with other check_*
 *                        functions in this file).
 */
function plugin_tags_check_hosts() {
	global $config;

	$tags_host_restart   = read_config_option("tags_host_restart");
	$tags_host_added     = read_config_option("tags_host_added");
	$tags_primary_device = read_config_option("tags_primary_device");

	$tags = 0;

	if ($tags_host_restart) {
		$color_host_restart = read_config_option('tags_host_restart_color');
	}

	if ($tags_host_added) {
		$color_host_added = read_config_option('tags_host_added_color');
	}

	$hosts = db_fetch_assoc ("SELECT id, snmp_sysUpTimeInstance, total_polls, ptu.uptime AS `old_uptime`
		FROM host AS h
		LEFT JOIN plugin_tags_uptime AS ptu
		ON h.id = ptu.host_id
		WHERE disabled != 'on' AND availability_method IN (1,2,5,6)");

	if (cacti_sizeof($hosts) > 0) {
		foreach ($hosts as $host) {

			if ($tags_host_added && $host['old_uptime'] === null && $host['total_polls'] < 10) { // adding new device
				if ($tags_primary_device > 0) {
					plugin_tags_create_tag(__('Device added, id %s', $host['id'], 'tags'), 'primary', $tags_primary_device, 0, $color_host_added, 'auto_device_added');
					$tags++;
				} else {
					cacti_log('Cannot create tag, primary device not set', 'tags');
				}

				plugin_tags_create_tag(__('Device added', 'tags'), 'device', $host['id'], 0, $color_host_added, 'auto_device_added');
				$tags++;
				continue;
			}

			if ($tags_host_restart && $host['old_uptime'] > $host['snmp_sysUpTimeInstance']
				&& $host['snmp_sysUpTimeInstance'] > 0) { // restart
				plugin_tags_create_tag(__('Restart, uptime was %s', get_daysfromtime($host['old_uptime']/100), 'tags'), 'device', $host['id'], 0, $color_host_restart, 'auto_device_restart');
				$tags++;
			}
		}
		db_execute("INSERT INTO plugin_tags_uptime (host_id, uptime)
			SELECT h.id, h.snmp_sysUpTimeInstance
			FROM host AS h
			WHERE h.disabled != 'on'
			AND h.availability_method IN (1,2,5,6)
			ON DUPLICATE KEY UPDATE
			uptime = VALUES(uptime)");
	}
	return $tags;
}


/**
 * Hook implementation for Cacti's 'device_remove' filter. Deletes all tag
 * events, archived tag events, and uptime records associated with the
 * removed device. Called by Cacti core via
 * api_plugin_hook('device_remove', ...) after a device is deleted.
 *
 * @param int $ids The host_id of the device being removed.
 *
 * @return void
 */
function plugin_tags_device_remove($ids) {
	db_execute_prepared('DELETE FROM plugin_tags_event WHERE host_id = ?', [$ids]);
	db_execute_prepared('DELETE FROM plugin_tags_event_archive WHERE host_id = ?', [$ids]);
	db_execute_prepared('DELETE FROM plugin_tags_uptime WHERE host_id = ?', [$ids]);
}


/**
 * Hook implementation for Cacti's 'rrd_graph_graph_options' filter. Adds
 * VRULE markers and a legend listing tags relevant to the graph being
 * rendered (matching by primary/device/site/graph/all target and the
 * graph's time range), grouping automatic tags together once the legend
 * limit is exceeded. Called by Cacti core via
 * api_plugin_hook('rrd_graph_graph_options', ...) while building a graph's
 * RRDtool options. If a tag was created a few seconds ago, it may not be
 * displayed immediately, but only after the next polling cycle; this is
 * due to the graph's time limit.
 *
 * @param array $data The graph's RRDtool option data, including 'start',
 *                     'end', and 'graph_id'.
 *
 * @return array The $data array with a 'Tags' header appended to
 *               'graph_opts' and the tag VRULEs/legend entries added to
 *               'txt_graph_items' when matching tags exist.
 *
 * @global array $config      Cacti global configuration array; used to
 *                             load this plugin's color array.
 * @global array $tags_colors The plugin's tag color palette, loaded from
 *                             include/arrays.php.
 */
function plugin_tags_rrd_graph_graph_options($data) {
	global $config, $tags_colors;

	require($config['base_path'] . '/plugins/tags/include/arrays.php');

	$tags = [];

	$all_colors = $tags_colors;

	$limit = read_config_option('tags_graph_limit');

	$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?',
		[$data['graph_id']]);

	$tags_primary = db_fetch_assoc_prepared("SELECT id, tag_time, description, color, type FROM plugin_tags_event WHERE
		tag_time BETWEEN ? AND ? AND
		enabled='on' AND
		target = 'primary' AND
		host_id = ? AND
		graph_id = 0
		ORDER BY tag_time desc",
		[$data['start'], $data['end'], $host_id]);

	$tags_host = db_fetch_assoc_prepared("SELECT id, tag_time, description, color, type FROM plugin_tags_event WHERE
		tag_time BETWEEN ? AND ? AND
		enabled='on' AND
		target = 'device' AND
		host_id = ? AND
		graph_id = 0
		ORDER BY tag_time desc",
		[$data['start'], $data['end'], $host_id]);

	$tags_all = db_fetch_assoc_prepared("SELECT id, tag_time, description, color, type FROM plugin_tags_event WHERE
		tag_time BETWEEN ? AND ? AND
		enabled='on' AND
		target = 'all'
		ORDER BY tag_time desc",
		[$data['start'], $data['end']]);

	$tags_graph = db_fetch_assoc_prepared("SELECT id, tag_time, description, color, type FROM plugin_tags_event WHERE
		tag_time BETWEEN ? AND ? AND
		enabled='on' AND
		target = 'graph' AND
		host_id = ? AND
		graph_id = ?
		ORDER BY tag_time desc",
		[$data['start'], $data['end'], $host_id, $data['graph_id']]);

	$site_id = db_fetch_cell_prepared('SELECT site_id FROM host WHERE id = ?',
		[$host_id]);

	if ($site_id > 0) {
		$tags_site = db_fetch_assoc_prepared("SELECT id, tag_time, description, color, type FROM plugin_tags_event WHERE
			tag_time BETWEEN ? AND ? AND
			enabled='on' AND
			target = 'site' AND
			site_id = ?
			ORDER BY tag_time desc",
			[$data['start'], $data['end'], $site_id]);
	}

	if (isset($tags_primary) && is_array($tags_primary) && cacti_sizeof($tags_primary) > 0) {
		$tags = array_merge($tags, $tags_primary);
	}
	if (isset($tags_host) && is_array($tags_host) && cacti_sizeof($tags_host) > 0) {
		$tags = array_merge($tags, $tags_host);
	}
	if (isset($tags_all) && is_array($tags_all) && cacti_sizeof($tags_all) > 0) {
		$tags = array_merge($tags, $tags_all);
	}
	if (isset($tags_graph) && is_array($tags_graph) && cacti_sizeof($tags_graph) > 0) {
		$tags = array_merge($tags, $tags_graph);
	}
	if (isset($tags_site) && is_array($tags_site) && cacti_sizeof($tags_site) > 0) {
		$tags = array_merge($tags, $tags_site);
	}

	usort($tags, function ($a, $b) {
		return $b['tag_time'] <=> $a['tag_time'];
	});

	$count        = cacti_sizeof($tags);
	$legend_limit = max(1, (int) $limit);
	$vrule_limit  = $legend_limit * 5;
	$add          = [];

	if ($count > 0) {
		$data['graph_opts'] .= 'COMMENT:"Tags\\:\l"' . RRD_NL;

		if ($count <= $legend_limit) {
			foreach ($tags as $tag) {
				$color = array_key_exists($tag['color'], $all_colors) ? $tag['color'] : '999999';
				$label = date('y-m-d H:i', $tag['tag_time']) . ' ' . $tag['description'];
				$label = str_replace(':', '\\:', $label);

				$add[] = RRD_NL . 'VRULE:' . $tag['tag_time'] . "#$color:'$label\l'" . RRD_NL;
			}
		} else {
			$automatic_groups = [];
			$manual_tags      = [];

			foreach ($tags as $tag) {
				if (tags_is_automatic_type($tag['type'])) {
					$type = $tag['type'];

					if (!isset($automatic_groups[$type])) {
						$automatic_groups[$type] = [
							'count'  => 0,
							'latest' => $tag,
							'dates'  => [],
						];
					}

					$automatic_groups[$type]['count']++;

					if (cacti_sizeof($automatic_groups[$type]['dates']) < 2) {
						$automatic_groups[$type]['dates'][] = $tag['tag_time'];
					}
				} else {
					$manual_tags[] = $tag;
				}
			}

			$automatic_groups = array_values($automatic_groups);
			usort($automatic_groups, function ($a, $b) {
				return $b['latest']['tag_time'] <=> $a['latest']['tag_time'];
			});

			$legend_entries = [];

			foreach (array_slice($automatic_groups, 0, $legend_limit) as $group) {
				$type       = $group['latest']['type'];
				$type_label = $tags_event_types[$type] ?? $type;
				$dates      = array_map(function ($timestamp) {
					return date('y-m-d H:i', $timestamp);
				}, $group['dates']);
				$label = $type_label . ' (' . $group['count'] . '): ' . implode(', ', $dates);

				$legend_entries[] = [
					'tag'   => $group['latest'],
					'label' => $label,
				];
			}

			$remaining = $legend_limit - cacti_sizeof($legend_entries);

			if ($remaining > 0) {
				foreach (array_slice($manual_tags, 0, $remaining) as $tag) {
					$legend_entries[] = [
						'tag'   => $tag,
						'label' => date('y-m-d H:i', $tag['tag_time']) . ' ' . $tag['description'],
					];
				}
			}

			$legend_by_id = [];

			foreach ($legend_entries as $entry) {
				$legend_by_id[$entry['tag']['id']] = str_replace(':', '\\:', $entry['label']);
			}

			$rendered_legend_ids = [];

			foreach (array_slice($tags, 0, $vrule_limit) as $tag) {
				$color = array_key_exists($tag['color'], $all_colors) ? $tag['color'] : '999999';
				$line  = 'VRULE:' . $tag['tag_time'] . "#$color";

				if (isset($legend_by_id[$tag['id']])) {
					$line .= ":'" . $legend_by_id[$tag['id']] . "\l'";
					$rendered_legend_ids[$tag['id']] = true;
				}

				$add[] = RRD_NL . $line . RRD_NL;
			}

			foreach ($legend_entries as $entry) {
				if (!isset($rendered_legend_ids[$entry['tag']['id']])) {
					$label = str_replace(':', '\\:', $entry['label']);
					$add[] = 'COMMENT:"' . $label . '\l"' . RRD_NL;
				}
			}
		}

		if (!empty($add)) {
			$add[] = 'COMMENT:"  \n"' . RRD_NL;

			if (is_array($data['txt_graph_items'])) {
				$data['txt_graph_items'] = array_merge($add, $data['txt_graph_items']);
			} elseif (is_string($data['txt_graph_items'])) {
				$data['txt_graph_items'] = implode(RRD_NL, $add) . $data['txt_graph_items'];
			} else {
				cacti_log('Error 01 - incorrect variable type', false, 'Tags');
			}
		}
	}

	return $data;
}


/**
 * Hook implementation for Cacti's 'graph_buttons'/'graph_buttons_thumbnails'
 * filters. Prints a link/icon that opens tags.php filtered to the current
 * graph's device. Called by Cacti core via
 * api_plugin_hook('graph_buttons'/'graph_buttons_thumbnails', ...) while
 * rendering a graph's action buttons.
 *
 * @param array $data Hook payload; $data[1]['local_graph_id'] identifies
 *                     the current graph.
 *
 * @return void Outputs HTML directly.
 *
 * @global array $config Cacti global configuration array; used to build
 *                        the tags.php link URL.
 */
function plugin_tags_graph_button($data) {
	global $config;

	$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local 
		WHERE id = ?',[$data[1]['local_graph_id']]);

	$redir = $config['url_path'] . 'plugins/tags/tags.php?host_id=' . $host_id;

	$fav = '<i class="fas fa-tags" title="' . __esc('Show tags for this graph', 'tags') . '"></i>';
	print '<a class="iconLink" href="' . html_escape($redir) . '">' . $fav . '</a><br/>';
}


/**
 * Inserts a new tag event row. For certain automatic tag types, the target
 * may be redirected to 'all' or the configured primary device (per the
 * 'tags_automatic_how' setting) before saving, to prevent duplicated
 * automatic tags across every device. Called throughout this file's
 * change-detection functions (plugin_tags_check_*, plugin_tags_device_*)
 * whenever a tag-worthy event is detected.
 *
 * @param string $description The tag's description text.
 * @param string $target      The tag's target scope ('all', 'primary',
 *                             'device', 'graph', or 'site').
 * @param int    $host_id     The associated host id, or 0 when not
 *                             applicable.
 * @param int    $graph_id    The associated graph id, or 0 when not
 *                             applicable.
 * @param string $color       The tag's six-character RGB color.
 * @param string $type        The tag's event type (e.g. 'manual' or an
 *                             'auto_*' type).
 *
 * @return bool True once the tag has been inserted, or false when an
 *              automatic tag needed a primary device that isn't
 *              configured.
 */
function plugin_tags_create_tag($description, $target, $host_id, $graph_id, $color, $type) {

	// In the settings, you can specify for certain automatic tags whether they should apply only to the primary device or to all devices.
	// I’m adjusting this here to prevent code duplication when there are multiple calls.

	if (in_array($type, ['auto_plugin_disabled', 'auto_plugin_enabled', 'auto_plugin_updated', 'auto_data_collector_down', 'auto_data_collector_recovered', 'auto_poller_overrun', 'auto_cacti_version_changed'])) {

		if (read_config_option('tags_automatic_how') == 'all') {

			db_execute_prepared("INSERT INTO plugin_tags_event
				(type, description, tag_time, target, host_id, graph_id, color, enabled)
				VALUES
				(?, ?, unix_timestamp(), 'all', 0, 0, ?, 'on')",
				[$type, $description, $color]);
		} else { // primary

			$primary_device = (int) read_config_option('tags_primary_device');

			if ($primary_device > 0) {
				db_execute_prepared("INSERT INTO plugin_tags_event
					(type, description, tag_time, target, host_id, graph_id, color, enabled)
					VALUES
					(?, ?, unix_timestamp(), 'primary', ?, 0, ?, 'on')",
					[$type, $description, $primary_device, $color]);
			} else {
				cacti_log('Cannot create tag, primary device not set', 'tags');
				return false;
			}
		}
	} else {
		db_execute_prepared("INSERT INTO plugin_tags_event
			(type, description, tag_time, target, host_id, graph_id, color, enabled)
			VALUES
			(?, ?, unix_timestamp(), ?, ?, ?, ?, 'on')",
			[$type, $description, $target, $host_id, $graph_id, $color]);
	}

	return true;
}


/**
 * Propagates changes to automatic-tag color and primary-device settings
 * onto already-created tag events, so existing tags stay consistent with
 * the user's latest configuration (e.g. re-coloring existing tags of a
 * type when its configured color changes). Runs automatically on every
 * poller run; called from poller_tags.php's main flow.
 *
 * @return bool True after settings changes have been processed.
 *
 * @global array $settings Cacti's registered Settings page fields (unused
 *                          directly here; declared for parity with other
 *                          settings-related functions).
 */
function plugin_tags_settings_update() {
	global $settings;

	$tags_sett = [
		'tags_cacti_version_color',
		'tags_device_save_color',
		'tags_host_added_color',
		'tags_host_restart_color',
		'tags_data_source_reindexed_color',
		'tags_poller_overrun_color',
		'tags_data_collector_status_color',
		'tags_plugin_state_color',
		'tags_primary_device'
	];

	$automatic_types = [
		'tags_cacti_version_color'         => ['auto_cacti_version_changed'],
		'tags_device_save_color'           => ['auto_device_changed'],
		'tags_host_added_color'            => ['auto_device_added'],
		'tags_host_restart_color'          => ['auto_device_restart'],
		'tags_data_source_reindexed_color' => ['auto_data_source_reindexed'],
		'tags_poller_overrun_color'        => ['auto_poller_overrun'],
		'tags_data_collector_status_color' => ['auto_data_collector_down', 'auto_data_collector_recovered'],
		'tags_plugin_state_color'          => ['auto_plugin_enabled', 'auto_plugin_disabled', 'auto_plugin_updated'],
	];

	foreach ($tags_sett as $ts) {
		if (config_value_exists($ts . '_old')) {
			$old = read_config_option($ts . '_old');
			$act = read_config_option($ts);

			if ($act != $old) {
				switch ($ts) {
					case 'tags_primary_device':
						db_execute_prepared("UPDATE plugin_tags_event
							SET host_id = ?
							WHERE target = 'primary' AND
							host_id = ?",
							[$act, $old]);

						set_config_option($ts . '_old', $act);

						break;

					default:
						if (isset($automatic_types[$ts])) {
							foreach ($automatic_types[$ts] as $automatic_type) {
								db_execute_prepared('UPDATE plugin_tags_event
									SET color = ?
									WHERE type = ? AND
									color = ?',
									[$act, $automatic_type, $old]);
							}
						}

						set_config_option($ts . '_old', $act);

						break;
				}
			}
		} else { // save default as old values
			set_config_option($ts . '_old', read_config_option($ts));
		}
	}

	return true;
}


/**
 * Finds tag events older than the configured retention period
 * ('tags_retention' days) and moves them to the archive table. Called
 * from poller_tags.php's main flow on every run; a retention of 0 disables
 * archiving entirely.
 *
 * @return bool|void True when archiving is disabled (retention is 0);
 *                    otherwise no explicit return value.
 */
function plugin_tags_archive() {

	$retention_days = (int) read_config_option('tags_retention');

	if ($retention_days == 0) {
		return true;
	}

	$limit = time() - ($retention_days * 86400);

	$result = db_fetch_assoc_prepared('SELECT id
		FROM plugin_tags_event
		WHERE tag_time < ?',
		[$limit]);

	if (cacti_sizeof($result) > 0) {
		$ids = array_column($result, 'id');
		plugin_tags_move_old_events($ids);
	}
}


/**
 * Moves the given tag events from plugin_tags_event into
 * plugin_tags_event_archive, in batches of 50, then deletes them from the
 * active table. Called from plugin_tags_archive() for expired events and
 * from tags.php's form_actions() for manually-archived events.
 *
 * @param array $ids The plugin_tags_event.id values to archive.
 *
 * @return void
 */
function plugin_tags_move_old_events($ids) {

	cacti_log('PLUGIN TAGS: moving ' . cacti_sizeof($ids) . ' records to the archive');

	foreach (array_chunk($ids, 50) as $chunk) {
		$chunk = array_map('intval', $chunk);

		$placeholders = implode(',', array_fill(0, count($chunk), '?'));

		db_execute_prepared("INSERT INTO plugin_tags_event_archive
			SELECT * FROM plugin_tags_event
			WHERE id IN ($placeholders)",
			$chunk);

		db_execute_prepared("DELETE FROM plugin_tags_event
			WHERE id IN ($placeholders)",
			$chunk);
	}
}

