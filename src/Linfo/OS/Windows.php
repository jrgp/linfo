<?php

/*
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo. If not, see <http://www.gnu.org/licenses/>.
 * 
*/

namespace Linfo\OS;

use Linfo\Meta\Timer;
use Linfo\Common;
use Linfo\Exceptions\FatalException;
use COM;

/**
 * Get info on Windows systems
 * Written and maintained by Oliver Kuckertz (mologie).
 */
class Windows extends OS
{
    // Keep these tucked away
    protected $settings;

    private $wmi, $windows_version;

    /**
     * Constructor. Localizes settings.
     *
     * @param array $settings of linfo settings
     * @throws FatalException
     */
    public function __construct($settings)
    {

        // Localize settings
        $this->settings = $settings;

        // Get WMI instance
        $this->wmi = new COM('winmgmts:{impersonationLevel=impersonate}//./root/cimv2');

        if (!is_object($this->wmi)) {
            throw new FatalException('This needs access to WMI. Please enable DCOM in php.ini and allow the current user to access the WMI DCOM object.');
        }
    }

    /**
     * Return a list of things to hide from view..
     *
     * @return array
     */
    public function getContains()
    {
        return array(
            'drives_rw_stats' => false,
            'nic_port_speed' => false,
        );
    }

    /**
     * getOS.
     * 
     * @return string current windows version
     */
    public function getOS()
    {
        foreach ($this->wmi->ExecQuery('SELECT Caption FROM Win32_OperatingSystem') as $os) {
            return $os->Caption;
        }

        return 'Windows';
    }

