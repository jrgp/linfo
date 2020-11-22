<?php

use \Linfo\Parsers\IO;

// Stand-in for the real FileIO class, so we can forge arbitrary files
// in memory, without the need for them to exist on disk
class MockedIO implements IO {

    private $files = [];

    function register($path, $contents) {
        $this->files[$path] = $contents;
    }

    function unregister($path) {
        unset($this->files[$path]);
    }

    // Get a file's contents, or default to second param
    public function getContents($file, $default = '')
    {
        return isset($this->files[$file]) ? $this->files[$file] : $default;
    }

    // Like above, but in lines instead of a big string
    public function getLines($file)
    {
        $contents = $this->getContents($file);
        return explode("\n", $contents);
    }
}
