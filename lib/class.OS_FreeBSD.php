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
 * Mostly complete FreeBSD info class.
 *
 * Note: When Linux compatibility is enabled and /proc is mounted, it only
 * contains process info; none of the hardware/system/network status that Linux /proc has.
 */

class OS_FreeBSD {
	
	// Encapsulate these
	protected
		$settings,
		$exec,
		$error;

	// Start us off
	public function __construct($settings) {
		
		// Localize settings
		$this->settings = $settings;
		
		// Localize error handler
		$this->error = LinfoError::Fledging();

		// Start our external executable executing stuff
		$this->exec = new CallExt;
		$this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
		
		// Used enough times to just call it once, here
		$this->bootLog = getContents('/var/run/dmesg.boot');
	}
	
	// This function will likely be shared among all the info classes
	public function getAll() {

		// Return everything, whilst obeying display permissions
		return array(
			'OS' => empty($this->settings['show']) ? '' : $this->getOS(), 			# done
			'Kernel' => empty($this->settings['show']) ? '' : $this->getKernel(), 		# done
			'HostName' => empty($this->settings['show']) ? '' : $this->getHostName(), 	# done
			'Mounts' => empty($this->settings['show']) ? array() : $this->getMounts(), 	# done
			'RAM' => empty($this->settings['show']) ? array() : $this->getRam(), 		# done
			'Load' => empty($this->settings['show']) ? array() : $this->getLoad(), 		# done
			'Devices' => empty($this->settings['show']) ? array() : $this->getDevs(), 	# done
			'HD' => empty($this->settings['show']) ? '' : $this->getHD(), 			# done
			'UpTime' => empty($this->settings['show']) ? '' : $this->getUpTime(), 		# done
			'RAID' => empty($this->settings['show']) ? '' : $this->getRAID(),	 	# done (gmirror only)
			'Network Devices' => empty($this->settings['show']) ? array() : $this->getNet(),# done (names only)
			'Battery' => empty($this->settings['show']) ? array(): $this->getBattery(),  	# works
			'CPU' => empty($this->settings['show']) ? array() : $this->getCPU(), 		# works
			'Temps' => empty($this->settings['show']) ? array(): $this->getTemps(), 	# TODO
		);
	}

	// Return OS type
	private function getOS() {
		return 'FreeBSD';	
	}
	
	// Get kernel version
	private function getKernel() {
		
		// hmm. PHP has a native function for this
		return php_uname('r');
	}

	// Get host name
	private function getHostName() {
		
		// Take advantage of that function again
		return php_uname('n');
	}

	// Get mounted file systems
	private function getMounts() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Mounted file systems');
		
		// Get result of mount command
		try {
			$res = $this->exec->exec('mount');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `mount` command');
			return array();
		}
		
		// Parse it
		if (preg_match_all('/(.+)\s+on\s+(.+)\s+\((\w+).*\)\n/i', $res, $m, PREG_SET_ORDER) == 0)
			return array();
		
		// Store them here
		$mounts = array();
		
		// Deal with each entry
		foreach ($m as $mount) {

			// Should we not show this?
			if (in_array($mount[1], $this->settings['hide']['storage_devices']) || in_array($mount[3], $this->settings['hide']['filesystems']))
				continue;
			
			// Get these
			$size = @disk_total_space($mount[2]);
			$free = @disk_free_space($mount[2]);
			$used = $size - $free;
			
			// Might be good, go for it
			$mounts[] = array(
				'device' => $mount[1],
				'mount' => $mount[2],
				'type' => $mount[3],
				'size' => $size ,
				'used' => $used,
				'free' => $free,
				'free_percent' => ((bool)$free != false && (bool)$size != false ? round($free / $size, 2) * 100 : false),
				'used_percent' => ((bool)$used != false && (bool)$size != false ? round($used / $size, 2) * 100 : false)

			);
		}

