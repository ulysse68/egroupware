<?php
/**
 * EGroupware EMailAdmin: Business logic
 *
 * @link http://www.stylite.de
 * @package emailadmin
 * @author Ralf Becker <rb@stylite.de>
 * @author Klaus Leithoff <kl@stylite.de>
 * @author Lars Kneschke
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.emailadmin_bo.inc.php 42795 2013-06-18 14:51:27Z ralfbecker $
 */

/**
 * Business logic
 */
class emailadmin_bo extends so_sql
{
	/**
	 * Name of our table
	 */
	const TABLE = 'egw_emailadmin';
	/**
	 * Name of app the table is registered
	 */
	const APP = 'emailadmin';
	/**
	 * Fields that are numeric
	 */
	static $numericfields = array(
		'ea_profile_id',
		'ea_smtp_port',
		'ea_smtp_auth',
		'ea_editforwardingaddress',
		'ea_smtp_ldap_use_default',
		'ea_imap_port',
		'ea_imap_tsl_auth',
		'ea_imap_tsl_encryption',
		'ea_imap_enable_cyrus',
		'ea_imap_enable_sieve',
		'ea_imap_sieve_port',
		'ea_user_defined_identities',
		'ea_user_defined_accounts',
		'ea_order',
		'ea_active',
		'ea_group',
		'ea_user',
		'ea_appname',
		'ea_user_defined_signatures',
		);

	static $sessionData = array();
	#var $userSessionData;
	var $LDAPData;

	//var $SMTPServerType = array();		// holds a list of config options
	static $supportedSMTPFields = array(
				'smtpServer',
				'smtpPort',
				'smtpAuth',
				'ea_smtp_auth_username',
				'ea_smtp_auth_password',
				'smtpType',
				'editforwardingaddress',
			);
	static $SMTPServerType = array(
		'emailadmin_smtp' 	=> array(
			'description'	=> 'standard SMTP-Server',
			'classname'	=> 'emailadmin_smtp'
		),
	);
	//var $IMAPServerType = array();		// holds a list of config options
	static $supportedIMAPFields = array(
				'imapServer',
				'imapPort',
				'imapType',
				'imapLoginType',
				'imapTLSEncryption',
				'imapTLSAuthentication',
				'imapEnableCyrusAdmin',
				'imapAdminUsername',
				'imapAdminPW',
				'imapEnableSieve',
				'imapSieveServer',
				'imapSievePort',
				'imapAuthUsername',
				'imapAuthPassword'
			);
	static $IMAPServerType = array(
		'defaultimap' 	=> array(
			'description'	=> 'standard IMAP server',
			'protocol'	=> 'imap',
			'classname'	=> 'defaultimap'
		)
	);

	var $imapClass;				// holds the imap/pop3 class
	var $smtpClass;				// holds the smtp class
	var $tracking;				// holds the tracking object

