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

  /* $Id: tables_update_0_9_9.inc.php 16306 2004-08-09 12:40:51Z reinerj $ */

	$test[] = '0.9.1';
	function phpgwapi_upgrade0_9_1()
	{
		global $phpgw_info, $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('access_log', 'lo', array('type' => 'varchar', 'precision' => 255));

		$phpgw_setup->oProc->query("update lang set lang='da' where lang='dk'");
		$phpgw_setup->oProc->query("update lang set lang='ko' where lang='kr'");

		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_company_id', array('type' => 'int', 'precision' => 4));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_title', array('type' => 'varchar', 'precision' => 60));
		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_address2', array('type' => 'varchar', 'precision' => 60));

		$phpgw_setup->oProc->query("update preferences set preference_name='da' where preference_name='dk'");
		$phpgw_setup->oProc->query("update preferences set preference_name='ko' where preference_name='kr'");

		//install weather support
		$phpgw_setup->oProc->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('weather', 'Weather', 1, 12, NULL, '".$phpgw_info['server']['version']."')");
		$phpgw_setup->oProc->query("INSERT INTO lang (message_id, app_name, lang, content) VALUES( 'weather','Weather','en','weather')");

		$setup_info['phpgwapi']['currentver'] = '0.9.2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	function phpgwapi_v0_9_2to0_9_3update_owner($table, $field)
	{
		global $phpgw_setup;

		$phpgw_setup->oProc->query("select distinct($field) from $table");
		if ($phpgw_setup->oProc->num_rows()) {
			while ($phpgw_setup->oProc->next_record())
			{
				$owner[count($owner)] = $phpgw_setup->oProc->f($field);
			}
			for($i=0;$i<count($owner);$i++)
			{
				$phpgw_setup->oProc->query("select account_id from accounts where account_lid='".$owner[$i]."'");
				$phpgw_setup->oProc->next_record();
				$phpgw_setup->oProc->query("update $table set $field=".$phpgw_setup->oProc->f("account_id")." where $field='".$owner[$i]."'");
			}
		}

		$phpgw_setup->oProc->AlterColumn($table, $field, array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0));
	}

	$test[] = '0.9.2';
	function phpgwapi_upgrade0_9_2()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.3pre1';
	function phpgwapi_upgrade0_9_3pre1()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre2';
	function phpgwapi_upgrade0_9_3pre2()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre3';
	function phpgwapi_upgrade0_9_3pre3()
	{
		global $setup_info;

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre4';
	function phpgwapi_upgrade0_9_3pre4()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn("config", "config_name", array("type" => "varchar", "precision" => 255, "nullable" => false));

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre5';
	function phpgwapi_upgrade0_9_3pre5()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'categories', array(
				'fd' => array(
					'cat_id' => array('type' => 'auto', 'nullable' => false),
					'account_id' => array('type' => 'int', 'precision' => 4, 'nullable' => false, 'default' => 0),
					'app_name' => array('type' => 'varchar', 'precision' => 25, 'nullable' => false),
					'cat_name' => array('type' => 'varchar', 'precision' => 150, 'nullable' => false),
					'cat_description' => array('type' => 'text', 'nullable' => false)
				),
				'pk' => array('cat_id'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre6';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre6';
	function phpgwapi_upgrade0_9_3pre6()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query("insert into applications (app_name, app_title, app_enabled, app_order, app_tables, app_version) values ('transy', 'Translation Management', 0, 13, NULL, '".$setup_info['phpgwapi']['version']."')");

		$phpgw_setup->oProc->AddColumn('addressbook', 'ab_url', array('type' => 'varchar', 'precision' => 255));

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre7';
	function phpgwapi_upgrade0_9_3pre7()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable('languages', array(
				'fd' => array(
					'lang_id' =>   array('type' => 'varchar', 'precision' => 2, 'nullable' => false),
					'lang_name' => array('type' => 'varchar', 'precision' => 50, 'nullable' => false),
					'available' => array('type' => 'char', 'precision' => 3, 'nullable' => false, 'default' => 'No')
				),
				'pk' => array('lang_id'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AA','Afar','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AB','Abkhazian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AF','Afrikaans','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AM','Amharic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AR','Arabic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AS','Assamese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AY','Aymara','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('AZ','Azerbaijani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BA','Bashkir','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BE','Byelorussian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BG','Bulgarian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BH','Bihari','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BI','Bislama','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BN','Bengali / Bangla','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BO','Tibetan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('BR','Breton','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CA','Catalan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CO','Corsican','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CS','Czech','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('CY','Welsh','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DA','Danish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DE','German','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('DZ','Bhutani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EL','Greek','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EN','English / US','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EO','Esperanto','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ES','Spanish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ET','Estonian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('EU','Basque','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FA','Persian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FI','Finnish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FJ','Fiji','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FO','Faeroese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FR','French','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('FY','Frisian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GA','Irish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GD','Gaelic / Scots Gaelic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GL','Galician','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GN','Guarani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('GU','Gujarati','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HA','Hausa','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HI','Hindi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HR','Croatian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HU','Hungarian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('HY','Armenian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IA','Interlingua','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IE','Interlingue','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IK','Inupiak','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IN','Indonesian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IS','Icelandic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IT','Italian','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('IW','Hebrew','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JA','Japanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JI','Yiddish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('JW','Javanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KA','Georgian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KK','Kazakh','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KL','Greenlandic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KM','Cambodian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KN','Kannada','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KO','Korean','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KS','Kashmiri','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KU','Kurdish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('KY','Kirghiz','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LA','Latin','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LN','Lingala','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LO','Laothian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LT','Lithuanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('LV','Latvian / Lettish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MG','Malagasy','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MI','Maori','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MK','Macedonian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ML','Malayalam','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MN','Mongolian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MO','Moldavian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MR','Marathi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MS','Malay','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MT','Maltese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('MY','Burmese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NA','Nauru','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NE','Nepali','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NL','Dutch','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('NO','Norwegian','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OC','Occitan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OM','Oromo / Afan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('OR','Oriya','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PA','Punjabi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PL','Polish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PS','Pashto / Pushto','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('PT','Portuguese','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('QU','Quechua','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RM','Rhaeto-Romance','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RN','Kirundi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RO','Romanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RU','Russian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('RW','Kinyarwanda','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SA','Sanskrit','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SD','Sindhi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SG','Sangro','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SH','Serbo-Croatian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SI','Singhalese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SK','Slovak','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SL','Slovenian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SM','Samoan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SN','Shona','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SO','Somali','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SQ','Albanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SR','Serbian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SS','Siswati','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ST','Sesotho','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SU','Sudanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SV','Swedish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('SW','Swahili','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TA','Tamil','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TE','Tegulu','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TG','Tajik','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TH','Thai','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TI','Tigrinya','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TK','Turkmen','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TL','Tagalog','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TN','Setswana','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TO','Tonga','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TR','Turkish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TS','Tsonga','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TT','Tatar','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('TW','Twi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UK','Ukrainian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UR','Urdu','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('UZ','Uzbek','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('VI','Vietnamese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('VO','Volapuk','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('WO','Wolof','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('XH','Xhosa','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('YO','Yoruba','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZH','Chinese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ZU','Zulu','No')");

		$setup_info['phpgwapi']['currentver'] = '0.9.3pre8';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.3pre8';
	function phpgwapi_upgrade0_9_3pre8()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.3pre9';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.3pre9';
	function phpgwapi_upgrade0_9_3pre9()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.3pre10';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.3pre10';
	function phpgwapi_upgrade0_9_3pre10()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.3';
	function phpgwapi_upgrade0_9_3()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.4pre1';
	function phpgwapi_upgrade0_9_4pre1()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.4pre2';
	function phpgwapi_upgrade0_9_4pre2()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.4pre3';
	function phpgwapi_upgrade0_9_4pre3()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.4pre4';
	function phpgwapi_upgrade0_9_4pre4()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->AlterColumn('sessions', 'session_lid', array('type' => 'varchar', 'precision' => 255));

		$setup_info['phpgwapi']['currentver'] = '0.9.4pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.4pre5';
	function phpgwapi_upgrade0_9_4pre5()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.4';
	function phpgwapi_upgrade0_9_4()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query('delete from languages');
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('aa','Afar','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ab','Abkhazian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('af','Afrikaans','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('am','Amharic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ar','Arabic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('as','Assamese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ay','Aymara','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('az','Azerbaijani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ba','Bashkir','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('be','Byelorussian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bg','Bulgarian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bh','Bihari','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bi','Bislama','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bn','Bengali / Bangla','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('bo','Tibetan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('br','Breton','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ca','Catalan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('co','Corsican','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('cs','Czech','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('cy','Welsh','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('da','Danish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('de','German','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('dz','Bhutani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('el','Greek','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('en','English / US','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('eo','Esperanto','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('es','Spanish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('et','Estonian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('eu','Basque','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fa','Persian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fi','Finnish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fj','Fiji','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fo','Faeroese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fr','French','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('fy','Frisian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ga','Irish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gd','Gaelic / Scots Gaelic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gl','Galician','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gn','Guarani','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('gu','Gujarati','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ha','Hausa','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hi','Hindi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hr','Croatian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hu','Hungarian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('hy','Armenian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ia','Interlingua','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ie','Interlingue','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ik','Inupiak','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('in','Indonesian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('is','Icelandic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('it','Italian','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('iw','Hebrew','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ja','Japanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ji','Yiddish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('jw','Javanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ka','Georgian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kk','Kazakh','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kl','Greenlandic','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('km','Cambodian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('kn','Kannada','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ko','Korean','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ks','Kashmiri','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ku','Kurdish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ky','Kirghiz','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('la','Latin','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ln','Lingala','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lo','Laothian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lt','Lithuanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('lv','Latvian / Lettish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mg','Malagasy','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mi','Maori','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mk','Macedonian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ml','Malayalam','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mn','Mongolian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mo','Moldavian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mr','Marathi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ms','Malay','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('mt','Maltese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('my','Burmese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('na','Nauru','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ne','Nepali','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('nl','Dutch','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('no','Norwegian','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('oc','Occitan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('om','Oromo / Afan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('or','Oriya','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pa','Punjabi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pl','Polish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ps','Pashto / Pushto','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('pt','Portuguese','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('qu','Quechua','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rm','Rhaeto-Romance','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rn','Kirundi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ro','Romanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ru','Russian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('rw','Kinyarwanda','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sa','Sanskrit','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sd','Sindhi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sg','Sangro','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sh','Serbo-Croatian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('si','Singhalese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sk','Slovak','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sl','Slovenian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sm','Samoan','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sn','Shona','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('so','Somali','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sq','Albanian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sr','Serbian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ss','Siswati','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('st','Sesotho','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('su','Sudanese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sv','Swedish','Yes')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('sw','Swahili','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ta','Tamil','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('te','Tegulu','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tg','Tajik','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('th','Thai','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ti','Tigrinya','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tk','Turkmen','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tl','Tagalog','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tn','Setswana','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('to','Tonga','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tr','Turkish','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ts','Tsonga','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tt','Tatar','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('tw','Twi','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('uk','Ukrainian','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('ur','Urdu','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('uz','Uzbek','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('vi','Vietnamese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('vo','Volapuk','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('wo','Wolof','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('xh','Xhosa','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('yo','Yoruba','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('zh','Chinese','No')");
		@$phpgw_setup->oProc->query("INSERT INTO languages (lang_id, lang_name, available) values ('zu','Zulu','No')");

		$setup_info['phpgwapi']['currentver'] = '0.9.5pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.5pre1';
	function phpgwapi_upgrade0_9_5pre1()
	{
		global $phpgw_info, $phpgw_setup;

		$phpgw_setup->oProc->DropTable('sessions');
		$phpgw_setup->oProc->CreateTable('phpgw_sessions', array(
				'fd' => array(
					'session_id' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
					'session_lid' => array('type' => 'varchar', 'precision' => 255),
					'session_pwd' => array('type' => 'varchar', 'precision' => 255),
					'session_ip' => array('type' => 'varchar', 'precision' => 255),
					'session_logintime' => array('type' => 'int', 'precision' => 4),
					'session_dla' => array('type' => 'int', 'precision' => 4)
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array('session_id')
			));

		$phpgw_setup->oProc->CreateTable('phpgw_acl', array(
				'fd' => array(
					'acl_appname' => array('type' => 'varchar', 'precision' => 50),
					'acl_location' => array('type' => 'varchar', 'precision' => 255),
					'acl_account' => array('type' => 'int', 'precision' => 4),
					'acl_account_type' => array('type' => 'char', 'precision' => 1),
					'acl_rights' => array('type' => 'int', 'precision' => 4)
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			));

		$phpgw_setup->oProc->DropTable('app_sessions');
		$phpgw_setup->oProc->CreateTable('phpgw_app_sessions', array(
				'fd' => array(
					'sessionid' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
					'loginid' => array('type' => 'varchar', 'precision' => 20),
					'app' => array('type' => 'varchar', 'precision' => 20),
					'content' => array('type' => 'text')
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			));

		$phpgw_setup->oProc->DropTable('access_log');
		$phpgw_setup->oProc->CreateTable('phpgw_access_log', array(
				'fd' => array(
					'sessionid' => array('type' => 'varchar', 'precision' => 255),
					'loginid' => array('type' => 'varchar', 'precision' => 30),
					'ip' => array('type' => 'varchar', 'precision' => 30),
					'li' => array('type' => 'int', 'precision' => 4),
					'lo' => array('type' => 'varchar', 'precision' => 255)
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			));
		
		$setup_info['phpgwapi']['currentver'] = '0.9.5pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.5pre2';
	function phpgwapi_upgrade0_9_5pre2()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.5';
	function phpgwapi_upgrade0_9_5()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.6';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.6';
	function phpgwapi_upgrade0_9_6()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.7pre1';
	function phpgwapi_upgrade0_9_7pre1()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
	$test[] = '0.9.7pre2';
	function phpgwapi_upgrade0_9_7pre2()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.7pre3';
	function phpgwapi_upgrade0_9_7pre3()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.7';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.7';
	function phpgwapi_upgrade0_9_7()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.8pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.8pre1';
	function phpgwapi_upgrade0_9_8pre1()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->query("SELECT * FROM preferences ORDER BY preference_owner");
		$t = array();
		while ($phpgw_setup->oProc->next_record())
		{
			$t[$phpgw_setup->oProc->f('preference_owner')][$phpgw_setup->oProc->f('preference_appname')][$phpgw_setup->oProc->f('preference_var')] = $phpgw_setup->oProc->f('preference_value');
		}

		$phpgw_setup->oProc->DropTable('preferences');
		$phpgw_setup->oProc->CreateTable('preferences', array(
				'fd' => array(
					'preference_owner' => array('type' => 'int', 'precision' => 4, 'nullable' => false),
					'preference_value' => array('type' => 'text')
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			));

		while ($tt = each($t))
		{
			$phpgw_setup->oProc->query("insert into preferences values ('$tt[0]','" . serialize($tt[1]) . "')");
		}

		$setup_info['phpgwapi']['currentver'] = '0.9.8pre2';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.8pre2';
	function phpgwapi_upgrade0_9_8pre2()
	{
		global $setup_info, $phpgw_setup;
		$setup_info['phpgwapi']['currentver'] = '0.9.8pre3';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.8pre3';
	function phpgwapi_upgrade0_9_8pre3()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->DropTable('phpgw_sessions');
		$phpgw_setup->oProc->CreateTable(
			'phpgw_sessions', array(
				'fd' => array(
					'session_id' => array('type' => 'varchar', 'precision' => 255, 'nullable' => false),
					'session_lid' => array('type' => 'varchar', 'precision' => 255),
					'session_ip' => array('type' => 'varchar', 'precision' => 255),
					'session_logintime' => array('type' => 'int', 'precision' => 4),
					'session_dla' => array('type' => 'int', 'precision' => 4),
					'session_info' => array('type' => 'text')
				),
				'pk' => array(),
				'ix' => array(),
				'fk' => array(),
				'uc' => array('session_id')
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.8pre4';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.8pre4';
	function phpgwapi_upgrade0_9_8pre4()
	{
		global $setup_info, $phpgw_setup;

		$phpgw_setup->oProc->CreateTable(
			'phpgw_hooks', array(
				'fd' => array(
					'hook_id' =>       array('type' => 'auto', 'nullable' => false),
					'hook_appname' =>  array('type' => 'varchar', 'precision' => 255),
					'hook_location' => array('type' => 'varchar', 'precision' => 255),
					'hook_filename' => array('type' => 'varchar', 'precision' => 255)
				),
				'pk' => array("hook_id"),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.8pre5';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.8pre5';
	function phpgwapi_upgrade0_9_8pre5()
	{
		global $setup_info, $phpgw_setup;

		// Since no applications are using it yet.  I am gonna drop it and create a new one.
		// This is becuase I never finished the classes
		$phpgw_setup->oProc->DropTable('categories');

		$phpgw_setup->oProc->CreateTable(
			'phpgw_categories', array(
				'fd' => array(
					'cat_id' =>      array('type' => 'auto', 'nullable' => false),
					'cat_parent' =>  array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
					'cat_owner' =>   array('type' => 'int', 'precision' => 4, 'default' => 0, 'nullable' => false),
					'cat_appname' => array('type' => 'varchar', 'precision'  => 50, 'nullable' => false),
					'cat_name' =>    array('type' => 'varchar', 'precision'  => 150, 'nullable' => false),
					'cat_description' => array('type' => 'varchar', 'precision'  => 255, 'nullable' => false),
					'cat_data' =>    array('type' => 'text')
				),
				'pk' => array('cat_id'),
				'ix' => array(),
				'fk' => array(),
				'uc' => array()
			)
		);

		$setup_info['phpgwapi']['currentver'] = '0.9.9pre1';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}

	$test[] = '0.9.9pre1';
	function phpgwapi_upgrade0_9_9pre1()
	{
		global $setup_info;
		$setup_info['phpgwapi']['currentver'] = '0.9.9';
		return $setup_info['phpgwapi']['currentver'];
		//return True;
	}
?>
