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
 *
 * Get info on a cups install by running lpq
 *
 */
class ext_cups {

	private
		$_CallExt,
		$_LinfoError,
		$_res;

	public function __construct() {
		$this->_LinfoError = LinfoError::Fledging();
		$this->_CallExt = new CallExt;
		$this->_CallExt->setSearchPaths(array('/usr/bin', '/usr/local/bin', '/sbin', '/usr/local/sbin'));
	}

	private function _call() {
		try {
			$result = $this->_CallExt->exec('lpq');
		}
		catch (CallExtException $e) {
			$this->_LinfoError->add('CUPS Extension', $e->getMessage());
			$this->_res = false;
			return false;
		}

		$lines = explode("\n", $result);
		$printers = array();
		$queue = array();
		$begin_queue_list = false;
		for ($i = 0, $num = count($lines); $i < $num; $i++) {
			$lines[$i] = trim($lines[$i]);
			if ($lines[$i] == 'no entries') {
				break;	
			}
			elseif (preg_match('/^(.+)+ is (ready|ready and printing|not ready)$/', $lines[$i], $printers_match) == 1) {
				$printers[] = array(
					'name' => str_replace('_', ' ', $printers_match[1]),
					'status' => $printers_match[2]
				);
			}
			elseif (preg_match('/^Rank\s+Owner\s+Job\s+File\(s\)\s+Total Size$/', $lines[$i])) {
				$begin_queue_list = true;
			}
			elseif ($begin_queue_list && preg_match('/^([a-z0-9]+)\s(\S+)\s+(\d+)\s+(.+)\s+(\d+) bytes$/', $lines[$i], $queue_match)) {
				$queue[] = array(
					'rank' => $queue_match[1],
					'owner' => $queue_match[2],
					'job' => $queue_match[2],
					'files' => $queue_match[3],
					'size' => $queue_match[4]
				);
			}
		}
		
		$this->_res = array(
			'printers' => $printers,
			'queue' => $queue
		);

		return true;
	}

	public function work() {
		$this->_call();
	}

	public function result() {
		if ($this->_res == false)
			return false;
		else {

			$rows = array();
			$rows[] = array (
				'type' => 'header',
				'values' => array(
					array(2, 'Printers')
				)
			);
			$rows[] = array (
				'type' => 'header',
				'values' => array(
					'Name',
					'Status'
				)
			);

			if (count($this->_res['printers']) == 0)
				$rows[] = array('type' => 'values', 'columns' => array(2, 'None found'));
			else {
				foreach ($this->_res['printers'] as $printer)
					$rows[] = array(
						'type' => 'values',
						'columns' => array(
							$printer['name'],
							$printer['status']
						)
					);
			}

			return array(
				'root_title' => 'CUPS Printer Status',
				'rows' => $rows
			);
		}
	}
}
