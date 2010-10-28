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

	// call lpq and parse it
	private function _call() {
		try {
			$result = $this->_CallExt->exec('lpq');
		}
		catch (CallExtException $e) {
			// fucked up somehow
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
			elseif ($begin_queue_list && preg_match('/^([a-z0-9]+)\s+(\S+)\s+(\d+)\s+(.+)\s+(\d+) bytes$/', $lines[$i], $queue_match)) {
				$queue[] = array(
					'rank' => $queue_match[1],
					'owner' => $queue_match[2],
					'job' => $queue_match[3],
					'files' => $queue_match[4],
					'size' => byte_convert($queue_match[5])
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

			// start off printers list
			$rows[] = array (
				'type' => 'header',
				'values' => array(
					array(5, 'Printers')
				)
			);
			$rows[] = array (
				'type' => 'header',
				'columns' => array(
					'Name',
					array(4, 'Status')
				)
			);
			
			// show printers if we have them
			if (count($this->_res['printers']) == 0)
				$rows[] = array('type' => 'values', 'columns' => array(array(5, 'None found')));
			else {
				foreach ($this->_res['printers'] as $printer)
					$rows[] = array(
						'type' => 'values',
						'columns' => array(
							$printer['name'],
							array(4, $printer['status'])
						)
					);
			}

			// show printer qeue list
			$rows[] = array(
				'type' => 'header',
				'columns' => array(
					array(5, 'Queue')
				)
			);
			
			$rows[] = array (
				'type' => 'header',
				'columns' => array(
					'Rank',
					'Owner',
					'Job',
					'files',
					'size',
				)
			);

			if (count($this->_res['queue']) == 0)
				$rows[] = array('type' => 'values', 'columns' => array(array(5, 'Empty')));
			else {
				foreach ($this->_res['queue'] as $job)
					$rows[] = array(
						'type' => 'values',
						'columns' => array(
							$job['rank'],
							$job['owner'],
							$job['job'],
							$job['files'],
							$job['size'],	
						)
					);
			}



			// give info
			return array(
				'root_title' => 'CUPS Printer Status',
				'rows' => $rows
			);
		}
	}
}
