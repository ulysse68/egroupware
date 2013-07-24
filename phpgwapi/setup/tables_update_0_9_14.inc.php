<?php
	/**************************************************************************\
	* phpGroupWare - Setup                                                     *
	* http://www.phpgroupware.org                                              *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id: tables_update_0_9_14.inc.php 19424 2005-10-15 21:52:37Z omgs $

	/* This is since the last release */
	$test[] = '0.9.12';
	function phpgwapi_upgrade0_9_12()
	{
		global $setup_info,$phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.13.001';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.001';
	function phpgwapi_upgrade0_9_13_001()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_categories','cat_access', array('type' => 'varchar', 'precision' => 7));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.002';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.002';
	function phpgwapi_upgrade0_9_13_002()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_accounts','account_file_space', array ('type' => 'varchar', 'precision' => 25));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.003';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.003';
	function phpgwapi_upgrade0_9_13_003()
	{
		global $setup_info,$phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_access_log','sessionid',array('type' => 'char', 'precision' => 32));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.004';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.004';
	function phpgwapi_upgrade0_9_13_004()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_access_log','account_id',array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => False));

		$phpgw_setup->setup_account_object();

		$phpgw_setup->oProc->query("select * from phpgw_access_log");
		while($phpgw_setup->oProc->next_record())
		{
			$lid         = explode('@',$phpgw_setup->oProc->f('loginid'));
			$account_lid = $lid[0];
			$account_id = $accounts->name2id($account_lid);

			$phpgw_setup->db->query("update phpgw_access_log set account_id='" . $account_id
				. "' where sessionid='" . $phpgw_setup->oProc->f('sessionid') . "'");
		}

		$setup_info['phpgwapi']['currentver'] = '0.9.13.005';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.005';
	function phpgwapi_upgrade0_9_13_005()
	{
		global $setup_info, $phpgw_setup;

		$newtbldef = array(
			'fd' => array(
				'account_id' => array('type' => 'auto', 'nullable' => false),
				'account_lid' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
				'account_pwd' => array('type' => 'varchar', 'precision' => 32, 'nullable' => false),
				'account_firstname' => array('type' => 'varchar', 'precision' => 50),
				'account_lastname' => array('type' => 'varchar', 'precision' => 50),
				'account_permissions' => array('type' => 'text'),
				'account_groups' => array('type' => 'varchar', 'precision' => 30),
				'account_lastlogin' => array('type' => 'int', 'precision' => 4),
				'account_lastloginfrom' => array('type' => 'varchar', 'precision' => 255),
				'account_lastpwd_change' => array('type' => 'int', 'precision' => 4),
				'account_status' => array('type' => 'char', 'precision' => 1, 'nullable' => false, 'default' => 'A'),
				'account_expires' => array('type' => 'int', 'precision' => 4),
				'account_type' => array('type' => 'char', 'precision' => 1, 'nullable' => true)
			),
			'pk' => array('account_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('account_lid')
		);

		$phpgw_setup->oProc->DropColumn('phpgw_accounts',$newtbldef,'account_file_space');

		$setup_info['phpgwapi']['currentver'] = '0.9.13.006';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.006';
	function phpgwapi_upgrade0_9_13_006()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_log', array(
				'fd' => array(
					'log_id' 	=> array('type' => 'auto',      'precision' => 4,  'nullable' => False),
					'log_date' 	=> array('type' => 'timestamp', 'nullable' => False),
					'log_user' 	=> array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'log_app' 	=> array('type' => 'varchar',   'precision' => 50, 'nullable' => False),
					'log_severity' 	=> array('type' => 'char',  'precision' => 1,  'nullable' => False)
				),
				'pk' => array('log_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$phpgw_setup->oProc->CreateTable(
			'phpgw_log_msg', array(
				'fd' => array(
					'log_msg_log_id' 	=> array('type' => 'auto',      'precision' => 4,  'nullable' => False),
					'log_msg_seq_no'	=> array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'log_msg_date'		=> array('type' => 'timestamp',	'nullable' => False),
					'log_msg_tx_fid'	=> array('type' => 'varchar',   'precision' => 4,  'nullable' => True),
					'log_msg_tx_id'		=> array('type' => 'varchar',   'precision' => 4,  'nullable' => True),
					'log_msg_severity'	=> array('type' => 'char',      'precision' => 1,  'nullable' => False),
					'log_msg_code' 		=> array('type' => 'varchar',   'precision' => 30, 'nullable' => False),
					'log_msg_msg' 		=> array('type' => 'text', 'nullable' => False),
					'log_msg_parms'		=> array('type' => 'text', 'nullable' => False)
			 	),
				'pk' => array('log_msg_log_id', 'log_msg_seq_no'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.13.007';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.007';
	function phpgwapi_upgrade0_9_13_007()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_log_msg','log_msg_log_id',array('type' => 'int', 'precision' => 4, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.008';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.008';
	function phpgwapi_upgrade0_9_13_008()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AddColumn('phpgw_log_msg','log_msg_file',array('type' => 'varchar', 'precision' => 255, 'nullable'=> False));
		$phpgw_setup->oProc->AddColumn('phpgw_log_msg','log_msg_line',array('type' => 'int', 'precision' => 4, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.009';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.009';
	function phpgwapi_upgrade0_9_13_009()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_interserv', array(
				'fd' => array(
					'server_id'   => array('type' => 'auto', 'nullable' => False),
					'server_name' => array('type' => 'varchar', 'precision' => 64,  'nullable' => True),
					'server_host' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'server_url'  => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'trust_level' => array('type' => 'int',     'precision' => 4),
					'trust_rel'   => array('type' => 'int',     'precision' => 4),
					'username'    => array('type' => 'varchar', 'precision' => 64,  'nullable' => True),
					'password'    => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'admin_name'  => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'admin_email' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'server_mode' => array('type' => 'varchar', 'precision' => 16,  'nullable' => False, 'default' => 'xmlrpc'),
					'server_security' => array('type' => 'varchar', 'precision' => 16,'nullable' => True)
				),
				'pk' => array('server_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.13.010';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.010';
	function phpgwapi_upgrade0_9_13_010()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_sessions','session_lid',array('type' => 'varchar', 'precision' => 255, 'nullable'=> False));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.011';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.011';
	function phpgwapi_upgrade0_9_13_011()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_vfs', array(
				'fd' => array(
					'file_id' => array('type' => 'auto','nullable' => False),
					'owner_id' => array('type' => 'int', 'precision' => 4,'nullable' => False),
					'createdby_id' => array('type' => 'int', 'precision' => 4,'nullable' => True),
					'modifiedby_id' => array('type' => 'int', 'precision' => 4,'nullable' => True),
					'created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
					'modified' => array('type' => 'date','nullable' => True),
					'size' => array('type' => 'int', 'precision' => 4,'nullable' => True),
					'mime_type' => array('type' => 'varchar', 'precision' => 150,'nullable' => True),
					'deleteable' => array('type' => 'char', 'precision' => 1,'nullable' => True,'default' => 'Y'),
					'comment' => array('type' => 'text','nullable' => True),
					'app' => array('type' => 'varchar', 'precision' => 25,'nullable' => True),
					'directory' => array('type' => 'text','nullable' => True),
					'name' => array('type' => 'text','nullable' => False),
					'link_directory' => array('type' => 'text','nullable' => True),
					'link_name' => array('type' => 'text','nullable' => True),
					'version' => array('type' => 'varchar', 'precision' => 30,'nullable' => False,'default' => '0.0.0.0')
				),
				'pk' => array('file_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);
		$setup_info['phpgwapi']['currentver'] = '0.9.13.012';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.012';
	function phpgwapi_upgrade0_9_13_012()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('phpgw_applications', 'app_tables', array('type' => 'text'));

		$setup_info['phpgwapi']['currentver'] = '0.9.13.013';
		return $setup_info['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.013';
	function phpgwapi_upgrade0_9_13_013()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_history_log', array(
				'fd' => array(
					'history_id'        => array('type' => 'auto',      'precision' => 4,  'nullable' => False),
					'history_record_id' => array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'history_appname'   => array('type' => 'varchar',   'precision' => 64, 'nullable' => False),
					'history_owner'     => array('type' => 'int',       'precision' => 4,  'nullable' => False),
					'history_status'    => array('type' => 'char',      'precision' => 2,  'nullable' => False),
					'history_new_value' => array('type' => 'text',      'nullable' => False),
					'history_timestamp' => array('type' => 'timestamp', 'nullable' => False, 'default' => 'current_timestamp')

				),
				'pk' => array('history_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.014';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.014';
	function phpgwapi_upgrade0_9_13_014()
	{
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_applications SET app_order=100 WHERE app_order IS NULL");
		$GLOBALS['phpgw_setup']->oProc->query("SELECT * FROM phpgw_applications");
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$app_name[]	= $GLOBALS['phpgw_setup']->oProc->f('app_name');
			$app_title[]	= $GLOBALS['phpgw_setup']->oProc->f('app_title');
			$app_enabled[]	= $GLOBALS['phpgw_setup']->oProc->f('app_enabled');
			$app_order[]	= $GLOBALS['phpgw_setup']->oProc->f('app_order');
			$app_tables[]	= $GLOBALS['phpgw_setup']->oProc->f('app_tables');
			$app_version[]	= $GLOBALS['phpgw_setup']->oProc->f('app_version');
		}

		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_applications');

		$GLOBALS['phpgw_setup']->oProc->CreateTable(
			'phpgw_applications', array(
				'fd' => array(
					'app_id' => array('type' => 'auto', 'precision' => 4, 'nullable' => false),
					'app_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
					'app_title' => array('type' => 'varchar', 'precision' => 50),
					'app_enabled' => array('type' => 'int', 'precision' => 4),
					'app_order' => array('type' => 'int', 'precision' => 4),
					'app_tables' => array('type' => 'varchar', 'precision' => 255),
					'app_version' => array('type' => 'varchar', 'precision' => 20, 'nullable' => false, 'default' => '0.0')
				),
				'pk' => array('app_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('app_name')
			)
		);

		$rec_count = count($app_name);
		for($rec_loop=0;$rec_loop<$rec_count;$rec_loop++)
		{
			$GLOBALS['phpgw_setup']->oProc->query('INSERT INTO phpgw_applications(app_id,app_name,app_title,app_enabled,app_order,app_tables,app_version) '
				. 'VALUES('.($rec_loop + 1).",'".$app_name[$rec_loop]."','".$app_title[$rec_loop]."',".$app_enabled[$rec_loop].','.$app_order[$rec_loop].",'".$app_tables[$rec_loop]."','".$app_version[$rec_loop]."')");
		}

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.015';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.015';
	function phpgwapi_upgrade0_9_13_015()
	{
		/* Skip this for mysql 3.22.X in php4 at least */
		if(phpversion() >= '4.0.5' && @$GLOBALS['phpgw_setup']->db->Type == 'mysql')
		{
			$_ver_str = @mysql_get_server_info();
			$_ver_arr = explode(".",$_ver_str);
			$_ver = $_ver_arr[1];
			if((int)$_ver < 23)
			{
				$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.016';
				return $GLOBALS['setup_info']['phpgwapi']['currentver'];
			}
		}

		$GLOBALS['phpgw_setup']->oProc->AlterColumn(
			'lang',
			'message_id',
			array(
				'type' => 'varchar',
				'precision' => 255,
				'nullable' => false,
				'default' => ''
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.016';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.016';
	function phpgwapi_upgrade0_9_13_016()
	{
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','acl_manager','hook_acl_manager.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','add_def_pref','hook_add_def_pref.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','after_navbar','hook_after_navbar.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','deleteaccount','hook_deleteaccount.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','config','hook_config.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','manual','hook_manual.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('admin','view_user','hook_view_user.inc.php')");

		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','admin_deleteaccount','hook_admin_deleteaccount.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','config','hook_config.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','manual','hook_manual.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','preferences','hook_preferences.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('preferences','settings','hook_settings.inc.php')");

		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','about','hook_about.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','add_def_pref','hook_add_def_pref.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','config_validate','hook_config_validate.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','deleteaccount','hook_deleteaccount.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','home','hook_home.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','manual','hook_manual.inc.php')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_hooks (hook_appname,hook_location,hook_filename) VALUES ('addressbook','notifywindow','hook_notifywindow.inc.php')");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.017';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.017';
	function phpgwapi_upgrade0_9_13_017()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_history_log','history_old_value',array('type' => 'text','nullable' => False));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.13.018';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.13.018';
	function phpgwapi_upgrade0_9_13_018()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.000';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.000';
	function phpgwapi_upgrade0_9_14_000()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.001';
	function phpgwapi_upgrade0_9_14_001()
	{
		// Fix bug from update script in 0.9.11.004/5:
		// column config_app was added to table phpgw_config (which places it as last column),
		// but in the tables_current.inc.php it was added as first column.
		// When setup / schemaproc wants to do the AlterColum it recreates the table for pgSql,
		// as pgSql could not change the column-type. This recreation is can not be based on 
		// tables_current, but on running tables_baseline throught all update-scripts.
		// Which gives at the end two different versions of the table on new or updated installs.
		// I fix it now in the (wrong) order of the tables_current, as some apps might depend on!

		$confs = array();
		$GLOBALS['phpgw_setup']->oProc->query("SELECT * FROM phpgw_config");
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$confs[] = array(
				'config_app' => $GLOBALS['phpgw_setup']->oProc->f('config_app'),
				'config_name' => $GLOBALS['phpgw_setup']->oProc->f('config_name'),
				'config_value' => $GLOBALS['phpgw_setup']->oProc->f('config_value')
			);
		}
		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_config');

		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_config',array(
			'fd' => array(
				'config_app' => array('type' => 'varchar', 'precision' => 50),
				'config_name' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
				'config_value' => array('type' => 'text')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('config_name')
		));

		foreach($confs as $conf)
		{
			$GLOBALS['phpgw_setup']->oProc->query(
				"INSERT INTO phpgw_config (config_app,config_name,config_value) VALUES ('".
				$conf['config_app']."','".$conf['config_name']."','".$conf['config_value']."')");
		}

		$GLOBALS['phpgw_setup']->oProc->query("UPDATE languages SET available='Yes' WHERE lang_id='cs'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.002';
	function phpgwapi_upgrade0_9_14_002()
	{
		// 0.9.14.5xx are the development-versions of the 0.9.16 release (based on the 0.9.14 api)
		// as 0.9.15.xxx are already used in HEAD
		
		// this is the 0.9.15.003 update, needed for the new filemanager and vfs-classes in the api
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_vfs','content', array ('type' => 'text', 'nullable' => True));

		// this is the 0.9.15.004 update, needed for the polish translations
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE languages set available='Yes' WHERE lang_id='pl'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.500';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.003';
	function phpgwapi_upgrade0_9_14_003()
	{
		// goes direct to 0.9.14.500
		return phpgwapi_upgrade0_9_14_002();
	}

	$test[] = '0.9.14.004';
	function phpgwapi_upgrade0_9_14_004()
	{
		// goes direct to 0.9.14.500
		return phpgwapi_upgrade0_9_14_002();
	}

	$test[] = '0.9.14.005';
	function phpgwapi_upgrade0_9_14_005()
	{
		// goes direct to 0.9.14.500
		return phpgwapi_upgrade0_9_14_002();
	}

	$test[] = '0.9.14.006';
	function phpgwapi_upgrade0_9_14_006()
	{
		// goes direct to 0.9.14.500
		return phpgwapi_upgrade0_9_14_002();
	}

	$test[] = '0.9.14.007';
	function phpgwapi_upgrade0_9_14_007()
	{
		// goes direct to 0.9.14.500
		return phpgwapi_upgrade0_9_14_002();
	}

	$test[] = '0.9.14.500';
	function phpgwapi_upgrade0_9_14_500()
	{
		// this is the 0.9.15.001 update
		$GLOBALS['phpgw_setup']->oProc->RenameTable('lang','phpgw_lang');
		$GLOBALS['phpgw_setup']->oProc->RenameTable('languages','phpgw_languages');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.501';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.501';
	function phpgwapi_upgrade0_9_14_501()
	{
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_async',array(
			'fd' => array(
				'id' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'next' => array('type' => 'int','precision' => '4','nullable' => False),
				'times' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'method' => array('type' => 'varchar','precision' => '80','nullable' => False),
				'data' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_applications',array(
			'fd' => array(
				'app_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'app_name' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'app_enabled' => array('type' => 'int','precision' => '4'),
				'app_order' => array('type' => 'int','precision' => '4'),
				'app_tables' => array('type' => 'text'),
				'app_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => '0.0')
			),
			'pk' => array('app_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array('app_name')
		),'app_title');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.502';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.502';
	function phpgwapi_upgrade0_9_14_502()
	{
		// because of all the trouble with sequences and indexes in the global namespace, 
		// we use an additional temp. table for postgres and not rename the existing one, but drop it.
		if ($GLOBALS['phpgw_setup']->oProc->sType == 'pgsql')	
		{
			$GLOBALS['phpgw_setup']->oProc->query("SELEcT * INTO TEMPORARY TABLE old_preferences FROM phpgw_preferences",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_preferences');
		}
		else
		{
			$GLOBALS['phpgw_setup']->oProc->RenameTable('phpgw_preferences','old_preferences');
		}
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_preferences',array(
			'fd' => array(
				'preference_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'preference_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'preference_value' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('preference_owner','preference_app'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));
		$db2 = $GLOBALS['phpgw_setup']->db;	// we need a 2. result-set
		$GLOBALS['phpgw_setup']->oProc->query("SELECT * FROM old_preferences");
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$owner = (int)$GLOBALS['phpgw_setup']->oProc->f('preference_owner');
			$prefs = unserialize($GLOBALS['phpgw_setup']->oProc->f('preference_value'));

			if (is_array($prefs))
			{
				foreach ($prefs as $app => $pref)
				{
					if (!empty($app) && count($pref))
					{
						$app = addslashes($app);
						$pref = serialize($pref);
						$db2->query("INSERT INTO phpgw_preferences".
							" (preference_owner,preference_app,preference_value)".
							" VALUES ($owner,'$app','$pref')");
					}
				}
			}
		}
		$GLOBALS['phpgw_setup']->oProc->DropTable('old_preferences');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.503';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.503';
	function phpgwapi_upgrade0_9_14_503()
	{
		// we create the column for postgres nullable, set all its values to 0 and set it NOT NULL
		if ($GLOBALS['phpgw_setup']->oProc->sType == 'pgsql')
		{
			$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_addressbook','last_mod',array(
				'type' => 'int',
				'precision' => '4',
			));
			$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_addressbook SET last_mod=0",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->oProc->query("ALTER TABLE phpgw_addressbook ALTER COLUMN last_mod SET NOT NULL",__LINE__,__FILE__);
		}
		else
		{
			$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_addressbook','last_mod',array(
				'type' => 'int',
				'precision' => '4',
				'nullable' => false,
			));
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.504';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.504';
	function phpgwapi_upgrade0_9_14_504()
	{
		// we create the column for postgres nullable, set all its values to 0 and set it NOT NULL
		if ($GLOBALS['phpgw_setup']->oProc->sType == 'pgsql')
		{
			$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_categories','last_mod',array(
				'type' => 'int',
				'precision' => '4',
			));
			$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_categories SET last_mod=0",__LINE__,__FILE__);
			$GLOBALS['phpgw_setup']->oProc->query("ALTER TABLE phpgw_categories ALTER COLUMN last_mod SET NOT NULL",__LINE__,__FILE__);
		}
		else
		{
			$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_categories','last_mod',array(
				'type' => 'int',
				'precision' => '4',
				'nullable' => false,
			));
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.505';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.505';
	function phpgwapi_upgrade0_9_14_505()
	{
		// postgres cant convert a column containing empty strings to int, updating them to '0' first
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_access_log SET lo='0' WHERE lo=''",__LINE__,__FILE__);
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_access_log','lo',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => True,
			'default' => '0'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.506';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.506';
	function phpgwapi_upgrade0_9_14_506()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_vfs','content',array(
			'type' => 'text',
			'nullable' => True
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.507';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.507';
	function phpgwapi_upgrade0_9_14_507()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_async','account_id',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => False,
			'default' => '0'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.508';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.14.508';
	function phpgwapi_upgrade0_9_14_508()
	{
		// the following if detects if we come from a phpGW version after the fork
		// (0.9.14.508 < currentversion < 0.9.99) and running only baseline-deltas
		if ($GLOBALS['phpgw_setup']->oProc->m_bDeltaOnly)
		{
			$currentver = explode('.',$GLOBALS['phpgw_setup']->process->currentversion);
			if ($currentver[0] == 0 && $currentver[1] == 9 && 
				($currentver[2] == 14 && $currentver[3] > 508 || 
				($currentver[2] > 14 && $currentver[2] < 99)))
			{
				// this is a phpGW update from a version after the fork
				$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.509';
				//echo "currentver=".print_r($currentver,true)." ==> following the phpGW update path ==> ".$GLOBALS['setup_info']['phpgwapi']['currentver']."<br>\n";
				return $GLOBALS['setup_info']['phpgwapi']['currentver'];
			}
		}					
		// update to 0.9.10pre3 droped the columns account_permissions and account_groups
		// unfortunally they are still in the tables_current of 0.9.14.508
		// so it depends on having a new or an updated install, if one have them or not
		// we now check if they are there and drop them if thats the case

		$GLOBALS['phpgw_setup']->oProc->m_oTranslator->_GetColumns($GLOBALS['phpgw_setup']->oProc,'phpgw_accounts',$columns);
		$columns = explode(',',$columns);
		if (in_array('account_permissions',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_accounts',array(
				'fd' => array(
					'account_id' => array('type' => 'auto','nullable' => False),
					'account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'account_pwd' => array('type' => 'varchar','precision' => '32','nullable' => False),
					'account_firstname' => array('type' => 'varchar','precision' => '50'),
					'account_lastname' => array('type' => 'varchar','precision' => '50'),
					'account_groups' => array('type' => 'varchar','precision' => '30'),
					'account_lastlogin' => array('type' => 'int','precision' => '4'),
					'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
					'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
					'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
					'account_expires' => array('type' => 'int','precision' => '4'),
					'account_type' => array('type' => 'char','precision' => '1','nullable' => True)
				),
				'pk' => array('account_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('account_lid')
			),'account_permissions');
		}
		if (in_array('account_groups',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_accounts',array(
				'fd' => array(
					'account_id' => array('type' => 'auto','nullable' => False),
					'account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'account_pwd' => array('type' => 'varchar','precision' => '32','nullable' => False),
					'account_firstname' => array('type' => 'varchar','precision' => '50'),
					'account_lastname' => array('type' => 'varchar','precision' => '50'),
					'account_lastlogin' => array('type' => 'int','precision' => '4'),
					'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
					'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
					'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
					'account_expires' => array('type' => 'int','precision' => '4'),
					'account_type' => array('type' => 'char','precision' => '1','nullable' => True)
				),
				'pk' => array('account_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('account_lid')
			),'account_groups');
		}

		// we add the person_id from the .16RC1, if its not already there
		if (!in_array('person_id',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_accounts','person_id',array(
				'type' => 'int',
				'precision' => '4',
				'nullable' => True
			));
		}
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_accounts','account_primary_group',array(
			'type' => 'int',
			'precision' => '4',
			'nullable' => False,
			'default' => '0'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.002';
	function phpgwapi_upgrade0_9_99_002()
	{
		// needed for the chinese(simplified) translations
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_languages SET lang_name='Chinese(simplified)',available='Yes' WHERE lang_id='zh'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.003';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	/*
	 * Updates from phpGroupWare after the fork
	 */

	$test[] = '0.9.14.509';
	function phpgwapi_upgrade0_9_14_509()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.510';
	}
		
	$test[] = '0.9.14.510';
	function phpgwapi_upgrade0_9_14_510()
	{
		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_log_msg');
		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_log');
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_log',array(
			'fd' => array(
				'log_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'log_date' => array('type' => 'timestamp','nullable' => False),
				'log_account_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'log_app' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'log_severity' => array('type' => 'char','precision' => '1','nullable' => False),
				'log_file' => array('type' => 'varchar','precision' => '255','nullable' => False, 'default' => ''),
				'log_line' => array('type' => 'int','precision' => '4','nullable' => False, 'default' => '0'),
				'log_msg' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('log_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.511';
	}
		
	$test[] = '0.9.14.511';
	function phpgwapi_upgrade0_9_14_511()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.512';
	}
		
	$test[] = '0.9.14.512';
	function phpgwapi_upgrade0_9_14_512()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.513';
	}
		
	$test[] = '0.9.14.513';
	function phpgwapi_upgrade0_9_14_513()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_accounts','account_quota',array('type' => 'int','precision' => '4','default' => -1,'nullable' => True));
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.514';
	}
	
	$test[] = '0.9.14.514';
	function phpgwapi_upgrade0_9_14_514()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.16.000';
	}
		
	$test[] = '0.9.16.000';
	function phpgwapi_upgrade0_9_16_000()
	{
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.16.001';
	}

	$test[] = '0.9.16.001';
	function phpgwapi_upgrade0_9_16_001()
	{
		foreach($GLOBALS['phpgw_setup']->db->table_names() as $tableinfo)
		{
			$tablenames[] = $tableinfo['table_name'];
		}
		// we need to redo the 0.9.14.510 update with the new phpgw_log table
		// we just drop and recreate the table, as it contains no important data
		$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_log');
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_log',array(
			'fd' => array(
				'log_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'log_date' => array('type' => 'timestamp','nullable' => False),
				'log_user' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_app' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'log_severity' => array('type' => 'char','precision' => '1','nullable' => False)
			),
			'pk' => array('log_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));
		if (in_array('phpgw_log_msg',$tablenames)) 
		{
			$GLOBALS['phpgw_setup']->oProc->DropTable('phpgw_log_msg');
		}
		$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_log_msg',array(
			'fd' => array(
				'log_msg_log_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_msg_seq_no' => array('type' => 'int','precision' => '4','nullable' => False),
				'log_msg_date' => array('type' => 'timestamp','nullable' => False),
				'log_msg_tx_fid' => array('type' => 'varchar','precision' => '4','nullable' => True),
				'log_msg_tx_id' => array('type' => 'varchar','precision' => '4','nullable' => True),
				'log_msg_severity' => array('type' => 'char','precision' => '1','nullable' => False),
				'log_msg_code' => array('type' => 'varchar','precision' => '30','nullable' => False),
				'log_msg_msg' => array('type' => 'text','nullable' => False),
				'log_msg_parms' => array('type' => 'text','nullable' => False),
				'log_msg_file' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'log_msg_line' => array('type' => 'int','precision' => '4','nullable' => False)
			),
			'pk' => array('log_msg_log_id','log_msg_seq_no'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));
		
		// now we need to drop phpgw_accounts.accounts_quota from the 0.9.14.513 update
		$GLOBALS['phpgw_setup']->oProc->m_oTranslator->_GetColumns($GLOBALS['phpgw_setup']->oProc,'phpgw_accounts',$columns);
		$columns = explode(',',$columns);
		if (in_array('account_quota',$columns))
		{
			$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_accounts',array(
				'fd' => array(
					'account_id' => array('type' => 'auto','nullable' => False),
					'account_lid' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'account_pwd' => array('type' => 'varchar','precision' => '32','nullable' => False),
					'account_firstname' => array('type' => 'varchar','precision' => '50'),
					'account_lastname' => array('type' => 'varchar','precision' => '50'),
					'account_lastlogin' => array('type' => 'int','precision' => '4'),
					'account_lastloginfrom' => array('type' => 'varchar','precision' => '255'),
					'account_lastpwd_change' => array('type' => 'int','precision' => '4'),
					'account_status' => array('type' => 'char','precision' => '1','nullable' => False,'default' => 'A'),
					'account_expires' => array('type' => 'int','precision' => '4'),
					'account_type' => array('type' => 'char','precision' => '1','nullable' => True)
				),
				'pk' => array('account_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array('account_lid')
			),'account_quota');
		}
		/* we dont drop phpGW's new contacts tables for now ;-)
		foreach(array(
			'phpgw_contact',
			'phpgw_contact_person',
			'phpgw_contact_org',
			'phpgw_contact_org_person',
			'phpgw_contact_addr',
			'phpgw_contact_note',
			'phpgw_contact_others',
			'phpgw_contact_comm',
			'phpgw_contact_comm_descr',
			'phpgw_contact_comm_type',
			'phpgw_contact_types',
			'phpgw_contact_addr_type',
			'phpgw_contact_note_type'
		) as $table)
		{
			$GLOBALS['phpgw_setup']->oProc->DropTable($table);
		}*/
		
		// we need to check if we stil have the original addressbook-tables and create them again if not
		if (!in_array('phpgw_addressbook',$tablenames))
		{
			$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_addressbook',array(
				'fd' => array(
					'id' => array('type' => 'auto','nullable' => False),
					'lid' => array('type' => 'varchar','precision' => '32'),
					'tid' => array('type' => 'char','precision' => '1'),
					'owner' => array('type' => 'int','precision' => '8'),
					'access' => array('type' => 'varchar','precision' => '7'),
					'cat_id' => array('type' => 'varchar','precision' => '32'),
					'fn' => array('type' => 'varchar','precision' => '64'),
					'n_family' => array('type' => 'varchar','precision' => '64'),
					'n_given' => array('type' => 'varchar','precision' => '64'),
					'n_middle' => array('type' => 'varchar','precision' => '64'),
					'n_prefix' => array('type' => 'varchar','precision' => '64'),
					'n_suffix' => array('type' => 'varchar','precision' => '64'),
					'sound' => array('type' => 'varchar','precision' => '64'),
					'bday' => array('type' => 'varchar','precision' => '32'),
					'note' => array('type' => 'text'),
					'tz' => array('type' => 'varchar','precision' => '8'),
					'geo' => array('type' => 'varchar','precision' => '32'),
					'url' => array('type' => 'varchar','precision' => '128'),
					'pubkey' => array('type' => 'text'),
					'org_name' => array('type' => 'varchar','precision' => '64'),
					'org_unit' => array('type' => 'varchar','precision' => '64'),
					'title' => array('type' => 'varchar','precision' => '64'),
					'adr_one_street' => array('type' => 'varchar','precision' => '64'),
					'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
					'adr_one_region' => array('type' => 'varchar','precision' => '64'),
					'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
					'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
					'adr_one_type' => array('type' => 'varchar','precision' => '32'),
					'label' => array('type' => 'text'),
					'adr_two_street' => array('type' => 'varchar','precision' => '64'),
					'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
					'adr_two_region' => array('type' => 'varchar','precision' => '64'),
					'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
					'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
					'adr_two_type' => array('type' => 'varchar','precision' => '32'),
					'tel_work' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_home' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_voice' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_fax' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_msg' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_cell' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_pager' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_bbs' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_modem' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_car' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_isdn' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_video' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
					'tel_prefer' => array('type' => 'varchar','precision' => '32'),
					'email' => array('type' => 'varchar','precision' => '64'),
					'email_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
					'email_home' => array('type' => 'varchar','precision' => '64'),
					'email_home_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
					'last_mod' => array('type' => 'int','precision' => '8','nullable' => False)
				),
				'pk' => array('id'),
				'fk' => array(),
				'ix' => array(array('tid','owner','access','n_family','n_given','email'),array('tid','cat_id','owner','access','n_family','n_given','email')),
				'uc' => array()
			));
			$GLOBALS['phpgw_setup']->oProc->CreateTable('phpgw_addressbook_extra',array(
				'fd' => array(
					'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
					'contact_owner' => array('type' => 'int','precision' => '8'),
					'contact_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
					'contact_value' => array('type' => 'text')
				),
				'pk' => array('contact_id','contact_name'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			));
		}
		// now we return to the version of the fork
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.508';
	}
	
	/*
	 * Updates / downgrades from phpGroupWare HEAD branch
	 */

	$test[] = '0.9.15.013';
	function phpgwapi_upgrade0_9_15_013()
	{
		// is db-compatible to 0.9.14.507
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.507';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.15.014';
	function phpgwapi_upgrade0_9_15_014()
	{
		// is db-compatible to 0.9.14.508
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.14.508';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	/*
	 * More eGroupWare 0.9.99 updates
	 */

	$test[] = '0.9.99.003';
	function phpgwapi_upgrade0_9_99_003()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_id',array(
			'type' => 'auto'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lid',array(
			'type' => 'varchar',
			'precision' => '25'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_firstname',array(
			'type' => 'varchar',
			'precision' => '50'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastname',array(
			'type' => 'varchar',
			'precision' => '50'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastlogin',array(
			'type' => 'int',
			'precision' => '4'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastloginfrom',array(
			'type' => 'varchar',
			'precision' => '255'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_lastpwd_change',array(
			'type' => 'int',
			'precision' => '4'
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_accounts','account_expires',array(
			'type' => 'int',
			'precision' => '4'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.004';
	function phpgwapi_upgrade0_9_99_004()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_app_sessions','content',array(
			'type' => 'longtext'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.005';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.005';
	function phpgwapi_upgrade0_9_99_005()
	{
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_languages SET available='Yes' WHERE lang_id='sl'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.006';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.006';
	function phpgwapi_upgrade0_9_99_006()
	{
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_languages SET available='Yes' WHERE lang_id='pt'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.007';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.007';
	function phpgwapi_upgrade0_9_99_007()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_languages', 'lang_id', array('type' => 'varchar','precision' => '5','nullable' => False));
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_languages (lang_id, lang_name, available) VALUES ('pt-br','Brazil','Yes')");
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.008';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.008';
	function phpgwapi_upgrade0_9_99_008()
	{
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_languages (lang_id, lang_name, available) VALUES ('es-es','Espa�ol / Espa�a','Yes')");
		$GLOBALS['phpgw_setup']->oProc->query("INSERT INTO phpgw_languages (lang_id, lang_name, available) VALUES ('es-mx','Spanish / Mexico','Yes')");
		$GLOBALS['phpgw_setup']->oProc->query("DELETE FROM phpgw_languages where lang_id='es'");
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.009';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.009';
	function phpgwapi_upgrade0_9_99_009()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn(
			'phpgw_accounts',
			'account_pwd',
			array('type' => 'varchar','precision' => '100','nullable' => False)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.010';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.010';
	function phpgwapi_upgrade0_9_99_010()
	{
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_languages SET available='Yes' WHERE lang_id='uk'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.011';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.011';
	function phpgwapi_upgrade0_9_99_011()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_categories','last_mod',array(
			'type' => 'int',
			'precision' => '8',
			'nullable' => False
		));
		$GLOBALS['phpgw_setup']->oProc->AlterColumn('phpgw_addressbook','last_mod',array(
			'type' => 'int',
			'precision' => '8',
			'nullable' => False
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.012';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.012';
	function phpgwapi_upgrade0_9_99_012()
	{
		$GLOBALS['phpgw_setup']->oProc->AlterColumn(
			'phpgw_accounts',
			'account_lid',
			array('type' => 'varchar','precision' => '25','nullable' => False)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.013';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.013';
	function phpgwapi_upgrade0_9_99_013()
	{
		// this update fixes the problem that some users cant change their password
		// it was caused be 0 acl_rights values in groups (inserted by setup::add_acl which is fixed too)
		$GLOBALS['phpgw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_appname='preferences' AND acl_location='changepassword' AND acl_rights=0");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.014';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '0.9.99.014';
	function phpgwapi_upgrade0_9_99_014()
	{
		// enabeling russian language
		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_languages SET available='Yes' WHERE lang_id='ru'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.015';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	// the following series of upgrades create indices for the api tables, RalfBecker 2004/04/03

	$test[] = '0.9.99.015';
	function phpgwapi_upgrade0_9_99_015()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_acl',array(
			'fd' => array(
				'acl_appname' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'acl_location' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'acl_account' => array('type' => 'int','precision' => '4','nullable' => False),
				'acl_rights' => array('type' => 'int','precision' => '4')
			),
			'pk' => array('acl_appname','acl_location','acl_account'),
			'fk' => array(),
			'ix' => array('acl_account',array('acl_location','acl_account'),array('acl_appname','acl_account')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.016';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.016';
	function phpgwapi_upgrade0_9_99_016()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_categories',array(
			'fd' => array(
				'cat_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'cat_main' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cat_parent' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cat_level' => array('type' => 'int','precision' => '2','nullable' => False,'default' => '0'),
				'cat_owner' => array('type' => 'int','precision' => '4','nullable' => False,'default' => '0'),
				'cat_access' => array('type' => 'varchar','precision' => '7'),
				'cat_appname' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'cat_name' => array('type' => 'varchar','precision' => '150','nullable' => False),
				'cat_description' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'cat_data' => array('type' => 'text'),
				'last_mod' => array('type' => 'int','precision' => '8','nullable' => False)
			),
			'pk' => array('cat_id'),
			'fk' => array(),
			'ix' => array(array('cat_appname','cat_owner','cat_parent','cat_level')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.017';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.017';
	function phpgwapi_upgrade0_9_99_017()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_lang',array(
			'fd' => array(
				'lang' => array('type' => 'varchar','precision' => '5','nullable' => False,'default' => ''),
				'app_name' => array('type' => 'varchar','precision' => '100','nullable' => False,'default' => 'common'),
				'message_id' => array('type' => 'varchar','precision' => '255','nullable' => False,'default' => ''),
				'content' => array('type' => 'text')
			),
			'pk' => array('lang','app_name','message_id'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.018';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.018';
	function phpgwapi_upgrade0_9_99_018()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_config',array(
			'fd' => array(
				'config_app' => array('type' => 'varchar','precision' => '50','nullable' => False),
				'config_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'config_value' => array('type' => 'text')
			),
			'pk' => array('config_app','config_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.019';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.019';
	function phpgwapi_upgrade0_9_99_019()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_applications',array(
			'fd' => array(
				'app_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'app_name' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'app_enabled' => array('type' => 'int','precision' => '4','nullable' => False),
				'app_order' => array('type' => 'int','precision' => '4','nullable' => False),
				'app_tables' => array('type' => 'text','nullable' => False),
				'app_version' => array('type' => 'varchar','precision' => '20','nullable' => False,'default' => '0.0')
			),
			'pk' => array('app_id'),
			'fk' => array(),
			'ix' => array(array('app_enabled','app_order')),
			'uc' => array('app_name')
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.020';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.020';
	function phpgwapi_upgrade0_9_99_020()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_app_sessions',array(
			'fd' => array(
				'sessionid' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'loginid' => array('type' => 'int','precision' => '4','nullable' => False),
				'app' => array('type' => 'varchar','precision' => '25','nullable' => False),
				'location' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'content' => array('type' => 'longtext'),
				'session_dla' => array('type' => 'int','precision' => '4')
			),
			'pk' => array('sessionid','loginid','location','app'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.021';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.021';
	function phpgwapi_upgrade0_9_99_021()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_sessions',array(
			'fd' => array(
				'session_id' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'session_lid' => array('type' => 'varchar','precision' => '128'),
				'session_ip' => array('type' => 'varchar','precision' => '32'),
				'session_logintime' => array('type' => 'int','precision' => '4'),
				'session_dla' => array('type' => 'int','precision' => '4'),
				'session_action' => array('type' => 'varchar','precision' => '255'),
				'session_flags' => array('type' => 'char','precision' => '2')
			),
			'pk' => array(),
			'fk' => array(),
			'ix' => array(array('session_flags','session_dla')),
			'uc' => array('session_id')
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.022';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.022';
	function phpgwapi_upgrade0_9_99_022()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_history_log',array(
			'fd' => array(
				'history_id' => array('type' => 'auto','precision' => '4','nullable' => False),
				'history_record_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'history_appname' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'history_owner' => array('type' => 'int','precision' => '4','nullable' => False),
				'history_status' => array('type' => 'char','precision' => '2','nullable' => False),
				'history_new_value' => array('type' => 'text','nullable' => False),
				'history_timestamp' => array('type' => 'timestamp','nullable' => False,'default' => 'current_timestamp'),
				'history_old_value' => array('type' => 'text','nullable' => False)
			),
			'pk' => array('history_id'),
			'fk' => array(),
			'ix' => array(array('history_appname','history_record_id','history_status','history_timestamp')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.023';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.023';
	function phpgwapi_upgrade0_9_99_023()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_vfs',array(
			'fd' => array(
				'file_id' => array('type' => 'auto','nullable' => False),
				'owner_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'createdby_id' => array('type' => 'int','precision' => '4'),
				'modifiedby_id' => array('type' => 'int','precision' => '4'),
				'created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
				'modified' => array('type' => 'date'),
				'size' => array('type' => 'int','precision' => '4'),
				'mime_type' => array('type' => 'varchar','precision' => '64'),
				'deleteable' => array('type' => 'char','precision' => '1','default' => 'Y'),
				'comment' => array('type' => 'varchar','precision' => '255'),
				'app' => array('type' => 'varchar','precision' => '25'),
				'directory' => array('type' => 'varchar','precision' => '255'),
				'name' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'link_directory' => array('type' => 'varchar','precision' => '255'),
				'link_name' => array('type' => 'varchar','precision' => '128'),
				'version' => array('type' => 'varchar','precision' => '30','nullable' => False,'default' => '0.0.0.0'),
				'content' => array('type' => 'text')
			),
			'pk' => array('file_id'),
			'fk' => array(),
			'ix' => array(array('directory','name','mime_type')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.024';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.024';
	function phpgwapi_upgrade0_9_99_024()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_addressbook_extra',array(
			'fd' => array(
				'contact_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'contact_owner' => array('type' => 'int','precision' => '8'),
				'contact_name' => array('type' => 'varchar','precision' => '255','nullable' => False),
				'contact_value' => array('type' => 'text')
			),
			'pk' => array('contact_id','contact_name'),
			'fk' => array(),
			'ix' => array(),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '0.9.99.025';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '0.9.99.025';
	function phpgwapi_upgrade0_9_99_025()
	{
		$GLOBALS['phpgw_setup']->oProc->RefreshTable('phpgw_addressbook',array(
			'fd' => array(
				'id' => array('type' => 'auto','nullable' => False),
				'lid' => array('type' => 'varchar','precision' => '32'),
				'tid' => array('type' => 'char','precision' => '1'),
				'owner' => array('type' => 'int','precision' => '8'),
				'access' => array('type' => 'varchar','precision' => '7'),
				'cat_id' => array('type' => 'varchar','precision' => '32'),
				'fn' => array('type' => 'varchar','precision' => '64'),
				'n_family' => array('type' => 'varchar','precision' => '64'),
				'n_given' => array('type' => 'varchar','precision' => '64'),
				'n_middle' => array('type' => 'varchar','precision' => '64'),
				'n_prefix' => array('type' => 'varchar','precision' => '64'),
				'n_suffix' => array('type' => 'varchar','precision' => '64'),
				'sound' => array('type' => 'varchar','precision' => '64'),
				'bday' => array('type' => 'varchar','precision' => '32'),
				'note' => array('type' => 'text'),
				'tz' => array('type' => 'varchar','precision' => '8'),
				'geo' => array('type' => 'varchar','precision' => '32'),
				'url' => array('type' => 'varchar','precision' => '128'),
				'pubkey' => array('type' => 'text'),
				'org_name' => array('type' => 'varchar','precision' => '64'),
				'org_unit' => array('type' => 'varchar','precision' => '64'),
				'title' => array('type' => 'varchar','precision' => '64'),
				'adr_one_street' => array('type' => 'varchar','precision' => '64'),
				'adr_one_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_one_region' => array('type' => 'varchar','precision' => '64'),
				'adr_one_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_one_countryname' => array('type' => 'varchar','precision' => '64'),
				'adr_one_type' => array('type' => 'varchar','precision' => '32'),
				'label' => array('type' => 'text'),
				'adr_two_street' => array('type' => 'varchar','precision' => '64'),
				'adr_two_locality' => array('type' => 'varchar','precision' => '64'),
				'adr_two_region' => array('type' => 'varchar','precision' => '64'),
				'adr_two_postalcode' => array('type' => 'varchar','precision' => '64'),
				'adr_two_countryname' => array('type' => 'varchar','precision' => '64'),
				'adr_two_type' => array('type' => 'varchar','precision' => '32'),
				'tel_work' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_home' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_voice' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_fax' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_msg' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_cell' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_pager' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_bbs' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_modem' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_car' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_isdn' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_video' => array('type' => 'varchar','precision' => '40','nullable' => False,'default' => '+1 (000) 000-0000'),
				'tel_prefer' => array('type' => 'varchar','precision' => '32'),
				'email' => array('type' => 'varchar','precision' => '64'),
				'email_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
				'email_home' => array('type' => 'varchar','precision' => '64'),
				'email_home_type' => array('type' => 'varchar','precision' => '32','default' => 'INTERNET'),
				'last_mod' => array('type' => 'int','precision' => '8','nullable' => False)
			),
			'pk' => array('id'),
			'fk' => array(),
			'ix' => array(array('tid','owner','access','n_family','n_given','email'),array('tid','cat_id','owner','access','n_family','n_given','email')),
			'uc' => array()
		));

		// we dont need to do update 0.9.99.026, as UpdateSequenze is called now by RefreshTable
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
	
	
	$test[] = '0.9.99.026';
	function phpgwapi_upgrade0_9_99_026()
	{
		// update the sequenzes for refreshed tables (postgres only)
		$GLOBALS['phpgw_setup']->oProc->UpdateSequence('phpgw_categories','cat_id');
		$GLOBALS['phpgw_setup']->oProc->UpdateSequence('phpgw_applications','app_id');
		$GLOBALS['phpgw_setup']->oProc->UpdateSequence('phpgw_history_log','history_id');
		$GLOBALS['phpgw_setup']->oProc->UpdateSequence('phpgw_vfs','file_id');
		$GLOBALS['phpgw_setup']->oProc->UpdateSequence('phpgw_addressbook','id');
		
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.0';
	function phpgwapi_upgrade1_0_0()
	{
		$GLOBALS['phpgw_setup']->oProc->AddColumn('phpgw_accounts','account_email',array(
			'type' => 'varchar',
			'precision' => '100'
		));
		
		$GLOBALS['phpgw_setup']->oProc->query("SELECT config_value FROM phpgw_config WHERE config_app='phpgwapi' AND config_name='mail_suffix'",__LINE__,__FILE__);
		$mail_domain = $GLOBALS['phpgw_setup']->oProc->next_record() ? $GLOBALS['phpgw_setup']->oProc->f(0) : '';

		// copy the email-addresses from the preferences of the mail-app (if set) to the new field
		$db2 = $GLOBALS['phpgw_setup']->oProc->m_odb;
		$sql = "SELECT account_id,account_lid,preference_value FROM phpgw_accounts LEFT JOIN phpgw_preferences ON account_id=preference_owner AND preference_app='email' WHERE account_type = 'u'";
		$GLOBALS['phpgw_setup']->oProc->query($sql,__LINE__,__FILE__);
		while ($GLOBALS['phpgw_setup']->oProc->next_record())
		{
			$email_prefs = unserialize($GLOBALS['phpgw_setup']->oProc->f('preference_value'));
			$account_lid = $GLOBALS['phpgw_setup']->oProc->f('account_lid');
			$db2->update('phpgw_accounts',array(
				'account_email' => $email_prefs['address'] ? $email_prefs['address'] : $account_lid.(strstr($account_lid,'@')===False?'@'.$mail_domain:''),
			),array(
				'account_id' => $GLOBALS['phpgw_setup']->oProc->f('account_id')
			),__LINE__,__FILE__);
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0.000';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.0.000';
	function phpgwapi_upgrade1_0_0_000()
	{
		// removing the not longer needed 'availible' column, that information is in the file setup/lang/languages
		$GLOBALS['phpgw_setup']->oProc->DropColumn('phpgw_languages',array(
			'fd' => array(
				'lang_id' => array('type' => 'varchar','precision' => '5','nullable' => False),
				'lang_name' => array('type' => 'varchar','precision' => '50','nullable' => False)
			),
			'pk' => array('lang_id'),
			'ix' => array(),
			'fk' => array(),
			'uc' => array()
		),'available');

		// correcting the id for Catalan
//		$GLOBALS['phpgw_setup']->oProc->query("UPDATE phpgw_languages SET lang_id='es-ca' WHERE lang_id='ca'");

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}
?>
