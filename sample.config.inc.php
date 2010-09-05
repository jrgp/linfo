<?php

// Don't touch this. It attempts to thwart attempts of reading this file by another php script
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
$settings['show']['process_stats'] = true; 
$settings['show']['hostname'] = true;
$settings['show']['devices'] = true; # Slow on old systems

// Disabled by default as they require extra config below
$settings['show']['temps'] = false;
$settings['show']['raid'] = false; 

// Following are probably only useful on laptop/desktop/workstation systems, not servers, although they work just as well
$settings['show']['battery'] = false;
$settings['show']['sound'] = false;
$settings['show']['wifi'] = false; # Not finished

/*
 * Misc settings pertaining to the above follow below:
 */

// Hide certain file systems / devices
$settings['hide']['filesystems'] = array(
	'tmpfs', 'ecryptfs', 'nfsd', 'rpc_pipefs',
	'usbfs', 'devpts', 'fusectl', 'securityfs');
$settings['hide']['storage_devices'] = array('gvfs-fuse-daemon', 'none');

// Hide hard drives that begin with /dev/sg?. These are duplicates of usual ones, like /dev/sd?
$settings['hide']['sg'] = true; # Linux only

// Various softraids. Set to true to enable.
// Only works if it's available on your system; otherwise does nothing
$settings['raid']['gmirror'] = false;  # For FreeBSD
$settings['raid']['mdadm'] = false;  # For Linux; known to support RAID 1, 5, and 6

// Various ways of getting temps/voltages/etc. Set to true to enable. Currently these are just for Linux
$settings['temps']['hwmon'] = true; // Requires no extra config, is fast, and is in /sys :)
$settings['temps']['hddtemp'] = false;
$settings['temps']['mbmon'] = false;
$settings['temps']['sensord'] = false; // Part of lm-sensors; logs periodically to syslog. slow

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
 * Debugging settings
 */

// Show errors? Disabled by default to hide vulnerabilities / attributes on the server
$settings['show_errors'] = false;

// Show results from timing ourselves? Similar to above.
// Lets you see how much time getting each bit of info takes.
$settings['timer'] = false;
