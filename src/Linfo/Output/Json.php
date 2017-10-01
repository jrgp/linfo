<?php

namespace Linfo\Output;

use Linfo\Linfo;
use Linfo\Exceptions\FatalException;

class Json implements Output
{
    protected $linfo;

    public function __construct(Linfo $linfo)
    {
        $this->linfo = $linfo;
    }

    public function output()
    {
        $settings = $this->linfo->getSettings();

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        // Make sure we have JSON
        if (!function_exists('json_encode')) {
            throw new FatalException('{"error":"JSON extension not loaded"}');
        }

        // Output buffering, along with compression (if supported)
        if (!isset($settings['compress_content']) || $settings['compress_content']) {
            ob_start(function_exists('ob_gzhandler') ? 'ob_gzhandler' : null);
        }

        $encoded = json_encode($this->linfo->getInfo());

        if ($encoded === false) {
            throw new FatalException('{"error":"Failed generating json: '.(function_exists('json_last_error_msg') ? json_last_error_msg() : json_last_error()).'"}');
        }

        // Give it. Support JSON-P like functionality if the ?callback param looks like a valid javascript
        // function name, including object traversal.
        echo array_key_exists('callback', $_GET) && preg_match('/^[a-z0-9\_\.]+$/i', $_GET['callback']) ?
          $_GET['callback'].'('.$encoded.')' : $encoded;

        // Send it all out
        if (!isset($settings['compress_content']) || $settings['compress_content']) {
            ob_end_flush();
        }
    }
}
