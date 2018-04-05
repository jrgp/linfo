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
use Linfo\Meta\Timer;
use Linfo\Meta\Errors;
use Linfo\Common;

/*
 * OpenBSD info class.
 * todo: as much functionality as the freebsd version
 */

class OpenBSD extends BSDcommon
{
    // Encapsulate these
    protected $settings,
        $exec,
        $dmesg;

    // Start us off
    public function __construct($settings)
    {

        // Initiate parent
        parent::__construct($settings);

        // We search these folders for our commands
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));

        // sysctl values we'll access below
        $this->GetSysCTL(array(

            // Has unix timestamp of boot time
            'kern.boottime',

            // Ram stuff
            'hw.physmem',

            // CPU related
            'hw.model',
            'hw.ncpu',
            'hw.cpuspeed',

            'vm.loadavg',
        ), false);
    }

    // What we should leave out
    public function getContains()
    {
        return array(
                'drives_rw_stats' => false,
                'hw_vendor' => false,
                'drives_vendor' => false,
            );
    }

    // Get mounted file systems and their disk usage stats
    public function getMounts()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Mounted file systems');
        }

        // Get result of mount command
        try {
            $mount_res = $this->exec->exec('mount');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error running `mount` command');

            return [];
        }

        // Match that up
        if (preg_match_all('/^(\S+) on (\S+) type (\S+) \(.+\)$/m', $mount_res, $mount_matches, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $mounts = [];

        // Go through
        foreach ($mount_matches as $mount) {
            // Should we not show this?
            if (in_array($mount[1], $this->settings['hide']['storage_devices']) || in_array($mount[3], $this->settings['hide']['filesystems'])) {
                continue;
            }

            // Get these
            $size = @disk_total_space($mount[2]);
            $free = @disk_free_space($mount[2]);
            $used = $size - $free;

            // Might be good, go for it
            $mounts[] = array(
                'device' => $mount[1],
                'mount' => $mount[2],
                'type' => $mount[3],
                'size' => $size ,
                'used' => $used,
                'free' => $free,
                'free_percent' => ((bool) $free != false && (bool) $size != false ? round($free / $size, 2) * 100 : false),
                'used_percent' => ((bool) $used != false && (bool) $size != false ? round($used / $size, 2) * 100 : false),
            );
        }

        // Give it
        return $mounts;
    }

    // Get memory usage statistics
    public function getRam()
    {
        $return = [];
        $return['swapTotal'] = 0;
        $return['swapFree'] = 0;
        $return['swapInfo'] = [];

        // Get amount of real hard ram, in bytes
        $return['total'] = $this->sysctl['hw.physmem'];

        // Get real
        try {
            $vmstat = $this->exec->exec('vmstat');
            if (preg_match('/\s\d\s\d\s\d\s+\d+\s+(\d+)/', $vmstat, $vmstat_match)) {
                $hard_ram_free = $vmstat_match[1];
                $return['type'] = 'Physical';
                $return['free'] = $return['total'] - ($hard_ram_free * 1024);
            }
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `vmstat` to get memory usage usage');
        }

        // Get swap
        try {
            $swapinfo = $this->exec->exec('swapctl', '-k');
            @preg_match_all('/^(\S+)\s+(\d+)\s+(\d+)\s+(\d+)/m', $swapinfo, $sm, PREG_SET_ORDER);
            foreach ($sm as $swap) {
                $return['swapTotal'] += $swap[2] * 1024;
                $return['swapFree'] += (($swap[2] - $swap[3]) * 1024);
                $ft = is_file($swap[1]) ? @filetype($swap[1]) : 'Unknown'; // TODO: I'd rather it be Partition or File
                $return['swapInfo'][] = array(
                    'device' => $swap[1],
                    'size' => $swap[2] * 1024,
                    'used' => $swap[3] * 1024,
                    'type' => ucfirst($ft),
                );
            }
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `swapctl` to get swap usage');
        }

        // Give it
        return $return;
    }

    // Get hardware devices
    public function getDevs()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Hardware Devices');
        }

        // Match them
        if (preg_match_all('/([a-z]+\d+) at ([a-z]+)\d*.+ "(.+)"/m', $this->dmesg, $devices_match, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $devices = [];

        // Stuff them
        foreach ($devices_match as $match) {
            $type = strtoupper($match[2]);

            $devices[] = array(
                'vendor' => false, // hmm
                'device' => $match[3],
                'type' => $type,

            );
        }

        // Give them
        return $devices;
    }

    // Get hard disk drives and the like
    public function getHD()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('CPU');
        }

        $drives = [];
        $curr_hd = false;

        // Parse dmesg
        foreach (explode("\n", $this->dmesg) as $dmesg_line) {
            if (preg_match('/^(\w+) at .+<([^>]+)>/', $dmesg_line, $hd_start_match)) {
                $curr_hd = $hd_start_match;
            } elseif ($curr_hd !== false && preg_match('/^'.preg_quote($curr_hd[1]).':.*\b(\d+)MB/', $dmesg_line, $hd_spec_match)) {
                $drives[] = array(
                    'name' => trim($curr_hd[2], ', '),
                    'vendor' => false,
                    'device' => '/dev/'.$curr_hd[1],
                    'size' => $hd_spec_match[1] * 1048576,

                    // Not sure how to get the following:
                    'reads' => false,
                    'writes' => false,

                );
                $curr_hd = false;
            } else {
                $curr_hd = false;
            }
        }

        // give it
        return $drives;
    }

    // Get uptime
    public function getUpTime()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Uptime');
        }

        // Short and sweet
        $booted = $this->sysctl['kern.boottime'];

        if ($booted == false) {
            return 'Unknown';
        }

        // Is it not a timestamp?
        if (!is_numeric($booted)) {
            $booted = strtotime($booted);
        }

        // Give it
        return array(
            'text' => Common::secondsConvert(time() - $booted),
            'bootedTimestamp' => $booted,
        );
    }

    // Get network devices, their stats, status, and type
    public function getNet()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Network Devices');
        }

        // Get result of netstat command
        try {
            $res = $this->exec->exec('netstat', '-nbi');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `netstat` to get network info');

            return [];
        }

        // Get initial matches
        if (preg_match_all('/^([a-z0-9]+)\*?\s+\d+\s+<Link>(?:\s+[a-z0-9\:]+)?\s+(\d+)\s+(\d+)$/m', $res, $net_matches, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store statuses for each here
        $statuses = [];

        // Try using ifconfig to get statuses for each interface
        try {
            $ifconfig = $this->exec->exec('ifconfig', '-a');
            $current_nic = false;
            foreach ((array) explode("\n", $ifconfig) as $line) {
                if (preg_match('/^(\w+):/m', $line, $m) == 1) {
                    $current_nic = $m[1];
                } elseif ($current_nic != false && preg_match('/^\s+status: ([^$]+)$/m', $line, $m) == 1) {
                    $statuses[$current_nic] = $m[1];
                    $current_nic = false;
                }
            }
        } catch (Exception $e) {
        }

        // Get type from dmesg boot
        $type = [];
        $type_nics = [];

        // Store the to-be detected nics here
        foreach ($net_matches as $net) {
            $type_nics[] = $net[1];
        }

        // Go through dmesg looking for them
        if (preg_match_all('/^(\w+) at ([a-zA-Z]+)\d*.+address [\w\:]+.+/m', $this->dmesg, $type_match, PREG_SET_ORDER)) {

            // Go through each
            foreach ($type_match as $type_nic_match) {

                // Is this one of our detected nics?
                if (in_array($type_nic_match[1], $type_nics)) {

                    // Yes; save status
                    $type[$type_nic_match[1]] = $type_nic_match[2];
                }
            }
        }

        // Save them here
        $nets = [];

        // Go through each
        foreach ($net_matches as $net) {

            // See if we successfully found a status, and use it if so
            switch (array_key_exists($net[1], $statuses) ? $statuses[$net[1]] : 'unknown') {
                case 'active':
                    $state = 'up';
                break;
                case 'inactive':
                case 'no carrier':
                    $state = 'down';
                break;
                default:
                    $state = 'unknown';
                break;
            }

            // Save this interface
            $nets[$net[1]] = array(

                // pulled from netstat
                'recieved' => array(
                    'bytes' => $net[2],
                ),
                'sent' => array(
                    'bytes' => $net[3],
                ),

                // pulled from ifconfig
                'state' => $state,

                // pulled from dmesg
                'type' => array_key_exists($net[1], $type) ? strtoupper($type[$net[1]]) : 'N/A',
            );
        }

        // Give them
        return $nets;
    }

    // processors...
    public function getCPU()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('CPUs');
        }

        // Store them here
        $cpus = [];

        // Stuff it with identical cpus
        for ($i = 0; $i < $this->sysctl['hw.ncpu']; ++$i) {

            // Save each
            $cpus[] = array(
                'Model' => $this->sysctl['hw.model'],
                'MHz' => $this->sysctl['hw.cpuspeed'],
            );
        }

        // Return
        return $cpus;
    }

    // Get process stats
    public function getProcessStats()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Process Stats');
        }

        // We'll return this after stuffing it with useful info
        $result = array(
            'exists' => true,
            'totals' => array(
                'running' => 0,
                'zombie' => 0,
                'sleeping' => 0,
                'stopped' => 0,
            ),
            'proc_total' => 0,
            'threads' => false, // I'm not sure how to get this
        );

        // Use ps
        try {
            // Get it
            $ps = $this->exec->exec('ps', 'ax');

            // Match them
            preg_match_all('/^\s*\d+\s+[\w?]+\s+([A-Z])\S*\s+.+$/m', $ps, $processes, PREG_SET_ORDER);

            // Get total
            $result['proc_total'] = count($processes);

            // Go through
            foreach ($processes as $process) {
                switch ($process[1]) {
                    case 'S':
                    case 'I':
                        $result['totals']['sleeping']++;
                    break;
                    case 'Z':
                        $result['totals']['zombie']++;
                    break;
                    case 'R':
                    case 'D':
                    case 'O':
                        $result['totals']['running']++;
                    break;
                    case 'T':
                        $result['totals']['stopped']++;
                    break;
                }
            }
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `ps` to get process info');
        }

        // Give
        return $result;
    }
}
