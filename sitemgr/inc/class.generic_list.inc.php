<?php
/**************************************************************************\
* eGroupWare - IWATS - Intelligent Web Agent Ticket System                *
* http://www.eGroupWare.org                                               *
* Written by Drago Bokal <drago DOT bokal AT fmf DOT uni-lj DOT si>       *
*            Joe Stewart <joestewart AT users DOT sourceforge DOT net>    *
* --------------------------------------------                            *
*  This program is free software; you can redistribute it and/or modify it*
*  under the terms of the GNU General Public License as published by the  *
*  Free Software Foundation; either version 2 of the License, or (at your *
*  option) any later version.                                             *
\**************************************************************************/

/* $ Id: class.et_media.inc.php,v 1.2 2002/10/19 11:11:03 ralfbecker Exp $ */

include_once(EGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');
$GLOBALS['egw_info']['flags']['included_classes']['so_sql'] = True;


/**!
@class generic_list
@author dragob
@abstract given a table and some e-templates enables listing, adding, editing and deleting the entries of the table
@discussion How to use generic_list
@discussion 1. Create a database table (using eTemplate DBTools). 
@discussion    Table must have an integer primary key.
@discussion    Table may have an integer foreign key which represent a master-slave relationship
@discussion    (in a one to many relationship, our table is on the "many" or "slave" side).
@discussion 2. Create a list entry template using eTemplate.
@discussion    Template must contain a set of labels whose Name field must be set to
@discussion    "${row}[field_name]", where field_name could be any field in the created table
@discussion    Template must have Edit and Delete buttons, whose Name field must be set
@discussion    edit[$row_cont[key_field]] and delete[$row_cont[key_field]] 
@discussion    Template may have other command buttons, whose Name field must be set to
@discussion    command[$row_cont[key_field]] 
@discussion 3. Create a list template using eTemplate.
@discussion    Template must have a message row for displaying variable "msg"
@discussion    Template must use list entry template as a subtemplate, and its Options must be 
@discussion    "entry".
@discussion    Template must have an Add button whose Name is set to "add".
@discussion    Template may have other command buttons, whose Name field must be set to
@discussion    "command"
@discussion 4. Create an edit template using eTemplate.
@discussion    Template must have a message row for displaying variable "msg"
@discussion    Template must assume the variables will have the names of the table fields.
@discussion    Template must have a Save button whose Name is set to "save".
@discussion    Template must have a Cancel button whose Name is set to "cancel".
@discussion    Template may have other command buttons, whose Name field must be set to
@discussion    "command"
@discussion 5. Create an delete template using eTemplate.
@discussion    Template must have a message row for displaying variable "msg"
@discussion    Template must assume the variables will have the names of the table fields.
@discussion    Template must have a Yes button whose Name is set to "yes".
@discussion    Template must have a No button whose Name is set to "no".
@discussion    Template may have other command buttons, whose Name field must be set to
@discussion    "command"
@discussion 6. Create a file class.ui_myclass.inc.php looking like
@discussion 
@discussion include_once(EGW_INCLUDE_ROOT . '/.../inc/class.generic_list.inc.php');
@discussion $GLOBALS['egw_info']['flags']['included_classes']['generic_list'] = True;
@discussion 
@discussion class ui_myclass extends generic_list
@discussion {
@discussion 
@discussion   function ui_myclass()
@discussion   {
@discussion     parent::create('my_application', 'my_table', 
@discussion       'ui_myclass', 'key_field', 'my_list_template', 
@discussion       'my_edit_template', 'my_add_template', 'my_delete_template', 
@discussion       'master_fk_field');
@discussion   }
@discussion 
@discussion }
@discussion 7. If the list or edit templates contain optional command buttons, 
@discussion    override the edit_elt method
@discussion      function edit_elt($content='',$msg = '')
@discussion      {
@discussion        if (isset($content['my_command1'])) {
@discussion          //process my_command1;
@discussion        }
@discussion        else if (isset($content['my_command2'])) {
@discussion          //process my_command2;
@discussion        }
@discussion        else {
@discussion          parent::edit_elt($content,$msg);
@discussion        }
@discussion      }
@discussion 8. If the delete templates contain optional command buttons, 
@discussion    override the delete_elt method in a similar fashion.
@discussion 
@discussion 9. If any of the templates contain select lists, 
@discussion    override the get_sel_fields method.
@discussion
@discussion  function get_sel_fields($content,$template)
@discussion  {
@discussion    if ($template==$this->edit_template) {
@discussion      return(
@discussion        array(    // the options for our selectboxes for states
@discussion            'field_name1' => array(
@discussion                          'key1'=>'value1',
@discussion                          'key2'=>'value2',
@discussion                          'key3'=>'value3'
@discussion                       ),
@discussion            'field_name2' => array(
@discussion                          'key1'=>'value1',
@discussion                          'key2'=>'value2',
@discussion                          'key3'=>'value3'
@discussion                       )
@discussion        )
@discussion      );
@discussion    }
@discussion    return array();
@discussion  }
@discussion
@discussion 10. If any of the templates contains fields that are not table fields, 
@discussion     override the preprocess_content method. This is useful if some integer 
@discussion     foreign key fields are to be replaced with their string descriptors.
@discussion
@discussion  function preprocess_content($content,$template)
@discussion  {
@discussion    if ($template==$this->list_template) {
@discussion      $content+=array(
@discussion        'new_field1'=>'value1',
@discussion        'new_field2'=>'value2',
@discussion        'new_field3'=>'value3'
@discussion      );
@discussion    }
@discussion    else if ($template==$this->edit_template) {
@discussion      $content+=array(
@discussion      ...
@discussion      );
@discussion    }
@discussion    return $content;
@discussion  }
@discussion}
@discussion 11. If your table is in a master-slave relationship to some other, call
@discussion     your class using some code similar to:
@discussion           
@discussion           ExecMethod('my_application.my_class.list_all',
@discussion             array('master_key_field'=>master_key_value));
@discussion           exit;
@discussion 12. If your table is not in a master-slave relationship to some other, call
@discussion     your class using some code similar to:
@discussion           
@discussion           ExecMethod('my_application.my_class.list_all');
@discussion           exit;
*/
 
class generic_list extends so_sql
{
	 var $application;
	 var $table;
	 var $class_name;
	 var $id;
	 var $master;
	 var $master_id;
	 
	 var $list_template;
	 var $edit_template;
	 var $delete_teplate;

	 function create($app, $tab, $cls, $id, $lst, $add, $edt, $del, $mas='')
	 {
			$this->application=$app;
			$this->table=$tab;
			$this->class_name=$app.'.'.$cls;
			$this->id=$id;
			$this->master=$mas;
			$this->master_id=0;
			
			$this->list_template=$lst;
			$this->add_template=$add;
			$this->edit_template=$edt;
			$this->delete_template=$del;
			
			$this->tmpl =& CreateObject('etemplate.etemplate',$this->list_template);

			$this->so_sql($this->application,$this->table);   // sets up our storage layer using the table 
			$this->empty_on_write = "''";    // what to write in the db, if a column is empty, the default is NULL

			$this->public_functions += array(   // this function can be called external, eg. by /index.php?menuaction=...
				 'list_all' => True,
				 'edit_elt' => True,
				 'delete_elt' => True
			);
	}
	
	function get_sel_fields($content,$template)
	{
		//Default: no FK descriptors;
		return(array());
	}
	function get_readonly_fields($content,$template)
	{
		//Default: all fields editable;
		return(array());
	}
	function preprocess_content($content,$template)
	{
		//Default: no FK descriptors;
		return $content;
	}
	
	function check_master($content)
	{
			if (($this->master!='') && ($this->master_id==0)) {
				if (isset($content[$this->master]))
					$this->master_id=$content[$this->master];
				else
					$this->master_id=$_GET[$this->master];
			}
	}

	 function list_all($content='',$msg='')
	 {
			$this->check_master($content);
		
			if ($this->master_id>0) {
				$list_elts = $this->search($this->master.'='.$this->master_id,False);
			}
			else {
				$list_elts = $this->search('',False);
			}
			

			if (!is_array($list_elts) || !count($list_elts))
			{
				 $content = array(
						'msg' => lang('There is nothing to list.',count($found))
				 );
			}
			else {
				 reset($list_elts);     // create array with all matches, indexes starting with 1
				 for ($row=0; list($key,$data) = each($list_elts); ++$row)
				 {
						$entry[$row] = $data;
				 }
				 $content = array(
						'msg' => lang('There are %1 elements in the list.',count($list_elts)),
						'entry' => $entry
				 );
			}

			if (!empty($msg))
				 $content['msg']=$msg.' '.$content['msg'];

			$this->tmpl->read($this->list_template);    // read the show-template

			// exec it with the edit-function as callback, passing master ID if neccessary

			if ($this->master_id>0) {
				$this->tmpl->exec($this->class_name.'.edit_elt',
					$this->preprocess_content($content,$this->list_template),
					$this->get_sel_fields($content,$this->list_template),
					$this->get_readonly_fields($content,$this->list_template),
					array($this->master => $this->master_id));   
			}
			else  {
				$this->tmpl->exec($this->class_name.'.edit_elt',
					$this->preprocess_content($content,$this->list_template),
					$this->get_sel_fields($content,$this->list_template),
					$this->get_readonly_fields($content,$this->list_template));   
			}
				
	 }


	 function edit_elt($content='',$msg = '')
	 {
		 $this->check_master($content);
		 
		if (isset($content['entry']))
				 $content=$content['entry'];


			if (isset($content['add'])) {
				$this->edit_elt('',$msg);
			}
			else if (isset($content['edit'])) {
	 //an ugly way to get the only key out of the array.
	 //fix it!
						for (;list($key,$data) = each($content['edit']); ++$row)
						{
							 $cont[$this->id] = $key;
						}
						$this->edit_elt($cont,$msg);
						exit;
			}
			else if (isset($content['delete'])) {
						for (;list($key,$data) = each($content['delete']); ++$row)
						{
							 $content[$this->id] = $key;
						}
				 $this->delete_elt($content,$msg);
				 exit;
			}

			if (is_array($content)) // we are called as callback for the dialog / form
			{
				if ($this->master_id>0) {
					$content = $content+array(
						$this->master => $this->master_id
					);
				}
					
				if ($content[$this->id] > 0)      // if we have an id --> read the entry
				{
					 $this->read($content);
				}
				$this->data_merge($content);  // merge content with our internal data-array ($this->data)

				if (isset($content['save']))  // save the entry ($this->data)
				{
					 if (!$this->save()) {
							$this->list_all(lang('Entry saved.'));
							return;
					 }
					 else
							$msg.= lang('Error writing to the database.');
				}
				else if (isset($content['duplicate']))  // save the entry ($this->data)
				{
					 if (!$this->save(array($this->id=>''))) {
							$this->list_all(lang('Entry duplicated.'));
							return;
					 }
					 else
							$msg.= lang('Error writing to the database.');
				}
				else if (isset($content['cancel'])) // just show the list
				{
					 $this->list_all();
					 return;
				}
		 }

		 // now we filling the content array for the next call to etemplate.exec
		 $content = $this->data + array(  // the content to be merged in the template
				'msg' => $msg
		 );
		 $preserv = array(    // this data is preserved over the exec-call (like a hidden input-field n form)
					 $this->id => $this->data[$this->id]
		 );
		 if ($this->master_id>0) {
			 $preserv += array(    
						 $this->master => $this->master_id
			 );
		 }
		 $this->tmpl->read($this->edit_template);   // read the edit-template
		 // exec it with the edit-function as callback, passing master ID if neccessary
		 $this->tmpl->exec($this->class_name.'.edit_elt',
			 $this->preprocess_content($content,$this->edit_template),
			 $this->get_sel_fields($content,$this->edit_template),
			 $this->get_readonly_fields($content,$this->edit_template),$preserv);   
	}


	 function delete_elt($content='',$msg = '')
	 {
		 $this->check_master($content);
		 
			if (!is_array($content)){
				 $this->list_all();
				 return;
			}

			if ($content[$this->id] <= 0){
				 $this->list_all();
				 return;
			}

			$this->read($content);
			$this->data_merge($content);  // merge content with our internal data-array ($this->data)

			if (isset($content['yes']))   //
			{
				 $msg .= $this->delete($content) ? lang('Element successfully deleted.') : lang('Error deleting the element.');
				 $this->list_all('',$msg);
				 return;
			}
			else if (isset($content['no']))  // just show the list
			{
				 $this->list_all();
				 return;
			}

			// now we filling the content array for the next call to etemplate.exec
			$content = $this->data + array(  // the content to be merged in the template
				 'msg' => $msg
			);
			$no_button = array(     // button not to show
			);
			$preserv = array(    // this data is preserved over the exec-call (like a hidden input-field in form)
				 $this->id => $this->data[$this->id]
			);
		 if ($this->master_id>0) {
			 $preserv += array(    
						 $this->master => $this->master_id,
			 );
		 }
			$this->tmpl->read($this->delete_template);    // read the delete-template
			// exec it with the edit-function as callback, passing master ID if neccessary
			$this->tmpl->exec($this->class_name.'.delete_elt',
				$this->preprocess_content($content,$this->delete_template),
				$this->get_sel_fields($content,$this->delete_template),
				$this->get_readonly_fields($content,$this->delete_template),$preserv);   
	 }
}

