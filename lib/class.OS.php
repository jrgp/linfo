<?php

/**
 * This file is part of Linfo (c) 2014 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.	If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Keep out hackers...
 */
defined('IN_LINFO') or exit;

abstract class OS {
	public function __call($name, $args) {
		throw new LinfoFatalException('Method '.$name.' not present.');
	}

	public function getAccessedIP() {
		return isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] ? $_SERVER['SERVER_ADDR'] : isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
	}

	public function getWebService() {
		return isset($_SERVER['SERVER_SOFTWARE']) && $_SERVER['SERVER_SOFTWARE'] ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
	}

	public function getPhpVersion() {
		return phpversion();
	}	
}
