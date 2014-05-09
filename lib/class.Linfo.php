<?php

/**
 * This file is part of Linfo (c) 2014 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.	If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Keep out hackers...
 */
defined('IN_LINFO') or exit;

/**
 * Linfo
 *
 * Serve as the script's "controller". Leverages other classes. Loads settings,
 * outputs them in formats, runs extensions, etc.
 *
 * @throws LinfoFatalException
 */
class Linfo {

	protected
		$settings = array(),
		$lang = array(),
		$info = array(),
		$parser = null,

		$app_name = 'Linfo',
		$version = '',
		$time_start = 0;

	public function __construct() {

		// Time us
		$this->time_start = microtime(true);

		// Get our version from git setattribs
		$scm = '$Format:%ci$';
		$this->version = strpos($scm, '$') !== false ? 'git' : $scm;

		// Run through dependencies / sanity checking
		if (!extension_loaded('pcre') && !function_exists('preg_match') && !function_exists('preg_match_all'))
			throw new LinfoFatalException($this->app_name.' needs the `pcre\' extension to be loaded. http://us2.php.net/manual/en/book.pcre.php');

		// Warnings usually displayed to browser happen if date.timezone isn't set in php 5.3+
		if (!ini_get('date.timezone')) 
			@ini_set('date.timezone', 'Etc/UTC');

		// Load our settings/language
		$this->loadSettings();
		$this->loadLanguage();
		
		// Some classes need our vars; config them
		foreach (array('LinfoCommon', 'CallExt') as $class)
			$class::config($this);

		// Determine OS
		$os = $this->getOS();

		if (!$os)
			throw new LinfoFatalException('Unknown/unsupported operating system');

		$distro_class = 'OS_'.$os;
		$this->parser = new $distro_class($this->settings);
	}

	public function scan() {
		// Run OS prober
		$this->info = $this->parser->getAll();
		$this->info['contains'] = array_key_exists('contains', $this->info) ? (array) $this->info['contains'] : array();
		$this->info['timestamp'] = date('c');

		// Run extra extensions
		$this->runExtensions();
	}

	protected function loadSettings() {

		// Running unit tests?
		if (defined('LINFO_TESTING')) {
			$this->settings = LinfoCommon::getVarFromFile(LINFO_TESTDIR . '/test_settings.php', 'settings');
			if (!is_array($this->settings))
				throw new LinfoFatalException('Failed getting test-specific settings');
			return;
		}

		// If configuration file does not exist but the sample does, say so
		if (!is_file(LINFO_LOCAL_PATH . 'config.inc.php') && is_file(LINFO_LOCAL_PATH . 'sample.config.inc.php'))
			throw new LinfoFatalException('Make changes to sample.config.inc.php then rename as config.inc.php');

		// If the config file is just gone, also say so
		elseif(!is_file(LINFO_LOCAL_PATH . 'config.inc.php'))
			throw new LinfoFatalException('Config file not found.');

		// It exists; load it
		$settings = LinfoCommon::getVarFromFile(LINFO_LOCAL_PATH . 'config.inc.php', 'settings');

		// Don't just blindly assume we have the ob_* functions...
		if (!function_exists('ob_start'))
			$settings['compress_content'] = false;

		// Make sure these are arrays
		$settings['hide']['filesystems'] = is_array($settings['hide']['filesystems']) ? $settings['hide']['filesystems'] : array();
		$settings['hide']['storage_devices'] = is_array($settings['hide']['storage_devices']) ? $settings['hide']['storage_devices'] : array();

		// Make sure these are always hidden
		$settings['hide']['filesystems'][] = 'rootfs';
		$settings['hide']['filesystems'][] = 'binfmt_misc';

		// Default timeformat
		$settings['dates'] = array_key_exists('dates', $settings) ? $settings['dates'] : 'm/d/y h:i A (T)';

		// Default to english translation if garbage is passed
		if (empty($settings['language']) || !preg_match('/^[a-z]{2}$/', $settings['language']))
			$settings['language'] = 'en';

		// If it can't be found default to english
		if (!is_file(LINFO_LOCAL_PATH . 'lang/'.$settings['language'].'.php'))
			$settings['language'] = 'en';

		$this->settings = $settings;
	}

	protected function loadLanguage() {

		// Running unit tests?
		if (defined('LINFO_TESTING')) {
			$this->lang = LinfoCommon::getVarFromFile(LINFO_TESTDIR . '/test_lang.php', 'lang');
			if (!is_array($this->lang))
				throw new LinfoFatalException('Failed getting test-specific language');
			return;
		}

		// Load translation, defaulting to english of keys are missing (assuming
		// we're not using english anyway and the english translation indeed exists)
		if (is_file(LINFO_LOCAL_PATH . 'lang/en.php') && $this->settings['language'] != 'en') 
			$this->lang = array_merge(LinfoCommon::getVarFromFile(LINFO_LOCAL_PATH . 'lang/en.php', 'lang'), 
				LinfoCommon::getVarFromFile(LINFO_LOCAL_PATH . 'lang/'.$this->settings['language'].'.php', 'lang'));

		// Otherwise snag desired translation, be it english or a non-english without english to fall back on
		else
			$this->lang = LinfoCommon::getVarFromFile(LINFO_LOCAL_PATH . 'lang/'.$this->settings['language'].'.php', 'lang');
	}

