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
 * Use this class for all error handling
 */
class LinfoError {
	
	/*
	 * Singleton
	 */
	protected static $_fledging;

	public function Fledging($settings = null) {
		$c = __CLASS__;
		if (!isset(self::$_fledging))
			self::$_fledging = new $c($settings);
		return self::$_fledging;
	}
	
	/*
	 * Store error messages here
	 */
	private $_errors = array();

	/*
	 * Add an error message
	 */
	public function add($whence, $message) {
		$this->_errors[] = array($whence, $message);
	}

	/*
	 * Get all error messages
	 */
	public function show() {
		return $this->_errors;
	}

	/*
	 * How many are there?
	 */
	 public function num() {
		return count($this->_errors);
	 }
}
