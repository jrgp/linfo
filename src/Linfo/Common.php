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

namespace Linfo;

use Linfo\Parsers\IO;

class Common
{
    protected static $settings = [],
        $lang = [];

    public static $io;

    public static function config(Linfo $linfo, IO $io)
    {
        self::$settings = $linfo->getSettings();
        self::$lang = $linfo->getLang();
        self::$io = $io;
    }

    public static function unconfig()
    {
        self::$settings = [];
        self::$lang = [];
    }

    // Certain files, specifcally the pci/usb ids files, vary in location from
    // linux distro to linux distro. This function, when passed an array of
    // possible file location, picks the first it finds and returns it. When
    // none are found, it returns false
    public static function locateActualPath($paths)
    {
        foreach ((array) $paths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return false;
    }

    // Append a string to the end of each element in a 2d array
    public static function arrayAppendString($array, $string = '', $format = '%1s%2s')
    {

        // Get to it
        foreach ($array as $k => $v) {
            $array[$k] = is_string($v) ? sprintf($format, $v, $string) : $v;
        }

        // Give
        return $array;
    }

    // Get a file who's contents should just be an int. Returns zero on failure.
    public static function getIntFromFile($file)
    {
        return (int) self::getContents($file, 0);
    }

    // Convert bytes to stuff like KB MB GB TB etc
    public static function byteConvert($size, $precision = 2, $notation = null, $bits = false)
    {

        // Sanity check
        if (!is_numeric($size)) {
            return '?';
        }

        // Get the notation
        if ($notation === null) {
            $notation = self::$settings['byte_notation'] == 1000 ? 1000 : 1024;
        }

        // Fixes large disk size overflow issue
        // Found at http://www.php.net/manual/en/function.disk-free-space.php#81207
        if ($bits) {
            $types = array('b', 'Kb', 'Mb', 'Gb', 'Tb');
            $types_i = array('b', 'Kib', 'Mib', 'Gib', 'Tib');
        } else {
            $types = array('B', 'KB', 'MB', 'GB', 'TB');
            $types_i = array('B', 'KiB', 'MiB', 'GiB', 'TiB');
        }

        for ($i = 0; $size >= $notation && $i < (count($types) - 1); $size /= $notation, $i++);

        return(round($size, $precision).' '.($notation == 1000 ? $types[$i] : $types_i[$i]));
    }

    // Like above, but for seconds
    public static function secondsConvert($uptime)
    {

        // Method here heavily based on freebsd's uptime source
        $uptime += $uptime > 60 ? 30 : 0;
        $years = floor($uptime / 31556926);
        $uptime %= 31556926;
        $days = floor($uptime / 86400);
        $uptime %= 86400;
        $hours = floor($uptime / 3600);
        $uptime %= 3600;
        $minutes = floor($uptime / 60);
        $seconds = floor($uptime % 60);

        // Send out formatted string
        $return = [];

        if ($years > 0) {
            $return[] = $years.' '.($years > 1 ? self::$lang['years'] : substr(self::$lang['years'], 0, strlen(self::$lang['years']) - 1));
        }

        if ($days > 0) {
            $return[] = $days.' '.self::$lang['days'];
        }

        if ($hours > 0) {
            $return[] = $hours.' '.self::$lang['hours'];
        }

        if ($minutes > 0) {
            $return[] = $minutes.' '.self::$lang['minutes'];
        }

        if ($seconds > 0) {
            $return[] = $seconds.' '.self::$lang['seconds'];
        }

        return implode(', ', $return);
    }

    // Get a file's contents, or default to second param
    public static function getContents($file, $default = '')
    {
        return self::$io->getContents($file, $default);
    }

    // Like above, but in lines instead of a big string
    public static function getLines($file)
    {
        return self::$io->getLines($file);
    }

    // Get a variable from a file. Include it in this function to avoid
    // clobbering the main namespace
    public static function getVarFromFile($file, $variable)
    {

        // Let's not waste our time, now
        if (!is_file($file)) {
            return false;
        }

        require $file;

        // Double dollar sign means treat variable contents
        // as the name of a variable.
        if (isset($$variable)) {
            return $$variable;
        }

        return false;
    }

    // Prevent silly conditionals like if (in_[] || in_[] || in_[])
    // Poor man's python's any() on a list comprehension kinda
    public static function anyInArray($needles, $haystack)
    {
        if (!is_array($needles) || !is_array($haystack)) {
            return false;
        }

        return count(array_intersect($needles, $haystack)) > 0;
    }

    // Easily determine if a string contains any of the snippets
    public static function anyInString($string, $snippets)
    {
	foreach ($snippets as $snippet) {
	    if (strpos($string, $snippet) !== false) {
                 return true;
	    }
	}
	return false;
    }

    // Given a string that should contain a float or a decimal, get back either
    public static function strToInt($str)
    {
        if (!is_string($str))
            return $str;
        if (strpos($str, '.') !== false)
            return (float) $str;
        return (int) $str;
    }
}
