<?php
/**
 * Addressbook - SIF parser
 *
 * @link http://www.egroupware.org
 * @author Lars Kneschke <lkneschke@egroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @package addressbook
 * @subpackage export
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.addressbook_sif.inc.php 29829 2010-04-16 09:24:55Z jlehrke $
 */

class addressbook_sif extends addressbook_bo
{
	var $sifMapping = array(
		'Anniversary'			=> '',
		'AssistantName'			=> 'assistent',
		'AssistantTelephoneNumber'	=> 'tel_assistent',
		'BillingInformation'		=> '',
		'Birthday'			=> 'bday',
		'Body'				=> 'note',
		'Business2TelephoneNumber'	=> '',
		'BusinessAddressCity'		=> 'adr_one_locality',
		'BusinessAddressCountry'	=> 'adr_one_countryname',
		'BusinessAddressPostalCode'	=> 'adr_one_postalcode',
		'BusinessAddressPostOfficeBox'	=> 'adr_one_street2',
		'BusinessAddressState'		=> 'adr_one_region',
		'BusinessAddressStreet'		=> 'adr_one_street',
		'BusinessFaxNumber'		=> 'tel_fax',
		'BusinessTelephoneNumber'	=> 'tel_work',
		'CallbackTelephoneNumber'	=> '',
		'CarTelephoneNumber'		=> 'tel_car',
		'Categories'			=> 'cat_id',
		'Children'			=> '',
		'Companies'			=> '',
		'CompanyMainTelephoneNumber'	=> '',
		'CompanyName'			=> 'org_name',
		'ComputerNetworkName'		=> '',
		'Department'			=> 'org_unit',
		'Email1Address'			=> 'email',
		'Email1AddressType'		=> '',
		'Email2Address'			=> 'email_home',
		'Email2AddressType'		=> '',
		'Email3Address'			=> '',
		'Email3AddressType'		=> '',
		'FileAs'			=> 'n_fileas',
		'FirstName'			=> 'n_given',
		'Hobby'				=> '',
		'Home2TelephoneNumber'		=> '',
		'HomeAddressCity'		=> 'adr_two_locality',
		'HomeAddressCountry'		=> 'adr_two_countryname',
		'HomeAddressPostalCode'		=> 'adr_two_postalcode',
		'HomeAddressPostOfficeBox'	=> 'adr_two_street2',
		'HomeAddressState'		=> 'adr_two_region',
		'HomeAddressStreet'		=> 'adr_two_street',
		'HomeFaxNumber'			=> 'tel_fax_home',
		'HomeTelephoneNumber'		=> 'tel_home',
		'Importance'			=> '',
		'Initials'			=> '',
		'JobTitle'			=> 'title',
		'Language'			=> '',
		'LastName'			=> 'n_family',
		'ManagerName'			=> '',
		'MiddleName'			=> 'n_middle',
		'Mileage'			=> '',
		'MobileTelephoneNumber'		=> 'tel_cell',
		'NickName'			=> '',
		'OfficeLocation'		=> 'room',
		'OrganizationalIDNumber'	=> '',
		'OtherAddressCity'		=> '',
		'OtherAddressCountry'		=> '',
		'OtherAddressPostalCode'	=> '',
		'OtherAddressPostOfficeBox'	=> '',
		'OtherAddressState'		=> '',
		'OtherAddressStreet'		=> '',
		'OtherFaxNumber'		=> '',
		'OtherTelephoneNumber'		=> 'tel_other',
		'PagerNumber'			=> 'tel_pager',
		'PrimaryTelephoneNumber'	=> 'tel_prefer',
		'Profession'			=> 'role',
		'RadioTelephoneNumber'		=> '',
		'Sensitivity'			=> 'private',
		'Spouse'			=> '',
		'Subject'			=> '',
		'Suffix'			=> 'n_suffix',
		'TelexNumber'			=> '',
		'Title'				=> 'n_prefix',
		'WebPage'			=> 'url',
		'YomiCompanyName'		=> '',
		'YomiFirstName'			=> '',
		'YomiLastName'			=> '',
		'HomeWebPage'			=> 'url_home',
		'Folder'			=> '',
	);

	// standard headers
	const xml_decl = '<?xml version="1.0" encoding="UTF-8"?>';
	const SIF_decl = '<SIFVersion>1.1</SIFVersion>';

	function startElement($_parser, $_tag, $_attributes)
	{
	}

	function endElement($_parser, $_tag)
	{
		if (!empty($this->sifMapping[$_tag]))
		{
			$this->contact[$this->sifMapping[$_tag]] = trim($this->sifData);
		}
		unset($this->sifData);
	}

	function characterData($_parser, $_data)
	{
		$this->sifData .= $_data;
	}

