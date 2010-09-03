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
 * The BSD os's are largely similar and thus draw from this class.
*/

abstract class OS_BSD_Common {
	
	// Store these
	protected
		$settings,
		$exec,
		$error,
		$dmesg,
		$sysctl = array();
	
	// Start us off
	protected function __construct($settings) {

		// Localize settings
		$this->settings = $settings;
		
		// Localize error handler
		$this->error = LinfoError::Fledging();
		
		// Exec running
		$this->exec = new CallExt;

		// Get dmesg
		$this->loadDmesg();
	}
	
	// Save dmesg
	protected function loadDmesg() {
		$this->dmesg = getContents('/var/run/dmesg.boot');
	}

	// Use sysctl to get something, and cache result.
	protected function getSysCTL($keys) {
		$keys = (array) $keys;
		$return = array();
		foreach ($keys as $i => $key) {
			if (array_key_exists($key, $this->sysctl)) {
				$return[$key] = $this->sysctl[$key];
				unset($keys[$i]);
			}
			else {
				try {
					$sys = $this->exec->exec('sysctl', '-n '.$key);
					$this->sysctl[$key] = $sys;
					$return[$key] = $sys;
				}
				catch(CallExtException $e) {}
			}
		}
		return count($return) == 1 ? current($return) : $return;
	}

}
