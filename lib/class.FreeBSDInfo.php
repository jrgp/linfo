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
 */

class FreeBSDInfo {

	protected $settings, $have = array(), $exec;

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
			'HD' => !(bool) $this->settings['show']['hd'] ? '' : $this->getHD(), 				# tbd
			'Load' => !(bool) $this->settings['show']['load'] ? array() : $this->getLoad(), 		# tbd
			'UpTime' => !(bool) $this->settings['show']['uptime'] ? '' : $this->getUpTime(), 		# tbd
			'CPU' => !(bool) $this->settings['show']['cpu'] ? array() : $this->getCPU(), 			# tbd
			'Network Devices' => !(bool) $this->settings['show']['network'] ? array() : $this->getNet(), 	# tbd
			'Devices' => !(bool) $this->settings['show']['devices'] ? array() : $this->getDevs(), 		# tbd
			'Temps' => !(bool) $this->settings['show']['temps'] ? array(): $this->getTemps(), 		# tbd
			'Battery' => !(bool) $this->settings['show']['battery'] ? array(): $this->getBattery()  	# tbd
		);
	}

	// Return OS type
	public function getOS() {
		return 'FreeBSD';	
	}
	
	// Get kernel version
	public function getKernel() {

		try {
			$res = $this->exec->exec('uname', '-a');
		}
		catch (CallExtException $e) {
			return 'Unknown';
		}

		if (preg_match('/^FreeBSD [\w\.]+ (\w+) FreeBSD$/', $res, $m) == 0)
			return 'Unknown';
		else
			return $m[1];
	}

	// Get host name
	public function getHostName() {
		
		try {
			$res = $this->exec->exec('uname', '-a');
		}
		catch (CallExtException $e) {
			return 'Unknown';
		}

		if (preg_match('/^FreeBSD ([\w\.]+) \w+ FreeBSD$/', $res, $m) == 0)
			return 'Unknown';
		else
			return $m[1];
	
	}

	// Get mounted file systems
	public function getMounts() {

		try {
			$res = $this->exec->exec('mount');
		}
		catch (CallExtException $e) {
			return array();
		}

		if (preg_match_all('/(.+)\s+on\s+(.+)\s+\((\w+)\, .+\)\n/i', $res, $m, PREG_SET_ORDER) == 0)
			return array();

		$mounts = array();

		foreach ($m as $mount) {
			// Should we not show this?
			if ($mount[1] == 'none' || in_array($mount[3], $this->settings['hide']['filesystems']))
				continue;

			if (in_array($mount[1], $this->settings['hide']['storage_devices']))
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

		return $mounts;
	}

	// Get ram usage
	public function getRam(){

		try {
			$res = $this->exec->exec('sysctl','vm.vmtotal');
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

	public function getHD(){}
	public function getTemps(){}
	public function getDevs(){}
	public function getLoad(){}
	public function getNet(){}
	public function getCPU(){}
	public function getUpTime(){}
	public function getBattery() {}
}
