<?php
/**
 * ajaxpreferences
 *
 * @package preferences
 * @copyright Lingewoud B.V.
 * @link http://www.egroupware.org
 * @author Pim Snel <pim-AT-lingewoud-DOT-nl>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.ajaxpreferences.inc.php 28383 2009-11-18 20:00:26Z ralfbecker $
 */

/**
 * Script which is called to store prefs using AJAX
 */
class ajaxpreferences
{
	/**
	 * storeEGWPref
	 *
	 * @param mixed $repository egroupware preferences repository
	 * @param mixed $key key to preference
	 * @param mixed $value new value
	 * @access public
	 * @return mixed returns null when no erro, else return error message.
	 */
	function storeEGWPref($repository,$key,$value)
	{
		$response = new xajaxResponse();
		$GLOBALS['egw']->preferences->read_repository();
		$GLOBALS['egw']->preferences->change($repository,$key,$value);
		$GLOBALS['egw']->preferences->save_repository(True);
		//$response->addAlert(__METHOD__."('$repository','$key','$value')");
		return $response->getXML();
	}
}
