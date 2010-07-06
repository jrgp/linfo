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

// Anti hack
define('IN_INFO', true);

// Configure paths
define('LOCAL_PATH', dirname(__FILE__) . '/');

$web_path = dirname($_SERVER['SCRIPT_NAME']);
$web_path .= substr($web_path, -1) == '/' ? '' : '/';
define('WEB_PATH', $web_path);

// Load conf file
if (!is_file(LOCAL_PATH . 'config.inc.php') && is_file(LOCAL_PATH . 'sample.config.inc.php'))
	exit('Make changes to sample.config.inc.php then rename as config.inc.php');
elseif(!is_file(LOCAL_PATH . 'config.inc.php'))
	exit('Config file not found.');
require_once LOCAL_PATH . 'config.inc.php';

// Fix some things
$settings['hide']['filesystems'] = is_array($settings['hide']['filesystems']) ? $settings['hide']['filesystems'] : array();
$settings['hide']['storage_devices'] = is_array($settings['hide']['storage_devices']) ? $settings['hide']['storage_devices'] : array();
$settings['hide']['filesystems'][] = 'rootfs';
$settings['hide']['filesystems'][] = 'binfmt_misc';

// Load libs
require_once LOCAL_PATH . 'lib/init.php';
require_once LOCAL_PATH . 'lib/misc.php';
require_once LOCAL_PATH . 'lib/display.php';

// Determine our OS
$os = determineOS();

// Cannot?
if ($os == false)
	exit('Unknown/unsupported operating system');

// Get info
$getter = parseSystem($os, $settings);
$info = $getter->getAll();

// Show
if (array_key_exists('json', $_GET))
	echo json_encode($info);
else
	showInfo($info, $settings);