		// Give it
		return $mounts;
	}

	// Get ram usage
	private function getRam(){
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Memory');
		
		// Use sysctl to get ram usage
		try {
			$sys = $this->exec->exec('sysctl', 'vm.vmtotal');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using sysctl to get ram usage');
			return array();
		}
		
		// We'll return the contents of this
		$tmpInfo = array();

		// Start us off at zilch
		$tmpInfo['total'] = 0;
		$tmpInfo['free'] = 0;
		$tmpInfo['swapTotal'] = 0;
		$tmpInfo['swapFree'] = 0;
		$tmpInfo['swapInfo'] = array();

		// Parse the file
		if (!preg_match_all('/([a-z\ ]+):\s*\(Total: (\d+)\w,? Active:? (\d+)\w\)\n/i', $sys, $rm, PREG_SET_ORDER))
			return $tmpInfo;

		// Parse each entry	
		foreach ($rm as $r) {
			switch ($r[1]) {

				case 'Real Memory':
					$tmpInfo['total'] = $r[2]  * 1024;
					$tmpInfo['free'] = ($r[2] - $r[3]) * 1024;
				break;
			}
		}
		
		// Swap info
		try {
			$swapinfo = $this->exec->exec('swapinfo', '-k');
			// Parse swap info
			@preg_match_all('/^(\S+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $swapinfo, $sm, PREG_SET_ORDER);
			foreach ($sm as $swap) {
				$tmpInfo['swapTotal'] += $swap[2]*1024;
				$tmpInfo['swapFree'] += (($swap[2] - $swap[3])*1024);
				$ft = @filetype($swap[1]); // TODO: I'd rather it be Partition or File
				$tmpInfo['swapInfo'][] = array(
					'device' => $swap[1],
					'size' => $swap[2]*1024,
					'used' => $swap[3]*1024,
					'type' => ucfirst($ft) 
				);
			}
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `swapinfo` to get swap usage');
			// meh
		}

		// Return it
		return $tmpInfo;
	}
	
	// Get system load
	private function getLoad() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Load Averages');
		
		// Use uptime, since it also has load values
		try {
			$res = $this->exec->exec('uptime');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `uptime` to get system load');
			return array();
		}

		// Parse it
		if (preg_match('/^.+load averages: ([\d\.]+), ([\d\.]+), ([\d\.]+)$/', $res, $m) == 0)
			return array();
		
		// Give
		return array(
			'now' => $m[1],
			'5min' => $m[2],
			'15min' => $m[3]
		);
	
	}
	
	// Get uptime
	private function getUpTime() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Uptime');
		
		// Use sysctl to get unix timestamp of boot. Very elegant!
		try {
			$res = $this->exec->exec('sysctl', 'kern.boottime');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using sysctl to get boot time');
			return '';
		}

		// Extract boot part of it
		if (preg_match('/^kern.boottime\: \{ sec \= (\d+).+$/', $res, $m) == 0)
			return '';
		
		// Boot unix timestamp
		$booted = $m[1];

		// Get it textual, as in days/minutes/hours/etc
		return seconds_convert(time() - $booted) . '; booted ' . date('m/d/y h:i A', $booted);
	}

	// RAID Stats
	private function getRAID() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('RAID');
		
		// Store raid arrays here
		$return = array();

		// Counter for each raid array
		$i = 0;
		
		// Gmirror?
		if (array_key_exists('gmirror', $this->settings['raid']) && !empty($this->settings['raid']['gmirror'])) {
			
			try {
				// Run gmirror status program to get raid array status
				$res = $this->exec->exec('gmirror', 'status');

				// Divide that into lines
				$lines = explode("\n", $res);

				// First is worthless
				unset ($lines[0]);

				// Parse the remaining ones
				foreach ($lines as $line => $content) {
					
					// Hitting a new raid definition
					if (preg_match('/^(\w+)\/(\w+)\s+(\w+)\s+(\w+)$/', $content, $m)) {
						$i++;
						$return[$i] = array(
							'device' => $m[2],
							'level' => $m[1],
							'status' => $m[3],
							'drives' => array($m[4]),
							'blocks' => 'unknown',
							'count' => '?/?'
						);
					}

					// Hitting a new device in a raid definition
					elseif (preg_match('/^                      (\w+)$/', $content, $m)) {
						if (array_key_exists($i, $info))
							$return[$i]['devices'][] = array('drive' => $m[1], 'state' => 'unknown');
					}
				}
			}
			catch (CallExtException $e) {
				$this->error->add('RAID', 'Error using gmirror to get raid info');
				// Don't jump out; allow potential more raid array
				// mechanisms to be gathered and outputted
			}
		}
		
		// Give off raid info
		return $return;
	}

	// So far just gets interface names :-/
	private function getNet() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Network Devices');

		// Store return vals here
		$return = array();
		
		// Use netstat to get info
		try {
			$netstat = $this->exec->exec('netstat', '-nbdi');
		}
		catch(CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `netstat` to get network info');
			return $return;
		}
		
		// Initially get interfaces themselves along with numerical stats
		if (preg_match_all('/^(\w+\w)\s*\w+\s+<Link\#\w+>(?:\D+|\s+\w+:\w+:\w+:\w+:\w+:\w+\s+)(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+/m', $netstat, $m, PREG_SET_ORDER) == 0)
			return $return;


		// Save info
		foreach ($m as $net)
			$return[$net[1]] = array(
				
				// Not sure how to get this stuff on freebsd
				'recieved' => array(
					'bytes' => $net[4],
					'errors' => $net[3],
					'packets' => $net[2] 
				),
				'sent' => array(
					'bytes' => $net[7],
					'errors' =>  $net[6],
					'packets' => $net[5] 
				),
				'state' => '?',
				'type' => '?'
			);

		// Return it
		return $return;
	}

	// Get CPU's
	// I still don't really like how this is done
	private function getCPU() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('CPUs');

		// Store them here
		$cpus = array();
		
		// Get cpu type
		if (preg_match('/^CPU: ([^(]+) \(([\d\.]+)\-MHz.+\).*\n\s+Origin = "(\w+)"/m', $this->bootLog, $cpu_m) == 0)
			return $cpus;
		
		// I don't like how this is done. It implies that if you have more than one CPU they're all identical
		$num = preg_match('/^FreeBSD\/SMP\: Multiprocessor System Detected\: (\d+) CPUs/m', $contents, $num_m) ? $num_m[1] : 1;	
		
		// Stuff it with identical cpus
		for ($i = 1; $i <= $num; $i++)
			$cpus[] = array(
				'Model' => $cpu_m[1],
				'MHz' => $cpu_m[2],
				'Vendor' => $cpu_m[3]
			);
		
		// Return
		return $cpus;
	}
	
	// It's either parse dmesg boot log or use atacontrol, which requires root
	// Let's do the former :-/
	private function getHD(){
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Drives');
		
		// Get hard drives detected at boot
		if (preg_match_all('/^((?:ad|da|acd|cd)\d+)\: ((?:\w+|\d+\w+)) \<(\S+)\s+([^>]+)\>/m', $this->bootLog, $m, PREG_SET_ORDER) == 0)
			return array();

		// Keep them here
		$drives = array();

		// Stuff array
		foreach ($m as $drive) {
			$drives[] = array(
				'name' => $drive[4],
				'vendor' => $drive[3],
				'device' => '/dev/'.$drive[1],
				'size' => preg_match('/^(\d+)MB$/', $drive[2], $m) == 1 ? $m[1] * 1048576 : false
			);
		}

		// Return
		return $drives;
	}
	
	// Parse dmesg boot log
	private function getDevs() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Hardware Devices');
		
		// Get all devices detected during boot
		if (preg_match_all('/^(\w+\d+): <(.+)>.* on (\w+)\d+$/m', $this->bootLog, $m, PREG_SET_ORDER) == 0)
			return array();

		// Keep them here
		$devices = array();

		// Store the type column for each key
		$sort_type = array();
		
		// Stuff it
		foreach ($m as $device) {

			// Only call this once
			$type = strtoupper($device[3]);

			// Stuff entry
			$devices[] = array(
				'vendor' => '?', // Maybe todo? 
				'device' => $device[2],
				'type' => $type
			);

			// For the sorting of this entry
			$sort_type[] = $type;
		}
		
		// Sort
		array_multisort($devices, SORT_STRING, $sort_type);

		// Return
		return $devices;
	}
		
	// APM? Seems to only support either one battery of them all collectively
	private function getBattery() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Batteries');

		// Store them here
		$batts = array();
		
		// Get result of program
		try {
			$res = $this->exec->exec('apm', '-abl');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `apm` battery info');
			return $batts;
		}
		
		// Values from program
		list(, $bat_status, $percentage) = explode("\n", $res);
		
		// Interpret status code
		switch ($bat_status) {
			case 0:
				$status = 'High';
			break;
			case 1:
				$status = 'Low';
			break;
			case 2:
				$status = 'Critical';
			break;	
			case 3:
				$status = 'Charging';
			break;
			default:
				$status = 'Unknown';
			break;	
		}
		
		// Save battery
		$batts[] = array(
			'percentage' => $percentage.'%',
			'state' => $status,
			'device' => 'battery'
		);
			
		// Return
		return $batts;
	}
	
	// idk
	private function getTemps() {
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Temperature');
	
	}
}
