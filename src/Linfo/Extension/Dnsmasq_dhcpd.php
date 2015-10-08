<?php

/*

This parses dnsmasq's dhcpd leases file. Commonly used to show dynamic IP's given to
libvirt's virtual machines. This does not require libvirt-php to be installed.

Installation: 
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['Dnsmasq_dhcpd'] = true; 
   $settings['dnsmasq_hide_mac'] = true;  // set to false to show mac addresses
   $settings['dnsmasq_leases'] = 'path';  // change path to the leases file. defaults to /var/lib/libvirt/dnsmasq/default.leases

*/

/**
 * This file is part of Linfo (c) 2015 Joseph Gillotti.
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

/**
 * Keep out hackers...
 */
namespace Linfo\Extension;

use Linfo\Linfo;
use Linfo\Common;
use Linfo\Meta\Errors;
use Linfo\Meta\Timer;

/**
 * Get status on dhcp3 leases.
 */
class Dnsmasq_dhcpd implements Extension
{
    // How dates should look
    const
        DATE_FORMAT = 'm/d/y h:i A';

    // Store these tucked away here
    private
        $_hide_mac,
        $_leases = array();

    /**
     * localize important stuff.
     * @param Linfo $linfo
     */
    public function __construct(Linfo $linfo)
    {
        $settings = $linfo->getSettings();

        // Should we hide mac addresses, to prevent stuff like mac address spoofing?
        $this->_hide_mac = array_key_exists('dnsmasq_hide_mac', $settings) ? (bool) $settings['dnsmasq_hide_mac'] : false;

        // Find leases file
        $this->_leases_file = isset($settings['dnsmasq_leases']) && is_file($settings['dnsmasq_leases']) ?
          $settings['dnsmasq_leases'] : Common::locateActualPath(array(
              '/var/lib/libvirt/dnsmasq/default.leases', 
          ));
    }

    /**
     * Do the job.
     */
    public function work()
    {
        $t = new Timer('dnsmasq leases extension');

        foreach (Common::getLines($this->_leases_file) as $line) {
            if (!preg_match('/^(\d+) ([a-z0-9:]+) (\S+) (\S+)/', $line, $m))
                continue;
            $this->_leases[] = array_combine(array('lease_end', 'mac', 'ip', 'hostname'), array_slice($m, 1));
        }
    }

    /**
     * Return result.
     * 
     * @return array of the leases
     */
    public function result()
    {
        // Store rows here
        $rows = array();

        // Start showing connections
        $rows[] = array(
            'type' => 'header',
            'columns' =>

            // Not hiding mac address?
            !$this->_hide_mac ? array(
                'IP Address',
                'MAC Address',
                'Hostname',
                'Lease End',
            ) :

            // Hiding it indeed
                array(
                'IP Address',
                'Hostname',
                'Lease End',
            ),
        );

        // Append each lease
        foreach ($this->_leases as $lease) {
            $rows[] = array(
                'type' => 'values',
                'columns' =>

                // Not hiding mac addresses?
                !$this->_hide_mac ? array(
                    $lease['ip'],
                    $lease['mac'],
                    array_key_exists('hostname', $lease) ?
                        $lease['hostname'] : '<em>unknown</em>',
                    date(self::DATE_FORMAT, $lease['lease_end']),
                ) :

                // Hiding them indeed
                array(
                    $lease['ip'],
                    array_key_exists('hostname', $lease) ?
                        $lease['hostname'] : '<em>unknown</em>',
                    date(self::DATE_FORMAT, $lease['lease_end']),
                ),
            );
        }

        // Give it off
        return array(
            'root_title' => 'DnsMasq DHCPD IP Leases',
            'rows' => $rows,
        );
    }
}
