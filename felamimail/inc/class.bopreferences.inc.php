<?php
	/***************************************************************************\
	* eGroupWare - FeLaMiMail                                                   *
	* http://www.linux-at-work.de                                               *
	* http://www.phpgw.de                                                       *
	* http://www.egroupware.org                                                 *
	* Written by : Lars Kneschke [lkneschke@linux-at-work.de]                   *
	* -------------------------------------------------                         *
	* This program is free software; you can redistribute it and/or modify it   *
	* under the terms of the GNU General Public License as published by the     *
	* Free Software Foundation; either version 2 of the License, or (at your    *
	* option) any later version.                                                *
	\***************************************************************************/
	/* $Id: class.bopreferences.inc.php 42768 2013-06-13 14:13:01Z leithoff $ */

	require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.sopreferences.inc.php');

	class bopreferences extends sopreferences
	{
		var $public_functions = array
		(
			'getPreferences'	=> True,
		);

		// stores the users profile
		var $profileData;
		var $sessionData;
		var $boemailadmin;

		function bopreferences($_restoreSession = true)
		{
			//error_log(__METHOD__." called ".print_r($_restoreSession,true).function_backtrace());
			parent::sopreferences();
			$this->boemailadmin = new emailadmin_bo(false,$_restoreSession);
			if ($_restoreSession && !(is_array($this->sessionData) && (count($this->sessionData)>0))  ) $this->restoreSessionData();
			if ($_restoreSession===false && (is_array($this->sessionData) && (count($this->sessionData)>0))  )
			{
				//error_log(__METHOD__." Unset Session ".function_backtrace());
				//make sure session data will be reset
				$this->sessionData = array();
				$this->profileData = array();
				self::saveSessionData();
			}
			//error_log(__METHOD__.print_r($this->sessionData,true));
			if (isset($this->sessionData['profileData']) && ($this->sessionData['profileData'] instanceof ea_preferences)) {
				$this->profileData = $this->sessionData['profileData'];
			}
		}

		function restoreSessionData()
		{
			//error_log(__METHOD__." Session restore ".function_backtrace());
			// set an own autoload function, search emailadmin for missing classes
			$GLOBALS['egw_info']['flags']['autoload'] = array(__CLASS__,'autoload');

			$this->sessionData = (array) unserialize($GLOBALS['egw']->session->appsession('fm_preferences','felamimail'));
		}

		/**
		 * Autoload classes from emailadmin, 'til they get autoloading conform names
		 *
		 * @param string $class
		 */
		static function autoload($class)
		{
			if (file_exists($file=EGW_INCLUDE_ROOT.'/emailadmin/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
				//error_log(__METHOD__."($class) included $file");
			}
			elseif (file_exists($file=EGW_INCLUDE_ROOT.'/felamimail/inc/class.'.$class.'.inc.php'))
			{
				include_once($file);
			}
			else
			{
				#error_log(__METHOD__."($class) failed!");
			}
		}

		function saveSessionData()
		{
			$GLOBALS['egw']->session->appsession('fm_preferences','felamimail',serialize($this->sessionData));
		}

		// get the first active user defined account
		function getAccountData(&$_profileData, $_accountID=NULL)
		{
			#echo "<p>backtrace: ".function_backtrace()."</p>\n";
			if(!($_profileData instanceof ea_preferences))
				die(__FILE__.': '.__LINE__);
			$accountData = parent::getAccountData($GLOBALS['egw_info']['user']['account_id'],$_accountID);

			// currently we use only the first profile available
			$accountData = array_shift($accountData);
			//_debug_array($accountData);

			$icServer = CreateObject('emailadmin.defaultimap');
			$icServer->ImapServerId	= $accountData['id'];
			$icServer->encryption	= isset($accountData['ic_encryption']) ? $accountData['ic_encryption'] : 1;
			$icServer->host		= $accountData['ic_hostname'];
			$icServer->port 	= isset($accountData['ic_port']) ? $accountData['ic_port'] : 143;
			$icServer->validatecert	= isset($accountData['ic_validatecertificate']) ? (bool)$accountData['ic_validatecertificate'] : 1;
			$icServer->username 	= $accountData['ic_username'];
			$icServer->loginName 	= $accountData['ic_username'];
			$icServer->password	= $accountData['ic_password'];
			$icServer->enableSieve	= isset($accountData['ic_enable_sieve']) ? (bool)$accountData['ic_enable_sieve'] : 1;
			$icServer->sieveHost	= $accountData['ic_sieve_server'];
			$icServer->sievePort	= isset($accountData['ic_sieve_port']) && !empty($accountData['ic_sieve_port']) ? $accountData['ic_sieve_port'] : 4190;
			if ($accountData['ic_folderstoshowinhome']) $icServer->folderstoshowinhome	= $accountData['ic_folderstoshowinhome'];
			if ($accountData['ic_trashfolder']) $icServer->trashfolder = $accountData['ic_trashfolder'];
			if ($accountData['ic_sentfolder']) $icServer->sentfolder = $accountData['ic_sentfolder'];
			if ($accountData['ic_draftfolder']) $icServer->draftfolder = $accountData['ic_draftfolder'];
			if ($accountData['ic_templatefolder']) $icServer->templatefolder = $accountData['ic_templatefolder'];

			$ogServer = new emailadmin_smtp();
			$ogServer->SmtpServerId	= $accountData['id'];
			$ogServer->host		= $accountData['og_hostname'];
			$ogServer->port		= isset($accountData['og_port']) ? $accountData['og_port'] : 25;
			$ogServer->smtpAuth	= (bool)$accountData['og_smtpauth'];
			if($ogServer->smtpAuth) {
				$ogServer->username 	= $accountData['og_username'];
				$ogServer->password 	= $accountData['og_password'];
			}

			$identity = CreateObject('emailadmin.ea_identity');
			$identity->emailAddress	= $accountData['emailaddress'];
			$identity->realName	= $accountData['realname'];
			//$identity->default	= true;
			$identity->default = (bool)$accountData['active'];
			$identity->organization	= $accountData['organization'];
			$identity->signature = $accountData['signatureid'];
			$identity->id  = $accountData['id'];

			$isActive = (bool)$accountData['active'];

			return array('icServer' => $icServer, 'ogServer' => $ogServer, 'identity' => $identity, 'active' => $isActive);
		}

		function getAllAccountData(&$_profileData)
		{
			if(!($_profileData instanceof ea_preferences))
				die(__FILE__.': '.__LINE__);
			$AllAccountData = parent::getAccountData($GLOBALS['egw_info']['user']['account_id'],'all');
			#_debug_array($accountData);
			foreach ($AllAccountData as $key => $accountData)
			{
				$icServer = CreateObject('emailadmin.defaultimap');
				$icServer->ImapServerId	= $accountData['id'];
				$icServer->encryption	= isset($accountData['ic_encryption']) ? $accountData['ic_encryption'] : 1;
				$icServer->host		= $accountData['ic_hostname'];
				$icServer->port 	= isset($accountData['ic_port']) ? $accountData['ic_port'] : 143;
				$icServer->validatecert	= isset($accountData['ic_validatecertificate']) ? (bool)$accountData['ic_validatecertificate'] : 1;
				$icServer->username 	= $accountData['ic_username'];
				$icServer->loginName 	= $accountData['ic_username'];
				$icServer->password	= $accountData['ic_password'];
				$icServer->enableSieve	= isset($accountData['ic_enable_sieve']) ? (bool)$accountData['ic_enable_sieve'] : 1;
				$icServer->sieveHost	= $accountData['ic_sieve_server'];
				$icServer->sievePort	= isset($accountData['ic_sieve_port']) && !empty($accountData['ic_sieve_port']) ? $accountData['ic_sieve_port'] : 4190;
				if ($accountData['ic_folderstoshowinhome']) $icServer->folderstoshowinhome = $accountData['ic_folderstoshowinhome'];
				if ($accountData['ic_trashfolder']) $icServer->trashfolder = $accountData['ic_trashfolder'];
				if ($accountData['ic_sentfolder']) $icServer->sentfolder = $accountData['ic_sentfolder'];
				if ($accountData['ic_draftfolder']) $icServer->draftfolder = $accountData['ic_draftfolder'];
				if ($accountData['ic_templatefolder']) $icServer->templatefolder = $accountData['ic_templatefolder'];

				$ogServer = new emailadmin_smtp();
				$ogServer->SmtpServerId	= $accountData['id'];
				$ogServer->host		= $accountData['og_hostname'];
				$ogServer->port		= isset($accountData['og_port']) ? $accountData['og_port'] : 25;
				$ogServer->smtpAuth	= (bool)$accountData['og_smtpauth'];
				if($ogServer->smtpAuth) {
					$ogServer->username 	= $accountData['og_username'];
					$ogServer->password 	= $accountData['og_password'];
				}

				$identity = CreateObject('emailadmin.ea_identity');
				$identity->emailAddress	= $accountData['emailaddress'];
				$identity->realName	= $accountData['realname'];
				//$identity->default	= true;
				$identity->default = (bool)$accountData['active'];
				$identity->organization	= $accountData['organization'];
				$identity->signature = $accountData['signatureid'];
				$identity->id  = $accountData['id'];
				$isActive = (bool)$accountData['active'];
				$out[$accountData['id']] = array('icServer' => $icServer, 'ogServer' => $ogServer, 'identity' => $identity, 'active' => $isActive);
			}
			return $out;
		}

		function getUserDefinedIdentities()
		{
			$profileID = emailadmin_bo::getUserDefaultProfileID();
			$profileData        = $this->boemailadmin->getUserProfile('felamimail');
			if(!($profileData instanceof ea_preferences) || !($profileData->ic_server[$profileID] instanceof defaultimap)) {
				return false;
			}
			if($profileData->userDefinedAccounts || $profileData->userDefinedIdentities)
			{
				// get user defined accounts
				$allAccountData = $this->getAllAccountData($profileData);
				if ($allAccountData)
				{
					foreach ($allAccountData as $tmpkey => $accountData)
					{
						$accountArray[] = $accountData['identity'];
					}
					return $accountArray;
				}
			}
			return array();
		}

		/**
		 * getPreferences - fetches the active profile for a user
		 *
		 * @param boolean $getUserDefinedProfiles
		 * @param int $_profileID - use this profile to be set its prefs as active profile (0)
		 * @param string $_appName - the app the profile is fetched for
		 * @param int $_singleProfileToFetch - single Profile to fetch no merging of profileData; emailadminprofiles only; for Administrative use only (by now)
		 * @return object ea_preferences object with the active emailprofile set to ID = 0
		 */
		function getPreferences($getUserDefinedProfiles=true,$_profileID=0,$_appName='felamimail',$_singleProfileToFetch=0)
		{
			if (isset($this->sessionData['profileData']) && ($this->sessionData['profileData'] instanceof ea_preferences))
			{
				$this->profileData = $this->sessionData['profileData'];
			}

			if((!($this->profileData instanceof ea_preferences) && $_singleProfileToFetch==0) || ($_singleProfileToFetch!=0 && !isset($this->profileData->icServer[$_singleProfileToFetch])))
			{
				$GLOBALS['egw']->preferences->read_repository();
				$userPreferences = $GLOBALS['egw_info']['user']['preferences']['felamimail'];
				$imapServerTypes	= $this->boemailadmin->getIMAPServerTypes();
				$profileData = $this->boemailadmin->getUserProfile($_appName,'',($_singleProfileToFetch<0?-$_singleProfileToFetch:'')); // by now we assume only one profile to be returned
				$icServerKeys = array_keys((array)$profileData->ic_server);
				$icProfileID = array_shift($icServerKeys);
				$ogServerKeys = array_keys((array)$profileData->og_server);
				$ogProfileID = array_shift($ogServerKeys);
				//error_log(__METHOD__.__LINE__.' ServerProfile(s)Fetched->'.array2string(count($profileData->ic_server)).':'.array2string($icProfileID));
				//may be needed later on, as it may hold users Identities connected to MailAlternateAdresses
				$IdIsDefault = 0;
				$rememberIdentities = $profileData->identities;
				foreach ($rememberIdentities as $adkey => $ident)
				{
					if ($ident->default) $IdIsDefault = $ident->id;
					$profileData->identities[$adkey]->default = false;
				}

				if(!($profileData instanceof ea_preferences) || !($profileData->ic_server[$icProfileID] instanceof defaultimap))
				{
					return false;
				}
				// set the emailadminprofile as profile 0; it will be assumed the active one (if no other profiles are active)
				$profileData->setIncomingServer($profileData->ic_server[$icProfileID],0);
				$profileID = $icProfileID;
				$profileData->setOutgoingServer($profileData->og_server[$ogProfileID],0);
				$profileData->setIdentity($profileData->identities[$icProfileID],0);
				$userPrefs = $this->mergeUserAndProfilePrefs($userPreferences,$profileData,$icProfileID);
				$rememberID = array(); // there may be more ids to be rememered
				$maxId = $icProfileID>0?$icProfileID:0;
				$minId = $icProfileID<0?$icProfileID:0;
				//$profileData->setPreferences($userPrefs,0);
				if($profileData->userDefinedAccounts && $GLOBALS['egw_info']['user']['apps']['felamimail'] && $getUserDefinedProfiles)
				{
					// get user defined accounts (only fetch the active one(s), as we call it without second parameter)
					// we assume only one account may be active at once
					$allAccountData = $this->getAllAccountData($profileData);
					foreach ((array)$allAccountData as $k => $accountData)
					{
						// set defined IMAP server
						if(($accountData['icServer'] instanceof defaultimap))
						{
							$profileData->setIncomingServer($accountData['icServer'],$k);
							$userPrefs = $this->mergeUserAndProfilePrefs($userPreferences,$profileData,$k);
							//$profileData->setPreferences($userPrefs,$k);
						}
						// set defined SMTP Server
						if(($accountData['ogServer'] instanceof emailadmin_smtp))
							$profileData->setOutgoingServer($accountData['ogServer'],$k);

						if(($accountData['identity'] instanceof ea_identity))
						{
							$profileData->setIdentity($accountData['identity'],$k);
							$rememberID[] = $k; // remember Identity as already added
							if ($k>0 && $k>$maxId) $maxId = $k;
							if ($k<0 && $k<$minId) $minId = $k;
						}

						if (empty($_profileID))
						{
							$setAsActive = $accountData['active'];
							//if($setAsActive) error_log(__METHOD__.__LINE__." Setting Profile with ID=$k (using Active Info) for ActiveProfile");
						}
						else
						{
							$setAsActive = ($_profileID==$k);
							//if($setAsActive) error_log(__METHOD__.__LINE__." Setting Profile with ID=$_profileID for ActiveProfile");
						}
						if($setAsActive)
						{
							// replace the global defined IMAP Server
							if(($accountData['icServer'] instanceof defaultimap))
							{
								$profileID = $k;
								$profileData->setIncomingServer($accountData['icServer'],0);
								$userPrefs = $this->mergeUserAndProfilePrefs($userPreferences,$profileData,$k);
								//$profileData->setPreferences($userPrefs,0);
							}

							// replace the global defined SMTP Server
							if(($accountData['ogServer'] instanceof emailadmin_smtp))
								$profileData->setOutgoingServer($accountData['ogServer'],0);

							// replace the global defined identity
							if(($accountData['identity'] instanceof ea_identity)) {
								//_debug_array($profileData);
								$profileData->setIdentity($accountData['identity'],0);
								$profileData->identities[0]->default = true;
								$rememberID[] = $IdIsDefault = $accountData['identity']->id;
							}
						}
					}
				}
				if($profileData->userDefinedIdentities && $GLOBALS['egw_info']['user']['apps']['felamimail'])
				{
					$allUserIdentities = $this->getUserDefinedIdentities();
					if (is_array($allUserIdentities))
					{
						$i=$maxId+1;
						$y=$minId-1;
						foreach ($allUserIdentities as $tmpkey => $id)
						{
							if (!in_array($id->id,$rememberID))
							{
								$profileData->setIdentity($id,$i);
								$i++;
							}
						}
					}
				}
				// make sure there is one profile marked as default (either 0 or the one found)
				$markedAsDefault = false;
				foreach ($profileData->identities as &$id)
				{
					if ($id->id == $idIsDefault)
					{
						$id->default = true;
						$markedAsDefault = true;
					}
				}
				if ($markedAsDefault == false) $profileData->identities[0]->default = true;

				$userPrefs = $this->mergeUserAndProfilePrefs($userPreferences,$profileData,$profileID);
				$profileData->setPreferences($userPrefs);

				//_debug_array($profileData);#exit;
				$this->sessionData['profileData'] = $this->profileData = $profileData;
				$this->saveSessionData();
				//_debug_array($this->profileData);
			}
			return $this->profileData;
		}

		function mergeUserAndProfilePrefs($userPrefs, &$profileData, $profileID)
		{
			// echo "<p>backtrace: ".function_backtrace()."</p>\n";
			if (is_array($profileData->ic_server[$profileID]->folderstoshowinhome) && !empty($profileData->ic_server[$profileID]->folderstoshowinhome[0]))
			{
				$userPrefs['mainscreen_showfolders'] = implode(',',$profileData->ic_server[$profileID]->folderstoshowinhome);
			}
			if (!empty($profileData->ic_server[$profileID]->sentfolder)) $userPrefs['sentFolder'] = $profileData->ic_server[$profileID]->sentfolder;
			if (!empty($profileData->ic_server[$profileID]->trashfolder)) $userPrefs['trashFolder'] = $profileData->ic_server[$profileID]->trashfolder;
			if (!empty($profileData->ic_server[$profileID]->draftfolder)) $userPrefs['draftFolder'] = $profileData->ic_server[$profileID]->draftfolder;
			if (!empty($profileData->ic_server[$profileID]->templatefolder)) $userPrefs['templateFolder'] = $profileData->ic_server[$profileID]->templatefolder;
			if(empty($userPrefs['deleteOptions']))
				$userPrefs['deleteOptions'] = 'mark_as_deleted';

			if (!empty($userPrefs['trash_folder']))
				$userPrefs['move_to_trash'] 	= True;
			if (!empty($userPrefs['sent_folder']))
			{
				if (!isset($userPrefs['sendOptions']) || empty($userPrefs['sendOptions'])) $userPrefs['sendOptions'] = 'move_to_sent';
			}
			/* not used anymore
			if (!empty($userPrefs['email_sig'])) $userPrefs['signature'] = $userPrefs['email_sig'];
			*/
 			if (isset($userPrefs['email_sig'])) unset($userPrefs['email_sig']);
			return $userPrefs;
		}

		function ggetSignature($_signatureID, $_unparsed = false)
		{
			if($_signatureID == -1) {
				$profileData = $this->boemailadmin->getUserProfile('felamimail');

				$systemSignatureIsDefaultSignature = !parent::getDefaultSignature($GLOBALS['egw_info']['user']['account_id']);

				$systemSignature = array(
					'signatureid'		=> -1,
					'description'		=> 'eGroupWare '. lang('default signature'),
					'signature'		=> ($_unparsed === true ? $profileData->ea_default_signature : $GLOBALS['egw']->preferences->parse_notify($profileData->ea_default_signature)),
					'defaultsignature'	=> $systemSignatureIsDefaultSignature,
				);

				return $systemSignature;

			} else {
				require_once('class.felamimail_signatures.inc.php');
				$signature = new felamimail_signatures($_signatureID);
				if($_unparsed === false) {
					$signature->fm_signature = $GLOBALS['egw']->preferences->parse_notify($signature->fm_signature);
				}
				return $signature;
			}
		}

		function ggetDefaultSignature()
		{
			return parent::getDefaultSignature($GLOBALS['egw_info']['user']['account_id']);
		}

		function ddeleteSignatures($_signatureID)
		{
			if(!is_array($_signatureID)) {
				return false;
			}
			return parent::deleteSignatures($GLOBALS['egw_info']['user']['account_id'], $_signatureID);
		}

		function saveAccountData($_icServer, $_ogServer, $_identity)
		{
			if(is_object($_icServer) && !isset($_icServer->validatecert)) {
				$_icServer->validatecert = true;
			}
			if(isset($_icServer->host)) {
				$_icServer->sieveHost = $_icServer->host;
			}
			$this->sessionData = array();
			$this->saveSessionData();
			return parent::saveAccountData($GLOBALS['egw_info']['user']['account_id'], $_icServer, $_ogServer, $_identity);
		}

		function deleteAccountData($_identity)
		{
			if (is_array($_identity)) {
				foreach ($_identity as $tmpkey => $id)
				{
					if ($id->id) {
						$identity[] = $id->id;
					} else {
						$identity[] = $id;
					}
				}
			} else {
				$identity = $_identity;
			}
			$this->sessionData = array();
			$this->saveSessionData();
			parent::deleteAccountData($GLOBALS['egw_info']['user']['account_id'], $identity);
		}

		function ssaveSignature($_signatureID, $_description, $_signature, $_isDefaultSignature)
		{
			return parent::saveSignature($GLOBALS['egw_info']['user']['account_id'], $_signatureID, $_description, $_signature, (bool)$_isDefaultSignature);
		}

		function setProfileActive($_status, $_identity=NULL)
		{
			$this->sessionData = array();
			$this->saveSessionData();
			parent::setProfileActive($GLOBALS['egw_info']['user']['account_id'], $_status, $_identity);
		}
	}
?>
