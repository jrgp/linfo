<?php

/*

This implements a ddhcpd.leases parser for dhcp3 servers.

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['dhcpd3_leases'] = true;
   $settings['dhcpd3_hide_mac'] = true;  // set to false to show mac addresses

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

use \DateTime;
use \DateTimeZone;

/**
 * Get status on dhcp3 leases.
 */
class Dhcpd3_leases implements Extension
{
    // How dates should look
    const
        DATE_FORMAT = 'm/d/y h:i A';

    // Store these tucked away here
    private
        $_hide_mac,
        $_res,
        $_leases = [];

    /**
     * localize important stuff.
     * @param Linfo $linfo
     */
    public function __construct(Linfo $linfo)
    {
        $settings = $linfo->getSettings();

        // Should we hide mac addresses, to prevent stuff like mac address spoofing?
        $this->_hide_mac = array_key_exists('dhcpd3_hide_mac', $settings) ? (bool) $settings['dhcpd3_hide_mac'] : false;

        // Find leases file
        $this->_leases_file = Common::locateActualPath(array(
            '/var/lib/dhcp/dhcpd.leases', // modern-er debian
            '/var/lib/dhcp3/dhcpd.leases',    // debian/ubuntu/others probably
            '/var/lib/dhcpd/dhcpd.leases',    // Possibly redhatish distros and others
            '/var/state/dhcp/dhcpd.leases',    // Arch linux, maybe others
            '/var/db/dhcpd/dhcpd.leases',    // FreeBSD
            '/var/db/dhcpd.leases',        // OpenBSD/NetBSD/Darwin(lol)/DragonFLY afaik
        ));
    }

