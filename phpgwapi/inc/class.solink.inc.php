<?php
/**
 * API - Interapplicaton links BO layer
 *
 * Links have two ends each pointing to an entry, each entry is a double:
 * 	 - app   app-name or directory-name of an egw application, eg. 'infolog'
 * 	 - id    this is the id, eg. an integer or a tupple like '0:INBOX:1234'
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2001-2008 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage link
 * @version $Id: class.solink.inc.php 38771 2012-04-04 06:21:22Z ralfbecker $
 */

/**
 * generalized linking between entries of eGroupware apps - SO layer
 *
 * All vars passed to this class get correct escaped to prevent query insertion.
 *
 * All methods are now static!
 */
class solink
{
	/**
	 * Name of the links table
	 */
	const TABLE = 'egw_links';
	/**
	 * Turns on debug-messages
	 */
	const DEBUG = false;
	/**
	 * Reference to the global db-class
	 *
	 * @var egw_db
	 */
	private static $db;
	/**
	 * Reference to current user from $GLOBALS['egw_info']['user']['account_id']
	 *
	 * @var int
	 */
	protected static $user;

	/**
	 * creats a link between $app1,$id1 and $app2,$id2
	 *
	 * @param string $app1 appname of 1. endpoint of the link
	 * @param string $id1 id in $app1
	 * @param string $app2 appname of 2. endpoint of the link
	 * @param string $id2 id in $app2
	 * @param string $remark='' Remark to be saved with the link (defaults to '')
	 * @param int $owner=0 Owner of the link (defaults to user)
	 * @param int $lastmod=0 timestamp of last modification (defaults to now=time())
	 * @param int $no_notify=0 &1 dont notify $app1, &2 dont notify $app2
	 * @return int/boolean False (for db or param-error) or on success link_id (Please not the return-value of $id1)
	 */
	static function link( $app1,&$id1,$app2,$id2='',$remark='',$owner=0,$lastmod=0,$no_notify=0 )
	{
		if (self::DEBUG)
		{
			echo "<p>solink.link('$app1',$id1,'$app2',$id2,'$remark',$owner)</p>\n";
		}
		if ($app1 == $app2 && $id1 == $id2 ||
		    $id1 == '' || $id2 == '' || $app1 == '' || $app2 == '')
		{
			return False;	// dont link to self or other nosense
		}
		if ($link = self::get_link($app1,$id1,$app2,$id2))
		{
			if ($link['link_remark'] != $remark)
			{
				self::update_remark($link['link_id'],$remark);
			}
			return $link['link_id'];	// link alread exist
		}
		if (!$owner)
		{
			$owner = self::$user;
		}
		return self::$db->insert(self::TABLE,array(
				'link_app1'		=> $app1,
				'link_id1'		=> $id1,
				'link_app2'		=> $app2,
				'link_id2'		=> $id2,
				'link_remark'	=> $remark,
				'link_lastmod'	=> $lastmod ? $lastmod : time(),
				'link_owner'	=> $owner,
			),False,__LINE__,__FILE__) ? self::$db->get_last_insert_id(self::TABLE,'link_id') : false;
	}

	/**
	 * update the remark of a link
	 *
	 * @param int $link_id link to update
	 * @param string $remark new text for the remark
	 * @return boolean true on success, else false
	 */
	static function update_remark($link_id,$remark)
	{
		return self::$db->update(self::TABLE,array(
				'link_remark'	=> $remark,
				'link_lastmod'	=> time(),
			),array(
				'link_id'	=> $link_id,
			),__LINE__,__FILE__);
	}

