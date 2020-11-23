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
use Linfo\Parsers\MacSystemProfiler;
use Linfo\Leveldict;

/*
 * Alpha osx class
 * Differs very slightly from the FreeBSD, especially in the fact that
 * only root can access dmesg
 */

class Darwin extends BSDcommon
{
    // Encapsulate these
    protected $settings,
        $exec,
        $dmesg;

    // Start us off
    public function __construct($settings)
    {

        // Instantiate parent
        parent::__construct($settings);

        // We search these folders for our commands
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/sbin'));

        // We need these sysctl values
        $this->GetSysCTL(array(
            'machdep.cpu.vendor',
            'machdep.cpu.brand_string',
            'hw.cpufrequency',
            'hw.ncpu',
            'vm.swapusage',
            'hw.memsize',
            'hw.usermem',
            'kern.boottime',
            'vm.loadavg',
            'hw.model',
        ), false);

        // And get this info for when the above fails
        try {
            $output = $this->exec->exec('system_profiler', 'SPHardwareDataType SPSoftwareDataType SPPowerDataType');
            $profilerParser = new MacSystemProfiler(explode("\n", $output));
            $this->systemProfiler = $profilerParser->parse();
        } catch (Exception $e) {
            // Meh
            Errors::add('Linfo Mac OS 10', 'Error using system_profiler');
            $this->systemProfiler = new Leveldict;
        }
    }

    // What we should leave out
    public function getContains()
    {
        return array(
                'hw_vendor' => false,
                'drives_rw_stats' => false,
                'drives_vendor' => false,
                'nic_type' => false,
                'nic_port_speed' => false,
            );
    }

    // Operating system
    public function getOS()
    {
        $version = $this->systemProfiler->get(['Software', 'System Software Overview', 'System Version']);
        return 'Darwin ('.($version ? $version  : 'Mac OS X').')';
    }

