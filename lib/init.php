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

// Set up class auto loading
function __autoload($class) {
	
	// Path to where it should be
	$file = LOCAL_PATH . 'lib/class.'.$class.'.php';

	// Load it if it does
	if (is_file($file)) 
		require_once $file;
	else
		exit('File for '.$class.' not found');
	
	// Ensure we have it
	if (class_exists($class))
		return;
	else
		exit('Class '.$class.' not found');
}


// Exception for info classes 
class GetInfoException extends Exception{}

// Determine OS.
// Linux support mostly done; FreeBSD under dev next
function determineOS($os = null) {

	// List of known/supported Os's
	$known = array('linux', 'freebsd', 'darwin', 'windows');

	// Maybe we hardcoded OS type in
	if ($os != null && in_array(strtolower($os), $known)) {
		return $os;
	}

	// Or not:

	// Get uname
	$uname = strtolower(trim(@`/bin/uname`));

	// Do we have it?
	if (in_array($uname, $known)) {
		return $uname;
	}

	// Otherwise no. Winfux support might be coming later'ish
	else {
		return false;
	}

}

// Start up class based on result of above
function parseSystem($type, $settings) {
	$type = ucfirst($type) . 'Info';
	if (!class_exists($type))
		exit('Info class for this does not exist');

	try {
		$info =  new $type($settings);
	}
	catch (GetInfoException $e) {
		exit($e->getMessage());
	}

	return $info;
}
