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

use Exception;

/*
 * Deal with MbMon
 */
class Mbmon
{
    // Store these
    protected $host, $port;

    // Default socket connect timeout
    const timeout = 3;

    // Localize host and port
    public function setAddress($host, $port = 411)
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
        $return = [];

        $lines = (array) explode("\n", trim($data));

        foreach ($lines as $line) {
            if (preg_match('/(\w+)\s*:\s*([-+]?[\d\.]+)/i', $line, $match) == 1) {
                $return[] = array(
                    'path' => 'N/A',
                    'name' => $match[1],
                    'temp' => $match[2],
                    'unit' => '', // TODO
                );
            }
        }

        return $return;
    }

    // Do work and return temps
    public function work()
    {
        $sockResult = $this->getSock();
        $temps = $this->parseSockData($sockResult);

        return $temps;
    }
}
