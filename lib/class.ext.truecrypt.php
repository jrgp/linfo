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
 * Get status on truecrypt volumes. very experimental
 */
class ext_truecrypt implements LinfoExtension {

	// Store these tucked away here
	private
		$_CallExt,
		$_LinfoError,
		$_res;

	// Localize important classes
	public function __construct() {
		$this->_LinfoError = LinfoError::Fledging();
		$this->_CallExt = new CallExt;
		$this->_CallExt->setSearchPaths(array('/usr/bin', '/usr/local/bin', '/sbin', '/usr/local/sbin'));
	}

	// call lpq and parse it
	private function _call() {
		
		// Time this
		$t = new LinfoTimerStart('Truecrypt Extension');

		// Deal with calling it
		try {
			$result = $this->_CallExt->exec('truecrypt', '-l');
		}
		catch (CallExtException $e) {
			// messed up somehow
			$this->_LinfoError->add('Truecrypt Extension', $e->getMessage());
			$this->_res = false;

			// Don't bother going any further
			return false;
		}

		// Get matches
		if (preg_match_all('/^(\d+): (\S+) (\S+) (\S+)/m', $result, $matches) == 0) {
			$this->_res = false;
			return false;
		}

		// Save result set
		foreach ($matches as $m) {
			$this->_res[] = array(
				'slot' => $m[1],
				'volume' => $m[2],
				'virtual_device' => $m[3],
				'mount_point' => $m[4],
			);
		}

		// Wish fullfilment
		$this->_res = true;

		// Apparent success
		return true;
	}

	// Called to get working
	public function work() {
		$this->_call();
	}

	// Get result. Essentially take results and make it usable by the create_table function
	public function result() {

		// Don't bother if it didn't go well
		if ($this->_res == false)
			return false;

		// it did; continue
		else {

			// Store rows here
			$rows = array();

			// start off volume list
			$rows[] = array (
				'type' => 'header',
				'columns' => array(
					'Slot',
					'Device',
					'Volume',
					'Mount Point',
				)
			);


			// show printers if we have them
			if (count($this->_res) == 0)
				$rows[] = array('type' => 'none', 'columns' => array(array(4, 'None found')));
			else {
				foreach ($this->_res as $vol)
					$rows[] = array(
						'type' => 'values',
						'columns' => array(
							$vol['slot'],
							$vol['volume'],
							$vol['virtual_device'],
							$vol['mount_point'],
						)
					);
			}

			// Give info
			return array(
				'root_title' => 'Truecrypt Volumes',
				'rows' => $rows
			);
		}
	}
}
