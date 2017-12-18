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
    private $_usb_file = '',
        $_pci_file = '',
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

        // Might need these
        $this->exec = new CallExt();
        $this->exec->setSearchPaths(array('/sbin', '/bin', '/usr/bin', '/usr/local/bin', '/usr/sbin'));
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

        $this->_fetchPciNames();
        $this->_fetchUsbNames();
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
