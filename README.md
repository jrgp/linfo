# Linfo - Server stats UI/library

[![Build Status](https://travis-ci.org/jrgp/linfo.svg?branch=master)](https://travis-ci.org/jrgp/linfo)


### Linfo is a:

 - Light themable Web UI and REST API displaying lots of system stats
 - Ncurses CLI view of WebUI
 - Extensible, easy (composer) to use PHP5 Library to get extensive system stats programmatically from your PHP app

### Contributing

Interested in contributing? Check out [Development Readme](DEVELOPERS.md)

### web UI
![Linfo WebUI Screenshot](http://jrgp.us/misc/linfo.png)

### ncurses preview
![Linfo Ncurses Screenshot](http://jrgp.us/misc/linfo_curses.png)

See: [Enabling ncurses](NCURSES.md)

### PHP library usage

```bash
composer require linfo/linfo
```

```php
<?php
$linfo = new \Linfo\Linfo;
$parser = $linfo->getParser();

var_dump($parser->getCPU()); // and a whole lot more
```



## Runs on
 - Linux
 - Windows
 - FreeBSD
 - NetBSD
 - OpenBSD
 - DragonflyBSD
 - Darwin/Mac OSX
 - Solaris
 - Minix

## Information reported
 - CPU type/speed; Architecture
 - Mount point usage
 - Hard/optical/flash drives
 - Hardware Devices
 - Network devices and stats
 - Uptime/date booted
 - Hostname
 - Memory usage (physical and swap, if possible)
 - Temperatures/voltages/fan speeds
 - RAID arrays
 - Via included extensions:
   - Nvidia GPU temps
   - Truecrypt mounts
   - DHCPD leases
   - Samba status
   - APC UPS status
   - Transmission torrents status
   - uTorrent torrents status
   - Soldat server status
   - CUPS printer status
   - IPMI
   - libvirt VMs
   - lxd Containers
   - more

## System requirements:
 - At least PHP 5.4
 - If you are using PHP 7.1.9 or lower, you might need to disable the opcache extension.
 - pcre extension

#### Windows
 - You need to have [COM enabled](http://www.php.net/manual/en/class.com.php).

#### Linux
 - /proc and /sys mounted and readable by PHP
 - Tested with the 2.6.x/3.x kernels

#### FreeBSD
 - PHP able to execute usual programs under /bin, /usr/bin, /usr/local/bin, etc
 - Tested on 8.0-RELEASE, 10.2-RELEASE

#### NetBSD
 - PHP able to execute usual programs under /bin, /usr/bin, /usr/local/bin, /usr/pkg/bin, etc
 - Tested on NetBSD 5.0.2

#### OpenBSD
 - PHP able to execute usual programs under /bin, /usr/bin, /usr/local/bin,  etc
 - Tested on OpenBSD 4.7, 5.7
 - Turn chroot of httpd/nginx/php-fpm off

## Web UI Installation
 1. Extract tarball contents to somewhere under your web root
 2. Rename ``sample.config.inc.php`` to ``config.inc.php``, after optionally changing values in it
 3. Visit page in web browser
 4. Pass URL to your friends to show off


### URL arguments

- ``?out=xml`` - XML output (requires SimpleXML extension)
- ``?out=json`` - JSON output
- ``?out=jsonp&callback=functionName`` - JSON output with a function callback. (Look here: http://www.json-p.org/ )
- ``?out=php_array`` - PHP serialized associative array
- ``?out=html`` - Usual lightweight HTML (default)

### Extensions
 - See a list of php files in src/Linfo/Extensions/
 - Open them and look at the comment at the top of the file for usage


## Troubleshooting:

Set ``$settings['show_errors'] = true;`` in ``config.inc.php`` to yield useful error messages.


## TODO:
 - Support for other Unix operating systems (Hurd, IRIX, AIX, HP UX, etc)
 - Support for strange operating systems: Haiku/BeOS
 - More superfluous features/extensions
 - Make ncurses mode rival htop (half kidding)

## Meta
 - By Joe Gillotti <joe@u13.net>
 - Licensed under MIT
 - Pull requests! [Linfo on Github](http://github.com/jrgp/linfo)
 - [Commit stats from OpenHub/Ohloh](https://www.openhub.net/p/linfo)

_This project is dedicated to the memory of Eric Looper._
