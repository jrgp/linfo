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

namespace Linfo\Parsers;

use \Linfo\Leveldict;


/*
 * Parser for the `system_profiler` macOS command, which yields
 * a giant tree of data in a nonstandard format, including using
 * uneven indention between the levels.
 *
 * Parse it into a Leveldict for easy lookups and insertions.
 */
class MacSystemProfiler {
  private $lines, $leveldict;

  public function __construct($lines) {
    $this->lines = $lines;
    $this->leveldict = new Leveldict;
  }
  
  public function parse() {
    $this->leveldict->clear();
    $path = [];
    $lastindent = -1;
    $levels = [];

    foreach ($this->lines as $n => $line) {
      $line = rtrim($line);
      if ($line == '') {
        continue;
      }
      if (!preg_match('/^(\s*)([^:]+): ?([^$]+)?$/', $line, $m)) {
        continue;
      }

      $indent = $m[1];
      $key = $m[2];
      if (isset($m[3])) {
        $value = $m[3];
      } else {
        $value = null;
      }

      $indent_len = strlen($indent);
      if ($value === null) {
        if ($indent_len > $lastindent) {
          $levels[$indent_len] = count($path);
          $path[] = $key;
        } elseif ($indent_len < $lastindent) {
          $path = array_slice($path, 0, $levels[$indent_len]);
          $path[] = $key;
        }
      } else {
        $this->leveldict->set(array_merge($path, [$key]), $value);
      }
      $lastindent = $indent_len;
    }

    return $this->leveldict;
  }
}
