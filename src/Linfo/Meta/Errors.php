<?php

/**
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Linfo\Meta;

/**
 * Use this class for all error handling.
 */
class Errors
{
    /**
     * Store singleton instance here.
     * 
     * @var object
     * @static
     */
    protected static $_fledging;

    /**
     * Singleton. Get singleton instance.
     * 
     * @param array $settings linfo settings
     *
     * @return object LinfoError instance
     */
    public static function Singleton($settings = null)
    {
        $c = __CLASS__;
        if (!isset(self::$_fledging)) {
            self::$_fledging = new $c($settings);
        }

        return self::$_fledging;
    }

    /**
     * Store error messages here.
     *
     * @var array
     */
    private $_errors = array();

    /**
     * Add an error message.
     *
     * @param string $whence  name of error message source
     * @param string $message error message text
     */
    public function add($whence, $message)
    {
        $this->_errors[] = array($whence, $message);
    }

    /**
     * Get all error messages.
     *
     * @return array of errors
     */
    public function show()
    {
        return $this->_errors;
    }

    /**
     * How many are there?
     *
     * @return int number of errors
     */
    public function num()
    {
        return count($this->_errors);
    }

    /**
     * Wipe out singleton instance. Used mainly for unit tests.
     *
     * @static
     */
    public static function clear()
    {
        self::$_fledging = null;
    }
}
