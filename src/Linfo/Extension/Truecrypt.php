<?php

/*

This implements a truecrypt mounted volume status shower

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['truecrypt'] = true;

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
use Linfo\Parsers\CallExt;
use Linfo\Meta\Errors;
use Linfo\Meta\Timer;
use Exception;

/*
 * Get status on truecrypt volumes. very experimental
 */
class Truecrypt implements Extension
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

    // call truecrypt and parse it
    private function _call()
    {

        // Time this
        $t = new Timer('Truecrypt Extension');

        // Deal with calling it
        try {
            $result = $this->_CallExt->exec('truecrypt', '-l -v');
        } catch (Exception $e) {
            // messed up somehow
            Errors::add('Truecrypt Extension', $e->getMessage());
            $this->_res = false;

            // Don't bother going any further
            return false;
        }

        // Store them here
        $this->_res = [];

        // Current one
        $curr = false;

        // Lines of output
        $lines = explode("\n", $result);

        // Go through each line
        for ($i = 0, $num = count($lines); $i < $num; ++$i) {

            // Extract juicy info
            if (!preg_match('/^([^:]+): ([^$]+)$/', $lines[$i], $line_match)) {
                continue;
            }

            // Decide what to do with that
            switch ($line_match[1]) {

                // It starts here
                case 'Slot':
                    if ($curr === false) {
                        $curr = array('slot' => $line_match[2]);
                    } elseif (is_array($curr)) {
                        $this->_res[] = $curr;
                        $curr = false;
                    }
                break;

                // Volume.
                case 'Volume':
                    if (is_array($curr)) {
                        $curr['volume'] = $line_match[2];
                    }
                break;

                // Virtual device
                case 'Virtual Device':
                    if (is_array($curr)) {
                        $curr['virtual_device'] = $line_match[2];
                    }
                break;

                // Where it might be mounted
                case 'Mount Directory':
                    if (is_array($curr)) {
                        $curr['mount_directory'] = $line_match[2];
                    }
                break;

                // Size of it
                case 'Size':
                    if (is_array($curr)) {
                        $curr['size'] = $line_match[2];
                    }
                break;

                // Is it read only?
                case 'Read-Only':
                    if (is_array($curr)) {
                        $curr['read_only'] = $line_match[2];
                    }
                break;

                // We deliberately ignore most keys for security reasons
                default:
                    continue;
                break;
            }
        }

        // Save a remaining one
        if (is_array($curr) && count($curr) > 0) {
            $this->_res[] = $curr;
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
        if ($this->_res == false) {
            return false;
        }

        // it did; continue
        else {

            // Store rows here
            $rows = [];

            // start off volume list
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    'Slot',
                    'Volume',
                    'Virtual Device',
                    'Mount Point',
                    'Size',
                    'Read Only',
                ),
            );

            // show volumes if we have them
            if (count($this->_res) == 0) {
                $rows[] = array('type' => 'none', 'columns' => array(array(6, 'None found')));
            } else {
                foreach ((array) $this->_res as $vol) {
                    $rows[] = array(
                        'type' => 'values',
                        'columns' => array(
                            $vol['slot'],
                            $vol['volume'],
                            $vol['virtual_device'],
                            $vol['mount_directory'],
                            $vol['size'],
                            $vol['read_only'],
                        ),
                    );
                }
            }

            // Give info
            return array(
                'root_title' => 'Truecrypt Volumes',
                'rows' => $rows,
            );
        }
    }
}
