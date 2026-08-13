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

chdir('../../');
require_once('./include/auth.php');
require_once($config['base_path'] . '/plugins/tags/include/functions.php');
require($config['base_path'] . '/plugins/tags/include/arrays.php');

$tags_actions_menu = [
	1 => __('Delete', 'tags'),
	2 => __('Disable', 'tags'),
	3 => __('Enable', 'tags'),
	4 => __('Archive', 'tags'),
];

set_default_action();

switch (get_request_var('action')) {
	case 'ajax_hosts':
		get_allowed_ajax_hosts(false, false);

		break;
	case 'ajax_graphs':
		if (!isempty_request_var('host_id')) {
			$sql_where = 'WHERE host_id = ' . get_filter_request_var('host_id');
		} else {
			$sql_where = '';
		}	
		get_allowed_ajax_graphs($sql_where);

		break;

	case 'ajax_sites':
		if (!isempty_request_var('site_id')) {
			$sql_where = 'WHERE id = ' . get_filter_request_var('ste_id');
		} else {
			$sql_where = '';
		}

		tags_get_ajax_sites($sql_where);

		break;

	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();
		tags_edit();
		bottom_footer();

		break;
	default:
		top_header();
		tags_list();
		bottom_footer();

		break;
}

/** Execute or confirm bulk tag actions.
 *
 * @return void
 */
