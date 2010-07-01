<?php
defined('IN_INFO') or exit;

/*
 * Stuff that can be hardcoded for speed and security reasons
 */

// Linux fully supported. FreeBSD alpha / partially working
$setting_os = 'linux';

/*
 * Settings
 */

// For certain reasons, some might choose to not display all we can
$settings['show']['kernel'] = true;
$settings['show']['os'] = true;
$settings['show']['uptime'] = true;
$settings['show']['load'] = true;
$settings['show']['ram'] = true;
$settings['show']['hd'] = true;
$settings['show']['mounts'] = true;
$settings['show']['network'] = true;
$settings['show']['uptime'] = true;
$settings['show']['cpu'] = true;
$settings['show']['raid'] = true;
$settings['show']['hostname'] = true;
$settings['show']['devices'] = true;
$settings['show']['temps'] = true;
$settings['show']['battery'] = true;


// Hide certain file systems / devices
$settings['hide']['filesystems'] = array(
	'tmpfs', 'ecryptfs', 'nfsd', 'rpc_pipefs',
	'usbfs', 'devpts', 'fusectl', 'securityfs');
$settings['hide']['storage_devices'] = array('gvfs-fuse-daemon', 'none');

// Softraid on linux
$settings['raid_type'] = 'mdadm'; // TODO

// Getting temps...how? Can either be false, 'hddtemp', 'mbmon', or both: array('hddtemp', 'mbmon')
$settings['options']['temps'] = false;

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
 * Caching.
 * Strongly recommended.
 * Make sure cachedir is writable by user php runs as
 * Requires SQLite, which is enabled by default in php => v5
 *
 * ==-- Not yet finished
 *
 */
$settings['cache'] = true; // Use cache?
$settings['cache_dir'] = dirname(__FILE__).'/cache/'; // Defaults to cache/ in current folder
