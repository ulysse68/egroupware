<?php
	/********************************************************************\
	* eGroupWare SiteMgr - Web Content Management                       *
	* http://www.egroupware.org                                         *
	* --------------------------------------------                      *
	* eGroupWare API - XML tools                                        *
	* Written by Dan Kuykendall <seek3r@phpgroupware.org>               *
	* and Bettina Gille [ceb@phpgroupware.org]                          *
	* and Ralf Becker <ralfbecker@outdoortraining.de>                   *
	* Michael Totschnig: changed function export_var, so that 
	* Copyright (C) 2002 Dan Kuykendall, Bettina Gille, Ralf Becker     *
	* ----------------------------------------------------------------- *
	* This library is part of the eGroupWare API                        *
	* ----------------------------------------------------------------- *
	* This library is free software; you can redistribute it and/or     *
	* modify it under the terms of the GNU General Public License as    *
	* published by the Free Software Foundation; either version 2 of    *
	* the License, or (at your option) any later version.               *
	*                                                                   *
	* This program is distributed in the hope that it will be useful,   *
	* but WITHOUT ANY WARRANTY; without even the implied warranty of    *
	* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU  *
	* General Public License for more details.                          *
	*                                                                   *
	* You should have received a copy of the GNU General Public License *
	* along with this program; if not, write to the Free Software       *
	* Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.         *
	\*******************************************************************/

	/* $Id: class.xmltool2.inc.php 27222 2009-06-08 16:21:14Z ralfbecker $ */

	// Michael Totschnig: this is a variant of xmltool where export_var returns subelements
	// in a subarray, even if there is only one of them
	// for example <dir><app>myapp</app></dir> is exported to
	// array('dir' => array('app' => array('myapp')) 
	// instead of
	// array('dir' => array('app' => 'myapp')
	// this helps interpreting the data returned

	function var2xml($name, $data)
	{
		$doc = new xmltool2('root','','');
		return $doc->import_var($name,$data,True,True);
	}

	class xmltool2
	{
		/* for root nodes */
		var $xmlversion = '1.0';
		var $doctype = Array();
		/* shared */
		var $node_type = '';
		var $name = '';
		var $data_type;
		var $data;
		/* for nodes */
		var $attributes = Array();
		var $comments = Array();
		var $indentstring = "\t";
		
		/* start the class as either a root or a node */
		function xmltool2 ($node_type = 'root', $name='',$indentstring="\t")
		{
			$this->node_type = $node_type;
			$this->indentstring = $indentstring;
			if ($this->node_type == 'node')
			{
				if($name !== '')
				{
					$this->name = $name;
				}
				else
				{
					echo 'You must name node type objects<br>';
					exit;
				}
			}
		}
		
		function set_version ($version = '1.0')
		{
			$this->xmlversion = $version;
			return True;
		}

		function set_doctype ($name, $uri = '')
		{
			if ($this->node_type == 'root')
			{
				$this->doctype[$name] = $uri;
				return True;
			}
			else
			{
				return False;
			}
		}

		function add_node ($node_object, $name = '')
		{
			switch ($this->node_type)
			{
				case 'root':
					if (is_object($node_object))
					{
						$this->data = $node_object;
					}
					else
					{
						$this->data = $this->import_var($name, $node_object);
					}
					break;
				case 'node':
					if(!is_array($this->data))
					{
						$this->data = Array();
						$this->data_type = 'node';
					}
					if (is_object($node_object))
					{
						if ($name != '')
						{
							$this->data[$name] = $node_object;
						}
						else
						{
							$this->data[] = $node_object;
						}
					}
					else
					{
						$this->data[$name] = $this->import_var($name, $node_object);
					}
					return True;
					break;
			}
		}

		function get_node ($name = '')	// what is that function doing: NOTHING !!!
		{
			switch	($this->data_type)
			{
				case 'root':
					break;
				case 'node':
					break;
				case 'object':
					break;
			}
		
		}

		function set_value ($string)
		{
			$this->data = $string;
			$this->data_type = 'value';
			return True;
		}
		
		function get_value ()
		{
			if($this->data_type == 'value')
			{
				return $this->data;
			}
			else
			{
				return False;
			}
		}

		function set_attribute ($name, $value = '')
		{
			$this->attributes[$name] = $value;
			return True;
		}

		function get_attribute ($name)
		{
			return $this->attributes[$name];
		}

		function get_attributes ()
		{
			return $this->attributes;
		}

		function add_comment ($comment)
		{
			$this->comments[] = $comment;
			return True;
		}

		function import_var($name, $value,$is_root=False,$export_xml=False)
		{
			//echo "<p>import_var: this->indentstring='$this->indentstring'</p>\n";
			$node = new xmltool2('node',$name,$this->indentstring);
			switch (gettype($value))
			{
				case 'string':
				case 'integer':
				case 'double':
				case 'NULL':
					$node->set_value($value);
					break;
				case 'boolean':
					if($value == True)
					{
						$node->set_value('1');
					}
					else
					{
						$node->set_value('0');
					}
					break;
				case 'array':
					$new_index = False;
					while (list ($idxkey, $idxval) = each ($value))
					{
						if(is_array($idxval))
						{
							while (list ($k, $i) = each ($idxval))
							{
								if (is_int($k))
								{
									$new_index = True;
								}
							}
						}
					}
					reset($value);
					while (list ($key, $val) = each ($value))
					{
						if($new_index)
						{
							$keyname = $name;
							$nextkey = $key;
						}
						else
						{
							$keyname = $key;
							$nextkey = $key;
						}
						switch (gettype($val))
						{
							case 'string':
							case 'integer':
							case 'double':
							case 'NULL':
								$subnode = new xmltool2('node', $nextkey,$this->indentstring);
								$subnode->set_value($val);
								$node->add_node($subnode);
								break;
							case 'boolean':
								$subnode = new xmltool2('node', $nextkey,$this->indentstring);
								if($val == True)
								{
									$subnode->set_value('1');
								}
								else
								{
									$subnode->set_value('0');
								}
								$node->add_node($subnode);
								break;
							case 'array':
								list($first_key) = each($val); reset($val);
								if($new_index && is_int($first_key))
								{
									while (list ($subkey, $subval) = each ($val))
									{
										$node->add_node($this->import_var($nextkey, $subval));
									}
								}
								else
								{
									$subnode = $this->import_var($nextkey, $val);
									$node->add_node($subnode);
								}
								break;
							case 'object':
								$subnode = new xmltool2('node', $nextkey,$this->indentstring);
								$subnode->set_value('PHP_SERIALIZED_OBJECT&:'.serialize($val));
								$node->add_node($subnode);
								break;
							case 'resource':
								echo 'Halt: Cannot package PHP resource pointers into XML<br>';
								exit;
							default:
								echo 'Halt: Invalid or unknown data type<br>';
								exit;
						}

					}
					break;
				case 'object':
					$node->set_value('PHP_SERIALIZED_OBJECT&:'.serialize($value));
					break;
				case 'resource':
					echo 'Halt: Cannot package PHP resource pointers into XML<br>';
					exit;
				default:
					echo 'Halt: Invalid or unknown data type<br>';
					exit;
			}
	
			if($is_root)
			{
				$this->add_node($node);
				if($export_xml)
				{
					$xml = $this->export_xml();
					return $xml;
				}
				else
				{
					return True;
				}
			}
			else
			{
				$this->add_node($node);
				return $node;
			}
		}

		function export_struct()
		{
			if($this->node_type == 'root')
			{
				return $this->data->export_struct();
			}

			$retval['tag'] = $this->name;
			$retval['attributes'] = $this->attributes;
			if($this->data_type != 'node')
			{	
				$found_at = strpos($this->data,'PHP_SERIALIZED_OBJECT&:');
				if($found_at !== False)
				{
					$retval['value'] = unserialize(str_replace ('PHP_SERIALIZED_OBJECT&:', '', $this->data));
				}
				else
				{
					$retval['value'] = $this->data;
				}
				return $retval;
			}
			else
			{
				reset($this->data);
				while(list($key,$val) = each($this->data))
				{
					$retval['children'][] = $val->export_struct();
				}				
				return $retval;
			}
		}

		
		function import_xml_children($data, &$i, $parent_node)
		{
			while (++$i < count($data))
			{
				switch ($data[$i]['type'])
				{
					case 'cdata':
					case 'complete':
						$node = new xmltool2('node',$data[$i]['tag'],$this->indentstring);
						if(is_array($data[$i]['attributes']) && count($data[$i]['attributes']) > 0)
						{
							while(list($k,$v)=each($data[$i]['attributes']))
							{
								$node->set_attribute($k,$v);
							}
						}
						$node->set_value($data[$i]['value']);
						$parent_node->add_node($node);
						break;
					case 'open':
						$node = new xmltool2('node',$data[$i]['tag'],$this->indentstring);
						if(is_array($data[$i]['attributes']) && count($data[$i]['attributes']) > 0)
						{
							while(list($k,$v)=each($data[$i]['attributes']))
							{
								$node->set_attribute($k,$v);
							}
						}
						
						$node = $this->import_xml_children($data, $i, $node);
						$parent_node->add_node($node);
						break;
					case 'close':
						return $parent_node;
				}
			}
		}
		
		function import_xml($xmldata) 
		{
			$parser = xml_parser_create();
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
			xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE,   1);
			xml_parse_into_struct($parser, $xmldata, $vals, $index);
			xml_parser_free($parser);
			unset($index);	
			$node = new xmltool2('node',$vals[0]['tag'],$this->indentstring);
			if(isset($vals[0]['attributes']))
			{
				while(list($key,$value) = each($vals[0]['attributes']))
				{
					$node->set_attribute($key, $value);
				}
			}
			switch ($vals[0]['type'])
			{
				case 'complete':
					$node->set_value($vals[0]['value']);
					break;
				case 'cdata':
					$node->set_value($vals[0]['value']);
					break;
				case 'open':
					$node = $this->import_xml_children($vals, $i = 0, $node);
					break;
				case 'closed':
					exit;
			}
			$this->add_node($node);
		}


		function export_var()
		{
			if($this->node_type == 'root')
			{
				return $this->data->export_var();
			}

			if($this->data_type != 'node')
			{	
				$found_at = strpos($this->data,'PHP_SERIALIZED_OBJECT&:');
				if($found_at !== False)
				{
					return unserialize(str_replace ('PHP_SERIALIZED_OBJECT&:', '', $this->data));
				}
				return $this->data;
			}
			else
			{
				reset($this->data);
				while(list($key,$val) = each($this->data))
				{
					$return_array[$val->name][] = $val->export_var();
				}
				return $return_array;
			}
		}

		function export_xml($indent = 1)
		{
			if ($this->node_type == 'root')
			{
				$result = '<?xml version="'.$this->xmlversion.'" encoding="' . lang('charset') . '"?>'."\n";
				if(count($this->doctype) == 1)
				{
					list($doctype_name,$doctype_uri) = each($this->doctype);
					$result .= '<!DOCTYPE '.$doctype_name.' SYSTEM "'.$doctype_uri.'">'."\n";
				}
				if(count($this->comments) > 0 )
				{
					//reset($this->comments);
					while(list($key,$val) = each ($this->comments))
					{
						$result .= "<!-- $val -->\n";
					}
				}
				if(is_object($this->data))
				{
					$indent = 0;
					$result .= $this->data->export_xml($indent);
				}
				return $result;
			}
			else /* For node objects */
			{
				for ($i = 0; $i < $indent; $i++)
				{
					$indentstring .= $this->indentstring;
				}

				$result = $indentstring.'<'.$this->name;
				if(count($this->attributes) > 0 )
				{
					reset($this->attributes);
					while(list($key,$val) = each ($this->attributes))
					{
						$result .= ' '.$key.'="'.htmlspecialchars($val).'"';
					}
				}

				$endtag_indent = $indentstring;
				if (empty($this->data_type))
				{
					$result .= '/>'."\n";
				}
				else
				{
					$result .= '>';

					switch ($this->data_type)
					{
						case 'value':
							if(is_array($this->data))
							{
								$type_error = True;
								break;
							}
							
							/*if(preg_match("(&|<)", $this->data))	// this is unnecessary with htmlspecialchars($this->data)
							{
								$result .= '<![CDATA['.$this->data.']]>';
								$endtag_indent = '';		
							}
							else*/if(strlen($this->data) > 30 && !empty($this->indentstring))
							{
								$result .= "\n".$indentstring.$this->indentstring.htmlspecialchars($this->data)."\n";
								$endtag_indent = $indentstring;
							}
							else
							{
								$result .= htmlspecialchars($this->data);
								$endtag_indent = '';
							}
							break;
						case 'node':
							$result .= "\n";
							if(!is_array($this->data))
							{
								$type_error = True;
								break;
							}
				
							$subindent = $indent+1;
							reset($this->data);
							while(list($key,$val) = each ($this->data))
							{
								if(is_object($val))
								{
									$result .= $val->export_xml($subindent);
								}
							}
							break;
						default:
						if($this->data != '')
						{
							echo 'Invalid or unset data type ('.$this->data_type.'). This should not be possible if using the class as intended<br>';
						}
					}

					if ($type_error)
					{
						echo 'Invalid data type. Tagged as '.$this->data_type.' but data is '.gettype($this->data).'<br>';
					}

					$result .= $endtag_indent.'</'.$this->name.'>';
					if($indent != 0)
					{
						$result .= "\n";
					}
				}
				if(count($this->comments) > 0 )
				{
					reset($this->comments);
					while(list($key,$val) = each ($this->comments))
					{
						$result .= $endtag_indent."<!-- $val -->\n";
					}
				}
				return $result;
			}
		}
	}

	class xmlnode extends xmltool2
	{
		function xmlnode($name)
		{
			$this->xmltool2('node',$name);
		}
	}

	class xmldoc extends xmltool2
	{
		function xmldoc($version = '1.0')
		{
			$this->xmltool2('root');
			$this->set_version($version);
		}

		function add_root($root_node)
		{
			return $this->add_node($root_node);
		}
	}

?>
