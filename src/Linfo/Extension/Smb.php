<?php

/*

This impliments a current samba usage status

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['smb'] = true;


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
use Linfo\Meta\Errors;
use Linfo\Meta\Timer;
use Linfo\Parsers\CallExt;
use Exception;

/*
 * Get info on a samba install by running smbstatus
 */
class Smb implements Extension
{
    // Store these tucked away here
    private $_CallExt,
        $_res,
        $_date_format = 'm/d/y @ h:i A';

    // Localize important classes
    public function __construct(Linfo $linfo)
    {
        $this->_CallExt = new CallExt();
        $this->_CallExt->setSearchPaths(array('/usr/bin', '/usr/local/bin', '/sbin', '/usr/local/sbin'));
    }

    // call samba and parse it
    private function _call()
    {

        // Time this
        $t = new Timer('Samba Status extension');

        // Deal with calling it
        try {
            $result = $this->_CallExt->exec('smbstatus');
        } catch (Exception $e) {
            // messed up somehow
            Errors::add('Samba Status Extension', $e->getMessage());
            $this->_res = false;

            // Don't bother going any further
            return false;
        }

        // Split it into lines
        $lines = explode("\n", $result);

        // Store temp stuff here
        $connections = [];
        $services = [];
        $files = [];
        $current_location = false;

        // Parse
        for ($i = 0, $num = count($lines); $i < $num; ++$i) {

            // Deal with pointlessness appropriately
            $lines[$i] = trim($lines[$i]);

            // Is this pointless?
            if ($lines[$i] == '' || preg_match('/^\-+$/', $lines[$i])) {
                continue;
            }

            // Beginning connections list?
            elseif (preg_match('/^PID\s+Username\s+Group\s+Machine$/', $lines[$i])) {
                $current_location = 'c';
            }

            // A connection?
            elseif ($current_location == 'c' && preg_match('/^(\d+)\s+(\w+)\s+(\w+)\s+(\S+)\s+\(([^)]+)\)$/', $lines[$i], $connection_match)) {
                $connections[] = array(
                    'pid' => $connection_match[1],
                    'username' => $connection_match[2],
                    'group' => $connection_match[3],
                    'hostname' => $connection_match[4],
                    'ip' => $connection_match[5],
                );
            }

            // Beginning services list?
            elseif (preg_match('/^Service\s+pid\s+machine\s+Connected at$/', $lines[$i])) {
                $current_location = 's';
            }

            // A service?
            elseif ($current_location == 's' && preg_match('/^(\w+)\s+(\d+)\s+(\S+)\s+([a-zA-z]+ [a-zA-Z]+ \d+ \d+:\d+:\d+ \d+)$/', $lines[$i], $service_match)) {
                $services[] = array(
                    'service' => $service_match[1],
                    'pid' => $service_match[2],
                    'machine' => $service_match[3],
                    'date' => strtotime($service_match[4]),
                );
            }

            // Beginning locked files list?
            elseif (preg_match('/^Pid\s+Uid\s+DenyMode\s+Access\s+R\/W\s+Oplock\s+SharePath\s+Name\s+Time$/', $lines[$i])) {
                $current_location = 'f';
            }

            // A locked file?
            elseif ($current_location == 'f' && preg_match('/^(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+([A-Z]+)\s+([A-Z+]+)\s+(\S+)\s+(.+)\s+([a-zA-Z]+ [a-zA-Z]+ \d+ \d+:\d+:\d+ \d+)$/', $lines[$i], $file_match)) {
                $files[] = array(
                    'pid' => $file_match[1],
                    'uid' => $file_match[2],
                    'deny_mode' => $file_match[3],
                    'access' => $file_match[4],
                    'rw' => $file_match[5],
                    'oplock' => $file_match[6],
                    'share' => $file_match[7],
                    'filename' => $file_match[8],
                    'date' => strtotime($file_match[9]),
                );
            }
        }

        // Give result
        $this->_res = array(
            'connections' => $connections,
            'services' => $services,
            'files' => $files,
        );

        // Success
        return true;
    }

    public function work()
    {
        $this->_call();
    }
    public function result()
    {
        // Don't bother if it didn't go well
        if ($this->_res === false) {
            return false;
        }
        // it did; continue
        else {

            // Store rows here
            $rows = [];

            // Start showing connections
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    array(5, 'Connections'),
                ),
            );
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    'Username',
                    'Group',
                    array(3,'Machine'),
                ),
            );

            // Show them
            if (count($this->_res['connections']) > 0) {
                foreach ($this->_res['connections'] as $conn) {
                    $rows[] = array(
                        'type' => 'values',
                        'columns' => array(
                            $conn['username'],
                            $conn['group'],
                            array(3,$conn['hostname'].($conn['hostname'] != $conn['ip'] ? ' <span class="perc">('.$conn['ip'].')</span>' : '')),
                        ),
                    );
                }
            } else {
                $rows[] = array(
                    'type' => 'none',
                    'columns' => array(
                        array(5, 'None found'),
                    ),
                );
            }

            // Now services
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    array(5, 'Services'),
                ),
            );
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    'Service',
                    'Machine',
                    array(3,'Date'),
                ),
            );

            // Show them
            if (count($this->_res['services']) > 0) {
                // Show them
                foreach ($this->_res['services'] as $service) {
                    $rows[] = array(
                        'type' => 'values',
                        'columns' => array(
                            $service['service'],
                            $service['machine'],
                            array(3, date($this->_date_format, $service['date'])),
                        ),
                    );
                }
            } else {
                $rows[] = array(
                    'type' => 'none',
                    'columns' => array(
                        array(5, 'None found'),
                    ),
                );
            }

            // Files time
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    array(5, 'Locked files'),
                ),
            );
            $rows[] = array(
                'type' => 'header',
                'columns' => array(
                    'UID',
                    'Mode',
                    'Share',
                    'Filename',
                    'Date',
                ),
            );

            // Show them
            if (count($this->_res['files']) > 0) {
                foreach ($this->_res['files'] as $f) {

                    // See if we can turn the uid into a username
                    $username = false;
                    if (function_exists('posix_getpwuid')) {
                        if ($user_info = @posix_getpwuid($f['uid'])) {
                            $username = $user_info['name'];
                        }
                    }

                    // Try making better sense of the R/W column
                    switch ($f['rw']) {
                        case 'RDONLY':
                            $rw = 'Read Only';
                        break;
                        case 'RDWR':
                            $rw = 'Read/Write';
                        break;
                        case 'WRONLY':
                            $rw = 'Write Only';
                        break;
                        default:
                            $rw = false;
                        break;
                    }

                    // Save entry
                    $rows[] = array(
                        'type' => 'values',
                        'columns' => array(
                            $f['uid'].($username != false ? ' ('.$username.')' : ''),
                            $rw ? $rw : $f['rw'],
                            $f['share'],
                            $f['filename'],
                            date($this->_date_format, $f['date']),
                    ), );
                }
            } else {
                $rows[] = array(
                    'type' => 'none',
                    'columns' => array(
                        array(5, 'None found'),
                    ),
                );
            }

            // Give it off
            return array(
                'root_title' => 'Samba Status',
                'rows' => $rows,
            );
        }
    }
}