function form_actions() {
	global $tags_actions_menu;

	// ================= input validation =================
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^([a-zA-Z0-9_]+)$/']]);
	// ====================================================

	$sql_table = 'plugin_tags_event';

	if (get_filter_request_var('is_archive')) {
		$sql_table = 'plugin_tags_event_archive';
	}

	// if we are to save this form, instead of display it
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_filter_request_var('drp_action') == 1) {
				if (cacti_sizeof($selected_items)) {
					foreach ($selected_items as $item) {
						db_execute_prepared("DELETE FROM $sql_table WHERE id = ?", [$item]);
					}
				}
			} elseif (get_filter_request_var('drp_action') == 2) { // disable
				foreach ($selected_items as $item) {
					db_execute_prepared("UPDATE $sql_table SET enabled = '' WHERE id = ?", [$item]);
				}
			} elseif (get_filter_request_var('drp_action') == 3) { // enable
				foreach ($selected_items as $item) {
					db_execute_prepared("UPDATE $sql_table SET enabled = 'on' WHERE id = ?", [$item]);
				}
			} elseif (get_filter_request_var('drp_action') == 4) { // archive
				plugin_tags_move_old_events($selected_items);
			}
		}

		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false');
		exit;
	}

	// setup some variables
	$item_list   = '';
	$items_array = [];

	// loop through each of the graphs selected on the previous page and get more info about them
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			// ================= input validation =================
			input_validate_input_number($matches[1]);
			// ====================================================

			$item_list .= '<li>' . db_fetch_cell_prepared("SELECT description FROM $sql_table WHERE id = ?", [$matches[1]]) . '</li>';
			$items_array[] = $matches[1];
		}
	}

	top_header();

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($tags_actions_menu[get_filter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (cacti_sizeof($items_array) > 0) {
		if (get_filter_request_var('drp_action') == 1) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following item.', 'Click \'Continue\' to delete following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete item', 'Delete items', cacti_sizeof($items_array)) . "'>";
		} elseif (get_filter_request_var('drp_action') == 2) { // disable
			print "><tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to disable the following item.', 'Click \'Continue\' to delete following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Disable item', 'Disable items', cacti_sizeof($items_array)) . "'>";
		} elseif (get_filter_request_var('drp_action') == 3) { // enable
			print "><tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to enable the following item.', 'Click \'Continue\' to delete following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Enable item', 'Enable items', cacti_sizeof($items_array)) . "'>";
		} elseif (get_filter_request_var('drp_action') == 4) { // archive
			print "><tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to archive the following item.', 'Click \'Continue\' to archive following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Archive item', 'Archive items', cacti_sizeof($items_array)) . "'>";
		}

	} else {
		raise_message(40);
		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($items_array) ? serialize($items_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			<input type='hidden' name='is_archive' value='" . (get_filter_request_var('is_archive') ? 1 : 0) . "'>
			$save_html
		</td>
	</tr>";


	html_end_box();

	form_end();

	bottom_footer();
}

/** Validate and save a tag from the edit form.
 *
 * @return void
 */
function form_save() {
	global $tags_colors, $tags_target;

	if (isset_request_var('save_component')) {
		$save['id']   = get_filter_request_var('id');
		$save['description'] = form_input_validate(get_nfilter_request_var('description'), 'description', '', false, 3);


		$available_colors = $tags_colors;

		if (array_key_exists(get_nfilter_request_var('color'), $available_colors)) {
			$save['color'] = get_nfilter_request_var('color');
		} else {
			$save['color'] = array_key_first($available_colors);
		}

		if (!array_key_exists(get_nfilter_request_var('target'), $tags_target)) {
			$_SESSION['sess_error_fields']['target'] = 'target';
		} else {
			$save['target'] = get_nfilter_request_var('target');

			if (get_nfilter_request_var('target') == 'all') {
				$save['host_id'] = 0;
				$save['graph_id'] = 0;
			} elseif  (get_nfilter_request_var('target') == 'device') {
				$save['host_id'] = get_filter_request_var('host_id');
			} elseif  (get_nfilter_request_var('target') == 'graph') {
				$host_id = db_fetch_cell_prepared('SELECT host_id FROM graph_local WHERE id = ?', [get_filter_request_var('graph_id')]);
				if ($host_id > 0) {
					$save['host_id'] = $host_id;
					$save['graph_id'] = get_filter_request_var('graph_id');
				} else {
					$_SESSION['sess_error_fields']['graph_id'] = 'graph_id';
				}
			} elseif (get_nfilter_request_var('target') == 'site') {
				$site_id = db_fetch_cell_prepared('SELECT id FROM sites WHERE id = ?', [get_filter_request_var('site_id')]);
				if ($site_id > 0) {
					$save['site_id'] = $site_id;
				} else {
					$_SESSION['sess_error_fields']['site_id'] = 'site_id';
				}
			}
		}

		$save['tag_time']     = strtotime(get_nfilter_request_var('tag_time'));

		if (isset_request_var('enabled')) {
			$save['enabled'] = 'on';
		} else {
			$save['enabled'] = '';
		}

		if ($save['id'] > 0 && isset($_SESSION['sess_error_fields']) && cacti_sizeof($_SESSION['sess_error_fields'])) {
			foreach ($_SESSION['sess_error_fields'] as $item) {
				unset($save[$item], $_SESSION['sess_error_fields'][$item]);
			}
			clear_messages();
		}

		if (!is_error_message()) {
			$saved_id = sql_save($save, 'plugin_tags_event');

			if ($saved_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false&action=edit&id=' . (empty($saved_id) ? get_nfilter_request_var('id') : $saved_id));
		} else {
			header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false');
		}
	}
	exit;
}

/** Render the tag edit form.
 *
 * @return void
 */
function tags_edit() {
	global $tags_fields, $tags_colors;

	// ================= input validation =================
	get_filter_request_var('id');
	// ====================================================


	if (isset_request_var('id')) {
		$data = db_fetch_row_prepared('SELECT *
			FROM plugin_tags_event
			WHERE id = ?',
			[get_filter_request_var('id')]);

		$header_label = __('Tag [edit: %s]', $data['description']);

		$tags_fields['color']['array'] = $tags_colors;
	} else {
		$data = [];
		$header_label = __('Tag [new]');
		$tags_fields['color']['array'] = $tags_colors;
	}

	$tags_fields['host_id']['id'] = isset($data['host_id']) ? $data['host_id'] : '';
	if (isset($data['host_id']) && $data['host_id'] > 0) {
		$tags_fields['host_id']['value'] = db_fetch_cell_prepared('SELECT description FROM host WHERE id = ?', [$data['host_id']]);
	}

	$tags_fields['graph_id']['id'] = isset($data['graph_id']) ? $data['graph_id'] : '';
	if (isset($data['graph_id']) && $data['graph_id'] > 0) {
		$tags_fields['graph_id']['value'] = db_fetch_cell_prepared('SELECT gt.name FROM graph_local AS gl 
		INNER JOIN graph_templates_graph AS gtg ON gl.id=gtg.local_graph_id 
		LEFT JOIN graph_templates AS gt ON gl.graph_template_id=gt.id WHERE gl.id = ?', [$data['graph_id']]);
	}

	$tags_fields['site_id']['id'] = isset($data['site_id']) ? $data['site_id'] : '';
	if (isset($data['site_id']) && $data['site_id'] > 0) {
		$tags_fields['site_id']['value'] = db_fetch_cell_prepared('SELECT name FROM sites WHERE id = ?', [$data['site_id']]);
	}

	// I don't know why it doesn't work in arrays.php
	if (isset($data['tag_time']) && $data['tag_time'] > 0) {
		$tags_fields['tag_time']['value'] = date('Y-m-d H:i',$data['tag_time']);
	}

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		[
			'config' => ['no_form_tag' => true],
			'fields' => inject_form_variables($tags_fields, $data)
		]
	);

	form_hidden_box('save_component', '1', '');

	html_end_box(true, true);

	form_save_button(htmlspecialchars(basename($_SERVER['PHP_SELF'])));
?>
	<script type='text/javascript'>

	var dateOpen = false;

	$(function() {

		setTag();

// tohle to kdyztak resi
 //   $('#target').on('change', function () {
   //     setTag();
   // });


		$('#tag_time').after('<i id="tagTime" class="calendar fa fa-calendar" title="<?php print __esc('Start Date/Time Selector', 'tags');?>"></i>');

		$('#tagTime').click(function() {
			if (dateOpen) {
				dateOpen = false;
				$('#tag_time').datetimepicker('hide');
			} else {
				dateOpen = true;
				$('#tag_time').datetimepicker('show');
			}
		});


		$('#tag_time').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});



	});



	function setTag() {
		var target_type = $('#target').val();

		$('#row_host_id').hide();
		$('#row_graph_id').hide();
		$('#row_site_id').hide();

		if (target_type == 'device') {
			$('#row_host_id').show();
		}

		if (target_type == 'graph') {
			$('#row_graph_id').show();
		}

		if (target_type == 'site') {
			$('#row_site_id').show();
		}
	}

	</script>

<?php

}


