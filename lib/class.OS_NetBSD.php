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
 * NetBSD info class. Should be similar to freebsd's
 */

class OS_NetBSD {

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
		$this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/pkg/bin', '/usr/sbin'));
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
			'Network Devices' => empty($this->settings['show']) ? array() : $this->getNet(),# lacks status and type
			'RAM' => empty($this->settings['show']) ? array() : $this->getRam(), 		# TODO
			'Devices' => empty($this->settings['show']) ? array() : $this->getDevs(), 	# TODO
			'HD' => empty($this->settings['show']) ? '' : $this->getHD(), 			# TODO
			'RAID' => empty($this->settings['show']) ? '' : $this->getRAID(),	 	# TODO 
			'Battery' => empty($this->settings['show']) ? array(): $this->getBattery(),  	# TODO
			'CPU' => empty($this->settings['show']) ? array() : $this->getCPU(), 		# TODO
			'Temps' => empty($this->settings['show']) ? array(): $this->getTemps(), 	# TODO
		);
	}

	private function getOS(){return 'NetBSD';}
	private function getKernel(){return php_uname('r');}
	private function getHostName(){return php_uname('n');}
	private function getMounts(){
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Mounted file systems');
		try {
			$res = $this->exec->exec('mount');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `mount` command');
			return array();
		}
		if(@preg_match_all('/^(\S+) on (\S+) type (\S+)/m', $res, $mount_match, PREG_SET_ORDER) == 0)
			return array();

		$mounts = array();

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
		
		return $mounts;
	}
	private function getLoad(){
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Load Averages');
		try {
			$res = $this->exec->exec('sysctl', 'vm.loadavg');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `sysctl` to get load');
			return array();
		}
		if (@preg_match('/([\d\.]+) ([\d\.]+) ([\d\.]+)$/', $res, $load_match))
			return array(
				'now' => $load_match[1],
				'5min' => $load_match[2],
				'15min' => $load_match[3]
			);
		else
			return false;
	}
	private function getUpTime(){
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Uptime');
		try {
			$res = $this->exec->exec('sysctl', 'kern.boottime');
		}
		catch (CallExtException $e) {
			$this->error->add('Linfo Core', 'Error running `sysctl` to get boot time');
			return array();
		}
		if (@preg_match('/^kern.boottime = ([^$]+)$/', $res, $time_match) != 1)
			return false;
		$booted = strtotime($time_match[1]);
		return seconds_convert(time() - $booted) . '; booted ' . date('m/d/y h:i A', $booted);
	}
	private function getNet(){
		// Time?
		if (!empty($this->settings['timer']))
			$t = new LinfoTimerStart('Network Devices');
		try {
			$res = $this->exec->exec('netstat', '-nbdi');
		}
		catch(CallExtException $e) {
			$this->error->add('Linfo Core', 'Error using `netstat` to get network info');
			return array();
		}
		if (preg_match_all('/^(\S+)\s+\d+\s+<Link>\s+[a-z0-9\:]+\s+(\d+)\s+(\d+)\s+\d+$/m', $res, $net_matches, PREG_SET_ORDER) == 0)
			return array();
		$nets = array();
		foreach($net_matches as $net)
			$nets[$net[1]] = array(
				'recieved' => array(
					'bytes' => $net[2],
				),
				'sent' => array(
					'bytes' => $net[3],
				),
				'state' => 'Unknown', // TODO
				'type' => 'Unknown' // TODO
			);
		return $nets;
	}
	private function getRam(){}
	private function getDevs(){}
	private function getHD(){}
	private function getRAID(){}
	private function getBattery(){}
	private function getCPU(){}
	private function getTemps(){}
}
