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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Linfo. If not, see <http://www.gnu.org/licenses/>.
 *
*/

namespace Linfo\OS;

use Linfo\Common;
use Linfo\Parsers\CallExt;
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
        $sysctl = array();

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
        $results = array();

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
            return array();
        }

        return array_combine(array('now', '5min', '15min'), $parts);
    }
}
