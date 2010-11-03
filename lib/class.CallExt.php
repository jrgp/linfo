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
 * Exception for CallExt class
 */
class CallExtException extends Exception {}

/**
 * Class used to call external programs 
 */
class CallExt {
	
	/**
	 * Store results of commands here to avoid calling them more than once
	 * 
	 * @var array
	 * @access protected
	 */
	protected $cliCache = array();

	/**
	 * Store paths to look for executables here
	 * 
	 * @var array
	 * @access protected
	 */
	protected $searchPaths = array();

	/**
	 * Say where we'll search for execs
	 *
	 * @param array $paths list of paths
	 */
	public function setSearchPaths($paths) {

		// Make sure they all have a trailing slash
		foreach ($paths as $k => $v)
			$paths[$k] .= substr($v, -1) == '/' ? '' : '/';

		// Save them
		$this->searchPaths = $paths;
	}
	
	/**
	 * Run a command and cache its output for later
	 *
	 * @throws CallExtException
	 * @param string $name name of executable to call
	 * @param string $switches command arguments
	 */
	public function exec($name, $switches = '') {
		
		// Have we gotten it before?
		if (array_key_exists($name.$switches, $this->cliCache))
			return $this->cliCache[$name.$switches];
		
		// Try finding the exec
		foreach ($this->searchPaths as $path) {
			if (is_file($path.$name) && is_executable($path.$name)) {
				$command = "$path$name $switches";
				$result = `$command`;
				$this->cliCache[$name.$switches] = $result;
				return $result;
				break;
			}
		}

		// Never got it
		throw new CallExtException('Exec `'.$name.'\' not found');
	}
}
