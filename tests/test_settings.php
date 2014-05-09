<?php

// test for as much functionality as possible

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
$settings['show']['mounts_options'] = false; 
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

$settings['cpu_usage'] = false; 

$settings['show']['duplicate_mounts'] = true;

$settings['show']['temps'] = false;
$settings['show']['raid'] = false; 

$settings['show']['battery'] = false;
$settings['show']['sound'] = false;
$settings['show']['wifi'] = false; 

$settings['show']['services'] = false;

$settings['hide']['storage_devices'] = array();
$settings['hide']['filesystems'] = array();
