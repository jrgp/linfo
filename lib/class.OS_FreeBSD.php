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
 * Nearly complete FreeBSD info class
 * So I decided to bite the bullet and use external programs for 
 * BSD/Mac/Win parsing functionality
 * Note: When Linux compatibility is enabled and /proc is mounted, it only
 * contains process info; none of the hardware/system/network status that Linux /proc has
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
		$this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
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
			'Network Devices' => !(bool) $this->settings['show']['network'] ? array() : $this->getNet(), 	# done (names only)
			'CPU' => !(bool) $this->settings['show']['cpu'] ? array() : $this->getCPU(), 			# eh
			'HD' => !(bool) $this->settings['show']['hd'] ? '' : $this->getHD(), 				# Done 
			'Devices' => !(bool) $this->settings['show']['devices'] ? array() : $this->getDevs(), 		# TODO
			'Temps' => !(bool) $this->settings['show']['temps'] ? array(): $this->getTemps(), 		# TODO
			'Battery' => !(bool) $this->settings['show']['battery'] ? array(): $this->getBattery()  	# TODO
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
		
		// Get result of mount command
		try {
			$res = $this->exec->exec('mount');
		}
		catch (CallExtException $e) {
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
		
		// Use sysctl to get ram usage
		try {
			$sys = $this->exec->exec('sysctl', 'vm.vmtotal');
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
				$ft = @filetype($swap[1]);
				$tmpInfo['swapInfo'][] = array(
					'device' => $swap[1],
					'size' => $swap[2]*1024,
					'used' => $swap[3]*1024,
					'type' => ucfirst($ft)
				);
			}
		}
		catch (CallExtException $e) {
			// meh
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
		
		// todo
		return '';

		// Use uptime
		try {
			$res = $this->exec->exec('uptime');
		}
		catch (CallExtException $e) {
			return '';
		}

		echo $res;

		// Parse it
		if (preg_match('/^\d+:\d+[AP]M\s+up\s+((\d+)\s+days,\s+)?(\d+):(\d+).+$/', $res, $m) == 0)
			return '';

		// Get what
		//list(, $days, $hours, $minutes) = $m;
		print_r($m);
		
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
		if (preg_match_all('/^([a-z0-9]+):.+$/im', $res, $m, PREG_SET_ORDER) == 0)
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
				),
				'state' => '?',
				'type' => '?'
			);

		// Give
		return $return;
	}

	// Get CPU's
	// I still don't really like how this is done
	private function getCPU() {

		$cpus = array();

		$file = '/var/run/dmesg.boot';

		$contents = getContents($file);
		if (preg_match('/^CPU: ([^(]+) \(([\d\.]+)\-MHz.+\).*\n\s+Origin = "(\w+)"/m', $contents, $cpu_m) == 0)
			return $cpus;
		
		// I don't like how this is done. It implies that if you have more than one CPU they're all identical
		$num = preg_match('/^FreeBSD\/SMP\: Multiprocessor System Detected\: (\d+) CPUs/m', $contents, $num_m) ? $num_m[1] : 1;	

		for ($i = 1; $i <= $num; $i++)
			$cpus[] = array(
				'Model' => $cpu_m[1],
				'MHz' => $cpu_m[2],
				'Vendor' => $cpu_m[3]
			);

		return $cpus;
	}
	
	// It's either parse dmesg boot log or use atacontrol, which requires root
	// Let's do the former :-/
	private function getHD(){
		$file = '/var/run/dmesg.boot';
		if (!is_file($file) || !is_readable($file))
			return array();
		$contents = getContents($file);
		if (preg_match_all('/^((?:ad|da|acd|cd)\d+)\: ((?:\w+|\d+\w+)) \<(\S+)\s+([^>]+)\>/m', $contents, $m, PREG_SET_ORDER) == 0)
			return array();
		$drives = array();
		foreach ($m as $drive) {
			$drives[] = array(
				'name' => $drive[4],
				'vendor' => $drive[3],
				'device' => '/dev/'.$drive[1],
				'size' => preg_match('/^(\d+)MB$/', $drive[2], $m) == 1 ? $m[1] * 1048576 : false
			);
		}
		return $drives;
	}
	
	// idk
	private function getTemps(){}
	
	// idk
	private function getDevs(){}
		
	// APM?
	private function getBattery() {

		$batts = array();

		try {
			$res = $this->exec->exec('apm', '-abl');
		}
		catch (CallExtException $e) {
			return $batts;
		}
		
		list(, $bat_status, $percentage) = explode("\n", $res);

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

		$batts[] = array(
			'percentage' => $percentage.'%',
			'state' => $status,
			'device' => 'battery'
		);

		return $batts;
	}
}
