<?php

/*

This shows a cursory list of running VMs managed by libvirt and their stats.
Requires libvirt php extension (http://libvirt.org/php/):
  sudo apt-get install php5-libvirt-php

To enable this extension, add/tweak the following to your config.inc.php

$settings['extensions']['libvirt'] = true;
$settings['libvirt_connection'] = array(
    'url' => 'qemu:///system', // For xen do 'xen:///' instead
    'credentials' => NULL
);


*/

/**
 * This file is part of Linfo (c) 2013 Joseph Gillotti.
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

namespace Linfo\Extension;

use Linfo\Linfo;
use Linfo\Common;
use Linfo\Meta\Errors;
use Linfo\Meta\Timer;

/**
 * Get status on libvirt VMs.
 */
class Libvirt implements Extension
{
    private
        $VMs = array(),
        $connection = false,
        $connectionSettings = array(),
        $hypervisor = false,
        $res = false;

    public function __construct(Linfo $linfo)
    {
        $settings = $linfo->getSettings();

        $this->connectionSettings = $settings['libvirt_connection'];
    }

    private function connect()
    {
        if (!($this->connection =
            @libvirt_connect($this->connectionSettings['url'], true))) {
            Errors::add('libvirt extension', 'Error connecting');
            $this->res = false;

            return false;
        }

        return true;
    }

    public function work()
    {
        $t = new Timer('libvirt extension');

        if (!extension_loaded('libvirt')) {
            Errors::add('libvirt extension', 'Libvirt PHP extension not installed');
            $this->res = false;

            return;
        }

        if (!$this->connect()) {
            Errors::add('libvirt extension', 'Failed connecting');
            return;
        }

        if ($hypervisor = libvirt_connect_get_hypervisor($this->connection)) {
          if (isset($hypervisor['hypervisor_string']) && $hypervisor['hypervisor_string'] != '') {
              $this->hypervisor = $hypervisor['hypervisor_string'];
          } else if (isset($hypervisor['hypervisor']) && $hypervisor['hypervisor'] != '') {
              $this->hypervisor = $hypervisor['hypervisor'];
          }
        }

        if (!($doms = libvirt_list_domains($this->connection))) {
            Errors::add('libvirt extension', 'Failed getting domain list');
            $this->res = false;
            return;
        }

        foreach ($doms as $name) {
            if (!($domain = libvirt_domain_lookup_by_name($this->connection, $name))) {
                continue;
            }

            if (!($info = libvirt_domain_get_info($domain)) || !is_array($info)) {
                continue;
            }

            $info['autostart'] = libvirt_domain_get_autostart($domain);

            if ($info['autostart'] == 1) {
                $info['autostart'] = 'Yes';
            } elseif ($info['autostart'] == 0) {
                $info['autostart'] = 'No';
            } else {
                $info['autostart'] = 'N/A';
            }

            $info['nets'] = array();

            $nets = @libvirt_domain_get_interface_devices($domain);

            foreach ($nets as $key => $net) {
                if (!is_numeric($key)) {
                    continue;
                }
                $info['nets'][] = $net;
            }

            $info['storage'] = array();

            foreach ((array) @libvirt_domain_get_disk_devices($domain) as $blockName) {
                if (!is_string($blockName)) {
                    continue;
                }

                // Sometime device exists but libvirt fails to get more docs. just settle for device name
                if (!($blockInfo = @libvirt_domain_get_block_info($domain, $blockName)) || !is_array($blockInfo)) {
                    $info['storage'][] = array(
                        'device' => $blockName,
                    );
                    continue;
                }

                if ($stats = @libvirt_domain_block_stats($domain, $blockName)) {
                    $blockInfo['stats'] = $stats;
                }

                if (isset($blockInfo['partition']) && !isset($blockInfo['file'])) {
                    $blockInfo['file'] = $blockInfo['partition'];
                }

                $info['storage'][] = $blockInfo;
            }

            $this->VMs[$name] = $info;
        }

        $this->res = true;
    }

    public function result()
    {
        if (!$this->res) {
            return false;
        }

        $rows[] = array(
            'type' => 'header',
            'columns' => array(
                'VM Name',
                'Status',
                'RAM Allocation',
                'CPUs',
                'CPU Time',
                'Autostart',
                'Block Storage',
                'Network Devices',
            ),
        );

        $running = 0;
        $allram = 0;

        foreach ($this->VMs as $name => $info) {
            $disks = array();

            foreach ($info['storage'] as $disk) {
                $extra_info = array();

                if (isset($disk['capacity'])) {
                    $extra_info[] = Common::byteConvert($disk['capacity'], 2) . ' size';
                }

                if (isset($disk['stats']) && is_array($disk['stats'])) {
                    $extra_info[] = Common::byteConvert($disk['stats']['rd_bytes'], 2) . ' read';
                    $extra_info[] = Common::byteConvert($disk['stats']['wr_bytes'], 2) . ' written';
                }

                $line = $disk['device'];

                if (isset($disk['file'])) {
                    $line .= ': ' . $disk['file'];
                }

                if (count($extra_info) > 0) {
                    $line .= ' <span class="caption">('.implode(', ', $extra_info).')</span>';
                }

                $disks[] = $line;
            }

            $rows[] = array(
                'type' => 'values',
                'columns' => array(
                    $name,
                    $info['state'] == 1 ? '<span style="color: green;">On</span>' : '<span style="color: maroon;">Off</span>',
                    Common::byteConvert($info['memory'] * 1024, 2),
                    $info['nrVirtCpu'],
                    $info['cpuUsed'] ? $info['cpuUsed'] : 'N/A',
                    $info['autostart'],
                    $disks ? implode('<br />', $disks) : 'None',
                    $info['nets'] ? implode('<br />', $info['nets']) : 'None',
                ),
            );

            if ($info['state'] == 1) {
                $running++;
                $allram += $info['memory'];
            }

        }

        // Give it off
        return array(
            'root_title' => 'libvirt Virtual Machines <span style="font-size: 80%;">'.($this->hypervisor ? ' ('.$this->hypervisor.') ' : '').
                            '('.$running.' running - using '.Common::byteConvert($allram * 1024, 2).' RAM)</span>',
            'rows' => $rows,
        );
    }
}
