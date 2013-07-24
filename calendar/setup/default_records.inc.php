<?php
/**
 * eGroupWare - Calendar setup
 *
 * @link http://www.egroupware.org
 * @package calendar
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: default_records.inc.php 36856 2011-10-07 05:10:01Z ralfbecker $
 */

// enable auto-loading of holidays from localhost by default
foreach(array(
	'auto_load_holidays' => 'True',
	'holidays_url_path'  => 'localhost',
) as $name => $value)
{
	$oProc->insert($GLOBALS['egw_setup']->config_table,array(
		'config_value' => $value,
	),array(
		'config_app' => 'phpgwapi',
		'config_name' => $name,
	),__FILE__,__LINE__);
}

// import timezone data from sqlite database
try
{
	calendar_timezones::import_sqlite();
	calendar_timezones::import_tz_aliases();
}
// catch broken sqlite extension exception and output message, as user can't do anything about it
// all other exceptions are fatal, to get user to fix them!
catch (egw_exception_wrong_userinput $e)	// all other exceptions are fatal
{
	_egw_log_exception($e);
	echo '<p>'.$e->getMessage()."</p>\n";
}