<?php
/**
 * eGroupWare generalized SQL Storage Object Version 2 - requires PHP5.1.2+!!!
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2007/8 by RalfBecker@outdoor-training.de
 * @version $Id: class.so_sql2.inc.php 25508 2008-05-26 08:32:27Z ralfbecker $
 */

/**
 * generalized SQL Storage Object
 *
 * the class can be used in following ways:
 * 1) by calling the constructor with an app and table-name or
 * 2) by setting the following documented class-vars in a class derifed from this one
 * Of cause can you derife the class and call the constructor with params.
 *
 * The so_sql2 class uses a privat $data array and __get and __set methods to set its data.
 * Please note:
 * You have to explicitly declare other object-properties of derived classes, which should NOT
 * be handled by that mechanism!
 *
 * @package etemplate
 * @subpackage api
 * @author RalfBecker-AT-outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */
class so_sql2 extends so_sql
{
	/**
	 * Private array containing all the object-data
	 *
	 * Colides with the original definition in so_sql and I dont want to change it there at the moment.
	 *
	 * @var array
	 */
	//private $data;

	/**
	 * constructor of the class
	 *
	 * NEED to be called from the constructor of the derived class !!!
	 *
	 * @param string $app should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param string $table should be set if table-defs to be read from <app>/setup/tables_current.inc.php
	 * @param object/db $db database object, if not the one in $GLOBALS['egw']->db should be used, eg. for an other database
	 * @param string $colum_prefix='' column prefix to automatic remove from the column-name, if the column name starts with it
	 * @param boolean $no_clone=false can we avoid to clone the db-object, default no
	 * 	new code using appnames and foreach(select(...,$app) can set it to avoid an extra instance of the db object
	 *
	 * @return so_sql2
	 */
	function __construct($app='',$table='',$db=null,$column_prefix='',$no_clone=false)
	{
		parent::__construct($app,$table,$db,$column_prefix,$no_clone);
	}

	/**
	 * php4 constructor
	 *
	 * @deprecated use __construct
	 */
	function so_sql2($app='',$table='',$db=null,$column_prefix='',$no_clone=false)
	{
		self::__construct($app,$table,$db,$column_prefix,$no_clone);
	}

	/**
	 * magic method to read a property from $this->data
	 *
	 * The special property 'id' always refers to the auto-increment id of the object, independent of its name.
	 *
	 * @param string $property
	 * @return mixed
	 */
	function __get($property)
	{
		switch($property)
		{
			case 'id':
				$property = $this->autoinc_id;
				break;
		}
		if (in_array($property,$this->db_cols) || in_array($property,$this->non_db_cols))
		{
			return $this->data[$property];
		}
	}

	/**
	 * magic method to set a property in $this->data
	 *
	 * The special property 'id' always refers to the auto-increment id of the object, independent of its name.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	function __set($property,$value)
	{
		switch($property)
		{
			case 'id':
				$property = $this->autoinc_id;
				break;
		}
		if (in_array($property,$this->db_cols) || in_array($property,$this->non_db_cols))
		{
			$this->data[$property] = $value;
		}
	}

	/**
	 * Return the whole object-data as array, it's a cast of the object to an array
	 *
	 * @return array
	 */
	function as_array()
	{
		return $this->data;
	}
}