	protected function getOS() {
		list($os) = explode('_', PHP_OS, 2);

		// This magical constant knows all
		switch ($os) {

			// These are supported
			case 'Linux':
			case 'FreeBSD':
			case 'DragonFly':
			case 'OpenBSD':
			case 'NetBSD':
			case 'Minix':
			case 'Darwin':
			case 'SunOS':
				return PHP_OS;
			break;
			case 'WINNT':
				define('IS_WINDOWS', true);
				return 'Windows';
			break;
			case 'CYGWIN':
				define('IS_CYGWIN', true);
				return 'CYGWIN';
			break;
		}

		// So anything else isn't
		return false;
	}

	/*
	 * getInfo()
	 *
	 * Returning reference so extensions can modify result
	 */
	public function &getInfo() {
		return $this->info;
	}

	/*
	 * getInfo()
	 *
	 * Output data in a variety of methods depending on situation
	 */
	public function output() {

		// A global, yuck!
		global $argv;

		$output = new LinfoOutput($this);

		if (defined('LINFO_CLI') && extension_loaded('ncurses') && !in_array('--nocurses', $argv)) {
			$output->ncursesOut();
			return;
		}

		switch (array_key_exists('out', $_GET) ? $_GET['out'] : 'html') {
			case 'html':
			default:
				$output->htmlOut();
			break;

			case 'json':
			case 'jsonp': // To use JSON-P, pass the GET arg - callback=function_name
				$output->jsonOut();
			break;

			case 'php_array':
				$output->serializedOut();
			break;

			case 'xml':
				if (!extension_loaded('SimpleXML'))
					throw new LinfoFatalException('Cannot generate XML. Install php\'s SimpleXML extension.');
				$output->xmlOut();
			break;
		}
	}

	protected function runExtensions() {
		$this->info['extensions'] = array();

		if(!array_key_exists('extensions', $this->settings) || count($this->settings['extensions']) == 0) 
			return;

		// Go through each enabled extension
		foreach((array)$this->settings['extensions'] as $ext => $enabled) {

			// Is it really enabled?
			if (empty($enabled)) 
				continue;

			// Anti hack
			if (!preg_match('/^[a-z0-9-_]+$/i', $ext)) {
				LinfoError::Singleton()->add('Extension Loader', 'Not going to load "'.$ext.'" extension as only characters allowed in name are letters/numbers/-_');
				continue;
			}

			// Does the file exist? load it then
			if (file_exists(LINFO_LOCAL_PATH . 'lib/class.ext.'.$ext.'.php'))
				require_once LINFO_LOCAL_PATH . 'lib/class.ext.'.$ext.'.php';
			else {
				
				// Issue an error and skip this thing otheriwse
				LinfoError::Singleton()->add('Extension Loader', 'Cannot find file for "'.$ext.'" extension.');
				continue;
			}

			// Name of its class
			$class = 'ext_'.$ext;

			// Make sure it exists
			if (!class_exists($class)) {
				LinfoError::Singleton()->add('Extension Loader', 'Cannot find class for "'.$ext.'" extension.');
				continue;
			}

			// Handle version checking
			$min_version = defined($class.'::LINFO_MIN_VERSION') ? constant($class.'::LINFO_MIN_VERSION') : false; 
			if ($min_version !== false && strtolower($this->version) != 'git' && !version_compare($this->version, $min_version, '>=')) {
				LinfoError::Singleton()->add('Extension Loader', '"'.$ext.'" extension requires at least Linfo v'.$min_version);
				continue;
			}

			// Load it
			$ext_class = new $class($this);

			// Deal with it
			$ext_class->work();
			
			// Does this edit the $info directly, instead of creating a separate output table type thing?
			if (!defined($class.'::LINFO_INTEGRATE')) {

				// Result
				$result = $ext_class->result();

				// Save result if it's good
				if ($result != false)
					$this->info['extensions'][$ext] = $result;
			}
		}
	}

	public function getLang() {
		return $this->lang;
	}

	public function getSettings() {
		return $this->settings;
	}

	public function getAppName() {
		return $this->app_name;
	}

	public function getVersion() {
		return $this->version;
	}

	public function getTimeStart() {
		return $this->time_start;
	}

	public function getParser() {
		return $this->parser;
	}
}

/*
	Goal is someone can just include init.php and do:
	$linfo = new $linfo;

	var_dump($linfo->getInfo()); // all of our stats

	-OR- 

	$linfo->output(); // html or whatever
*/