	function __construct($_profileID=false,$_restoreSesssion=true)
	{
		parent::__construct(self::APP,self::TABLE,null,'',true);
		//error_log(__METHOD__.function_backtrace());
		if (!is_object($GLOBALS['emailadmin_bo']))
		{
			$GLOBALS['emailadmin_bo'] = $this;
		}
		$this->soemailadmin = new emailadmin_so();
		//init with all servertypes and translate the standard entry description
		self::$SMTPServerType = self::getSMTPServerTypes();
		self::$IMAPServerType = self::getIMAPServerTypes();
		self::$SMTPServerType['emailadmin_smtp']['description'] = lang('standard SMTP-Server');
		self::$IMAPServerType['defaultimap']['description'] = lang('standard IMAP Server');
		if ($_restoreSesssion) // &&  !(is_array(self::$sessionData) && (count(self::$sessionData)>0))  )
		{
			$this->restoreSessionData();
		}
		if ($_restoreSesssion===false) // && (is_array(self::$sessionData) && (count(self::$sessionData)>0))  )
		{
			// make sure session data will be created new
			self::$sessionData = array();
			self::saveSessionData();
		}
		#_debug_array(self::$sessionData);
		if(!($_profileID === false))
		{
			$this->profileID	= $_profileID;

			$this->profileData	= $this->getProfile($_profileID);

			// try autoloading class, if that fails include it from emailadmin
			$class = self::$IMAPServerType[$this->profileData['imapType']]['classname'];
			if (!empty($class))
			{
				if (!class_exists($class))
				{
					include_once(EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php');
				}
				$this->imapClass	= new $class;
			}
			if ($this->profileData['smtpType']=='defaultsmtp') $this->profileData['smtpType']='emailadmin_smtp';
			$class = self::$SMTPServerType[$this->profileData['smtpType']]['classname'];
			if (!empty($class))
			{
				if (!class_exists($class))
				{
					include_once(EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php');
				}
				$this->smtpClass	= new $class;
			}
		}
		$this->tracking = new emailadmin_tracking($this);
	}

	function delete($profileid=null)
	{
		if (empty($profileid)) return 0;
		$deleted = parent::delete(array('ea_profile_id' => $profileid));
		if (!is_array($profileid)) $profileid = (array)$profileid;
		foreach ($profileid as $tk => $pid)
		{
			self::$sessionData['profile'][$pid] = array();
			$GLOBALS['egw']->contenthistory->updateTimeStamp('emailadmin_profiles', $pid, 'delete', time());
		}
		self::saveSessionData();
		return $deleted;
	}

	function save()
	{
		$content = $this->data;
		$old = $this->read($content);
		$this->data = $content;
		if ((!isset($this->data['ea_appname']) || empty($this->data['ea_appname']) ) &&
			(!isset($this->data['ea_group']) || empty($this->data['ea_group']) ) &&
			(!isset($this->data['ea_user']) || empty($this->data['ea_user']) ) &&
			(isset($this->data['ea_active']) && !empty($this->data['ea_active']) && $this->data['ea_active'] ))
		{
			$new_config = array();
			foreach(array(
					'ea_imap_server'    => 'mail_server',
					'ea_imap_type'      => 'mail_server_type',
					'ea_imap_login_type' => 'mail_login_type',
					'ea_default_domain' => 'mail_suffix',
					'ea_smtp_server'    => 'smtp_server',
					'ea_smtp_port'      => 'smtp_port',
				)+($this->data['ea_smtp_auth']!='no' ? array( //ToDo: if no, we may have to reset config values for that too?
					'ea_smtp_auth' => 'smtpAuth',
					'ea_smtp_auth_username' => 'smtp_auth_user',
					'ea_smtp_auth_password' => 'smtp_auth_passwd',
				) : array()) as $ea_name => $config_name)
			{
				if (isset($this->data[$ea_name]))
				{
					if ($ea_name != 'ea_imap_type')
					{
						$new_config[$config_name] = $this->data[$ea_name];
					}
					else	// imap type, no pop3 code anymore
					{
						$new_config[$config_name] = 'imap'.($this->data['ea_imap_tsl_encryption'] ? 's' : '');
					}
				}
			}
			if (count($new_config))
			{
				foreach($new_config as $name => $value)
				{
					//error_log(__METHOD__.__LINE__.' Saving to config:'."$name,$value,phpgwapi");
					config::save_value($name,$value,'phpgwapi');
				}
				//echo "<p>eGW configuration update: ".print_r($new_config,true)."</p>\n";
			}
		}
		if (empty($this->data['ea_stationery_active_templates'])) $this->data['ea_stationery_active_templates']='';
		//error_log(__METHOD__.__LINE__.' Content to save:'.array2string($this->data));
		if (is_numeric($this->data['ea_profile_id'])) self::unsetCachedObjects($this->data['ea_profile_id']*-1);
		if (!($result = parent::save()))
		{
			$GLOBALS['egw']->contenthistory->updateTimeStamp('emailadmin_profiles', $this->data['ea_profile_id'], $old === false ? 'add' : 'modify', time());
			//error_log(__METHOD__.__LINE__.array2string($content));
			$this->tracking->track($content,(is_array($old)?$old:array()),null,false,null,true);
		}
		return $result;
	}

	function addAccount($_hookValues)
	{
		if (is_object($this->imapClass))
		{
			#ExecMethod("emailadmin.".$this->imapClass.".addAccount",$_hookValues,3,$this->profileData);
			$this->imapClass->addAccount($_hookValues);
		}

		if (is_object($this->smtpClass))
		{
			#ExecMethod("emailadmin.".$this->smtpClass.".addAccount",$_hookValues,3,$this->profileData);
			$this->smtpClass->addAccount($_hookValues);
		}
		self::$sessionData =array();
		$this->saveSessionData();
	}

	function deleteAccount($_hookValues)
	{
		if (is_object($this->imapClass))
		{
			#ExecMethod("emailadmin.".$this->imapClass.".deleteAccount",$_hookValues,3,$this->profileData);
			$this->imapClass->deleteAccount($_hookValues);
		}

		if (is_object($this->smtpClass))
		{
			#ExecMethod("emailadmin.".$this->smtpClass.".deleteAccount",$_hookValues,3,$this->profileData);
			$this->smtpClass->deleteAccount($_hookValues);
		}
		self::$sessionData = array();
		$this->saveSessionData();
	}

	function getAccountEmailAddress($_accountName, $_profileID)
	{
		$profileData	= $this->getProfile($_profileID);

		#$smtpClass	= self::$SMTPServerType[$profileData['smtpType']]['classname'];
		if ($profileData['smtpType']=='defaultsmtp') $profileData['smtpType']='emailadmin_smtp';
		$smtpClass	= CreateObject('emailadmin.'.self::$SMTPServerType[$profileData['smtpType']]['classname']);

		#return empty($smtpClass) ? False : ExecMethod("emailadmin.$smtpClass.getAccountEmailAddress",$_accountName,3,$profileData);
		return is_object($smtpClass) ?  $smtpClass->getAccountEmailAddress($_accountName) : False;
	}

	function getFieldNames($_serverTypeID, $_class)
	{
		switch($_class)
		{
			case 'imap':
				return (isset(self::$IMAPServerType[$_serverTypeID]['fieldNames'])?self::$IMAPServerType[$_serverTypeID]['fieldNames']:self::$supportedIMAPFields);
				break;
			case 'smtp':
				if ($_serverTypeID=='defaultsmtp') $_serverTypeID='emailadmin_smtp';
				return (isset(self::$SMTPServerType[$_serverTypeID]['fieldNames'])?self::$SMTPServerType[$_serverTypeID]['fieldNames']:self::$supportedSMTPFields);
				break;
		}
	}

	function getLDAPStorageData($_serverid)
	{
		$storageData = $this->soemailadmin->getLDAPStorageData($_serverid);
		return $storageData;
	}

	function getMailboxString($_folderName)
	{
		if (is_object($this->imapClass))
		{
			return ExecMethod("emailadmin.".$this->imapClass.".getMailboxString",$_folderName,3,$this->profileData);
			return $this->imapClass->getMailboxString($_folderName);
		}
		else
		{
			return false;
		}
	}

	function getProfile($_profileID)
	{
		if (!(is_array(self::$sessionData) && (count(self::$sessionData)>0))) $this->restoreSessionData();
		if (is_array(self::$sessionData) && (count(self::$sessionData)>0) && self::$sessionData['profile'][$_profileID]) {
			//error_log("sessionData Restored for Profile $_profileID <br>");
			return self::$sessionData['profile'][$_profileID];
		}
		$EAuserProfileData= egw_cache::getCache(egw_cache::INSTANCE,'email','EAuserProfileData'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*1);
		if (isset($EAuserProfileData[$_profileID]) && is_array($EAuserProfileData[$_profileID]) && !empty($EAuserProfileData[$_profileID]))
		{
			return $EAuserProfileData[$_profileID];
		}

		$profileData = $this->soemailadmin->getProfileList($_profileID);
		$found = false;
		if (is_array($profileData) && count($profileData))
		{
			foreach($profileData as $n => $data)
			{
				if ($data['ProfileID'] == $_profileID)
				{
					$found = $n;
					break;
				}
			}
		}
		if ($found === false)		// no existing profile selected
		{
			if (is_array($profileData) && count($profileData)) {	// if we have a profile use that
				reset($profileData);
				list($found,$data) = each($profileData);
				$this->profileID = $_profileID = $data['profileID'];
			} elseif ($GLOBALS['egw_info']['server']['smtp_server']) { // create a default profile, from the data in the api config
				$this->profileID = $_profileID = $this->setDefaultProfile(array(
					'description' => $GLOBALS['egw_info']['server']['smtp_server'],
					'defaultDomain' => $GLOBALS['egw_info']['server']['mail_suffix'],
					'organisationName' => '',
					'smtp_server' => $GLOBALS['egw_info']['server']['smtp_server'],
					'smtp_port' => $GLOBALS['egw_info']['server']['smtp_port'],
					'smtpAuth' => $GLOBALS['egw_info']['server']['smtpAuth'],
					'smtp_auth_user' => $GLOBALS['egw_info']['server']['smtp_auth_user'],
					'smtp_auth_passwd' => $GLOBALS['egw_info']['server']['smtp_auth_passwd'],
					'mail_server' => $GLOBALS['egw_info']['server']['mail_server'], // ? DO NOT USE THE SMTP Server, as no IMAP Server may be intentional
					//	$GLOBALS['egw_info']['server']['mail_server'] : $GLOBALS['egw_info']['server']['smtp_server'],
					'mail_server_type' => $GLOBALS['egw_info']['server']['mail_server_type'],
					'mail_login_type' => $GLOBALS['egw_info']['server']['mail_login_type'] ?
						$GLOBALS['egw_info']['server']['mail_login_type'] : 'standard',
				));
				$profileData[$found = 0] = array(
					'smtpType' => 'emailadmin_smtp',
					'imapType' => 'defaultimap',
				);
			}
		}
		$fieldNames = array();
		if (isset($profileData[$found]))
		{
			if ($profileData[$found]['smtpType']=='defaultsmtp') $profileData[$found]['smtpType'] = 'emailadmin_smtp';
			$smtpFields = self::$supportedSMTPFields;
			$imapFields = self::$supportedIMAPFields;
			if (isset(self::$SMTPServerType[$profileData[$found]['smtpType']]['fieldNames'])) $smtpFields = self::$SMTPServerType[$profileData[$found]['smtpType']]['fieldNames'];
			if (isset(self::$IMAPServerType[$profileData[$found]['imapType']]['fieldNames'])) $imapFields = self::$IMAPServerType[$profileData[$found]['imapType']]['fieldNames'];
			$fieldNames = array_merge($smtpFields,$imapFields);
		}
		$fieldNames[] = 'description';
		$fieldNames[] = 'defaultDomain';
		$fieldNames[] = 'profileID';
		$fieldNames[] = 'organisationName';
		$fieldNames[] = 'userDefinedAccounts';
		$fieldNames[] = 'userDefinedIdentities';
		$fieldNames[] = 'ea_appname';
		$fieldNames[] = 'ea_group';
		$fieldNames[] = 'ea_user';
		$fieldNames[] = 'ea_active';
		$fieldNames[] = 'ea_user_defined_signatures';
		$fieldNames[] = 'ea_default_signature';
		$fieldNames[] = 'ea_stationery_active_templates';

		$profileData = $this->soemailadmin->getProfile($_profileID, $fieldNames);
		$profileData['imapTLSEncryption'] = ($profileData['imapTLSEncryption'] == 'yes' ? 1 : (int)$profileData['imapTLSEncryption']);
		if(strlen($profileData['ea_stationery_active_templates']) > 0)
		{
			$profileData['ea_stationery_active_templates'] = explode(',',$profileData['ea_stationery_active_templates']);
		}
		self::$sessionData['profile'][$_profileID] = $EAuserProfileData[$_profileID] = $profileData;
		egw_cache::setCache(egw_cache::INSTANCE,'email','EAuserProfileData'.trim($GLOBALS['egw_info']['user']['account_id']),$EAuserProfileData, $expiration=60*1);
		$this->saveSessionData();
		return $profileData;
	}

	function getProfileList($_profileID='',$_appName=false,$_groupID=false,$_accountID=false)
	{
		if ($_appName!==false ||$_groupID!==false ||$_accountID!==false) {
			return $this->soemailadmin->getProfileList($_profileID,false,$_appName,$_groupID,$_accountID);
		} else {
			return $this->soemailadmin->getProfileList($_profileID);
		}
	}

	/**
	 * Get a list of supported SMTP servers
	 *
	 * Calls hook "smtp_server_types" to allow applications to supply own server-types
	 *
	 * @return array classname => label pairs
	 */
	static public function getSMTPServerTypes($extended=true)
	{
		$retData = array();
		foreach($GLOBALS['egw']->hooks->process(array(
			'location' => 'smtp_server_types',
			'extended' => $extended,
		), array('managementserver', 'emailadmin'), true) as $app => $data)
		{
			if ($data) $retData += $data;
		}
		uksort($retData, array(__CLASS__, 'smtp_sort'));
		return $retData;
	}

	static function smtp_sort($a, $b)
	{
		static $prio = array(	// not explicitly mentioned get 0
			'emailadmin_smtp' => 9,
			'emailadmin_smtp_sql' => 8,
			'smtpplesk' => -1,
		);
		return (int)$prio[$b] - (int)$prio[$a];
	}
	/**
	 * Get a list of supported IMAP servers
	 *
	 * Calls hook "imap_server_types" to allow applications to supply own server-types
	 *
	 * @param boolean $extended=true
	 * @return array classname => label pairs
	 */
	static public function getIMAPServerTypes($extended=true)
	{
		$retData = array();
		foreach($GLOBALS['egw']->hooks->process(array(
			'location' => 'imap_server_types',
			'extended' => $extended,
		), array('managementserver', 'emailadmin'), true) as $app => $data)
		{
			if ($data) $retData += $data;
		}
		uksort($retData, array(__CLASS__, 'imap_sort'));
		return $retData;
	}

	static function imap_sort($a, $b)
	{
		static $prio = array(	// not explicitly mentioned get 0
			'defaultimap' => 9,
			'managementserver_imap' => 8,
			'emailadmin_dovecot' => 7,
			'cyrusimap' => 6,
			'pleskimap' => -1,
		);
		return (int)$prio[$b] - (int)$prio[$a];
	}

	/**
	 * unset certain CachedObjects for the given profile id, unsets the profile for default ID=0 as well
	 *
	 * @param int $_profileID=null default profile of user as returned by getUserDefaultProfileID
	 * @return void
	 */
	static function unsetCachedObjects($_profileID=null)
	{
		if (is_null($_profileID)) $_profileID = self::getUserDefaultProfileID();
		//error_log(__METHOD__.__LINE__.' called with ProfileID:'.$_profileID.' from '.function_backtrace());
		if (!is_array($_profileID) && is_numeric($_profileID))
		{
			//resetSessionCache['ea_preferences'], and cache to reduce database traffic
			unset(self::$sessionData['ea_preferences']);
			egw_cache::setCache(egw_cache::INSTANCE,'email','EASessionEAPrefs'.trim($GLOBALS['egw_info']['user']['account_id']),array(), $expiration=60*1);
			$EAuserProfileData= egw_cache::getCache(egw_cache::INSTANCE,'email','EAuserProfileData'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*1);
			if (isset($EAuserProfileData[$_profileID]))
			{
				unset($EAuserProfileData[$_profileID]);
			}
			egw_cache::setCache(egw_cache::INSTANCE,'email','EAuserProfileData'.trim($GLOBALS['egw_info']['user']['account_id']),$EAuserProfileData, $expiration=60*1);

			$nameSpace = egw_cache::getSession('email','defaultimap_nameSpace');
			if (isset($nameSpace[$_profileID]))
			{
				unset($nameSpace[$_profileID]);
				egw_cache::setSession('email','defaultimap_nameSpace',$nameSpace);
			}
			if ($_profileID != 0) self::unsetCachedObjects(0); // reset the default ServerID as well
		}
	}

	/**
	 * getTimeOut
	 *
	 * @param string _use decide if the use is for IMAP or SIEVE, by now only the default differs
	 *
	 * @return int - timeout (either set or default 20/10)
	 */
	static function getTimeOut($_use='IMAP')
	{
		$timeout = $GLOBALS['egw_info']['user']['preferences']['felamimail']['connectionTimeout'];
		if (empty($timeout)) $timeout = ($_use=='SIEVE'?10:20); // this is the default value
		return $timeout;
	}

	/**
	 * Password changed hook --> unset cached objects, as password might be used for email connection
	 *
	 * @param array $hook_data
	 */
	public static function changepassword($hook_data)
	{
		if ($hook_data['account_id'] == $GLOBALS['egw_info']['user']['account_id'])
		{
			self::unsetCachedObjects();
		}
	}

	/**
	 * Get EMailAdmin profile for a user
	 *
	 * @param string $_appName=''
	 * @param int|array $_groups=''
	 * @return ea_preferences
	 */
	function getUserProfile($_appName='', $_groups='', $_profileID='')
	{
		//error_log(__METHOD__.__LINE__." $_appName, $_groups, $_profileID".function_backtrace());
		if (!(is_array(self::$sessionData) && (count(self::$sessionData)>0))) $this->restoreSessionData();
		if (is_array(self::$sessionData) && count(self::$sessionData)>0 && self::$sessionData['ea_preferences'] &&
			($_profileID=='' && count(self::$sessionData['ea_preferences']->icServer) || $_profileID && isset(self::$sessionData['ea_preferences']->icServer[$_profileID])))
		{
			//error_log("sessionData Restored for UserProfile<br>".array2string(self::$sessionData['ea_preferences']));
			return self::$sessionData['ea_preferences'];
		}
		if (empty($_profileID))
		{
			$EAPrefCache = egw_cache::getCache(egw_cache::INSTANCE,'email','EASessionEAPrefs'.trim($GLOBALS['egw_info']['user']['account_id']),$callback=null,$callback_params=array(),$expiration=60*1);
			//error_log(__METHOD__.__LINE__.array2string($EAPrefCache));
			if (!empty($EAPrefCache) && $EAPrefCache instanceof ea_preferences) return $EAPrefCache;
		}
		$appName	= ($_appName != '' ? $_appName : $GLOBALS['egw_info']['flags']['currentapp']);
		if(!is_array($_groups)) {
			// initialize with 0 => means no group id
			$groups = array(0);
			// set the second entry to the users primary group
			$groups[] = $GLOBALS['egw_info']['user']['account_primary_group'];
			$userGroups = $GLOBALS['egw']->accounts->membership($GLOBALS['egw_info']['user']['account_id']);
			foreach((array)$userGroups as $groupInfo) {
				$groups[] = $groupInfo['account_id'];
			}
		} else {
			$groups = $_groups;
		}

		if (!empty($_profileID))
		{
			//error_log(__METHOD__.__LINE__.'#'.$appName.','.array2string($groups).','. $_profileID);
			$data = $this->getProfile($_profileID);
		}
		else
		{
			//error_log(__METHOD__.__LINE__.'#'.$appName.','.array2string($groups).','. $_profileID);
			$data = $this->soemailadmin->getUserProfile($appName, $groups,$GLOBALS['egw_info']['user']['account_id']);
		}
		if($data)
		{
			//error_log(__METHOD__.__LINE__.array2string($data));
			$eaPreferences = CreateObject('emailadmin.ea_preferences');

			// fetch the IMAP / incomming server data
			if (!class_exists($icClass=$data['imapType']))
			{
				if (!file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$icClass.'.inc.php'))
				{
					$file = EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.($icClass='defaultimap').'.inc.php';
				}
				include_once($file);
			}
			$icServer = new $icClass;
			$icServer->ImapServerId	= $data['profileID']*-1;
			$icServer->encryption	= ($data['imapTLSEncryption'] == 'yes' ? 1 : (int)$data['imapTLSEncryption']);
			$icServer->host		= $data['imapServer'];
			$icServer->port 	= $data['imapPort'];
			$icServer->validatecert	= $data['imapTLSAuthentication'] == 'yes';
			$icServer->username 	= $GLOBALS['egw_info']['user']['account_lid'];
			$icServer->password	= $GLOBALS['egw_info']['user']['passwd'];
			// restore the default loginType and check if there are forced/predefined user access Data ($imapAuthType may be set to admin)
			//error_log(__METHOD__.__LINE__.' ServerID:'.$icServer->ImapServerId.' Logintype:'.array2string($data['imapLoginType']));
			list($data['imapLoginType'],$imapAuthType) = explode('#',$data['imapLoginType'],2);
			if (empty($data['imapLoginType'])) $data['imapLoginType'] = 'standard';
			if (empty($imapAuthType)) $imapAuthType = $data['imapLoginType'];
			//error_log(__METHOD__.__LINE__.' ServerID:'.$icServer->ImapServerId.' Logintype:'.array2string($data['imapLoginType']).' AuthType:'.$imapAuthType);
			$icServer->loginType	= $data['imapLoginType'];
			$icServer->domainName	= $data['defaultDomain'];
//			$icServer->loginName 	= $data['imapLoginType'] == 'standard' ? $GLOBALS['egw_info']['user']['account_lid'] : $GLOBALS['egw_info']['user']['account_lid'].'@'.$data['defaultDomain'];
			$icServer->loginName 	= emailadmin_smtp_ldap::mailbox_addr($GLOBALS['egw_info']['user'],$data['defaultDomain'],$data['imapLoginType']);
			$icServer->enableCyrusAdmin = ($data['imapEnableCyrusAdmin'] == 'yes');
			$icServer->adminUsername = $data['imapAdminUsername'];
			$icServer->adminPassword = $data['imapAdminPW'];
			if ( $data['imapEnableCyrusAdmin'] == 'yes' &&
				isset($GLOBALS['egw_info']['server']['cyrus_admin_user']) && isset($GLOBALS['egw_info']['server']['cyrus_admin_password']) &&
				!empty($GLOBALS['egw_info']['server']['cyrus_admin_user']) && !empty($GLOBALS['egw_info']['server']['cyrus_admin_password']) )
			{
				$icServer->adminUsername = $GLOBALS['egw_info']['server']['cyrus_admin_user'];
				$icServer->adminPassword = $GLOBALS['egw_info']['server']['cyrus_admin_password'];
			}
			$icServer->enableSieve	= ($data['imapEnableSieve'] == 'yes');
			if (!empty($data['imapSieveServer']))
			{
				$icServer->sieveHost = $data['imapSieveServer'];
			}
			$icServer->sievePort	= $data['imapSievePort'];
			if ($imapAuthType == 'admin') {
				if (!empty($data['imapAuthUsername'])) $icServer->username = $icServer->loginName = $data['imapAuthUsername'];
				if (!empty($data['imapAuthPassword'])) $icServer->password = $data['imapAuthPassword'];
			}
			if ($imapAuthType == 'email' || $icServer->loginType == 'email') {
				$icServer->username = $icServer->loginName = $GLOBALS['egw_info']['user']['account_email'];
			}
			if (method_exists($icServer,'init')) $icServer->init();
			$eaPreferences->setIncomingServer($icServer,(int)$icServer->ImapServerId);

			// fetch the SMTP / outgoing server data
			if (!class_exists($ogClass=$data['smtpType']))
			{
				if (!file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$ogClass.'.inc.php'))
				{
					$file = EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.($ogClass='emailadmin_smtp').'.inc.php';
				}
				include_once($file);
			}
			$ogServer = new $ogClass($icServer->domainName);
			$ogServer->SmtpServerId	= $data['profileID']*-1;
			$ogServer->host		= $data['smtpServer'];
			$ogServer->port		= $data['smtpPort'];
			$ogServer->editForwardingAddress = ($data['editforwardingaddress'] == 'yes');
			$ogServer->smtpAuth	= ($data['smtpAuth'] == 'yes' || $data['smtpAuth'] == 'ann' );
			if($ogServer->smtpAuth) {
				if(!empty($data['ea_smtp_auth_username']) && $data['smtpAuth'] == 'yes') {
					$ogServer->username 	= $data['ea_smtp_auth_username'];
				} else {
					// if we use special logintypes for IMAP, we assume this to be used for SMTP too
					if ($imapAuthType == 'email' || $icServer->loginType == 'email') {
						$ogServer->username     = $GLOBALS['egw_info']['user']['account_email'];
					} elseif ($icServer->loginType == 'vmailmgr') {
						$ogServer->username     = $GLOBALS['egw_info']['user']['account_lid'].'@'.$icServer->domainName;
					} else {
						$ogServer->username 	= $GLOBALS['egw_info']['user']['account_lid'];
					}
				}
				if(!empty($data['ea_smtp_auth_password']) && $data['smtpAuth'] == 'yes') {
					$ogServer->password     = $data['ea_smtp_auth_password'];
				} else {
					$ogServer->password     = $GLOBALS['egw_info']['user']['passwd'];
				}
			}
			if (method_exists($ogServer,'init')) $ogServer->init();
			$eaPreferences->setOutgoingServer($ogServer,(int)$ogServer->SmtpServerId);

			/*
			 //may be used for debugging mailAlternateAdresses
			 $emailAddresseses[] = array('address'=>$GLOBALS['egw_info']['user']['account_email'],'name'=>$GLOBALS['egw_info']['user']['account_lid'],'type'=>'default');
			 $emailAddresseses[] = array('address'=>'ich@du.de','name'=>'ikke','type'=>'nix');
			 $emailAddresseses[] = array('address'=>'du@ich.de','name'=>'du-wie-ikke','type'=>'nix');
			*/
			$i=$data['profileID']*-1;
			foreach($ogServer->getAccountEmailAddress($GLOBALS['egw_info']['user']['account_lid'])/*$emailAddresseses*/ as $emailAddresses)
			{
				// as we map the identities to ids, and use the first one to idetify the identity of the mainProfile
				// we should take care that our mapping index does not interfere with the profileID
				//if ($i==$data['profileID']) $i--;
				$identity = CreateObject('emailadmin.ea_identity');
				$identity->emailAddress	= $emailAddresses['address'];
				$identity->realName	= $emailAddresses['name'];
				$identity->default	= ($emailAddresses['type'] == 'default');
				$identity->organization	= $data['organisationName'];
				$identity->id = $i;
				// first identity found will be associated with the profileID,
				// others will be set to a negative value, to indicate that they belong to the account
				$eaPreferences->setIdentity($identity,$i);
				$i--;
			}

			$eaPreferences->userDefinedAccounts		= ($data['userDefinedAccounts'] == 'yes');
			$eaPreferences->userDefinedIdentities     = ($data['userDefinedIdentities'] == 'yes' || $data['userDefinedAccounts']=='yes');
			$eaPreferences->ea_user_defined_signatures	= ($data['ea_user_defined_signatures'] == 'yes');
			$eaPreferences->ea_default_signature		= $data['ea_default_signature'];
			if (is_array($data['ea_stationery_active_templates'])) $data['ea_stationery_active_templates'] = implode(',',$data['ea_stationery_active_templates']);
			if(strlen($data['ea_stationery_active_templates']) > 0)
			{
				$eaPreferences->ea_stationery_active_templates = explode(',',$data['ea_stationery_active_templates']);
			}
			self::$sessionData['ea_preferences'] = $eaPreferences;
			if (empty($_profileID)) egw_cache::setCache(egw_cache::INSTANCE,'email','EASessionEAPrefs'.trim($GLOBALS['egw_info']['user']['account_id']),$eaPreferences, $expiration=60*1);
			$this->saveSessionData();
			return $eaPreferences;
		}

		return false;
	}

