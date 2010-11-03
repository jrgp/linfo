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

/**
 * Keep out hackers...
 */
defined('IN_INFO') or exit;

/**
 * Get info on a usual linux system
 * Works by exclusively looking around /proc and /sys
 * Totally ignores CallExt class, very deliberately
 * Also deliberately ignores trying to find out the distro. 
 */
class OS_Windows {

	// Keep these tucked away
	protected
		$settings, $error;
	
	private
		$wmi;

	/**
	 * Constructor. Localizes settings
	 * 
	 * @param array $settings of linfo settings
	 * @access public
	 */
	public function __construct($settings) {

		// Localize settings
		$this->settings = $settings;

		// Localize error handler
		$this->error = LinfoError::Fledging();
		
		// Gather some data from WMI!
		$required_data = array(
			"COMPUTERSYSTEM",
			"CPU",
			//"NIC",
			"OS",
			//"PARTITION", // using VOLUME instead
			//"TEMPERATURE",
			//"VOLTAGE",
			"VOLUME",
		);
		foreach($required_data as $e) {
			$this->wmi[$e] = $this->WMIRequest($e);
		}
		echo "<!-- ";
		var_dump($this->wmi);
		echo " -->";
	}

	/**
	 * getAll 
	 * 
	 * @access public
	 * @return array the info
	 */
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
	
	/**
	 * getOS 
	 * 
	 * @access private
	 * @return string current windows version
	 */
	private function getOS() {
		
		return $this->wmi['OS'][0]['Caption'];
	}
	
	/**
	 * getKernel 
	 * 
	 * @access private
	 * @return string kernel version
	 */
	private function getKernel() {
	
		return 'Build ' . $this->wmi['OS'][0]['BuildNumber'];
	}
	
	/**
	 * getHostName 
	 * 
	 * @access private
	 * @return string the host name
	 */
	private function getHostName() {
		
		return $this->wmi['COMPUTERSYSTEM'][0]['DNSHostName'];
	}
	
	/**
	 * getRam 
	 * 
	 * @access private
	 * @return array the memory information
	 */
	private function getRam(){
		
		$return['type'] = 'Physical';
		$return['total'] = $this->wmi['COMPUTERSYSTEM'][0]['TotalPhysicalMemory'];
		$return['free'] = $this->wmi['OS'][0]['FreePhysicalMemory'] * 1024;
		/*$return['swapTotal'] = $memVals['SwapTotal']*1024;
		$return['swapFree'] = $memVals['SwapFree']*1024;
		$return['swapCached'] = $memVals['SwapCached']*1024;
		$return['swapInfo'] = $swapVals;*/
		
		return $return;
	}
	
	/**
	 * getCPU 
	 * 
	 * @access private
	 * @return array of cpu info
	 */
	private function getCPU() {
	}
	
	/**
	 * getUpTime 
	 * 
	 * @access private
	 * @return string uptime
	 */
	private function getUpTime () {
	}
	
	/**
	 * getHD 
	 * 
	 * @access private
	 * @return array the hard drive info
	 */
	private function getHD() {
	}
	
	/**
	 * getTemps 
	 * 
	 * @access private
	 * @return array the temps
	 */
	private function getTemps() {
	}
	
	/**
	 * getMounts 
	 * 
	 * @access private
	 * @return array the mounted the file systems
	 */
	private function getMounts() {
		
		$volumes = array();
		
		foreach($this->wmi['VOLUME'] as $volume) {
			if($volume['DriveType'] != 3) { // present but not mounted
				continue;
			}
			$options = array();
			if ($volume['Automount']) {
				$options[] = 'automount';
			}
			if ($volume['BootVolume']) {
				$options[] = 'boot';
			}
			if ($volume['Compressed']) {
				$options[] = 'compressed';
			}
			if ($volume['IndexingEnabled']) {
				$options[] = 'indexed';
			}
			$volumes[] = array(
				'device' => false,
				'label' => $volume['Label'],
				'mount' => $volume['Caption'],
				'type' => $volume['FileSystem'],
				'size' => $volume['Capacity'],
				'used' => $volume['Capacity'] - $volume['FreeSpace'],
				'free' => $volume['FreeSpace'],
				'free_percent' => round($volume['FreeSpace'] / $volume['Capacity'], 2) * 100,
				'used_percent' => round(($volume['Capacity'] - $volume['FreeSpace']) / $volume['Capacity'], 2) * 100,
				'options' => $options
			);
		}
		
		return $volumes;
	}
	
	/**
	 * getDevs 
	 * 
	 * @access private
	 * @return array of devices
	 */
	private function getDevs() {
	}
	
	/**
	 * getRAID 
	 * 
	 * @access private
	 * @return array of raid arrays
	 */
	private function getRAID() {
	}
	
	/**
	 * getLoad 
	 * 
	 * @access private
	 * @return array of current system load values
	 */
	private function getLoad() {
	}
	
	/**
	 * getNet 
	 * 
	 * @access private
	 * @return array of network devices
	 */
	private function getNet() {
	}
	
	/**
	 * getBattery 
	 * 
	 * @access private
	 * @return array of battery status
	 */
	private function getBattery() {
	}
	
	/**
	 * getWifi 
	 * 
	 * @access private
	 * @return array of wifi devices
	 */
	private function getWifi() {
	}
	
	/**
	 * getSoundCards 
	 * 
	 * @access private
	 * @return array of soundcards
	 */
	private function getSoundCards() {
	}
	
	/**
	 * getProcessStats 
	 * 
	 * @access private
	 * @return array of process stats
	 */
	private function getProcessStats() {
	}
	
	/**
	 * WMIRequest
	 * 
	 * @access private
	 * @param string $name alias
	 * @return array results
	 */
	private function WMIRequest($name) {
	
		exec("wmic $name", $results, $errorcode);
		if ($errorcode != 0) {
			$this->error->add('Linfo Windows wmic parser', "Failed to execute wmic with parameter $name");
			return array();
		}
		$header = $results[0];
		unset($results[0]);
		
		// Alright, this is going to get a bit tricky: Parse the WMI table output and return it as array
		$columns = array();
		$start = 0;
		while ($start !== false) {
			$end = strpos($header, '  ', $start);
			if ($end === false) {
				$columns[] = array("start" => $start, "end" => -1);
				break;
			}
			$end = $end + 2;
			for ($i = $end; $i < strlen($header); $i++) {
				if($header[$i] != ' ') {
					$end = $i;
					break;
				}
			}
			if ($i == strlen($header)) {
				$this->error->add('Linfo Windows wmic parser', "Error parsing wmic output for $name");
				return array();
			}
			$columns[] = array("start" => $start, "end" => $end, "caption" => trim(substr($header, $start, $end - $start)));
			$start = $end;
		}
		$table = array();
		foreach ($results as $e) {
			if (empty($e)) {
				continue;
			}
			$a = array();
			foreach ($columns as $c) {
				$start = $c['start'];
				$len = ($c['end'] == -1) ? null : $c['end'] - $start;
				$s = substr($e, $start, $len);
				if ($s) {
					$s = trim($s);
					// Convert to bool
					if ($s == "TRUE") {
						$s = true;
					} else if ($s == "FALSE") {
						$s = false;
					}
					$a[$c['caption']] = $s;
				}
			}
			$table[] = $a;
		}
		return $table;
	}
}
