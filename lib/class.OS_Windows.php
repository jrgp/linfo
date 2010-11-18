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
		$wmi, $windows_version;

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
		
		// Get WMI instance
		$this->wmi = new COM('winmgmts:{impersonationLevel=impersonate}//./root/cimv2');
		
		if (!is_object($this->wmi)) {
			throw new GetInfoException('This needs access to WMI. Please enable DCOM in php.ini and allow the current user to access the WMI DCOM object.');
		}
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
		
		foreach ($this->wmi->ExecQuery("SELECT Caption FROM Win32_OperatingSystem") as $os) {
			return $os->Caption;
		}
		return "Unknown";
	}
	
	/**
	 * getKernel 
	 * 
	 * @access private
	 * @return string kernel version
	 */
	private function getKernel() {
	
		foreach ($this->wmi->ExecQuery("SELECT WindowsVersion FROM Win32_Process WHERE Handle = 0") as $process) {
			$this->windows_version = $process->WindowsVersion;
			return $process->WindowsVersion;
		}
		return "Unknown";
	}
	
	/**
	 * getHostName 
	 * 
	 * @access private
	 * @return string the host name
	 */
	private function getHostName() {
		
		foreach ($this->wmi->ExecQuery("SELECT Name FROM Win32_ComputerSystem") as $cs) {
			return $cs->Name;
		}
		return "Unknown";
	}
	
	/**
	 * getRam 
	 * 
	 * @access private
	 * @return array the memory information
	 */
	private function getRam(){
		
		$total_memory = 0;
		$free_memory = 0;
		
		foreach ($this->wmi->ExecQuery("SELECT TotalPhysicalMemory FROM Win32_ComputerSystem") as $cs) {
			$total_memory = $cs->TotalPhysicalMemory;
			break;
		}
		
		foreach ($this->wmi->ExecQuery("SELECT FreePhysicalMemory FROM Win32_OperatingSystem") as $os) {
			$free_memory = $os->FreePhysicalMemory;
			break;
		}
		
		return array(
			'type'  => 'Physical',
			'total' => $total_memory,
			'free'  => $free_memory * 1024
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
		$alt = false;
		$object = $this->wmi->ExecQuery("SELECT Name, Manufacturer, CurrentClockSpeed, NumberOfLogicalProcessors FROM Win32_Processor");
		
		if (!is_object($object)) {
			$object = $this->wmi->ExecQuery("SELECT Name, Manufacturer, CurrentClockSpeed FROM Win32_Processor");
			$alt = true;
		}

		foreach($object as $cpu) {
			$curr = array(
				'Model' => $cpu->Name,
				'Vendor' => $cpu->Manufacturer,
				'MHz' => $cpu->CurrentClockSpeed,
			);
			$curr['Model'] = $cpu->Name;
			
			if (!$alt) {
				for ($i = 0; $i < $cpu->NumberOfLogicalProcessors; $i++)
					$cpus[] = $curr;
			} else {
				$cpus[] = $curr;
			}
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
		
		$booted_str = "";
		
		foreach ($this->wmi->ExecQuery("SELECT LastBootUpTime FROM Win32_OperatingSystem") as $os) {
			$booted_str = $os->LastBootUpTime;
			break;
		}
		
		$booted = array(
			'year'   => substr($booted_str, 0, 4),
			'month'  => substr($booted_str, 4, 2),
			'day'    => substr($booted_str, 6, 2),
			'hour'   => substr($booted_str, 8, 2),
			'minute' => substr($booted_str, 10, 2),
			'second' => substr($booted_str, 12, 2)
		);
		$booted_ts = mktime($booted['hour'], $booted['minute'], $booted['second'], $booted['month'], $booted['day'], $booted['year']);
		
		return seconds_convert(time() - $booted_ts) . '; booted ' . date('m/d/y h:i A', $booted_ts);
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
		
		foreach ($this->wmi->ExecQuery("SELECT DiskIndex, Size, DeviceID, Type FROM Win32_DiskPartition") as $partition) {
			$partitions[$partition->DiskIndex][] = array(
				'size' => $partition->Size,
				'name' => $partition->DeviceID . ' (' . $partition->Type . ')'
			);
		}
		
		foreach ($this->wmi->ExecQuery("SELECT Caption, DeviceID, Index, Size FROM Win32_DiskDrive") as $drive) {
			$drives[] = array(
				'name'   =>  $drive->Caption,
				'vendor' => reset(explode(" ", $drive->Caption)),
				'device' => $drive->DeviceID,
				'reads'  => false,
				'writes' => false,
				'size'   => $drive->Size,
				'partitions' => array_key_exists($drive->Index, $partitions) && is_array($partitions[$drive->Index]) ? $partitions[$drive->Index] : false 
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
		
		if ($this->windows_version > "6.1.0000") {
			$object = $this->wmi->ExecQuery("SELECT Automount, BootVolume, Compressed, IndexingEnabled, Label, Caption, FileSystem, Capacity, FreeSpace, DriveType FROM Win32_Volume");
		} else {
			$object = $this->wmi->ExecQuery("SELECT Compressed, Name, FileSystem, Size, FreeSpace, DriveType FROM Win32_LogicalDisk");
		}
		
		foreach($object as $volume) {
			$options = array();
			if ($this->windows_version > "6.1.0000") {
				if ($volume->Automount) {
					$options[] = 'automount';
				}
				if ($volume->BootVolume) {
					$options[] = 'boot';
				}
				if ($volume->IndexingEnabled) {
					$options[] = 'indexed';
				}
			}
			if ($volume->Compressed) {
				$options[] = 'compressed';
			}
			$capacity = ($this->windows_version > "6.1.0000") ? $volume->Capacity : $volume->Size;
			$label    = ($this->windows_version > "6.1.0000") ? $volume->Label : $volume->Name;
			$mount    = ($this->windows_version > "6.1.0000") ? $volume->Caption : $label . '\\';
			$a = array(
				'device' => false,
				'label' => $label,
				'devtype' => '',
				'mount' => $mount,
				'type' => $volume->FileSystem,
				'size' => $capacity,
				'used' => $capacity - $volume->FreeSpace,
				'free' => $volume->FreeSpace,
				'free_percent' => 0,
				'used_percent' => 0,
				'options' => $options
			);
			
			switch ($volume->DriveType) {
				case 2:
					$a['devtype'] = 'Removable drive';
					break;
				case 3:
					$a['devtype'] = 'Fixed drive';
					break;
				case 4:
					$a['devtype'] = 'Remote drive';
					break;
				case 5:
					$a['devtype'] = 'CD-ROM';
					break;
				case 6:
					$a['devtype'] = 'RAM disk';
					break;
				default:
					$a['devtype'] = 'Unknown';
					break;
			}
			
			if ($capacity != 0) {
				$a['free_percent'] = round($volume->FreeSpace / $capacity, 2) * 100;
				$a['used_percent'] = round(($capacity - $volume->FreeSpace) / $capacity, 2) * 100;
			}
			
			$volumes[] = $a;
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
		
		foreach($this->wmi->ExecQuery("SELECT DeviceID, Caption, Manufacturer FROM Win32_PnPEntity") as $pnpdev) {
			$type = reset(explode("\\", $pnpdev->DeviceID));
			if (($type != 'USB' && $type != 'PCI') || (empty($pnpdev->Caption) || $pnpdev->Manufacturer[0] == '(')) {
				continue;
			}
			$devs[] = array(
				'vendor' => $pnpdev->Manufacturer,
				'device' => $pnpdev->Caption,
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
		foreach ($this->wmi->ExecQuery("SELECT LoadPercentage FROM Win32_Processor") as $cpu) {
			$load[] = $cpu->LoadPercentage;
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
	
		$return = array();
		$i = 0;
		
		if ($this->windows_version > "6.1.0000") {
			$object = $this->wmi->ExecQuery("SELECT AdapterType, Name, NetConnectionStatus, GUID FROM Win32_NetworkAdapter WHERE PhysicalAdapter = TRUE");
		} else {
			$object = $this->wmi->ExecQuery("SELECT AdapterType, Name, NetConnectionStatus FROM Win32_NetworkAdapter WHERE NetConnectionStatus != NULL");
		}
		
		
		foreach ($object as $net) {
			// Save and get info for each
			$return[$net->Name] = array(
				'recieved' => array(
					'bytes' => 0,
					'errors' => 0,
					'packets' => 0
				),
				'sent' => array(
					'bytes' => 0,
					'errors' => 0,
					'packets' => 0
				),
				'state' => 0,
				'type' => $net->AdapterType
			);
			switch($net->NetConnectionStatus) {
				case 0:
					$return[$net->Name]['state'] = 'down';
					break;
				case 1:
					$return[$net->Name]['state'] = 'Connecting';
					break;
				case 2:
					$return[$net->Name]['state'] = 'up';
					break;
				case 3:
					$return[$net->Name]['state'] = 'Disconnecting';
					break;
				case 4:
					$return[$net->Name]['state'] = 'down'; // MSDN 'Hardware not present'
					break;
				case 5:
					$return[$net->Name]['state'] = 'Hardware disabled';
					break;
				case 6:
					$return[$net->Name]['state'] = 'Hardware malfunction';
					break;
				case 7:
					$return[$net->Name]['state'] = 'Media disconnected';
					break;
				case 8:
					$return[$net->Name]['state'] = 'Authenticating';
					break;
				case 9:
					$return[$net->Name]['state'] = 'Authentication succeeded';
					break;
				case 10:
					$return[$net->Name]['state'] = 'Authentication failed';
					break;
				case 11:
					$return[$net->Name]['state'] = 'Invalid address';
					break;
				case 12:
					$return[$net->Name]['state'] = 'Credentials required';
					break;
				default:
					$return[$net->Name]['state'] = 'unknown';
					break;
			}
			// @Microsoft: An index would be nice here indeed.
			if ($this->windows_version > "6.1.0000") {
				$canonname = preg_replace("/[^A-Za-z0-9- ]/", "_", $net->Name);
				$isatapname = "isatap." . $net->GUID;
				$result = $this->wmi->ExecQuery("SELECT BytesReceivedPersec, PacketsReceivedErrors, PacketsReceivedPersec, BytesSentPersec, PacketsSentPersec FROM Win32_PerfRawData_Tcpip_NetworkInterface WHERE Name = '$canonname' OR Name = '$isatapname'");
			} else {
				$canonname = preg_replace("/[^A-Za-z0-9- ]/", "_", $net->Name);
				$result = $this->wmi->ExecQuery("SELECT BytesReceivedPersec, PacketsReceivedErrors, PacketsReceivedPersec, BytesSentPersec, PacketsSentPersec FROM Win32_PerfRawData_Tcpip_NetworkInterface WHERE Name = '$canonname'");
			}
			foreach ($result as $netspeed) {
				$return[$net->Name]['recieved'] = array(
					'bytes' => (int)$netspeed->BytesReceivedPersec,
					'errors' => (int)$netspeed->PacketsReceivedErrors,
					'packets' => (int)$netspeed->PacketsReceivedPersec
				);
				$return[$net->Name]['sent'] = array(
					'bytes' => (int)$netspeed->BytesSentPersec,
					'erros' => 0,
					'packets' => (int)$netspeed->PacketsSentPersec
				);
			}
			$i++;
		}
		
		return $return;
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
		
		$cards = array();
		$i = 0;
		
		foreach ($this->wmi->ExecQuery("SELECT Caption, Manufacturer FROM Win32_SoundDevice") as $card) {
			$cards[] = array(
				'number' => $i,
				'vendor' => $card->Manufacturer,
				'card' => $card->Caption
			);
			$i++;
		}
		
		return $cards;
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
			'proc_total' => 0,
			'threads' => 0
		);
		
		foreach($this->wmi->ExecQuery("SELECT ThreadCount FROM Win32_Process") as $proc) {
			$result['threads'] += (int)$proc->ThreadCount;
			$result['proc_total']++;
		}
		
		
		return $result;
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
