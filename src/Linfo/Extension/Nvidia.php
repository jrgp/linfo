<?php

/*

This grabs wattages and temps for nvidia cards by using nvidia-smi

Installation:
 - The following lines must be added to your config.inc.php:
   $settings['extensions']['nvidia'] = true;

*/

/*
 * This file is part of Linfo (c) 2017 Joseph Gillotti.
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

