<?php
	/**************************************************************************\
	* eGroupWare SiteMgr - Web Content Management                              *
	* http://www.egroupware.org                                                *
	* Written by Pim Snel <pim@lingewoud.nl>                                   *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: hook_sidebox_menu.inc.php 33317 2010-12-06 17:36:43Z ralfbecker $ */

{

 /*
	This hookfile is for generating an app-specific side menu used in the idots
	template set.

	$menu_title speaks for itself
	$file is the array with link to app functions

	display_sidebox can be called as much as you like
 */

	if (!isset($GLOBALS['Common_BO']) || !is_object($GLOBALS['Common_BO']))
	{
		$GLOBALS['Common_BO'] = CreateObject('sitemgr.Common_BO');
		$GLOBALS['Common_BO']->sites->set_currentsite(false,'Administration');
	}
	$menu_title = lang('Website') . ' ' . $GLOBALS['Common_BO']->sites->current_site['site_name'];
	$file = $GLOBALS['Common_BO']->get_sitemenu();
	display_sidebox($appname,$menu_title,$file);
	$file = $GLOBALS['Common_BO']->get_othermenu();
	if ($file)
	{
		$menu_title = lang('Other websites');
		display_sidebox($appname,$menu_title,$file);
	}
	$menu_title = lang('Preferences');
	$file = Array(
		'Preferences' => $GLOBALS['egw']->link('/index.php',array('menuaction'=>'preferences.uisettings.index','appname'=>'sitemgr')),
	);
	display_sidebox($appname,$menu_title,$file);
}
