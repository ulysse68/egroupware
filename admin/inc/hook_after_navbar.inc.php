<?php
/**************************************************************************\
* eGroupWare - Admin                                                       *
* http://www.egroupware.org                                                *
* This application written by Miles Lott <milos@groupwhere.org>            *
* 04/27/2005	Fixed by Olivier TITECA-BEAUPORT <oliviert@maphilo.com>	   *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id: hook_after_navbar.inc.php 23338 2007-01-23 23:35:12Z mipmip $ */

/* Check currentapp and API upgrade status */
//if$GLOBALS['egw_info']['user']['preferences']['common']);
//=
//itemplate_set

if( $GLOBALS['egw_info']['user']['preferences']['common']['show_general_menu'] == 'sidebox' &&
    $GLOBALS['egw_info']['flags']['currentapp'] != 'home' &&
	$GLOBALS['egw_info']['flags']['currentapp'] != 'welcome' &&
	(isset($GLOBALS['egw_info']['server']['checkappversions']) &&
	$GLOBALS['egw_info']['server']['checkappversions']))
{
	if((isset($GLOBALS['egw_info']['user']['apps']['admin']) &&
		$GLOBALS['egw_info']['user']['apps']['admin']) ||
		$GLOBALS['egw_info']['server']['checkappversions'] == 'All')
	{
		$_returnhtml = array();
		$app_name = $GLOBALS['egw_info']['flags']['currentapp'];
		$GLOBALS['egw']->db->query("SELECT app_name,app_version FROM egw_applications WHERE app_name='$app_name' OR app_name='phpgwapi'",__LINE__,__FILE__);
		while($GLOBALS['egw']->db->next_record())
		{
			$_db_version  = $GLOBALS['egw']->db->f('app_version');
			$app_name     = $GLOBALS['egw']->db->f('app_name');
			$_versionfile = $GLOBALS['egw']->common->get_app_dir($app_name) . '/setup/setup.inc.php';
			if(file_exists($_versionfile))
			{
				include($_versionfile);
				$_file_version = $setup_info[$app_name]['version'];
				unset($setup_info);

				if(amorethanb($_file_version, $_db_version))
				{
					if($app_name == 'phpgwapi' )
					{
						$_returnhtml[$app_name] = lang('The API requires an upgrade');
					}
					else
					{
						$_returnhtml[$app_name] = lang('This application requires an upgrade') . ": \n <br />" . lang('Please run setup to become current') . '.' . "\n";
					}
				}
				else
				{
					if($app_name == 'phpgwapi' )
					{
						$_returnhtml[$app_name] = lang('The API is current');
					}
					else
					{
						$_returnhtml[$app_name] = lang('This application is current') . "\n";
					}
				}
				unset($_file_version);
			}
			else
			{
				// if setup.inc.php do not exist for the app, we assume that the app is current
				if($app_name == 'phpgwapi' )
				{
					$_returnhtml[$app_name] = lang('The API is current');
				}
				else
				{
					$_returnhtml[$app_name] = lang('This application is current') . "\n";
				}
			}
			unset($_db_version);
			unset($_versionfile);
		}
		echo '<p style="text-align: center;">'.implode('<br />',$_returnhtml)."</p>\n";

		unset($_returnhtml);
		unset($_html);
	}
}
