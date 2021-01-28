# v4.0.6 1/28/2021

- Fix PHP8 type error
- Rewrite hwpci parser to be more efficient and track count of found PCI/USB devices
- Apple M1 fixes/support
- Misc fixes

# v4.0.5 11/14/2020

- Fixes to Linux Distro detection
- Fixes to FreeBSD memory detection
- Unit test modernization

# v4.0.4 10/21/2019

- version bump

# v4.0.3 10/10/2019

- Windows fixes

# v4.0.2 9/12/2019

- Misc fixes

# v4.0.1 1/30/2019

- Misc fixes

# v4.0.0 4/4/2018

- Relicense under MIT

# v3.0.2 3/30/2018

- New extensions for Nvidia temps/stats, LXD containers,
- Modernized transmission extension
- Ability to change theme on demand in the UI
- Persian and Spanish translations
- More themes
- Lots of bugfixes

# v3.0.1 8/24/2016


# v3.0.0 10/21/2015
 - Full code reorganization. Uses namespaces now. Easily used as a library from within composer
 - Tested on modern releases of FreeBSD and OpenBSD and some tweaks for each
 - Misc tweaks
 - First new version bump in a long time
 - Adding dnsmasq dhcpd leases parsing extension
 - FreeBSD KVM guest detection


# v2.0.3 9/15/2015
 - Showing IP is now optional
 - James Price added Grunt functionality, cleaned up sass themes, added new theme
 - Fixed some CUPS parsing, and some other ideas from Stefan Briest
 - Adding icon for Raspbian distro, since I see a lot of RPI installs on google
 - Added linux mdadm raid10 detection
 - Frogg added a new theme and better css for mobile and various other improvements
 - Composer improvements, thanks to Tom Witkowski
 - Travis testing. Support definitely confirmed for 5.3+
 - NIC Linux driver tection, Linux SSD detection
 - Various suggestions from various people :)


# v2.0.2 11/24/2014
 - Some layout/css fixes
 - Converted layout/themes to use sass templating
 - Added motherboard detection for Linux
 - Made hwmon Linux parsing more intricate and work on newer kernels
 - Fixed Windows and other OSs bug where parser functions are all private


# v2.0.1 7/7/2014
 - Bugfix: services not showing up on Linux


# v2.0 7/7/2014
 - Major refactor: no global vars; easier to use simply as a library by third party apps
 - Added unit tests via PHPUnit
 - added utorrent web ui parsing extension
 - proper fqdn resolution on linux using /etc/hosts if version reported by sysctl isn't fully qualified
 - all inline exit's converted to exceptions so this can be used as a library without fear of killing whichever app uses it
 - More detailed network detection type on Linux
 - CYGWIN support removed as it was pointless
 - Removed getAll() function from all info classes in favor of a unified behavior in the Linfo->scan() method
 - Tweaks to common functions
 - Biting composer koolaid


# v1.11 5/2/2014
 - Intelligent output handling when running from cli
 - Adding some extentions to main git tree instead of packaging them separately
 - Better support for OS X Mavericks
 - Virtualization detection for Linux
 - CPU Usage reporting for Linux


# v1.10 1/17/2014
 - Regex filtering for filesystem mountpoints
 - Fixed redhat icon
 - Multiple color scheme support


# v1.9 - 7/10/2013
 - Number of users logged in (with active shells)
 - Bugfixes
 - Default to UTC if timezone isn't set, instead of letting php5.3 barf
 - HTML 5
 - More accurate memory usage since caches are omitted
 - JSON-P
 - Won't barf if ob_start or ob_gzhandler don't exist
 - Better OpenBSD support
 - Added Chinese/Finish/Italian translations
 - Date format customizable in config


# v1.8.1 - 4/7/2011
 - Bugfix


# v1.8 - 4/7/2011
 - DragonFly BSD support
 - Better Darwin (Mac) support
 - Reporting of Mac model
 - Ability to run certain commands as root (via nopasswd sudo)
 - Display CPU architecture on Windows
 - Better FreeBSD device detection
 - Bugfixes


# v1.7 - 1/07/2011
 - XML/JSON/PHP array output format support
 - Moved list of other authors from README to THANKS
 - Various improvements
 - Information on how to fix timezone is now in sample config file
 - Ability to find distribution name/version on Linux on major distros
 - Icons of OS's and popular Linux distros
 - Hardware detection (USB, PCI) now works on CentOS, among other linux distros
 - Kernel architecture is shown on Linux
 - FreeBSD soft-raid detection improved


# v1.6 - 11/22/2010
 - Windows XP support
 - More mature extension support
 - Various improvements


# v1.5 - 11/16/2010
 - Extensions now packaged/developed separately
 - Windows 7 support
 - Various improvements


# v1.4 - 10/20/2010
 - MAC netstat parsing fix
 - Experimental cups/samba status parsing
 - New colorscheme
 - Various improvements


# v1.3 - 10/27/2010
 - Various other OS support
 - Various improvements
 - Various new features


# v1.2 - 09/18/2010
 - OpenBSD support
 - Partial Minix support
 - Various improvements
 - Real changelog started


# v1.1 - 09/05/2010
 - Full NetBSD support
 - First real cross platform release
