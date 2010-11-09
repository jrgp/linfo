<?php

/**
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
*/

/**
 * Keep out hackers...
 */
defined('IN_INFO') or exit;

/**
 * Deal with pci.ids and usb.ids workings
 * @author Joe Gillotti
 */
class HW_IDS {

	private
		$_usb_file = '',
		$_pci_file = '',
		$_cache_file = '',
		$_existing_cache_vals = array(),
		$_usb_entries = array(),
		$_pci_entries = array(),
		$_usb_devices = array(),
		$_pci_devices = array(),
		$_result = array();

	/**
	 * Constructor
	 *
	 * @access public
	 */
	public function __construct($usb_file, $pci_file) {
		$this->_pci_file = $pci_file;
		$this->_usb_file = $usb_file;
		$this->_cache_file = CACHE_PATH.'/ids_cache.json';
		$this->_populate_cache();
	}

	/**
	 * Run the cache file
	 *
	 * @access private
	 */
	private function _populate_cache() {
		if (function_exists('json_decode') && is_readable($this->_cache_file))
			$this->_existing_cache_vals = (array) @json_decode(getContents($this->_cache_file, ''), true);
	}
	
	/**
	 * Get the USB ids from /sys
	 *
	 * @access private
	 */
	private function _fetchUsbIds() {
		$usb_paths = (array) @glob('/sys/bus/usb/devices/*/uevent', GLOB_NOSORT);
		$num_usb_paths = count($usb_paths);
		for ($i = 0; $i < $num_usb_paths; $i++) {
			$path = $usb_paths[$i];
			if (is_readable($path) && preg_match('/^product=([^\/]+)\/([^\/]+)\/[^$]+$/m', strtolower(getContents($path)), $match) == 1) {
				$this->_usb_entries[str_pad($match[1], 4, '0', STR_PAD_LEFT)][str_pad($match[2], 4, '0', STR_PAD_LEFT)] = 1;
			}
		}
	}

	/**
	 * Get the PCI ids from /sys
	 *
	 * @access private
	 */
	private function _fetchPciIds() {
		$pci_paths = (array) @glob('/sys/bus/pci/devices/*/uevent', GLOB_NOSORT);
		$num_pci_paths = count($pci_paths);
		for ($i = 0; $i < $num_pci_paths; $i++) {
			$path = $pci_paths[$i];
			if (is_readable($path) && preg_match('/pci\_(?:subsys_)?id=(\w+):(\w+)/', strtolower(getContents($path)), $match) == 1) {
				$this->_pci_entries[$match[1]][$match[2]] = 1;
			}
			else {
				$path = dirname($path);
				$vendor = getContents($path.'/subsystem_vendor', false);
				$device = getContents($path.'/subsystem_device', false);
				if ($vendor !== false && $device !== false) {
					$vendor = str_pad(strtoupper(substr($vendor, 2)), 4, '0', STR_PAD_LEFT);
					$device = str_pad(strtoupper(substr($device, 2)), 4, '0', STR_PAD_LEFT);
					$this->_pci_entries[$vendor][$device] = 1;
				}
			}
		}
	}

	/**
	 * Use the pci.ids file to translate the ids to names
	 *
	 * @access private
	 */
	private function _fetchPciNames() {
		for ($v = false, $file = @fopen($this->_pci_file, 'r'); $file != false && $contents = fgets($file);) {
				if (preg_match('/^(\S{4})\s+([^$]+)$/', $contents, $vend_match) == 1) {
					$v = $vend_match;
				}
				elseif(preg_match('/^\s+(\S{4})\s+([^$]+)$/', $contents, $dev_match) == 1) {
					if($v && isset($this->_pci_entries[strtolower($v[1])][strtolower($dev_match[1])])) {
						$this->_pci_devices[$v[1]][$dev_match[1]] = array('vendor' => rtrim($v[2]), 'device' => rtrim($dev_match[2]));
					}
				}
		}
		$file && @fclose($file);
	}
	
	/**
	 * Use the usb.ids file to translate the ids to names
	 *
	 * @access private
	 */
	private function _fetchUsbNames() {
		for ($v = false, $file = @fopen($this->_usb_file, 'r'); $file != false && $contents = fgets($file);) {
				if (preg_match('/^(\S{4})\s+([^$]+)$/', $contents, $vend_match) == 1) {
					$v = $vend_match;
				}
				elseif(preg_match('/^\s+(\S{4})\s+([^$]+)$/', $contents, $dev_match) == 1) {
					if($v && isset($this->_usb_entries[strtolower($v[1])][strtolower($dev_match[1])])) {
						$this->_usb_devices[strtolower($v[1])][$dev_match[1]] = array('vendor' => rtrim($v[2]), 'device' => rtrim($dev_match[2]));
					}
				}
		}
		$file && @fclose($file);
	}

	/**
	 * Decide if the cache file is sufficient enough to not parse the ids files
	 *
	 * @access private
	 */
	private function _is_cache_worthy() {
		$pci_good = true;
		foreach(array_keys($this->_pci_entries) as $vendor) {
			foreach (array_keys($this->_pci_entries[$vendor]) as $dever) {
				if (!isset($this->_existing_cache_vals['hw']['pci'][$vendor][$dever])) {
					$pci_good = false;
					break 2;
				}
			}
		}
		$usb_good = true;
		foreach(array_keys($this->_usb_entries) as $vendor) {
			foreach (array_keys($this->_usb_entries[$vendor]) as $dever) {
				if (!isset($this->_existing_cache_vals['hw']['usb'][$vendor][$dever])) {
					$usb_good = false;
					break 2;
				}
			}
		}
		return array('pci' => $pci_good, 'usb' => $usb_good);
	}

	/*
	 * Write cache file with latest shit
	 *
	 * @access private
	 */
	private function _write_cache() {
		if (function_exists('json_encode') && is_writable(CACHE_PATH))
			@file_put_contents($this->_cache_file, json_encode(array(
				'hw' => array(
					'pci' => $this->_pci_devices,
					'usb' => $this->_usb_devices
				)
			)));
	}

	/**
	 * Do its goddam job
	 *
	 * @access public
	 */
	public function work() {
		$this->_fetchPciIds();
		$this->_fetchUsbIds();
		$worthiness = $this->_is_cache_worthy();
		$save_cache = false;
		if (!$worthiness['pci']) {
			$save_cache = true;
			$this->_fetchPciNames();
		}
		else 
			$this->_pci_devices = $this->_existing_cache_vals['hw']['pci'];
		if (!$worthiness['usb']) {
			$save_cache = true;
			$this->_fetchUsbNames();
		}
		else 
			$this->_usb_devices = $this->_existing_cache_vals['hw']['usb'];
		if ($save_cache)
			$this->_write_cache();
	}

	/**
	 * Compile and return results
	 *
	 * @access public
	 */
	 public function result() {
		foreach (array_keys((array)$this->_pci_devices) as $v) 
			foreach ($this->_pci_devices[$v] as $d)
				$this->_result[] = array('vendor' => $d['vendor'], 'device' => $d['device'], 'type' => 'PCI');
		foreach (array_keys((array)$this->_usb_devices) as $v) 
			foreach ($this->_usb_devices[$v] as $d)
				$this->_result[] = array('vendor' => $d['vendor'], 'device' => $d['device'], 'type' => 'USB');
		return $this->_result;
	 }
}