/** Validate and persist list filter request variables.
 *
 * @return void
 */
function request_validation() {
	$filters = [
		'tag_timespan' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			],
		'rows' => [
			'filter'  => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			],
		'page' => [
			'filter'  => FILTER_VALIDATE_INT,
			'default' => '1'
			],
		'filter' => [
			'filter'  => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			],
		'sort_column' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'tag_time',
			'options' => ['options' => 'sanitize_search_string']
			],
		'sort_direction' => [
			'filter'  => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => ['options' => 'sanitize_search_string']
			],
		'target' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'default' => '-1'
			],
		'type' => [
			'filter'  => FILTER_CALLBACK,
			'options' => ['options' => 'sanitize_search_string'],
			'default' => '-1'
			],
	];

	validate_store_request_vars($filters, 'sess_tags_event');
}

/** Render the filtered tag list.
 *
 * @return void
 */
function tags_list() {
	global $config, $tags_actions_menu, $tags_colors, $tags_target, $tags_type;

	$sql_table = 'plugin_tags_event';

	if (get_filter_request_var('archive')) {
		$sql_table = 'plugin_tags_event_archive';
	}

	include_once($config['base_path'] . '/lib/timespan_settings.php');

	request_validation();

/*
if (get_request_var('predefined_timespan')) {
	echo "budu resit cas";
}
*/

	$sql_where = '';

	if (get_filter_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_filter_request_var('rows');
	}

	if (get_filter_request_var('host_id') && get_filter_request_var('host_id') > 0) {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . 'host_id  = ' . get_filter_request_var('host_id');
	}

	if (get_nfilter_request_var('target') && in_array(get_nfilter_request_var('target'), array_keys($tags_target))) {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . 'target = "' . get_nfilter_request_var('target') . '"';
	}

	if (get_nfilter_request_var('type') === 'automatic') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . "type LIKE 'auto\\_%'";
	} elseif (get_nfilter_request_var('type') === 'manual') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . "type = 'manual'";
	}

	if (get_filter_request_var('tag_timespan') >= GT_CUSTOM) {
		$graph_start = (int) get_current_graph_start();
		$graph_end   = (int) get_current_graph_end();

		if ($graph_start > 0 && $graph_end >= $graph_start) {
			$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
				"tag_time BETWEEN $graph_start AND $graph_end";
		}
	}

	tags_filter();

	$primary = (int) read_config_option('tags_primary_device');

	if (get_nfilter_request_var('target') == 'primary' && $primary == 0 ) {
		print __('You have to set primary device in Console - Configuration - Settings - Tags');
		return true;
	}

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . ' plugin_tags_event.description LIKE "%' . get_request_var('filter') . '%"';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows * (get_request_var('page') - 1)) . ',' . $rows;


