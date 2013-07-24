<?php
/**
 * eGroupWare API - LDAP Authentication
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <ralfbecker@outdoor-training.de>
 * @author Lars Kneschke <lkneschke@linux-at-work.de>
 * @author Joseph Engo <jengo@phpgroupware.org>
 * Copyright (C) 2000, 2001 Joseph Engo
 * Copyright (C) 2002, 2003 Lars Kneschke
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @subpackage authentication
 * @version $Id: class.auth_ldap.inc.php 41793 2013-02-21 10:03:02Z ralfbecker $
 */

/**
 * Authentication agains a LDAP Server
 */
class auth_ldap implements auth_backend
{
	var $previous_login = -1;
	/**
	 * Switch this on to get messages in Apache error_log, why authtication fails
	 *
	 * @var boolean
	 */
	var $debug = false;

	/**
	 * authentication against LDAP
	 *
	 * @param string $username username of account to authenticate
	 * @param string $passwd corresponding password
	 * @return boolean true if successful authenticated, false otherwise
	 */
	function authenticate($username, $passwd, $passwd_type='text')
	{
		// allow non-ascii in username & password
		$username = translation::convert($username,translation::charset(),'utf-8');
		$passwd = translation::convert($passwd,translation::charset(),'utf-8');

		if(!$ldap = common::ldapConnect())
		{
			$GLOBALS['egw']->log->message('F-Abort, Failed connecting to LDAP server for authenication, execution stopped');
			$GLOBALS['egw']->log->commit();
			return False;
		}

		/* Login with the LDAP Admin. User to find the User DN.  */
		if(!@ldap_bind($ldap, $GLOBALS['egw_info']['server']['ldap_root_dn'], $GLOBALS['egw_info']['server']['ldap_root_pw']))
		{
			if ($this->debug) error_log(__METHOD__."('$username',\$password) can NOT bind with ldap_root_dn to search!");
			return False;
		}
		/* find the dn for this uid, the uid is not always in the dn */
		$attributes	= array('uid','dn','givenName','sn','mail','uidNumber','shadowExpire');

		$filter = $GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)';
		$filter = str_replace(array('%user','%domain'),array(ldap::quote($username),$GLOBALS['egw_info']['user']['domain']),$filter);

		if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap')
		{
			$filter = "(&$filter(objectclass=posixaccount))";
		}
		$sri = ldap_search($ldap, $GLOBALS['egw_info']['server']['ldap_context'], $filter, $attributes);
		$allValues = ldap_get_entries($ldap, $sri);

