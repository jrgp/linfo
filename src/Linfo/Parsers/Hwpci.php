<?php

/* Linfo
 *
 * Copyright (c) 2020 Joe Gillotti
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
use Linfo\Meta\Errors;
use Linfo\Common;

/**
 * Deal with pci.ids and usb.ids workings.
 *
 * @author Joe Gillotti
 */
class Hwpci
{
    private $pci_file, $usb_file, $os, $enable_cache, $cache_file;

    /**
     * Constructor.
     */
    public function __construct($usb_file, $pci_file, $os, $enable_cache = true)
    {
        $this->pci_file = $pci_file;
        $this->usb_file = $usb_file;
        $this->os = $os;
        $this->enable_cache = $enable_cache;

        // Allow the same web root to be used for multiple insances of linfo, across multiple machines using
        // nfs or whatever, and to have a different cache file for each
        $sys_id = is_readable('/proc/sys/kernel/hostname') ?
            '_'.substr(md5(Common::getContents('/proc/sys/kernel/hostname')), 0, 10) : '_x';

        // Path to the cache file
        $this->cache_file = dirname(dirname(dirname(__DIR__))).'/cache/ids_cache'.$sys_id;

        // Need to execute pciconf on bsd
        $this->exec = new CallExt();
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
    }

    /*
     * Parse vendor and device names out of hardware ID files. Works for USB and PCI
     */
    public function resolve_ids($file, $vendors, $device_keys){
        if ($file == '') {
            return [];
        }
        $file = @fopen($file, 'r');
        if (!$file){
            return [];
        }
        $result = [];
        $remaining = count($device_keys);
        $vendor_id = null;
        $vendor_name = null;
        while(($line = fgets($file)) && $remaining > 0) {
            $line = rtrim($line);
            if($line == '')
                continue;
            if($line[0] == '#')
                continue;
            if ($line[0] != "\t") {
                $vendor_id = substr($line, 0, 4);
                $vendor_name = substr($line, 6);
                // If we aren't looking for this vendor, skip parsing all of it
                if (!isset($vendors[$vendor_id]) || !$vendor_id || !$vendor_name) {
                    $vendor_id = null;
                    $vendor_name = null;
                }
            } elseif ($line[1] != "\t" && $vendor_id != null) {
                $device_id = substr($line, 1, 4);
                $device_name = substr($line, 7);
                if ($device_id && $device_name) {
                    $device_key = $vendor_id.'-'.$device_id;
                    if(isset($device_keys[$device_key])) {
                        $result[$device_key] = [$vendor_name, $device_name];
                        $remaining--;
                    }
                }
            }
        }
        fclose($file);
        return $result;
    }

    /*
     * Get device and vendor IDs for USB devices on Linux
     */
    function get_usb_ids_linux(){
        $devices = [];
        $vendors = [];
        $speeds = [];
        foreach ((array) @glob('/sys/bus/usb/devices/*', GLOB_NOSORT) as $path) {

            // Avoid the same device artificially appearing more than once
            if (strpos($path, ':') !== false) {
                continue;
            }

            $device_key = '';

            // First try uevent
            if (is_readable($path.'/uevent') &&
                preg_match('/^product=([^\/]+)\/([^\/]+)\/[^$]+$/m', strtolower(Common::getContents($path.'/uevent')), $match)) {
                $vendor_id = str_pad($match[1], 4, '0', STR_PAD_LEFT);
                $device_id = str_pad($match[2], 4, '0', STR_PAD_LEFT);
                $device_key = $vendor_id.'-'.$device_id;
                $vendors[$vendor_id] = true;
                $devices[$device_key] = isset($devices[$device_key]) ? $devices[$device_key] + 1: 1;
            }

            // And next modalias
            elseif (is_readable($path.'/modalias') &&
                preg_match('/^usb:v([0-9A-Z]{4})p([0-9A-Z]{4})/', Common::getContents($path.'/modalias'), $match)) {
                $vendor_id = strtolower($match[1]);
                $device_id = strtolower($match[2]);
                $device_key = $vendor_id.'-'.$device_id;
                $vendors[$vendor_id] = true;
                $devices[$device_key] = isset($devices[$device_key]) ? $devices[$device_key] + 1: 1;
            } else {
                // Forget it
                continue;
            }

            // Also get speed
            $speed = Common::getIntFromFile($path.'/speed');
            if ($speed) {
                $speeds[$device_key] = $speed * 1000 * 1000;
            }
        }
        return [
            'vendors' => $vendors,
            'devices' => $devices,
            'speeds' => $speeds,
        ];
    }

