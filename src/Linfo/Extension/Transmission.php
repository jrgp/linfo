<?php

/*

This implements a transmission-remote parsing extension which displays status of running torrents

Installation:
 - The following lines must be added to your config.inc.php:

    $settings['extensions']['transmission'] = true;

    // Set this to the URL you access transmission's web UI at, up until the port
    $settings['transmission_api_url'] = 'http://192.168.1.120:9091';


   // If you want download/upload/ratio/duration stats, make sure the web server user can
   // read this folder, which is in the home directory of hteu ser that transmission is
   // running as
   $settings['transmission_folder'] = '/home/user/.config/transmission/';

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
use Linfo\Parsers\CallExt;
use Exception;

/**
 * Get status on transmission torrents.
 */
class Transmission implements Extension
{
    private
        $_res,
        $_torrents = [],
        $_stats = false,
        $_url;

    /**
     * localize important stuff.
     * @param Linfo $linfo
     */
    public function __construct(Linfo $linfo)
    {
        $settings = $linfo->getSettings();

        $this->_url = array_key_exists('transmission_api_url', $settings) ? $settings['transmission_api_url'] : false;
        $this->_folder = array_key_exists('transmission_folder', $settings) && is_dir($settings['transmission_folder']) && is_readable($settings['transmission_folder']) ? $settings['transmission_folder'] : false;
    }

