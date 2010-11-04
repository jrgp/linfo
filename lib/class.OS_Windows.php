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
 * Get info on Windows systems
 * Uses the wmic WMI command line client
 * Written and maintained by Oliver Kuckertz (mologie).
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
			"DISKDRIVE",
			//"NIC",
			"OS",
			"PATH Win32_PnPEntity",
			"PARTITION",
			"PROCESS",
			//"TEMPERATURE",
			//"VOLTAGE",
			"VOLUME",
		);
		foreach($required_data as $e) {
			$this->wmi[$e] = $this->WMIRequest($e);
		}
		
		//if($_SERVER['REMOTE_ADDR'] == '127.0.0.1') { var_dump($this->wmi); }
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
	
		return $this->wmi['PROCESS'][0]['WindowsVersion'];
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
		
		return array(
			'type' => 'Physical',
			'total' => $this->wmi['COMPUTERSYSTEM'][0]['TotalPhysicalMemory'],
			'free' => $this->wmi['OS'][0]['FreePhysicalMemory'] * 1024
		);
	}
	
	/**
	 * getCPU 
	 * 
	 * @access private
	 * @return array of cpu info
	 */
	private function getCPU() {
		
		$cpus = array();
		
		foreach($this->wmi["CPU"] as $cpu) {
			$curr = array(
				'Model' => $cpu['Name'],
				'Vendor' => $cpu['Manufacturer'],
				'MHz' => $cpu['CurrentClockSpeed'],
			);
			$curr['Model'] = $cpu['Name'];
			for ($i = 0; $i < $cpu['NumberOfLogicalProcessors']; $i++)
				$cpus[] = $curr;
		}
		
		return $cpus;
	}
	
	/**
	 * getUpTime 
	 * 
	 * @access private
	 * @return string uptime
	 */
	private function getUpTime () {
		
		$booted = $this->wmi['OS'][0]['LastBootUpTime'];
		$booted = date_parse_from_format("YmdHMS", $booted);
		$booted = mktime($booted['hour'], $booted['minute'], $booted['second'], $booted['month'], $booted['day'], $booted['year']);
		
		return seconds_convert(time() - $booted) . '; booted '.date('m/d/y h:i A', $booted);
	}
	
	/**
	 * getHD 
	 * 
	 * @access private
	 * @return array the hard drive info
	 */
	private function getHD() {
		
		$drives = array();
		$partitions = array();
		
		foreach ($this->wmi['PARTITION'] as $partition) {
			$partitions[$partition['DiskIndex']][] = array(
				'size' => $partition['Size'],
				'name' => $partition['DeviceID'] . ' (' . $partition['Type'] . ')'
			);
		}
		
		foreach ($this->wmi['DISKDRIVE'] as $drive) {
			$drives[] = array(
				'name' =>  $drive['Caption'],
				'vendor' => reset(explode(" ", $drive['Caption'])),
				'device' => $drive['DeviceID'],
				'reads' => false,
				'writes' => false,
				'size' => $drive['Size'],
				'partitions' => array_key_exists($drive['Index'], $partitions) && is_array($partitions[$drive['Index']]) ? $partitions[$drive['Index']] : false 
			);
		}
		
		usort($drives, array('OS_Windows', 'compare_drives'));
		
		return $drives;
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
		
		usort($volumes, array('OS_Windows', 'compare_mounts'));
		
		return $volumes;
	}
	
	/**
	 * getDevs 
	 * 
	 * @access private
	 * @return array of devices
	 */
	private function getDevs() {
		
		$devs = array();
		
		foreach($this->wmi['PATH Win32_PnPEntity'] as $pnpdev) {
			$type = reset(explode("\\", $pnpdev['DeviceID']));
			if(($type != 'USB' && $type != 'PCI') || (empty($pnpdev['Caption']) || $pnpdev['Manufacturer'][0] == '(')) {
				continue;
			}
			$devs[] = array(
				'vendor' => $pnpdev['Manufacturer'],
				'device' => $pnpdev['Caption'],
				'type' => $type
			);
		}
		
		// Sort by 1. Type, 2. Vendor
		usort($devs, array('OS_Windows', 'compare_devices'));
		
		return $devs;
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
		
		$load = array();
		foreach ($this->wmi['CPU'] as $cpu) {
			$load[] = $cpu['LoadPercentage'];
		}
		return (array_sum($load) / count($load)) . "%";
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
		
		$result = array(
			'exists' => true,
			'totals' => array(
				'running' => 0,
				'zombie' => 0,
				'sleeping' => 0,
				'stopped' => 0,
			),
			'proc_total' => 0,
			'threads' => 0
		);
		
		foreach($this->wmi['PROCESS'] as $proc) {
			$result['threads'] += (int)$proc['ThreadCount'];
			$result['totals']['running']++;
		}
		
		$result['proc_total'] = $result['totals']['running'];
		
		return $result;
	}
	
	/**
	 * WMIRequest
	 * 
	 * @access private
	 * @param string $name alias
	 * @return array results
	 */
	private function WMIRequest($name) {
	
		$cachefile = CACHE_PATH . "wmic_cache_$name.json";
		
		if ($this->settings['wmi_cache']['active']  && file_exists($cachefile) && time() - filemtime($cachefile) < @$this->settings['wmi_cache'][$name]) {
			$cache = file_get_contents($cachefile);
			$cache = json_decode($cache, true);
			return $cache;
		}
		
		$this->error->add('Linfo Windows wmic parser', "File not cached: $name");
		
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
				$columns[] = array("start" => $start, "end" => -1, "caption" => trim(substr($header, $start)));
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
				$len = ($c['end'] == -1) ? strlen($e) - $start : $c['end'] - $start;
				$s = substr($e, $start, $len);
				if ($s) {
					$s = trim($s);
					// Convert to bool
					if ($s == "TRUE") {
						$s = true;
					} else if ($s == "FALSE") {
						$s = false;
					}/* else if(function_exists('mb_convert_encoding')) {
						$s = mb_convert_encoding($s, 'CP850');
					}*/
					$a[$c['caption']] = $s;
				}
			}
			$table[] = $a;
		}
		
		if($this->settings['wmi_cache']['active']) {
			file_put_contents($cachefile, json_encode($table));
		}
		
		return $table;
	}
	
	/**
	 * @ignore
	 */
	static function compare_devices($a, $b) {
		
		if ($a['type'] == $b['type']) {
			if ($a['vendor'] == $b['vendor']) {
				if ($a['device'] == $b['device']) {
					return 0;
				}
				return ($a['device'] > $b['device']) ? 1 : -1;
			}
			return ($a['vendor'] > $b['vendor']) ? 1 : -1;
		}
		return ($a['type'] > $b['type']) ? 1 : -1;
	}
	
	/**
	 * @ignore
	 */
	static function compare_drives($a, $b) {
		
		if ($a['device'] == $b['device']) {
			return 0;
		}
		return ($a['device'] > $b['device']) ? 1 : -1;
	}
	
	/**
	 * @ignore
	 */
	static function compare_mounts($a, $b) {
		
		if ($a['mount'] == $b['mount']) {
			return 0;
		}
		return ($a['mount'] > $b['mount']) ? 1 : -1;
	}
}
