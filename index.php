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

// Timer
define('TIME_START', microtime(true));

// Version
define('AppName', 'Linfo');
define('VERSION', sprintf('%s %s', AppName, '(svn)'));

// Anti hack, as in allow included files to ensure they were included
define('IN_INFO', true);

// Configure absolute path to local directory
define('LOCAL_PATH', dirname(__FILE__) . '/');

// Configure absolute path to stored info cache, for things that take a while
// to find and don't change, like hardware devcies
define('CACHE_PATH', dirname(__FILE__) . '/cache/');

// Configure absolute path to web directory
$web_path = dirname($_SERVER['SCRIPT_NAME']);
define('WEB_PATH', substr($web_path, -1) == '/' ? $web_path : $web_path.'/');

// If configuration file does not exist but the sample does, say so
if (!is_file(LOCAL_PATH . 'config.inc.php') && is_file(LOCAL_PATH . 'sample.config.inc.php'))
	exit('Make changes to sample.config.inc.php then rename as config.inc.php');

// If the config file is just gone, also say so
elseif(!is_file(LOCAL_PATH . 'config.inc.php'))
	exit('Config file not found.');

// It exists; just include it
require_once LOCAL_PATH . 'config.inc.php';

// Make sure these are arrays
$settings['hide']['filesystems'] = is_array($settings['hide']['filesystems']) ? $settings['hide']['filesystems'] : array();
$settings['hide']['storage_devices'] = is_array($settings['hide']['storage_devices']) ? $settings['hide']['storage_devices'] : array();

// Make sure these are always hidden
$settings['hide']['filesystems'][] = 'rootfs';
$settings['hide']['filesystems'][] = 'binfmt_misc';

// Load libs
require_once LOCAL_PATH . 'lib/init.php';
require_once LOCAL_PATH . 'lib/misc.php';
require_once LOCAL_PATH . 'lib/display.php';
require_once LOCAL_PATH . 'lib/class.LinfoTimer.php';
require_once LOCAL_PATH . 'lib/interface.LinfoExtension.php';

// Default to english translation if garbage is passed
if (empty($settings['language']) || !preg_match('/^[a-z]{2}$/', $settings['language']))
	$settings['language'] = 'en';

// If it can't be found default to english
if (!is_file(LOCAL_PATH . 'lang/'.$settings['language'].'.php'))
	$settings['language'] = 'en';
	
// Load translation
require_once LOCAL_PATH . 'lang/'.$settings['language'].'.php';

// Determine our OS
$os = determineOS();

// Cannot?
if ($os == false)
	exit('Unknown/unsupported operating system');

// Get info
$getter = parseSystem($os, $settings);
$info = $getter->getAll();

// Extensions
runExtensions($info, $settings);

// Show
if (array_key_exists('json', $_GET))
	// Allow json'ing the info, which might be helpful for
	// using linfo to be an ajax source for some other app
	echo json_encode($info);
else
	// Otherwise, extremely minimal html 
	showInfo($info, $settings);

// "This is where it ends, Commander"
