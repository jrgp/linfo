<?php

/**
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
    private $_use_json = false,
        $_usb_file = '',
        $_pci_file = '',
        $_cache_file = '',
        $_existing_cache_vals = array(),
        $_usb_entries = array(),
        $_pci_entries = array(),
        $_usb_devices = array(),
        $_pci_devices = array(),
        $_result = array(),
        $exec;

    /**
     * Constructor.
     * @param $usb_file
     * @param $pci_file
     */
    public function __construct($usb_file, $pci_file)
    {

        // Localize paths to the ids files
        $this->_pci_file = $pci_file;
        $this->_usb_file = $usb_file;

        // Prefer json, but check for it
        $this->_use_json = function_exists('json_encode') && function_exists('json_decode');

        // Allow the same web root to be used for multiple insances of linfo, across multiple machines using 
        // nfs or whatever, and to have a different cache file for each
        $sys_id = is_readable('/proc/sys/kernel/hostname') ?
            '_'.substr(md5(Common::getContents('/proc/sys/kernel/hostname')), 0, 10) : '_x';

        // Path to the cache file
        $this->_cache_file = dirname(dirname(dirname(__DIR__))).'/cache/ids_cache'.$sys_id.($this->_use_json ? '.json' : '');

        // Load contents of cache
        $this->_populate_cache();

        // Might need these
        $this->exec = new CallExt();
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
    }

    /**
     * Run the cache file.
     */
    private function _populate_cache()
    {
        if ($this->_use_json) {
            if (is_readable($this->_cache_file) &&
            ($loaded = @json_decode(Common::getContents($this->_cache_file, ''), true)) && is_array($loaded)) {
                $this->_existing_cache_vals = $loaded;
            }
        } else {
            if (is_readable($this->_cache_file) &&
            ($loaded = @unserialize(Common::getContents($this->_cache_file, false))) && is_array($loaded)) {
                $this->_existing_cache_vals = $loaded;
            }
        }
    }

    /**
     * Get the USB ids from /sys.
     */
    private function _fetchUsbIdsLinux()
    {
        foreach ((array) @glob('/sys/bus/usb/devices/*', GLOB_NOSORT) as $path) {

            // First try uevent
            if (is_readable($path.'/uevent') &&
                preg_match('/^product=([^\/]+)\/([^\/]+)\/[^$]+$/m', strtolower(Common::getContents($path.'/uevent')), $match)) {
                $this->_usb_entries[str_pad($match[1], 4, '0', STR_PAD_LEFT)][str_pad($match[2], 4, '0', STR_PAD_LEFT)] = 1;
            }

            // And next modalias 
            elseif (is_readable($path.'/modalias') &&
                preg_match('/^usb:v([0-9A-Z]{4})p([0-9A-Z]{4})/', Common::getContents($path.'/modalias'), $match)) {
                $this->_usb_entries[strtolower($match[1])][strtolower($match[2])] = 1;
            }
        }
    }

    /**
     * Get the PCI ids from /sys.
     */
    private function _fetchPciIdsLinux()
    {
        foreach ((array) @glob('/sys/bus/pci/devices/*', GLOB_NOSORT) as $path) {

            // See if we can use simple vendor/device files and avoid taking time with regex
            if (($f_device = Common::getContents($path.'/device', '')) && ($f_vend = Common::getContents($path.'/vendor', '')) &&
                $f_device != '' && $f_vend != '') {
                list(, $v_id) = explode('x', $f_vend, 2);
                list(, $d_id) = explode('x', $f_device, 2);
                $this->_pci_entries[$v_id][$d_id] = 1;
            }

            // Try uevent nextly
            elseif (is_readable($path.'/uevent') &&
                preg_match('/pci\_(?:subsys_)?id=(\w+):(\w+)/', strtolower(Common::getContents($path.'/uevent')), $match)) {
                $this->_pci_entries[$match[1]][$match[2]] = 1;
            }

            // Now for modalias
            elseif (is_readable($path.'/modalias') &&
                preg_match('/^pci:v0{4}([0-9A-Z]{4})d0{4}([0-9A-Z]{4})/', Common::getContents($path.'/modalias'), $match)) {
                $this->_pci_entries[strtolower($match[1])][strtolower($match[2])] = 1;
            }
        }
    }

    /**
     * Use the pci.ids file to translate the ids to names.
     */
    private function _fetchPciNames()
    {
        for ($v = false, $file = @fopen($this->_pci_file, 'r'); $file != false && $contents = fgets($file);) {
            if (preg_match('/^(\S{4})\s+([^$]+)$/', $contents, $vend_match) == 1) {
                $v = $vend_match;
            } elseif (preg_match('/^\s+(\S{4})\s+([^$]+)$/', $contents, $dev_match) == 1) {
                if ($v && isset($this->_pci_entries[strtolower($v[1])][strtolower($dev_match[1])])) {
                    $this->_pci_devices[$v[1]][$dev_match[1]] = array('vendor' => rtrim($v[2]), 'device' => rtrim($dev_match[2]));
                }
            }
        }
        $file && @fclose($file);
    }

    /**
     * Use the usb.ids file to translate the ids to names.
     */
    private function _fetchUsbNames()
    {
        for ($v = false, $file = @fopen($this->_usb_file, 'r'); $file != false && $contents = fgets($file);) {
            if (preg_match('/^(\S{4})\s+([^$]+)$/', $contents, $vend_match) == 1) {
                $v = $vend_match;
            } elseif (preg_match('/^\s+(\S{4})\s+([^$]+)$/', $contents, $dev_match) == 1) {
                if ($v && isset($this->_usb_entries[strtolower($v[1])][strtolower($dev_match[1])])) {
                    $this->_usb_devices[strtolower($v[1])][$dev_match[1]] = array('vendor' => rtrim($v[2]), 'device' => rtrim($dev_match[2]));
                }
            }
        }
        $file && @fclose($file);
    }

    /**
     * Decide if the cache file is sufficient enough to not parse the ids files.
     */
    private function _is_cache_worthy()
    {
        $pci_good = true;
        foreach (array_keys($this->_pci_entries) as $vendor) {
            foreach (array_keys($this->_pci_entries[$vendor]) as $dever) {
                if (!isset($this->_existing_cache_vals['hw']['pci'][$vendor][$dever])) {
                    $pci_good = false;
                    break 2;
                }
            }
        }
        $usb_good = true;
        foreach (array_keys($this->_usb_entries) as $vendor) {
            foreach (array_keys($this->_usb_entries[$vendor]) as $dever) {
                if (!isset($this->_existing_cache_vals['hw']['usb'][$vendor][$dever])) {
                    $usb_good = false;
                    break 2;
                }
            }
        }

        return array('pci' => $pci_good, 'usb' => $usb_good);
    }

    /*
     * Write cache file with latest info
     *
     * @access private
     */
    private function _write_cache()
    {
        if (is_writable(dirname(dirname(dirname(__DIR__))).'/cache')) {
            @file_put_contents($this->_cache_file, $this->_use_json ?
                json_encode(array(
                    'hw' => array(
                        'pci' => $this->_pci_devices,
                        'usb' => $this->_usb_devices,
                    ),
                ))
                : serialize(array(
                    'hw' => array(
                        'pci' => $this->_pci_devices,
                        'usb' => $this->_usb_devices,
                    ),
            )));
        }
    }

    /*
     * Parse pciconf to get pci ids
     *
     * @access private
     */
    private function _fetchPciIdsPciConf()
    {
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
            $this->_pci_entries[$dev[2]][$dev[1]] = 1;
        }
    }

    /**
     * Do its goddam job.
     * @param $os
     */
    public function work($os)
    {
        switch ($os) {
            case 'linux':
                $this->_fetchPciIdsLinux();
                $this->_fetchUsbIdsLinux();
            break;
            case 'freebsd':
            case 'dragonfly':
                $this->_fetchPciIdsPciConf();
            break;
            default:
                return;
            break;
        }
        $worthiness = $this->_is_cache_worthy();
        $save_cache = false;
        if (!$worthiness['pci']) {
            $save_cache = true;
            $this->_fetchPciNames();
        } else {
            $this->_pci_devices = isset($this->_existing_cache_vals['hw']['pci']) ? $this->_existing_cache_vals['hw']['pci'] : array();
        }
        if (!$worthiness['usb']) {
            $save_cache = true;
            $this->_fetchUsbNames();
        } else {
            $this->_usb_devices = isset($this->_existing_cache_vals['hw']['usb']) ? $this->_existing_cache_vals['hw']['usb'] : array();
        }
        if ($save_cache) {
            $this->_write_cache();
        }
    }

     /**
      * Compile and return results.
      */
     public function result()
     {
         foreach (array_keys((array) $this->_pci_devices) as $v) {
             foreach ($this->_pci_devices[$v] as $d) {
                 $this->_result[] = array('vendor' => $d['vendor'], 'device' => $d['device'], 'type' => 'PCI');
             }
         }
         foreach (array_keys((array) $this->_usb_devices) as $v) {
             foreach ($this->_usb_devices[$v] as $d) {
                 $this->_result[] = array('vendor' => $d['vendor'], 'device' => $d['device'], 'type' => 'USB');
             }
         }

         return $this->_result;
     }
}
