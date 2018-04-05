<?php

/*

This impliments a CUPS printer queue status parser.

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['cups'] = true;

*/

/* Linfo
 *
 * Copyright (c) 2018 Joe Gillotti
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Linfo\Extension;

use Linfo\Linfo;
use Linfo\Common;
use Linfo\Meta\Errors;
use Linfo\Parsers\CallExt;
use Linfo\Meta\Timer;
use Exception;

/*
 * Get info on a cups install by running lpq
 */
class Cups implements Extension
{
    // Store these tucked away here
    private $_CallExt,
        $_res;

    // Localize important classes
    public function __construct(Linfo $linfo)
    {
        $this->_CallExt = new CallExt();
        $this->_CallExt->setSearchPaths(array('/usr/bin', '/usr/local/bin', '/sbin', '/usr/local/sbin'));
    }

    // call lpq and parse it
    private function _call()
    {

        // Time this
        $t = new Timer('CUPS extension');

        // Deal with calling it
        try {
            $result = $this->_CallExt->exec('lpstat', '-p -o -l');
        } catch (Exception $e) {
            // messed up somehow
            Errors::add('CUPS Extension', $e->getMessage());
            $this->_res = false;

            // Don't bother going any further
            return false;
        }

        // Split it into lines
        $lines = explode("\n", $result);

        // Hold temporarily values here
        $printers = [];
        $queue = [];
        $begin_queue_list = false;

        // Go through it line by line
        for ($i = 0, $num = count($lines); $i < $num; ++$i) {

            // So regexes don't break on endlines
            $lines[$i] = trim($lines[$i]);

            // If there are no entries, don't waste time and end here
            if ($lines[$i] == 'no entries') {
                break;
            } elseif (preg_match('/^printer (.+) is idle\. (.+)$/', $lines[$i], $printers_match) == 1) {
                $printers[] = array(
                    'name' => str_replace('_', ' ', $printers_match[1]),
                    'status' => $printers_match[2],
                );
            }

            // A printer entry
            elseif (preg_match('/^(.+)+ is (ready|ready and printing|not ready)$/', $lines[$i], $printers_match) == 1) {
                $printers[] = array(
                    'name' => str_replace('_', ' ', $printers_match[1]),
                    'status' => $printers_match[2],
                );
            }

            // The beginning of the queue list
            elseif (preg_match('/^Rank\s+Owner\s+Job\s+File\(s\)\s+Total Size$/', $lines[$i])) {
                $begin_queue_list = true;
            }

            // A job in the queue
            elseif ($begin_queue_list && preg_match('/^([a-z0-9]+)\s+(\S+)\s+(\d+)\s+(.+)\s+(\d+) bytes$/', $lines[$i], $queue_match)) {
                $queue[] = array(
                    'rank' => $queue_match[1],
                    'owner' => $queue_match[2],
                    'job' => $queue_match[3],
                    'files' => $queue_match[4],
                    'size' => Common::byteConvert($queue_match[5]),
                );
            }
        }

        // Save result lset
        $this->_res = array(
            'printers' => $printers,
            'queue' => $queue,
        );

        // Apparent success
        return true;
    }

    // Called to get working
    public function work()
    {
        $this->_call();
    }

    // Get result. Essentially take results and make it usable by the Common::createTable function
    public function result()
    {

        // Don't bother if it didn't go well
        if ($this->_res == false) {
            return false;
        }

        // it did; continue
        else {

            // Store rows here
            $rows = [];

            // start off printers list
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    array(5, 'Printers'),
                ),
            );
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    'Name',
                    array(4, 'Status'),
                ),
            );

            // show printers if we have them
            if (count($this->_res['printers']) == 0) {
                $rows[] = array('type' => 'none', 'columns' => array(array(5, 'None found')));
            } else {
                foreach ($this->_res['printers'] as $printer) {
                    $rows[] = array(
                        'type' => 'values',
                        'columns' => array(
                            $printer['name'],
                            array(4, $printer['status']),
                        ),
                    );
                }
            }

            // show printer queue list
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    array(5, 'Queue'),
                ),
            );

            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    'Rank',
                    'Owner',
                    'Job',
                    'Files',
                    'Size',
                ),
            );

            // Go through each item in the lsit
            if (count($this->_res['queue']) == 0) {
                $rows[] = array('type' => 'none', 'columns' => array(array(5, 'Empty')));
            } else {
                foreach ($this->_res['queue'] as $job) {
                    $rows[] = array(
                        'type' => 'values',
                        'columns' => array(
                            $job['rank'],
                            $job['owner'],
                            $job['job'],
                            $job['files'],
                            $job['size'],
                        ),
                    );
                }
            }

            // give info
            return array(
                'root_title' => 'CUPS Printer Status',
                'rows' => $rows,
            );
        }
    }
}