    // Hostname
    public function getHostname()
    {
        $name = $this->systemProfiler->get(['Software', 'System Software Overview', 'Computer Name']);
        return $name ? $name : php_uname('n');
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
        if (preg_match_all('/(.+)\s+on\s+(.+)\s+\((\w+).*\)\n/i', $res, $m, PREG_SET_ORDER) == 0) {
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

    // Get network interfaces
    public function getNet()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Network Devices');
        }

        // Store return vals here
        $return = [];

        // Use netstat to get info
        try {
            $netstat = $this->exec->exec('netstat', '-nbdi');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `netstat` to get network info');

            return $return;
        }

        // Initially get interfaces themselves along with numerical stats
        //
        // Example output:
        // Name  Mtu   Network       Address            Ipkts Ierrs     Ibytes    Opkts Oerrs     Obytes  Coll Drop
        // lo0   16384 <Link#1>                          1945     0     429565     1945     0     429565     0
        // en0   1500  <Link#4>    58:b0:35:f9:fd:2b        0     0          0        0     0      59166     0
        // fw0   4078  <Link#6>    d8:30:62:ff:fe:f5:c8:9c        0     0          0        0     0        346     0
        if (preg_match_all(
            '/^
			([a-z0-9*]+)\s*  # Name
			\w+\s+           # Mtu
			<Link\#\w+>      # Network
			(?:\D+|\s+\w+:\w+:\w+:\w+:\w+:\w+\s+)  # MAC address
			(\w+)\s+  # Ipkts
			(\w+)\s+  # Ierrs
			(\w+)\s+  # Ibytes
			(\w+)\s+  # Opkts
			(\w+)\s+  # Oerrs
			(\w+)\s+  # Obytes
			(\w+)\s+  # Coll
			(\w+)?\s*  # Drop
			$/mx', $netstat, $netstat_match, PREG_SET_ORDER) == 0) {
            return $return;
        }

        // Try using ifconfig to get states of the network interfaces
        $statuses = [];
        try {
            // Output of ifconfig command
            $ifconfig = $this->exec->exec('ifconfig', '-a');

            // Set this to false to prevent wasted regexes
            $current_nic = false;

            // Go through each line
            foreach ((array) explode("\n", $ifconfig) as $line) {

                // Approachign new nic def
                if (preg_match('/^(\w+):/', $line, $m) == 1) {
                    $current_nic = $m[1];
                }

                // Hopefully match its status
                elseif ($current_nic && preg_match('/status: (\w+)$/', $line, $m) == 1) {
                    $statuses[$current_nic] = $m[1];
                    $current_nic = false;
                }
            }
        } catch (Exception $e) {
        }

        // Save info
        foreach ($netstat_match as $net) {

            // Determine status
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

            // Save info
            $return[$net[1]] = array(

                // These came from netstat
                'recieved' => array(
                    'bytes' => $net[4],
                    'errors' => $net[3],
                    'packets' => $net[2],
                ),
                'sent' => array(
                    'bytes' => $net[7],
                    'errors' => $net[6],
                    'packets' => $net[5],
                ),

                // This came from ifconfig -a
                'state' => $state,

                // Not sure where to get his
                'type' => '?',
            );
        }

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

        // Extract boot part of it
        if (preg_match('/^\{ sec \= (\d+).+$/', $this->sysctl['kern.boottime'], $m) == 0) {
            return '';
        }

        return array(
            'text' => Common::secondsConvert(time() - $m[1]),
            'bootedTimestamp' => $m[1],
        );
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

    // Get cpus
    public function getCPU()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('CPUs');
        }

        $model = '';
        $vendor = '';
        $freq = 0;
        $ncpu = 0;

        if($profiler_model = $this->systemProfiler->get(['Hardware', 'Hardware Overview', 'Chip'])) {
            $model = $profiler_model;
        } elseif (!empty($this->sysctl['machdep.cpu.brand_string'])) {
            $model = $this->sysctl['machdep.cpu.brand_string'];
        } elseif ($profiler_model = $this->systemProfiler->get([''])) {
            $model = $profiler_model;
        }

        if (!empty($this->sysctl['machdep.cpu.vendor'])) {
            $vendor = $this->sysctl['machdep.cpu.vendor'];
        }

        if (!empty($this->sysctl['hw.ncpu'])) {
            $ncpu = $this->sysctl['hw.ncpu'];
        } elseif ($profiler_ncpu = $this->systemProfiler->get(['Hardware', 'Hardware Overview', 'Total Number of Cores'])) {
            if (preg_match('/^(\d+) /', $profiler_ncpu, $m)) {
                $ncpu = (int)$m[1];
            }
        }

        if (!empty($this->sysctl['hw.cpufrequency'])) {
            $freq = $this->sysctl['hw.cpufrequency'] / 1000000;
        }

        // Store them here
        $cpus = [];

        // The same one multiple times (for now)
        for ($i = 0; $i < $ncpu; ++$i) {
            $cpu = [
                'Model' => $model,
                'Vendor' => $vendor,
            ];
            if ($freq) {
                $cpu['MHz'] = $freq;
            }
            $cpus[] = $cpu;
        }
        return $cpus;
    }

