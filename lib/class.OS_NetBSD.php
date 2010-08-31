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
 * NetBSD info class. Differs slightly from FreeBSD's
 */

class OS_NetBSD {

	// Encapsulate these
	protected
		$settings,
		$exec,
		$error,
		$dmesg;

	// Start us off
	public function __construct($settings) {
		
		// Localize settings
		$this->settings = $settings;
		
		// Localize error handler
		$this->error = LinfoError::Fledging();

		// Start our external executable executing stuff
		$this->exec = new CallExt;
		$this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/pkg/bin', '/usr/sbin'));

		// Called in a separate function so we can time it easier
		$this->loadDmesg();

	}


	// Load dmesg
	private function loadDmesg() {
		// Time it
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Output of dmesg');
		
		// We parse dmesg often
		try {
			$this->dmesg = $this->exec->exec('dmesg');
		}

		// As much as I'd love to use realtime dmesg, settle for boot log if that didn't work
		catch (CallExtException $e) {
			$this->dmesg = getContents('/var/run/dmesg.boot');
		}
	}
	
	// Get
	public function getAll() {

		// Return everything, whilst obeying display permissions
		return array(
			'OS' => empty($this->settings['show']) ? '' : $this->getOS(), 			# done
			'Kernel' => empty($this->settings['show']) ? '' : $this->getKernel(), 		# done
			'HostName' => empty($this->settings['show']) ? '' : $this->getHostName(), 	# done
			'Mounts' => empty($this->settings['show']) ? array() : $this->getMounts(), 	# done
			'Load' => empty($this->settings['show']) ? array() : $this->getLoad(), 		# done
			'UpTime' => empty($this->settings['show']) ? '' : $this->getUpTime(), 		# done
			'Network Devices' => empty($this->settings['show']) ? array() : $this->getNet(),# lacks type
			'CPU' => empty($this->settings['show']) ? array() : $this->getCPU(), 		# Works, but assumes they're identical
			'HD' => empty($this->settings['show']) ? '' : $this->getHD(), 			# Known to get hard drives and cdroms
			'RAM' => empty($this->settings['show']) ? array() : $this->getRam(), 		# TODO
			'Devices' => empty($this->settings['show']) ? array() : $this->getDevs(), 	# TODO
			'RAID' => empty($this->settings['show']) ? '' : $this->getRAID(),	 	# TODO 
			'Battery' => empty($this->settings['show']) ? array(): $this->getBattery(),  	# TODO
			'Temps' => empty($this->settings['show']) ? array(): $this->getTemps(), 	# TODO
		);
	}

	// Operating System
	private function getOS() {
		return 'NetBSD';
	}

	// Kernel version
	private function getKernel() {
		return php_uname('r');
	}

	// Host name
	private function getHostName() {
		return php_uname('n');
	}

