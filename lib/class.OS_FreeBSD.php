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
 * Incomplete FreeBSD info class
 * So I decided to bite the bullet and use external programs for 
 * BSD/Mac/Win parsing functionality
 *
 * As I don't have access to a FreeBSD machine with php I'm not sure
 * how well this works, if at all.
 */

class OS_FreeBSD {
	
	// Encapsulate these
	protected
		$settings,
		$exec;

	// Start us off
	public function __construct($settings) {
		
		// Localize settings
		$this->settings = $settings;

		// Start our external executable executing stuff
		$this->exec = new CallExt;
		$this->exec->setSearchPaths('/sbin', '/bin', '/user/bin', '/user/local/bin');
	}
	
	// This function will likely be shared among all the info classes
	public function getAll() {

		// Return everything, whilst obeying display permissions
		return array(
			'OS' => !(bool) $this->settings['show']['os'] ? '' : $this->getOS(), 				# done
			'Kernel' => !(bool) $this->settings['show']['kernel'] ? '' : $this->getKernel(), 		# done 
			'HostName' => !(bool) $this->settings['show']['hostname'] ? '' : $this->getHostName(), 		# done
			'Mounts' => !(bool) $this->settings['show']['mounts'] ? array() : $this->getMounts(), 		# done
			'RAM' => !(bool) $this->settings['show']['ram'] ? array() : $this->getRam(), 			# done
			'Load' => !(bool) $this->settings['show']['load'] ? array() : $this->getLoad(), 		# done
			'UpTime' => !(bool) $this->settings['show']['uptime'] ? '' : $this->getUpTime(), 		# done
			'RAID' => !(bool) $this->settings['show']['raid'] ? '' : $this->getRAID(),	 		# done (gmirror only)
			'Network Devices' => !(bool) $this->settings['show']['network'] ? array() : $this->getNet(), 	# done (dev names only)
			'CPU' => !(bool) $this->settings['show']['cpu'] ? array() : $this->getCPU(), 			# ugh
			'HD' => !(bool) $this->settings['show']['hd'] ? '' : $this->getHD(), 				# tbd
			'Devices' => !(bool) $this->settings['show']['devices'] ? array() : $this->getDevs(), 		# tbd
			'Temps' => !(bool) $this->settings['show']['temps'] ? array(): $this->getTemps(), 		# tbd
			'Battery' => !(bool) $this->settings['show']['battery'] ? array(): $this->getBattery()  	# tbd
		);
	}

	// Return OS type
	private function getOS() {
		return 'FreeBSD';	
	}
	
	// Get kernel version
	private function getKernel() {
		
		// Try getting uname result
		try {
			$res = $this->exec->exec('uname', '-a');
		}
		catch (CallExtException $e) {
			return 'Unknown';
		}
		
		// Try parsing it
		if (preg_match('/^FreeBSD [\w\.]+ (\w+) FreeBSD$/', $res, $m) == 0)
			return 'Unknown';
		else
			return $m[1];
	}

	// Get host name
	private function getHostName() {
		
		// We need uname again; it should use the result above
		// instead of calling it again
		try {
			$res = $this->exec->exec('uname', '-a');
		}
		catch (CallExtException $e) {
			return 'Unknown';
		}
		
		// Try parsing it
		if (preg_match('/^FreeBSD ([\w\.]+) \w+ FreeBSD$/', $res, $m) == 0)
			return 'Unknown';
		else
			return $m[1];
	
	}

	// Get mounted file systems
	private function getMounts() {
		
		// Get result of mount command
		try {
			$res = $this->exec->exec('mount');
		}
		catch (CallExtException $e) {
			return array();
		}
		
		// Parse it
		if (preg_match_all('/(.+)\s+on\s+(.+)\s+\((\w+)\, .+\)\n/i', $res, $m, PREG_SET_ORDER) == 0)
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
			
			// Might be good, go for it
			$mounts[] = array(
				'device' => $mount[1],
				'mount' => $parts[2],
				'type' => $parts[3],
				'size' => $size ,
				'used' => $size - $free,
				'free' => $free
			);
		}

