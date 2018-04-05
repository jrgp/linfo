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

use Exception;
use Linfo\Parsers\CallExt;

/*
 * Get info on a Minix system
 * ---
 * Note: the cli tools on minix are so meager that getting real detail
 * out of it (like nic stats / fs types / etc) is either difficult or
 * impossible. Nevertheless, this is my attempt at doing so.
 */

class Minix extends OS
{
    // Store these here
    protected $settings,
        $exec;

    // Start us off by localizing the settings and initializing the external
    // application running class
    public function __construct($settings)
    {

        // Localize settings
        $this->settings = $settings;

        // Start up external app loader
        $this->exec = new CallExt();

        // Have it look in these places
        $this->exec->setSearchPaths(array('/usr/bin', '/usr/local/bin', '/bin'));
    }

    // Mounted file systems
    // ---
    // Note: the `mount` command does not have file system type
    // and php's disk_free_space/disk_total_space functions don't seem
    // to work here
    public function getMounts()
    {

        // Try using the `mount` command to get mounted file systems
        try {
            $res = $this->exec->exec('mount');
        } catch (Exception $e) {
            return [];
        }

        // Try matching up the output
        if (preg_match_all('/^(\S+) is .+ mounted on (\S+) \(.+\)$/m', $res, $mount_matches, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $mounts = [];

        // Go through each match
        foreach ($mount_matches as $mount) {

            // These might be a waste
            $size = @disk_total_space($mount[2]);
            $free = @disk_free_space($mount[2]);
            $used = $size - $free;

            // Save it
            $mounts[] = array(
                'device' => $mount[1],
                'mount' => $mount[2],
                'type' => '?', // Haven't a clue on how to get this on minix
                'size' => $size,
                'used' => $used,
                'free' => $free,
                'free_percent' => ((bool) $free != false && (bool) $size != false ? round($free / $size, 2) * 100 : false),
                'used_percent' => ((bool) $used != false && (bool) $size != false ? round($used / $size, 2) * 100 : false),
            );
        }

        // Return them
        return $mounts;
    }

    // Get network interfaces
    // ---
    // netstat isn't installed by default and ifconfig doesn't have
    // much functionality for viewing status, so I can't seem to get
    // more than just name of interface
    public function getNet()
    {

        // Try getting it.
        try {
            $res = $this->exec->exec('ifconfig', '-a');
        } catch (Exception $e) {
            return [];
        }

        // Match up the entries
        if (preg_match_all('/^([^:]+)/m', $res, $net_matches, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $nets = [];

        // Go through each
        foreach ($net_matches as $net) {

            // Save this one
            $nets[$net[1]] = array(
                'state' => '?',
                'type' => '?',
            );
        }

        // Give them
        return $nets;
    }
}
