<?php

/*
 * This file is part of Linfo (c) 2010-2015 Joseph Gillotti.
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

// Are we running from the CLI?
if (isset($argc) && is_array($argv))
	define('LINFO_CLI', true);

// Version
define('AppName', 'Linfo');
define('VERSION', 'git');

// Anti hack, as in allow included files to ensure they were included
define('IN_INFO', true);

// Configure absolute path to local directory
define('LOCAL_PATH', dirname(__FILE__) . '/');

// Configure absolute path to stored info cache, for things that take a while
// to find and don't change, like hardware devcies
define('CACHE_PATH', dirname(__FILE__) . '/cache/');

// Configure absolute path to web directory
define('WEB_PATH', !defined('LINFO_CLI') ? substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/')+1) : './');

// If configuration file does not exist but the sample does, say so
if (!is_file(LOCAL_PATH . 'config.inc.php') && is_file(LOCAL_PATH . 'sample.config.inc.php'))
	exit('Make changes to sample.config.inc.php then rename as config.inc.php');

// If the config file is just gone, also say so
elseif(!is_file(LOCAL_PATH . 'config.inc.php'))
	exit('Config file not found.');

// It exists; just include it
require_once LOCAL_PATH . 'config.inc.php';

// This is essentially the only extension we need, so make sure we have it
if (!extension_loaded('pcre') && !function_exists('preg_match') && !function_exists('preg_match_all')) {
	echo AppName.' needs the `pcre\' extension to be loaded. http://us2.php.net/manual/en/book.pcre.php';
	exit(1);
}

// Make sure these are arrays
$settings['hide']['filesystems'] = is_array($settings['hide']['filesystems']) ? $settings['hide']['filesystems'] : array();
$settings['hide']['storage_devices'] = is_array($settings['hide']['storage_devices']) ? $settings['hide']['storage_devices'] : array();

// Make sure these are always hidden
$settings['hide']['filesystems'][] = 'rootfs';
$settings['hide']['filesystems'][] = 'binfmt_misc';

// Load libs
require_once LOCAL_PATH . 'lib/functions.init.php';
require_once LOCAL_PATH . 'lib/functions.misc.php';
require_once LOCAL_PATH . 'lib/functions.display.php';
require_once LOCAL_PATH . 'lib/class.LinfoTimer.php';
require_once LOCAL_PATH . 'lib/interface.LinfoExtension.php';

// Default timeformat
$settings['dates'] = array_key_exists('dates', $settings) ? $settings['dates'] : 'm/d/y h:i A (T)';

// Default to english translation if garbage is passed
if (empty($settings['language']) || !preg_match('/^[a-z]{2}$/', $settings['language']))
	$settings['language'] = 'en';

// If it can't be found default to english
if (!is_file(LOCAL_PATH . 'lang/'.$settings['language'].'.php'))
	$settings['language'] = 'en';
	
// Load translation, defaulting to english of keys are missing (assuming
// we're not using english anyway and the english translation indeed exists)
if (is_file(LOCAL_PATH . 'lang/en.php') && $settings['language'] != 'en') 
	$lang = array_merge(get_var_from_file(LOCAL_PATH . 'lang/en.php', 'lang'), 
		get_var_from_file(LOCAL_PATH . 'lang/'.$settings['language'].'.php', 'lang'));

// Otherwise snag desired translation, be it english or a non-english without english to fall back on	
else	
	require_once LOCAL_PATH . 'lang/'.$settings['language'].'.php';

// Bullshit happens if date.timezone isn't set in php 5.3+
if (!ini_get('date.timezone')) 
	@ini_set('date.timezone', 'Etc/UTC');

// Don't just blindly assume we have the ob_* functions...
if (!function_exists('ob_start'))
	$settings['compress_content'] = false;

// Determine our OS
$os = determineOS();

// Cannot?
if ($os == false)
	exit("Unknown/unsupported operating system\n");

// Get info
$getter = parseSystem($os, $settings);
$info = $getter->getAll();

// Store current timestamp for alternative output formats
$info['timestamp'] = date('c');

// Extensions
runExtensions($info, $settings);

// Make sure we have an array of what not to show
$info['contains'] = array_key_exists('contains', $info) ? (array) $info['contains'] : array();

// From the command prompt, we have ncurses, and we aren't being given --nocurses?
if (defined('LINFO_CLI') && extension_loaded('ncurses') && !in_array('--nocurses', $argv)) {
	$out = new out_ncurses();
	$out->work($info, $settings, $getter);
}

// Coming from a web server or we don't want curses
else {
	// Decide what web format to output in
	switch (array_key_exists('out', $_GET) ? $_GET['out'] : 'html') {

		// Just regular html 
		case 'html':
		default:
			showInfoHTML($info, $settings);
		break;

		// JSON
		case 'json':
		case 'jsonp':	// To use JSON-P, pass the GET arg - callback=function_name
			showInfoJSON($info, $settings);
		break;

		// Serialized php array
		case 'php_array':
			echo serialize($info);
		break;

		// XML
		case 'xml':

			// Try using SimpleXML
			if (extension_loaded('SimpleXML')) 
				showInfoSimpleXML($info, $settings);
			

			// If not that, then try XMLWriter
			elseif (extension_loaded('XMLWriter')) 
				showInfoXMLWriter($info, $settings);	

			// Can't generate XML anywhere :-/
			else 
				exit('Cannot generate XML. Install either php\'s SimpleXML or XMLWriter extension');
		break;
	}
}

// "This is where it ends, Commander"
