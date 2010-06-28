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
 * Works by totally looking around /proc, mostly
 */
class LinuxInfo {

	// Keep these tucked away
	protected
		$have = array(),
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
			'OS' => !(bool) $this->settings['show']['os'] ? '' : $this->getOS(),
			'Kernel' => !(bool) $this->settings['show']['kernel'] ? '' : $this->getKernel(),
			'RAM' => !(bool) $this->settings['show']['ram'] ? array() : $this->getRam(),
			'HD' => !(bool) $this->settings['show']['hd'] ? '' : $this->getHD(),
			'Mounts' => !(bool) $this->settings['show']['mounts'] ? array() : $this->getMounts(),
			'Load' => !(bool) $this->settings['show']['load'] ? array() : $this->getLoad(),
			'HostName' => !(bool) $this->settings['show']['hostname'] ? '' : $this->getHostName(),
			'UpTime' => !(bool) $this->settings['show']['uptime'] ? '' : $this->getUpTime(),
			'CPU' => !(bool) $this->settings['show']['cpu'] ? array() : $this->getCPU(),
			'Network Devices' => !(bool) $this->settings['show']['network'] ? array() : $this->getNet(),
			'Devices' => !(bool) $this->settings['show']['devices'] ? array() : $this->getDevs(),
			'Temps' => !(bool) $this->settings['show']['temps'] ? array(): $this->getTemps()
		);
	}

	// Return OS version
	public function getOS() {
		return 'Linux';
	}

	// Get linux kernel version
	public function getKernel(){

		// File containing info
		$file = '/proc/version';

		// Make sure we can use it
		if (!is_file($file) || !is_readable($file))
			return array();

		// Get it
		$contents = trim(@file_get_contents($file));

		// Parse it
		@preg_match('/^Linux version ([^\s]+).+$/', $contents, $m);

		return $m[1];
	}

	// Get host name
	public function getHostName() {

		// File containing info
		$file = '/proc/sys/kernel/hostname';

		// Make sure we can use it
		if (!is_file($file) || !is_readable($file))
			return '';

		// Get it
		$contents = trim(@file_get_contents($file));

		// Return it
		return $contents;
	}

	// Get ram usage/amount/types
	public function getRam(){

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
		$memContents = trim(@file_get_contents($procFileMem));
		$swapContents = trim(@file_get_contents($procFileSwap));

		// Get memContents
		@preg_match_all('/^(\w+)\:\s+(\d+)\s*(kb)\s*?/mi', $memContents, $matches, PREG_OFFSET_CAPTURE);

		// Deal with it
		foreach ((array)$matches[1] as $k => $v)
			$memVals[$v[0]] = $matches[2][$k][0];

		// Get swapContents
		@preg_match_all('/([^\s]+)\s+(\w+)\s+(\d+)\s(\d+)/i', $swapContents, $matches);
		foreach ((array)$matches[0] as $k => $v)
			$swapVals[] = array(
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
	public function getCPU() {

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

		// Go through lines in file
		foreach ($lines as $num => $line) {

			// No current cpu yet? Make a holder for one
			if (!is_array($cur_cpu))
				$cur_cpu = array();

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
	public function getUpTime () {

		// File that has it
		$file = '/proc/uptime';

		// Not there?
		if (!is_file($file) || !is_readable($file))
			return false;

		// Get contents
		$contents = trim(@file_get_contents($file));

		// Parts
		$parts = explode(' ', $contents);

		// Seconds of uptime, floor high
		$seconds = ceil($parts[0]);

		// Get it textual, as in days/minutes/hours/etc
		return seconds_convert($seconds);
	}

	// Get hard drives
	// DONE
	// Retrieving more info on the hard drives would be good, though.
	// TODO: Somehow make this ignore optical drives (or list that as a feature :P)
	public function getHD(){

		$return = array();

		foreach((array)@glob('/sys/block/*/device/model') as $path) {
			$dirname = dirname(dirname($path));
			$parts = explode('/', $path);
			$dev = '/dev/'.$parts[3];
			$model = trim(@file_get_contents($path));
			$return[$dev] = $model;
		}

		return $return;

	}

	// Get temps/voltages
	public function getTemps(){

		// Method of getting temps
		if (is_string($this->settings['options']['temps'])) {
			switch ($this->settings['options']['temps']) {

				// Only hddtemp is support as of now
				case 'hddtemp':

					switch ($this->settings['hddtemp']['mode']) {

						case 'daemon':
							try {
								$hddt = new GetHddTemp;
								$hddt->setMode('daemon');
								$hddt->setAddress(
									$this->settings['hddtemp']['address']['host'],
									$this->settings['hddtemp']['address']['port']
									);

								return $hddt->work();

							}
							catch (GetHddTempException $e) {
								return array();
							}
						break;

						case 'syslog':
							try {
								$hddt = new GetHddTemp;
								$hddt->setMode('syslog');
								return $hddt->work();
							}
							catch (GetHddTempException $e) {
								return array();
							}
						break;


						// Anything else gets an empty array
						default:
							return array();
						break;
					}
				break;

				// Mbmon
				case 'mbmon':
					try {
						$hddt = new GetMbMon;
						$hddt->setAddress(
							$this->settings['mbmon']['address']['host'],
							$this->settings['mbmon']['address']['port']
							);

						return $hddt->work();

					}
					catch (GetMbMonException $e) {
						return array();
					}
				break;

				// Anything else gets an empty array
				default:
					return array();
				break;
			}
		}

		// Do more than one?
		elseif(is_array($this->settings['options']['temps'])) {

			// To hold temps to return
			$return = array();

			// Do mbmon
			if (in_array('mbmon', $this->settings['options']['temps'])) {
				try {
					$hddt = new GetMbMon;
					$hddt->setAddress(
						$this->settings['mbmon']['address']['host'],
						$this->settings['mbmon']['address']['port']
						);

					$mbmresult = $hddt->work();

				}
				catch (GetMbMonException $e) {
					$mbmresult = array();
				}
			}

			// Save it
			if (is_array($mbmresult))
				$return = array_merge($return, $mbmresult);

			// Do hddtemp
			if (in_array('hddtemp', $this->settings['options']['temps'])) {
				switch ($this->settings['hddtemp']['mode']) {

					case 'daemon':
						try {
							$hddt = new GetHddTemp;
							$hddt->setMode('daemon');
							$hddt->setAddress(
								$this->settings['hddtemp']['address']['host'],
								$this->settings['hddtemp']['address']['port']
								);

							$hddtresult = $hddt->work();

						}
						catch (GetHddTempException $e) {
							return array();
						}
					break;

					case 'syslog':
						try {
							$hddt = new GetHddTemp;
							$hddt->setMode('syslog');
							$hddtresult = $hddt->work();
						}
						catch (GetHddTempException $e) {
							return array();
						}
					break;


					// Anything else gets an empty array
					default:
						$hddtresult = array();
					break;
				}
			}

			// Save it
			if (is_array($hddtresult))
				$return = array_merge($return, $hddtresult);


			// Return temps
			return $return;

		}
		// Lolwhut?
		else {
				return array();
		}
	}

	// Get mounts
	public function getMounts(){

		// File that has it
		$file = '/proc/mounts';

		// Not there?
		if (!is_file($file) || !is_readable($file))
			return false;

		// Get contents
		$contents = trim(@file_get_contents($file));

		// Parse it
		$lines = explode("\n", $contents);

		// Mounts
		$mounts = array();

		// Each line
		foreach ($lines as $line) {
			// The parts
			$parts = explode(' ', trim($line));

			// Should we not show this?
			if ($parts[0] == 'none' || in_array($parts[2], $this->settings['hide']['filesystems']))
				continue;
			if (in_array($parts[0], $this->settings['hide']['storage_devices']))
				continue;

			// Get these
			$size = @disk_total_space($parts[1]);
			$free = @disk_free_space($parts[1]);

			$realpath = $parts[2] != 'nfs' ? realpath($parts[0]) : false;

			// Might be good, go for it
			$mounts[] = array(
				'device' => $realpath ? $realpath : $parts[0],
				'mount' => $parts[1],
				'type' => $parts[2],
				'size' => $size ,
				'used' => $size - $free,
				'free' => $free
			);
		}

		// Return them
		return $mounts;
	}

	// Get device names
	// DONE.
	// TODO optimization
	public function getDevs(){

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
			$contents = (string) @file_get_contents($path);
			if (preg_match('/[PCI_ID|PCI_SUBSYS_ID]=([a-z0-9]+):(.+)\n/i', $contents, $m) == 1) {
				$pci_dev_id[strtolower($m[1])][strtolower($m[2])] = 1;
				$pci_dev_num++;
			}
		}

		// Get all USB ids
		foreach ((array) @glob($sys_usb_dir.'*/uevent') as $path) {
			$contents = (string) @file_get_contents($path);
			if (preg_match('/PRODUCT=(.+)\/(.+)\/.+\n/i', $contents, $m) == 1) {
				$usb_dev_id[str_pad(strtolower($m[1]), 4, '0', STR_PAD_LEFT)][str_pad(strtolower($m[2]), 4, '0', STR_PAD_LEFT)] = 1;
				$usb_dev_num++;
			}
		}

		// Get PCI vendor/dev names
		$f = @fopen($pci_ids, 'rb');
		$left = $pci_dev_num;
		if ($f !== FALSE) {
			for ($line = 0; $contents = fgets($f); $line++) {
				$contents = rtrim($contents);
				if (preg_match('/^([a-z0-9]{4})  (.+)/i', $contents, $m) == 1) {
					$cmid = trim(strtolower($m[1]));
					$cname = $m[2];
				}
				elseif(preg_match('/^	([a-z0-9]{4})  (.+)/i', $contents, $m) == 1) {
					if (array_key_exists($cmid, $pci_dev_id) && is_array($pci_dev_id[$cmid]) && array_key_exists($m[1], $pci_dev_id[$cmid])) {
						$pci_dev[] = array('vendor' => $cname, 'device' => $m[2], 'type' => 'PCI');
						$left--;
					}
				}
				if ($left == 0)
					break;
			}
			@fclose($f);
		}

		// Get USB vendor/dev names
		$f = @fopen($usb_ids, 'rb');
		$left = $usb_dev_num;
		if ($f !== FALSE) {
			for ($line = 0; $contents = fgets($f); $line++) {
				$contents = rtrim($contents);
				if (preg_match('/^([a-z0-9]{4})  (.+)/i', $contents, $m) == 1) {
					$cmid = trim(strtolower($m[1]));
					$cname = $m[2];
				}
				elseif(preg_match('/^	([a-z0-9]{4})  (.+)/i', $contents, $m) == 1) {
					if (array_key_exists($cmid, $usb_dev_id) && is_array($usb_dev_id[$cmid]) && array_key_exists($m[1], $usb_dev_id[$cmid])) {
						$usb_dev[] = array('vendor' => $cname, 'device' => $m[2], 'type' => 'USB');
						$left--;
					}
				}
				if ($left == 0)
					break;
			}
			@fclose($f);
		}

		// Return it all
		return array_merge($pci_dev, $usb_dev);
	}

	// Get mdadm raid
	// TODO - finish. And maybe support other Linux software raids?
	public function getRaid() {

		// Firstly, are we allowed?
		if (in_array('raid', $this->settings['show']) && !(bool) $this->settings['show']['raid'])
			return array();

		// Store it here
		$raidinfo = array();

		// Decide what
		switch ($this->settings['raid_type']) {

			case 'mdadm':

				// File needed
				$file = '/proc/mdstat';

				// Is it ok?
				if (!is_file($file) || !is_readable($file))
					return false;

				// Get contents
				$contents = trim(@file_get_contents($file));

				// Regex for parsing
				@preg_match_all(
					'/(?<name>md\d+)\s+\:\s+(?<state>\w+)\s+(?<personality>raid\d+)\s+(?<devices>\w+\[.+\].*)+\n'.
					'\s+(?<blocks>\d+)\s+blocks\s+(level (?<level>\d+)\, (?<chunk>\w+) chunk, algorithm '.
					'(?<algorithm>\d+)\s+)?\[(?<active>\d+\/\d+)\]\s+\[(?<drives>\w+)\]/i'
					, $contents, $matches, PREG_SET_ORDER);

				// Well?
				//print_r($matches);

				// Debug
				//exit;

				$raidinfo = $matches;

			break;
		}

		// Return info
		return $raidinfo;
	}

	// Get load
	public function getLoad(){

		// File that has it
		$file = '/proc/loadavg';

		// Is it ok?
		if (!is_file($file) || !is_readable($file))
			return false;

		// Get contents
		$contents = trim(@file_get_contents($file));

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
	public function getNet() {

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
}

