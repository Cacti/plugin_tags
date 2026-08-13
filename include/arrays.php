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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/


$tags_retention = [
	'0'   => __('Never', 'tags'),
	'7'   => __('%d Days', 7, 'tags'),
	'14'  => __('%d Days', 14, 'tags'),
	'30'  => __('%d Month', 1, 'tags'),
	'90'  => __('%d Months', 3, 'tags'),
	'180' => __('%d Months', 6, 'tags'),
	'365' => __('%d Year', 1, 'tags'),
	'730' => __('%d Years', 2, 'tags')
];

$tags_colors = [
	'000000' => __('Black', 'tags'),
	'ff0000' => __('Red', 'tags'),
	'00ff00' => __('Green', 'tags'),
	'0000ff' => __('Blue', 'tags'),
	'ffff00' => __('Yellow', 'tags'),
	'cccccc' => __('Grey', 'tags'),
	'663300' => __('Brown', 'tags'),
	'ff66b2' => __('Pink', 'tags'),
];

$tags_type = [
	'automatic' => __('Automatic', 'tags'),
	'manual'    => __('Manual', 'tags'),
];

$tags_auto_how = [
	'primary' => __('Primary device', 'tags'),
	'all'    => __('All devices', 'tags'),
];


$tags_event_types = [
	'manual'                     => __('Manual', 'tags'),
	'auto_device_added'          => __('Device Added', 'tags'),
	'auto_device_changed'        => __('Device Changed', 'tags'),
	'auto_device_restart'        => __('Device Restart', 'tags'),
	'auto_cacti_version_changed' => __('Cacti Version Changed', 'tags'),
	'auto_other'                    => __('Other Automatic Events', 'tags'),
	'auto_data_source_reindexed'    => __('Data Source Reindexed', 'tags'),
	'auto_poller_overrun'           => __('Poller Overrun', 'tags'),
	'auto_data_collector_down'      => __('Data Collector Down', 'tags'),
	'auto_data_collector_recovered' => __('Data Collector Recovered', 'tags'),
];

$tags_target = [
	'all'     => 'All devices',
	'site'    => 'All devices with the same site id',
	'device'  => 'Specific device',
	'graph'   => 'Specific graph',
	'primary' => 'Primary Cacti device',
];

$tags_fields = [
	'general_spacer' => [
		'method'        => 'spacer',
		'friendly_name' => __('General Settings', 'tags')
	],
	'description' => [
		'method'        => 'textbox',
		'friendly_name' => __('Description', 'tags'),
		'description'   => __('Tag message, max. length 64 chars', 'tags'),
		'value'         => '|arg1:description|',
		'max_length'    => '64',
	],
	'target' => [
		'friendly_name' => __('For which device', 'tags'),
		'method'        => 'drop_array',
		'on_change'     => 'setTag()',
		'array'         => $tags_target,
		'default'       => 'all',
		'description'   => __('Tag can be assigned with on or more devices', 'tags'),
		'value'         => '|arg1:target|',
	],
	'host_id' => [
		'method'        => 'drop_callback',
		'friendly_name' => __('Device'),
		'description'   => __('Select device'),
		'filter'        => FILTER_VALIDATE_INT,
		'value'         => isset($data['host_id']) ? $data['host_id'] : '',
		'sql'           => 'SELECT distinct id, description AS name FROM host ORDER BY description',
		'action'        => 'ajax_hosts',
		'default'       => '',
		'none_value'    => __('Select a Device', 'tags'),
	],
	'graph_id' => [
		'method'        => 'drop_callback',
		'friendly_name' => __('Graph'),
		'description'   => __('Select graph'),
		'filter'        => FILTER_VALIDATE_INT,
		'sql'           => 'select gt.name, gl.host_id, IF(gl.graph_template_id = 0, 0, IF(gl.snmp_query_id = 0, 2, 1)) AS graph_source, IF(gl.snmp_query_id > 0, sqg.name, gt.name) AS source_name FROM graph_local AS gl INNER JOIN graph_templates_graph AS gtg ON gl.id=gtg.local_graph_id LEFT JOIN graph_templates AS gt ON gl.graph_template_id=gt.id LEFT JOIN aggregate_graphs AS ag ON ag.local_graph_id=gl.id LEFT JOIN host AS h ON h.id=gl.host_id LEFT JOIN sites AS s ON h.site_id=s.id LEFT JOIN snmp_query_graph AS sqg ON gl.snmp_query_id = sqg.snmp_query_id AND gl.graph_template_id = sqg.graph_template_id AND gl.snmp_query_graph_id = sqg.id WHERE ag.local_graph_id IS NULL ORDER BY `title_cache` ASC',
		'action'        => 'ajax_graphs',
		'value'         => isset($data['graph_id']) ? $data['graph_id'] : '',
		'none_value'    => __('None', 'tags'),
	],
	'site_id' => [
		'method'        => 'drop_callback',
		'friendly_name' => __('Site'),
		'description'   => __('Select site'),
		'filter'        => FILTER_VALIDATE_INT,
		'sql'           => 'SELECT id, name FROM sites ORDER BY `name` ASC',
		'action'        => 'ajax_sites',
		'value'         => isset($data['site_id']) ? $data['site_id'] : '',
		'none_value'    => __('None', 'tags'),
	],
	'tag_time' => [
		'method' => 'textbox',
		'friendly_name' => __('Date and time of tag', 'tags'),
		'description' => __('The date / time for this tag.', 'tags'),
		'value' => isset($data['tag_time']) ? date('Y-m-d H:i', $data['tag_time']) : '',
		'default' => date('Y-m-d H:i', time()),
		'max_length' => 22,
		'size' => 22,
	],
	'color' => [
		'friendly_name' => __('Choose color', 'tags'),
		'method'        => 'drop_array',
		'array'         => $tags_colors,
		'default'       => 'all',
		'description'   => __('Select color for this tag', 'tags'),
		'value'         => '|arg1:color|',
	],
	'enabled' => [
		'method'        => 'checkbox',
		'friendly_name' => __('Enable', 'tags'),
		'description'   => __('Disabled tag will not be shown in graph.', 'tags'),
		'value'         => '|arg1:enabled|',
		'default'       => 'on',
	],
	'id' => [
		'method' => 'hidden_zero',
		'value'  => '|arg1:id|'
	],
];

