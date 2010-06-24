<?php

/*
 * This file is part of Linfo (c) 2010 Joseph Gillotti.
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


defined('IN_INFO') or exit;

/*
 * Incomplete FreeBSD info class
 * Unknown if this will finish since the goal of Linfo is 
 * to not call external functions from PHP
 */

class FreeBSDInfo {
	public function getOS() {}
	public function getKernel(){}
	public function getRam(){}
	public function getHD(){}
	public function getTemps(){}
	public function getMounts(){}
	public function getDevs(){}
	public function getHealth(){}
}
