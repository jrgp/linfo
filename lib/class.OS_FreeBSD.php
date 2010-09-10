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

class OS_FreeBSD extends OS_BSD_Common{
	
	// Encapsulate these
	protected
		$settings,
		$exec,
		$error,
		$dmesg;

	// Start us off
	public function __construct($settings) {

		// Initiate parent
		parent::__construct($settings);

		// We search these folders for our commands
		$this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
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
			'Network Devices' => empty($this->settings['show']) ? array() : $this->getNet(),# done 
			'RAID' => empty($this->settings['show']) ? '' : $this->getRAID(),	 	# done (gmirror only)
			'processStats' => empty($this->settings['show']['process_stats']) ? array() : $this->getProcessStats(), # lacks thread stats
			'Battery' => empty($this->settings['show']) ? array(): $this->getBattery(),  	# works
			'CPU' => empty($this->settings['show']) ? array() : $this->getCPU(), 		# works
			'Temps' => empty($this->settings['show']) ? array(): $this->getTemps(), 	# TODO
		);
	}

	// Return OS type
	private function getOS() {

		// Obviously
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
		
		// Use sysctl for memory
		$sys = $this->getSysCTL('vm.vmtotal');
		
		// We'll return the contents of this
		$return = array();

		// Start us off at zilch
		$return['type'] = 'Virtual';
		$return['total'] = 0;
		$return['free'] = 0;
		$return['swapTotal'] = 0;
		$return['swapFree'] = 0;
		$return['swapInfo'] = array();

		// Parse the file
		if (!preg_match_all('/([a-z\ ]+):\s*\(Total: (\d+)\w,? Active:? (\d+)\w\)\n/i', $sys, $rm, PREG_SET_ORDER))
			return $return;

		// Parse each entry	
		foreach ($rm as $r) {
			if ($r[1] == 'Real Memory') {
					$return['total'] = $r[2]  * 1024;
					$return['free'] = ($r[2] - $r[3]) * 1024;
			}
		}
		
		// Swap info
		try {
			$swapinfo = $this->exec->exec('swapinfo', '-k');
			// Parse swap info
			@preg_match_all('/^(\S+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $swapinfo, $sm, PREG_SET_ORDER);
			foreach ($sm as $swap) {
				$return['swapTotal'] += $swap[2]*1024;
				$return['swapFree'] += (($swap[2] - $swap[3])*1024);
				$ft = @filetype($swap[1]); // TODO: I'd rather it be Partition or File
				$return['swapInfo'][] = array(
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
		return $return;
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
		$res = $this->getSysCTL('kern.boottime');
		
		// Extract boot part of it
		if (preg_match('/^\{ sec \= (\d+).+$/', $res, $m) == 0)
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

						// Save result set
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

						// This migh be part of a raid dev; save it if it is
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

	// Done
	private function getNet() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Network Devices');

		// Store return vals here
		$return = array();
		
		// Use netstat to get info
		// TODO: This is reallllyyyy slow. Alternative?
		try {
			$netstat = $this->exec->exec('netstat', '-nbdi');
		}
		catch(CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `netstat` to get network info');
			return $return;
		}
		
		// Initially get interfaces themselves along with numerical stats
		if (preg_match_all('/^(\w+\w)\s*\w+\s+<Link\#\w+>(?:\D+|\s+\w+:\w+:\w+:\w+:\w+:\w+\s+)(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+(\w+)\s+/m', $netstat, $netstat_match, PREG_SET_ORDER) == 0)
			return $return;

		// Try using ifconfig to get states of the network interfaces
		$statuses = array();
		try {
			// Output of ifconfig command
			$ifconfig = $this->exec->exec('ifconfig', '-a');

			// Set this to false to prevent wasted regexes
			$current_nic = false;

			// Go through each line
			foreach ((array) explode("\n", $ifconfig) as $line) {

				// Approachign new nic def
				if (preg_match('/^(\w+):/', $line, $m) == 1)
					$current_nic = $m[1];

				// Hopefully match its status
				elseif ($current_nic && preg_match('/^\s+status: (\w+)$/', $line, $m) == 1) {
					$statuses[$current_nic] = $m[1];
					$current_nic = false;
				}
			}
		}
		catch(CallExtException $e) {}

		// Get type from dmesg boot
		$type = array();
		$type_nics = array();

		// Store the to-be detected nics here
		foreach ($netstat_match as $net)
			$type_nics[] = $net[1];

		// Go through dmesg looking for them
		if (preg_match_all('/^(\w+): <.+>.+on ([a-z]+)\d+/m', $this->dmesg, $type_match, PREG_SET_ORDER)) {
			
			// Go through each
			foreach ($type_match as $type_nic_match) 

				// Is this one of our detected nics?
				if (in_array($type_nic_match[1], $type_nics))

					// Yes; save status
					$type[$type_nic_match[1]] = $type_nic_match[2];
		}


		// Save info
		foreach ($netstat_match as $net) {

			// Determine status
			switch (array_key_exists($net[1], $statuses) ? $statuses[$net[1]] : 'unknown') {

				case 'active':
					$state = 'up';
				break;
				
				case 'inactive':
					$state = 'down';
				break;

				default:
					$state = 'unknown';
				break;
			}

			// Save info
			$return[$net[1]] = array(
				
				// These came from netstat
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

				// This came from ifconfig -a
				'state' => $state,

				// And this came from dmeg.
				// TODO: Value for following is usually vague
				'type' => array_key_exists($net[1], $type) ? strtoupper($type[$net[1]]) : 'N/A'
			);
		}

		// Return it
		return $return;
	}

	// Get CPU's
	// I still don't really like how this is done
	// todo: support multiple non-identical cpu's
	private function getCPU() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('CPUs');

		// Store them here
		$cpus = array();
		
		// Get cpu type
		if (preg_match('/^CPU: ([^(]+) \(([\d\.]+)\-MHz.+\).*\n\s+Origin = "(\w+)"/m', $this->dmesg, $cpu_m) == 0)
			return $cpus;
		
		// I don't like how this is done. It implies that if you have more than one CPU they're all identical
		$num = preg_match('/^FreeBSD\/SMP\: Multiprocessor System Detected\: (\d+) CPUs/m', $contents, $num_m) ? $num_m[1] : 1;	
		
		// Stuff it with identical cpus
		for ($i = 1; $i <= $num; $i++)
			
			// Save each
			$cpus[] = array(
				'Model' => $cpu_m[1],
				'MHz' => $cpu_m[2],
				'Vendor' => $cpu_m[3]
			);
		
		// Return
		return $cpus;
	}
	
	// TODO: Get reads/writes and partitions for the drives
	private function getHD(){
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Drives');
		
		// Get hard drives detected at boot
		if (preg_match_all('/^((?:ad|da|acd|cd)\d+)\: ((?:\w+|\d+\w+)) \<(\S+)\s+([^>]+)\>/m', $this->dmesg, $m, PREG_SET_ORDER) == 0)
			return array();

		// Keep them here
		$drives = array();

		// Stuff array
		foreach ($m as $drive) {
			$drives[] = array(
				'name' => $drive[4],
				'vendor' => $drive[3],
				'device' => '/dev/'.$drive[1],
				'size' => preg_match('/^(\d+)MB$/', $drive[2], $m) == 1 ? $m[1] * 1048576 : false,
				'reads' => false,
				'writes' => false
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
		if (preg_match_all('/^(\w+\d+): <(.+)>.* on (\w+)\d+$/m', $this->dmesg, $m, PREG_SET_ORDER) == 0)
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
				'vendor' => false, // Maybe todo? 
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
	
	// Get stats on processes
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
				'idle' => 0
			),
			'proc_total' => 0,
			'threads' => false // I'm not sure how to get this
		);

		// Use ps
		try {
			// Get it
			$ps = $this->exec->exec('ps', 'ax');

			// Match them
			preg_match_all('/^\s*\d+\s+[\w?]+\s+([A-Z])\S*\s+.+$/m', $ps, $processes, PREG_SET_ORDER);
			
			// Get total
			$result['proc_total'] = count($processes);
			
			// Go through
			foreach ($processes as $process) {
				switch ($process[1]) {
					case 'S':
					case 'I':
						$result['totals']['sleeping']++;
					break;
					case 'Z':
						$result['totals']['zombie']++;
					break;
					case 'R':
					case 'D':
						$result['totals']['running']++;
					break;
					case 'T':
						$result['totals']['stopped']++;
					break;
					case 'W':
						$result['totals']['idle']++;
					break;
				}
			}
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `ps` to get process info');
		}

		// Give
		return $result;
	}
	
	// idk
	private function getTemps() {
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Temperature');
	
	}
}
