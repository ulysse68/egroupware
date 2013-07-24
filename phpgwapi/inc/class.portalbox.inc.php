<?php
  /**************************************************************************\
  * eGroupWare API - Portal Box manager                                      *
  * This file written by Joseph Engo <jengo@phpgroupware.org>                *
  * Helps manage the portal boxes for eGroupWares main page                  *
  * Copyright (C) 2000, 2001  Joseph Engo                                    *
  * -------------------------------------------------------------------------*
  * This library is part of the eGroupWare API                               *
  * http://www.egroupware.org/api                                            * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

  /* $Id: class.portalbox.inc.php 27892 2009-09-21 21:47:04Z nathangray $ */

	class portalbox
	{
		//Set up the Object, reserving memory space for variables

		var $outerwidth;
		var $outerbordercolor;
		var $outerborderwidth = 1;
		var $titlebgcolor;
		var $width;
		var $innerwidth;
		var $innerbgcolor;
		var $controls;
		var $header_background_image;
		var $classname;
		var $up;
		var $down;
		var $close;
		var $question;
		var $edit;
		
		var $data = Array();

		// Textual variables
		var $title;

		// Template
		var $p;

		/*
		Use these functions to get and set the values of this
		object's variables. This is good OO practice, as it means
		that datatype checking can be completed and errors raised accordingly.
		*/
		function setvar($var,$value='')
		{
			if ($value=='')
			{
				global $$var;
				$value = $$var;
			}
			$this->$var = $value;
			// echo $var." = ".$this->$var."<br>\n";
		}

		function getvar($var='')
		{
			if ($var=='' || !isset($this->$var))
			{
				echo 'Programming Error: '.$this->getvar('classname').'->getvar('.$var.')!<br>'."\n";
				echo function_backtrace(1);
				$GLOBALS['phpgw']->common->phpgw_exit();
			}
			//echo "Var = ".$var."<br>\n";
			//echo $var." = ".$this->$var."<br>\n";
			return $this->$var;
		}

		/*
		This is the constructor for the object.
		*/
		function portalbox($title='', $primary='', $secondary='', $tertiary='')
		{
			$this->setvar('title',$title);
			// echo 'After SetVar Title = '.$this->getvar('title')."<br>\n";
			$this->setvar('titlebgcolor',$primary);
			$this->setvar('innerbgcolor',$secondary);
			$this->setvar('outerbordercolor',$tertiary);
		}

		function start_template()
		{
			$this->p = CreateObject('phpgwapi.Template',$GLOBALS['phpgw']->common->get_tpl_dir('home'));
			$this->p->set_file(
				array(
					'PORTAL'	=> 'portal.tpl'
				)
			);

			$this->p->set_block('PORTAL','portal_box','portal_box');
			$this->p->set_block('PORTAL','portal_row','portal_row');
			$this->p->set_block('PORTAL','portal_listbox_header','portal_listbox_header');
			$this->p->set_block('PORTAL','portal_listbox_link','portal_listbox_link');
			$this->p->set_block('PORTAL','portal_listbox_footer','portal_listbox_footer');
			$this->p->set_block('PORTAL','portal_control','portal_control');
			$this->p->set_block('PORTAL','link_field','link_field');

			$var = Array(
				'outer_width'	=> $this->getvar('width'),
				'title'	=> $this->getvar('title'),
				'inner_width'	=> $this->getvar('width'),
				'control_link'	=> ''
			);
			$this->p->set_var($var);
			$this->p->set_var('row','',False);
		}

		function set_controls($control='',$control_param='')
		{
			if($control != '' && $control_param != '')
			{
				if($control == 'close' && $GLOBALS['egw']->preferences->forced[$GLOBALS['egw']->applications->id2name($control_param['app'])]['homepage_display']) {
					// User is forced to have this box on their home page, hide the close button
					return;
				}
				$this->setvar($control,$GLOBALS['phpgw']->link($control_param['url'],'app='.$control_param['app'].'&control='.$control));
			}
		}

		function set_internal($data='')
		{
			if($data=='' && !count($this->data))
			{
				$data = '<td>&nbsp;</td>';
			}
			$this->p->set_var('output',$data);
			$this->p->parse('row','portal_row',true);
		}

		function draw_box()
		{
			$control = '';
			if($this->up || $this->down || $this->close || $this->question || $this->edit)
			{
				$control_array = Array(
					'up',
					'down',
					'close',
					/*'question',
					'edit'*/
				);
				@reset($control_array);
				while(list($key,$param) = each($control_array))
				{
					if(isset($this->$param) && $this->$param)
					{
						$image_width = 15;
						if($param == 'edit')
						{
							$image_width = 30;
						}
						$this->p->set_var('link_field_data','<a href="'.$this->$param.'"><img src="'.$GLOBALS['phpgw']->common->image('phpgwapi',$param.'.button').'" border="0" width="'.$image_width.'" height="15" alt="'.lang($param).'"></a>');
						$this->p->parse('control_link','link_field',True);
					}
				}
				$this->p->parse('portal_controls','portal_control',True);
			}
			return $this->p->fp('out','portal_box');
		}
	}
