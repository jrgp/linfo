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


class OS_SunOS {
	
	// Encapsulate these
	protected
		$settings,
		$exec,
		$error;

	// Start us off
	public function __construct($settings) {
		
		// Localize settings
		$this->settings = $settings;
		
		// External exec runnign
		$this->exec = new CallExt;

		// We search these folders for our commands
		$this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
		

		$this->release = php_uname('r');
	}
	
	// This function will likely be shared among all the info classes
	public function getAll() {

		// Return everything, whilst obeying display permissions
		return array(
			'OS' => empty($this->settings['show']) ? '' : $this->getOS(), 			# done
			'Kernel' => empty($this->settings['show']) ? '' : $this->getKernel(), 		# done
			'HostName' => empty($this->settings['show']) ? '' : $this->getHostName(), 	# done
			'Mounts' => empty($this->settings['show']) ? array() : $this->getMounts(), 	# todo
			'RAM' => empty($this->settings['show']) ? array() : $this->getRam(), 		# todo
			/*'Load' => empty($this->settings['show']) ? array() : $this->getLoad(), 		# todo
			'Devices' => empty($this->settings['show']) ? array() : $this->getDevs(), 	# todo
			'HD' => empty($this->settings['show']) ? '' : $this->getHD(), 			# todo
			'UpTime' => empty($this->settings['show']) ? '' : $this->getUpTime(), 		# todo
			'Network Devices' => empty($this->settings['show']) ? array() : $this->getNet(),# todo 
			'RAID' => empty($this->settings['show']) ? '' : $this->getRAID(),	 	# todo 
			'processStats' => empty($this->settings['show']['process_stats']) ? array() : $this->getProcessStats(), # todo
			'Battery' => empty($this->settings['show']) ? array(): $this->getBattery(),  	# todo
			'CPU' => empty($this->settings['show']) ? array() : $this->getCPU(), 		# todo
			'Temps' => empty($this->settings['show']) ? array(): $this->getTemps(), 	# TODO
			*/
		);
	}

	// Return OS type
	private function getOS() {

		if (reset(explode('.', $this->release, 2)) < 5)
			return 'SunOS';
		else
			return 'Solaris';
	}
	
	// Get kernel version
	private function getKernel() {
		
		// hmm. PHP has a native function for this
		return $this->release;
	}

	// Get host name
	private function getHostName() {
		
		// Take advantage of that function again
		return php_uname('n');
	}

	// Mounted file systems
	private function getMounts() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Mounted file systems');

		// Run mount command
		try {
			$res = $this->exec->exec('mount', '-p');
		}
		catch (CallExtException $e){
			$this->error->add('Linfo Core', 'Error running `mount` command');
			return array();
		}
		
		// Parse it
		if (!preg_match_all('/^(\S+) - (\S+) (\w+).+/m', $res, $mount_matches, PREG_SET_ORDER))
			return array();

		// Store them here
		$mounts = array();
		
		// Deal with each entry
		foreach ($mount_matches as $mount) {

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

	// Get ram stats
	private function getRAM() {
		
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Memory');
		
		// We'll return the contents of this
		$return = array();

		// Start us off at zilch
		$return['type'] = 'Virtual';
		$return['total'] = 0;
		$return['free'] = 0;
		$return['swapTotal'] = 0;
		$return['swapFree'] = 0;
		$return['swapInfo'] = array();

		// Get swap info
		try {

			// Run it
			$swap_res = $this->exec->exec('swap', '-s');

			// Match
			if (preg_match('/^total: \d+k bytes allocated \+ \d+k reserved = (\d+)k used, (\d+)k available$/', $swap_res, $swap_match)) {
				$return['swapTotal'] = $swap_match[1]*1024 + $swap_match[2]*1024;
				$return['swapFree'] = $swap_match[2]*1024;
			}
		}
		catch (CallExtException $e){
			// Couldn't get swap
		}
		

		// Give
		return $return;
	}

}
