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
 */

class OS_Linux {

	// Keep these tucked away
	protected
		$settings;

	// Start us off
	public function __construct($settings) {

		// Localize settings
		$this->settings = $settings;

		// Make sure we have what we need
		if (!is_dir('/sys') || !is_dir('/proc')) {
			throw new GetInfoException('This needs access to /proc and /sys to work.');
		}

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
		);
	}

	// Return OS type
	private function getOS() {
		return 'Linux';
	}

	// Get linux kernel version
	private function getKernel() {

		// File containing info
		$file = '/proc/version';

		// Make sure we can use it
		if (!is_file($file) || !is_readable($file))
			return 'Unknown';

		// Get it
		$contents = getContents($file);

		// Parse it
		@preg_match('/^Linux version (\S+).+$/', $contents, $match);

		return $match[1] ? $match[1] : 'Unknown';
	}

	// Get host name
	private function getHostName() {

		// File containing info
		$file = '/proc/sys/kernel/hostname';
		
		// Return it
		return getContents($file, 'Unknown');
	}

	// Get ram usage/amount/types
	private function getRam(){

		// We'll return the contents of this
		$tmpInfo = array();

		// Files containing juicy info
		$procFileSwap = '/proc/swaps';
		$procFileMem = '/proc/meminfo';

		// First off, these need to exist..
		if (!is_readable($procFileSwap) || !is_readable($procFileMem))
			return array();

		// To hold their values
		$memVals = array();
		$swapVals = array();

		// Get contents of both
		$memContents = getContents($procFileMem);
		$swapContents = getContents($procFileSwap);

		// Get memContents
		@preg_match_all('/^([^:]+)\:\s+(\d+)\s*([kb|KB])\s*?/m', $memContents, $matches, PREG_OFFSET_CAPTURE);

		// Deal with it
		foreach ((array)$matches[1] as $k => $v)
			$memVals[$v[0]] = $matches[2][$k][0];

		// Get swapContents
		@preg_match_all('/(\S+)\s+(\w+)\s+(\d+)\s(\d+)/i', $swapContents, $matches);
		foreach ((array)$matches[0] as $k => $v)
			$swapVals[] = array (
				'device' => $matches[1][$k],
				'type' => $matches[2][$k],
				'size' => $matches[3][$k],
				'used' => $matches[4][$k]
			);

		// Get individual vals
		$tmpInfo['total'] = $memVals['MemTotal']*1024;
		$tmpInfo['free'] = $memVals['MemFree']*1024;
		$tmpInfo['swapTotal'] = $memVals['SwapTotal']*1024;
		$tmpInfo['swapFree'] = $memVals['SwapFree']*1024;
		$tmpInfo['swapCached'] = $memVals['SwapCached']*1024;
		$tmpInfo['swapInfo'] = $swapVals;

		// Return it
		return $tmpInfo;

	}

	// Get processor info
	private function getCPU() {

		// File that has it
		$file = '/proc/cpuinfo';

		// Not there?
		if (!is_file($file) || !is_readable($file))
			return array();

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

		// Get contents
		$contents = getContents('/proc/uptime', false);

		// eh?
		if ($contents == false)
			return 'Unknown';

		// Seconds
		list($seconds) = explode(' ', $contents, 1);

		// Get it textual, as in days/minutes/hours/etc
		return seconds_convert(ceil($seconds));
	}

	// Get disk drives
	// TODO: Possibly more information?
	private function getHD() {

		$return = array();

		foreach((array)@glob('/sys/block/*/device/model') as $path) {
			$dirname = dirname(dirname($path));
			$parts = explode('/', $path);
			$return[] = array(
				'name' => getContents($path),
				'device' => '/dev/'.$parts[3]
			);
		}

		return $return;
	}

	// Get temps/voltages
	private function getTemps() {
		
		// Hold them here
		$return = array();

		// hddtemp?
		if (array_key_exists('hddtemp', $this->settings['temps']) && !empty($this->settings['temps']['hddtemp'])) {
			try {
				$hddtemp = new GetHddTemp;
				$hddtemp->setMode($this->settings['hddtemp']['mode']);
				if ($this->settings['hddtemp']['mode'] == 'daemon') {
					$hddtemp->setAddress(
						$this->settings['hddtemp']['address']['host'],
						$this->settings['hddtemp']['address']['port']);
				}
				$hddtemp_res = $hddtemp->work();
				if (is_array($hddtemp_res))
					$return = array_merge($return, $hddtemp_res);

			}
			catch (GetHddTempException $e) {
				// Current lack of error handling
			}
		}

		// mbmon?
		if (array_key_exists('mbmon', $this->settings['temps']) && !empty($this->settings['temps']['mbmon'])) {
			try {
				$mbmon = new GetMbMon;
				$mbmon->setAddress(
					$this->settings['mbmon']['address']['host'],
					$this->settings['mbmon']['address']['port']);
				$mbmon_res = $mbmon->work();
				if (is_array($mbmon_res))
					$return = array_merge($return, $mbmon_res);
			}
			catch (GetMbMonException $e) {
				// Current lack of error handling
			}
		}

		// Done
		return $return;
	}

	// Get mounts
	private function getMounts() {

		// File
		$contents = getContents('/proc/mounts');

		// Parse
		@preg_match_all('/^(\S+) (\S+) (\S+) (\S+) \d \d$/m', $contents, $match, PREG_SET_ORDER);

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
				'free_percent' => ($free !== false && $size !== false ? round($free / $size, 2) * 100 : false),
				'used_percent' => ($used !== false && $size !== false ? round($used / $size, 2) * 100 : false)
			);
		}

		// Return
		return $mounts;
	}

	// Get device names
	// TODO optimization. On newer systems this takes only a few fractions of a second,
	// but on older it can take upwards of 5 seconds, since it parses the entire ids files
	// looking for device names which resolve to the pci addresses
	private function getDevs() {

		// Return array
		$return = array();

		// Location of useful paths
		$pci_ids = '/usr/share/misc/pci.ids';
		$usb_ids = '/usr/share/misc/usb.ids';
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
		foreach ((array) @glob($sys_pci_dir.'*/uevent') as $path) {
			if (preg_match('/[pci_id|pci_subsys_id]=(\w+):(\w+)/', strtolower(getContents($path)), $match) == 1) {
				$pci_dev_id[$match[1]][$match[2]] = 1;
				$pci_dev_num++;
			}
		}

		// Get all USB ids
		foreach ((array) @glob($sys_usb_dir.'*/uevent') as $path) {
			if (preg_match('/^product=([^\/]+)\/([^\/]+)\/[^$]+$/m', strtolower(getContents($path)), $match) == 1) {
				$usb_dev_id[str_pad($match[1], 4, '0', STR_PAD_LEFT)][str_pad($match[2], 4, '0', STR_PAD_LEFT)] = 1;
				$usb_dev_num++;
			}
		}

		// Get PCI vendor/dev names
		$file = @fopen($pci_ids, 'rb');
		$left = $pci_dev_num;
		if ($f !== false) {
			for ($line = 0; $contents = fgets($file); $line++) {
				if (preg_match('/^(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					$cmid = trim(strtolower($match[1]));
					$cname = $match[2];
				}
				elseif(preg_match('/^	(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					if (array_key_exists($cmid, $pci_dev_id) && is_array($pci_dev_id[$cmid]) && array_key_exists($match[1], $pci_dev_id[$cmid])) {
						$pci_dev[] = array('vendor' => $cname, 'device' => $match[2], 'type' => 'PCI');
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
		$file = @fopen($usb_ids, 'rb');
		$left = $usb_dev_num;
		if ($file !== false) {
			for ($line = 0; $contents = fgets($file); $line++) {
				if (preg_match('/^(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					$cmid = trim(strtolower($match[1]));
					$cname = $match[2];
				}
				elseif(preg_match('/^	(\S{4})  ([^$]+)$/', $contents, $match) == 1) {
					if (array_key_exists($cmid, $usb_dev_id) && is_array($usb_dev_id[$cmid]) && array_key_exists($match[1], $usb_dev_id[$cmid])) {
						$usb_dev[] = array('vendor' => $cname, 'device' => $match[2], 'type' => 'USB');
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
		
		// Store it here
		$raidinfo = array();

		// mdadm?
		if (array_key_exists('mdadm', $this->settings['raid']) && !empty($this->settings['raid']['mdadm'])) {

			// Try getting contents
			$mdadm_contents = getContents('/proc/mdstat');

			// Parse
			@preg_match_all('/(\S+)\s*:\s*(\w+)\s*raid(\d+)\s*([\w+\[\d+\] (\(\w\))?]+)\n\s+(\d+) blocks\s*(level \d\, [\w\d]+ chunk\, algorithm \d\s*)?\[(\d\/\d)\] \[([U\_]+)\]/mi', $mdadm_contents, $match, PREG_SET_ORDER);

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

		// File that has it
		$file = '/proc/loadavg';

		// Get contents
		$contents = getContents($file);

		// ugh
		if ($contents == '')
			return array();

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

		// Hold our return values
		$return = array();

		// Use glob to get paths
		$nets = (array) @glob('/sys/class/net/*');

		// Get values for each device
		foreach ($nets as $v) {

			// Save and get info for each
			$return[end(explode('/', $v))] = array(
				'recieved' => array(
					'bytes' => get_int_from_file($v.'/statistics/rx_bytes'),
					'errors' => get_int_from_file($v.'/statistics/rx_errors'),
					'packets' => get_int_from_file($v.'/statistics/rx_packets')
				),
				'sent' => array(
					'bytes' => get_int_from_file($v.'/statistics/tx_bytes'),
					'errors' => get_int_from_file($v.'/statistics/tx_errors'),
					'packets' => get_int_from_file($v.'/statistics/rx_packets')
				)
			);
		}

		// Return array of info
		return $return;
	}

	// Useful for things like laptops. I think this might also work for UPS's, but I'm not sure.
	private function getBattery() {
		
		// Return values
		$return = array();

		// Here they should be
		$bats = (array) @glob('/sys/class/power_supply/BAT*');
	
		// Get vals for each battery
		foreach ($bats as $b) {
			$charge_full = get_int_from_file($b.'/charge_full');
			$charge_now = get_int_from_file($b.'/charge_now');
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
	private function getWifi() {

		// Return these
		$return = array();

		// In here
		$contents = getContents('/proc/self/net/wireless');

		// Oi
		if ($contents == false)
			return $return;

		// Parse
		@preg_match_all('/^ ([a-zA-Z0-9]+)\:\s*(\d+)\s*([\d\.\-]+)\s*([\d\.\-]+)\s*([\d\.\-]+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*(\d+)\s*$/m', $contents, $match, PREG_SET_ORDER);
		
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
		return $contents;
	}
}

