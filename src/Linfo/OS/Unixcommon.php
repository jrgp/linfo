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

/*
 * The Unix os's are largely similar and thus draw from this class.
*/
abstract class Unixcommon extends OS
{
    public function ensureFQDN($hostname)
    {
        $parts = explode('.', $hostname);
        $num_parts = count($parts);

        // Already FQDN, like a boss..
        if ($num_parts >= 2) {
            return $hostname;
        }

        // Don't bother trying to expand on .local
        if ($num_parts > 0 && $parts[$num_parts - 1] == '.local') {
            return $hostname;
        }

        // This relies on reading /etc/hosts.
        if (!($contents = Common::getContents('/etc/hosts', false))) {
            return $hostname;
        }

        preg_match_all('/^[^\s#]+\s+(.+)/m', $contents, $matches, PREG_SET_ORDER);

        // Lets see if we can do some magic with /etc/hosts..
        foreach ($matches as $match) {
            if (!preg_match_all('/(\S+)/', $match[1], $hosts, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($hosts as $host) {

                // I don't want to expand on localhost as it's pointlesss
                if (strpos('localhost', $host[1]) !== false) {
                    continue;
                }

                $entry_parts = explode('.', $host[1]);
                if (count($entry_parts) > 1 && $entry_parts[0] == $hostname) {
                    return $host[1];
                }
            }
        }

        // Couldn't make it better :/
        return $hostname;
    }
}