	function siftoegw($sifData)
	{

		#$tmpfname = tempnam('/tmp/sync/contents','sifc_');

		#$handle = fopen($tmpfname, "w");
		#fwrite($handle, $sifData);
		#fclose($handle);

		// Horde::logMessage("SyncML siftoegw:\n$sifData", __FILE__, __LINE__, PEAR_LOG_DEBUG);

		$this->xml_parser = xml_parser_create('UTF-8');
		xml_set_object($this->xml_parser, $this);
		xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($this->xml_parser, "startElement", "endElement");
		xml_set_character_data_handler($this->xml_parser, "characterData");
		$this->strXmlData = xml_parse($this->xml_parser, $sifData);
		if (!$this->strXmlData)
		{
			error_log(sprintf("XML error: %s at line %d",
				xml_error_string(xml_get_error_code($this->xml_parser)),
				xml_get_current_line_number($this->xml_parser)));
			return false;
		}

		foreach ($this->contact as $key => $value)
		{
			$value = preg_replace('/<\!\[CDATA\[(.+)\]\]>/Usim', '$1', $value);
			$value = $GLOBALS['egw']->translation->convert($value, 'utf-8');
			switch ($key) {
				case 'cat_id':
					if (!empty($value))
					{
						$categories1 = explode(',', $value);
						$categories2 = explode(';', $value);
						$finalContact[$key] = count($categories1) > count($categories2) ? $categories1 : $categories2;
					}
					break;

				case 'private':
					$finalContact[$key] = (int) ($value > 0);	// eGW private is 0 (public) or 1 (private), SIF seems to use 0 and 2
					break;

				default:
					$finalContact[$key] = str_replace("\r\n", "\n", $value);
					break;
			}
		}

		$this->fixup_contact($finalContact);
		// Horde::logMessage("SyncML siftoegw: " . print_r($finalContact, true), __FILE__, __LINE__, PEAR_LOG_DEBUG);
		return $finalContact;
	}

	/**
	 * Search an exactly matching entry (used for slow sync)
	 *
	 * @param string $_sifdata
	 * @return array of matching contact-ids
	 */
	function search($_sifdata, $contentID=null, $relax=false)
	{
	  	$result = array();

	  	if (($contact = $this->siftoegw($_sifdata)))
	  	{
		  	if ($contentID)
		  	{
			  	$contact['contact_id'] = $contentID;
		  	}
		  	$result = $this->find_contact($contact, $relax);
	  	}
		return $result;
	}

	/**
	* import a vard into addressbook
	*
	* @return int contact id
	* @param string	$_vcard		the vcard
	* @param int/string	$_abID=null		the internal addressbook id or !$_abID for a new enty
	* @param boolean $merge=false	merge data with existing entry
	*/
	function addSIF($_sifdata, $_abID=null, $merge=false)
	{
		if (!$contact = $this->siftoegw($_sifdata))
		{
			return false;
		}

		if ($_abID)
		{
			if (($old_contact = $this->read($_abID)))
			{
				if ($merge)
				{
					foreach ($contact as $key => $value)
					{
						if (!empty($old_contact[$key]))
						{
							$contact[$key] = $old_contact[$key];
						}
					}
				}
				else
				{
					if (isset($old_contact['account_id']))
					{
						$contact['account_id'] = $old_contact['account_id'];
					}
					if (is_array($contact['cat_id']))
					{
						$contact['cat_id'] = implode(',',$this->find_or_add_categories($contact['cat_id'], $_abID));
					}
					else
					{
						// restore from orignal
						$contact['cat_id'] = $old_contact['cat_id'];
					}
				}
			}
			// update entry
			$contact['id'] = $_abID;
		}
		else
    	{
    		if (is_array($contact['cat_id']))
			{
				$contact['cat_id'] = implode(',',$this->find_or_add_categories($contact['cat_id'], -1));
			}
    		if (isset($GLOBALS['egw_info']['user']['preferences']['syncml']['filter_addressbook']))
    		{
	    		$owner = $GLOBALS['egw_info']['user']['preferences']['syncml']['filter_addressbook'];
	    		switch ($owner)
				{
					case 'G':
						$contact['owner'] = $GLOBALS['egw_info']['user']['account_primary_group'];
					break;
					case 'P':
					case  0:
						$contact['owner'] = $this->user;
						break;
					default:
						$contact['owner'] = (int)$owner;
				}
    		}
    	}
		return $this->save($contact);
	}

	/**
	* return a sifc
	*
	* @param int	$_id		the id of the contact
	* @return string containing the vcard
	*/
	function getSIF($_id)
	{
		$sysCharSet	= $GLOBALS['egw']->translation->charset();

		$fields = array_unique(array_values($this->sifMapping));
		sort($fields);

		if (!($entry = $this->read($_id))) return false;

		$sifContact = self::xml_decl . "\n<contact>" . self::SIF_decl;

		#error_log(print_r($entry,true));

		// fillup some defaults such as n_fn and n_fileas is needed
		$this->fixup_contact($entry);

		foreach ($this->sifMapping as $sifField => $egwField)
		{
			if (empty($egwField)) continue;

			#error_log("$sifField => $egwField");
			#error_log('VALUE1: '.$entry[0][$egwField]);
			$value = $GLOBALS['egw']->translation->convert($entry[$egwField], $sysCharSet, 'utf-8');
			#error_log('VALUE2: '.$value);

			switch ($sifField)
			{
				case 'Sensitivity':
					$value = 2 * $value;	// eGW private is 0 (public) or 1 (private)
					$sifContact .= "<$sifField>$value</$sifField>";
					break;

				case 'Folder':
					# skip currently. This is the folder where Outlook stores the contact.
					#$sifContact .= "<$sifField>/</$sifField>";
					break;

				case 'Categories':
					if (!empty($value)) {
						$value = implode(", ", $this->get_categories($value));
						$value = $GLOBALS['egw']->translation->convert($value, $sysCharSet, 'utf-8');
					} else {
						break;
					}

				default:
					$value = @htmlspecialchars(trim($value), ENT_NOQUOTES, 'utf-8');
					$sifContact .= "<$sifField>$value</$sifField>";
					break;
			}
		}
		$sifContact .= "</contact>";

		return $sifContact;
	}
}
