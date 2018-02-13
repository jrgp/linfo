<?php

/*

This shows a cursory list of containers managed by LXD and their state. It works by
hitting /var/lib/lxd/unix.socket, so make sure your web server use can hit that.

To enable this extension, add/tweak the following to your config.inc.php

$settings['extensions']['lxd'] = true;

*/

/**
 * This file is part of Linfo (c) 2018 Joseph Gillotti.
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
 * Get status on lxd containers.
 */
class Lxd implements Extension
{
    private
        $sock_path = null,
        $vms = false;

    public function __construct(Linfo $linfo)
    {
        $settings = $linfo->getSettings();
        $this->sock_path = 'unix:///var/lib/lxd/unix.socket';
    }

    public function work()
    {
        $t = new Timer('lxd extension');

        $vm_list = $this->hitIt('/1.0/containers');

        if (!$vm_list)
            return;

        $this->vms = [];

        foreach ($vm_list['metadata'] as $vm_url) {
            $vm_info = $this->hitIt($vm_url);
            $this->vms[] = array(
              'name' => $vm_info['metadata']['name'],
              'status' => $vm_info['metadata']['status'],
              'cpu_limit' => isset($vm_info['metadata']['expanded_config']['limits.cpu']) ? $vm_info['metadata']['expanded_config']['limits.cpu'] : 'Unlimited',
              'memory_limit' => isset($vm_info['metadata']['expanded_config']['limits.memory']) ? $vm_info['metadata']['expanded_config']['limits.memory'] : 'Unlimited',
            );
        }
    }

    public function result()
    {
        if (!$this->vms)
          return false;

        $rows = [];

        $rows[] = array(
            'type' => 'header',
            'columns' => array(
                'VM Name',
                'Status',
                'Max CPUs',
                'Max Memory',
            ),
        );

        foreach($this->vms as $vm) {
            $rows[] = array(
                'type' => 'values',
                'columns' => array(
                    $vm['name'],
                    $vm['status'] == 'Running' ? '<span style="color: green;">Running</span>' : ($vm['status'] == 'Stopped' ? '<span style="color: maroon;">Off</span>' : $vm['status']),
                    $vm['cpu_limit'],
                    $vm['memory_limit'],
                ),
            );

        }

        return array(
            'root_title' => 'LXD Containers',
            'rows' => $rows,
        );
    }

    private function hitIt($url)
    {
      $sock = fsockopen($this->sock_path);

      if (!$sock) {
          Errors::add('lxd extension', 'Error connecting to socket ' . $this->sock_path);
          return false;
      }

      fwrite($sock, "GET $url HTTP/1.1\r\nHost: localhost\r\n\r\n");

      $size = null;

      // If we try reading past the http body, we hang forever, so specifically just
      // read up to that point
      while ($line = fgets($sock)) {
        if (preg_match('/^Content-Length: (\d+)/', $line, $m)) {
          $size = (int) $m[1];
          break;
        }
      };

      // The + 2 is to read past the \r\n
      $response = fread($sock, $size + 2);

      fclose($sock);

      return json_decode($response, true);
    }
}
