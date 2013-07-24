<?php 
	/**************************************************************************\
	* eGroupWare - holidaycalc                                                 *
	* http://www.egroupware.org                                                *
	* Based on Yoshihiro Kamimura <your@itheart.com>                           *
	*          http://www.itheart.com                                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: class.holidaycalc.inc.php 22392 2006-09-03 05:12:42Z ralfbecker $ */

	if (empty($GLOBALS['egw_info']['user']['preferences']['common']['country']) ||
		strlen($GLOBALS['egw_info']['user']['preferences']['common']['country']) > 2)
	{
		$rule = 'US';
	}
	else
	{
		$rule = $GLOBALS['egw_info']['user']['preferences']['common']['country'];
	}

	$calc_include = EGW_INCLUDE_ROOT.'/calendar/inc/class.holidaycalc_'.$rule.'.inc.php';
	if(@file_exists($calc_include))
	{
		include($calc_include);
	}
	else
	{
		include(EGW_INCLUDE_ROOT.'/calendar/inc/class.holidaycalc_US.inc.php');
	}
