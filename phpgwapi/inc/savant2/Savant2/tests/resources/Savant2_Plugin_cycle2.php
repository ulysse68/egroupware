<?php

/**
* 
* Example plugin for unit testing.
*
* @version $Id: Savant2_Plugin_cycle2.php 18360 2005-05-26 19:38:09Z mipmip $
*
*/

require_once 'Savant2/Plugin.php';

class Savant2_Plugin_cycle extends Savant2_Plugin {
	function plugin()
	{
		return "REPLACES DEFAULT CYCLE";
	}
}
?>