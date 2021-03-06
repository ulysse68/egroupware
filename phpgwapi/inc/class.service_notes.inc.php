<?php
  /**************************************************************************\
  * eGroupWare API - Services Abstraction Class                              *
  * This file written by Miles Lott <milos@groupwhere.org>                   *
  * Copyright (C) 2001-2004 Miles Lott                                       *
  * ------------------------------------------------------------------------ *
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

  /* $Id: class.service_notes.inc.php 17483 2004-11-30 13:40:46Z milosch $ */

	class service_notes extends service
	{
		function service_notes()
		{
			$this->provider = $GLOBALS['phpgw_info']['notes_service'] ? $GLOBALS['phpgw_info']['notes_service'] : 'notes';
			$this->svc = $this->provider . '.bo' . $this->provider;
			$type = $this->type ? $this->type : 'xmlrpc';
			$this->function_map = ExecMethod($this->svc . '.list_methods',$type);
		}

		function read($data)
		{
			return ExecMethod($this->svc . '.' . $this->function_map['read']['function'],$data);
		}

		function read_list($data)
		{
			return ExecMethod($this->svc . '.' . $this->function_map['read_list']['function'],$data);
		}

		function save($data)
		{
			return ExecMethod($this->svc . '.' . $this->function_map['save']['function'],$data);
		}

		function add($data)
		{
			return ExecMethod($this->svc . '.' . $this->function_map['save']['function'],$data);
		}

		function delete($data)
		{
			return ExecMethod($this->svc . '.' . $this->function_map['delete']['function'],$data);
		}
	}
?>