	/**
	 * returns array of links to $app,$id
	 *
	 * @param string $app appname
	 * @param string/array $id id(s) in $app
	 * @param string $only_app if set return only links from $only_app (eg. only addressbook-entries) or NOT from if $only_app[0]=='!'
	 * @param string $order defaults to newest links first
	 * @return array id => links pairs if $id is an array or just the links (only_app: ids) or empty array if no matching links found
	 */
	static function get_links( $app,$id,$only_app='',$order='link_lastmod DESC' )
	{
		if (self::DEBUG)
		{
			echo "<p>solink.get_links($app,".print_r($id,true).",$only_app,$order)</p>\n";
		}
		if (($not_only = $only_app[0] == '!'))
		{
			$only_app = substr($only_app,1);
		}
		#var_dump($not_only);echo "$only_app<br>";
		$links = array();
		foreach(self::$db->select(self::TABLE,'*',self::$db->expression(self::TABLE,'(',array(
					'link_app1'	=> $app,
					'link_id1'	=> $id,
				),') OR (',array(
					'link_app2'	=> $app,
					'link_id2'	=> $id,
				),')'
			),__LINE__,__FILE__,False,$order ? " ORDER BY $order" : '') as $row)
		{
			// check if left side (1) is one of our targets --> add it
			if ($row['link_app1'] == $app && in_array($row['link_id1'],(array)$id))
			{
				self::_add2links($row,true,$only_app,$not_only,$links);
			}
			// check if right side (2) is one of our targets --> add it (both can be true for multiple targets!)
			if ($row['link_app2'] == $app && in_array($row['link_id2'],(array)$id))
			{
				self::_add2links($row,false,$only_app,$not_only,$links);
			}
		}
		return is_array($id) ? $links : ($links[$id] ? $links[$id] : array());
	}

	private function _add2links($row,$left,$only_app,$not_only,array &$links)
	{
		$linked_app = $left ? $row['link_app2'] : $row['link_app1'];
		$linked_id  = $left ? $row['link_id2'] : $row['link_id1'];
		$app_id = $left ? $row['link_id1'] : $row['link_id2'];
		if ($only_app && $not_only == ($linked_app == $only_app) || !$GLOBALS['egw_info']['user']['apps'][$linked_app])
		{
			#echo "$linked_app == $only_app, ";var_dump($linked_app == $only_app);echo "	->dont return a link<br>";
			return;
		}
		#echo "returning ".(($only_app && !$not_only) ? " linkid:".$linked_id : " full array with linkid $linked_id")."<br>";
		$links[$app_id][$row['link_id']] = ($only_app && !$not_only) ? $linked_id : array(
			'app'     => $linked_app,
			'id'      => $linked_id,
			'remark'  => $row['link_remark'],
			'owner'   => $row['link_owner'],
			'lastmod' => $row['link_lastmod'],
			'link_id' => $row['link_id'],
		);
	}

	/**
	 * returns data of a link
	 *
	 * @param ing/string $app_link_id > 0 link_id of link or app-name of link
	 * @param string $id='' id in $app, if no integer link_id given in $app_link_id
	 * @param string $app2='' appname of 2. endpoint of the link, if no integer link_id given in $app_link_id
	 * @param string $id2='' id in $app2, if no integer link_id given in $app_link_id
	 * @return array with link-data or False
	 */
	static function get_link($app_link_id,$id='',$app2='',$id2='')
	{
		if (self::DEBUG)
		{
			echo "<p>solink.get_link('$app_link_id',$id,'$app2','$id2')</p>\n";
		}
		if ((int) $app_link_id > 0)
		{
			$where = array('link_id' => $app_link_id);
		}
		else
		{
			if ($app_link_id == '' || $id == '' || $app2 == '' || $id2 == '')
			{
				return False;
			}
			$where = self::$db->expression(self::TABLE,'(',array(
					'link_app1'	=> $app_link_id,
					'link_id1'	=> $id,
					'link_app2'	=> $app2,
					'link_id2'	=> $id2,
				),') OR (',array(
					'link_app2'	=> $app_link_id,
					'link_id2'	=> $id,
					'link_app1'	=> $app2,
					'link_id1'	=> $id2,
				),')');
		}
		foreach(self::$db->select(self::TABLE,'*',$where,__LINE__,__FILE__) as $row)
		{
			if (self::DEBUG)
			{
				_debug_array($row);
			}
			return $row;
		}
		return False;
	}