	/**
	 * Query user data from incomming (IMAP) and outgoing (SMTP) mail-server
	 *
	 * @param int $_accountID
	 * @return array
	 */
	function getUserData($_accountID)
	{
		if($userProfile = $this->getUserProfile('felamimail')) {
			$ogServerKeys = array_keys((array)$userProfile->og_server);
			$profileID = array_shift($ogServerKeys);
			$ogServer = $userProfile->getOutgoingServer($profileID);
			if(($ogServer instanceof emailadmin_smtp)) {
				$ogUserData = $ogServer->getUserData($_accountID);
				//_debug_array($ogUserData);
			}
			// query imap server only, if account is active (or no smtp server configured)
			// imap server tells us stuff about quota only, so we should not query, if the account is forward only
			if (!isset($ogUserData) || $ogUserData['accountStatus'] == 'active')
			{
				$queryIMAP = true;
				if (isset($ogUserData['deliveryMode']) && $ogUserData['deliveryMode']==emailadmin_smtp::FORWARD_ONLY) $queryIMAP=false;
				$icServerKeys = array_keys((array)$userProfile->ic_server);
				$profileID = array_shift($icServerKeys);
				$icServer = $userProfile->getIncomingServer($profileID);
				if(($icServer instanceof defaultimap) && $username = $GLOBALS['egw']->accounts->id2name($_accountID)) {
					$icUserData = ($queryIMAP?$icServer->getUserData($username):array());
					//_debug_array($icUserData);
				}
			}
			// we consider ogServer Data as more recent, assuming ldap is the leading system here (being in control of the attributes it is managing)
			return (array)$ogUserData + (array)$icUserData;
		}

		return false;
	}

