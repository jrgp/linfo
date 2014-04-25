<?php

/*

This shows a cursory list of running VMs managed by libvirt and their stats. 
Requires libvirt php extension (http://libvirt.org/php/)

To enable this extension, add/tweak the following to your config.inc.php

$settings['extensions']['libvirt'] = true;
$settings['libvirt_connection'] = array(
        'url' => 'qemu:///system',
        'credentials' => NULL
);


*/

/**
 * This file is part of Linfo (c) 2013 Joseph Gillotti.
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
 * Get status on libvirt VMs
 */
class ext_libvirt implements LinfoExtension {

	const
		LINFO_MIN_VERSION = '1.5';
	
	private
		$LinfoError,
		$VMs = array(),
		$connection = false,
		$connectionSettings = array(),
		$res = false;

	public function __construct() {

		global $settings;

		$this->LinfoError = LinfoError::Fledging();

		$this->connectionSettings = $settings['libvirt_connection'];
	}

	private function connect() {

		if (!($this->connection =
			@libvirt_connect($this->connectionSettings['url'], true))) {
			$this->LinfoError->add('libvirt extension', 'Error connecting');
			$this->res = false;
			return false;
		}

		return true;
	}

	public function work() {

		$t = new LinfoTimerStart('libvirt extension');

		if (!extension_loaded('libvirt')) {
			$this->LinfoError->add('libvirt extension', 'Libvirt PHP extension not installed');
			$this->res = false;
			return false;
		}

		if (!$this->connect())
			return false;

		if (!($doms = libvirt_list_domains($this->connection))) {
			$this->LinfoError->add('libvirt extension', 'Failed getting domain list');
			$this->res = false;
			return false;
		}

		foreach ($doms as $name) {
		    
			if (!($domain = libvirt_domain_lookup_by_name($this->connection, $name)))
				continue;

			if (!($info =  libvirt_domain_get_info($domain)) || !is_array($info))
				continue;

			$info['storage'] = array();

			foreach ((array) @libvirt_domain_get_disk_devices($domain) as $blockName) {
				if (!is_string($blockName))
					continue;

				if (!($blockInfo = libvirt_domain_get_block_info($domain, $blockName)) || !is_array($blockInfo))
					continue;

				$info['storage'][] = $blockInfo;
			}

			$this->VMs[$name] = $info;
		}

		$this->res = true;
	}

	public function result() {
		if (!$this->res)
			return false;

		// Start showing connections
		$rows[] = array(
			'type' => 'header',
			'columns' =>
				array(
				'VM Name',
				'Status',
				'RAM Allocation',
				'CPUs',
				'CPU Time',
				'Block Storage'
			)
		);

		foreach ($this->VMs as $name => $info) {

			$disks = array();

			foreach ($info['storage'] as $disk) {
				$disks[] = $disk['device'].': '.$disk['file'].' ('.byte_convert($disk['capacity'], 2).')';
			}

			$rows[] = array(
				'type' => 'values',
				'columns' => array(
					$name, 
					$info['state'] == 1 ? '<span style="color: green;">On</span>' : '<span style="color: maroon;">Off</span>',
					byte_convert($info['memory']*1024, 2),
					$info['nrVirtCpu'],
					$info['cpuUsed'] ? $info['cpuUsed'] : 'N/A',
					$disks ? implode('<br />', $disks) : 'None'
				)
			);
		}

		// Give it off
		return array(
			'root_title' => 'libvirt Virtual Machines',
			'rows' => $rows
		);
	}
}

