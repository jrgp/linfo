<?php

// Don't touch this
defined('IN_INFO') or exit;

/*
 * Possibly don't show stuff
 */

// For certain reasons, some might choose to not display all we can
// Set these to true to enable; false to disable. They default to false.
$settings['show']['kernel'] = true;
$settings['show']['os'] = true;
$settings['show']['load'] = true;
$settings['show']['ram'] = true;
$settings['show']['hd'] = true;
$settings['show']['mounts'] = true;
$settings['show']['network'] = true;
$settings['show']['uptime'] = true;
$settings['show']['cpu'] = true;
$settings['show']['hostname'] = true;
$settings['show']['devices'] = true;
$settings['show']['temps'] = true;
$settings['show']['battery'] = true;
$settings['show']['raid'] = false; 
$settings['show']['wifi'] = false; # Not finished

// Hide certain file systems / devices
$settings['hide']['filesystems'] = array(
	'tmpfs', 'ecryptfs', 'nfsd', 'rpc_pipefs',
	'usbfs', 'devpts', 'fusectl', 'securityfs');
$settings['hide']['storage_devices'] = array('gvfs-fuse-daemon', 'none');

// Various softraids. Set to true to enable.
// Only works if it's available on your system; otherwise does nothing
$settings['raid']['gmirror'] = false;  # For FreeBSD
$settings['raid']['mdadm'] = false;  # For Linux; known to support RAID 1, 5, and 6

// Various ways of getting temps/voltages/etc. Set to true to enable.
$settings['temps']['hddtemp'] = false;
$settings['temps']['mbmon'] = false;

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


