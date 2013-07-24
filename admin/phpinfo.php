<?php
	/**************************************************************************\
	* eGroupWare - administration                                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: phpinfo.php 19420 2005-10-14 17:03:16Z ralfbecker $ */

	$GLOBALS['egw_info']['flags'] = array(
		'noheader'   => True,
		'nonavbar'   => True,
		'currentapp' => 'admin'
	);
	include('../header.inc.php');

	if ($GLOBALS['egw']->acl->check('info_access',1,'admin'))
	{
		$GLOBALS['egw']->redirect_link('/index.php');
	}

// Throw a little notice out if PHPaccelerator is enabled.
	if($GLOBALS['_PHPA']['ENABLED'])
	{
		echo 'PHPaccelerator enabled:</br>'."\n";
		echo 'PHPaccelerator Version: '.$GLOBALS['_PHPA']['VERSION'].'</br></p>'."\n";
	}

	phpinfo();
//	$GLOBALS['egw']->common->egw_footer();
?>
