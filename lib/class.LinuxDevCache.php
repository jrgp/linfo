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

// EVERYTHING HERE CURRENTLY UNUSED QUESTIONABLE RUBBISH!


/*
 * Deal with caching
 */
class LinuxDevCacheException extends Exception {}
class LinuxDevCache {
	protected $settings, $file, $handle;
	const TABLE = 'LinuxDevCache';
	public function __construct($settings) {
		$this->settings = $settings;
		if (!extension_loaded('SQLite'))
			throw new LinuxDevCacheException('SQLite not loaded');
		if (empty($this->settings['cache']))
			throw new LinuxDevCacheException('Caching not enabled');

		if (!is_writable($this->settings['cache_dir']) || !is_readable($this->settings['cache_dir']))
			throw new LinuxDevCacheException('Cache dir not usable');

		$this->file = $this->settings['cache_dir'].'LinuxDevCache.sqlite';

		if (!($this->handle = @sqlite_open($this->file, 0666, $serror)))
			throw new LinuxDevCacheException('Error loading sqlite cache file: '.$serror);
	}

	public function ensureTable() {
		$create_sql = sprintf("CREATE TABLE %s (vendid INTEGER, devid INTEGER, type VARCHAR, vendname VARCHAR, devname VARCHAR, PRIMARY KEY (vendid, devid, type))", self::TABLE);
		$check_sql = sprintf("SELECT name FROM sqlite_master WHERE type='table' and name = '%s'", self::TABLE);
		$check_res = sqlite_query($this->handle, $check_sql);
		if (sqlite_num_rows($check_res) == 0) {
			$create_res = sqlite_exec($this->handle, $create_sql);
			if ($create_res == FALSE)
				throw new LinuxDevCacheException('Error creating table');
		}
	}

	public function getVals() {
		$get_sql = sprintf("SELECT vendid, devid, type, vendname, devname FROM %s", self::TABLE);
		$q_res = sqlite_query($this->handle, $get_sql);
		$res = sqlite_fetch_all($q_res, SQLITE_ASSOC);
		return $res;
	}

	public function saveVals($values)  {
		foreach ($values as $val) {
			$sql = sprintf("REPLACE INTO %s (vendid, devid, type, vendname, devname) VALUES (%d, %d, '%s', '%s', '%s')",
				self::TABLE,
				$val['vendid'],
				$val['devid'],
				$val['type'],
				sqlite_escape_string($val['vendor']),
				sqlite_escape_string($val['device'])
			);
			if (!sqlite_exec($this->handle, $sql, $errstr))
				echo $errstr;
		}
	}
}
