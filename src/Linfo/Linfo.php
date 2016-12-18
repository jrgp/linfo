<?php

/**
 * This file is part of Linfo (c) 2014, 2015 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Linfo;

use Linfo\Parsers\CallExt;
use Linfo\Exceptions\FatalException;
use Linfo\Meta\Errors;
use ReflectionClass;
use ReflectionException;

/**
 * Linfo.
 *
 * Serve as the script's "controller". Leverages other classes. Loads settings,
 * outputs them in formats, runs extensions, etc.
 *
 * @throws FatalException
 */
class Linfo
{
    protected $settings = array(),
        $lang = array(),
        $info = array(),
        $parser = null,

        $app_name = 'Linfo',
        $version = '',
        $time_start = 0,
        $linfo_testdir = null,
        $linfo_localdir = null;

    public function __construct($settings = array())
    {

        // Time us
        $this->time_start = microtime(true);

        // Some paths..
        $this->linfo_testdir = dirname(dirname(__DIR__)).'/tests';
        $this->linfo_localdir = dirname(dirname(__DIR__)).'/';

        // Get our version from git setattribs
        $scm = '$Format:%ci$';
        list($this->version) = strpos($scm, '$') !== false ? array('git') : explode(' ', $scm);

        // Run through dependencies / sanity checking
        if (!extension_loaded('pcre') && !function_exists('preg_match') && !function_exists('preg_match_all')) {
            throw new FatalException($this->app_name.' needs the `pcre\' extension to be loaded. http://us2.php.net/manual/en/book.pcre.php');
        }

        // Warnings usually displayed to browser happen if date.timezone isn't set in php 5.3+
        if (!ini_get('date.timezone')) {
            @ini_set('date.timezone', 'Etc/UTC');
        }

        // Load our settings/language
        $this->loadSettings($settings);
        $this->loadLanguage();

        // Some classes need our vars; config them
        Common::config($this);
        CallExt::config($this);

        // Determine OS
        $os = $this->getOS();

        if (!$os) {
            throw new FatalException('Unknown/unsupported operating system');
        }

        $distro_class = '\\Linfo\\OS\\'.$os;
        $this->parser = new $distro_class($this->settings);
    }
    
    // Forward missing method request to the parser
    public function __call ( $name , $args )
    {
        if (method_exists($this->parser, $name) && is_callable (array($this->parser, $name))) {
			
           return call_user_func(array($this->parser,$name), $args);
			
        }
    }
            