    /**
     * getKernel.
     * 
     * @return string kernel version
     */
    public function getKernel()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Kernel');
        }

        foreach ($this->wmi->ExecQuery('SELECT WindowsVersion FROM Win32_Process WHERE Handle = 0') as $process) {
            $this->windows_version = $process->WindowsVersion;

            return $process->WindowsVersion;
        }

        return 'Unknown';
    }

    /**
     * getHostName.
     * 
     * @return string the host name
     */
    public function getHostName()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Hostname');
        }

        foreach ($this->wmi->ExecQuery('SELECT Name FROM Win32_ComputerSystem') as $cs) {
            return $cs->Name;
        }

        return 'Unknown';
    }

    /**
     * getRam.
     * 
     * @return array the memory information
     */
    public function getRam()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Memory');
        }

        $total_memory = 0;
        $free_memory = 0;

        foreach ($this->wmi->ExecQuery('SELECT TotalPhysicalMemory FROM Win32_ComputerSystem') as $cs) {
            $total_memory = $cs->TotalPhysicalMemory;
            break;
        }

        foreach ($this->wmi->ExecQuery('SELECT FreePhysicalMemory FROM Win32_OperatingSystem') as $os) {
            $free_memory = $os->FreePhysicalMemory;
            break;
        }

        return array(
            'type' => 'Physical',
            'total' => $total_memory,
            'free' => $free_memory * 1024,
        );
    }

    /**
     * getCPU.
     * 
     * @return array of cpu info
     */
    public function getCPU()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('CPUs');
        }

        $cpus = array();
        $alt = false;
        $object = $this->wmi->ExecQuery('SELECT Name, Manufacturer, CurrentClockSpeed, NumberOfLogicalProcessors,LoadPercentage FROM Win32_Processor');

        if (!is_object($object)) {
            $object = $this->wmi->ExecQuery('SELECT Name, Manufacturer, CurrentClockSpeed,LoadPercentage FROM Win32_Processor');
            $alt = true;
        }

        foreach ($object as $cpu) {
            $curr = array(
                'Model' => $cpu->Name,
                'Vendor' => $cpu->Manufacturer,
                'MHz' => $cpu->CurrentClockSpeed,
            );

            if ($cpu->LoadPercentage != '') {
                $curr['usage_percentage'] = $cpu->LoadPercentage;
            }

            if (!$alt) {
                for ($i = 0; $i < $cpu->NumberOfLogicalProcessors; ++$i) {
                    $cpus[] = $curr;
                }
            } else {
                $cpus[] = $curr;
            }
        }

        return $cpus;
    }

    /**
     * getUpTime.
     * 
     * @return string uptime
     */
    public function getUpTime()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Uptime');
        }

        $booted_str = '';

        foreach ($this->wmi->ExecQuery('SELECT LastBootUpTime FROM Win32_OperatingSystem') as $os) {
            $booted_str = $os->LastBootUpTime;
            break;
        }

        $booted = array(
            'year' => substr($booted_str, 0, 4),
            'month' => substr($booted_str, 4, 2),
            'day' => substr($booted_str, 6, 2),
            'hour' => substr($booted_str, 8, 2),
            'minute' => substr($booted_str, 10, 2),
            'second' => substr($booted_str, 12, 2),
        );
        $booted_ts = mktime($booted['hour'], $booted['minute'], $booted['second'], $booted['month'], $booted['day'], $booted['year']);

        return array(
            'text' => Common::secondsConvert(time() - $booted_ts),
            'bootedTimestamp' => $booted_ts,
        );
    }

    /**
     * getHD.
     * 
     * @return array the hard drive info
     */
    public function getHD()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Drives');
        }

        $drives = array();
        $partitions = array();

        foreach ($this->wmi->ExecQuery('SELECT DiskIndex, Size, DeviceID, Type FROM Win32_DiskPartition') as $partition) {
            $partitions[$partition->DiskIndex][] = array(
                'size' => $partition->Size,
                'name' => $partition->DeviceID.' ('.$partition->Type.')',
            );
        }

        foreach ($this->wmi->ExecQuery('SELECT Caption, DeviceID, Index, Size FROM Win32_DiskDrive') as $drive) {
            $caption = explode(' ', $drive->Caption);
            $drives[] = array(
                'name' => $drive->Caption,
                'vendor' => reset($caption),
                'device' => $drive->DeviceID,
                'reads' => false,
                'writes' => false,
                'size' => $drive->Size,
                'partitions' => array_key_exists($drive->Index, $partitions) && is_array($partitions[$drive->Index]) ? $partitions[$drive->Index] : false,
            );
        }

        usort($drives, array('Linfo\OS\Windows', 'compare_drives'));

        return $drives;
    }

    /**
     * getTemps.
     * 
     * @return array the temps
     */
    public function getTemps()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Temperature');
        }

        return array(); // TODO
    }

    /**
     * getMounts.
     * 
     * @return array the mounted the file systems
     */
    public function getMounts()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Mounted file systems');
        }

        $volumes = array();

        if ($this->windows_version > '6.1.0000') {
            $object = $this->wmi->ExecQuery('SELECT Automount, BootVolume, Compressed, IndexingEnabled, Label, Caption, FileSystem, Capacity, FreeSpace, DriveType FROM Win32_Volume');
        } else {
            $object = $this->wmi->ExecQuery('SELECT Compressed, Name, FileSystem, Size, FreeSpace, DriveType FROM Win32_LogicalDisk');
        }

        foreach ($object as $volume) {
            $options = array();
            if ($this->windows_version > '6.1.0000') {
                if ($volume->Automount) {
                    $options[] = 'automount';
                }
                if ($volume->BootVolume) {
                    $options[] = 'boot';
                }
                if ($volume->IndexingEnabled) {
                    $options[] = 'indexed';
                }
            }
            if ($volume->Compressed) {
                $options[] = 'compressed';
            }
            $capacity = ($this->windows_version > '6.1.0000') ? $volume->Capacity : $volume->Size;
            $label = ($this->windows_version > '6.1.0000') ? $volume->Label : $volume->Name;
            $mount = ($this->windows_version > '6.1.0000') ? $volume->Caption : $label.'\\';
            $a = array(
                'device' => false,
                'label' => $label,
                'devtype' => '',
                'mount' => $mount,
                'type' => $volume->FileSystem,
                'size' => $capacity,
                'used' => $capacity - $volume->FreeSpace,
                'free' => $volume->FreeSpace,
                'free_percent' => 0,
                'used_percent' => 0,
                'options' => $options,
            );

            switch ($volume->DriveType) {
                case 2:
                    $a['devtype'] = 'Removable drive';
                    break;
                case 3:
                    $a['devtype'] = 'Fixed drive';
                    break;
                case 4:
                    $a['devtype'] = 'Remote drive';
                    break;
                case 5:
                    $a['devtype'] = 'CD-ROM';
                    break;
                case 6:
                    $a['devtype'] = 'RAM disk';
                    break;
                default:
                    $a['devtype'] = 'Unknown';
                    break;
            }

            if ($capacity != 0) {
                $a['free_percent'] = round($volume->FreeSpace / $capacity, 2) * 100;
                $a['used_percent'] = round(($capacity - $volume->FreeSpace) / $capacity, 2) * 100;
            }

            $volumes[] = $a;
        }

        usort($volumes, array('Linfo\OS\Windows', 'compare_mounts'));

        return $volumes;
    }

    /**
     * getDevs.
     * 
     * @return array of devices
     */
    public function getDevs()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Hardware Devices');
        }

        $devs = array();

        foreach ($this->wmi->ExecQuery('SELECT DeviceID, Caption, Manufacturer FROM Win32_PnPEntity') as $pnpdev) {
            $devId = explode('\\', $pnpdev->DeviceID);
            $type = reset($devId);
            if (($type != 'USB' && $type != 'PCI') || (empty($pnpdev->Caption) || $pnpdev->Manufacturer[0] == '(')) {
                continue;
            }
            $manufacturer = $pnpdev->Manufacturer;
            $caption = $pnpdev->Caption;
            if (function_exists('iconv')) {
                $manufacturer = iconv('Windows-1252', 'UTF-8//TRANSLIT', $manufacturer);
                $caption = iconv('Windows-1252', 'UTF-8//TRANSLIT', $caption);
            }
            $devs[] = array(
                'vendor' => $manufacturer,
                'device' => $caption,
                'type' => $type,
            );
        }

        // Sort by 1. Type, 2. Vendor
        usort($devs, array('Linfo\OS\Windows', 'compare_devices'));

        return $devs;
    }

    /**
     * getRAID.
     * 
     * @return array of raid arrays
     */
    public function getRAID()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('RAID');
        }

        return array();
    }

    /**
     * getLoad.
     * 
     * @return array of current system load values
     */
    public function getLoad()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Load Averages');
        }

        $load = array();
        foreach ($this->wmi->ExecQuery('SELECT LoadPercentage FROM Win32_Processor') as $cpu) {
            $load[] = $cpu->LoadPercentage;
        }

        return round(array_sum($load) / count($load), 2).'%';
    }

    /**
     * getNet.
     * 
     * @return array of network devices
     */
    public function getNet()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Network Devices');
        }

        $return = array();
        $i = 0;

        if ($this->windows_version > '6.1.0000') {
            $object = $this->wmi->ExecQuery('SELECT AdapterType, Name, NetConnectionStatus, GUID FROM Win32_NetworkAdapter WHERE PhysicalAdapter = TRUE');
        } else {
            $object = $this->wmi->ExecQuery('SELECT AdapterType, Name, NetConnectionStatus FROM Win32_NetworkAdapter WHERE NetConnectionStatus != NULL');
        }

        foreach ($object as $net) {
            // Save and get info for each
            $return[$net->Name] = array(
                'recieved' => array(
                    'bytes' => 0,
                    'errors' => 0,
                    'packets' => 0,
                ),
                'sent' => array(
                    'bytes' => 0,
                    'errors' => 0,
                    'packets' => 0,
                ),
                'state' => 0,
                'type' => $net->AdapterType,
            );
            switch ($net->NetConnectionStatus) {
                case 0:
                    $return[$net->Name]['state'] = 'down';
                    break;
                case 1:
                    $return[$net->Name]['state'] = 'Connecting';
                    break;
                case 2:
                    $return[$net->Name]['state'] = 'up';
                    break;
                case 3:
                    $return[$net->Name]['state'] = 'Disconnecting';
                    break;
                case 4:
                    $return[$net->Name]['state'] = 'down'; // MSDN 'Hardware not present'
                    break;
                case 5:
                    $return[$net->Name]['state'] = 'Hardware disabled';
                    break;
                case 6:
                    $return[$net->Name]['state'] = 'Hardware malfunction';
                    break;
                case 7:
                    $return[$net->Name]['state'] = 'Media disconnected';
                    break;
                case 8:
                    $return[$net->Name]['state'] = 'Authenticating';
                    break;
                case 9:
                    $return[$net->Name]['state'] = 'Authentication succeeded';
                    break;
                case 10:
                    $return[$net->Name]['state'] = 'Authentication failed';
                    break;
                case 11:
                    $return[$net->Name]['state'] = 'Invalid address';
                    break;
                case 12:
                    $return[$net->Name]['state'] = 'Credentials required';
                    break;
                default:
                    $return[$net->Name]['state'] = 'unknown';
                    break;
            }
            // @Microsoft: An index would be nice here indeed.
            if ($this->windows_version > '6.1.0000') {
                $canonname = preg_replace('/[^A-Za-z0-9- ]/', '_', $net->Name);
                $isatapname = 'isatap.'.$net->GUID;
                $result = $this->wmi->ExecQuery("SELECT BytesReceivedPersec, PacketsReceivedErrors, PacketsReceivedPersec, BytesSentPersec, PacketsSentPersec FROM Win32_PerfRawData_Tcpip_NetworkInterface WHERE Name = '$canonname' OR Name = '$isatapname'");
            } else {
                $canonname = preg_replace('/[^A-Za-z0-9- ]/', '_', $net->Name);
                $result = $this->wmi->ExecQuery("SELECT BytesReceivedPersec, PacketsReceivedErrors, PacketsReceivedPersec, BytesSentPersec, PacketsSentPersec FROM Win32_PerfRawData_Tcpip_NetworkInterface WHERE Name = '$canonname'");
            }
            foreach ($result as $netspeed) {
                $return[$net->Name]['recieved'] = array(
                    'bytes' => (int) $netspeed->BytesReceivedPersec,
                    'errors' => (int) $netspeed->PacketsReceivedErrors,
                    'packets' => (int) $netspeed->PacketsReceivedPersec,
                );
                $return[$net->Name]['sent'] = array(
                    'bytes' => (int) $netspeed->BytesSentPersec,
                    'erros' => 0,
                    'packets' => (int) $netspeed->PacketsSentPersec,
                );
            }
            ++$i;
        }

        return $return;
    }

    /**
     * getBattery.
     * 
     * @return array of battery status
     */
    public function getBattery()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Batteries');
        }

        return array(); // TODO
    }

    /**
     * getWifi.
     * 
     * @return array of wifi devices
     */
    public function getWifi()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Wifi');
        }
    }

    /**
     * getSoundCards.
     * 
     * @return array of soundcards
     */
    public function getSoundCards()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Sound cards');
        }

        $cards = array();
        $i = 1;

        foreach ($this->wmi->ExecQuery('SELECT Caption, Manufacturer FROM Win32_SoundDevice') as $card) {
            $manufacturer = $card->Manufacturer;
            $caption = $card->Caption;
            if (function_exists('iconv')) {
                $manufacturer = iconv('Windows-1252', 'UTF-8//TRANSLIT', $manufacturer);
                $caption = iconv('Windows-1252', 'UTF-8//TRANSLIT', $caption);
            }
            $cards[] = array(
                'number' => $i,
                'vendor' => $manufacturer,
                'card' => $caption,
            );
            ++$i;
        }

        return $cards;
    }

    /**
     * getProcessStats.
     * 
     * @return array of process stats
     */
    public function getProcessStats()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('Process Stats');
        }

        $result = array(
            'exists' => true,
            'proc_total' => 0,
            'threads' => 0,
        );

        foreach ($this->wmi->ExecQuery('SELECT ThreadCount FROM Win32_Process') as $proc) {
            $result['threads'] += (int) $proc->ThreadCount;
            ++$result['proc_total'];
        }

        return $result;
    }

    /**
     * getServices.
     * 
     * @return array the services
     */
    public function getServices()
    {
        return array(); // TODO
    }

    /**
     * getDistro.
     * 
     * @return array the distro,version or false
     */
    public function getDistro()
    {
        return false;
    }

    /**
     * getCPUArchitecture.
     * 
     * @return string the arch and bits
     */
    public function getCPUArchitecture()
    {

        // Time?
        if (!empty($this->settings['timer'])) {
            $t = new Timer('CPU architecture');
        }

        foreach ($this->wmi->ExecQuery('SELECT Architecture FROM Win32_Processor') as $cpu) {
            switch ($cpu->Architecture) {
                case 0:
                    return 'x86';
                case 1:
                    return 'MIPS';
                case 2:
                    return 'Alpha';
                case 3:
                    return 'PowerPC';
                case 6:
                    return 'Itanium-based systems';
                case 9:
                    return 'x64';
            }
        }

        return 'Unknown';
    }

    /**
     * @ignore
     * @param $a
     * @param $b
     * @return int
     */
    public static function compare_devices($a, $b)
    {
        if ($a['type'] == $b['type']) {
            if ($a['vendor'] == $b['vendor']) {
                if ($a['device'] == $b['device']) {
                    return 0;
                }

                return ($a['device'] > $b['device']) ? 1 : -1;
            }

            return ($a['vendor'] > $b['vendor']) ? 1 : -1;
        }

        return ($a['type'] > $b['type']) ? 1 : -1;
    }

    /**
     * @ignore
     * @param $a
     * @param $b
     * @return int
     */
    public static function compare_drives($a, $b)
    {
        if ($a['device'] == $b['device']) {
            return 0;
        }

        return ($a['device'] > $b['device']) ? 1 : -1;
    }

    /**
     * @ignore
     * @param $a
     * @param $b
     * @return int
     */
    public static function compare_mounts($a, $b)
    {
        if ($a['mount'] == $b['mount']) {
            return 0;
        }

        return ($a['mount'] > $b['mount']) ? 1 : -1;
    }
}
