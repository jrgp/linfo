<?php

namespace Linfo\Parsers;

class FileIO implements IO {

    private $path_prefix = false;

    public function __construct($path_prefix) {
        $this->path_prefix = $path_prefix;
    }

    // Get a file's contents, or default to second param
    public function getContents($file, $default = '')
    {
        if (is_string($this->path_prefix)) {
            $file = $this->path_prefix.$file;
        }

        return !is_file($file) || !is_readable($file) || !($contents = @file_get_contents($file)) ? $default : trim($contents);
    }

    // Like above, but in lines instead of a big string
    public function getLines($file)
    {
        return !is_file($file) || !is_readable($file) || !($lines = @file($file, FILE_SKIP_EMPTY_LINES)) ? [] : $lines;
    }
}
