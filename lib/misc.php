<?php

/*
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.  If not, see <http://www.gnu.org/licenses/>.
 * 
*/


defined('IN_INFO') or exit;

// Certain files, specifcally the pci/usb ids files, vary in location from
// linux distro to linux distro. This function, when passed an array of
// possible file location, picks the first it finds and returns it. When
// none are found, it returns false
function locate_actual_path($paths) {
	
	// Make absolutely sure that's an array
	$paths = (array) $paths;

	$num_paths = count($paths);
	for ($i = 0; $i < $num_paths; $i++)
		if (is_file($paths[$i]))
			return $paths[$i];

	return false;
}

// Get a file who's contents should just be an int. Returns zero on failure.
function get_int_from_file($file) {
	if (!file_exists($file))
		return 0;

	if (!($contents = @file_get_contents($file)))
		return 0;

	$int = trim($contents);

	return (int) $int;
}

// Convert bytes to stuff like KB MB GB TB etc
function byte_convert($size, $precision = 2) {
	
	// Grab these
	global $settings;

	// Sanity check
	if (!is_numeric($size))
		return '?';
	
	// Get the notation
	$notation = $settings['byte_notation'] == 1000 ? 1000: 1024;

	// Fixes large disk size overflow issue
	// Found at http://www.php.net/manual/en/function.disk-free-space.php#81207
	$types = array( 'B', 'KB', 'MB', 'GB', 'TB' );
	for( $i = 0; $size >= $notation && $i < ( count( $types ) -1 ); $size /= $notation, $i++ );
	return( round( $size, $precision ) . ' ' . $types[$i] );
}

// Like above, but for seconds
function seconds_convert($uptime) {
	
	// Method here heavily based on freebsd's uptime source
	$uptime += $uptime > 60 ? 30 : 0;
	$days = floor($uptime / 86400);
	$uptime %= 86400;
	$hours = floor($uptime / 3600);
	$uptime %= 3600;
	$minutes = floor($uptime / 60);
	$seconds = floor($uptime % 60);

	// Send out formatted string
	$return = array();

	if ($days > 0)
		$return[] = $days.' days';
	
	if ($hours > 0)
		$return[] = $hours.' hrs';

	if ($minutes > 0)
		$return[] = $minutes.' mins';

	if ($seconds > 0)
		$return[] = $seconds. (date('m/d') == '06/03' ? ' sex' : ' secs');

	return implode(', ', $return);
}

// Get a file's contents, or default to second param
function getContents($file, $default = '') {
	if (!is_file($file) || !($contents = @file_get_contents($file)))
		return $default;
	else
		return trim($contents);
}

// Like above, but in lines instead of a big string
function getLines($file) {
	if (!is_file($file) || !($lines = @file($file, FILE_SKIP_EMPTY_LINES)))
		return array();
	else
		return $lines;
}

// Create a table out of an array. Mostly used by extensions
/*
	$structure = array(
		'root_title' => 'Name',
		'rows' => array(
			01 = array(
				'type' => 'header',
				'columns' => array(
					'Column 1',
					'Column 2',
					// OR array(colspannumber, 'value')
				)
			)
			02 => array(
				'type' => 'values',
				'columns' => array(
					'Value 1',
					'Value 2',
					// OR array(colspannumber, 'value')
				)
			)
		)
	);
*/
function create_table($structure) {
	$html = '
		<div class="infoTable">
		<h2>'.$structure['root_title'].'</h2>
		<table>
	';

	foreach ($structure['rows'] as $row) {
		$html .= '<tr>';
		if ($row['type'] == 'header') {
			foreach ($row['columns'] as $v)
				$html .= is_array($v) ? '<th colspan="'.$v[0].'">'.$v[1].'</th>' : '<th>'.$v.'</th>';
		}
		elseif ($row['type'] == 'values') {
			foreach ($row['columns'] as $v)
				$html .= is_array($v) ? '<td colspan="'.$v[0].'">'.$v[1].'</td>' : '<td>'.$v.'</td>';

		}
		$html .= '</tr>';
	}

	$html .= '</table>';

	return $html;
}
