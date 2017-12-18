# Linfo - Server stats library

### Linfo is a:
 - Extensible, easy (composer) to use PHP Library to get extensive system stats programmatically from your PHP app

### PHP library usage

```bash
composer require gemorroj/linfo
```

```php
<?php
$linfo = new \Linfo\Linfo;
$info = $linfo->getInfo();

print_r($info); // and a whole lot more
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
   - more

## System requirements:
 - At least PHP 5.6
 - If you are using PHP 7.1, you might need to disable the opcache extension.
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

### Extensions
 - See a list of php files in src/Linfo/Extensions/
 - Open them and look at the comment at the top of the file for usage


## TODO:
 - Support for other Unix operating systems (Hurd, IRIX, AIX, HP UX, etc)
 - Support for strange operating systems: Haiku/BeOS
 - More superfluous features/extensions
 - Make ncurses mode rival htop (half kidding)

## Meta
 - By Joe Gillotti <joe@u13.net>
 - Licensed under GPL
 - Pull requests! [Linfo on Github](http://github.com/jrgp/linfo)
 - [Commit stats from OpenHub/Ohloh](https://www.openhub.net/p/linfo)

_This project is dedicated to the memory of Eric Looper._
