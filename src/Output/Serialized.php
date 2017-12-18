<?php

namespace Linfo\Output;

use Linfo\Linfo;

class Serialized implements Output
{
    protected $linfo;

    public function __construct(Linfo $linfo)
    {
        $this->linfo = $linfo;
    }

    public function output()
    {
        $settings = $this->linfo->getSettings();

        // Output buffering, along with compression (if supported)
        if (!isset($settings['compress_content']) || $settings['compress_content']) {
            ob_start(function_exists('ob_gzhandler') ? 'ob_gzhandler' : null);
        }

        echo serialize($this->linfo->getInfo());
    }
}
