<?php
	/**************************************************************************\
	* eGroupWare - Administration                                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: class.boaclmanager.inc.php 19420 2005-10-14 17:03:16Z ralfbecker $ */

	class boaclmanager
	{
		var $ui;
		var $so;
		var $public_functions = array(
			'submit' => True
		);

		function boaclmanager()
		{
			//$this->so =& CreateObject('admin.soaclmanager');
			$this->ui =& CreateObject('admin.uiaclmanager');
		}

		function submit()
		{
			if ($GLOBALS['cancel'])
			{
				$this->ui->list_apps();
				return False;
			}

			$location = base64_decode($GLOBALS['location']);

			$total_rights = 0;
			while (is_array($GLOBALS['acl_rights']) && list(,$rights) = each($GLOBALS['acl_rights']))
			{
				$total_rights += $rights;
			}

			$GLOBALS['egw']->acl->add_repository($GLOBALS['acl_app'], $location, $GLOBALS['account_id'], $total_rights);

			$this->ui->list_apps();
		}

	}