    // Get ram usage
    public function getRam()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Memory');
        }

        // Start us off
        $return = [];
        $return['type'] = 'Physical';
        $return['total'] = $this->sysctl['hw.memsize'];
        $return['swapTotal'] = 0;
        $return['swapFree'] = 0;
        $return['swapInfo'] = [];

        // FIXME: this is not correct. It is an innacurate approximation stopgap as hw.usermem is now longer accurate
        // on modern versions of macOS as it returns a negative number, possibly due to an integer overflow.
        //
        // Programmatically determining accurate memory usage on mac (like what Activity Monitor shows) is nearly
        // impossible.
        try {
            $top_output = $this->exec->exec('top', '-l 1 -n 0');
            if (!$top_output) {
                throw new Exception('Broken');
            }
            if (preg_match('/PhysMem.+ (\d+)M unused/', $top_output, $m)) {
                $return['free'] = $m[1] * 1024 * 1024;
            }
        } catch (Exception $e) {
            // Broken
            $return['free'] = $this->sysctl['hw.memsize'] - $this->sysctl['hw.usermem'];
        }

        // Sort out swap
        if (preg_match('/total = ([\d\.]+)M\s+used = ([\d\.]+)M\s+free = ([\d\.]+)M/', $this->sysctl['vm.swapusage'], $swap_match)) {
            list(, $swap_total, $swap_used, $swap_free) = $swap_match;
            $return['swapTotal'] = $swap_total * 1048576;
            $return['swapFree'] = $swap_free * 1048576;
        }

        // Return ram info
        return $return;
    }

    // Model of mac
    public function getModel()
    {
        $model = $this->systemProfiler->get(['Hardware', 'Hardware Overview', 'Model Name']);
        if ($model) {
            return $model;
        }

        if (preg_match('/^([a-zA-Z]+)/', $this->sysctl['hw.model'], $m)) {
            return $m[1];
        } else {
            return $this->sysctl['hw.model'];
        }
    }

    // Battery
    public function getBattery()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Battery');
        }

        // Store any we find here
        $batteries = [];

        $percentage = $this->systemProfiler->get(['Power', 'Battery Information', 'Charge Information', 'State of Charge (%)']);
        $device = $this->systemProfiler->get(['Power', 'Battery Information', 'Model Information', 'Device Name']);
        $fully_charged = $this->systemProfiler->get(['Power', 'Battery Information', 'Charge Information', 'Fully Charged']);
        $charging = $this->systemProfiler->get(['Power', 'Battery Information', 'Charge Information', 'Charging']);

        // If we have what we need, append
        if($percentage && $device && $fully_charged && $charging) {
            $batteries[] = [
                'device' => $device,
                'percentage' => $percentage,
                'state' => $charging == 'Yes' ? 'Charging' : ($fully_charged == 'yes' ? 'Fully Charged' : 'Discharging')
            ];
        }

        // Give
        return $batteries;
    }

    // drives
    public function getHD()
    {
        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Drives');
        }

        // Use system profiler to get info
        try {
            $res = $this->exec->exec('diskutil', ' list');
        } catch (Exception $e) {
            Errors::add('Linfo drives', 'Error using `diskutil list` to get drives');

            return [];
        }

        // Get it into lines
        $lines = explode("\n", $res);

        // Keep drives here
        $drives = [];

        // Work on tmp drive here
        $tmp = false;

        for ($i = 0, $num_lines = count($lines); $i < $num_lines; ++$i) {

            // A drive or partition entry
            if (preg_match('/^\s+(\d+):\s+([a-zA-Z0-9\_]+)\s+([\s\w]*) \*?(\d+(?:\.\d+)? [A-Z])B\s+([a-z0-9]+)/', $lines[$i], $m)) {

                // Get size sorted out
                $size_parts = explode(' ', $m[4]);
                switch ($size_parts[1]) {
                    case 'K':
                        $size = $size_parts[0] * 1000;
                    break;
                    case 'M':
                        $size = $size_parts[0] * 1000000;
                    break;
                    case 'G':
                        $size = $size_parts[0] * 1000000000;
                    break;
                    case 'T':
                        $size = $size_parts[0] * 1000000000000;
                    break;
                    case 'P':
                        $size = $size_parts[0] * 1000000000000000;
                    break;
                    default:
                        $size = false;
                    break;
                }

                // A drive?
                if ($m[1] == 0) {

                    // Finish prior drive
                    if (is_array($tmp)) {
                        $drives[] = $tmp;
                    }

                    // Try getting the name
                    $drive_name = false; // I'm pessimistic
                    try {
                        $drive_res = $this->exec->exec('diskutil', ' info /dev/'.$m[5]);
                        if (preg_match('/^\s+Device \/ Media Name:\s+(.+)/m', $drive_res, $drive_m)) {
                            $drive_name = $drive_m[1];
                        }
                    } catch (Exception $e) {
                    }

                    // Start this one off
                    $tmp = array(
                        'name' => $drive_name,
                        'vendor' => 'Unknown',
                        'device' => '/dev/'.$m[5],
                        'reads' => false,
                        'writes' => false,
                        'size' => $size,
                        'partitions' => [],
                    );
                }

                // Or a partition
                elseif ($m[1] > 0) {

                    // Save it
                    $tmp['partitions'][] = array(
                        'size' => $size,
                        'name' => '/dev/'.$m[5],
                    );
                }
            }
        }

        // Save a drive
        if (is_array($tmp)) {
            $drives[] = $tmp;
        }

        // Give
        return $drives;
    }

    public function getVirtualization()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Determining virtualization type');
        }

        // All results on google show this file only being present and related to VMware Fusion
        if (file_exists('/dev/vmmon')) {
            return array('type' => 'host', 'method' => 'VMWare');
        }

        return false;
    }

    public function getLoad()
    {
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Load Averages');
        }

        $loads = $this->sysctl['vm.loadavg'];

        if (preg_match('/([\d\.\,]+) ([\d\.\,]+) ([\d\.\,]+)/', $loads, $m)) {
            return array_combine(array('now', '5min', '15min'), array_slice($m, 1, 3));
        } else {
            return [];
        }
    }
}