$settings['tags'] = [
	'tags_display_header1' => [
		'friendly_name' => __('Settings', 'tags'),
		'method'        => 'spacer',
	],
	'tags_retention' => [
		'friendly_name' => __('Data Retention', 'tags'),
		'description' => __('After this time, tags will be moved to the archive and will no longer be displayed', 'mactrack'),
		'method' => 'drop_array',
		'default' => '365',
		'array' => $tags_retention,
	],
	'tags_graph_limit' => [
		'friendly_name' => __('Graph legend limit', 'tags'),
		'description'   => __('If number of tags is bigger than limit, legend will be aggregated. Due to potential display issues, the number of vertical lines is limited to 5 times this value.', 'tags'),
		'method'        => 'textbox',
		'max_length'    => 2,
		'default'       => 4,
	],
	'tags_primary_device' => [
		'friendly_name' => __('Select Primary Device', 'tags'),
		'description'   => __('Typically a local Cacti device. Tags related to the Cacti system will be displayed here.', 'tags'),
		'method'        => 'drop_sql',
		'default'       => '',
		'none_value'    => __('None', 'tags'),
		'sql'           => 'SELECT id, description AS name FROM host ORDER BY description',
	],
	'tags_display_header2' => [
		'friendly_name' => __('Automatic tags - host specific', 'tags'),
		'method'        => 'spacer',
	],
	'tags_host_restart' => [
		'friendly_name' => __('Host restart automatic tag', 'tags'),
		'description'   => __('If checked, plugin will create automatic tag when device restarts', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_host_restart_color' => [
		'friendly_name' => __('Host restart tag color', 'tags'),
		'description'   => __('Recommendation: Use a different color for each automatic tag', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_host_added' => [
		'friendly_name' => __('Host added automatic tag. Will be displayed on \'Default\' device and specific device', 'tags'),
		'description'   => __('If checked, plugin will create automatic tags when device is added', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_host_added_color' => [
		'friendly_name' => __('Host added tag color', 'tags'),
		'description'   => __('Recommendation: Use a different color for each automatic tag', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_device_save' => [
		'friendly_name' => __('Device save/change automatic tag', 'tags'),
		'description'   => __('If checked, plugin will create automatic tag when device is saved (changed)', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_device_save_color' => [
		'friendly_name' => __('Device save/change tag color', 'tags'),
		'description'   => __('Recommendation: Use a different color for each automatic tag', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_data_source_reindexed' => [
		'friendly_name' => __('Data source reindex automatic tag', 'tags'),
		'description'   => __('Create a tag when the set of indexes returned by a device data query changes.', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_data_source_reindexed_color' => [
		'friendly_name' => __('Data source reindex tag color', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_display_header3' => [
		'friendly_name' => __('Automatic tags - others', 'tags'),
		'method'        => 'spacer',
	],
	'tags_automatic_how' => [
		'friendly_name' => __('Choose how to display tags listed below', 'tags'),
		'description' => __('For which devices will the tags below displayed?', 'mactrack'),
		'method' => 'drop_array',
		'default' => 'all',
		'array' => $tags_auto_how,
	],
	'tags_cacti_version' => [
		'friendly_name' => __('Cacti version automatic tag', 'tags'),
		'description'   => __('If checked, plugin will create automatic tag when Cacti version is changed. Tags is for all graphs.', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_cacti_version_color' => [
		'friendly_name' => __('Cacti version tag color', 'tags'),
		'description'   => __('Recommendation: Use a different color for each automatic tag', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_poller_overrun' => [
		'friendly_name' => __('Poller overrun automatic tag', 'tags'),
		'description'   => __('Create a tag when a data collector polling cycle exceeds the configured poller interval.', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_poller_overrun_color' => [
		'friendly_name' => __('Poller overrun tag color', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_data_collector_status' => [
		'friendly_name' => __('Data collector status automatic tags', 'tags'),
		'description'   => __('Create tags when a remote data collector goes down or recovers.', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_data_collector_status_color' => [
		'friendly_name' => __('Data collector status tag color', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
	'tags_plugin_state' => [
		'friendly_name' => __('Plugin status automatic tag', 'tags'),
		'description'   => __('Create a tag when any plugin\'s status change (enable/disable/upgrade)', 'tags'),
		'method'        => 'checkbox',
		'default'       => 'on',
	],
	'tags_plugin_state_color' => [
		'friendly_name' => __('Plugin state tag color', 'tags'),
		'method'        => 'drop_array',
		'default'       => array_key_first($tags_colors),
		'array'         => $tags_colors,
	],
];


