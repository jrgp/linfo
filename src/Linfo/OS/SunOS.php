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
use Linfo\Parsers\CallExt;

class SunOS extends OS
{
    // Encapsulate these
    protected $settings,
        $exec,
        $kstat = [];

    // Start us off
    public function __construct($settings)
    {

        // Localize settings
        $this->settings = $settings;

        // External exec runnign
        $this->exec = new CallExt();

        // We search these folders for our commands
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));

        // Used multpile times so might as well just get it once. here
        $this->release = php_uname('r');

        // Get multiple kstat values at once and store them here. It seems kstat is SunOS' version of BSD's sysctl
        $this->loadkstat(array(

            // unix time stamp of system boot
            'unix:0:system_misc:boot_time',

            // usual 3 system load values
            'unix:0:system_misc:avenrun_1min',
            'unix:0:system_misc:avenrun_5min',
            'unix:0:system_misc:avenrun_15min',

            // physical ram info
            'unix:0:seg_cache:slab_size',
            'unix:0:system_pages:pagestotal',
            'unix:0:system_pages:pagesfree',

            // Info on all CPUs
            'cpu_info:0:',

            // Network interface stats
            'link:0:',
        ));
    }

    // Get kstat values. *extremely* similar in practice to the sysctl nature of the bsd's
    // -
    // Use kstat to get something, and cache result.
    // Also allow getting multiple keys at once, in which case sysctl
    // will only be called once instead of multiple times (assuming it doesn't break)
    protected function loadkstat($keys)
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Solaris Kstat Parsing');
        }

        $results = [];

        foreach ($keys as $k => $v) {
            if (array_key_exists($v, $this->kstat)) {
                unset($keys[$k]);
            }
        }

        try {
            $command = $this->exec->exec('kstat', ' -p '.implode(' ', array_map('escapeshellarg', $keys)));
            $lines = explode("\n", $command);
        } catch (Exception $e) {
            Errors::add('Solaris Core', 'Failed running kstat.');
        }

        if (!is_array($lines)) {
            return;
        }

        // Not very efficient as it loops over each line for every key that exists, but it is
        // very effective and thorough
        foreach ($keys as $key) {
            foreach ($lines as $line) {
                $line = trim($line);

                if (strpos($line, $key) !== 0) {
                    continue;
                }

                $value = ltrim(substr($line, strlen($key)));
                if (isset($results[$key])) {
                    $results[$key] .= "\n".$value;
                } else {
                    $results[$key] = $value;
                }
            }
        }

        $this->kstat = array_merge($results, $this->kstat);
    }

    // Return OS type
    public function getOS()
    {

        // Get SunOS version
        $v = reset(explode('.', $this->release, 2));

        // Stuff 4 and under is SunOS. 5 and up is Solaris
        switch ($v) {
            case ($v > 4):
                return 'Solaris';
            break;
            default:
                return 'SunOS';
            break;
        }

        // What's next is determining what variant of Solaris,
        // eg: opensolaris (R.I.P.), nexenta, illumos, etc
    }

    // Get kernel version
    public function getKernel()
    {
        return $this->release;
    }

    // Mounted file systems
    public function getMounts()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Mounted file systems');
        }

        // Run mount command
        try {
            $res = $this->exec->exec('mount', '-p');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error running `mount` command');

            return [];
        }

        // Parse it
        if (!preg_match_all('/^(\S+) - (\S+) (\w+).+/m', $res, $mount_matches, PREG_SET_ORDER)) {
            return [];
        }

        // Store them here
        $mounts = [];

        // Deal with each entry
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

    // Get ram stats
    public function getRAM()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Memory');
        }

        // Give
        return array(
            'type' => 'Physical',
            'total' => $this->kstat['unix:0:system_pages:pagestotal'] * $this->kstat['unix:0:seg_cache:slab_size'],
            'free' => $this->kstat['unix:0:system_pages:pagesfree'] * $this->kstat['unix:0:seg_cache:slab_size'],
            'swapInfo' => [],
        );
    }

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
            $ps = $this->exec->exec('ps', '-fe -o s');

            // Go through it
            foreach (explode("\n", trim($ps)) as $process) {

                // Decide what this is
                switch ($process) {
                    case 'S':
                        $result['totals']['sleeping']++;
                    break;
                    case 'Z':
                        $result['totals']['zombie']++;
                    break;
                    case 'R':
                    case 'O':
                        $result['totals']['running']++;
                    break;
                    case 'T':
                        $result['totals']['stopped']++;
                    break;
                }

                // Increment total
                ++$result['proc_total'];
            }
        }

        // Something bad happened
        catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `ps` to get process info');
        }

        // Give
        return $result;
    }

    // uptime
    public function getUpTime()
    {
        $booted = $this->kstat['unix:0:system_misc:boot_time'];

        return array(
            'text' => Common::secondsConvert(time() - $booted),
            'bootedTimestamp' => $booted,
        );
    }

    // load
    public function getLoad()
    {
        // Give
        return array(
            'now' => round($this->kstat['unix:0:system_misc:avenrun_1min'] / 256, 2),
            '5min' => round($this->kstat['unix:0:system_misc:avenrun_5min'] / 256, 2),
            '15min' => round($this->kstat['unix:0:system_misc:avenrun_10min'] / 256, 2),
        );
    }

    public function getCPU()
    {
        $cpus = [];

        foreach (explode("\n", $this->kstat['cpu_info:0:']) as $line) {
            if (!preg_match('/^cpu_info(\d+):(\S+)\s+(.+)/', trim($line), $m)) {
                continue;
            }
            if (!isset($cpus[$m[1]])) {
                $cpus[$m[1]] = [];
            }

            $cur_cpu = &$cpus[$m[1]];

            $value = trim($m[3]);
            switch ($m[2]) {
                case 'vendor_id':
                    $cur_cpu['Vendor'] = $value;
                break;

                case 'clock_MHz':
                    $cur_cpu['MHz'] = $value;
                break;

                case 'brand':
                    $cur_cpu['Model'] = $value;
                break;
            }
        }

        return $cpus;
    }

    public function getNet()
    {
        $nets = [];

        // ifconfig for nics/statuses
        try {
            $ifconfig = $this->exec->exec('ifconfig', '-a');
        } catch (Exception $e) {
            Errors::add('Solaris Core', 'Failed running ifconfig -a.');

            return [];
        }

        foreach (explode("\n", $ifconfig) as $line) {
            if (!preg_match('/^([^:]+):[^<]+<([^>]+)>/', trim($line), $m)) {
                continue;
            }

            $nic = $m[1];
            $flags = explode(',', strtolower($m[2]));

            if (isset($nets[$nic])) {
                continue;
            }

            $type = null;

            if (in_array('loopback', $flags)) {
                $type = 'Loopback';
            }

            $nets[$nic] = array(

                // To be filled in later
                'recieved' => array(
                    'bytes' => null,
                    'packets' => null,
                    'errors' => null,
                ),
                'sent' => array(
                    'bytes' => null,
                    'bytes' => null,
                    'errors' => null,
                ),

                // Should find a better way of getting these
                'state' => in_array('up', $flags) ? 'up' : 'Unknown',
                'type' => $type,
            );
        }

        // kstat for more stats
        foreach (explode("\n", $this->kstat['link:0:']) as $line) {
            if (!preg_match('/^([^:]+):(\S+)\s+(\S+)/', trim($line), $m)) {
                continue;
            }

            list(, $nic, $key, $value) = $m;

            if (!isset($nets[$nic])) {
                continue;
            }

            $cur_nic = &$nets[$nic];

            switch ($key) {
                case 'ipackets64':
                    $cur_nic['recieved']['packets'] = $value;
                break;
                case 'opackets64':
                    $cur_nic['sent']['packets'] = $value;
                break;
                case 'rbytes64':
                    $cur_nic['recieved']['bytes'] = $value;
                break;
                case 'obytes64':
                    $cur_nic['sent']['bytes'] = $value;
                break;
            }
        }

        // dladm for more stats...
        try {
            $dladm = $this->exec->exec('dladm', 'show-link');
            foreach (explode("\n", $dladm) as $line) {
                if (!preg_match('/^(\S+)\s+(\S+)\s+\d+\s+(\S+)/', $line, $m)) {
                    continue;
                }

                if (!isset($nets[$m[1]])) {
                    continue;
                }

                if (!$nets[$m[1]]['type'] && $m[2] == 'phys') {
                    $nets[$m[1]]['type'] = 'Physical';
                }

                if (!$nets[$m[1]]['state'] || $nets[$m[1]]['state'] == 'unknown') {
                    $nets[$m[1]]['state'] = $m[3];
                }
            }
        } catch (Exception $e) {
            Errors::add('Solaris Core', 'Failed running dladm show-link.');

            return [];
        }

        return $nets;
    }
}
