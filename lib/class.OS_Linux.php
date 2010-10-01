<?php

/*
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.  If not, see <http://www.gnu.org/licenses/>.
 * 
*/


defined('IN_INFO') or exit;

/*
 * Get info on a usual linux system
 * Works by exclusively looking around /proc and /sys
 * Totally ignores CallExt class, very deliberately
 * Also deliberately ignores trying to find out the distro. 
 */

class OS_Linux {

	// Keep these tucked away
	protected
		$settings, $error;

	// Start us off
	public function __construct($settings) {

		// Localize settings
		$this->settings = $settings;

		// Localize error handler
		$this->error = LinfoError::Fledging();

		// Make sure we have what we need
		if (!is_dir('/sys') || !is_dir('/proc'))
			throw new GetInfoException('This needs access to /proc and /sys to work.');
	}

	// All
	public function getAll() {

		// Return everything, whilst obeying display permissions
		return array(
			'OS' => empty($this->settings['show']['os']) ? '' : $this->getOS(),
			'Kernel' => empty($this->settings['show']['kernel']) ? '' : $this->getKernel(),
			'RAM' => empty($this->settings['show']['ram']) ? array() : $this->getRam(),
			'HD' => empty($this->settings['show']['hd']) ? '' : $this->getHD(),
			'Mounts' => empty($this->settings['show']['mounts']) ? array() : $this->getMounts(),
			'Load' => empty($this->settings['show']['load']) ? array() : $this->getLoad(),
			'HostName' => empty($this->settings['show']['hostname']) ? '' : $this->getHostName(),
			'UpTime' => empty($this->settings['show']['uptime']) ? '' : $this->getUpTime(),
			'CPU' => empty($this->settings['show']['cpu']) ? array() : $this->getCPU(),
			'Network Devices' => empty($this->settings['show']['network']) ? array() : $this->getNet(),
			'Devices' => empty($this->settings['show']['devices']) ? array() : $this->getDevs(),
			'Temps' => empty($this->settings['show']['temps']) ? array(): $this->getTemps(),
			'Battery' => empty($this->settings['show']['battery']) ? array(): $this->getBattery(),
			'Raid' => empty($this->settings['show']['raid']) ? array(): $this->getRAID(),
			'Wifi' => empty($this->settings['show']['wifi']) ? array(): $this->getWifi(),
			'SoundCards' => empty($this->settings['show']['sound']) ? array(): $this->getSoundCards(),
			'processStats' => empty($this->settings['show']['process_stats']) ? array() : $this->getProcessStats()
		);
	}

	// Return OS type
	private function getOS() {
		
		// Linux, obviously
		return 'Linux';
	}

	// Get linux kernel version
	private function getKernel() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Kernel');

		// File containing info
		$file = '/proc/version';

		// Make sure we can use it
		if (!is_file($file) || !is_readable($file)) {
			$this->error->add('Linfo Core', '/proc/version not found');
			return 'Unknown';
		}

		// Get it
		$contents = getContents($file);

		// Parse it
		if (preg_match('/^Linux version (\S+).+$/', $contents, $match) != 1) {
			$this->error->add('Linfo Core', 'Error parsing /proc/version');
			return 'Unknown';
		}

