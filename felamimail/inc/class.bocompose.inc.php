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
	* Free Software Foundation; version 2 of the License.                       *
	\***************************************************************************/
	/* $Id: class.bocompose.inc.php 42328 2013-04-23 10:22:16Z leithoff $ */

	class bocompose
	{
		var $public_functions = array
		(
			'addAtachment'	=> True,
			'action'	=> True
		);

		var $attachments;	// Array of attachments
		var $preferences;	// the prefenrences(emailserver, username, ...)
		var $preferencesArray;
		var $bofelamimail;
		var $bopreferences;
		var $bosignatures;
		var $displayCharset;

		function bocompose($_composeID = '', $_charSet = 'iso-8859-1')
		{
			$this->displayCharset	= strtolower($_charSet);
			$this->bosignatures	= CreateObject('felamimail.felamimail_bosignatures');
			$this->bofelamimail	= CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$this->bopreferences =& $this->bofelamimail->bopreferences;
			$this->preferences	= $this->bopreferences->getPreferences();
			$this->preferencesArray =& $GLOBALS['egw_info']['user']['preferences']['felamimail'];
			//force the default for the forwarding -> asmail
			if (is_array($this->preferencesArray)) {
				if (!array_key_exists('message_forwarding',$this->preferencesArray)
					|| !isset($this->preferencesArray['message_forwarding'])
					|| empty($this->preferencesArray['message_forwarding'])) $this->preferencesArray['message_forwarding'] = 'asmail';
			} else {
				$this->preferencesArray['message_forwarding'] = 'asmail';
			}
			// check if there is a composeid set, and restore the session, if so
			if (!empty($_composeID))
			{
				$this->composeID = $_composeID;
				$this->restoreSessionData();
			}
			else	// new email
			{
				$this->setDefaults();
			}
		}

		/**
		 * adds uploaded files or files in eGW's temp directory as attachments
		 *
		 * It also stores the given data in the session
		 *
		 * @param array $_formData fields of the compose form (to,cc,bcc,reply_to,subject,body,priority,signature), plus data of the file (name,file,size,type)
		 */
		function addAttachment($_formData)
		{
			$attachfailed = false;
			// to gard against exploits the file must be either uploaded or be in the temp_dir
			// check if formdata meets basic restrictions (in tmp dir, or vfs, mimetype, etc.)
			try
			{
				$tmpFileName = bofelamimail::checkFileBasics($_formData,$this->composeID,false);
			}
			catch (egw_exception_wrong_userinput $e)
			{
				$attachfailed = true;
				$alert_msg = $e->getMessage();
			}

			if ($attachfailed === false)
			{
				$buffer = array(
					'name'	=> $_formData['name'],
					'type'	=> $_formData['type'],
					'file'	=> $tmpFileName,
					'size'	=> $_formData['size']
				);
				// trying diiferent ID-ing Method, as getRandomString seems to produce non Random String on certain systems.
				$attachmentID = md5(time().serialize($buffer));
				//error_log(__METHOD__." add Attachment with ID:".$attachmentID." (md5 of serialized array)");
				$this->sessionData['attachments'][$attachmentID] = $buffer;
				unset($buffer);
			}
			else
			{
				error_log(__METHOD__.__LINE__.array2string($alert_msg));
			}

			$this->saveSessionData();
			#print"<pre>";
			#error_log(print_r($this->sessionData,true));
			#print"</pre>";exit;
		}

		function addMessageAttachment($_uid, $_partID, $_folder, $_name, $_type, $_size)
		{
			$this->sessionData['attachments'][]=array (
				'uid'		=> $_uid,
				'partID'	=> $_partID,
				'name'		=> $_name,
				'type'		=> $_type,
				'size'		=> $_size,
				'folder'	=> $_folder
			);
			$this->saveSessionData();
		}

		/**
		* replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
		* always returns 1
		*/
		static function replaceEmailAdresses(&$text)
		{
			// replace emailaddresses eclosed in <> (eg.: <me@you.de>) with the emailaddress only (e.g: me@you.de)
			bofelamimail::replaceEmailAdresses($text);
			return 1;
		}

		function convertHTMLToText(&$_html,$sourceishtml = true)
		{
			$stripalltags = true;
			// third param is stripalltags, we may not need that, if the source is already in ascii
			if (!$sourceishtml) $stripalltags=false;
			return bofelamimail::convertHTMLToText($_html,false,$stripalltags);
		}

		function convertHTMLToTextTiny($_html)
		{
			print "<pre>"; print htmlspecialchars($_html); print "</pre>";
			// remove these tags and any spaces behind the tags
			$search = array('/<p.*?> */', '/<.?strong>/', '/<.?em>/', '/<.?u>/', '/<.?ul> */', '/<.?ol> */', '/<.?font.*?> */', '/<.?blockquote> */');
			$replace = '';
			$text = preg_replace($search, $replace, $_html);

			// convert these tags and any spaces behind the tags to line breaks
			$search = array('/<\/li> */', '/<br \/> */');
			$replace = "\r\n";
			$text = preg_replace($search, $replace, $text);

			// convert these tags and any spaces behind the tags to double line breaks
			$search = array('/&nbsp;<\/p> */', '/<\/p> */');
			$replace = "\r\n\r\n";
			$text = preg_replace($search, $replace, $text);

			// special replacements
			$search = array('/<li>/');
			$replace = array('  * ');

			$text = preg_replace($search, $replace, $text);

			$text = html_entity_decode($text, ENT_COMPAT, $this->displayCharset);

			print "<pre>"; print htmlspecialchars($text); print "</pre>"; exit;

			return $text;
		}

		function generateRFC822Address($_addressObject)
		{
			if(!empty($_addressObject->personal) && !empty($_addressObject->mailbox) && !empty($_addressObject->host)) {
				return sprintf('"%s" <%s@%s>', $this->bofelamimail->decode_header($_addressObject->personal), $_addressObject->mailbox, $_addressObject->host);
			} elseif(!empty($_addressObject->mailbox) && !empty($_addressObject->host)) {
				return sprintf("%s@%s", $_addressObject->mailbox, $_addressObject->host);
			} else {
				return $_addressObject->mailbox;
			}
		}

		// create a hopefully unique id, to keep track of different compose windows
		// if you do this, you are creating a new email
		function getComposeID()
		{
			$this->composeID = $this->getRandomString();

			$this->setDefaults();

			return $this->composeID;
		}

		// $_mode can be:
		// single: for a reply to one address
		// all: for a reply to all
		function getDraftData($_icServer, $_folder, $_uid, $_partID=NULL)
		{
			$this->sessionData['to'] = array();

			$bofelamimail =& $this->bofelamimail; //CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$bofelamimail->openConnection();
			$bofelamimail->reopen($_folder);

			// the array $userEMailAddresses was used for filtering out emailaddresses that are owned by the user, for draft data we should not do this
			//$userEMailAddresses = $this->preferences->getUserEMailAddresses();

			// get message headers for specified message
			#$headers	= $bofelamimail->getMessageHeader($_folder, $_uid);
			$headers	= $bofelamimail->getMessageEnvelope($_uid, $_partID);
			$addHeadInfo = $bofelamimail->getMessageHeader($_uid, $_partID);
			if (!empty($addHeadInfo['X-MAILFOLDER'])) {
				foreach ( explode('|',$addHeadInfo['X-MAILFOLDER']) as $val ) {
					$this->sessionData['folder'][] = $val;
				}
			}
			if (!empty($addHeadInfo['X-SIGNATURE'])) {
				$this->sessionData['signatureID'] = $addHeadInfo['X-SIGNATURE'];
			}
			if (!empty($addHeadInfo['X-STATIONERY'])) {
				$this->sessionData['stationeryID'] = $addHeadInfo['X-STATIONERY'];
			}
			if (!empty($addHeadInfo['X-IDENTITY'])) {
				$this->sessionData['identity'] = $addHeadInfo['X-IDENTITY'];
			}
			$this->sessionData['uid'] = $_uid;
			$this->sessionData['messageFolder'] = $_folder;
			$this->sessionData['isDraft'] = true;
			foreach((array)$headers['CC'] as $val) {
				if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
					continue;
				}

				//if($userEMailAddresses[$val['EMAIL']]) {
				//	continue;
				//}

				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->bofelamimail->decode_header($address);
					$this->sessionData['cc'][] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}

			foreach((array)$headers['TO'] as $val) {
				if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
					continue;
				}

				//if($userEMailAddresses[$val['EMAIL']]) {
				//	continue;
				//}

				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->bofelamimail->decode_header($address);
					$this->sessionData['to'][] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}

			foreach((array)$headers['REPLY_TO'] as $val) {
				if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
					continue;
				}

				//if($userEMailAddresses[$val['EMAIL']]) {
				//	continue;
				//}

				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->bofelamimail->decode_header($address);
					$this->sessionData['replyto'][] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}

			foreach((array)$headers['BCC'] as $val) {
				if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
					continue;
				}

				//if($userEMailAddresses[$val['EMAIL']]) {
				//	continue;
				//}

				if(!$foundAddresses[$val['EMAIL']]) {
					$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
					$address = $this->bofelamimail->decode_header($address);
					$this->sessionData['bcc'][] = $address;
					$foundAddresses[$val['EMAIL']] = true;
				}
			}

			$this->sessionData['subject']	= $bofelamimail->decode_header($headers['SUBJECT']);
			// remove a printview tag if composing
			$searchfor = '/^\['.lang('printview').':\]/';
			$this->sessionData['subject'] = preg_replace($searchfor,'',$this->sessionData['subject']);
			$bodyParts = $bofelamimail->getMessageBody($_uid, $this->preferencesArray['always_display'], $_partID);
			//_debug_array($bodyParts);

			#$fromAddress = ($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? $headers['FROM'][0]['RFC822_EMAIL'] : $headers['FROM'][0]['EMAIL'];
			if($bodyParts['0']['mimeType'] == 'text/html') {
				$this->sessionData['mimeType'] 	= 'html';

				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= '<hr>';
					}
					if($bodyParts[$i]['mimeType'] == 'text/plain') {
						#$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body']);
						$bodyParts[$i]['body'] = "<pre>".$bodyParts[$i]['body']."</pre>";
					}
					$bodyParts[$i]['body'] = $GLOBALS['egw']->translation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
					#error_log( "GetDraftData (HTML) CharSet:".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
					$this->sessionData['body'] .= "<br>". $bodyParts[$i]['body'] ;
				}

			} else {
				$this->sessionData['mimeType']	= 'plain';

				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= "<hr>";
					}
					$bodyParts[$i]['body'] = $GLOBALS['egw']->translation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
					#error_log( "GetDraftData (Plain) CharSet".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));
					$this->sessionData['body'] .= "\r\n". $bodyParts[$i]['body'] ;
				}
			}

			if($attachments = $bofelamimail->getMessageAttachments($_uid,$_partID)) {
				foreach($attachments as $attachment) {
					$this->addMessageAttachment($_uid, $attachment['partID'],
						$_folder,
						$attachment['name'],
						$attachment['mimeType'],
						$attachment['size']);
				}
			}
			$bofelamimail->closeConnection();

			$this->saveSessionData();
		}

		function getErrorInfo()
		{
			if(isset($this->errorInfo)) {
				$errorInfo = $this->errorInfo;
				unset($this->errorInfo);
				return $errorInfo;
			}
			return false;
		}

		function getForwardData($_icServer, $_folder, $_uid, $_partID)
		{
			if  ($this->preferencesArray['message_forwarding'] == 'inline') {
				$this->getReplyData('forward', $_icServer, $_folder, $_uid, $_partID);
			}
			$bofelamimail    =& $this->bofelamimail; //CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$bofelamimail->openConnection();
			$bofelamimail->reopen($_folder);

			// get message headers for specified message
			$headers	= $bofelamimail->getMessageEnvelope($_uid, $_partID);

			#_debug_array($headers); exit;
			// check for Re: in subject header
			$this->sessionData['subject'] 	= "[FWD] " . $bofelamimail->decode_header($headers['SUBJECT']);
			$this->sessionData['sourceFolder']=$_folder;
			$this->sessionData['forwardFlag']='forwarded';
			$this->sessionData['forwardedUID']=$_uid;
			if  ($this->preferencesArray['message_forwarding'] == 'asmail') {
				$this->sessionData['mimeType']  = $this->preferencesArray['composeOptions'];
				if($headers['SIZE'])
					$size				= $headers['SIZE'];
				else
					$size				= lang('unknown');

				$this->addMessageAttachment($_uid, $_partID, $_folder,
					$bofelamimail->decode_header(($headers['SUBJECT']?$headers['SUBJECT']:lang('no subject'))),
					'MESSAGE/RFC822', $size);
			}
			else
			{
				unset($this->sessionData['in-reply-to']);
				unset($this->sessionData['to']);
				unset($this->sessionData['cc']);
				if($attachments = $bofelamimail->getMessageAttachments($_uid,$_partID)) {
					foreach($attachments as $attachment) {
						$this->addMessageAttachment($_uid, $attachment['partID'],
							$_folder,
							$attachment['name'],
							$attachment['mimeType'],
							$attachment['size']);
					}
				}
			}
			$bofelamimail->closeConnection();

			$this->saveSessionData();
		}

		/**
		 * getRandomString - function to be used to fetch a random string and md5 encode that one
		 * @param none
		 * @returns string - a random number which is md5 encoded
		 */
		function getRandomString() {
			mt_srand((float) microtime() * 1000000);
			return md5(mt_rand (100000, 999999));
		}

		/**
		 * getReplyData - function to gather the replyData and save it with the session, to be used then.
		 * @param $_mode can be:
		 * 		single: for a reply to one address
		 * 		all: for a reply to all
		 * 		forward: inlineforwarding of a message with its attachments
		 * @param $_icServer number (0 as it is the active Profile)
		 * @param $_folder string
		 * @param $_uid number
		 * @param $_partID number
		 */
		function getReplyData($_mode, $_icServer, $_folder, $_uid, $_partID)
		{
			$foundAddresses = array();

			$bofelamimail    =& $this->bofelamimail; //CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$bofelamimail->openConnection();
			$bofelamimail->reopen($_folder);

			$userEMailAddresses = $this->preferences->getUserEMailAddresses();

			// get message headers for specified message
			#print "AAAA: $_folder, $_uid, $_partID<br>";
			$headers	= $bofelamimail->getMessageEnvelope($_uid, $_partID);
			#$headers	= $bofelamimail->getMessageHeader($_uid, $_partID);
			$this->sessionData['uid'] = $_uid;
			$this->sessionData['messageFolder'] = $_folder;
			$this->sessionData['in-reply-to'] = $headers['MESSAGE_ID'];

			// check for Reply-To: header and use if available
			if(!empty($headers['REPLY_TO']) && ($headers['REPLY_TO'] != $headers['FROM'])) {
				foreach($headers['REPLY_TO'] as $val) {
					if($val['EMAIL'] == 'NIL') {
						continue;
					}

					if(!$foundAddresses[$val['EMAIL']]) {
						$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
						$address = $this->bofelamimail->decode_header($address);
						$oldTo[] = $address;
						$foundAddresses[$val['EMAIL']] = true;
					}
				}
				$oldToAddress	= $headers['REPLY_TO'][0]['EMAIL'];
			} else {
				foreach($headers['FROM'] as $val) {
					if($val['EMAIL'] == 'NIL') {
						continue;
					}
					if(!$foundAddresses[$val['EMAIL']]) {
						$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
						$address = $this->bofelamimail->decode_header($address);
						$oldTo[] = $address;
						$foundAddresses[$val['EMAIL']] = true;
					}
				}
				$oldToAddress	= $headers['REPLY_TO'][0]['EMAIL'];
			}

			if($_mode != 'all' || ($_mode == 'all' && !$userEMailAddresses[$oldToAddress]) ) {
				$this->sessionData['to'] = $oldTo;
			}

			if($_mode == 'all') {
				// reply to any address which is cc, but not to my self
				#if($headers->cc) {
					foreach($headers['CC'] as $val) {
						if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
							continue;
						}

						if($userEMailAddresses[$val['EMAIL']]) {
							continue;
						}

						if(!$foundAddresses[$val['EMAIL']]) {
							$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
							$address = $this->bofelamimail->decode_header($address);
							$this->sessionData['cc'][] = $address;
							$foundAddresses[$val['EMAIL']] = true;
						}
					}
				#}

				// reply to any address which is to, but not to my self
				#if($headers->to) {
					foreach($headers['TO'] as $val) {
						if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
							continue;
						}

						if($userEMailAddresses[$val['EMAIL']]) {
							continue;
						}

						if(!$foundAddresses[$val['EMAIL']]) {
							$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
							$address = $this->bofelamimail->decode_header($address);
							$this->sessionData['to'][] = $address;
							$foundAddresses[$val['EMAIL']] = true;
						}
					}
				#}

				#if($headers->from) {
					foreach($headers['FROM'] as $val) {
						if($val['MAILBOX_NAME'] == 'undisclosed-recipients' || (empty($val['MAILBOX_NAME']) && empty($val['HOST_NAME'])) ) {
							continue;
						}

						if($userEMailAddresses[$val['EMAIL']]) {
							continue;
						}

						if(!$foundAddresses[$val['EMAIL']]) {
							$address = $val['PERSONAL_NAME'] != 'NIL' ? $val['RFC822_EMAIL'] : $val['EMAIL'];
							$address = $this->bofelamimail->decode_header($address);
							$this->sessionData['to'][] = $address;
							$foundAddresses[$val['EMAIL']] = true;
						}
					}
				#}
			}

			// check for Re: in subject header
			if(strtolower(substr(trim($bofelamimail->decode_header($headers['SUBJECT'])), 0, 3)) == "re:") {
				$this->sessionData['subject'] = $bofelamimail->decode_header($headers['SUBJECT']);
			} else {
				$this->sessionData['subject'] = "Re: " . $bofelamimail->decode_header($headers['SUBJECT']);
			}

			//_debug_array($headers);
			$bodyParts = $bofelamimail->getMessageBody($_uid, $this->preferencesArray['always_display'], $_partID);
			//_debug_array($bodyParts);

			$fromAddress = bofelamimail::htmlspecialchars($bofelamimail->decode_header(($headers['FROM'][0]['PERSONAL_NAME'] != 'NIL') ? $headers['FROM'][0]['RFC822_EMAIL'] : $headers['FROM'][0]['EMAIL']));

			$toAddressA = array();
			$toAddress = '';
			foreach ($headers['TO'] as $mailheader) {
				$toAddressA[] =  ($mailheader['PERSONAL_NAME'] != 'NIL') ? $mailheader['RFC822_EMAIL'] : $mailheader['EMAIL'];
			}
			if (count($toAddressA)>0)
			{
				$toAddress = bofelamimail::htmlspecialchars($bofelamimail->decode_header(implode(', ', $toAddressA)));
				$toAddress = @htmlspecialchars(lang("to")).": ".$toAddress.($bodyParts['0']['mimeType'] == 'text/html'?"\r\n<br>":"\r\n");;
			}
			$ccAddressA = array();
			$ccAddress = '';
			foreach ($headers['CC'] as $mailheader) {
				$ccAddressA[] =  ($mailheader['PERSONAL_NAME'] != 'NIL') ? $mailheader['RFC822_EMAIL'] : $mailheader['EMAIL'];
			}
			if (count($ccAddressA)>0)
			{
				$ccAddress = bofelamimail::htmlspecialchars($bofelamimail->decode_header(implode(', ', $ccAddressA)));
				$ccAddress = @htmlspecialchars(lang("cc")).": ".$ccAddress.($bodyParts['0']['mimeType'] == 'text/html'?"\r\n<br>":"\r\n");
			}
			if($bodyParts['0']['mimeType'] == 'text/html') {
				$this->sessionData['body']	= "<br>&nbsp;\r\n<p>".'----------------'.lang("original message").'-----------------'."\r\n".'<br>'.
					@htmlspecialchars(lang("from")).": ".$fromAddress."\r\n<br>".
					$toAddress.$ccAddress.
					@htmlspecialchars(lang("date").": ".$headers['DATE'],ENT_QUOTES | ENT_IGNORE,bofelamimail::$displayCharset, false)."\r\n<br>".
					'----------------------------------------------------------'."\r\n</p>";
				$this->sessionData['mimeType'] 	= 'html';
				$this->sessionData['body']	.= '<blockquote type="cite">';

				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= '<hr>';
					}
					if($bodyParts[$i]['mimeType'] == 'text/plain') {
						#$bodyParts[$i]['body'] = nl2br($bodyParts[$i]['body'])."<br>";
						$bodyParts[$i]['body'] = "<pre>".$bodyParts[$i]['body']."</pre>";
					}
					$tempBody = "<br>".self::_getCleanHTML($GLOBALS['egw']->translation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']));
					bofelamimail::replaceTagsCompletley($tempBody,'head'); // Strip out stuff in head
                        		$this->sessionData['body'] .= str_replace(array('<body>','</body>'),'',$tempBody);

					#error_log( "GetReplyData (HTML) CharSet:".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));

				}

				$this->sessionData['body']	.= '</blockquote><br>';
			} else {
				#$this->sessionData['body']	= @htmlspecialchars(lang("on")." ".$headers['DATE']." ".$bofelamimail->decode_header($fromAddress), ENT_QUOTES) . " ".lang("wrote").":\r\n";
                $this->sessionData['body']  = " \r\n \r\n".'----------------'.lang("original message").'-----------------'."\r\n".
                    @htmlspecialchars(lang("from")).": ".$fromAddress."\r\n".
					$toAddress.$ccAddress.
					@htmlspecialchars(lang("date").": ".$headers['DATE'], ENT_QUOTES | ENT_IGNORE,bofelamimail::$displayCharset, false)."\r\n".
                    '-------------------------------------------------'."\r\n \r\n ";

				$this->sessionData['mimeType']	= 'plain';

				for($i=0; $i<count($bodyParts); $i++) {
					if($i>0) {
						$this->sessionData['body'] .= "<hr>";
					}

					// add line breaks to $bodyParts
					$newBody	= $GLOBALS['egw']->translation->convert($bodyParts[$i]['body'], $bodyParts[$i]['charSet']);
					#error_log( "GetReplyData (Plain) CharSet:".mb_detect_encoding($bodyParts[$i]['body'] . 'a' , strtoupper($bodyParts[$i]['charSet']).','.strtoupper($this->displayCharset).',UTF-8, ISO-8859-1'));

					$newBody        = explode("\n",$newBody);
					$this->sessionData['body'] .= "\r\n";
					// create body new, with good line breaks and indention
					foreach($newBody as $value) {
						// the explode is removing the character
						if (trim($value) != '') {
							#if ($value != "\r") $value .= "\n";
						}
						$numberOfChars = strspn(trim($value), ">");
						$appendString = str_repeat('>', $numberOfChars + 1);

						$bodyAppend = $this->bofelamimail->wordwrap($value, 76-strlen("\r\n$appendString "), "\r\n$appendString ");

						if($bodyAppend[0] == '>') {
							$bodyAppend = '>'. $bodyAppend;
						} else {
							$bodyAppend = '> '. $bodyAppend;
						}

						$this->sessionData['body'] .= $bodyAppend;
					}
				}
			}

			$bofelamimail->closeConnection();

			$this->saveSessionData();
		}

		static function _getCleanHTML($_body, $usepurify = false, $cleanTags=true)
		{
			static $nonDisplayAbleCharacters = array('[\016]','[\017]',
					'[\020]','[\021]','[\022]','[\023]','[\024]','[\025]','[\026]','[\027]',
					'[\030]','[\031]','[\032]','[\033]','[\034]','[\035]','[\036]','[\037]');
			bofelamimail::getCleanHTML($_body, $usepurify, $cleanTags);
			$_body	= preg_replace($nonDisplayAbleCharacters, '', $_body);

			return $_body;
		}

		function getSessionData()
		{
			return $this->sessionData;
		}

		// get the user name, will will use for the FROM field
		function getUserName()
		{
			$retData = sprintf("%s <%s>",$this->preferences['realname'],$this->preferences['emailAddress']);
			return $retData;
		}

		function removeAttachment($_attachmentID) {
			if (parse_url($this->sessionData['attachments'][$_attachmentID]['file'],PHP_URL_SCHEME) != 'vfs') {
				unlink($this->sessionData['attachments'][$_attachmentID]['file']);
			}
			unset($this->sessionData['attachments'][$_attachmentID]);
			$this->saveSessionData();
		}

		function restoreSessionData()
		{
			$this->sessionData = $GLOBALS['egw']->session->appsession('compose_session_data_'.$this->composeID, 'felamimail');
		}

		function saveSessionData()
		{
			$GLOBALS['egw']->session->appsession('compose_session_data_'.$this->composeID,'felamimail',$this->sessionData);
		}

		function createMessage(&$_mailObject, $_formData, $_identity, $_signature = false)
		{
			$bofelamimail	=& $this->bofelamimail; //CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$_mailObject->PluginDir = EGW_SERVER_ROOT."/phpgwapi/inc/";
			$activeMailProfile = $this->preferences->getIdentity(0);
			$_mailObject->IsSMTP();
			$_mailObject->CharSet	= $this->displayCharset;
			// you need to set the sender, if you work with different identities, since most smtp servers, dont allow
			// sending in the name of someone else
			$_mailObject->Sender  = $activeMailProfile->emailAddress;
			$_mailObject->From 	= $_identity->emailAddress;
			$_mailObject->FromName = $_mailObject->EncodeHeader($_identity->realName);
			$_mailObject->Priority = $_formData['priority'];
			$_mailObject->Encoding = 'quoted-printable';
			$_mailObject->AddCustomHeader('X-Mailer: FeLaMiMail');
			if(isset($this->sessionData['in-reply-to'])) {
				$_mailObject->AddCustomHeader('In-Reply-To: '. $this->sessionData['in-reply-to']);
			}
			if($_formData['disposition']) {
				$_mailObject->AddCustomHeader('Disposition-Notification-To: '. $_identity->emailAddress);
			}
			if(!empty($_identity->organization)) {
				#$_mailObject->AddCustomHeader('Organization: '. $bofelamimail->encodeHeader($_identity->organization, 'q'));
				$_mailObject->AddCustomHeader('Organization: '. $_identity->organization);
			}

			foreach((array)$_formData['to'] as $address) {
				$address_array	= imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address), '');
				foreach((array)$address_array as $addressObject) {
					if ($addressObject->host == '.SYNTAX-ERROR.') continue;
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddAddress($emailAddress, $emailName);
					$_mailObject->AddAddress($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$emailAddress)));
				}
			}

			foreach((array)$_formData['cc'] as $address) {
				$address_array	= imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
				foreach((array)$address_array as $addressObject) {
					if ($addressObject->host == '.SYNTAX-ERROR.') continue;
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddCC($emailAddress, $emailName);
					$_mailObject->AddCC($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$emailAddress)));
				}
			}

			foreach((array)$_formData['bcc'] as $address) {
				$address_array	= imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
				foreach((array)$address_array as $addressObject) {
				if ($addressObject->host == '.SYNTAX-ERROR.') continue;
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddBCC($emailAddress, $emailName);
					$_mailObject->AddBCC($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$emailAddress)));
				}
			}

			foreach((array)$_formData['replyto'] as $address) {
				$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
				foreach((array)$address_array as $addressObject) {
					if ($addressObject->host == '.SYNTAX-ERROR.') continue;
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					#$emailName = $bofelamimail->encodeHeader($addressObject->personal, 'q');
					#$_mailObject->AddBCC($emailAddress, $emailName);
					$_mailObject->AddReplyto($emailAddress, str_replace(array('@'),' ',($addressObject->personal?$addressObject->personal:$emailAddress)));
				}
			}

			//$_mailObject->WordWrap = 76; // as we break lines ourself, we will not need/use the buildin WordWrap
			#$_mailObject->Subject = $bofelamimail->encodeHeader($_formData['subject'], 'q');
			$_mailObject->Subject = $_formData['subject'];
			#$realCharset = mb_detect_encoding($_formData['body'] . 'a' , strtoupper($this->displayCharset).',UTF-8, ISO-8859-1');
			#error_log("bocompose::createMessage:".$realCharset);
			// this should never happen since we come from the edit dialog
			if (bofelamimail::detect_qp($_formData['body'])) {
				error_log("Error: bocompose::createMessage found QUOTED-PRINTABLE while Composing Message. Charset:$realCharset Message:".print_r($_formData['body'],true));
				$_formData['body'] = preg_replace('/=\r\n/', '', $_formData['body']);
				$_formData['body'] = quoted_printable_decode($_formData['body']);
			}
			#if ($realCharset != $this->displayCharset) error_log("Error: bocompose::createMessage found Charset ($realCharset) differs from DisplayCharset (".$this->displayCharset.")");
			$signature = $_signature->fm_signature;
			$signature = bofelamimail::merge($signature,array($GLOBALS['egw']->accounts->id2name($GLOBALS['egw_info']['user']['account_id'],'person_id')));
			if($_formData['mimeType'] =='html') {
				$_mailObject->IsHTML(true);
				if(!empty($signature)) {
					#$_mailObject->Body    = array($_formData['body'], $_signature['signature']);
					if($this->sessionData['stationeryID']) {
						$bostationery = new felamimail_bostationery();
						$_mailObject->Body = $bostationery->render($this->sessionData['stationeryID'],$_formData['body'],$signature);
					} else {
						$_mailObject->Body = $_formData['body'] .'<hr style="border:dotted 1px silver; width:90%; border:dotted 1px silver;">'. $signature;
					}
					$_mailObject->AltBody = $this->convertHTMLToText($_formData['body']).
						"\r\n-- \r\n".
						$this->convertHTMLToText($signature);
					#print "<pre>$_mailObject->AltBody</pre>";
					#print htmlentities($_signature['signature']);
				} else {
					if($this->sessionData['stationeryID']) {
						$bostationery = new felamimail_bostationery();
						$_mailObject->Body = $bostationery->render($this->sessionData['stationeryID'],$_formData['body']);
					} else {
						$_mailObject->Body	= $_formData['body'];
					}
					$_mailObject->AltBody	= $this->convertHTMLToText($_formData['body']);
				}
			} else {
				$_mailObject->IsHTML(false);
				$_mailObject->Body = $this->convertHTMLToText($_formData['body'],false);
				#$_mailObject->Body = $_formData['body'];
				if(!empty($signature)) {
					$_mailObject->Body .= "\r\n-- \r\n". $this->convertHTMLToText($signature);
				}
			}

			// add the attachments
			$bofelamimail->openConnection();
			if (is_array($this->sessionData) && isset($this->sessionData['attachments']))
			{
				$tnfattachments = null;
				foreach((array)$this->sessionData['attachments'] as $attachment) {
					if(is_array($attachment))
					{
						if (!empty($attachment['uid']) && !empty($attachment['folder'])) {
							$bofelamimail->reopen($attachment['folder']);
							switch($attachment['type']) {
								case 'MESSAGE/RFC822':
									$rawHeader='';
									if (isset($attachment['partID'])) {
										$rawHeader      = $bofelamimail->getMessageRawHeader($attachment['uid'], $attachment['partID']);
									}
									$rawBody        = $bofelamimail->getMessageRawBody($attachment['uid'], $attachment['partID']);
									$_mailObject->AddStringAttachment($rawHeader.$rawBody, $_mailObject->EncodeHeader($attachment['name']), '7bit', 'message/rfc822');
									break;
								default:
									$attachmentData	= $bofelamimail->getAttachment($attachment['uid'], $attachment['partID']);
									if ($attachmentData['type'] == 'APPLICATION/MS-TNEF')
									{
										if (!is_array($tnfattachments)) $tnfattachments = $bofelamimail->decode_winmail($attachment['uid'], $attachment['partID']);
										foreach ($tnfattachments as $k)
										{
											if ($k['name'] == $attachment['name'])
											{
												$tnfpart = $bofelamimail->decode_winmail($attachment['uid'], $attachment['partID'],$k['is_winmail']);
												$attachmentData['attachment'] = $tnfpart['attachment'];
												//error_log(__METHOD__.__LINE__.$k['name'].'<->'.$attachment['name'].':'.array2string($attachmentData['attachment']));
												break;
											}
										}
									}
									$_mailObject->AddStringAttachment($attachmentData['attachment'], $_mailObject->EncodeHeader($attachment['name']), 'base64', $attachment['type']);
									break;

							}
						} else {
							if (isset($attachment['file']) && parse_url($attachment['file'],PHP_URL_SCHEME) == 'vfs')
							{
								egw_vfs::load_wrapper('vfs');
							}
							$_mailObject->AddAttachment (
								$attachment['file'],
								$_mailObject->EncodeHeader($attachment['name']),
								(strtoupper($attachment['type'])=='MESSAGE/RFC822'?'7bit':'base64'),
								$attachment['type']
							);
						}
					}
				}
			}
			$bofelamimail->closeConnection();
		}

		function saveAsDraft($_formData)
		{
			$bofelamimail	=& $this->bofelamimail; //CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$mail		= new egw_mailer();
			$identity	= $this->preferences->getIdentity((int)$this->sessionData['identity']);
			$flags = '\\Seen \\Draft';
			$BCCmail = '';

			$this->createMessage($mail, $_formData, $identity);
			// preserve the bcc and if possible the save to folder information
			$this->sessionData['folder']    = $_formData['folder'];
			$this->sessionData['bcc']   = $_formData['bcc'];
			$this->sessionData['signatureID'] = $_formData['signatureID'];
			$this->sessionData['stationeryID'] = $_formData['stationeryID'];
			$this->sessionData['identity']  = $_formData['identity'];
			foreach((array)$this->sessionData['bcc'] as $address) {
				$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
				foreach((array)$address_array as $addressObject) {
					$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
					$mailAddr[] = array($emailAddress, $addressObject->personal);
				}
			}
			// folder list as Customheader
			if (!empty($this->sessionData['folder']))
			{
				$folders = implode('|',array_unique($this->sessionData['folder']));
				$mail->AddCustomHeader("X-Mailfolder: $folders");
			}
			$mail->AddCustomHeader('X-Signature: '.$this->sessionData['signatureID']);
			$mail->AddCustomHeader('X-Stationery: '.$this->sessionData['stationeryID']);
			$mail->AddCustomHeader('X-Identity: '.(int)$this->sessionData['identity']);
			// decide where to save the message (default to draft folder, if we find nothing else)
			// if the current folder is in draft or template folder save it there
			// if it is called from printview then save it with the draft folder
			$savingDestination = ($this->preferences->ic_server[0]->draftfolder ? $this->preferences->ic_server[0]->draftfolder : $this->preferencesArray['draftFolder']);
			if (empty($this->sessionData['messageFolder']) && !empty($this->sessionData['mailbox'])) $this->sessionData['messageFolder'] = $this->sessionData['mailbox'];
			if ($bofelamimail->isDraftFolder($this->sessionData['messageFolder'])
				|| $bofelamimail->isTemplateFolder($this->sessionData['messageFolder']))
			{
				$savingDestination = $this->sessionData['messageFolder'];
			}
			if (  !empty($_formData['printit']) && $_formData['printit'] == 0 ) $savingDestination = ($this->preferences->ic_server[0]->draftfolder ? $this->preferences->ic_server[0]->draftfolder : $this->preferencesArray['draftFolder']);

			if (count($mailAddr)>0) $BCCmail = $mail->AddrAppend("Bcc",$mailAddr);
			$bofelamimail->openConnection();
			if ($bofelamimail->folderExists($savingDestination,true)) {
				$messageUid = $bofelamimail->appendMessage($savingDestination,
					$BCCmail.$mail->getMessageHeader(),
					$mail->getMessageBody(),
					$flags);
			} else {
				error_log("bofelamimail::saveAsDraft->".lang("folder")." ". $savingDestination." ".lang("does not exist on IMAP Server."));
				return false;
			}
			$bofelamimail->closeConnection();
			return $messageUid;
		}

		function send($_formData)
		{
			$bofelamimail	=& $this->bofelamimail; //CreateObject('felamimail.bofelamimail',$this->displayCharset);
			$mail 		= new egw_mailer();
			$messageIsDraft	=  false;

			$this->sessionData['identity']	= $_formData['identity'];
			$this->sessionData['to']	= $_formData['to'];
			$this->sessionData['cc']	= $_formData['cc'];
			$this->sessionData['bcc']	= $_formData['bcc'];
			$this->sessionData['folder']	= $_formData['folder'];
			$this->sessionData['replyto']	= $_formData['replyto'];
			$this->sessionData['subject']	= trim($_formData['subject']);
			$this->sessionData['body']	= $_formData['body'];
			$this->sessionData['priority']	= $_formData['priority'];
			$this->sessionData['signatureID'] = $_formData['signatureID'];
			$this->sessionData['stationeryID'] = $_formData['stationeryID'];
			$this->sessionData['disposition'] = $_formData['disposition'];
			$this->sessionData['mimeType']	= $_formData['mimeType'];
			$this->sessionData['to_infolog'] = $_formData['to_infolog'];
			// if the body is empty, maybe someone pasted something with scripts, into the message body
			// this should not happen anymore, unless you call send directly, since the check was introduced with the action command
			if(empty($this->sessionData['body']))
			{
				// this is to be found with the egw_unset_vars array for the _POST['body'] array
				$name='_POST';
				$key='body';
				#error_log($GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
				if (isset($GLOBALS['egw_unset_vars'][$name.'['.$key.']']))
				{
					$this->sessionData['body'] = self::_getCleanHTML( $GLOBALS['egw_unset_vars'][$name.'['.$key.']']);
					$_formData['body']=$this->sessionData['body'];
				}
				#error_log($this->sessionData['body']);
			}
			if(empty($this->sessionData['to']) && empty($this->sessionData['cc']) &&
			   empty($this->sessionData['bcc']) && empty($this->sessionData['folder'])) {
			   	$messageIsDraft = true;
			}
			#error_log(print_r($this->preferences,true));
			$identity = $this->preferences->getIdentity((int)$this->sessionData['identity']);
			$signature = $this->bosignatures->getSignature((int)$this->sessionData['signatureID']);
			#error_log($this->sessionData['identity']);
			#error_log(print_r($identity,true));
			// create the messages
			$this->createMessage($mail, $_formData, $identity, $signature);
			#print "<pre>". $mail->getMessageHeader() ."</pre><hr><br>";
			#print "<pre>". $mail->getMessageBody() ."</pre><hr><br>";
			#exit;

			$ogServer = $this->preferences->getOutgoingServer(0);
			#_debug_array($ogServer);
			$mail->Host 	= $ogServer->host;
			$mail->Port	= $ogServer->port;
			// SMTP Auth??
			if($ogServer->smtpAuth) {
				$mail->SMTPAuth	= true;
				// check if username contains a ; -> then a sender is specified (and probably needed)
				list($username,$senderadress) = explode(';', $ogServer->username,2);
				if (isset($senderadress) && !empty($senderadress)) $mail->Sender = $senderadress;
				$mail->Username = $username;
				$mail->Password	= $ogServer->password;
			}

			// set a higher timeout for big messages
			@set_time_limit(120);
			//$mail->SMTPDebug = 10;
			#error_log("Folder:".count(array($this->sessionData['folder']))."To:".count((array)$this->sessionData['to'])."CC:". count((array)$this->sessionData['cc']) ."bcc:".count((array)$this->sessionData['bcc']));
			if(count((array)$this->sessionData['to']) > 0 || count((array)$this->sessionData['cc']) > 0 || count((array)$this->sessionData['bcc']) > 0) {
				try {
					$mail->Send();
				}
				catch(phpmailerException $e) {
					$this->errorInfo = $e->getMessage();
					if ($mail->ErrorInfo) // use the complete mailer ErrorInfo, for full Information
					{
						if (stripos($mail->ErrorInfo, $this->errorInfo)===false)
						{
							$this->errorInfo = $mail->ErrorInfo.'<br>'.$this->errorInfo;
						}
						else
						{
							$this->errorInfo = $mail->ErrorInfo;
						}
					}
					error_log(__METHOD__.__LINE__.array2string($this->errorInfo));
					return false;
				}
			} else {
				if (count(array($this->sessionData['folder']))>0 && !empty($this->sessionData['folder'])) {
					#error_log("Folders:".print_r($this->sessionData['folder'],true));
				} else {
					$this->errorInfo = lang("Error: ").lang("No Address TO/CC/BCC supplied, and no folder to save message to provided.");
					#error_log($this->errorInfo);
					return false;
				}
			}
			#error_log("Mail Sent.!");
			$folder = (array)$this->sessionData['folder'];
			if(isset($this->preferences->preferences['sentFolder']) &&
				$this->preferences->preferences['sentFolder'] != 'none' &&
				$messageIsDraft == false) {
				$folder[] = $this->preferences->preferences['sentFolder'];
			}
			if($messageIsDraft == true) {
			   	if(!empty($this->preferences->preferences['draftFolder'])) {
				   	$folder[] = $this->sessionData['folder'] = array($this->preferences->preferences['draftFolder']);
			   	}
			}
			$folder = array_unique($folder);
			#error_log("Number of Folders to move copy the message to:".count($folder));
			if ((count($folder) > 0) || (isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder']))
                || (isset($this->sessionData['forwardFlag']) && isset($this->sessionData['sourceFolder']))) {
				$bofelamimail =& $this->bofelamimail; //CreateObject('felamimail.bofelamimail');
				$bofelamimail->openConnection();
				//$bofelamimail->reopen($this->sessionData['messageFolder']);
				#error_log("(re)opened Connection");
			}
			if (count($folder) > 0) {

				foreach((array)$this->sessionData['bcc'] as $address) {
					$address_array  = imap_rfc822_parse_adrlist((get_magic_quotes_gpc()?stripslashes($address):$address),'');
					foreach((array)$address_array as $addressObject) {
						$emailAddress = $addressObject->mailbox. (!empty($addressObject->host) ? '@'.$addressObject->host : '');
						$mailAddr[] = array($emailAddress, $addressObject->personal);
					}
				}
				$BCCmail='';
				if (count($mailAddr)>0) $BCCmail = $mail->AddrAppend("Bcc",$mailAddr);
				//$bofelamimail =& CreateObject('felamimail.bofelamimail');
				//$bofelamimail->openConnection();
				foreach($folder as $folderName) {
					if($bofelamimail->isSentFolder($folderName)) {
						$flags = '\\Seen';
					} elseif($bofelamimail->isDraftFolder($folderName)) {
						$flags = '\\Draft';
					} else {
						$flags = '';
					}
					#$mailHeader=explode('From:',$mail->getMessageHeader());
					#$mailHeader[0].$mail->AddrAppend("Bcc",$mailAddr).'From:'.$mailHeader[1],
					if ($bofelamimail->folderExists($folderName,true)) {
						$bofelamimail->appendMessage($folderName,
								$BCCmail.$mail->getMessageHeader(),
								$mail->getMessageBody(),
								$flags);
					}
				}
				//$bofelamimail->closeConnection();
			}
			#error_log("handling draft messages, flagging and such");
			if((isset($this->sessionData['uid']) && isset($this->sessionData['messageFolder']))
				|| (isset($this->sessionData['forwardFlag']) && isset($this->sessionData['sourceFolder']))) {
				// mark message as answered
				//$bofelamimail =& CreateObject('felamimail.bofelamimail');
				$bofelamimail->openConnection();
				$bofelamimail->reopen($this->sessionData['messageFolder']);
				// if the draft folder is a starting part of the messages folder, the draft message will be deleted after the send
				// unless your templatefolder is a subfolder of your draftfolder, and the message is in there
				if ($bofelamimail->isDraftFolder($this->sessionData['messageFolder']) && !$bofelamimail->isTemplateFolder($this->sessionData['messageFolder']))
				{
					$bofelamimail->deleteMessages(array($this->sessionData['uid']));
				} else {
					$bofelamimail->flagMessages("answered", array($this->sessionData['uid']));
					if (array_key_exists('forwardFlag',$this->sessionData) && $this->sessionData['forwardFlag']=='forwarded')
					{
						$bofelamimail->flagMessages("forwarded", array($this->sessionData['forwardedUID']));
					}
				}
				//$bofelamimail->closeConnection();
			}
			if ($bofelamimail) $bofelamimail->closeConnection();
			//error_log("performing Infolog Stuff");
			//error_log(print_r($this->sessionData['to'],true));
			//error_log(print_r($this->sessionData['cc'],true));
			//error_log(print_r($this->sessionData['bcc'],true));
			if (is_array($this->sessionData['to']))
			{
				$mailaddresses = $this->sessionData['to'];
			}
			else
			{
				$mailaddresses = array();
			}
			if (is_array($this->sessionData['cc'])) $mailaddresses = array_merge($mailaddresses,$this->sessionData['cc']);
			if (is_array($this->sessionData['bcc'])) $mailaddresses = array_merge($mailaddresses,$this->sessionData['bcc']);
			// attention: we dont return from infolog. cleanups will be done there.
			if ($_formData['to_infolog'] == 'on') {
				$uiinfolog =& CreateObject('infolog.infolog_ui');
				$uiinfolog->import_mail(
					$mailaddresses,
					$this->sessionData['subject'],
					$this->convertHTMLToText($this->sessionData['body']),
					$this->sessionData['attachments']
				);
			}

			if(is_array($this->sessionData['attachments'])) {
				reset($this->sessionData['attachments']);
				while(list($key,$value) = @each($this->sessionData['attachments'])) {
					#print "$key: ".$value['file']."<br>";
					if (!empty($value['file']) && parse_url($value['file'],PHP_URL_SCHEME) != 'vfs') {	// happens when forwarding mails
						unlink($value['file']);
					}
				}
			}

			$this->sessionData = '';
			$this->saveSessionData();

			return true;
		}

		function setDefaults()
		{
			require_once(EGW_INCLUDE_ROOT.'/felamimail/inc/class.felamimail_bosignatures.inc.php');
			$boSignatures = new felamimail_bosignatures();

			if($signatureData = $boSignatures->getDefaultSignature()) {
				if (is_array($signatureData)) {
					$this->sessionData['signatureID'] = $signatureData['signatureid'];
				} else {
					$this->sessionData['signatureID'] = $signatureData;
				}
			} else {
				$this->sessionData['signatureID'] = -1;
			}
			// retrieve the signature accociated with the identity
			$accountData    = $this->bopreferences->getAccountData($this->preferences,'active');
			if ($accountData['identity']->signature) $this->sessionData['signatureID'] = $accountData['identity']->signature;
			// apply the current mailbox to the compose session data of the/a new email
			$appsessionData = $GLOBALS['egw']->session->appsession('session_data');
			$this->sessionData['mailbox'] = $appsessionData['mailbox'];

			$this->sessionData['mimeType'] = 'html';
			if (!empty($this->preferencesArray['composeOptions']) && $this->preferencesArray['composeOptions']=="text") $this->sessionData['mimeType']  = 'text/plain';

			$this->saveSessionData();
		}

		function stripSlashes($_string)
		{
			if (get_magic_quotes_gpc()) {
				return stripslashes($_string);
			} else {
				return $_string;
			}
		}
}
