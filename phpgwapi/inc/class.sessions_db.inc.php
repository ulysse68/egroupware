<?php
 	/**
	* eGW's Session Management
	*
	* This allows eGroupWare to use php or database sessions
	*
	* @link www.egroupware.org
	* @author NetUSE AG Boris Erdmann, Kristian Koehntopp
	* @author Dan Kuykendall <seek3r@phpgroupware.org>
	* @author Joseph Engo <jengo@phpgroupware.org>
	* @author Ralf Becker <ralfbecker@outdoor-training.de>
	* @copyright &copy; 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp <br> &copy; 2003 FreeSoftware Foundation
	* @license LGPL
	* @version $Id: class.sessions_db.inc.php 22474 2006-09-24 06:53:34Z ralfbecker $
	*/
 
	/**
	* Session Management via database (based on phplib sessions)
	*
	* @package api
	* @subpackage sessions
	*/

	class sessions extends sessions_
	{
		var $sessions_table = 'egw_sessions';
		var $app_sessions_table = 'egw_app_sessions';

		function sessions($domain_names=null)
		{
			$this->sessions_($domain_names);
		}
		
		function read_session()
		{
			$this->db->select($this->sessions_table,'*',array('session_id' => $this->sessionid),__LINE__,__FILE__);
			
			return $this->db->row(true);
		}

		/**
		 * remove stale sessions out of the database
		 */
		function clean_sessions()
		{
			$this->db->delete($this->sessions_table,array(
				'session_dla <= ' . (time() - $GLOBALS['egw_info']['server']['sessions_timeout']),
				"session_flags != 'A'",
			),__LINE__,__FILE__);

			// This is set a little higher, we don't want to kill session data for anonymous sessions.
			$GLOBALS['egw']->db->delete($this->app_sessions_table,array(
				'session_dla <= ' . (time() - $GLOBALS['egw_info']['server']['sessions_timeout']),
			),__LINE__,__FILE__);
		}

		function register_session($login,$user_ip,$now,$session_flags)
		{
			$GLOBALS['egw']->db->insert($this->sessions_table,array(
				'session_lid'       => $login,
				'session_ip'        => $user_ip,
				'session_logintime' => $now,
				'session_dla'       => $now,
				'session_action'    => $_SERVER['PHP_SELF'],
				'session_flags'     => $session_flags,
			),array(
				'session_id'        => $this->sessionid,
			),__LINE__,__FILE__);
		}

		/**
		 * update the DateLastActive column, so the login does not expire
		 */
		function update_dla()
		{
			if (@isset($_GET['menuaction']))
			{
				$action = $_GET['menuaction'];
			}
			else
			{
				$action = $_SERVER['PHP_SELF'];
			}

			// This way XML-RPC users aren't always listed as
			// xmlrpc.php
			if ($this->xmlrpc_method_called)
			{
				$action = $this->xmlrpc_method_called;
			}

			$GLOBALS['egw']->db->update($this->sessions_table,array(
				'session_dla'    => time(),
				'session_action' => $action,
			),array(
				'session_id'     => $this->sessionid,
			),__LINE__,__FILE__);

			$GLOBALS['egw']->db->update($this->app_sessions_table,array(
				'session_dla'    => time(),
			),array(
				'sessionid'     => $this->sessionid,
			),__LINE__,__FILE__);

			return True;
		}

		function destroy($sessionid, $kp3)
		{
			if (!$sessionid && $kp3)
			{
				return False;
			}
			$GLOBALS['egw']->db->transaction_begin();

			$GLOBALS['egw']->db->delete($this->sessions_table,array('session_id' => $sessionid),__LINE__,__FILE__);
			$GLOBALS['egw']->db->delete($this->app_sessions_table,array('sessionid' => $sessionid),__LINE__,__FILE__);

			$this->log_access($this->sessionid);	// log logout-time

			// Only do the following, if where working with the current user
			if ($sessionid == $GLOBALS['egw_info']['user']['sessionid'])
			{
				$this->clean_sessions();
			}
			$GLOBALS['egw']->db->transaction_commit();

			return True;
		}

		/*************************************************************************\
		* Functions for appsession data and session cache                         *
		\*************************************************************************/

		/**
		 * delete the old phpgw_info cache
		 *
		 * @deprecated not longer used
		 */
		function delete_cache($accountid='')
		{
		}

		function appsession($location = 'default', $appname = '', $data = '##NOTHING##')
		{
			if (!$this->account_id || !$this->sessionid)
			{
				return False;	// this can happen during login or logout
			}
			if (!$appname)
			{
				$appname = $GLOBALS['egw_info']['flags']['currentapp'];
			}
			
			/* This allows the user to put '' as the value. */
			if ($data == '##NOTHING##')
			{
				$GLOBALS['egw']->db->select($this->app_sessions_table,'content',array(
					'sessionid' => $this->sessionid,
					'loginid'   => $this->account_id,
					'app'       => $appname,
					'location'  => $location,
				),__LINE__,__FILE__);
				$GLOBALS['egw']->db->next_record();

				// do not decrypt and return if no data (decrypt returning garbage)
				if(($data = $GLOBALS['egw']->db->f('content')))
				{
					return $GLOBALS['egw']->crypto->decrypt($data);
				}
				return null;
			}
			$GLOBALS['egw']->db->insert($this->app_sessions_table,array(
				'content'   => $GLOBALS['egw']->crypto->encrypt($data),
			),array(
				'sessionid' => $this->sessionid,
				'loginid'   => $this->account_id,
				'app'       => $appname,
				'location'  => $location,
			),__LINE__,__FILE__);

			return $data;
		}

		/**
		 * list all sessions
		 */
		function list_sessions($start, $order, $sort, $all_no_sort = False)
		{
			$values = array();
			
			$order_by = 'ORDER BY '.$sort.' '.$order;
			if (!preg_match('/^[a-z_0-9, ]+$/i',$sort) || !preg_match('/^(asc|desc)?$/i',$sort))
			{
				$order_by = 'ORDER BY session_dla asc';
			}
			$this->db->select($this->sessions_table,'*',"session_flags != 'A'",__LINE__,__FILE__,(int)$start,$order_by);

			while (($row = $this->db->row(true)))
			{
				$values[] = $row;
			}
			return $values;
		}
		
		/**
		 * get number of regular / non-anonymous sessions
		 *
		 * @return int
		 */
		function total()
		{
			$this->db->select($this->sessions_table,'COUNT(*)',"session_flags != 'A'",__LINE__,__FILE__);

			return $this->db->next_record() ? $this->db->f(0) : 0;
		}
	}
?>
