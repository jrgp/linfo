<?php

/**
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
 */

/**
 * Keep out hackers...
 */
defined('IN_INFO') or exit;

/**
 * Set up class auto loading
 * @param string $class the name of the class being searched fro
 */
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


/**
 * Exception for info classes
 */
class GetInfoException extends Exception{}

/**
 * Determine the OS
 * @return string|false if the OS is found, returns the name; Otherwise false
 */
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
		case 'SunOS':
			return PHP_OS;
		break;

		// So anything else isn't
		default:
			return false;	
		break;
	}
}

/**
 * Start up class based on result of determineOS
 * @param string $type the name of the operating system
 * @param array $settings linfo settings
 * @return array the system information
 */
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

/**
 * Deal with extra extensions
 * @param array $info the system information
 * @param array $settings linfo settings
 */
function runExtensions(&$info, $settings) {

	// Info array is passed by reference so we can edit it directly
	$info['extensions'] = array();

	// Go through each enabled extension
	foreach((array)$settings['extensions'] as $ext => $enabled) {

		// Is it really enabled?
		if (empty($enabled)) 
			continue;

		// Does the file exist? load it then
		if (file_exists(LOCAL_PATH . 'lib/class.ext.'.$ext.'.php'))
			require_once LOCAL_PATH . 'lib/class.ext.'.$ext.'.php';
		else {
			
			// Issue an error and skip this thing otheriwse
			LinfoError::Fledging()->add('Extension Loader', 'Cannot find file for "'.$ext.'" extension.');
			continue;
		}

		// Name of its class
		$class = 'ext_'.$ext;

		// Load it
		$ext_class = new $class();

		// Deal with it
		$ext_class->work();

		// Result
		$result = $ext_class->result();

		// Save result if it's good
		if ($result != false)
			$info['extensions'][$ext] = $result;
	}
}