	/**
	 * Remove link with $link_id or all links matching given params
	 *
	 * @param $link_id link-id to remove if > 0
	 * @param string $app='' app-name of links to remove
	 * @param string $id='' id in $app or '' remove all links from $app
	 * @param int $owner=0 account_id to delete all links of a given owner, or 0
	 * @param string $app2='' appname of 2. endpoint of the link
	 * @param string $id2='' id in $app2
	 * @return array with deleted links
	 */
	static function unlink($link_id,$app='',$id='',$owner=0,$app2='',$id2='')
	{
		if (self::DEBUG)
		{
			echo "<p>solink.unlink($link_id,$app,$id,$owner,$app2,$id2)</p>\n";
		}
		if ((int)$link_id > 0)
		{
			$where = array('link_id' => $link_id);
		}
		elseif ($app == '' AND $owner == '')
		{
			return 0;
		}
		else
		{
			if ($app != '' && $app2 == '')
			{
				$check1 = array('link_app1' => $app);
				$check2 = array('link_app2' => $app);
				if ($id != '')
				{
					$check1['link_id1'] = $id;
					$check2['link_id2'] = $id;
				}
				$where = self::$db->expression(self::TABLE,'((',$check1,') OR (',$check2,'))');
			}
			elseif ($app != '' && $app2 != '')
			{
				$where = self::$db->expression(self::TABLE,'(',array(
						'link_app1'	=> $app,
						'link_id1'	=> $id,
						'link_app2'	=> $app2,
						'link_id2'	=> $id2,
					),') OR (',array(
						'link_app1'	=> $app2,
						'link_id1'	=> $id2,
						'link_app2'	=> $app,
						'link_id2'	=> $id,
					),')');
			}
			if ($owner)
			{
				if ($app) $where = array($where);
				$where['link_owner'] = $owner;
			}
		}
		$deleted = array();
		foreach(self::$db->select(self::TABLE,'*',$where,__LINE__,__FILE__) as $row)
		{
			$deleted[] = $row;
		}
		self::$db->delete(self::TABLE,$where,__LINE__,__FILE__);

		return $deleted;
	}

	/**
	 * Changes ownership of all links from $owner to $new_owner
	 *
	 * This is needed when a user/account gets deleted
	 * Does NOT change the modification-time
	 *
	 * @param int $owner acount_id of owner to change
	 * @param int $new_owner account_id of new owner
	 * @return int number of links changed
	 */
	static function chown($owner,$new_owner)
	{
		if ((int)$owner <= 0 || (int) $new_owner <= 0)
		{
			return 0;
		}
		self::$db->update(self::TABLE,array('owner'=>$new_owner),array('owner'=>$owner),__LINE__,__FILE__);

		return self::$db->affected_rows();
	}

