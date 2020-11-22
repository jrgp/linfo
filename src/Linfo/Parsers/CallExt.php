<?php

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

namespace Linfo\Parsers;

use Linfo\Common;
use Linfo\Linfo;
use Exception;

/**
 * Class used to call external programs.
 */
class CallExt
{
    protected static $settings = [];

    public static function config(Linfo $linfo)
    {
        self::$settings = $linfo->getSettings();
    }

    /**
     * Maintain a count of how many external programs we call.
     *
     * @var int
     */
    public static $callCount = 0;

    /**
     * Store results of commands here to avoid calling them more than once.
     *
     * @var array
     */
    protected $cliCache = [];

    /**
     * Store paths to look for executables here.
     *
     * @var array
     */
    protected $searchPaths = [];

    /**
     * Say where we'll search for execs.
     *
     * @param array $paths list of paths
     */
    public function setSearchPaths($paths)
    {

        // Merge in possible custom paths
        if (array_key_exists('additional_paths', self::$settings) &&
            is_array(self::$settings['additional_paths']) &&
            count(self::$settings['additional_paths']) > 0) {

            $paths = array_merge(self::$settings['additional_paths'], $paths);
        }

        // Make sure they all have a trailing slash
        foreach ($paths as $k => $v) {
            $paths[$k] .= substr($v, -1) == '/' ? '' : '/';
        }

        // Save them
        $this->searchPaths = $paths;
    }

    /**
     * Run a command and cache its output for later.
     *
     * @throws Exception
     *
     * @param string $name     name of executable to call
     * @param string|array $switches command arguments
     */
    public function exec($name, $switches = '')
    {

        // Accept an array of switches and if so, escapeshellarg them ourselves
        if (is_array($switches)) {
            $switches = $this->prepareArrayArgs($switches);
        }

        // Sometimes it is necessary to call a program with sudo
        $attempt_sudo = array_key_exists('sudo_apps', self::$settings) && in_array($name, self::$settings['sudo_apps']);

        // Have we gotten it before?
        if (array_key_exists($name.$switches, $this->cliCache)) {
            return $this->cliCache[$name.$switches];
        }

        // Try finding the exec
        foreach ($this->searchPaths as $path) {

            // Found it; run it
            if (is_file($path.$name) && is_executable($path.$name)) {

                // Complete command, path switches and all
                $command = "$path$name $switches";

                // Sudoing?
                $command = $attempt_sudo ? Common::locateActualPath(Common::arrayAppendString($this->searchPaths, 'sudo', '%2s%1s')).' '.$command : $command;

                // Result of command
                $result = `$command`;

                // Increment call count
                ++self::$callCount;

                // Cache that
                $this->cliCache[$name.$switches] = $result;

                // Give result
                return $result;
            }
        }

        // Never got it
        throw new Exception('Exec `'.$name.'\' not found');
    }

    private function prepareArrayArgs($arr){
        return implode(' ', array_map('escapeshellarg', $arr));
    }
}