    /**
     * Deal with it.
     */
    private function _call()
    {
        // Time this
        $t = new Timer('dhcpd3 leases extension');

        // We couldn't find leases file?
        if ($this->_leases_file === false) {
            Errors::add('dhcpd3 leases extension', 'couldn\'t find leases file');
            $this->_res = false;

            return;
        }

        // Get contents
        $contents = Common::getContents($this->_leases_file, false);

        // Couldn't?
        if ($contents === false) {
            Errors::add('dhcpd3 leases extension', 'Error getting contents of leases file');
            $this->_res = false;

            return;
        }

        // All dates in the file are in UTC format. Attempt finding out local time zone to convert UTC to local.
        // This prevents confusing the hell out of people.
        $do_date_conversion = false;
        $local_timezone = false;

        // Make sure we have what we need. Stuff this requires doesn't exist on certain php installations
        if (function_exists('date_default_timezone_get') && class_exists('DateTime') && class_exists('DateTimeZone')) {
            // I only want this called once, hence value stored here. It also might fail
            $local_timezone = @date_default_timezone_get();

            // Make sure it didn't fail
            if ($local_timezone !== false && is_string($local_timezone)) {
                $do_date_conversion = true;
            } // Say we'll allow conversion later on
        }

        // Get it into lines
        $lines = explode("\n", $contents);

        $seen_ips = [];

        // Store temp entries here
        $curr = false;

        // Parse each line, while ignoring certain useless'ish values
        // I'd do a single preg_match_all() using multiline regex, but the values in each lease block are inconsistent. :-/
        foreach ($lines as $line) {

            // Kill padding whitespace
            $line = trim($line);

            // Last line in entry
            if ($line == '}') {
                // Have we a current entry to save?
                if (is_array($curr)) {
                    $this->_leases[] = $curr;
                }

                // Make it empty for next time
                $curr = false;
            }

            // First line in entry. Save IP
            elseif (preg_match('/^lease (\d+\.\d+\.\d+\.\d+) \{$/', $line, $m)) {
                $curr = array('ip' => $m[1]);
                $seen_ips[$m[1]] = isset($seen_ips[$m[1]]) ? $seen_ips[$m[1]] + 1 : 1;
            }

            // Line with lease start
            elseif ($curr && preg_match('/^starts \d+ (\d+\/\d+\/\d+ \d+:\d+:\d+);$/', $line, $m)) {

                // Get it in unix time stamp for prettier formatting later and easier tz offset conversion
                $curr['lease_start'] = strtotime($m[1]);

                // Handle offset conversion
                if ($do_date_conversion) {

                    // This handy class helps out with timezone offsets. Pass it original date, not unix timestamp
                    $d = new DateTime($m[1], new DateTimeZone($local_timezone));
                    $offset = $d->getOffset();

                    // If ofset looks good, deal with it
                    if (is_numeric($offset) && $offset != 0) {
                        $curr['lease_start'] += $offset;
                    }
                }
            }

            // Line with lease end
            elseif ($curr && preg_match('/^ends \d+ (\d+\/\d+\/\d+ \d+:\d+:\d+);$/', $line, $m)) {

                // Get it in unix time stamp for prettier formatting later and easier tz offset conversion
                $curr['lease_end'] = strtotime($m[1]);

                // Handle offset conversion
                if ($do_date_conversion) {

                    // This handy class helps out with timezone offsets. Pass it original date, not unix timestamp
                    $d = new DateTime($m[1], new DateTimeZone($local_timezone));
                    $offset = $d->getOffset();

                    // If ofset looks good, deal with it
                    if (is_numeric($offset) && $offset != 0) {
                        $curr['lease_end'] += $offset;
                    }
                }

                // Is this old?
                // The file seems to contain all leases since the dhcpd server was started for the first time
                if (time() > $curr['lease_end']) {

                    // Kill current entry and ignore any following parts of this lease
                    $curr = false;

                    // Jump out right now
                    continue;
                }
            }

            // Line with MAC address
            elseif (!$this->_hide_mac && $curr && preg_match('/^hardware ethernet (\w+:\w+:\w+:\w+:\w+:\w+);$/', $line, $m)) {
                $curr['mac'] = $m[1];
            }

            // [optional] Line with hostname
            elseif ($curr && preg_match('/^client\-hostname "([^"]+)";$/', $line, $m)) {
                $curr['hostname'] = $m[1];
            }
        }

        // Dedupe duplicates by only keeping the latest entries
        // for IPs which appear more than once. This logic works
        // as the leases file is kept sorted in time order ascending.
        foreach ($this->_leases as $key => $lease) {
            if ($seen_ips[$lease['ip']] > 1) {
                unset($this->_leases[$key]);
                $seen_ips[$lease['ip']]--;
            }
        }
    }

    /**
     * Do the job.
     */
    public function work()
    {
        $this->_call();
    }

    /**
     * Return result.
     *
     * @return false on failure|array of the leases
     */
    public function result()
    {
        // Don't bother if it didn't go well
        if ($this->_res === false) {
            return false;
        }

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
                'Lease Start',
                'Lease End',
            ) :

            // Hiding it indeed
                array(
                'IP Address',
                'Hostname',
                'Lease Start',
                'Lease End',
            ),
        );

        // Append each lease
        foreach($this->_leases as $lease) {
            $rows[] = array(
                'type' => 'values',
                'columns' =>

                // Not hiding mac addresses?
                !$this->_hide_mac ? array(
                    $lease['ip'],
                    $lease['mac'],
                    array_key_exists('hostname', $lease) ?
                        $lease['hostname'] : '<em>unknown</em>',
                    date(self::DATE_FORMAT, $lease['lease_start']),
                    date(self::DATE_FORMAT, $lease['lease_end']),
                ) :

                // Hiding them indeed
                array(
                    $lease['ip'],
                    array_key_exists('hostname', $lease) ?
                        $lease['hostname'] : '<em>unknown</em>',
                    date(self::DATE_FORMAT, $lease['lease_start']),
                    date(self::DATE_FORMAT, $lease['lease_end']),
                ),
            );
        }

        // Give it off
        return array(
            'root_title' => 'DHCPD IP Leases ('.count($this->_leases).')',
            'rows' => $rows,
        );
    }
}