	function restoreSessionData()
	{
		$GLOBALS['egw_info']['flags']['autoload'] = array(__CLASS__,'autoload');

		//echo function_backtrace()."<br>";
		//unserializing the sessiondata, since they are serialized for objects sake
		self::$sessionData = (array) unserialize($GLOBALS['egw']->session->appsession('session_data','emailadmin'));
	}

	/**
	* Autoload classes from emailadmin, 'til they get autoloading conform names
	*
	* @param string $class
	*/
	static function autoload($class)
	{
		if (strlen($class)<100)
		{
			if (file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
			}
			elseif (strpos($class,'activesync')===0)
			{
				//temporary solution/hack to fix the false loading of activesync stuff, even as we may not need it for ui
				//but trying to load it blocks the mail app
				//error_log(__METHOD__.__LINE__.' '.$class);
				include_once(EGW_INCLUDE_ROOT.'/activesync/backend/egw.php');
			}

		}
	}

	function saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy)
	{
		if (is_object($this->smtpClass))
		{
			#$smtpClass = CreateObject('emailadmin.'.$this->smtpClass,$this->profileID);
			#$smtpClass->saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy);
			$this->smtpClass->saveSMTPForwarding($_accountID, $_forwardingAddress, $_keepLocalCopy);
		}

	}

	/**
	 * called by the validation hook in setup
	 *
	 * @param array $settings following keys: mail_server, mail_server_type {IMAP|IMAPS|POP-3|POP-3S},
	 *	mail_login_type {standard|vmailmgr}, mail_suffix (domain), smtp_server, smtp_port, smtp_auth_user, smtp_auth_passwd
	 */
	function setDefaultProfile($settings)
	{
		if (($profiles = $this->soemailadmin->getProfileList(0,true)))
		{
			$profile = array_shift($profiles);
			//error_log(__METHOD__.__LINE__.' Found profile 2 merge:'.array2string($profile));
		}
		else
		{
			//error_log(__METHOD__.__LINE__.' Create profile 4 merge');
			$profile = array(
				'smtpType' => 'emailadmin_smtp',
				'description' => 'default profile (created by setup)',
				//'ea_appname' => '', // default is null, and expected to be null if empty
				//'ea_group' => 0,
				//'ea_user' => 0,
				'ea_active' => 1,
				'userDefinedAccounts' => 'yes',
				'userDefinedIdentities' => 'yes',
				'ea_user_defined_signatures' => 'yes',
				'editforwardingaddress' => 'yes',
				'defaultQuota' => 2048,
			);
		}
		foreach($to_parse = array(
			'mail_server' => 'imapServer',
			'mail_server_type' => array(
				'imap' => array(
					'imapType' => 'defaultimap',
					'imapPort' => 143,
					'imapTLSEncryption' => 0,
				),
				'imaps' => array(
					'imapType' => 'defaultimap',
					'imapPort' => 993,
					'imapTLSEncryption' => '3',
				),
			),
			'mail_login_type' => 'imapLoginType',
			'mail_suffix' => 'defaultDomain',
			'smtp_server' => 'smtpServer',
			'smtp_port' => 'smtpPort',
			'smtpAuth' => 'ea_smtp_auth',
			'smtp_auth_user' => 'ea_smtp_auth_username',
			'smtp_auth_passwd' => 'ea_smtp_auth_password',
		) as $setup_name => $ea_name_data)
		{
			if ($setup_name == 'smtp_auth_passwd' && empty($settings[$setup_name]) && !empty($settings['smtp_auth_user']) && $settings['smtpAuth'] != 'no') continue;
			if (!is_array($ea_name_data))
			{
				$profile[$ea_name_data] = $settings[$setup_name];
				//if ($setup_name == 'smtp_auth_user' && $profile['smtpAuth'] == 'no' && !empty($settings['smtp_auth_user'])) $profile['smtpAuth'] = 'yes';
			}
			else
			{
				foreach($ea_name_data as $setup_val => $ea_data)
				{
					if ($setup_val == $settings[$setup_name])
					{
						foreach($ea_data as $var => $val)
						{
							if ($var != 'imapType' || $val != 'defaultimap') // old code: || $profile[$var] < 3)	// dont kill special imap server types
							{
								$profile[$var] = $val;
							}
						}
						break;
					}
				}
			}
		}
		// merge the other not processed values unchanged
		$profile = array_merge($profile,array_diff_assoc($settings,$to_parse));
		//error_log(__METHOD__.__LINE__.' Profile to Save:'.array2string($profile));
		//error_log(__METHOD__.__LINE__.' Profile to Parse:'.array2string($to_parse));

		// ToDo Klaus: use $this->save to store profile and move logic for storing defaultQuota there
		if (($profileID = $this->soemailadmin->updateProfile($profile)) &&
			!empty($profile['imapType']) &&
			($profile['defaultQuota'] > 0 || (string)$profile['defaultQuota'] === '0') &&
			stripos(constant($profile['imapType'].'::CAPABILITIES'), 'providedefaultquota') !==false)
		{
			$classname = $profile['imapType'];
			$classname::setDefaultQuota($profile['defaultQuota']);
		}
		self::$sessionData['profile'] = array();
		$this->saveSessionData();
		//echo "<p>EMailAdmin profile update: ".print_r($profile,true)."</p>\n"; exit;
		return $profileID;
	}

	function saveSessionData()
	{
		// serializing the session data, for the sake of objects
		if (is_object($GLOBALS['egw']->session))	// otherwise setup(-cli) fails
		{
			$GLOBALS['egw']->session->appsession('session_data','emailadmin',serialize(self::$sessionData));
		}
		#$GLOBALS['egw']->session->appsession('user_session_data','',$this->userSessionData);
	}

	function saveUserData($_accountID, $_formData) {

		if($userProfile = $this->getUserProfile('felamimail'))
		{
			$ogServerKeys = array_keys((array)$userProfile->og_server);
			$profileID = array_shift($ogServerKeys);
			$ogServer = $userProfile->getOutgoingServer($profileID);
			if(($ogServer instanceof emailadmin_smtp)) {
				$ogServer->setUserData($_accountID,
					(array)$_formData['mailAlternateAddress'],
					(array)$_formData['mailForwardingAddress'],
					$_formData['deliveryMode'],
					$_formData['accountStatus'],
					$_formData['mailLocalAddress'],
					$_formData['quotaLimit']
				);
			}

			$icServerKeys = array_keys((array)$userProfile->ic_server);
			$profileID = array_shift($icServerKeys);
			$icServer = $userProfile->getIncomingServer($profileID);
			if(($icServer instanceof defaultimap) && $username = $GLOBALS['egw']->accounts->id2name($_accountID)) {
				$icServer->setUserData($username, $_formData['quotaLimit']);
			}

			// calling a hook to allow other apps to monitor the changes
			$_formData['account_id'] = $_accountID;
			$_formData['location'] = 'editaccountemail';
			$GLOBALS['egw']->hooks->process($_formData);

			return true;
			self::$sessionData = array();
			$this->saveSessionData();
		}

		return false;
	}

	function setOrder($_order) {
		if(is_array($_order)) {
			$this->soemailadmin->setOrder($_order);
		}
		self::$sessionData = array();
		$this->saveSessionData();
	}

	function updateAccount($_hookValues) {
		if (is_object($this->imapClass)) {
			#ExecMethod("emailadmin.".$this->imapClass.".updateAccount",$_hookValues,3,$this->profileData);
			$this->imapClass->updateAccount($_hookValues);
		}

		if (is_object($this->smtpClass)) {
			#ExecMethod("emailadmin.".$this->smtpClass.".updateAccount",$_hookValues,3,$this->profileData);
			$this->smtpClass->updateAccount($_hookValues);
		}
		self::$sessionData = array();
		$this->saveSessionData();
	}

	/**
	 * Get ID of default profile
	 *
	 * ID is negative for FMail, which used positive ID's for user profiles!
	 *
	 * @return int
	 */
	static function getDefaultProfileID()
	{
		$soemailadmin = new emailadmin_so();
		if (($profiles = $soemailadmin->getProfileList(0, true)))
		{
			$default_profile = array_shift($profiles);

			return -$default_profile['profileID'];
		}
	}

	/**
	 * Get ID of User specific default profile
	 *
	 * ID is negative for FMail, which used positive ID's for user profiles!
	 *
	 * @return int
	 */
	static function getUserDefaultProfileID()
	{
		$groups = array(0);
		// set the second entry to the users primary group
		$groups[] = $GLOBALS['egw_info']['user']['account_primary_group'];
		$userGroups = $GLOBALS['egw']->accounts->membership($GLOBALS['egw_info']['user']['account_id']);
		foreach((array)$userGroups as $groupInfo) {
			$groups[] = $groupInfo['account_id'];
		}
		$soemailadmin = new emailadmin_so();
		if (($profile = $soemailadmin->getUserProfile('felamimail',$groups,$GLOBALS['egw_info']['user']['account_id'])))
		{
			$default_profile = $profile['profileID']*-1;;

			return $default_profile;
		}
	}
}