    /*
     * Get device and vendor IDs for PCI devices on Linux
     */
    private function get_pci_ids_linux(){
        $vendors = [];
        $devices = [];
        foreach ((array) @glob('/sys/bus/pci/devices/*', GLOB_NOSORT) as $path) {

            // See if we can use simple vendor/device files and avoid taking time with regex
            if (($f_device = Common::getContents($path.'/device', '')) && ($f_vend = Common::getContents($path.'/vendor', '')) &&
                $f_device != '' && $f_vend != '') {
                list(, $vendor_id) = explode('x', $f_vend, 2);
                list(, $device_id) = explode('x', $f_device, 2);
                $device_key = $vendor_id.'-'.$device_id;
                $vendors[$vendor_id] = true;
                $devices[$device_key] = isset($devices[$device_key]) ? $devices[$device_key] + 1: 1;
            }

            // Try uevent nextly
            elseif (is_readable($path.'/uevent') &&
                preg_match('/pci\_(?:subsys_)?id=(\w+):(\w+)/', strtolower(Common::getContents($path.'/uevent')), $match)) {
                list(, $vendor_id, $device_id) = $match;
                $device_key = $vendor_id.'-'.$device_id;
                $vendors[$vendor_id] = true;
                $devices[$device_key] = isset($devices[$device_key]) ? $devices[$device_key] + 1: 1;
            }

            // Now for modalias
            elseif (is_readable($path.'/modalias') &&
                preg_match('/^pci:v0{4}([0-9A-Z]{4})d0{4}([0-9A-Z]{4})/i', strtolower(Common::getContents($path.'/modalias')), $match)) {
                list(, $vendor_id, $device_id) = $match;
                $device_key = $vendor_id.'-'.$device_id;
                $vendors[$vendor_id] = true;
                $devices[$device_key] = isset($devices[$device_key]) ? $devices[$device_key] + 1: 1;
            }
        }
        return [
            'vendors' => $vendors,
            'devices' => $devices,
            'speeds' => [],
        ];
    }

    /*
     * Get device and vendor IDs for PCI devices on FreeBSD and similar
     */
    private function get_pci_ids_pciconf(){
        $vendors = [];
        $devices = [];
        try {
            $pciconf = $this->exec->exec('pciconf', '-l');
        } catch (Exception $e) {
            Errors::add('Linfo Core', 'Error using `pciconf -l` to get hardware info');
            return;
        }
        if (preg_match_all('/^.+chip=0x([a-z0-9]{4})([a-z0-9]{4})/m', $pciconf, $devs, PREG_SET_ORDER) == 0) {
            return;
        }
        foreach ($devs as $dev) {
            $vendor_id = $dev[2];
            $device_id = $dev[1];
            $device_key = $vendor_id.'-'.$device_id;
            $vendors[$vendor_id] = true;
            $devices[$device_key] = isset($devices[$device_key]) ? $devices[$device_key] + 1: 1;
        }
        return [
            'vendors' => $vendors,
            'devices' => $devices,
            'speeds' => [],
        ];
    }

    /*
     * Abstract away getting pci devices
     */
    private function get_pci_ids(){
        switch ($this->os) {
            case 'linux':
                return $this->get_pci_ids_linux();
            case 'freebsd':
            case 'dragonfly':
                return $this->get_pci_ids_pciconf();
        }
        return [];

    }

    /*
     * Abstract away getting usb devices
     */
    private function get_usb_ids(){
        switch ($this->os) {
            case 'linux':
                return $this->get_usb_ids_linux();
        }
        return [];
    }

    /*
     * Get any USB or PCI devices present on the host system
     */
    private function extractdevs($type){
        if ($type == 'PCI') {
            $file = $this->pci_file;
            $device_ids = $this->get_pci_ids();
        } elseif ($type == 'USB') {
            $file = $this->usb_file;
            $device_ids = $this->get_usb_ids();
        } else {
            return [];
        }
        if (!count($device_ids)) {
            return [];
        }
        $vendors = $device_ids['vendors'];
        $device_keys = $device_ids['devices'];
        $speeds = $device_ids['speeds'];
        $cache_fresh = false;
        $resolved_names = [];
        $my_cache_file = $this->cache_file . '.'.$type.'.json';
        if ($this->enable_cache && is_readable($my_cache_file)) {
            $cached_resolved_names = @json_decode(Common::getContents($my_cache_file), true);
            if (is_array($cached_resolved_names) && count(array_diff_key($device_keys, $cached_resolved_names)) == 0) {
                $cache_fresh = true;
                $resolved_names = $cached_resolved_names;
            }
        }
        if (!$cache_fresh) {
            $resolved_names = $this->resolve_ids($file, $vendors, $device_keys);
            if ($this->enable_cache && is_writable(dirname($my_cache_file))) {
                $encoded = json_encode($resolved_names);
                @file_put_contents($my_cache_file, $encoded);
            }
        }
        $result = [];
        foreach($device_keys as $key => $count) {
            if (isset($resolved_names[$key])) {
                list($vendor, $device) = $resolved_names[$key];
                $result[] = [
                    'vendor' => $vendor,
                    'device' => $device,
                    'type' => $type,
                    'count' => $count,
                    'speed' => isset($speeds[$key]) ? $speeds[$key] : null];
            }
        }
        return $result;
    }

    /**
     * Compile and return USB and PCI devices in a sorted list
     */
    public function result(){
        $usb = $this->extractdevs('USB');
        $pci = $this->extractdevs('PCI');
        $all_devices = array_merge($usb, $pci);

        $sort_type = [];
        $sort_vendor = [];
        $sort_device = [];

        foreach($all_devices as $device) {
            $sort_type[] = $device['type'];
            $sort_vendor[] = $device['vendor'];
            $sort_device[] = $device['device'];
        }

        array_multisort($sort_type, SORT_ASC, $sort_vendor, SORT_ASC, $sort_device, SORT_ASC, $all_devices);

        return $all_devices;
    }
}