		// Return it
		return $match[1];
	}

	// Get host name
	private function getHostName() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Hostname');

		// File containing info
		$file = '/proc/sys/kernel/hostname';
		
		// Get it
		$hostname = getContents($file, false);

		// Failed?
		if ($hostname === false) {
			$this->error->add('Linfo Core', 'Error getting /proc/sys/kernel/hostname');
			return 'Unknown';
		}
		else {

			// Didn't fail; return it
			return $hostname;
		}
	}

	// Get ram usage/amount/types
	private function getRam(){
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Memory');

		// We'll return the contents of this
		$return = array();

		// Files containing juicy info
		$procFileSwap = '/proc/swaps';
		$procFileMem = '/proc/meminfo';

		// First off, these need to exist..
		if (!is_readable($procFileSwap) || !is_readable($procFileMem)) {
			$this->error->add('Linfo Core', '/proc/swaps and/or /proc/meminfo are not readable');
			return array();
		}

		// To hold their values
		$memVals = array();
		$swapVals = array();

		// Get memContents
		@preg_match_all('/^([^:]+)\:\s+(\d+)\s*(?:k[bB])?\s*/m', getContents($procFileMem), $matches, PREG_SET_ORDER);

		// Deal with it
		foreach ((array)$matches as $memInfo)
			$memVals[$memInfo[1]] = $memInfo[2];

		// Get swapContents
		@preg_match_all('/^(\S+)\s+(\S+)\s+(\d+)\s(\d+)[^$]*$/m', getContents($procFileSwap), $matches, PREG_SET_ORDER);
		foreach ((array)$matches as $swapDevice) {
			
			// Append each swap device
			$swapVals[] = array (
				'device' => $swapDevice[1],
				'type' => $swapDevice[2],
				'size' => $swapDevice[3]*1024,
				'used' => $swapDevice[4]*1024
			);
		}

		// Get individual vals
		$return['type'] = 'Physical';
		$return['total'] = $memVals['MemTotal']*1024;
		$return['free'] = $memVals['MemFree']*1024;
		$return['swapTotal'] = $memVals['SwapTotal']*1024;
		$return['swapFree'] = $memVals['SwapFree']*1024;
		$return['swapCached'] = $memVals['SwapCached']*1024;
		$return['swapInfo'] = $swapVals;

		// Return it
		return $return;
	}

	// Get processor info
	private function getCPU() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('CPUs');

		// File that has it
		$file = '/proc/cpuinfo';

		// Not there?
		if (!is_file($file) || !is_readable($file)) {
			$this->error->add('Linfo Core', '/proc/cpuinfo not readable');
			return array();
		}

		/*
		 * Get all info for all CPUs from the cpuinfo file
		 */

		// Get contents
		$contents = trim(@file_get_contents($file));

		// Lines
		$lines = explode("\n", $contents);

		// Store CPU's here
		$cpus = array();

		// Holder for current cpu info
		$cur_cpu = array();

		// Go through lines in file
		foreach ($lines as $num => $line) {

			// Approaching new CPU? Save current and start new info for this
			if ($line == '' && count($cur_cpu) > 0) {
				$cpus[] = $cur_cpu;
				$cur_cpu = array();
				continue;
			}

			// Info here
			$m = explode(':', $line, 2);
			$m[0] = trim($m[0]);
			$m[1] = trim($m[1]);

			// Pointless?
			if ($m[0] == '' || $m[1] == '')
				continue;

			// Save this one
			$cur_cpu[$m[0]] = $m[1];

		}

		// Save remaining one
		if (count($cur_cpu) > 0)
			$cpus[] = $cur_cpu;

		/*
		 * What we want are MHZ, Vendor, and Model.
		 */

		// Store them here
		$return = array();

		// See if we have what we want
		foreach($cpus as $cpu) {

			// Save info for this one here temporarily
			$curr = array();

			// Try getting brand/vendor
			if (array_key_exists('vendor_id', $cpu))
				$curr['Vendor'] = $cpu['vendor_id'];
			else
				$curr['Vendor'] = 'unknown';

			// Speed in MHz
			if (array_key_exists('cpu MHz', $cpu))
				$curr['MHz'] = $cpu['cpu MHz'];
			elseif (array_key_exists('Cpu0ClkTck', $cpu)) // Old Sun boxes
				$curr['MHz'] = hexdec($cpu['Cpu0ClkTck']) / 1000000;
			else
				$curr['MHz'] = 'unknown';

			// CPU Model
			if (array_key_exists('model name', $cpu))
				$curr['Model'] = $cpu['model name'];
			elseif (array_key_exists('cpu', $cpu)) // Again, old Sun boxes
				$curr['Model'] = $cpu['cpu'];
			else
				$curr['Model'] = 'unknown';

			// Save this one
			$return[] = $curr;
		}

		// Return them
		return $return;
	}

	// Famously interesting uptime
	private function getUpTime () {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Uptime');

		// Get contents
		$contents = getContents('/proc/uptime', false);

		// eh?
		if ($contents === false) {
			$this->error->add('Linfo Core', '/proc/uptime does not exist.');
			return 'Unknown';
		}

		// Seconds
		list($seconds) = explode(' ', $contents, 1);

		// Get it textual, as in days/minutes/hours/etc
		$uptime = seconds_convert(ceil($seconds));

		// Now find out when the system was booted
		$contents = getContents('/proc/stat', false);

		// Ugh
		if ($contents === false)
			return $uptime; // Settle for just uptime

		// Get date of boot
		if (preg_match('/^btime (\d+)$/m', $contents, $boot) != 1)
			return $uptime;

		// Okay?
		list(, $boot) = $boot;

		// Return
		return $uptime . '; booted '.date('m/d/y h:i A', $boot);
	}

	// Get disk drives
	// TODO: Possibly more information?
	private function getHD() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Drives');

		// Get partitions
		$partitions = array();
		$partitions_contents = getContents('/proc/partitions');
		if (@preg_match_all('/(\d+)\s+([a-z]{3})(\d+)$/m', $partitions_contents, $partitions_match, PREG_SET_ORDER) > 0) {
			// Go through each match
			foreach($partitions_match as $partition)
				// And save each partition, using the drive path as a key
				$partitions[$partition[2]][] = array(
					'size' => $partition[1] * 1024,
					'number' => $partition[3]
				);
		}
		
		// Store drives here
		$drives = array();
		
		// Get actual drives
		foreach((array)@glob('/sys/block/*/device/model', GLOB_NOSORT) as $path) {

			// Dirname of the drive's sys entry
			$dirname = dirname(dirname($path));

			// Parts of the path
			$parts = explode('/', $path);

			// Attempt getting read/write stats
			if (preg_match('/^(\d+)\s+\d+\s+\d+\s+\d+\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+\s+\d+$/', getContents(dirname(dirname($path)).'/stat'), $statMatches) !== 1) {
				// Didn't get it
				$reads = false;
				$writes = false;
			}
			else
				// Got it, save it
				list(, $reads, $writes) = $statMatches;

			// Append this drive on
			$drives[] = array(
				'name' =>  getContents($path, 'Unknown'),
				'vendor' => getContents(dirname($path).'/vendor', 'Unknown'),
				'device' => '/dev/'.$parts[3],
				'reads' => $reads,
				'writes' => $writes,
				'size' => getContents(dirname(dirname($path)).'/size', 0) * 512,
				'partitions' => array_key_exists($parts[3], $partitions) && is_array($partitions[$parts[3]]) ? $partitions[$parts[3]] : false 
			);
		}

		// Return drives
		return $drives;
	}

	// Get temps/voltages
	private function getTemps() {
	
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Temperature');

		// Hold them here
		$return = array();

		// hddtemp?
		if (array_key_exists('hddtemp', (array)$this->settings['temps']) && !empty($this->settings['temps']['hddtemp'])) {
			try {
				// Initiate class
				$hddtemp = new GetHddTemp($this->settings);

				// Set mode, as in either daemon or syslog
				$hddtemp->setMode($this->settings['hddtemp']['mode']);

				// If we're daemon, save host and port
				if ($this->settings['hddtemp']['mode'] == 'daemon') {
					$hddtemp->setAddress(
						$this->settings['hddtemp']['address']['host'],
						$this->settings['hddtemp']['address']['port']);
				}

				// Result after working it
				$hddtemp_res = $hddtemp->work();

				// If it's an array, it worked
				if (is_array($hddtemp_res))
					// Save result
					$return = array_merge($return, $hddtemp_res);

			}

			// There was an issue
			catch (GetHddTempException $e) {
				$this->error->add('hddtemp parser', $e->getMessage());
			}
		}

		// mbmon?
		if (array_key_exists('mbmon', (array)$this->settings['temps']) && !empty($this->settings['temps']['mbmon'])) {
			try {
				// Initiate class
				$mbmon = new GetMbMon;

				// Set host and port
				$mbmon->setAddress(
					$this->settings['mbmon']['address']['host'],
					$this->settings['mbmon']['address']['port']);

				// Get result after working it
				$mbmon_res = $mbmon->work();

				// If it's an array, it worked
				if (is_array($mbmon_res))
					// Save result
					$return = array_merge($return, $mbmon_res);
			}
			catch (GetMbMonException $e) {
				$this->error->add('mbmon parser', $e->getMessage());
			}
		}

		// sensord? (part of lm-sensors)
		if (array_key_exists('sensord', (array)$this->settings['temps']) && !empty($this->settings['temps']['sensord'])) {
			try {
				// Iniatate class
				$sensord = new GetSensord;

				// Work it
				$sensord_res = $sensord->work();

				// If it's an array, it worked
				if (is_array($sensord_res))
					// Save result
					$return = array_merge($return, $sensord_res);
			}
			catch (GetSensordException $e) {
				$this->error->add('sensord parser', $e->getMessage());
			}
		}

		// hwmon? (probably the fastest of what's here)
		// too simple to be in its own class
		if (array_key_exists('hwmon', (array)$this->settings['temps']) && !empty($this->settings['temps']['hwmon'])) {

			// Store them here
			$hdmon_vals = array();

			// Wacky location
			foreach ((array) @glob('/sys/class/hwmon/hwmon*/*_label', GLOB_NOSORT) as $path) {

				// Get info here
				$section = rtrim($path, 'label');
				$filename = basename($path);
				$label = getContents($path);
				$value = getContents($section.'input');

				// Determine units and possibly fix values
				if (strpos($filename, 'fan') !== false)
					$unit = 'RPM';
				elseif (strpos($filename, 'temp') !== false) {
					$unit = 'C';  // Always seems to be in celsius
					$value = strlen($value) == 5 ? substr($value, 0, 2) : $value;  // Pointless extra 0's
				}
				elseif (preg_match('/^in\d_label$/', $filename)) {
					$unit = 'v'; 
				}
				else 
					$unit = ''; // Not sure if there's a temp

				// Append values
				$hwmon_vals[] = array(
					'path' => 'N/A',
					'name' => $label,
					'temp' => $value,
					'unit' => $unit
				);
			}
			
			// Save any if we have any
			if (count($hwmon_vals) > 0)
				$return = array_merge($return, $hwmon_vals);
		}

		// Done
		return $return;
	}

	// Get mounts
	private function getMounts() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Mounted file systems');

		// File
		$contents = getContents('/proc/mounts', false);

		// Can't?
		if ($contents == false)
			$this->error->add('Linfo Core', '/proc/mounts does not exist');

		// Parse
		if (@preg_match_all('/^(\S+) (\S+) (\S+) (\S+) \d \d$/m', $contents, $match, PREG_SET_ORDER) === false)
			$this->error->add('Linfo Core', 'Error parsing /proc/mounts');

		// Return these
		$mounts = array();

		// Populate
		foreach($match as $mount) {
			
			// Should we not show this?
			if (in_array($mount[1], $this->settings['hide']['storage_devices']) || in_array($mount[3], $this->settings['hide']['filesystems']))
				continue;
			
			// Spaces and other things in the mount path are escaped C style. Fix that.
			$mount[2] = stripcslashes($mount[2]);
			
			// Get these
			$size = @disk_total_space($mount[2]);
			$free = @disk_free_space($mount[2]);
			$used = $size != false && $free != false ? $size - $free : false;

			// If it's a symlink, find out where it really goes.
			// (using realpath instead of readlink because the former gives absolute paths)
			$symlink = is_link($mount[1]) ? realpath($mount[1]) : false;

			// Might be good, go for it
			$mounts[] = array(
				'device' => $symlink != false ? $symlink : $mount[1],
				'mount' => $mount[2],
				'type' => $mount[3],
				'size' => $size,
				'used' => $used,
				'free' => $free,
				'free_percent' => ((bool)$free != false && (bool)$size != false ? round($free / $size, 2) * 100 : false),
				'used_percent' => ((bool)$used != false && (bool)$size != false ? round($used / $size, 2) * 100 : false)
			);
		}

		// Return
		return $mounts;
	}

	// Get device names
	// TODO optimization. On newer systems this takes only a few fractions of a second,
	// but on older it can take upwards of 5 seconds, since it parses the entire ids files
	// looking for device names which resolve to the pci addresses
	// Also todo: quantity of duplicates. 
	private function getDevs() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Hardware Devices');

		// Return array
		$return = array();

		// Location of useful paths
		$pci_ids = locate_actual_path(array(
			'/usr/share/misc/pci.ids',	// debian/ubuntu
			'/usr/share/pci.ids',		// opensuse
			'/usr/share/hwdata/pci.ids',	// centos. maybe also redhat/fedora
		));
		$usb_ids = locate_actual_path(array(
			'/usr/share/misc/usb.ids',	// debian/ubuntu
			'/usr/share/usb.ids',		// opensuse
			'/usr/share/hwdata/usb.ids',	// centos. maybe also redhat/fedora
		));

		// /sys and /proc are identical across distros
		$sys_pci_dir = '/sys/bus/pci/devices/';
		$sys_usb_dir = '/sys/bus/usb/devices/';

		// Store temporary stuff here
		$pci_dev_id = array();
		$usb_dev_id = array();
		$pci_dev = array();
		$usb_dev = array();
		$pci_dev_num = 0;
		$usb_dev_num = 0;

		// Get all PCI ids
		foreach ((array) @glob($sys_pci_dir.'*/uevent', GLOB_NOSORT) as $path) {

			// Usually fetch vendor/device id out of uevent
			if (is_readable($path) && preg_match('/pci\_(?:subsys_)?id=(\w+):(\w+)/', strtolower(getContents($path)), $match) == 1) {
				$pci_dev_id[$match[1]][$match[2]] = 1;
				$pci_dev_num++;
			}

			// I think only centos forbids read access to uevent and has this instead:
			else {
				$path = dirname($path);
				$vendor = getContents($path.'/subsystem_vendor', false);
				$device = getContents($path.'/subsystem_device', false);
				if ($vendor !== false && $device !== false) {
					$vendor = str_pad(strtoupper(substr($vendor, 2)), 4, '0', STR_PAD_LEFT);
					$device = str_pad(strtoupper(substr($device, 2)), 4, '0', STR_PAD_LEFT);
					$pci_dev_id[$vendor][$device] = 1;
					$pci_dev_num++;
				}
			}
		}

		// Get all USB ids
		foreach ((array) @glob($sys_usb_dir.'*/uevent', GLOB_NOSORT) as $path) {
			if (preg_match('/^product=([^\/]+)\/([^\/]+)\/[^$]+$/m', strtolower(getContents($path)), $match) == 1) {
				$usb_dev_id[str_pad($match[1], 4, '0', STR_PAD_LEFT)][str_pad($match[2], 4, '0', STR_PAD_LEFT)] = 1;
				$usb_dev_num++;
			}
		}

		// Get PCI vendor/dev names
		$file = $pci_ids != false ? @fopen($pci_ids, 'rb') : false;
		$left = $pci_dev_num;
		if ($file !== false) {
			while ($contents = @fgets($file)) {
				if (preg_match('/^(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					$cmid = trim(strtolower($match[1]));
					$cname = trim($match[2]);
				}
				elseif(preg_match('/^	(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					if (array_key_exists($cmid, $pci_dev_id) && is_array($pci_dev_id[$cmid]) && array_key_exists($match[1], $pci_dev_id[$cmid])) {
						$pci_dev[] = array('vendor' => $cname, 'device' => trim($match[2]), 'type' => 'PCI');
						$left--;
					}
				}
				// Potentially save time by not parsing the rest of the file once we have what we need
				if ($left == 0)
					break;
			}
			@fclose($file);
		}

		// Get USB vendor/dev names
		$file = $usb_ids ? @fopen($usb_ids, 'rb') : false;
		$left = $usb_dev_num;
		if ($file !== false) {
			while($contents = @fgets($file)) {
				if (preg_match('/^(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					$cmid = trim(strtolower($match[1]));
					$cname = trim($match[2]);
				}
				elseif(preg_match('/^	(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					if (array_key_exists($cmid, $usb_dev_id) && is_array($usb_dev_id[$cmid]) && array_key_exists($match[1], $usb_dev_id[$cmid])) {
						$usb_dev[] = array('vendor' => $cname, 'device' => trim($match[2]), 'type' => 'USB');
						$left--;
					}
				}
				// Potentially save time by not parsing the rest of the file once we have what we need
				if ($left == 0)
					break;
			}
			@fclose($file);
		}

		// Return it all
		return array_merge($pci_dev, $usb_dev);
	}

	// Get mdadm raid
	// TODO: Maybe support other methods of Linux raid info?
	private function getRAID() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('RAID');
		
		// Store it here
		$raidinfo = array();

		// mdadm?
		if (array_key_exists('mdadm', (array)$this->settings['raid']) && !empty($this->settings['raid']['mdadm'])) {

			// Try getting contents
			$mdadm_contents = getContents('/proc/mdstat', false);

			// No?
			if ($mdadm_contents === false)
				$this->error->add('Linux softraid mdstat parser', '/proc/mdstat does not exist.');

			// Parse
			@preg_match_all('/(\S+)\s*:\s*(\w+)\s*raid(\d+)\s*([\w+\[\d+\] (\(\w\))?]+)\n\s+(\d+) blocks\s*(level \d\, [\w\d]+ chunk\, algorithm \d\s*)?\[(\d\/\d)\] \[([U\_]+)\]/mi', (string) $mdadm_contents, $match, PREG_SET_ORDER);

			// Store them here
			$mdadm_arrays = array();

			// Deal with entries
			foreach ((array) $match as $array) {
				
				// Temporarily store drives here
				$drives = array();

				// Parse drives
				foreach (explode(' ', $array[4]) as $drive) {

					// Parse?
					if(preg_match('/([\w\d]+)\[\d+\](\(\w\))?/', $drive, $match_drive) == 1) {

						// Determine a status other than normal, like if it failed or is a spare
						if (array_key_exists(2, $match_drive)) {
							switch ($match_drive[2]) {
								case '(S)':
									$drive_state = 'spare';
								break;
								case '(F)':
									$drive_state = 'failed';
								break;
								case null:
									$drive_state = 'normal';
								break;

								// I'm not sure if there are status codes other than the above
								default:
									$drive_state = 'unknown';
								break;
							}
						}
						else
							$drive_state = 'normal';

						// Append this drive to the temp drives array
						$drives[] = array(
							'drive' => '/dev/'.$match_drive[1],
							'state' => $drive_state
						);
					}
				}

				// Add record of this array to arrays list
				$mdadm_arrays[] = array(
					'device' => '/dev/'.$array[1],
					'status' => $array[2],
					'level' => $array[3],
					'drives' => $drives,
					'blocks' =>  $array[5],
					'algorithm' => $array[6],
					'count' => $array[7],
					'chart' => $array[8]
				);
			}

			// Append MD arrays to main raidinfo if it's good
			if (is_array($mdadm_arrays) && count($mdadm_arrays) > 0 )
				$raidinfo = array_merge($raidinfo, $mdadm_arrays);
		}

		// Return info
		return $raidinfo;
	}

	// Get load
	private function getLoad() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Load Averages');

		// File that has it
		$file = '/proc/loadavg';

		// Get contents
		$contents = getContents($file, false);

		// ugh
		if ($contents === false) {
			$this->error->add('Linfo Core', '/proc/loadavg unreadable');
			return array();
		}

		// Parts
		$parts = explode(' ', $contents);

		// Return array of info
		return array(
			'now' => $parts[0],
			'5min' => $parts[1],
			'15min' => $parts[2]
		);
	}

	// Get network devices
	private function getNet() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Network Devices');

		// Hold our return values
		$return = array();

		// Use glob to get paths
		$nets = (array) @glob('/sys/class/net/*', GLOB_NOSORT);

		// Get values for each device
		foreach ($nets as $v) {

			// States
			$operstate_contents = getContents($v.'/operstate');
			switch (operstate_contents) {
				case 'down':
				case 'up':
				case 'unknown':
					$state = $operstate_contents;
				break;

				default:
					$state = 'unknown';
				break;
			}

			// Type
			$type_contents = strtoupper(getContents($v.'/device/modalias'));
			list($type) = explode(':', $type_contents, 2);
			$type = $type != 'USB' && $type != 'PCI' ? 'N/A' : $type;
			

			// Save and get info for each
			$return[end(explode('/', $v))] = array(

				// Stats are stored in simple files just containing the number
				'recieved' => array(
					'bytes' => get_int_from_file($v.'/statistics/rx_bytes'),
					'errors' => get_int_from_file($v.'/statistics/rx_errors'),
					'packets' => get_int_from_file($v.'/statistics/rx_packets')
				),
				'sent' => array(
					'bytes' => get_int_from_file($v.'/statistics/tx_bytes'),
					'errors' => get_int_from_file($v.'/statistics/tx_errors'),
					'packets' => get_int_from_file($v.'/statistics/rx_packets')
				),

				// These were determined above
				'state' => $state,
				'type' => $type
			);
		}

		// Return array of info
		return $return;
	}

	// Useful for things like laptops. I think this might also work for UPS's, but I'm not sure.
	private function getBattery() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Batteries');
		
		// Return values
		$return = array();

		// Here they should be
		$bats = (array) @glob('/sys/class/power_supply/BAT*', GLOB_NOSORT);
	
		// Get vals for each battery
		foreach ($bats as $b) {

			// Get these from the simple text files
			$charge_full = get_int_from_file($b.'/charge_full');
			$charge_now = get_int_from_file($b.'/charge_now');

			// Save result set
			$return[] = array(
				'charge_full' => $charge_full,
				'charge_now' => $charge_now,
				'percentage' => ($charge_now != 0 && $charge_full != 0 ? (round($charge_now / $charge_full, 4) * 100) : '?').'%',
				'device' => getContents($b.'/manufacturer') . ' ' . getContents($b.'/model_name', 'Unknown'),
				'state' => getContents($b.'/status', 'Unknown')
			);
		}

		// Give it
		return $return;
	}

	// Again useful probably only for things like laptops. Get status on wifi adapters
	// Parses it successfully, yes. But what should I use this info for? idk
	// And also, I'm not sure how to interpret the status value
	private function getWifi() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Wifi');

		// Return these
		$return = array();

		// In here
		$contents = getContents('/proc/self/net/wireless');

		// Oi
		if ($contents == false) {
			$this->error->add('Linux WiFi info parser', '/proc/self/net/wireless does not exist');
			return $return;
		}

		// Parse
		@preg_match_all('/^ (\S+)\:\s*(\d+)\s*(\S+)\s*(\S+)\s*(\S+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*$/m', $contents, $match, PREG_SET_ORDER);
		
		// Match
		foreach ($match as $wlan) {
			$return[] = array(
				'device' => $wlan[1],
				'status' => $wlan[2],
				'quality_link' => $wlan[3],
				'quality_level' => $wlan[4],
				'quality_noise' => $wlan[5],
				'dis_nwid' => $wlan[6],
				'dis_crypt' => $wlan[7],
				'dis_frag' => $wlan[8],
				'dis_retry' => $wlan[9],
				'dis_misc' => $wlan[10],
				'mis_beac' => $wlan[11]
			);
		}

		// Done
		return $return;
	}

	// Yet something else that has no business being enabled on a server system
	// Sound card stuff
	private function getSoundCards() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Sound cards');

		// This should be it
		$file = '/proc/asound/cards';

		// eh?
		if (!is_file($file)) {
			$this->error->add('Linux sound card detector', '/proc/asound/cards does not exist');
		}

		// Get contents and parse
		$contents = getContents($file);

		// Parse
		if (preg_match_all('/^\s*(\d+)\s\[[\s\w]+\]:\s(.+)$/m', $contents, $matches, PREG_SET_ORDER) == 0)
			return array();

		// eh?
		$cards = array();

		// Deal with results
		foreach ($matches as $card)	
			$cards[] = array(
				'number' => $card[1],
				'card' => $card[2],
			);

		// Give cards
		return $cards;
	}

	// Get stats on processes
	// todo: merge state and thread regexes into one, which might be possible
	private function getProcessStats() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Process Stats');

		// We'll return this after stuffing it with useful info
		$result = array(
			'exists' => true, 
			'totals' => array(
				'running' => 0,
				'zombie' => 0,
				'sleeping' => 0,
				'stopped' => 0,
			),
			'proc_total' => 0,
			'threads' => 0
		);
		
		// Get all the paths to each process' status file
		$processes = (array) @glob('/proc/*/status', GLOB_NOSORT);

		// Total
		$result['proc_total'] = count($processes);

		// Go through each
		foreach ($processes as $status_path) {

			// Don't waste time if we can't use it
			if (!is_readable($status_path))
				continue;
			
			// Get that file's contents
			$status_contents = getContents($status_path);

			// Try getting state
			@preg_match('/^State:\s+(\w)/m', $status_contents, $state_match);

			// Well? Determine state
			switch ($state_match[1]) {
				case 'D': // disk sleep? wtf?
				case 'S':
					$result['totals']['sleeping']++;
				break;
				case 'Z':
					$result['totals']['zombie']++;
				break;
				case 'R':
					$result['totals']['running']++;
				break;
				case 'T':
					$result['totals']['stopped']++;
				break;
			}

			// Try getting number of threads
			@preg_match('/^Threads:\s+(\d+)/m', $status_contents, $threads_match);

			// Well?
			if ($threads_match)
				list(, $threads) = $threads_match;

			// Append it on if it's good
			if (is_numeric($threads))
				$result['threads'] = $result['threads'] + $threads;
		}

		// Give off result
		return $result;
	}
}
