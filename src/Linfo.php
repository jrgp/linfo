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

use Linfo\OS\OS;
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
    protected $settings = array();
    protected $lang = array();
    protected $info = array();
    /** @var OS */
    protected $parser;
    protected $linfo_localdir;

    /**
     * Linfo constructor.
     * @param array $userSettings
     * @throws FatalException
     */
    public function __construct(array $userSettings = array())
    {
        // Some paths..
        $this->linfo_localdir = dirname(dirname(__DIR__)) . '/';

        // Run through dependencies / sanity checking
        if (!extension_loaded('pcre') && !function_exists('preg_match') && !function_exists('preg_match_all')) {
            throw new FatalException('Linfo needs the `pcre` extension to be loaded. https://php.net/pcre');
        }

        // Warnings usually displayed to browser happen if date.timezone isn't set in php 5.3+
        if (!ini_get('date.timezone')) {
            @ini_set('date.timezone', 'Etc/UTC');
        }

        // Load our settings/language
        $this->loadSettings(array_merge($this->getDefaultSettings(), $userSettings));
        $this->loadLanguage();

        // Some classes need our vars; config them
        Common::config($this);
        CallExt::config($this);

        // Determine OS
        $os = $this->getOS();

        if (!$os) {
            throw new FatalException('Unknown/unsupported operating system');
        }

        $distro_class = '\\Linfo\\OS\\' . $os;
        $this->parser = new $distro_class($this->settings);
    }

    /**
     * Forward missing method request to the parser
     *
     * @param string $name
     * @param string $args
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (method_exists($this->parser, $name) && is_callable(array($this->parser, $name))) {
            return call_user_func(array($this->parser, $name), $args);
        }
    }

    /**
     * Load everything, while obeying permissions...
     */
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

    /**
     * load settings
     * @param array $settings
     */
    protected function loadSettings(array $settings = array())
    {
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
        if (!is_file($this->linfo_localdir . 'src/Linfo/Lang/' . $settings['language'] . '.php')) {
            $settings['language'] = 'en';
        }

        $this->settings = $settings;
    }

    /**
     * load language
     */
    protected function loadLanguage()
    {
        // Load translation, defaulting to english of keys are missing (assuming
        // we're not using english anyway and the english translation indeed exists)
        if (is_file($this->linfo_localdir . 'src/Linfo/Lang/en.php') && $this->settings['language'] != 'en') {
            $this->lang = array_merge(require($this->linfo_localdir . 'src/Linfo/Lang/en.php'),
                require($this->linfo_localdir . 'src/Linfo/Lang/' . $this->settings['language'] . '.php'));
        } // Otherwise snag desired translation, be it english or a non-english without english to fall back on
        else {
            $this->lang = require $this->linfo_localdir . 'src/Linfo/Lang/' . $this->settings['language'] . '.php';
        }
    }

    /**
     * @return null|string
     */
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
        return null;
    }

    /**
     * getInfo()
     *
     * Returning reference so extensions can modify result
     */
    public function &getInfo()
    {
        return $this->info;
    }

    /**
     * run extensions
     */
    protected function runExtensions()
    {
        $this->info['extensions'] = array();

        if (!array_key_exists('extensions', $this->settings) || count($this->settings['extensions']) == 0) {
            return;
        }

        // Go through each enabled extension
        foreach ((array)$this->settings['extensions'] as $ext => $enabled) {

            // Is it really enabled?
            if (empty($enabled)) {
                continue;
            }

            // Anti hack
            if (!preg_match('/^[a-z0-9-_]+$/i', $ext)) {
                Errors::add('Extension Loader', 'Not going to load "' . $ext . '" extension as only characters allowed in name are letters/numbers/-_');
                continue;
            }

            // Support older config files with lowercase
            if (preg_match('/^[a-z]/', $ext)) {
                $ext = ucfirst($ext);
            }

            // Try loading our class..
            try {
                $reflector = new ReflectionClass('\\Linfo\\Extension\\' . $ext);
                $ext_class = $reflector->newInstance($this);
            } catch (ReflectionException $e) {
                Errors::add('Extension Loader', 'Cannot instantiate class for "' . $ext . '" extension: ' . $e->getMessage());
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

    /**
     * @return array
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return array
     */
    protected function getDefaultSettings()
    {
        $settings = array();
        // If you experience timezone errors, uncomment (remove //) the following line and change the timezone to your liking
        // date_default_timezone_set('America/New_York');

        /*
         * Usual configuration
         */
        $settings['byte_notation'] = 1024; // Either 1024 or 1000; defaults to 1024
        $settings['dates'] = 'm/d/y h:i A (T)'; // Format for dates shown. See php.net/date for syntax
        $settings['language'] = 'en'; // Refer to the lang/ folder for supported lanugages

        /*
         * Possibly don't show stuff
         */

        // For certain reasons, some might choose to not display all we can
        // Set these to true to enable; false to disable. They default to false.
        $settings['show']['kernel'] = true;
        $settings['show']['ip'] = true;
        $settings['show']['os'] = true;
        $settings['show']['load'] = true;
        $settings['show']['ram'] = true;
        $settings['show']['hd'] = true;
        $settings['show']['mounts'] = true;
        $settings['show']['mounts_options'] = false; // Might be useless/confidential information; disabled by default.
        $settings['show']['webservice'] = false; // Might be dangerous/confidential information; disabled by default.
        $settings['show']['phpversion'] = false; // Might be dangerous/confidential information; disabled by default.
        $settings['show']['network'] = true;
        $settings['show']['uptime'] = true;
        $settings['show']['cpu'] = true;
        $settings['show']['process_stats'] = true;
        $settings['show']['hostname'] = true;
        $settings['show']['distro'] = true; # Attempt finding name and version of distribution on Linux systems
        $settings['show']['devices'] = true; # Slow on old systems
        $settings['show']['model'] = true; # Model of system. Supported on certain OS's. ex: Macbook Pro
        $settings['show']['numLoggedIn'] = true; # Number of unqiue users with shells running (on Linux)
        $settings['show']['virtualization'] = true; # whether this is a VPS/VM and what kind

        // CPU Usage on Linux (per core and overall). This requires running sleep(1) once so it slows
        // the entire page load down. Enable at your own inconvenience, especially since the load averages
        // are more useful.
        $settings['cpu_usage'] = false;

        // Sometimes a filesystem mount is mounted more than once. Only list the first one I see?
        // (note, duplicates are not shown twice in the file system totals)
        $settings['show']['duplicate_mounts'] = true;

        // Disabled by default as they require extra config below
        $settings['show']['temps'] = false;
        $settings['show']['raid'] = false;

        // Following are probably only useful on laptop/desktop/workstation systems, not servers, although they work just as well
        $settings['show']['battery'] = false;
        $settings['show']['sound'] = false;
        $settings['show']['wifi'] = false; # Not finished

        // Service monitoring
        $settings['show']['services'] = false;

        /*
         * Misc settings pertaining to the above follow below:
         */

        // Hide certain file systems / devices
        $settings['hide']['filesystems'] = array(
            'tmpfs', 'ecryptfs', 'nfsd', 'rpc_pipefs', 'proc', 'sysfs',
            'usbfs', 'devpts', 'fusectl', 'securityfs', 'fuse.truecrypt',
            'cgroup', 'debugfs', 'mqueue', 'hugetlbfs', 'pstore');
        $settings['hide']['storage_devices'] = array('gvfs-fuse-daemon', 'none', 'systemd-1', 'udev');

        // filter mountpoints based on PCRE regex, eg '@^/proc@', '@^/sys@', '@^/dev@'
        $settings['hide']['mountpoints_regex'] = array();

        // Hide mount options for these file systems. (very, very suggested, especially the ecryptfs ones)
        $settings['hide']['fs_mount_options'] = array('ecryptfs');

        // Hide hard drives that begin with /dev/sg?. These are duplicates of usual ones, like /dev/sd?
        $settings['hide']['sg'] = true; # Linux only

        // Set to true to not resolve symlinks in the mountpoint device paths. Eg don't convert /dev/mapper/root to /dev/dm-0
        $settings['hide']['dont_resolve_mountpoint_symlinks'] = false; # Linux only

        // Various softraids. Set to true to enable.
        // Only works if it's available on your system; otherwise does nothing
        $settings['raid']['gmirror'] = false;  # For FreeBSD
        $settings['raid']['mdadm'] = false;  # For Linux; known to support RAID 1, 5, and 6

        // Various ways of getting temps/voltages/etc. Set to true to enable. Currently these are just for Linux
        $settings['temps']['hwmon'] = true; // Requires no extra config, is fast, and is in /sys :)
        $settings['temps']['thermal_zone'] = false;
        $settings['temps']['hddtemp'] = false;
        $settings['temps']['mbmon'] = false;
        $settings['temps']['sensord'] = false; // Part of lm-sensors; logs periodically to syslog. slow
        $settings['temps_show0rpmfans'] = false; // Set to true to show fans with 0 RPM

        // Configuration for getting temps with hddtemp
        $settings['hddtemp']['mode'] = 'daemon'; // Either daemon or syslog
        $settings['hddtemp']['address'] = array( // Address/Port of hddtemp daemon to connect to
            'host' => 'localhost',
            'port' => 7634
        );
        // Configuration for getting temps with mbmon
        $settings['mbmon']['address'] = array( // Address/Port of mbmon daemon to connect to
            'host' => 'localhost',
            'port' => 411
        );

        /*
         * For the things that require executing external programs, such as non-linux OS's
         * and the extensions, you may specify other paths to search for them here:
         */
        $settings['additional_paths'] = array(//'/opt/bin' # for example
        );


        /*
         * Services. It works by specifying locations to PID files, which then get checked
         * Either that or specifying a path to the executable, which we'll try to find a running
         * process PID entry for. It'll stop on the first it finds.
         */

        // Format: Label => pid file path
        $settings['services']['pidFiles'] = array(
            // 'Apache' => '/var/run/apache2.pid', // uncomment to enable
            // 'SSHd' => '/var/run/sshd.pid'
        );

        // Format: Label => path to executable or array containing arguments to be checked
        $settings['services']['executables'] = array(
            // 'MySQLd' => '/usr/sbin/mysqld' // uncomment to enable
            // 'BuildSlave' => array('/usr/bin/python', // executable
            //						1 => '/usr/local/bin/buildslave') // argv[1]
        );

        // Format: Label => systemd service name
        $settings['services']['systemdServices'] = array(
            // 'Apache' => 'httpd', // uncomment to enable
            // 'SSHd' => 'sshd'
        );

        /*
         * Occasional sudo
         * Sometimes you may want to have one of the external commands here be ran as root with
         * sudo. This requires the web server user be set to "NOPASS" in your sudoers so the sudo
         * command just works without a prompt.
         *
         * Add names of commands to the array if this is what you want. Just the name of the command;
         * not the complete path. This also applies to commands called by extensions.
         *
         * Note: this is extremely dangerous if done wrong
         */
        $settings['sudo_apps'] = array(//'ps' // For example
        );

        return $settings;
    }

    /**
     * @return array
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @return OS
     */
    public function getParser()
    {
        return $this->parser;
    }
}
