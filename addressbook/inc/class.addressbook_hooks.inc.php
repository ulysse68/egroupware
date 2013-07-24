<?php
/**
 * Addressbook - admin, preferences and sidebox-menus and other hooks
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2006-9 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.addressbook_hooks.inc.php 36326 2011-08-26 10:14:17Z ralfbecker $
 */

/**
 * Class containing admin, preferences and sidebox-menus and other hooks
 */
class addressbook_hooks
{
	/**
	 * hooks to build projectmanager's sidebox-menu plus the admin and preferences sections
	 *
	 * @param string/array $args hook args
	 */
	static function all_hooks($args)
	{
		$appname = 'addressbook';
		$location = is_array($args) ? $args['location'] : $args;
		//echo "<p>contacts_admin_prefs::all_hooks(".print_r($args,True).") appname='$appname', location='$location'</p>\n";

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'Add'             => "javascript:egw_openWindowCentered2('".
					egw::link('/index.php',array('menuaction' => 'addressbook.addressbook_ui.edit'),false).
					"','_blank',870,480,'yes')",
				'Advanced search' => "javascript:egw_openWindowCentered2('".
					egw::link('/index.php',array('menuaction' => 'addressbook.addressbook_ui.search'),false).
					"','_blank',870,480,'yes')",
				'CSV-Import'      => egw::link('/addressbook/csv_import.php')
			);
			display_sidebox($appname,lang('Addressbook menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
				'Grant Access'    => egw::link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
				'Edit Categories' => egw::link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($GLOBALS['egw_info']['server']['contact_repository'] == 'ldap' || $GLOBALS['egw_info']['server']['deny_user_grants_access'])
			{
				unset($file['Grant Access']);
			}
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site configuration' => egw::link('/index.php',array(
					'menuaction' => 'admin.uiconfig.index',
					'appname'    => $appname,
				)),
				'Global Categories'  => egw::link('/index.php',array(
					'menuaction' => 'admin.uicategories.index',
					'appname'    => $appname,
					'global_cats'=> True,
				)),
			);
			// custom fields are not availible in LDAP
			if ($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap')
			{
				$file['Custom fields'] = egw::link('/index.php',array(
					'menuaction' => 'admin.customfields.edit',
					'appname'    => $appname,
					'use_private'=> 1,
				));
			}
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	static function settings($hook_data)
	{
		$settings = array();
		$settings['add_default'] = array(
			'type'   => 'select',
			'label'  => 'Default addressbook for adding contacts',
			'name'   => 'add_default',
			'help'   => 'Which addressbook should be selected when adding a contact AND you have no add rights to the current addressbook.',
			'values' => !$hook_data['setup'] ? ExecMethod('addressbook.addressbook_ui.get_addressbooks',EGW_ACL_ADD) : array(),
			'xmlrpc' => True,
			'admin'  => False,
		);
		if ($GLOBALS['egw_info']['server']['hide_birthdays'] != 'yes')	// calendar config
		{
			$settings['mainscreen_showbirthdays'] = array(
				'type'   => 'select',
				'label'  => 'Show birthday reminders on main screen',
				'name'   => 'mainscreen_showbirthdays',
				'help'   => 'Displays a remider for birthdays on the startpage (page you get when you enter eGroupWare or click on the homepage icon).',
				'values' => array(
					0 => lang('No'),
					1 => lang('Yes, for today and tomorrow'),
					3 => lang('Yes, for the next three days'),
					7 => lang('Yes, for the next week'),
					14=> lang('Yes, for the next two weeks'),
				),
				'xmlrpc' => True,
				'admin'  => False,
				'default'=> 3,
			);
		}
		$settings['no_auto_hide'] = array(
			'type'   => 'check',
			'label'  => 'Don\'t hide empty columns',
			'name'   => 'no_auto_hide',
			'help'   => 'Should the columns photo and home address always be displayed, even if they are empty.',
			'xmlrpc' => True,
			'admin'  => false,
			'forced' => false,
		);
		// CSV Export
		$settings['csv_fields'] = array(
			'type'   => 'select',
			'label'  => 'Fields for the CSV export',
			'name'   => 'csv_fields',
			'values' => array(
				'all'      => lang('All'),
				'business' => lang('Business address'),
				'home'     => lang('Home address'),
			),
			'help'   => 'Which fields should be exported. All means every field stored in the addressbook incl. the custom fields. The business or home address only contains name, company and the selected address.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'business',
		);
		$settings['csv_charset'] = array(
			'type'   => 'select',
			'label'  => 'Charset for the CSV export',
			'name'   => 'csv_charset',
			'values' => translation::get_installed_charsets(),
			'help'   => 'Which charset should be used for the CSV export. The system default is the charset of this eGroupWare installation.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'iso-8859-1',
		);

		$selectCharSet = array(
			'utf-8'			=> 'UTF-8',
			'iso-8859-1'	=> 'ISO-8859-1',
		);

		$settings['vcard_charset'] = array(
			'type'   => 'select',
			'label'  => 'Charset for the vCard export',
			'name'   => 'vcard_charset',
			'values' => $selectCharSet,
			'help'   => 'Which charset should be used for the vCard export.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'utf-8',
		);

		if ($GLOBALS['egw_info']['server']['contact_repository'] != 'ldap')
		{
			$settings['private_addressbook'] = array(
				'type'   => 'check',
				'label'  => 'Enable an extra private addressbook',
				'name'   => 'private_addressbook',
				'help'   => 'Do you want a private addressbook, which can not be viewed by users, you grant access to your personal addressbook?',
				'xmlrpc' => True,
				'admin'  => False,
				'forced' => false,
			);
		}
		$settings['link_title'] = array(
			'type'   => 'select',
			'label'  => 'Link title for contacts show',
			'name'   => 'link_title',
			'values' => array(
				'n_fileas' => lang('own sorting').' ('.lang('default').': '.lang('Company').': '.lang('lastname').', '.lang('firstname').')',
				'org_name: n_family, n_given' => lang('Company').': '.lang('lastname').', '.lang('firstname'),
				'org_name, org_unit: n_family, n_given' => lang('Company').', '.lang('Department').': '.lang('lastname').', '.lang('firstname'),
				'org_name, adr_one_locality: n_family, n_given' => lang('Company').', '.lang('City').': '.lang('lastname').', '.lang('firstname'),
				'org_name, org_unit, adr_one_locality: n_family, n_given' => lang('Company').', '.lang('Department').', '.lang('City').': '.lang('lastname').', '.lang('firstname'),
			),
			'help'   => 'What should links to the addressbook display in other applications. Empty values will be left out. You need to log in anew, if you change this setting!',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'org_name: n_family, n_given',
		);
		$settings['addr_format'] = array(
			'type'   => 'select',
			'label'  => 'Default address format',
			'name'   => 'addr_format',
			'values' => array(
				'postcode_city' => lang('zip code').' '.lang('City'),
				'city_state_postcode' => lang('City').' '.lang('State').' '.lang('zip code'),
			),
			'help'   => 'Which address format should the addressbook use for countries it does not know the address format. If the address format of a country is known, it uses it independent of this setting.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'postcode_city',
		);
		$settings['fileas_default'] = array(
			'type'   => 'select',
			'label'  => 'Default file as format',
			'name'   => 'fileas_default',
			'values' => ExecMethod('addressbook.addressbook_bo.fileas_options'),
			'help'   => 'Default format for fileas, eg. for new entries.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> 'org_name: n_family, n_given',
		);
		$settings['hide_accounts'] = array(
			'type'   => 'check',
			'label'  => 'Hide accounts from addressbook',
			'name'   => 'hide_accounts',
			'help'   => 'Hides accounts completly from the adressbook.',
			'xmlrpc' => True,
			'admin'  => false,
			'default'=> false,
		);
		$settings['distributionListPreferredMail'] = array(
			'type'   => 'select',
			'label'  => 'Preferred email address to use in distribution lists',
			'name'   => 'distributionListPreferredMail',
			'values' => array(
				'email'	=> lang("Work email if given, else home email"),
				'email_home'	=> lang("Home email if given, else work email"),
			),
			'help'   => 'Defines which email address (business or home) to use as the preferred one for distribution lists in mail.',
			'xmlrpc' => True,
			'admin'  => False,
			'forced'=> 'email',
		);
		if ($GLOBALS['egw_info']['user']['apps']['filemanager'])
		{
			$link = egw::link('/index.php','menuaction=addressbook.addressbook_merge.show_replacements');

			$settings['default_document'] = array(
				'type'   => 'input',
				'size'   => 60,
				'label'  => 'Default document to insert contacts',
				'name'   => 'default_document',
				'help'   => lang('If you specify a document (full vfs path) here, addressbook displays an extra document icon for each address. That icon allows to download the specified document with the contact data inserted.').' '.
					lang('The document can contain placeholder like $$n_fn$$, to be replaced with the contact data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>').' '.
					lang('At the moment the following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
			$settings['document_dir'] = array(
				'type'   => 'input',
				'size'   => 60,
				'label'  => 'Directory with documents to insert contacts',
				'name'   => 'document_dir',
				'help'   => lang('If you specify a directory (full vfs path) here, addressbook displays an action for each document. That action allows to download the specified document with the contact data inserted.').' '.
					lang('The document can contain placeholder like $$n_fn$$, to be replaced with the contact data (%1full list of placeholder names%2).','<a href="'.$link.'" target="_blank">','</a>').' '.
					lang('At the moment the following document-types are supported:'). implode(',',bo_merge::get_file_extensions()),
				'run_lang' => false,
				'xmlrpc' => True,
				'admin'  => False,
			);
		}
		return $settings;
	}

	/**
	 * add an Addressbook tab to Admin >> Edit user
	 */
	static function edit_user()
	{
		global $menuData;

		$menuData[] = array(
			'description' => 'Addressbook',
			'url'         => '/index.php',
			'extradata'   => 'menuaction=addressbook.addressbook_ui.edit',
			'options'     => "onclick=\"egw_openWindowCentered2(this,'_blank',870,440,'yes'); return false;\"".
				' title="'.htmlspecialchars(lang('Edit extra account-data in the addressbook')).'"',
		);
	}

	/**
	 * Hook called by link-class to include calendar in the appregistry of the linkage
	 *
	 * @param array/string $location location and other parameters (not used)
	 * @return array with method-names
	 */
	static function search_link($location)
	{
		return array(
			'query' => 'addressbook.addressbook_bo.link_query',
			'title' => 'addressbook.addressbook_bo.link_title',
			'titles' => 'addressbook.addressbook_bo.link_titles',
			'view' => array(
				'menuaction' => 'addressbook.addressbook_ui.view'
			),
			'view_id' => 'contact_id',
			'add' => array(
				'menuaction' => 'addressbook.addressbook_ui.edit'
			),
			'add_app'    => 'link_app',
			'add_id'     => 'link_id',
			'add_popup'  => '870x440',
			'file_access'=> 'addressbook.addressbook_bo.file_access',
			'default_types' => array('n' => array('name' => 'contact', 'options' => array('icon' => 'navbar.png','template' => 'addressbook.edit'))),
		);
	}

	/**
	 * Register contacts as calendar resources (items which can be sheduled by the calendar)
	 *
	 * @param array $args hook-params (not used)
	 * @return array
	 */
	static function calendar_resources($args)
	{
		return array(
			'type' => 'c',// one char type-identifiy for this resources
			'info' => 'addressbook.addressbook_bo.calendar_info',// info method, returns array with id, type & name for a given id
		);
	}

	/**
	 * Register addressbook for group-acl
	 *
	 * @param array $args hook-params (not used)
	 * @return boolean|string true=standard group acl link, of string with link
	 */
	static function group_acl($args)
	{
		// addressbook uses group-acl, only if contacts-backend is NOT LDAP, as the ACL can not be modified there
		return $GLOBALS['egw_info']['server']['contact_repository'] != 'ldap';
	}
}