	/**
	 * Get all links from a given app's entries to an other app's entries, which both link to the same 3. app and id
	 *
	 * Example:
	 * I search all timesheet's linked to a given project and id(s), who are also linked to other entries,
	 * which link to the same project:
	 *
	 * ($app='timesheet'/some id) <--a--> (other app/other id) <--b--> ($t_app='projectmanager'/$t_id=$pm_id)
	 *                  ^                                                                     ^
	 *                  +---------------------------c-----------------------------------------+
	 *
	 * bolink::get_3links('timesheet','projectmanager',$pm_id) returns the links (c) between the timesheet and the project,
	 * plus the other app/id in the keys 'app3' and 'id3'
	 *
	 * @param string $app app the returned links are linked on one side (atm. this must be link_app1!)
	 * @param string $target_app app the returned links other side link also to
	 * @param string/array $target_id=null id(s) the returned links other side link also to
	 * @param boolean $just_app_ids=false return array with link_id => app_id pairs, not the full link record
	 * @return array with links from entries from $app to $target_app/$target_id plus the other (b) link_id/app/id in the keys 'link3'/'app3'/'id3'
	 */
	static function get_3links($app,$target_app,$target_id=null,$just_app_ids=false)
	{
		$table = self::TABLE;
		$arrayofselects=array(
			// retrieve the type of links, where the relation is realized as timesheet->infolog/tracker via infolog->projectmanager to timesheet->projectmanager
			array('table'=>self::TABLE,
				'cols'=>'c.*,b.link_app1 AS app3,b.link_id1 AS id3,b.link_id AS link3',
				'where'=>'a.link_app1='.self::$db->quote($app).' AND c.link_app2='.self::$db->quote($target_app).
                        		(!$target_id ? '' : self::$db->expression(self::TABLE,' AND c.',array('link_id2' => $target_id))),
                        	'join'=>" a
                        		JOIN $table b ON a.link_id2=b.link_id1 AND a.link_app2=b.link_app1
                       			JOIN $table c ON a.link_id1=c.link_id1 AND a.link_app1=c.link_app1 AND a.link_id!=c.link_id AND c.link_app2=b.link_app2 AND c.link_id2=b.link_id2",
			),
			// retrieve the type of links, where the relation is realized as timesheet->infolog/tracker and projectmanager->timesheet
			array('table'=>self::TABLE,
				'cols'=>'b.link_id, b.link_app2 as app1, b.link_id2 as id1, b.link_app1 as app2, b.link_id1 as id2, b.link_remark,b.link_lastmod,b.link_owner,null,c.link_app1 AS app3,c.link_id1 AS id3,c.link_id AS link3',
                       		'where'=>'a.link_app1='.self::$db->quote($app).' AND b.link_app1='.self::$db->quote($target_app).
                        		(!$target_id ? '' : self::$db->expression(self::TABLE,' AND b.',array('link_id1' => $target_id))),
                        	'join'=>" a
                        		JOIN $table b ON a.link_id1=b.link_id2 AND a.link_app1=b.link_app2
                        		JOIN $table c ON a.link_id2=c.link_id1 AND a.link_app2=c.link_app1 AND a.link_id!=c.link_id AND c.link_app2=b.link_app1 AND c.link_id2=b.link_id1",
			),
			// retrieve the type of links, where the relation is realized as timesheet->projectmanager and infolog->timesheet
			array('table'=>self::TABLE,
				'cols'=>'a.*,c.link_app1 AS app3,c.link_id1 AS id3,c.link_id AS link3',
                     		'where'=>'a.link_app1='.self::$db->quote($app).' AND a.link_app2='.self::$db->quote($target_app).
                        		(!$target_id ? '' : self::$db->expression(self::TABLE,' AND a.',array('link_id2' => $target_id))),
                       		'join'=>" a
                       			JOIN $table b ON a.link_id1=b.link_id2 AND a.link_app1=b.link_app2
                        		JOIN $table c ON a.link_id2=c.link_id2 AND a.link_app2=c.link_app2 AND a.link_id!=c.link_id AND c.link_app1=b.link_app1 AND c.link_id1=b.link_id1",
			),
		);
		$links = array();
		foreach(self::$db->union($arrayofselects,__LINE__,__FILE__) as $row)
		{
			if ($just_app_ids)
			{
				if ($row['link_app1'] == $target_app && (is_null($target_id) || in_array($row['link_id1'],(array)$target_id)))
				{
					$links[$row['link_id']] = $row['link_id2'];
				}
				else
				{
					$links[$row['link_id']] = $row['link_id1'];
				}
			}
			else
			{
				$links[] = egw_db::strip_array_keys($row,'link_');
			}
		}
 		return $links;
	}

	/**
	 * Initialise our static vars
	 */
	static function init_static( )
	{
		self::$db     = $GLOBALS['egw']->db;
		self::$user   =& $GLOBALS['egw_info']['user']['account_id'];
	}
}
solink::init_static();
