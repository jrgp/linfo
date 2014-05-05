<?php

/*
 * This file is part of Linfo (c) 2010-2015 Joseph Gillotti.
 * 
 * Linfo is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Linfo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Linfo.  If not, see <http://www.gnu.org/licenses/>.
 * 
*/

// Load libs
require_once __DIR__.'/init.php';

// Begin
$linfo = new Linfo;
$linfo->output();

// Developers:
// if you include init.php as above and instantiate a $linfo
// object, you can get an associative array of all of the 
// system info with $linfo->getInfo();
