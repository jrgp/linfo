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

namespace Linfo\OS;

use Linfo\Exceptions\FatalException;

abstract class OS
{
    public function __call($name, $args)
    {
        throw new FatalException('Method '.$name.' not present.');
    }

    /**
     * Perform optional initialization. Defined by child classes.
     *
     */
    public function init()
    {
    }


    /**
     * getAccessedIP
     *
     * @return string SERVER_ADDR or LOCAL_ADDR key in $_SERVER superglobal or Unknown
     */
    public function getAccessedIP()
    {
        return isset($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] ? $_SERVER['SERVER_ADDR'] : (isset($_SERVER['LOCAL_ADDR']) && $_SERVER['LOCAL_ADDR'] ? $_SERVER['LOCAL_ADDR'] : 'Unknown');
    }

    /**
     * getWebService
     *
     * @return string SERVER_SOFTWARE key in $_SERVER superglobal or Unknown
     */
    public function getWebService()
    {
        return isset($_SERVER['SERVER_SOFTWARE']) && $_SERVER['SERVER_SOFTWARE'] ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
    }

    /**
     * getPhpVersion
     *
     * @return string the version of php
     */
    public function getPhpVersion()
    {
        return phpversion();
    }

    /**
     * getCPUArchitecture
     *
     * @return string the arch and bits
     */
    public function getCPUArchitecture()
    {
        return php_uname('m');
    }

    /**
     * getKernel
     *
     * @return string the OS kernel. A few OS classes override this.
     */
    public function getKernel()
    {
        return php_uname('r');
    }

    /**
     * getHostName
     *
     * @return string the OS' hostname A few OS classes override this.
     */
    public function getHostName()
    {

        // Take advantage of that function again
        return php_uname('n');
    }

    /**
     * getOS
     *
     * @return string the OS' name.
     */
    public function getOS()
    {
        $parts = explode('\\', get_class($this));
        return array_pop($parts);
    }
}
