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
        $_leases = [];

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
        $rows = [];

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