    // Load everything, while obeying permissions...
    public function scan()
    {
        $reflector = new ReflectionClass($this->parser);

        // Prime parser. Do things not appropriate to do in constructor. Most OS classes
        // don't have this.
        if ($reflector->hasMethod('init') && ($method = $reflector->getMethod('init'))) {
            $method->invoke($this->parser);
        }

        // Array fields, tied to method names and default values...
        $fields = array(
            'OS' => array(
                'show' => !empty($this->settings['show']['os']),
                'default' => '',
                'method' => 'getOS',
            ),

            'Kernel' => array(
                'show' => !empty($this->settings['show']['kernel']),
                'default' => '',
                'method' => 'getKernel',
            ),

            'AccessedIP' => array(
                'show' => !isset($this->settings['show']['ip']) || !empty($this->settings['show']['ip']),
                'default' => '',
                'method' => 'getAccessedIP',
            ),

            'Distro' => array(
                'show' => !empty($this->settings['show']['distro']),
                'default' => '',
                'method' => 'getDistro',
            ),

            'RAM' => array(
                'show' => !empty($this->settings['show']['ram']),
                'default' => array(),
                'method' => 'getRam',
            ),

            'HD' => array(
                'show' => !empty($this->settings['show']['hd']),
                'default' => array(),
                'method' => 'getHD',
            ),

            'Mounts' => array(
                'show' => !empty($this->settings['show']['mounts']),
                'default' => array(),
                'method' => 'getMounts',
            ),

            'Load' => array(
                'show' => !empty($this->settings['show']['load']),
                'default' => array(),
                'method' => 'getLoad',
            ),

            'HostName' => array(
                'show' => !empty($this->settings['show']['hostname']),
                'default' => '',
                'method' => 'getHostName',
            ),

            'UpTime' => array(
                'show' => !empty($this->settings['show']['uptime']),
                'default' => array(),
                'method' => 'getUpTime',
            ),

            'CPU' => array(
                'show' => !empty($this->settings['show']['cpu']),
                'default' => array(),
                'method' => 'getCPU',
            ),

            'Model' => array(
                'show' => !empty($this->settings['show']['model']),
                'default' => array(),
                'method' => 'getModel',
            ),

            'CPUArchitecture' => array(
                'show' => !empty($this->settings['show']['cpu']),
                'default' => '',
                'method' => 'getCPUArchitecture',
            ),

            'Network Devices' => array(
                'show' => !empty($this->settings['show']['network']),
                'default' => array(),
                'method' => 'getNet',
            ),

            'Devices' => array(
                'show' => !empty($this->settings['show']['devices']),
                'default' => array(),
                'method' => 'getDevs',
            ),

            'Temps' => array(
                'show' => !empty($this->settings['show']['temps']),
                'default' => array(),
                'method' => 'getTemps',
            ),

            'Battery' => array(
                'show' => !empty($this->settings['show']['battery']),
                'default' => array(),
                'method' => 'getBattery',
            ),

            'Raid' => array(
                'show' => !empty($this->settings['show']['raid']),
                'default' => array(),
                'method' => 'getRAID',
            ),

            'Wifi' => array(
                'show' => !empty($this->settings['show']['wifi']),
                'default' => array(),
                'method' => 'getWifi',
            ),

            'SoundCards' => array(
                'show' => !empty($this->settings['show']['sound']),
                'default' => array(),
                'method' => 'getSoundCards',
            ),

            'processStats' => array(
                'show' => !empty($this->settings['show']['process_stats']),
                'default' => array(),
                'method' => 'getProcessStats',
            ),

            'services' => array(
                'show' => !empty($this->settings['show']['services']),
                'default' => array(),
                'method' => 'getServices',
            ),

            'numLoggedIn' => array(
                'show' => !empty($this->settings['show']['numLoggedIn']),
                'default' => false,
                'method' => 'getnumLoggedIn',
            ),

            'virtualization' => array(
                'show' => !empty($this->settings['show']['virtualization']),
                'default' => array(),
                'method' => 'getVirtualization',
            ),

            'cpuUsage' => array(
                'show' => !empty($this->settings['cpu_usage']),
                'default' => false,
                'method' => 'getCPUUsage',
            ),

            'phpVersion' => array(
                'show' => !empty($this->settings['show']['phpversion']),
                'default' => false,
                'method' => 'getPhpVersion',
            ),

            'webService' => array(
                'show' => !empty($this->settings['show']['webservice']),
                'default' => false,
                'method' => 'getWebService',
            ),

            // Extra info such as which fields to not show
            'contains' => array(
                'show' => true,
                'default' => array(),
                'method' => 'getContains',
            ),
        );

        foreach ($fields as $key => $data) {
            if (!$data['show']) {
                $this->info[$key] = $data['default'];
                continue;
            }

            try {
                $method = $reflector->getMethod($data['method']);
                $this->info[$key] = $method->invoke($this->parser);
            } catch (ReflectionException $e) {
                $this->info[$key] = $data['default'];
            }
        }

        // Add a timestamp
        $this->info['timestamp'] = date('c');

        // Run extra extensions
        $this->runExtensions();
    }

