<?php

/*

This implements a raspberrypi status checker for temps

Installation: 
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['raspberrypi'] = true; 

*/

/*
 * This file is part of Linfo (c) 2011 Joseph Gillotti.
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

namespace Linfo\Extension;

use Linfo\Linfo;
use Linfo\Meta\Errors;
use Linfo\Meta\Timer;
use Linfo\Parsers\CallExt;
use Exception;

/**
 * Raspberry pi extension for temps.
 *
 * @author hongruichen
 */
class Raspberrypi implements Extension
{
    // Minimum version of Linfo required
    const
        LINFO_INTEGRATE = true,
        EXTENSION_NAME = 'raspberrypi';

    // Store these tucked away here
    private $_CallExt,
    $linfo;

    // Start us off
    public function __construct(Linfo $linfo)
    {
        $this->linfo = $linfo;
        $this->_CallExt = new CallExt();
        $this->_CallExt->setSearchPaths(array('/usr/bin', '/usr/local/bin', '/sbin', '/usr/local/sbin'));
    }

    // Work it, baby
    public function work()
    {
        $info = &$this->linfo->getInfo();

        // Make sure this is an array
        $info['Temps'] = (array) $info['Temps'];

        // Time this
        $t = new Timer(self::EXTENSION_NAME.' Extension');


        for ($v = false, $file = @fopen('/sys/class/thermal/thermal_zone0/temp', 'r'); $file != false && $contents = fgets($file);)
        {
            $temp = floatval($contents);
            $temp = $temp / 1000;

            // Save this one
            $info['Temps'][] = array(
                'path' => '',
                'name' => 'cpu',
                'temp' => strval($temp),
                'unit' => 'degree',
            );
        }
        $file && @fclose($file);
    }

    // Not needed
    public function result()
    {
        return false;
    }
}
