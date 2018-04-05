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

/*
 * Main class
 */

class Sensord
{
    public function work()
    {
        $temps = $this->parseSysLog();

        return $temps;
    }

    private function parseSysLog()
    {

        /*
         * For parsing the syslog looking for sensord entries
         * POTENTIALLY BUGGY -- only tested on debian/ubuntu flavored syslogs
         * Also slow as balls as it parses the entire syslog instead of
         * using something like tail
         */
        $file = '/var/log/syslog';
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $devices = [];
        foreach (Common::getLines($file) as $line) {
            if (preg_match('/\w+\s*\d+ \d{2}:\d{2}:\d{2} \w+ sensord:\s*(.+):\s*(.+)/i', trim($line), $match) == 1) {
                // Replace current record of dev with updated temp
                $devices[$match[1]] = $match[2];
            }
        }
        $return = [];
        foreach ($devices as $dev => $stat) {
            $return[] = array(
                'path' => 'N/A', // These likely won't have paths
                'name' => $dev,
                'temp' => $stat,
                'unit' => '', // Usually included in above
            );
        }

        return $return;
    }
}
