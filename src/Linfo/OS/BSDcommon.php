<?php

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

namespace Linfo\OS;

use Linfo\Common;
use Linfo\Parsers\CallExt;
use Linfo\Meta\Timer;
use Exception;

/*
 * The BSD os's are largely similar and thus draw from this class.
*/

abstract class BSDcommon extends Unixcommon
{
    // Store these
    protected $settings,
        $exec,
        $dmesg,
        $sysctl = [];

    // Start us off
    protected function __construct($settings)
    {

        // Localize settings
        $this->settings = $settings;

        // Exec running
        $this->exec = new CallExt();

        // Get dmesg
        $this->loadDmesg();
    }

    // Save dmesg
    protected function loadDmesg()
    {
        $this->dmesg = Common::getContents('/var/run/dmesg.boot');
    }

    // Use sysctl to get something, and cache result.
    // Also allow getting multiple keys at once, in which case sysctl
    // will only be called once instead of multiple times (assuming it doesn't break)
    protected function getSysCTL($keys, $do_return = true)
    {

        // Get the keys as an array, so we can treat it as an array of keys
        $keys = (array) $keys;

        // Store the results of which here
        $results = [];

        // Go through each
        foreach ($keys as $k => $v) {
            $keys[$k] = escapeshellarg($v);

            // Check and see if we have any of these already. If so, use previous
            // values and don't retrive them again
            if (array_key_exists($v, $this->sysctl)) {
                unset($keys[$k]);
                $results[$v] = $this->sysctl[$v];
            }
        }

        // Try running sysctl to get all the values together
        try {
            // Result of sysctl
            $command = $this->exec->exec('sysctl', implode(' ', $keys));

            // Place holder
            $current_key = false;

            // Go through each line
            foreach (explode("\n", $command) as $line) {

                // If this is the beginning of one of the keys' values
                if (preg_match('/^([a-z0-9\.\-\_]+)\s*(?:\:|=)(.+)/', $line, $line_match) == 1) {
                    if ($line_match[1] != $current_key) {
                        $current_key = $line_match[1];
                        $results[$line_match[1]] = trim($line_match[2]);
                    }
                }

                // If this line is a continuation of one of the keys' values
                elseif ($current_key != false) {
                    $results[$current_key] .= "\n".trim($line);
                }
            }
        }

        // Something broke with that sysctl call; try getting
        // all the values separately (slower)
        catch (Exception $e) {

            // Go through each
            foreach ($keys as $v) {

                // Try it
                try {
                    $results[$v] = $this->exec->exec('sysctl', $v);
                }

                // Didn't work again... just forget it and set value to empty string
                catch (Exception $e) {
                    $results[$v] = '';
                }
            }
        }

        // Cache these incase they're called upon again
        $this->sysctl = array_merge($results, $this->sysctl);

        // Return an array of all values retrieved, or if just one was
        // requested, then that one as a string
        if ($do_return) {
            return count($results) == 1 ? reset($results) : $results;
        }
        else {
            return null;
        }
    }


    // System load averages
    public function getLoad()
    {
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Load Averages');
        }

        $parts = explode(' ', trim($this->sysctl['vm.loadavg']));

        if (!$parts) {
            return [];
        }

        return array_combine(array('now', '5min', '15min'), $parts);
    }
}
