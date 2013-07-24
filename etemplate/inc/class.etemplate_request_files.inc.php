<?php
/**
 * eGroupWare - eTemplate request object storing the data in the filesystem
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2009 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id: class.etemplate_request_files.inc.php 26626 2009-03-16 13:43:16Z ralfbecker $
 */

/**
 * Class to represent the persitent information stored on the server for each eTemplate request
 *
 * The information is stored in the filesystem. The admin has to take care of regulary cleaning of
 * the used directory, as old requests get NOT deleted by this handler.
 *
 * To enable the use of this handler, you have to set (in etemplate/inc/class.etemplate_request.inc.php):
 *
 * 		etemplate_request::$request_class = 'etemplate_request_files';
 *
 * The request object should be instancated only via the factory method etemplate_request::read($id=null)
 *
 * $request = etemplate_request::read();
 *
 * // add request data
 *
 * $id = $request->id();
 *
 * b) open or modify an existing request:
 *
 * if (!($request = etemplate_request::read($id)))
 * {
 * 		// request not found
 * }
 *
 * Ajax requests can use this object to open the original request by using the id, they have to transmitt back,
 * and register further variables, modify the registered ones or delete them AND then update the id, if it changed:
 *
 *	if (($new_id = $request->id()) != $id)
 *	{
 *		$response->addAssign('etemplate_exec_id','value',$new_id);
 *	}
 *
 * For an example look in link_widget::ajax_search()
 */
class etemplate_request_files extends etemplate_request
{
	/**
	 * request id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Name of the directory to store the request data, by default $GLOBALS['egw_info']['server']['temp_dir']
	 *
	 * @var string
	 */
	static public $directory;

	/**
	 * Private constructor to force the instancation of this class only via it's static factory method read
	 *
	 * @param array $id
	 */
	private function __construct($id=null)
	{
		if (is_null(self::$directory))
		{
			self::$directory = $GLOBALS['egw_info']['server']['temp_dir'];
		}
		if (!$id) $id = self::request_id();

		$this->id = $id;
	}

	/**
	 * return the id of this request
	 *
	 * @return string
	 */
	public function id()
	{
		//error_log(__METHOD__."() id=$this->id");
		return $this->id;
	}

	/**
	 * Factory method to get a new request object or the one for an existing request
	 *
	 * @param string $id=null
	 * @return etemplate_request|boolean the object or false if $id is not found
	 */
	static function read($id=null)
	{
		$request = new etemplate_request_files($id);

		if (!is_null($id))
		{
			if (!file_exists($filename = self::$directory.'/'.$id) || !is_readable($filename))
			{
				error_log("Error opening '$filename' to read the etemplate request data!");
				return false;
			}
			$request->data = unserialize(file_get_contents($filename));
			if ($request->data === false) error_log("Error unserializing '$filename' to read the etemplate request data!");
		}
		//error_log(__METHOD__."(id=$id");
		return $request;
	}

	/**
	 * creates a new request-id via microtime()
	 *
	 * @return string
	 */
	static function request_id()
	{
		do
		{
			$id = uniqid('etemplate_'.$GLOBALS['egw_info']['flags']['currentapp'].'_',true);
		}
		while (file_exists(self::$directory.'/'.$id));

		return $id;
	}

	/**
	 * saves content,readonlys,template-keys, ... via eGW's appsession function
	 *
	 * As a user may open several windows with the same content/template wie generate a location-id from microtime
	 * which is used as location for request to descriminate between the different windows. This location-id
	 * is then saved as a hidden-var in the form. The above mentions session-id has nothing to do / is different
	 * from the session-id which is constant for all windows opened in one session.
	 */
	function __destruct()
	{
		if (!file_put_contents($filename = self::$directory.'/'.$this->id,serialize($this->data)))
		{
			error_log("Error opening '$filename' to store the etemplate request data!");
		}
	}
}