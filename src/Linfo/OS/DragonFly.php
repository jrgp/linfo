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
use Linfo\Parsers\Hwpci;

class DragonFly extends BSDcommon
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
            'kern.boottime',
            'vm.loadavg',
            'hw.model',
            'hw.ncpu',
            'hw.clockrate',
        ), false);
    }

    // What we should leave out
    public function getContains()
    {
        return array(
                'drives_rw_stats' => false,
                'nic_type' => false,
            );
    }

    // Return OS type
    public function getOS()
    {

        // Obviously
        return 'DragonFly BSD';
    }

    // Get mounted file systems
    public function getMounts()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Mounted file systems');
        }

        // Get result of mount command
        try {
            $res = $this->exec->exec('mount');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error running `mount` command');

            return [];
        }

        // Parse it
        if (preg_match_all('/^(\S+) on (\S+) \((\w+)(?:, (.+))?\)/m', $res, $m, PREG_SET_ORDER) == 0) {
            return [];
        }

        // Store them here
        $mounts = [];

        // Deal with each entry
        foreach ($m as $mount) {

            // Should we not show this?
            if (in_array($mount[1], $this->settings['hide']['storage_devices']) || in_array($mount[3], $this->settings['hide']['filesystems'])) {
                continue;
            }

            // Get these
            $size = @disk_total_space($mount[2]);
            $free = @disk_free_space($mount[2]);
            $used = $size - $free;

            // Optionally get mount options
            if (
                $this->settings['show']['mounts_options'] &&
                !in_array($mount[3], (array) $this->settings['hide']['fs_mount_options']) &&
                isset($mount[4])
            ) {
                $mount_options = explode(', ', $mount[4]);
            } else {
                $mount_options = [];
            }

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
                'options' => $mount_options,
            );
        }

        // Give it
        return $mounts;
    }

    // Get ram usage
    public function getRam()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Memory');
        }

        // We'll return the contents of this
        $return = [];

        // Start us off at zilch
        $return['type'] = 'Virtual';
        $return['total'] = 0;
        $return['free'] = 0;
        $return['swapTotal'] = 0;
        $return['swapFree'] = 0;
        $return['swapInfo'] = [];

        // Get swap

        // Return it
        return $return;
    }

    // Get uptime
    public function getUpTime()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Uptime');
        }

        // Use sysctl to get unix timestamp of boot. Very elegant!
        if (preg_match('/^\{ sec \= (\d+).+$/', $this->sysctl['kern.boottime'], $m) == 0) {
            return '';
        }

        // Boot unix timestamp
        $booted = $m[1];

        // Get it textual, as in days/minutes/hours/etc
        return array(
            'text' => Common::secondsConvert(time() - $booted),
            'bootedTimestamp' => $booted,
        );
    }

    // RAID Stats
    public function getRAID()
    {
    }

    // Done
    public function getNet()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Network Devices');
        }

        // Use netstat to get nic names and stats
        try {
            $netstat = $this->exec->exec('netstat', '-nibd');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'error using netstat');

            return [];
        }

        // Store nics here
        $nets = [];

        // Match that up
        if (!preg_match_all('/^([\da-z]+\*?)\s+\d+\s+<Link#\d+>(?:\s+[a-z0-9:]+)?\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)$/m', $netstat, $netstat_m, PREG_SET_ORDER)) {
            return [];
        }

        // Go through each match
        foreach ($netstat_m as $m) {
            $nets[$m[1]] = array(
                'recieved' => array(
                    'bytes' => $m[4],
                    'errors' => $m[3],
                    'packets' => $m[2],
                ),
                'sent' => array(
                    'bytes' => $m[7],
                    'errors' => $m[6],
                    'packets' => $m[5],
                ),
                'state' => 'unknown',
                'type' => 'N/A',
            );
        }

        // Try getting the statuses with ifconfig
        try {

            // Store current nic here
            $current_nic = false;

            $ifconfig = $this->exec->exec('ifconfig', '-a');

            // Go through each line
            foreach (explode("\n", $ifconfig) as $line) {

                // Approaching new nic?
                if (preg_match('/^([a-z0-9]+):/', $line, $m)) {
                    if (array_key_exists($m[1], $nets)) {
                        $current_nic = $m[1];
                    } else {
                        $current_nic = false;
                    }
                }

                // In a nick and found a status entry
                elseif ($current_nic && preg_match('/^\s+status: ([^$]+)$/', $line, $m)) {

                    // Decide what it is and save it
                    switch ($m[1]) {
                        case 'active':
                            $nets[$current_nic]['state'] = 'up';
                        break;
                        case 'inactive':
                        case 'no carrier':
                            $nets[$current_nic]['state'] = 'down';
                        break;
                        default:
                            $nets[$current_nic]['state'] = 'unknown';
                        break;
                    }

                    // Don't waste further time until we find another nic entry
                    $current_nic = false;
                }
            }
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'error using ifconfig to get nic statuses');
        }

        // Give nets
        return $nets;
    }

    // Get CPU's
    // I still don't really like how this is done
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
                'MHz' => $this->sysctl['hw.clockrate'],
            );
        }

        // Return
        return $cpus;
    }

    // Parse dmesg boot log
    public function getDevs()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Hardware Devices');
        }

        $hw = new Hwpci(null, '/usr/share/misc/pci_vendors');
        $hw->work('dragonfly');

        return $hw->result();
    }

    // APM? Seems to only support either one battery of them all collectively
    public function getBattery()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Batteries');
        }

        return [];
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
                'idle' => 0,
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
                        $result['totals']['running']++;
                    break;
                    case 'T':
                        $result['totals']['stopped']++;
                    break;
                    case 'W':
                        $result['totals']['idle']++;
                    break;
                }
            }
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `ps` to get process info');
        }

        // Give
        return $result;
    }

    // idk
    public function getTemps()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Temperature');
        }
    }
}
