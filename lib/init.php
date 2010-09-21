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
		exit('File for '.$file.' not found');
	
	// Make sure we have it
	if (!class_exists($class))
		exit('Class '.$class.' not found in '.$file);
}


// Exception for info classes 
class GetInfoException extends Exception{}

// Determine OS. If true, returns OS name; otherwise false
function determineOS() {
	
	// This magical constant knows all
	switch (PHP_OS) {

		// These are supported
		case 'Linux':
		case 'FreeBSD':
		case 'OpenBSD':
		case 'NetBSD':
		case 'Minix':
		case 'Darwin':
			return PHP_OS;
		break;

		// So anything else isn't
		default:
			return false;	
		break;
	}
}

// Start up class based on result of above
function parseSystem($type, $settings) {
	$class = 'OS_'.$type;
	try {
		$info =  new $class($settings);
	}
	catch (GetInfoException $e) {
		exit($e->getMessage());
	}

	return $info;
}