		if ($allValues['count'] > 0)
		{
			if ($GLOBALS['egw_info']['server']['case_sensitive_username'] == true &&
				$allValues[0]['uid'][0] != $username)
			{
				if ($this->debug) error_log(__METHOD__."('$username',\$password) wrong case in username!");
				return false;
			}
			if ($GLOBALS['egw_info']['server']['account_repository'] == 'ldap' &&
				isset($allValues[0]['shadowexpire']) && $allValues[0]['shadowexpire'][0]*24*3600 < time())
			{
				if ($this->debug) error_log(__METHOD__."('$username',\$password) account is expired!");
				return false;	// account is expired
			}
			$userDN = $allValues[0]['dn'];

			// try to bind as the user with user suplied password
			// only if a non-empty password given, in case anonymous search is enabled
			if (!empty($passwd) && ($ret = @ldap_bind($ldap, $userDN, $passwd)))
			{
				if ($GLOBALS['egw_info']['server']['account_repository'] != 'ldap')
				{
					if (!($id = $GLOBALS['egw']->accounts->name2id($username,'account_lid','u')))
					{
						// account does NOT exist, check if we should create it
						if ($GLOBALS['egw_info']['server']['auto_create_acct'])
						{
							// create a global array with all availible info about that account
							$GLOBALS['auto_create_acct'] = array();
							foreach(array(
								'givenname' => 'firstname',
								'sn'        => 'lastname',
								'uidnumber' => 'account_id',
								'mail'      => 'email',
							) as $ldap_name => $acct_name)
							{
								$GLOBALS['auto_create_acct'][$acct_name] =
									translation::convert($allValues[0][$ldap_name][0],'utf-8');
							}
							$ret = true;
						}
						else
						{
							$ret = false;
							if ($this->debug) error_log(__METHOD__."('$username',\$password) bind as user failed!");
						}
					}
					// account exists, check if it is acctive
					else
					{
						$ret = $GLOBALS['egw']->accounts->id2name($id,'account_status') == 'A';

						if ($this->debug && !$ret) error_log(__METHOD__."('$username',\$password) account NOT active!");
					}
				}
				// account-repository is ldap --> check if passwd hash migration is enabled
				elseif ($GLOBALS['egw_info']['server']['pwd_migration_allowed'] &&
					!empty($GLOBALS['egw_info']['server']['pwd_migration_types']))
				{
					// try to query password from ldap server (might fail because of ACL) and check if we need to migrate the hash
					if (($sri = ldap_search($ldap, $userDN,"(objectclass=*)", array('userPassword'))) &&
						($values = ldap_get_entries($ldap, $sri)) && isset($values[0]['userpassword'][0]) &&
						($type = preg_match('/^{(.+)}/',$values[0]['userpassword'][0],$matches) ? strtolower($matches[1]) : 'plain') &&
						// for crypt use auth::crypt_compare to detect correct sub-type, strlen("{crypt}")=7
						($type != 'crypt' || auth::crypt_compare($passwd, substr($values[0]['userpassword'][0], 7), $type)) &&
						in_array($type, explode(',',strtolower($GLOBALS['egw_info']['server']['pwd_migration_types']))))
					{
						$this->change_password($passwd, $passwd, $allValues[0]['uidnumber'][0], false);
					}
				}
				return $ret;
			}
		}
		if ($this->debug) error_log(__METHOD__."('$username','$password') dn not found or password wrong!");
		// dn not found or password wrong
		return False;
	}

	/**
	 * changes password in LDAP
	 *
	 * If $old_passwd is given, the password change is done binded as user and NOT with the
	 * "root" dn given in the configurations.
	 *
	 * @param string $old_passwd must be cleartext or empty to not to be checked
	 * @param string $new_passwd must be cleartext
	 * @param int $account_id account id of user whose passwd should be changed
	 * @param boolean $update_lastchange=true
	 * @return boolean true if password successful changed, false otherwise
	 */
	function change_password($old_passwd, $new_passwd, $account_id=0, $update_lastchange=true)
	{
		if (!$account_id)
		{
			$username = $GLOBALS['egw_info']['user']['account_lid'];
		}
		else
		{
			$username = translation::convert($GLOBALS['egw']->accounts->id2name($account_id),
				translation::charset(),'utf-8');
		}
		if ($this->debug) error_log(__METHOD__."('$old_passwd','$new_passwd',$account_id, $update_lastchange) username='$username'");

		$filter = $GLOBALS['egw_info']['server']['ldap_search_filter'] ? $GLOBALS['egw_info']['server']['ldap_search_filter'] : '(uid=%user)';
		$filter = str_replace(array('%user','%domain'),array($username,$GLOBALS['egw_info']['user']['domain']),$filter);

		$ds = $ds_admin = common::ldapConnect();
		$sri = ldap_search($ds, $GLOBALS['egw_info']['server']['ldap_context'], $filter);
		$allValues = ldap_get_entries($ds, $sri);

		$entry['userpassword'] = auth::encrypt_password($new_passwd);
		if ($update_lastchange) $entry['shadowlastchange'] = round((time()-date('Z')) / (24*3600));

		$dn = $allValues[0]['dn'];

		if($old_passwd)	// if old password given (not called by admin) --> bind as that user to change the pw
		{
			$user_ds = new ldap(true);	// true throw exceptions in case of error
			try {
				$ds = $user_ds->ldapConnect('',$dn,$old_passwd);
			}
			catch (egw_exception_no_permission $e) {
				return false;	// wrong old user password
			}
		}
		// try changing password bind as user or as admin, to cater for all sorts of ldap configuration
		// where either only user is allowed to change his password, or only admin user is allowed to
		if (!@ldap_modify($ds, $dn, $entry) && (!$old_passwd || !@ldap_modify($ds_admin, $dn, $entry)))
		{
			return false;
		}
		if($old_passwd)	// if old password given (not called by admin) update the password in the session
		{
		}
		return $entry['userpassword'];
	}
}