    protected function loadSettings($settings = array())
    {

        // Running unit tests?
        if (defined('LINFO_TESTING')) {
            $this->settings = Common::getVarFromFile($this->linfo_testdir.'/test_settings.php', 'settings');
            if (!is_array($this->settings)) {
                throw new FatalException('Failed getting test-specific settings');
            }

            return;
        }

        // Don't just blindly assume we have the ob_* functions...
        if (!function_exists('ob_start')) {
            $settings['compress_content'] = false;
        }

        if (!isset($settings['hide'])) {
            $settings['hide'] = array(
                'filesystems' => array(),
                'storage_devices' => array(),
            );
        }

        // Make sure these are arrays
        $settings['hide']['filesystems'] = is_array($settings['hide']['filesystems']) ? $settings['hide']['filesystems'] : array();
        $settings['hide']['storage_devices'] = is_array($settings['hide']['storage_devices']) ? $settings['hide']['storage_devices'] : array();

        // Make sure these are always hidden
        $settings['hide']['filesystems'][] = 'rootfs';
        $settings['hide']['filesystems'][] = 'binfmt_misc';

        // Default timeformat
        $settings['dates'] = array_key_exists('dates', $settings) ? $settings['dates'] : 'm/d/y h:i A (T)';

        // Default to english translation if garbage is passed
        if (empty($settings['language']) || !preg_match('/^[a-z]{2}$/', $settings['language'])) {
            $settings['language'] = 'en';
        }

        // If it can't be found default to english
        if (!is_file($this->linfo_localdir.'src/Linfo/Lang/'.$settings['language'].'.php')) {
            $settings['language'] = 'en';
        }

        $this->settings = $settings;
    }

    protected function loadLanguage()
    {

        // Running unit tests?
        if (defined('LINFO_TESTING')) {
            $this->lang = require $this->linfo_testdir.'/test_lang.php';
            if (!is_array($this->lang)) {
                throw new FatalException('Failed getting test-specific language');
            }

            return;
        }

        // Load translation, defaulting to english of keys are missing (assuming
        // we're not using english anyway and the english translation indeed exists)
        if (is_file($this->linfo_localdir.'src/Linfo/Lang/en.php') && $this->settings['language'] != 'en') {
            $this->lang = array_merge(require($this->linfo_localdir.'src/Linfo/Lang/en.php'),
                require($this->linfo_localdir.'src/Linfo/Lang/'.$this->settings['language'].'.php'));
        }

        // Otherwise snag desired translation, be it english or a non-english without english to fall back on
        else {
            $this->lang = require $this->linfo_localdir.'src/Linfo/Lang/'.$this->settings['language'].'.php';
        }
    }

    protected function getOS()
    {
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
                return 'Windows';
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
    public function &getInfo()
    {
        return $this->info;
    }

    protected function runExtensions()
    {
        $this->info['extensions'] = array();

        if (!array_key_exists('extensions', $this->settings) || count($this->settings['extensions']) == 0) {
            return;
        }

        // Go through each enabled extension
        foreach ((array) $this->settings['extensions'] as $ext => $enabled) {

            // Is it really enabled?
            if (empty($enabled)) {
                continue;
            }

            // Anti hack
            if (!preg_match('/^[a-z0-9-_]+$/i', $ext)) {
                Errors::add('Extension Loader', 'Not going to load "'.$ext.'" extension as only characters allowed in name are letters/numbers/-_');
                continue;
            }

            // Support older config files with lowercase
            if (preg_match('/^[a-z]/', $ext)) {
                $ext = ucfirst($ext);
            }

            // Try loading our class..
            try {
                $reflector = new ReflectionClass('\\Linfo\\Extension\\'.$ext);
                $ext_class = $reflector->newInstance($this);
            } catch (ReflectionException $e) {
                Errors::add('Extension Loader', 'Cannot instantiate class for "'.$ext.'" extension: '.$e->getMessage());
                continue;
            }

            // Deal with it
            $ext_class->work();

            // Does this edit the $info directly, instead of creating a separate output table type thing?
            if (!$reflector->hasConstant('LINFO_INTEGRATE')) {

                // Result
                $result = $ext_class->result();

                // Save result if it's good
                if ($result != false) {
                    $this->info['extensions'][$ext] = $result;
                }
            }
        }
    }

    public function getLang()
    {
        return $this->lang;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function getAppName()
    {
        return $this->app_name;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getTimeStart()
    {
        return $this->time_start;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function getLocalDir()
    {
        return $this->linfo_localdir;
    }

    public function getTestDir()
    {
        return $this->linfo_testdir;
    }

    public function getCacheDir()
    {
        return dirname(dirname(__DIR__)).'/cache/';
    }
}