		// Give it
		return $mounts;
	}

	// Get ram usage
	private function getRam(){
		
		// Use sysctl to get ram usage
		try {
			$res = $this->exec->exec('sysctl', 'vm.vmtotal');
		}
		catch (CallExtException $e) {
			return array();
		}
		
		// We'll return the contents of this
		$tmpInfo = array();

		// Start us off at zilch
		$tmpInfo['total'] = 0;
		$tmpInfo['free'] = 0;
		$tmpInfo['swapTotal'] = 0;
		$tmpInfo['swapFree'] = 0;

		// Parse the file
		if (preg_match_all('/([a-z\ ]+):\s*\(Total: (\d+)\w,? Active:? (\d+)\w\)\n/i', $string, $m, PREG_SET_ORDER) == 0)
			return $tmpInfo;

		// Parse each entry	
		foreach ($m as $r) {
			switch ($r[1]) {
				case 'Virtual Memory':
					$tmpInfo['swapTotal'] = $r[2] * 1024;
					$tmpInfo['swapFree'] = ($r[2] - $r[3]) * 1024;
				break;
				case 'Real Memory':
					$tmpInfo['total'] = $r[2]  * 1024;
					$tmpInfo['free'] = ($r[2] - $r[3]) * 1024;
				break;
			}
		}
	
		// Return it
		return $tmpInfo;
	}
	
	// Get system load
	private function getLoad() {
		
		// Use uptime, since it also has load values and we'll use the rest of it later
		try {
			$res = $this->exec->exec('uptime');
		}
		catch (CallExtException $e) {
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
		
		// Use uptime
		try {
			$res = $this->exec->exec('uptime');
		}
		catch (CallExtException $e) {
			return '';
		}

		// Parse it
		if (preg_match('/^\d+:\d+\w{2}\s+up\s+(\d+)\s+days,\s+(\d+):(\d+).+$/', $res, $m) == 0)
			return '';

		// Get what
		list(, $days, $hours, $minutes) = $m;
		
		// Convert to seconds
		$seconds = 0;
		$seconds += $days*24*60*60;
		$seconds += $hours*60*60;
		$seconds += $minutes*60;
		
		// Get it textual, as in days/minutes/hours/etc
		return seconds_convert($seconds);
	}

	// RAID Stats
	private function getRAID() {
		
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
							'name' => $m[2],
							'type' => $m[1],
							'status' => $m[3],
							'devices' => array($m[4])
						);
					}

					// Hitting a new device in a raid definition
					elseif (preg_match('/^                      (\w+)$/', $content, $m)) {
						if (array_key_exists($i, $info))
							$return[$i]['devices'][] = $m[1];
					}
				}
			}
			catch (CallExtException $e) {
				// Don't jump out; allow potential more raid array
				// mechanisms to be gathered and outputted
			}
		}
		
		// Give off raid info
		return $return;
	}

	// So far just gets interface names :-/
	private function getNet() {

		// Store return vals here
		$return = array();
		
		// Use ifconfig to get net info
		try {
			$res = $this->exec->exec('ifconfig');
		}
		catch (CallExtException $e) {
			return $return;
		}

		// Parse result
		if (preg_match_all('/^([a-z0-9]+):.+$/im', $string, $m, PREG_SET_ORDER) == 0)
			return $return;

		// Entries
		foreach ($m as $net)
			$return[$net[1]] = array(

				// Not sure how to get this stuff on freebsd
				'recieved' => array(
					'bytes' => false,
					'errors' => false,
					'packets' => false 
				),
				'sent' => array(
					'bytes' => false,
					'errors' =>  false,
					'packets' => false 
				)
			);

		// Give
		return $return;
	}

	// Get CPU's
	// I don't really like how this is done
	private function getCPU() {

		// Use sysctl to get CPU info
		try {
			$res = $this->exec->exec('sysctl', 'hw.model hw.ncpu');
		}
		catch (CallExtException $e) {
			return array();
		}

		// Parse result
		if (preg_match_all('/([\w\.]+): (.+)/', $res, $m, PREG_SET_ORDER) == 0)
			return array();

		// Get
		foreach ($m as $cpstat) {
			switch ($cpstat[1]) {
				case 'hw.model':
					$model = $cpstat[2];
				break;
				case 'hw.ncpu':
					$num = $cpstat[2];
				break;
			}
		}

		// Ugh
		if (!$num || !$model)
			return array();
		
		// Return this
		$cpus = array();

		// Get output ready
		for ($i = 1; $i <= $num; $i++)
			$cpus[] = array(
				'Vendor' => '?',# ugh
				'MHz' => '?',	# ugh
				'Model' => $model
			);
		
		// Return
		return $cpus;
	}
	
	// idk
	private function getHD(){}
	
	// idk
	private function getTemps(){}
	
	// idk
	private function getDevs(){}
		
	// idk
	private function getBattery() {}
}
