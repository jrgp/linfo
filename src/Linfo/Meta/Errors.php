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
     * Store error messages here.
     *
     * @var array
     */
    private static $errors = array();

    /**
     * Add an error message.
     *
     * @static
     * @param string $whence  name of error message source
     * @param string $message error message text
     */
    public static function add($whence, $message)
    {
        self::$errors[] = array($whence, $message);
    }

    /**
     * Get all error messages.
     *
     * @static
     * @return array of errors
     */
    public static function show()
    {
        return self::$errors;
    }

    /**
     * How many are there?
     *
     * @static
     * @return int number of errors
     */
    public static function num()
    {
        return count(self::$errors);
    }

    /**
     * Used mainly for unit tests.
     *
     * @static
     */
    public static function clear()
    {
        self::$errors = array();
    }
}
