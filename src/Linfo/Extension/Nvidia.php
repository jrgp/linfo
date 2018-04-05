<?php

/*

This grabs wattages and temps for nvidia cards by using nvidia-smi

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['nvidia'] = true;

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
 * Get nvidia card temps from nvidia-smmi
 *
 * @author Joseph Gillotti
 */
class Nvidia implements Extension
{
    // Minimum version of Linfo required
    const
        LINFO_INTEGRATE = true,
        EXTENSION_NAME = 'nvidia';

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

        // Get card names and their IDs
        try {
            $cards_list = $this->_CallExt->exec('nvidia-smi', ' -L');
        } catch (Exception $e) {
            // messed up somehow
            Errors::add(self::EXTENSION_NAME.' Extension', $e->getMessage());
            return;
        }

        // Match it up
        if (!preg_match_all('/GPU (\d+): (.+) \(UUID:.+\)/', $cards_list, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach ($matches as $card) {
            $id = $card[1];
            $name = trim($card[2]);

            // Get temp and power for this card
            try {
                $card_stat = $this->_CallExt->exec('nvidia-smi', ' dmon -s p -c 1 -i '.$id);
            } catch (Exception $e) {
                Errors::add(self::EXTENSION_NAME.' Extension', $e->getMessage());
                continue;
            }

            if (preg_match('/(\d+)\s+(\d+)\s+(\d+)/', $card_stat, $match)) {
                if ($match[1] != $id)
                    continue;

            $info['Temps'][] = array(
                'path' => '',
                'name' => $name . ' Power',
                'temp' => $match[2],
                'unit' => 'W',
            );

            $info['Temps'][] = array(
                'path' => '',
                'name' => $name . ' Temperature',
                'temp' => $match[3],
                'unit' => 'C',
            );
            }
        }
    }

    // Not needed
    public function result()
    {
        return false;
    }
}