	// Mounted file systems
	private function getMounts() {

		// Time it
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Mounted file systems');

		// Try getting mount command
		try {
			$res = $this->exec->exec('mount');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `mount` command');
			return array();
		}

		// Match the file systems
		if(@preg_match_all('/^(\S+) on (\S+) type (\S+)/m', $res, $mount_match, PREG_SET_ORDER) == 0)
			return array();

		// Store them here
		$mounts = array();

		// Go through each
		foreach ($mount_match as $mount) {
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
		
		// Give them
		return $mounts;
	}

	// Get system load
	private function getLoad(){

		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Load Averages');

		// Try using sysctl to get load average
		try {
			$res = $this->exec->exec('sysctl', 'vm.loadavg');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `sysctl` to get load');
			return array();
		}

		// Match it
		if (@preg_match('/([\d\.]+) ([\d\.]+) ([\d\.]+)$/', $res, $load_match))
			return array(
				'now' => $load_match[1],
				'5min' => $load_match[2],
				'15min' => $load_match[3]
			);

		// Match failed
		else
			return false;
	}

	// Get the always gloatable uptime
	private function getUpTime(){
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Uptime');

		// Use sysctl
		try {
			$res = $this->exec->exec('sysctl', 'kern.boottime');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `sysctl` to get boot time');
			return array();
		}

		// Match it
		if (@preg_match('/^kern.boottime = ([^$]+)$/', $res, $time_match) != 1)
			return false;

		// Get it in unix timestamp
		$booted = strtotime($time_match[1]);

		// Give it
		return seconds_convert(time() - $booted) . '; booted ' . date('m/d/y h:i A', $booted);
	}

	// Get network devices
	private function getNet(){
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Network Devices');

		// Try using netstat
		try {
			$res = $this->exec->exec('netstat', '-nbdi');
		}
		catch(CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `netstat` to get network info');
			return array();
		}

		// Match the interfaces themselves
		if (preg_match_all('/^(\S+)\s+\d+\s+<Link>\s+[a-z0-9\:]+\s+(\d+)\s+(\d+)\s+\d+$/m', $res, $net_matches, PREG_SET_ORDER) == 0)
			return array();

		// Store statuses for each here
		$statuses = array();
	
		// Try using ifconfig to get statuses for each interface
		try {
			$ifconfig = $this->exec->exec('ifconfig', '-a');
			foreach ((array) explode("\n", $ifconfig) as $line) {
				if (preg_match('/^(\w+):/', $line, $m) == 1)
					$current_nic = $m[1];
				elseif (preg_match('/^\s+status: (\w+)$/', $line, $m) == 1)
					$statuses[$current_nic] = $m[1];
			}
		}
		catch(CallExtException $e) {}

		// Store interfaces here
		$nets = array();

		// Go through each
		foreach($net_matches as $net) {

			// See if we successfully found a status, and use it if so
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

			// Save this interface
			$nets[$net[1]] = array(
				'recieved' => array(
					'bytes' => $net[2],
				),
				'sent' => array(
					'bytes' => $net[3],
				),
				'state' => $state,
				'type' => 'Unknown' // TODO
			);
		}

		// Give it
		return $nets;
	}

	// Get drives
	private function getHD(){

		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('CPU');

		// Temp shit
		$drives = array();
		$curr_hd = false;

		// Parse dmesg
		foreach ((array) @explode("\n", $this->dmesg) as $dmesg_line) {
			
			// Beginning of a drive entry
			if (preg_match('/^([a-z]{2}\d) at [^:]+: <([^>]+)> (\w+)/', $dmesg_line, $init_match)) {

				// If it's a cdrom just stop here and save it.
				if ($init_match[3] == 'cdrom') {
					
					// Save entry
					$drives[] = array(
						'name' => preg_match('/^([^,]+)/', $init_match[2], $cd_match) ? $cd_match[1] : $init_match[2],
						'vendor' => false, // I don't know if this is possible
						'device' => '/dev/'.$init_match[1],
						
						// Not sure how to get the following:
						'size' => false,
						'reads' => false,
						'writes' => false
					);
				}
				
				// Otherwise prep for further info on a later line
				elseif ($init_match[3] == 'disk') {
					$curr_hd = array($init_match[1], $init_match[2], $init_match[3]);
				}

				// Don't go any farther with this line
				continue;
			}

			// A hard drive setting line, that has size and stuff
			elseif ($curr_hd != false && preg_match('/^'.preg_quote($curr_hd[0]).': (\d+) MB/', $dmesg_line, $drive_match)) {
				
				// Try getting vendor or name
				$make = preg_match('/^([^,]+), ([^,]+)/', $curr_hd[1], $v_match) ? array($v_match[1], $v_match[2]) : false;

				// Save entry
				$drives[] = array(
					'name' => $make ? $make[1] : $curr_hd[1],
					'vendor' => $make ? $make[0] : false,
					'device' => '/dev/'.$curr_hd[0],
					'size' => $drive_match[1] * 1048576,
					
					// Not sure how to get the following:
					'reads' => false, 
					'writes' => false 
				);

				// We're done with this drive
				$curr_hd = false;
				
				// Don't go any farther with this line
				continue;
			}
		}

		// Give drives
		return $drives;
	}

	// Get cpu's
	private function getCPU(){
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('CPU');

		// Parse dmesg
		if (preg_match_all('/^cpu\d+ at [^:]+: (\S+) ([^,]+), (\d+)MHz/m', $this->dmesg, $cpu_matches, PREG_SET_ORDER) == 0)
			return array();

		// Store them here
		$cpus = array();

		// Store as many as possible
		foreach ($cpu_matches as $cpu_m)
			$cpus[] = array(
				'Model' => $cpu_m[2],
				'MHz' => $cpu_m[3],
				'Vendor' => $cpu_m[1]
			);

		// Give them
		return $cpus;
	}

	// TODO:
	private function getRam(){}
	private function getDevs(){}
	private function getRAID(){}
	private function getBattery(){}
	private function getTemps(){}
}
