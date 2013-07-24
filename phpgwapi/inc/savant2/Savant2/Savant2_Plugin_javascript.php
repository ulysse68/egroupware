<?php

/**
* Base plugin class.
*/
require_once 'Savant2/Plugin.php';

/**
* 
* Output a <script></script> link to a JavaScript file.
* 
* $Id: Savant2_Plugin_javascript.php 18360 2005-05-26 19:38:09Z mipmip $
* 
* @author Paul M. Jones <pmjones@ciaweb.net>
* 
* @package Savant2
* 
* @license http://www.gnu.org/copyleft/lesser.html LGPL
* 
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as
* published by the Free Software Foundation; either version 2.1 of the
* License, or (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
* Lesser General Public License for more details.
* 
*/

class Savant2_Plugin_javascript extends Savant2_Plugin {

	/**
	* 
	* Output a <script></script> link to a JavaScript file.
	* 
	* @access public
	* 
	* @param string $href The HREF leading to the JavaScript source
	* file.
	* 
	* @return string
	* 
	*/
	
	function plugin($href)
	{
		return '<script language="javascript" type="text/javascript" src="' .
			htmlspecialchars($href) . '"></script>';
	}

}
?>