<?php
	/**************************************************************************\
	* eGroupWare SiteMgr - Web Content Management                              *
	* http://www.egroupware.org                                                *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: class.module_hello.inc.php 13729 2004-02-10 14:56:34Z ralfbecker $ */

class module_hello extends Module 
{
	function module_hello()
	{
		$this->arguments = array(
			'name' => array(
				'type' => 'textfield', 
				'label' => lang('The person to say hello to')
			)
		);
		$this->post = array('name' => array('type' => 'textfield'));
		$this->session = array('name');
		$this->title = lang('Hello world');
		$this->description = lang('This is a simple sample module');
	}

	function get_content(&$arguments,$properties) 
	{
		$this->validate($arguments);
		return lang('Hello') . ' ' . $arguments['name'] . '<br><form method="post">' . 
			$this->build_post_element('name',lang('Enter a name')) .
			'</form>';
	}

	function validate(&$data)
	{
		if (preg_match("/[[:upper:]]/",$data['name']))
		{
			$data['name'] = strtolower($data['name']);
			$this->validation_error = lang('Name has been translated to lower case');
		}
		return true;
	}
}
