<?php
	/**************************************************************************\
	* eGroupWare - Setup                                                       *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	// $Id: tables_update_1_0.inc.php 24084 2007-06-12 15:38:04Z ralfbecker $

	// updates from the stable 1.0.0 branch
	$test[] = '1.0.0.001';
	function phpgwapi_upgrade1_0_0_001()
	{
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.0.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.0.002';
	function phpgwapi_upgrade1_0_0_002()
	{
		// identical to 1.0.0.001, only created to get a new version of the packages
		return phpgwapi_upgrade1_0_0_001();
	}

	$test[] = '1.0.0.003';
	function phpgwapi_upgrade1_0_0_003()
	{
		// identical to 1.0.0.001, only created to get a new version of the final 1.0 packages
		return phpgwapi_upgrade1_0_0_001();
	}
	
	$test[] = '1.0.0.004';
	function phpgwapi_upgrade1_0_0_004()
	{
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_async','id','async_id');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_async','next','async_next');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_async','times','async_times');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_async','method','async_method');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_async','data','async_data');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_async','account_id','async_account_id');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.001';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.0.005';
	function phpgwapi_upgrade1_0_0_005()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.0.006';
	function phpgwapi_upgrade1_0_0_006()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.0.007';
	function phpgwapi_upgrade1_0_0_007()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.0.008';
	function phpgwapi_upgrade1_0_0_008()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.0.009';
	function phpgwapi_upgrade1_0_0_009()
	{
		// identical to 1.0.0.001, only created to get a new version of the bugfix release
		return phpgwapi_upgrade1_0_0_004();
	}
	
	$test[] = '1.0.1.001';
	function phpgwapi_upgrade1_0_1_001()
	{
		// removing the ACL entries of deleted accounts
		$GLOBALS['egw_setup']->setup_account_object();
		if ($GLOBALS['egw']->accounts->table)
		{
			$GLOBALS['egw']->accounts->table = $GLOBALS['egw_setup']->accounts_table;
		}
		if (($all_accounts = $GLOBALS['egw']->accounts->search(array('type'=>'both'))))
		{
			foreach($all_accounts as $key => $value)
			{
				// the latest version of the egw api(>1.2.001) is returning negative groupids
				// but in the currently updated version of the acl table, the groupids are yet positive
				$allaccounts[] = abs($key);
			}
			$GLOBALS['egw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_account NOT IN (".implode(',',$allaccounts).")",__LINE__,__FILE__);
			$GLOBALS['egw_setup']->oProc->query("DELETE FROM phpgw_acl WHERE acl_appname='phpgw_group' AND acl_location NOT IN ('".implode("','",$allaccounts)."')",__LINE__,__FILE__);
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.002';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.002';
	function phpgwapi_upgrade1_0_1_002()
	{
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','file_id','vfs_file_id');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','owner_id','vfs_owner_id');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','createdby_id','vfs_createdby_id');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','modifiedby_id','vfs_modifiedby_id');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','created','vfs_created');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','modified','vfs_modified');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','size','vfs_size');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','mime_type','vfs_mime_type');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','deleteable','vfs_deleteable');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','comment','vfs_comment');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','app','vfs_app');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','directory','vfs_directory');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','name','vfs_name');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','link_directory','vfs_link_directory');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','link_name','vfs_link_name');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','version','vfs_version');
		$GLOBALS['egw_setup']->oProc->RenameColumn('phpgw_vfs','content','vfs_content');
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_vfs','egw_vfs');

		$GLOBALS['egw_setup']->oProc->RefreshTable('egw_vfs',array(
			'fd' => array(
				'vfs_file_id' => array('type' => 'auto','nullable' => False),
				'vfs_owner_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'vfs_createdby_id' => array('type' => 'int','precision' => '4'),
				'vfs_modifiedby_id' => array('type' => 'int','precision' => '4'),
				'vfs_created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
				'vfs_modified' => array('type' => 'date'),
				'vfs_size' => array('type' => 'int','precision' => '4'),
				'vfs_mime_type' => array('type' => 'varchar','precision' => '64'),
				'vfs_deleteable' => array('type' => 'char','precision' => '1','default' => 'Y'),
				'vfs_comment' => array('type' => 'varchar','precision' => '255'),
				'vfs_app' => array('type' => 'varchar','precision' => '25'),
				'vfs_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_name' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'vfs_link_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_link_name' => array('type' => 'varchar','precision' => '128'),
				'vfs_version' => array('type' => 'varchar','precision' => '30','nullable' => False,'default' => '0.0.0.0'),
				'vfs_content' => array('type' => 'text')
			),
			'pk' => array('vfs_file_id'),
			'fk' => array(),
			'ix' => array(array('vfs_directory','vfs_name')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.003';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.003';
	function phpgwapi_upgrade1_0_1_003()
	{
		$GLOBALS['egw_setup']->oProc->CreateTable(
			'egw_api_content_history', array(
				'fd' => array(
					'sync_appname'	=>  array('type' => 'varchar','precision' => '60','nullable' => False),
					'sync_contentid' => array('type' => 'varchar','precision' => '60','nullable' => False),
					'sync_added'	=>  array('type' => 'timestamp', 'nullable' => False),
					'sync_modified'	=>  array('type' => 'timestamp', 'nullable' => False),
					'sync_deleted'	=>  array('type' => 'timestamp', 'nullable' => False),
					'sync_id'	=>  array('type' => 'auto','nullable' => False),
					'sync_guid'	=>  array('type' => 'varchar','precision' => '120','nullable' => False),
					'sync_changedby' => array('type' => 'int','precision' => '4','nullable' => False),
				),
				'pk' => array('sync_id'),
				'fk' => array(),
				'ix' => array(array('sync_appname','sync_contentid'),'sync_added','sync_modified','sync_deleted','sync_guid','sync_changedby'),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.004';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.004';
	function phpgwapi_upgrade1_0_1_004()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_api_content_history','sync_added',array(
			'type' => 'timestamp'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_api_content_history','sync_modified',array(
			'type' => 'timestamp'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_api_content_history','sync_deleted',array(
			'type' => 'timestamp'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.005';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.005';
	function phpgwapi_upgrade1_0_1_005()
	{
		/*********************************************************************\
		 *	                       VFS version 2                             *
		\*********************************************************************/

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_mimetypes', array(
				'fd' => array(
					'mime_id' => array('type' => 'auto','nullable' => False),
					'extension' => array('type' => 'varchar', 'precision' => 10, 'nullable' => false),
					'mime' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
					'mime_magic' => array('type' => 'varchar', 'precision' => 255, 'nullable' => true),
					'friendly' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
					'image' => array('type' => 'blob'),
					'proper_id' => array('type' => 'varchar', 'precision' => 4)
				),
				'pk' => array('mime_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_files' , array(
				'fd' => array(
					'file_id' => array('type' => 'auto','nullable' => False),
					'mime_id' => array('type' => 'int','precision' => 4),
					'owner_id' => array('type' => 'int','precision' => 4,'nullable' => False),
					'createdby_id' => array('type' => 'int','precision' => 4),
					'created' => array('type' => 'timestamp','default' => '1970-01-01 00:00:00', 'nullable' => False),
					'size' => array('type' => 'int','precision' => 8),
					'deleteable' => array('type' => 'char','precision' => 1,'default' => 'Y'),
					'comment' => array('type' => 'varchar','precision' => 255),
					'app' => array('type' => 'varchar','precision' => 25),
					'directory' => array('type' => 'varchar','precision' => 255),
					'name' => array('type' => 'varchar','precision' => 128,'nullable' => False),
					'link_directory' => array('type' => 'varchar','precision' => 255),
					'link_name' => array('type' => 'varchar','precision' => 128),
					'version' => array('type' => 'varchar','precision' => 30,'nullable' => False,'default' => '0.0.0.0'),
					'content' => array('type' => 'longtext'),
					'is_backup' => array('type' => 'varchar', 'precision' => 1, 'nullable' => False, 'default' => 'N'),
					'shared' => array('type' => 'varchar', 'precision' => 1, 'nullable' => False,'default' => 'N'),
					'proper_id' => array('type' => 'varchar', 'precision' => 45)
				),
				'pk' => array('file_id'),
				'fk' => array('mime_id' => array ('phpgw_vfs2_mimetypes' => 'mime_id')),
				'ix' => array(array('directory','name')),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_customfields' , array(
				'fd' => array(
					'customfield_id' => array('type' => 'auto','nullable' => False),
					'customfield_name' => array('type' => 'varchar','precision' => 60,'nullable' => False),
					'customfield_description' => array('type' => 'varchar','precision' => 255,'nullable'=> True),
					'customfield_type' => array('type' => 'varchar','precision' => 20, 'nullable' => false),
					'customfield_precision' => array('type' => 'int', 'precision' => 4, 'nullable' => true),
					'customfield_active' => array('type' => 'varchar','precision' => 1,'nullable' => False,'default' => 'N')
				),
				'pk' => array('customfield_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_quota' , array(
				'fd' => array(
					'account_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'quota' => array('type' => 'int','precision' => 4,'nullable' => false)
				),
				'pk' => array('account_id'),
				'fk' => array('account_id' => array('phpgw_accounts' => 'account_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_shares' , array(
				'fd' => array(
					'account_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'acl_rights' => array('type' => 'int','precision' => 4,'nullable' => false)
				),
				'pk' => array('account_id','file_id'),
				'fk' => array('account_id' => array('phpgw_accounts' => 'account_id'), 'file_id' => array('phpgw_vfs2_files' => 'file_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_versioning' , array(
				'fd' => array(
					'version_id' => array('type' => 'auto', 'nullable' => false),
					'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'operation' => array('type' => 'int','precision' => 4, 'nullable' => False),
					'modifiedby_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'modified' => array('type' => 'timestamp', 'nullable' => False ),
					'version' => array('type' => 'varchar', 'precision' => 30, 'nullable' => False ),
					'comment' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'backup_file_id' => array('type' => 'int','precision' => 4, 'nullable' => True),
					'backup_content' => array('type' => 'longtext', 'nullable' => True),
					'src' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True),
					'dest' => array('type' => 'varchar', 'precision' => 255, 'nullable' => True)
				),
				'pk' => array('version_id'),
				'fk' => array('file_id' => array('phpgw_vfs2_files' => 'file_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_customfields_data' , array(
				'fd' => array(
					'file_id' => array('type' => 'int','precision' => 4,'nullable' => false),
					'customfield_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
					'data' => array('type' => 'longtext', 'nullable' => True)
				),
				'pk' => array('file_id','customfield_id'),
				'fk' => array('file_id' => array('phpgw_vfs2_files' => 'file_id'),'customfield_id' => array('phpgw_vfs2_customfields' => 'customfield_id')),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'phpgw_vfs2_prefixes' , array(
				'fd' => array(
					'prefix_id' => array('type' => 'auto','nullable' => false),
					'prefix' => array('type' => 'varchar', 'precision' => 8, 'nullable' => false),
					'owner_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
					'prefix_description' => array('type' => 'varchar', 'precision' => 30, 'nullable' => True),
					'prefix_type' => array('type' => 'varchar', 'precision' => 1, 'nullable' => false, 'default' => 'p')
				),
				'pk' => array('prefix_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		/*************************************************************************\
		 *                    Default Records for VFS v2                         *
		\*************************************************************************/
		if ($GLOBALS['DEBUG'])
		{
			echo "<br>\n<b>initiating to create the default records for VFS SQL2...";
		}
		
		include PHPGW_INCLUDE_ROOT.'/phpgwapi/setup/default_records_mime.inc.php';

		$GLOBALS['egw_setup']->oProc->query("INSERT INTO phpgw_vfs2_files (mime_id,owner_id,createdby_id,size,directory,name)
					   SELECT mime_id,0,0,4096,'/','' FROM phpgw_vfs2_mimetypes WHERE mime='Directory'");

		if ($GLOBALS['DEBUG'])
		{
			echo " DONE!</b>";
		}
		/*************************************************************************/

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.006';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.006';
	function phpgwapi_upgrade1_0_1_006()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_async','egw_async');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.007';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.007';
	function phpgwapi_upgrade1_0_1_007()
	{
		//Creating cached values for modified and modifiedby_id
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_vfs2_files', 'modifiedby_id', array('type' => 'int','precision' => 4));
		$GLOBALS['egw_setup']->oProc->AddColumn('phpgw_vfs2_files', 'modified', array('type' => 'timestamp', 'nullable' => true));

		//Updating existing values
		$sql = "SELECT max(modified) as mod, file_id, min(modifiedby_id) from phpgw_vfs2_versioning group by file_id";

		$GLOBALS['egw_setup']->oProc->m_odb->query($sql,__LINE__,__FILE__);

		$files_to_change = array();
		while ($GLOBALS['egw_setup']->oProc->m_odb->next_record())
		{
			$files_to_change[] = $GLOBALS['egw_setup']->oProc->m_odb->Record;
		}

		foreach ($files_to_change as $key => $val)
		{
			$GLOBALS['egw_setup']->oProc->m_odb->update('phpgw_vfs2_files',
				array(
					'modified' => $val['mod'],
					'modifiedby_id' => $val['modifiedby_id']
					),
				array('file_id' => $val['file_id']),__LINE__,__FILE__
				);
		}

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.008';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.008';
	function phpgwapi_upgrade1_0_1_008()
	{
		$GLOBALS['egw_setup']->oProc->CreateTable(
			'egw_contentmap', array(
				'fd' => array(
					'map_id'	=> array('type' => 'varchar', 'precision' => '255', 'nullable' => False),
					'map_guid'	=> array('type' => 'varchar', 'precision' => '200', 'nullable' => False),
					'map_locuid'	=> array('type' => 'varchar', 'precision' => '200', 'nullable' => False),
					'map_timestamp'	=> array('type' => 'timestamp', 'nullable' => False),
					'map_expired'	=> array('type' => 'bool', 'nullable' => False),
				),
				'pk' => array(array('map_id','map_guid','map_locuid')),
				'fk' => array(),
				'ix' => array(array('map_id','map_locuid'),'map_expired'),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'egw_syncmldevinfo', array(
				'fd' => array(
					'dev_id'		=> array('type' => 'varchar', 'precision' => '255', 'nullable' => False),
					'dev_dtdversion'	=> array('type' => 'varchar', 'precision' => '10', 'nullable' => False),
					'dev_numberofchanges'	=> array('type' => 'bool', 'nullable' => False),
					'dev_largeobjs'		=> array('type' => 'bool', 'nullable' => False),
					'dev_swversion'		=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'dev_oem'		=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'dev_model'		=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'dev_manufacturer'	=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'dev_devicetype'	=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'dev_deviceid'		=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'dev_datastore'		=> array('type' => 'text', 'nullable' => False),
				),
				'pk' => array('dev_id'),
				'fk' => array(),
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['egw_setup']->oProc->CreateTable(
			'egw_syncmlsummary', array(
				'fd' => array(
					'dev_id'		=> array('type' => 'varchar', 'precision' => '255', 'nullable' => False),
					'sync_path'		=> array('type' => 'varchar', 'precision' => '100', 'nullable' => False),
					'sync_serverts'		=> array('type' => 'varchar', 'precision' => '20', 'nullable' => False),
					'sync_clientts'		=> array('type' => 'varchar', 'precision' => '20', 'nullable' => False),
				),
				'pk' => array(array('dev_id','sync_path')),
				'fk' => array(),	
				'ix' => array(),
				'uc' => array()
			)
		);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.009';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.009';
	function phpgwapi_upgrade1_0_1_009()
	{
		if (@file_exists(EGW_SERVER_ROOT . '/home/setup/setup.inc.php'))
		{
			// automatic install of the new home app
			include(EGW_SERVER_ROOT . '/home/setup/setup.inc.php');
			$home_version = $setup_info['home']['version'];
			
			$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->applications_table,array(
				'app_enabled' => $setup_info['home']['enable'],
				'app_order'   => $setup_info['home']['app_order'],
				'app_version' => $setup_info['home']['version'],
				'app_tables'  => '',
				'app_version' => $home_version,
			),array(
				'app_name' => 'home',
			),__LINE__,__FILE__,False,False,$GLOBALS['egw_setup']->oProc->GetTableDefinition($GLOBALS['egw_setup']->applications_table));
			
			// give all users and groups with preferences rights, rights for the home app.
			$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->acl_table,'acl_account',array(
				'acl_appname'  => 'preferences',
				'acl_location' => 'run',
				'acl_rights'   => 1,
			),__LINE__,__FILE__);
			$accounts_with_preference_rights = array();
			while (($row = $GLOBALS['egw_setup']->db->row(true)))
			{
				$accounts_with_preference_rights[] = $row['acl_account'];
			}
			foreach($accounts_with_preference_rights as $account)
			{
				$GLOBALS['egw_setup']->db->insert($GLOBALS['egw_setup']->acl_table,array(
					'acl_rights'   => 1,
				),array(
					'acl_appname'  => 'home',
					'acl_location' => 'run',
					'acl_account'  => $account,
				),__LINE__,__FILE__,False,False,$GLOBALS['egw_setup']->oProc->GetTableDefinition($GLOBALS['egw_setup']->acl_table));
			}	
		}
		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.010';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.010';
	function phpgwapi_upgrade1_0_1_010()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_sessions','session_ip',array(
			'type' => 'varchar',
			'precision' => '40'
		));

		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_access_log','ip',array(
			'type' => 'varchar',
			'precision' => '40',
			'nullable' => False
		));

		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_access_log','loginid',array(
			'type' => 'varchar',
			'precision' => '128'
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.011';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.011';
	function phpgwapi_upgrade1_0_1_011()
	{
		// moving the egw_links table into the API
		if ($GLOBALS['egw_setup']->oProc->GetTableDefinition('phpgw_links'))
		{
			// table exists with old name ==> rename it to new one
			$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_links','egw_links');
		}
		elseif (!$GLOBALS['egw_setup']->oProc->GetTableDefinition('egw_links'))
		{
			// table does not exist at all (infolog not installed) ==> create it
			$GLOBALS['egw_setup']->oProc->CreateTable('egw_links',array(
				'fd' => array(
					'link_id' => array('type' => 'auto','nullable' => False),
					'link_app1' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'link_id1' => array('type' => 'varchar','precision' => '50','nullable' => False),
					'link_app2' => array('type' => 'varchar','precision' => '25','nullable' => False),
					'link_id2' => array('type' => 'varchar','precision' => '50','nullable' => False),
					'link_remark' => array('type' => 'varchar','precision' => '50'),
					'link_lastmod' => array('type' => 'int','precision' => '4','nullable' => False),
					'link_owner' => array('type' => 'int','precision' => '4','nullable' => False)
				),
				'pk' => array('link_id'),
				'fk' => array(),
				'ix' => array(array('link_app1','link_id1','link_lastmod'),array('link_app2','link_id2','link_lastmod')),
				'uc' => array()
			));
		}
		// move the link-configuration to the api
		$GLOBALS['egw_setup']->oProc->query('UPDATE '.$GLOBALS['egw_setup']->config_table." SET config_app='phpgwapi' WHERE config_app='infolog' AND config_name IN ('link_pathes','send_file_ips')",__LINE__,__FILE__);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.012';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.012';
	function phpgwapi_upgrade1_0_1_012()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_accounts','egw_accounts');
		$GLOBALS['egw_setup']->set_table_names(True);
		if ($GLOBALS['egw']->accounts->table)
		{
			$GLOBALS['egw']->accounts->table = $GLOBALS['egw_setup']->accounts_table;
		}
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_acl','egw_acl');
		$GLOBALS['egw_setup']->set_table_names(True);
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_log','egw_log');
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_log_msg','egw_log_msg');

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.013';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.013';
	function phpgwapi_upgrade1_0_1_013()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_config','egw_config');
		$GLOBALS['egw_setup']->set_table_names(True);
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_applications','egw_applications');
		$GLOBALS['egw_setup']->set_table_names(True);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.014';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}

	$test[] = '1.0.1.014';
	function phpgwapi_upgrade1_0_1_014()
	{
		// index was to big for mysql with charset utf8 (max 1000byte = 333 utf8 chars)
		// before we can shorten the message_id, we have to make sure there are no identical message_id > 128 chars
		// and we have to truncate the message_id explicitly, postgresql f.e. will not do it for us, but bail out instead
		$to_delete = array();
		$to_truncate = array();
		$GLOBALS['egw_setup']->db->select('phpgw_lang','app_name,lang,message_id','LENGTH(message_id) > 128',__LINE__,__FILE__,
			false,'ORDER BY app_name,lang,message_id');
		while(($row = $GLOBALS['egw_setup']->db->row(true)))
		{
			if ($last_row && $last_row['app_name'] == $row['app_name'] && $last_row['lang'] == $row['lang'] && 
				substr($last_row['message_id'],0,128) == substr($row['message_id'],0,128))
			{
				$to_delete[] = $row;
			}
			else
			{
				$to_truncate[] = $row;
			}
			$last_row = $row;
		}
		$table_def = $GLOBALS['egw_setup']->oProc->GetTableDefinition('phpgw_lang');
		foreach ($to_delete as $row)
		{
			$GLOBALS['egw_setup']->db->delete('phpgw_lang',$row,__LINE__,__FILE__,False,$table_def);
		}
		foreach ($to_truncate as $row)
		{
			$where = $row;
			$row['message_id'] = substr($row['message_id'],0,128);
			$GLOBALS['egw_setup']->db->update('phpgw_lang',$row,$where,__LINE__,__FILE__,'phpgwapi',False,$table_def);
		}
		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_lang','app_name',array(
			'type' => 'varchar',
			'precision' => '32',
			'nullable' => False,
			'default' => 'common'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_lang','message_id',array(
			'type' => 'varchar',
			'precision' => '128',
			'nullable' => False,
			'default' => ''
		));
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_lang','egw_lang');
		$GLOBALS['egw_setup']->set_table_names(True);
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_languages','egw_languages');
		$GLOBALS['egw_setup']->set_table_names(True);

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.015';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.015';
	function phpgwapi_upgrade1_0_1_015()
	{
		// index was to big for mysql with charset utf8 (max 1000byte = 333 utf8 chars)
		/* done by RefreshTable() anyway
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_contentmap','map_id',array(
			'type' => 'varchar',
			'precision' => '128',
			'nullable' => False
		));*/
		/* done by RefreshTable() anyway
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_contentmap','map_guid',array(
			'type' => 'varchar',
			'precision' => '128',
			'nullable' => False
		));*/
		/* done by RefreshTable() anyway
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_contentmap','map_locuid',array(
			'type' => 'int',
			'precision' => '8',
			'nullable' => False
		));*/
		$GLOBALS['egw_setup']->oProc->RefreshTable('egw_contentmap',array(
			'fd' => array(
				'map_id' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'map_guid' => array('type' => 'varchar','precision' => '128','nullable' => False),
				'map_locuid' => array('type' => 'int','precision' => '8','nullable' => False),
				'map_timestamp' => array('type' => 'timestamp','nullable' => False),
				'map_expired' => array('type' => 'bool','nullable' => False)
			),
			'pk' => array('map_id','map_guid','map_locuid'),
			'fk' => array(),
			'ix' => array('map_expired',array('map_id','map_locuid')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.016';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.016';
	function phpgwapi_upgrade1_0_1_016()
	{
		// index was to big for mysql with charset utf8 (max 1000byte = 333 utf8 chars)
		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_vfs2_files','name',array(
			'type' => 'varchar',
			'precision' => '64',
			'nullable' => False
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.017';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.017';
	function phpgwapi_upgrade1_0_1_017()
	{
		// index was to big for mysql with charset utf8 (max 1000byte = 333 utf8 chars)
		/* done by RefreshTable() anyway
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_vfs','vfs_name',array(
			'type' => 'varchar',
			'precision' => '64',
			'nullable' => False
		));*/
		$GLOBALS['egw_setup']->oProc->RefreshTable('egw_vfs',array(
			'fd' => array(
				'vfs_file_id' => array('type' => 'auto','nullable' => False),
				'vfs_owner_id' => array('type' => 'int','precision' => '4','nullable' => False),
				'vfs_createdby_id' => array('type' => 'int','precision' => '4'),
				'vfs_modifiedby_id' => array('type' => 'int','precision' => '4'),
				'vfs_created' => array('type' => 'date','nullable' => False,'default' => '1970-01-01'),
				'vfs_modified' => array('type' => 'date'),
				'vfs_size' => array('type' => 'int','precision' => '4'),
				'vfs_mime_type' => array('type' => 'varchar','precision' => '64'),
				'vfs_deleteable' => array('type' => 'char','precision' => '1','default' => 'Y'),
				'vfs_comment' => array('type' => 'varchar','precision' => '255'),
				'vfs_app' => array('type' => 'varchar','precision' => '25'),
				'vfs_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_name' => array('type' => 'varchar','precision' => '64','nullable' => False),
				'vfs_link_directory' => array('type' => 'varchar','precision' => '255'),
				'vfs_link_name' => array('type' => 'varchar','precision' => '128'),
				'vfs_version' => array('type' => 'varchar','precision' => '30','nullable' => False,'default' => '0.0.0.0'),
				'vfs_content' => array('type' => 'text')
			),
			'pk' => array('vfs_file_id'),
			'fk' => array(),
			'ix' => array(array('vfs_directory','vfs_name')),
			'uc' => array()
		));

		$GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.018';
		return $GLOBALS['setup_info']['phpgwapi']['currentver'];
	}


	$test[] = '1.0.1.018';
	function phpgwapi_upgrade1_0_1_018()
	{
		// This update fixes charset in mysql4+ tables, if the default client charset does not match the eGW system-charset.
		// It is necessary as update, as we now set the system_charset as client charset, which causes the existing input to be returned wrong.
		
		
		// We have to shorten the felamimail columns first, as this update would fail, because it's run before the felamimail update
		// (shortening them twice, does no harm) !!!
		if ($GLOBALS['egw_setup']->table_exist(array('phpgw_felamimail_cache')))
		{
			$table_def_cache = $GLOBALS['egw_setup']->oProc->GetTableDefinition('phpgw_felamimail_cache');
			$table_def_folderstatus = $GLOBALS['egw_setup']->oProc->GetTableDefinition('phpgw_felamimail_folderstatus');

			foreach (array('fmail_accountname','accountname','fmail_foldername','foldername') as $column_name)
			{
				if (isset($table_def_cache['fd'][$column_name]))
				{
					$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_cache',$column_name,array(
				'type' => 'varchar',
				'precision' => '128',
				'nullable' => False
			));
				}
				if (isset($table_def_folderstatus['fd'][$column_name]))
				{
					$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_felamimail_folderstatus',$column_name,array(
				'type' => 'varchar',
				'precision' => '128',
				'nullable' => False
			));
		}
			}
		}
		if (substr($GLOBALS['egw_setup']->db->Type,0,5) == 'mysql' && $GLOBALS['egw_setup']->system_charset && $GLOBALS['egw_setup']->db_charset_was &&
			$GLOBALS['egw_setup']->system_charset != $GLOBALS['egw_setup']->db_charset_was)
		{
			include(EGW_SERVER_ROOT.'/setup/fix_mysql_charset.php');

			// now the DB is fixed we can set the charset
			$GLOBALS['egw_setup']->db->Link_ID->SetCharSet($GLOBALS['egw_setup']->system_charset);
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.019';
	}


	$test[] = '1.0.1.019';
	function phpgwapi_upgrade1_0_1_019()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_categories','egw_categories');
		$GLOBALS['egw_setup']->cats_table = 'egw_categories';

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.020';
	}


	$test[] = '1.0.1.020';
	function phpgwapi_upgrade1_0_1_020()
	{
		// in some old installations the email_type is NOT NULL, contrary to what our tables_current says
		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_addressbook','email_type',array(
			'type' => 'varchar',
			'precision' => '32',
			'default' => 'INTERNET'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('phpgw_addressbook','email_home_type',array(
			'type' => 'varchar',
			'precision' => '32',
			'default' => 'INTERNET'
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.021';
	}


	$test[] = '1.0.1.021';
	function phpgwapi_upgrade1_0_1_021()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_hooks','egw_hooks');
		$GLOBALS['egw_setup']->hooks_table = 'egw_hooks';

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.022';
	}


	$test[] = '1.0.1.022';
	function phpgwapi_upgrade1_0_1_022()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_preferences','egw_preferences');
		$GLOBALS['egw_setup']->prefs_table = 'egw_preferences';

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.023';
	}


	$test[] = '1.0.1.023';
	function phpgwapi_upgrade1_0_1_023()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_sessions','egw_sessions');
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sessions','session_dla',array(
			'type' => 'int',
			'precision' => '8',		// timestamps need to be 64bit since the 32bit overflow in 2003
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_sessions','session_logintime',array(
			'type' => 'int',
			'precision' => '8',		// timestamps need to be 64bit since the 32bit overflow in 2003
		));

		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_app_sessions','egw_app_sessions');
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_app_sessions','session_dla',array(
			'type' => 'int',
			'precision' => '8',		// timestamps need to be 64bit since the 32bit overflow in 2003
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.024';
	}


	$test[] = '1.0.1.024';
	function phpgwapi_upgrade1_0_1_024()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_access_log','egw_access_log');

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.025';
	}


	$test[] = '1.0.1.025';
	function phpgwapi_upgrade1_0_1_025()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_nextid','egw_nextid');

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.026';
	}


	$test[] = '1.0.1.026';
	function phpgwapi_upgrade1_0_1_026()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_history_log','egw_history_log');

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.027';
	}


	$test[] = '1.0.1.027';
	function phpgwapi_upgrade1_0_1_027()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_interserv','egw_interserv');

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.028';
	}


	$test[] = '1.0.1.028';
	function phpgwapi_upgrade1_0_1_028()
	{
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_addressbook','egw_addressbook');
		$GLOBALS['egw_setup']->oProc->RenameTable('phpgw_addressbook_extra','egw_addressbook_extra');

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.0.1.029';
	}
	
	
	$test[] = '1.0.1.029';
	function phpgwapi_upgrade1_0_1_029()
	{
		// convert all positive group id's to negative ones, since 1.2.002 except the account_id itself
		// this allows duplicate id for users and groups in ldap
		// This update include the next 2 updates and goes direct to version 1.2.002!
		$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_value',array(
			'config_name' => 'account_repository',
			'config_app'  => 'phpgwapi',
		),__LINE__,__FILE__);
		
		if($GLOBALS['egw_setup']->db->next_record() && $GLOBALS['egw_setup']->db->f('config_value') == 'ldap')
		{
			$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->acl_table,'DISTINCT acl_location',array(
				'acl_appname' => 'phpgw_group',
				'acl_location > 0',
			),__LINE__,__FILE__);
		}
		else
		{
			$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->accounts_table,'account_id',array(
				'account_type' => 'g',
				'account_id > 0',
			),__LINE__,__FILE__);
		}
		$groupIDs = array();
		while($GLOBALS['egw_setup']->db->next_record())
		{
			$groupIDs[] = $GLOBALS['egw_setup']->db->f(0);
		}
		$tables = array();
		foreach($GLOBALS['egw_setup']->db->table_names() as $data)
		{
			$tables[] = $data['table_name'];
		}
		foreach(array(
			array('egw_acl','acl_location'),
			array('egw_acl','acl_account'),
			array('egw_accounts','account_primary_group',"account_type='u'"),
			array('egw_cal_user','cal_user_id',"cal_user_type='u'"),
			// adding the old name, as the rename might have not been done (api upgrades run befor app ones)
			array('phpgw_cal_user','cal_user_id',"cal_user_type='u'"),
			array('egw_wiki_pages','wiki_readable',true),
			array('egw_wiki_pages','wiki_writable',true),
			// adding the old name, as the rename might have not been done (api upgrades run befor app ones)
			array('phpgw_wiki_pages','wiki_readable',true),
			array('phpgw_wiki_pages','wiki_writable',true),
			array('phpgw_wiki_pages','readable',true),
			array('phpgw_wiki_pages','writable',true),
			array('egw_vfs','vfs_owner_id'),
			array('egw_vfs','vfs_createdby_id'),
		) as $data)
		{
			$where = false;
			list($table,$col,$where) = $data;
			
			if (!in_array($table,$tables)) continue;	// app is not installed

			if ($col == 'acl_location')	// varchar not int!
			{
				$set = $col.'='.$GLOBALS['egw_setup']->db->concat("'-'",$col);
				$in = "$col IN ('".implode("','",$groupIDs)."')";
			}
			else
			{
				$set = "$col=-$col";
				$in = "$col IN (".implode(',',$groupIDs).')';				
			}
			if ($where === true)
			{
				$in = '';
				$where = '1=1';
			}
			$query = "UPDATE $table SET $set WHERE $in".($in && $where ? ' AND ' : '').$where;
			//echo "<p>$query</p>\n";
			$GLOBALS['egw_setup']->db->query($query,__LINE__,__FILE__);
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.002';
	}
	
	
	$test[] = '1.2';
	function phpgwapi_upgrade1_2()
	{
		// groupid's in egw_vfs.{owner|createdby}_id were not converted
		$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->config_table,'config_value',array(
			'config_name' => 'account_repository',
			'config_app'  => 'phpgwapi',
		),__LINE__,__FILE__);
		
		if($GLOBALS['egw_setup']->db->next_record() && $GLOBALS['egw_setup']->db->f('config_value') == 'ldap')
		{
			$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->acl_table,'DISTINCT acl_location',array(
				'acl_appname' => 'phpgw_group',
			),__LINE__,__FILE__);
		}
		else
		{
			$GLOBALS['egw_setup']->db->select($GLOBALS['egw_setup']->accounts_table,'account_id',array(
				'account_type' => 'g',
			),__LINE__,__FILE__);
		}
		$groupIDs = array();
		while($GLOBALS['egw_setup']->db->next_record())
		{
			$groupIDs[] = abs($GLOBALS['egw_setup']->db->f(0));
		}
		$tables = array();
		foreach($GLOBALS['egw_setup']->db->table_names() as $data)
		{
			$tables[] = $data['table_name'];
		}
		foreach(array(
			array('egw_vfs','vfs_owner_id'),
			array('egw_vfs','vfs_createdby_id'),
		) as $data)
		{
			$where = false;
			list($table,$col,$where) = $data;
			
			if (!in_array($table,$tables)) continue;	// app is not installed

			if ($col == 'acl_location')	// varchar not int!
			{
				$set = $col.'='.$GLOBALS['egw_setup']->db->concat("'-'",$col);
				$in = "$col IN ('".implode("','",$groupIDs)."')";
			}
			else
			{
				$set = "$col=-$col";
				$in = "$col IN (".implode(',',$groupIDs).')';				
			}
			if ($where === true)
			{
				$in = '';
				$where = '1=1';
			}
			$query = "UPDATE $table SET $set WHERE $in".($in && $where ? ' AND ' : '').$where;
			//echo "<p>$query</p>\n";
			$GLOBALS['egw_setup']->db->query($query,__LINE__,__FILE__);
		}
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.001';
	}
	
	$test[] = '1.2.001';
	function phpgwapi_upgrade1_2_001()
	{
		// convert groupid's in egw_accounts back to positive, as not all DBMS can deal with neg. id's
		if ($GLOBALS['egw_setup']->db->Type == 'mssql')
		{
			$GLOBALS['egw_setup']->db->query("SET identity_update egw_accounts ON",__LINE__,__FILE__);
		}
		$GLOBALS['egw_setup']->db->query("UPDATE egw_accounts SET account_id=-account_id WHERE account_type='g' AND account_id < 0",__LINE__,__FILE__);

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.002';
	}

	$test[] = '1.2.002';
	function phpgwapi_upgrade1_2_002()
	{
		// removed 1.2.002 update as it exceeded the max index of 1000Byte under MySQL, 1.2.004 does the right thing now
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.003';
	}

	$test[] = '1.2.003';
	function phpgwapi_upgrade1_2_003()
	{
		// change lenght of dir/name from 255/64 to 233/100, as 64 was definitly to short, 
		// sum has to be <= 333 (1000/3) because of the mysql index restrictions
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_vfs','vfs_directory',array(
			'type' => 'varchar',
			'precision' => '233'
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_vfs','vfs_name',array(
			'type' => 'varchar',
			'precision' => '100',
			'nullable' => False
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.004';
	}

	$test[] = '1.2.004';
	function phpgwapi_upgrade1_2_004()
	{
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_contentmap','map_guid',array(
			'type' => 'varchar',
			'precision' => '100',
			'nullable' => False
		));
		$GLOBALS['egw_setup']->oProc->AlterColumn('egw_contentmap','map_locuid',array(
			'type' => 'varchar',
			'precision' => '100',
			'nullable' => False
		));

		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.005';
	}

	$test[] = '1.2.005';
	function phpgwapi_upgrade1_2_005()
	{
		// new version number for 1.2RC7
		return $GLOBALS['setup_info']['phpgwapi']['currentver'] = '1.2.007';
	}
?>