//!! tady pak asi budu hledat i graf, jestli pod to zarizeni spada

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM $sql_table
		$sql_where");

// outer je tam kvuli target='all'
	$result = db_fetch_assoc("SELECT $sql_table.*, host.description AS host_description
		FROM $sql_table
		LEFT OUTER JOIN host
		ON host.id = $sql_table.host_id
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = [
		'description' => [
			'display' => __('Description', 'tags'),
			'sort'    => 'ASC'
		],
		'tag_time' => [
			'display' => __('Tag time', 'tags'),
			'sort'    => 'ASC'
		],
		'target' => [
			'display' => __('Target', 'tags'),
			'sort'    => 'ASC'
		],
		'host_id' => [
			'display' => __('Device', 'tags'),
			'sort'    => 'ASC'
		],
		'graph_id' => [
			'display' => __('Graph', 'tags'),
			'sort'    => 'ASC'
		],
		'site_id' => [
			'display' => __('Site', 'tags'),
			'sort'    => 'ASC'
		],
		'automatic' => [
			'display' => __('Automatic', 'tags'),
			'sort'    => 'ASC'
		],
	];

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?filter=' . get_request_var('filter') . '&archive=' . get_filter_request_var('archive'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Tags', 'tags'), 'page', 'main');

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])), 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {

			if (array_key_exists($row['color'], $tags_colors)) {
				$color = $tags_colors[$row['color']];
			} else {
				$color = '999999';
			}

			$row['host_id']  = $row['host_id']  > 0 ? $row['host_id']  : '-';
			$row['graph_id'] = $row['graph_id'] > 0 ? $row['graph_id'] : '-';
			$row['site_id']  = $row['site_id']  > 0 ? $row['site_id']  : '-';

			$row['type']  = tags_is_automatic_type($row['type']) ? __('Yes', 'tags') : __('No', 'tags');;

			form_alternate_row('line' . $row['id'], false, $row['enabled']);
			form_selectable_cell("<span class='color-box' style='--tagcolor: " . htmlspecialchars($color) . "'></span><a class='linkEditMain' href='" . html_escape(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false&action=edit&id=' . $row['id']) . "'>" . $row['description'] . '</a>', $row['id']);
			form_selectable_cell(date('Y-m-d H:i', $row['tag_time']), $row['id']);
			form_selectable_cell($tags_target[$row['target']], $row['id']);
			form_selectable_cell($row['host_description'], $row['id']);
			form_selectable_cell($row['graph_id'], $row['id']);
			form_selectable_cell($row['site_id'], $row['id']);
			form_selectable_cell($row['type'], $row['id']);
			form_checkbox_cell($row['description'], $row['id']);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . $columns . "'><em>" . __('Empty', 'tags') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	if (get_filter_request_var('archive')) {
		$a = array_slice($tags_actions_menu, 0, 1, true);
		draw_actions_dropdown($a, 1);
	} else {
		draw_actions_dropdown($tags_actions_menu, 1);
	}



// TADY

	print "<input type='hidden' name='is_archive' value='" . (get_nfilter_request_var('archive') ? 1 : 0) . "'>";

	form_end();
}

/** Render tag list filters and time-span controls.
 *
 * @return void
 */
function tags_filter() {
	global $item_rows, $tags_target, $tags_type, $graph_timespans;

	if (get_filter_request_var('host_id')) {
		$host_id = get_filter_request_var('host_id');
	} else if (isset($_SESSION['plugin_uptime_host_id'])) {
		$host_id = $_SESSION['plugin_uptime_host_id'];
	} else {
		$host_id = -1;
	}

	html_start_box(__('Tags Management', 'tags') , '100%', '', '3', 'center', htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?action=edit');

	?>
	<tr class='even'>
		<td>
		<form id='form_tags_item' action='<?php print htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>'>
			<table class='filterTable'>
				<tr>
					<?php
						print html_host_filter($host_id, 'applyFilter', '', false, true);
					?>
					<td>
						<?php print __('Search', 'tags'); ?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='20' value='<?php print html_escape_request_var('filter'); ?>'>
					</td>
					<td>
					<?php print __('Target', 'tags'); ?>
					</td>
					<td>
						<select id='target' onChange='applyFilter()'>
						<?php
						print "<option value='-1'" . (get_request_var('rows') == -1 ? ' selected' : '') . '>' . __('Any', 'tags') . '</option>';

						if (cacti_sizeof($tags_target)) {
							foreach ($tags_target as $key => $value) {
								print "<option value='" . $key . "'";

								if (get_request_var('target') == $key) {
									print ' selected';
								} print '>' . htmlspecialchars($value) . '</option>';
							}
						}
						?>
					</select>
					</td>
					<td>
					<?php print __('Type', 'tags'); ?>
					</td>
					<td>
						<select id='type' onChange='applyFilter()'>
						<?php
						print "<option value='-1'" . (get_request_var('rows') == -1 ? ' selected' : '') . '>' . __('Any', 'tags') . '</option>';

						if (cacti_sizeof($tags_type)) {
							foreach ($tags_type as $key => $value) {
								print "<option value='" . $key . "'";

								if (get_request_var('type') == $key) {
									print ' selected';
								} print '>' . htmlspecialchars($value) . '</option>';
							}
						}
						?>
					</select>
					</td>
					<td>
					<?php print __('Tags', 'tags'); ?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
						<?php
						print "<option value='-1'" . (get_request_var('rows') == -1 ? ' selected' : '') . '>' . __('Default', 'tags') . '</option>';

						if (cacti_sizeof($item_rows)) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'";

								if (get_request_var('rows') == $key) {
									print ' selected';
								} print '>' . htmlspecialchars($value) . '</option>';
							}
						}
						?>
					</select>
					</td>
					<td>
					<input id="archive" type="checkbox" name="archive" value="1" class="ui-state-default ui-corner-all"
					<?php echo (get_filter_request_var('archive') ? 'checked="checked"' : '');?>> Archive
					</td>
					<td>
						<span class='nowrap'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go'); ?>' title='<?php print __esc('Set/Refresh Filters', 'tags'); ?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear'); ?>' title='<?php print __esc('Clear Filters', 'tags'); ?>'>
						</span>
					</td>

				</tr>
			</table>
		</td>
	</tr>
	<tr class='even noprint'>
		<td class='noprint'>
			<table class='filterTable'>
				<tr id='timespan'>
					<td>
						<?php print __('Presets');?>
					</td>
					<td>
						<select id='predefined_timespan'>
							<?php
							$display_timespans = [
								-1        => __('Any'),
								GT_CUSTOM => __('Custom'),
							] + $graph_timespans;
							$start_val = 0;
							$end_val   = cacti_sizeof($display_timespans);

							if (cacti_sizeof($display_timespans)) {
								foreach($display_timespans as $value => $text) {
									print "<option value='$value'"; if (get_filter_request_var('tag_timespan') == $value) { print ' selected'; } print '>' . html_escape($text) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('From');?>
					</td>
					<td>
						<span>

							<input type='text' class='ui-state-default ui-corner-all' id='date1' size='18' value='<?php print (isset($_SESSION['sess_current_date1']) ? $_SESSION['sess_current_date1'] : '');?>'>
							<i id='startDate' class='calendar fa fa-calendar' title='<?php print __esc('Start Date Selector');?>'></i>
						</span>
					</td>
					<td>
						<?php print __('To');?>
					</td>
					<td>
						<span>
							<input type='text' class='ui-state-default ui-corner-all' id='date2' size='18' value='<?php print (isset($_SESSION['sess_current_date2']) ? $_SESSION['sess_current_date2'] : '');?>'>
							<i id='endDate' class='calendar fa fa-calendar' title='<?php print __esc('End Date Selector');?>'></i>
						</span>
					</td>
				</tr>
			</table>
		</td>
	</tr>

<?php
	html_end_box();
?>
	</form>
	<script type='text/javascript'>

//get current graph xxxx muzu nechat
		var graph_start     = <?php print get_current_graph_start();?>;
		var graph_end       = <?php print get_current_graph_end();?>;
//		var pageAction      = <?php //print json_encode($action);?>;
		var graphPage       = 'tags.php';
		var date1Open       = false;
		var date2Open       = false;

		function setCustomTimespan() {
			var preset = $('#predefined_timespan');

			preset.val(<?php print GT_CUSTOM; ?>);

			if (preset.data('ui-selectmenu')) {
				preset.selectmenu('refresh');
			}
		}


		function applyFilter() {

			if ($('#archive').is(':checked')) {
				var archive = 1;
			} else {
				var archive = 0;
			}

			strURL  = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>?header=false';
			strURL += '&host_id=' + $('#host_id').val();
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();
			strURL += '&target=' + $('#target').val();
			strURL += '&archive=' + archive;
			strURL += '&type=' + $('#type').val();

			strURL += '&tag_timespan=' + $('#predefined_timespan').val();

			if ($('#predefined_timespan').val() >= 0) {
				strURL += '&predefined_timespan=' + $('#predefined_timespan').val();

				if ($('#predefined_timespan').val() == <?php print GT_CUSTOM; ?>) {
					strURL += '&date1=' + encodeURIComponent($('#date1').val());
					strURL += '&date2=' + encodeURIComponent($('#date2').val());
				}
			}
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {


			$('#rows').click(function() {
				applyFilter();
			});

			$('#target').click(function() {
				applyFilter();
			});

			$('#type').click(function() {
				applyFilter();
			});

			$('#archive').click(function() {
				applyFilter();
			});

			$('#refresh').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_tags_item').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
/// z time

			$('#startDate').on('click', function() {
				if (date1Open) {
					date1Open = false;
					$('#date1').datetimepicker('hide');
				} else {
					date1Open = true;
					$('#date1').datetimepicker('show');
				}
			});

			$('#endDate').on('click', function() {
				if (date2Open) {
					date2Open = false;
					$('#date2').datetimepicker('hide');
				} else {
					date2Open = true;
					$('#date2').datetimepicker('show');
				}
			});

			$('#date1').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd',
				showButtonPanel: false
			});

			$('#date2').datetimepicker({
				minuteGrid: 10,
				stepMinute: 1,
				showAnim: 'slideDown',
				numberOfMonths: 1,
				timeFormat: 'HH:mm',
				dateFormat: 'yy-mm-dd',
				showButtonPanel: false
			});

			$('#date1, #date2').on('change', function() {
				setCustomTimespan();
			});


			$('#predefined_timespan').on('change', function() {
				applyFilter();
			});

		});

		</script>
<?php
}



/** Return sites matching an AJAX search request.
 *
 * @param string $sql_where Optional SQL WHERE clause.
 *
 * @return array|null Empty array for an unauthorized user; otherwise null.
 */
function tags_get_ajax_sites($sql_where = '') {
	$user_id = $_SESSION['sess_user_id'];

	if (!auth_valid_user($user_id)) {
		return array();
	}

	$return = array();

	$term = get_filter_request_var('term', FILTER_CALLBACK, array('options' => 'sanitize_search_string'));

	if ($term != '') {
		$sql_where .= ($sql_where != '' ? ' AND ' : '') .
		'(name LIKE ' . db_qstr("%$term%") .
		' OR city LIKE ' . db_qstr("%$term%") .
		' OR country LIKE ' . db_qstr("%$term%") . ')';
	}

	$total_rows = -1;

	$sites = db_fetch_assoc('SELECT id, name FROM sites ' . $sql_where);
	if (cacti_sizeof($sites)) {
		foreach($sites as $site) {
			$return[] = array('label' => html_escape($site['name']),
			'value' => html_escape($site['name']), 'id' => $site['id']);
		}
	}
	print json_encode($return);
}

