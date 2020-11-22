<?php

/* Linfo
 *
 * Copyright (c) 2020 Joe Gillotti
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

/*
 * A class representing a dictionary that allows setting
 * and retrieving values using an array of keys, with each element
 * being a sub level in the dict.
 *
 * See this class's unit test for more information.
 */
class Leveldict {
    private $dict;

    public function __construct() {
        $this->clear();
    }

    public function set($parts, $var) {
        $key = array_pop($parts);
        $mydict = &$this->dict;
        foreach ($parts as $part) {
            if (!isset($mydict[$part])) {
                $mydict[$part] = [];
            }
            $mydict = &$mydict[$part];
        }
        $mydict[$key] = $var;
    }

    public function get($parts){
        $value = $this->dict;
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        return $value;
    }

    public function clear(){
        $this->dict = [];
    }

    public function todict() {
        return $this->dict;
    }
}

