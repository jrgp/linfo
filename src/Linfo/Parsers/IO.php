<?php

namespace Linfo\Parsers;

interface IO {
    public function getContents($file, $default='');
    public function getLines($file);
}
