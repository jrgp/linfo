<?php

/*

This implements a ipmi status checker for temps/voltages

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['ipmi'] = true;

 - The ipmitool command most likely needs to be run as root, so,
   if you don't have php running as root, configure sudo appropriately
   for the user the php scripts are running as, comment out 'Defaults    requiretty' in your sudoers
   file, and add 'ipmitool' to the $settings['sudo_apps'] array in config.inc.php

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

/**
 * IPMI extension for temps/voltages.
 *
 * @author Joseph Gillotti
 */
class Ipmi implements Extension
{
    // Minimum version of Linfo required
    const
        LINFO_INTEGRATE = true,
        EXTENSION_NAME = 'ipmi';

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

        // Deal with calling it
        try {
            $result = $this->_CallExt->exec('ipmitool', ' sdr');
        } catch (Exception $e) {
            // messed up somehow
            Errors::add(self::EXTENSION_NAME.' Extension', $e->getMessage());

            return;
        }

        // Match it up
        if (!preg_match_all('/^([^|]+)\| ([\d\.]+ (?:Volts|degrees [CF]))\s+\| ok$/m', $result, $matches, PREG_SET_ORDER)) {
            return;
        }

        // Go through with it
        foreach ($matches as $m) {

            // Separate them by normal spaces
            $v_parts = explode(' ', trim($m[2]));

            // Deal with the type of it
            switch ($v_parts[1]) {
                case 'Volts':
                    $unit = 'v';
                break;
                case 'degrees':
                    $unit = $v_parts[2];
                break;
                default:
                    $unit = '';
                break;
            }

            // Save this one
            $info['Temps'][] = array(
                'path' => 'N/A',
                'name' => trim($m[1]),
                'temp' => $v_parts[0],
                'unit' => $unit,
            );
        }
    }

    // Not needed
    public function result()
    {
        return false;
    }
}
