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
 * NetBSD info class. Differs slightly from FreeBSD's
 * TODO: netbsd's /proc contains really useful info
 * possibly get some stuff from it if it exists
 */

class NetBSD extends BSDcommon
{
    // Encapsulate these
    protected $settings,
        $exec,
        $error,
        $dmesg;

    // Start us off
    public function __construct($settings)
    {

        // Initiate parent
        parent::__construct($settings);

        // We search these folders for our commands
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/pkg/bin', '/usr/sbin'));

        // sysctl values we'll access below
        $this->GetSysCTL(array('kern.boottime', 'vm.loadavg'), false);
    }

    // Mounted file systems
    public function getMounts()
    {

        // Time it
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Mounted file systems');
        }

        // Try getting mount command
        try {
            $res = $this->exec->exec('mount');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error running `mount` command');

            return [];
        }

        // Match the file systems
        if (@preg_match_all('/^(\S+) on (\S+) type (\S+)/m', $res, $mount_match, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $mounts = [];

        // Go through each
        foreach ($mount_match as $mount) {
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

        // Give them
        return $mounts;
    }

    // Get the always gloatable uptime
    public function getUpTime()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Uptime');
        }

        // Use sysctl
        $booted = strtotime($this->sysctl['kern.boottime']);

        // Give it
        return array(
            'text' => Common::secondsConvert(time() - $booted),
            'bootedTimestamp' => $booted,
        );
    }

