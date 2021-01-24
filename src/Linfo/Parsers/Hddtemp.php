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

namespace Linfo\Parsers;

use Linfo\Common;
use Exception;

/*
 * Deal with hddtemp
 */
class Hddtemp
{
    // Store these
    protected $mode, $host, $port, $settings;

    // Default socket connect timeout
    const timeout = 3;

    // Start us off
    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    // Localize mode
    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /*
     *  For connecting to HDDTemp daemon
     */

    // Localize host and port
    public function setAddress($host, $port = 7634)
    {
        $this->host = $host;
        $this->port = $port;
    }

    // Connect to host/port and get info
    private function getSock()
    {
        // Try connecting
        if (!($sock = @fsockopen($this->host, $this->port, $errno, $errstr, self::timeout))) {
            throw new Exception('Error connecting');
        }

        // Try getting stuff
        $buffer = '';
        while ($mid = @fgets($sock)) {
            $buffer .= $mid;
        }

        // Quit
        @fclose($sock);

        // Output:
        return $buffer;
    }

    // Parse and return info from daemon socket
    private function parseSockData($data)
    {

        // Kill surounding ||'s and split it by pipes
        $drives = explode('||', trim($data, '|'));

        // Return our stuff here
        $return = [];

        // Go through each
        foreach ($drives as $drive) {

            // Extract stuff from it
            list($path, $name, $temp, $unit) = explode('|', trim($drive));

            // Ignore garbled output from SSDs that hddtemp cant parse
            if (strpos($temp, 'UNK') !== false) {
                continue;
            }

            // Ignore /dev/sg?
            if (!empty($this->settings['hide']['sg']) && substr($path, 0, 7) == '/dev/sg') {
                continue;
            }

            // Ignore no longer existant devices?
            if (!file_exists($path) && is_readable('/dev')) {
                continue;
            }

            // Save it
            $return[] = array(
                'path' => $path,
                'name' => $name,
                'temp' => Common::strToInt($temp),
                'unit' => strtoupper($unit),
            );
        }

        // Give off results
        return $return;
    }

    /*
     * For parsing the syslog looking for hddtemp entries
     * POTENTIALLY BUGGY -- only tested on debian/ubuntu flavored syslogs
     * Also slow as balls as it parses the entire syslog instead of
     * using something like tail
     */
    private function parseSysLogData()
    {
        $file = '/var/log/syslog';
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $devices = [];
        foreach (Common::getLines($file) as $line) {
            if (preg_match('/\w+\s*\d+ \d{2}:\d{2}:\d{2} \w+ hddtemp\[\d+\]: (.+): (.+): (\d+) ([CF])/i', trim($line), $match) == 1) {
                // Replace current record of dev with updated temp
                $devices[$match[1]] = array($match[2], $match[3], $match[4]);
            }
        }
        $return = [];
        foreach ($devices as $dev => $stat) {
            $return[] = array(
                'path' => $dev,
                'name' => $stat[0],
                'temp' => Common::strToInt($stat[1]),
                'unit' => strtoupper($stat[2]),
            );
        }

        return $return;
    }

    /*
     * Wrapper function around the private ones here which do the
     * actual work, and returns temps
     */

    // Use supplied mode, and optionally host/port, to get temps and return them
    public function work()
    {

        // Deal with differences in mode
        switch ($this->mode) {

            // Connect to daemon mode
            case 'daemon':
                return $this->parseSockData($this->getSock());
            break;

            // Syslog every n seconds
            case 'syslog':
                return $this->parseSysLogData();
            break;

            // Some other mode
            default:
                throw new Exception('Not supported mode');
            break;
        }
    }
}