    /**
     * Use libcurl to interact with the transmission server API
     */
    private function hit_api()
    {
        if (!$this->_url) {
            Errors::add('transmission extension', 'Config option transmission_api_url not specified');
            return;
        }

        if (!extension_loaded('curl')) {
            Errors::add('transmission extension', 'Curl PHP extension not installed');
            return;
        }

        // See https://trac.transmissionbt.com/browser/trunk/extras/rpc-spec.txt for docs here
        // 1) Hit the API and get back a 409 with a token in the X-Transmission-Session-Id header
        // 2) Pass that token in as a header in the next request which will get our list of torrents

        $curl = curl_init();

        if (!$curl) {
            Errors::add('transmission extension', 'failed initializing curl');
            return;
        }

        $url = $this->_url . '/transmission/rpc';

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HEADER  => true,
            CURLOPT_RETURNTRANSFER  => true,
        ]);

        $result = curl_exec($curl);

        if (!$result) {
            Errors::add('transmission extension', 'failed running curl to get token');
            return;
        }

        if(!preg_match('/X-Transmission-Session-Id: (\S+)/', $result, $m)) {
            Errors::add('transmission extension', 'Failed parsing token');
            return;
        }

        $token = $m[1];

        $postdata = json_encode([
            'method' => 'torrent-get',
            'arguments' => [
                'fields'=> [
                    'name',
                    'percentDone',
                    'rateDownload',
                    'rateUpload',
                    'leftUntilDone',
                    'eta',
                    'status',
                    'uploadRatio',
                    'downloadedEver',
                    'uploadedEver',
                ]
            ]
        ]);


        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ['X-Transmission-Session-Id: '.$token],
            CURLOPT_URL => $url,
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HEADER  => false,
        ]);

        $result = curl_exec($curl);

        curl_close($curl);

        if (!$result) {
            Errors::add('transmission extension', 'failed running curl to get data');
            return;
        }

        $response = json_decode($result, true);

        if (!$response) {
            Errors::add('transmission extension', 'failed decoding transmission response as json');
            return;
        }

        if ($response['result'] != 'success') {
            Errors::add('transmission extension', 'transmission API call was not successful: '.$response['result']);
            return;
        }

        return $response['arguments']['torrents'];

    }

    /**
     * Deal with it.
     */
    private function _call()
    {
        $t = new Timer('Transmission extension');

        if ($this->_folder && ($stats_contents = Common::getContents($this->_folder.'stats.json', false)) && $stats_contents != false) {
            $stats_vals = @json_decode($stats_contents, true);
            if (is_array($stats_vals)) {
                $this->_stats = $stats_vals;
            }
        }

        $this->_res = true;

        $torrents = $this->hit_api();

        if (!$torrents) {
            $this->_res = false;
            return;
        }

        // See $transmission_url/transmission/web/javascript/torrent.js
        $status_map = [
            'Stopped',
            'CheckWait',
            'Check',
            'DownloadWait',
            'Downloading',
            'SeedWait',
            'Seeding',
        ];

        $sort_done = [];
        $sort_ratio = [];
        $sort_name = [];

        foreach($torrents as $torrent) {

            $this->_torrents[] = array(
                'done' => $torrent['percentDone'] * 100,
                'have' => $torrent['downloadedEver'],
                'uploaded' => $torrent['uploadedEver'],
                'eta' => $torrent['eta'],
                'up' => $torrent['rateUpload'] * 1024, // always in KIB
                'down' => $torrent['rateDownload'] * 1024, // ^
                'ratio' => $torrent['uploadRatio'],
                'state' => $status_map[$torrent['status']],
                'torrent' => $torrent['name'],
            );

            $sort_done[] = $torrent['percentDone'];
            $sort_ratio[] = (float) $torrent['uploadRatio'];
            $sort_name[] = $torrent['name'];
        }

        array_multisort($sort_done, SORT_DESC, $sort_ratio, SORT_DESC, $sort_name, SORT_ASC, $this->_torrents);
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
     * @return false on failure|array of the torrents
     */
    public function result()
    {
        if ($this->_res === false) {
            return false;
        }

        $rows = [];

        $rows[] = array(
            'type' => 'header',
            'columns' => array(
                'Torrent',
                array(1, 'Done', '10%'),
                'State',
                'Have',
                'Uploaded',
                'Time Left',
                'Ratio',
                'Up',
                'Down',
            ),
        );

        if (count($this->_torrents) == 0) {
            $rows[] = array(
                'type' => 'none',
                'columns' => array(
                    array(9, 'None found'),
                ),
            );
        } else {

            $status_tally = [];

            $status_tally['Downloaded'] = 0;
            $status_tally['Uploaded'] = 0;

            foreach ($this->_torrents as $torrent) {

                $status_tally[$torrent['state']] = !array_key_exists($torrent['state'], $status_tally) ? 1 : $status_tally[$torrent['state']] + 1;
                $have_bytes = $torrent['have'];
                $uploaded_bytes = $torrent['uploaded'];

                if (is_numeric($have_bytes) && $have_bytes > 0 && is_numeric($uploaded_bytes) && $uploaded_bytes > 0) {
                    $status_tally['Downloaded'] += $have_bytes;
                    $status_tally['Uploaded'] += $uploaded_bytes;
                }

                $rows[] = array(
                    'type' => 'values',
                    'columns' => array(
                        wordwrap(htmlspecialchars($torrent['torrent']), 50, ' ', true),

                        '<div class="bar_chart">
                          <div class="bar_inner" style="width: '.(int) $torrent['done'].'%;">
                            <div class="bar_text">
                              '.($torrent['done'] ? $torrent['done'].'%' : '0%').'
                            </div>
                          </div>
                        </div>',

                        $torrent['state'],
                        $have_bytes !== false ? Common::byteConvert($have_bytes) : $torrent['have'],
                        $uploaded_bytes !== false ? Common::byteConvert($uploaded_bytes) : 'None',
                        $torrent['eta'] == -1 ? 'N/A' : Common::secondsConvert($torrent['eta']),
                        max(0, $torrent['ratio']),
                        Common::byteConvert($torrent['up']).'/s',
                        Common::byteConvert($torrent['down']).'/s',
                    ),
                );
            }

            $status_tally['Ratio'] = $status_tally['Downloaded'] > 0 && $status_tally['Uploaded'] > 0 ? round($status_tally['Uploaded'] / $status_tally['Downloaded'], 2) : 'N/A';
            $status_tally['Downloaded'] = $status_tally['Downloaded'] > 0 ? Common::byteConvert($status_tally['Downloaded']) : 'None';
            $status_tally['Uploaded'] = $status_tally['Uploaded'] > 0 ? Common::byteConvert($status_tally['Uploaded']) : 'None';

            if (count($status_tally) > 0) {

                $tally_contents = [];

                foreach ($status_tally as $state => $tally) {
                    $tally_contents[] = $state.': '.$tally;
                }

                $rows[] = array(
                    'type' => 'values',
                    'columns' => array(
                        array(9, implode(', ', $tally_contents)),
                    ),
                );
            }
        }

        if (
            is_array($this->_stats) &&
            array_key_exists('downloaded-bytes', $this->_stats) &&
            array_key_exists('uploaded-bytes', $this->_stats) &&
            array_key_exists('seconds-active', $this->_stats
        )) {
            $extra_vals = array(
                'title' => 'Transmission Stats',
                'values' => array(
                    array('Total Downloaded', Common::byteConvert($this->_stats['downloaded-bytes'])),
                    array('Total Uploaded', Common::byteConvert($this->_stats['uploaded-bytes'])),
                    $this->_stats['uploaded-bytes'] > 0 && $this->_stats['downloaded-bytes'] > 0 ? array('Total Ratio', round($this->_stats['uploaded-bytes'] / $this->_stats['downloaded-bytes'], 3)) : false,
                    array('Duration', Common::secondsConvert($this->_stats['seconds-active'])),
                ),
            );
        } else {
            $extra_vals = false;
        }

        return array(
            'root_title' => 'Transmission Torrents',
            'rows' => $rows,
            'extra_type' => 'k->v',
            'extra_vals' => $extra_vals,
        );
    }
}
