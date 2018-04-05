<?php

// test for as much functionality as possible

// json test will break if this is enabled
$settings['compress_content'] = false;

$settings['byte_notation'] = 1024;
$settings['dates'] = 'm/d/y h:i A (T)';
$settings['language'] = 'en';
$settings['icons'] = true;
$settings['theme'] = 'default';

$settings['show']['kernel'] = true;
$settings['show']['os'] = true;
$settings['show']['load'] = true;
$settings['show']['ram'] = true;
$settings['show']['hd'] = true;
$settings['show']['mounts'] = true;
$settings['show']['mounts_options'] = true;
$settings['show']['network'] = true;
$settings['show']['uptime'] = true;
$settings['show']['cpu'] = true;
$settings['show']['process_stats'] = true;
$settings['show']['hostname'] = true;
$settings['show']['distro'] = true;
$settings['show']['devices'] = true;
$settings['show']['model'] = true;
$settings['show']['numLoggedIn'] = true;
$settings['show']['virtualization'] = true;

$settings['cpu_usage'] = true;

$settings['show']['duplicate_mounts'] = true;

$settings['show']['temps'] = true;
$settings['show']['raid'] = true;

$settings['show']['battery'] = true;
$settings['show']['sound'] = true;
$settings['show']['wifi'] = false;

$settings['show']['services'] = false;

$settings['hide']['storage_devices'] = [];
$settings['hide']['filesystems'] = [];

$settings['hide']['mountpoints_regex'] = [];
$settings['hide']['fs_mount_options'] = [];

$settings['hide']['sg'] = true;

$settings['temps']['hwmon'] = true;
$settings['temps']['hddtemp'] = true;
$settings['temps']['mbmon'] = true;
$settings['temps']['sensord'] = true;

$settings['raid'] = [];
$settings['raid']['mdadm'] = true;

$settings['additional_paths'] = [];
$settings['services'] = [];
