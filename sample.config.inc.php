<?php

// If you experience timezone errors, uncomment (remove //) the following line and change the timezone to your liking
// date_default_timezone_set('America/New_York');

/*
 * Usual configuration
 */
$settings['byte_notation'] = 1024; // Either 1024 or 1000; defaults to 1024
$settings['dates'] = 'm/d/y h:i A (T)'; // Format for dates shown. See php.net/date for syntax
$settings['language'] = 'en'; // Refer to the lang/ folder for supported languages
$settings['icons'] = true; // simple icons
$settings['theme'] = 'default'; // Theme file (layout/theme_$n.css). Look at the contents of the layout/ folder for other themes.
$settings['gzip'] = false; // Manually gzip output. Unneeded if your web server already does it.


$settings['allow_changing_themes'] = false; // Allow changing the theme per user in the UI?

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
$settings['hide']['mountpoints_regex'] = [];

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
$settings['additional_paths'] = array(
	 //'/opt/bin' # for example
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

/*
 * Debugging settings
 */

// Show errors? Disabled by default to hide vulnerabilities / attributes on the server
$settings['show_errors'] = false;

// Show results from timing ourselves? Similar to above.
// Lets you see how much time getting each bit of info takes.
$settings['timer'] = false;

// Compress content, can be turned off to view error messages in browser
$settings['compress_content'] = true;

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
$settings['sudo_apps'] = array(
	//'ps' // For example
);