    // Get network devices
    public function getNet()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Network Devices');
        }

        // Try using netstat
        try {
            $res = $this->exec->exec('netstat', '-nbdi');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `netstat` to get network info');

            return [];
        }

        // Match the interfaces themselves
        if (preg_match_all('/^(\S+)\s+\d+\s+<Link>\s+[a-z0-9\:]+\s+(\d+)\s+(\d+)\s+\d+$/m', $res, $net_matches, PREG_SET_ORDER) == 0) {
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
                } elseif ($current_nic != false && preg_match('/^\s+status: (\w+)$/m', $line, $m) == 1) {
                    $statuses[$current_nic] = $m[1];
                    $current_nic = false;
                }
            }
        } catch (Exception $e) {
        }

        // Store interfaces here
        $nets = [];

        // Go through each
        foreach ($net_matches as $net) {

            // See if we successfully found a status, and use it if so
            switch (array_key_exists($net[1], $statuses) ? $statuses[$net[1]] : 'unknown') {
                case 'active':
                    $state = 'up';
                break;
                case 'inactive':
                    $state = 'down';
                break;
                default:
                    $state = 'unknown';
                break;
            }

            // Save this interface
            $nets[$net[1]] = array(
                'recieved' => array(
                    'bytes' => $net[2],
                ),
                'sent' => array(
                    'bytes' => $net[3],
                ),
                'state' => $state,
                'type' => 'Unknown', // TODO
            );
        }

        // Give it
        return $nets;
    }

    // Get drives
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

            // Beginning of a drive entry
            if (preg_match('/^([a-z]{2}\d) at [^:]+: <([^>]+)> (\w+)/', $dmesg_line, $init_match)) {

                // If it's a cdrom just stop here and save it.
                if ($init_match[3] == 'cdrom') {

                    // Save entry
                    $drives[] = array(
                        'name' => preg_match('/^([^,]+)/', $init_match[2], $cd_match) ? $cd_match[1] : $init_match[2],
                        'vendor' => false, // I don't know if this is possible
                        'device' => '/dev/'.$init_match[1],

                        // Not sure how to get the following:
                        'size' => false,
                        'reads' => false,
                        'writes' => false,
                    );
                }

                // Otherwise prep for further info on a later line
                elseif ($init_match[3] == 'disk') {
                    $curr_hd = array($init_match[1], $init_match[2], $init_match[3]);
                }

                // Don't go any farther with this line
                continue;
            }

            // A hard drive setting line, that has size and stuff
            elseif ($curr_hd != false && preg_match('/^'.preg_quote($curr_hd[0]).': (\d+) MB/', $dmesg_line, $drive_match)) {

                // Try getting vendor or name
                $make = preg_match('/^([^,]+), ([^,]+)/', $curr_hd[1], $v_match) ? array($v_match[1], $v_match[2]) : false;

                // Save entry
                $drives[] = array(
                    'name' => $make ? $make[1] : $curr_hd[1],
                    'vendor' => $make ? $make[0] : false,
                    'device' => '/dev/'.$curr_hd[0],
                    'size' => $drive_match[1] * 1048576,

                    // Not sure how to get the following:
                    'reads' => false,
                    'writes' => false,
                );

                // We're done with this drive
                $curr_hd = false;

                // Don't go any farther with this line
                continue;
            }
        }

        // Give drives
        return $drives;
    }

    // Get cpu's
    public function getCPU()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('CPU');
        }

        // Parse dmesg
        if (preg_match_all('/^cpu\d+ at [^:]+: (\S+) ([^,]+), (\d+)MHz/m', $this->dmesg, $cpu_matches, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $cpus = [];

        // Store as many as possible
        foreach ($cpu_matches as $cpu_m) {
            $cpus[] = array(
                'Model' => $cpu_m[2],
                'MHz' => $cpu_m[3],
                'Vendor' => $cpu_m[1],
            );
        }

        // Give them
        return $cpus;
    }

    // Get ram usage
    public function getRam()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Memory');
        }

        // Start us off at zilch
        $return = [];
        $return['type'] = 'Virtual';
        $return['total'] = 0;
        $return['free'] = 0;
        $return['swapTotal'] = 0;
        $return['swapFree'] = 0;
        $return['swapInfo'] = [];

        // Get virtual memory usage with vmstat
        try {
            // Get result of vmstat
            $vmstat = $this->exec->exec('vmstat', '-s');

            // Get bytes per page
            preg_match('/^\s+(\d+) bytes per page$/m', $vmstat, $bytes_per_page);

            // Did we?
            if (!is_numeric($bytes_per_page[1]) || $bytes_per_page[1] < 0) {
                throw new Exception('Error parsing page size out of `vmstat`');
            } else {
                list(, $bytes_per_page) = $bytes_per_page;
            }

            // Get available ram
            preg_match('/^\s+(\d+) pages managed$/m', $vmstat, $available_ram);

            // Did we?
            if (!is_numeric($available_ram[1])) {
                throw new Exception('Error parsing managed pages out of `vmstat`');
            } else {
                list(, $available_ram) = $available_ram;
            }

            // Get free ram
            preg_match('/^\s+(\d+) pages free$/m', $vmstat, $free_ram);

            // Did we?
            if (!is_numeric($free_ram[1])) {
                throw new Exception('Error parsing free pages out of `vmstat`');
            } else {
                list(, $free_ram) = $free_ram;
            }

            // Okay, cool. Total them up
            $return['total'] = $available_ram * $bytes_per_page;
            $return['free'] = $free_ram * $bytes_per_page;
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `vmstat` to get memory usage');
        }

        // Get swap
        try {
            $swapinfo = $this->exec->exec('swapctl', '-l');
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

        // Give it off
        return $return;
    }

    // Get devices
    public function getDevs()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Hardware Devices');
        }

        // Get them
        if (preg_match_all('/^([a-z]+\d+) at ([a-z]+)\d+[^:]+:(.+)/m', $this->dmesg, $devices_match, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Keep them here
        $devices = [];

        // Store the type column for each key
        $sort_type = [];

        // Stuff it
        foreach ($devices_match as $device) {
            if ($device[2] == 'ppb' || strpos($device[3], 'vendor') !== false) {
                continue;
            }

            // Only call this once
            $type = strtoupper($device[2]);

            // Stuff entry
            $devices[] = array(
                'vendor' => false, // Maybe todo?
                'device' => $device[3],
                'type' => $type,
            );

            // For the sorting of this entry
            $sort_type[] = $type;
        }

        // Sort
        array_multisort($devices, SORT_STRING, $sort_type);

        // Give them
        return $devices;
    }

    // Get stats on processes
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
