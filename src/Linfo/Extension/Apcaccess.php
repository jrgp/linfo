<?php

/*

This lets you view the command output of the APC program apcaccess.

Make sure that you have your UPS connected correctly, the apc package installed, and that
running apcaccess produces output you find interesting enough for Linfo to display.

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['apcaccess'] = true;

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
 * Get status on apcaccess volumes.
 */
class Apcaccess implements Extension
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

    // call apcaccess and parse it
    private function _call()
    {

        // Time this
        $t = new Timer('apcaccess Extension');

        // Deal with calling it
        try {
            $result = $this->_CallExt->exec('apcaccess');
        } catch (Exception $e) {
            // messed up somehow
            Errors::add('apcaccess Extension', $e->getMessage());
            $this->_res = false;

            // Don't bother going any further
            return false;
        }

        // Store them here
        $this->_res = [];

        // Get name
        if (preg_match('/^UPSNAME\s+:\s+(.+)$/m', $result, $m)) {
            $this->_res['name'] = $m[1];
        }

        // Get model
        if (preg_match('/^MODEL\s+:\s+(.+)$/m', $result, $m)) {
            $this->_res['model'] = $m[1];
        }

        // Get battery voltage
        if (preg_match('/^BATTV\s+:\s+(\d+\.\d+)/m', $result, $m)) {
            $this->_res['volts'] = $m[1];
        }

        // Get charge percentage, and get it cool
        if (preg_match('/^BCHARGE\s+:\s+(\d+(?:\.\d+)?)/m', $result, $m)) {
            $charge = (int) $m[1];
            $this->_res['charge'] = '
					<div class="bar_chart">
						<div class="bar_inner" style="width: '.(int) $charge.'%;">
							<div class="bar_text">
								'.($charge ? $charge.'%' : '?').'
							</div>
						</div>
					</div>
			';
        }

        // Get time remaning
        if (preg_match('/^TIMELEFT\s+:\s+([\d\.]+)/m', $result, $m)) {
            $this->_res['time_left'] = Common::secondsConvert($m[1] * 60);
        }

        // Get status
        if (preg_match('/^STATUS\s+:\s+([A-Z]+)/m', $result, $m)) {
            $this->_res['status'] = $m[1] == 'ONBATT' ? 'On Battery' : ucfirst(strtolower($m[1]));
        }

        // Load percentage looking cool
        if (preg_match('/^LOADPCT\s+:\s+(\d+\.\d+)/m', $result, $m)) {
            $load = (int) $m[1];
            $this->_res['load'] = '
					<div class="bar_chart">
						<div class="bar_inner" style="width: '.(int) $load.'%;">
							<div class="bar_text">
								'.($load ? $load.'%' : '?').'
							</div>
						</div>
					</div>
			';
        }

        // Attempt getting wattage
        if (isset($load) && preg_match('/^NOMPOWER\s+:\s+(\d+)/m', $result, $m)) {
            $watts = (int) $m['1'];
            $this->_res['watts_used'] = $load * round($watts / 100);
        } else {
            $this->_res['watts_used'] = false;
        }

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
        if ($this->_res === false) {
            return false;
        }

        // Store rows here
        $rows = [];

        // Start showing connections
        $rows[] = array(
            'type' => 'header',
            'columns' => array(
                'UPS Name',
                'Model',
                'Battery Volts',
                'Battery Charge',
                'Time Left',
                'Current Load',
                $this->_res['watts_used'] ? 'Current Usage' : false,
                'Status',
            ),
        );

        // And all the values
        $rows[] = array(
            'type' => 'values',
            'columns' => array(
                $this->_res['name'],
                $this->_res['model'],
                $this->_res['volts'],
                $this->_res['charge'],
                $this->_res['time_left'],
                $this->_res['load'],
                $this->_res['watts_used'] ? $this->_res['watts_used'].'W' : false,
                $this->_res['status'],
            ),
        );

        // Give it off
        return array(
            'root_title' => 'APC UPS Status',
            'rows' => $rows,
        );
    }
}
