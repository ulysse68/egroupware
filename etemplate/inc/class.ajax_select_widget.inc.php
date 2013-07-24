<?php
/**
 * eGroupWare eTemplate Extension - AJAX Select Widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Nathan Gray <nathangray@sourceforge.net>
 * @version $Id: class.ajax_select_widget.inc.php 29168 2010-02-10 10:10:59Z leithoff $
 */

/**
 * AJAX Select Widget
 *
 * Using AJAX, this widget allows a type-ahead find similar to a ComboBox, where as the user enters information,
 * a drop-down box is populated with the n closest matches.  If the user clicks on an item in the drop-down, that
 * value is selected.
 * n is the maximum number of results set in the user's preferences.
 * The user is restricted to selecting values in the list.
 * This widget can get data from any function that can provide data to a nextmatch widget.
 * This widget is generating html, so it does not work (without an extra implementation) in an other UI
 */

class ajax_select_widget
{
	var $public_functions = array(
		'pre_process' => True,
		'post_process' => True,
		'ajax_search'	=>	True,
	);
	var $human_name = 'AJAX Select';	// this is the name for the editor

	// Accepted option keys if you're passing in an array to set up the widget
	// Additional options will be passed to the search query
	public static $known_options = array(
		// These ones can be passed in from eTemplate editor in size
		'get_rows',
		'get_title',
		'id_field',
		'template',
		'filter',
		'filter2',
		'link',
		'icon',

		// Pass by code only
		'values',
	);

	// Flag used in id_field to indicate that the key of the record should be used as the value
	const ARRAY_KEY = 'array_key';

	// Array of static values to emulate a combo-box, with no DB lookup
	protected static $static_values = array();

	private $debug = false;

	function ajax_select_widget($ui='')
	{

		switch($ui)
		{
			case '':
			case 'html':
				$this->ui = 'html';
				break;
			default:
				echo "UI='$ui' not implemented";
		}
		return 0;
	}

	/**
	* pre-processing of the extension
	*
	* This function is called before the extension gets rendered
	*
	* @param string $name form-name of the control
	* @param mixed &$value value / existing content, can be modified
	* @param array &$cell array with the widget, can be modified for ui-independent widgets
	* @param array &$readonlys names of widgets as key, to be made readonly
	* @param mixed &$extension_data data the extension can store persisten between pre- and post-process
	* @param object &$tmpl reference to the template we belong too
	* @return boolean true if extra label is allowed, false otherwise
	*/
	function pre_process($name,&$value,&$cell,&$readonlys,&$extension_data,&$tmpl)
	{
		if($this->debug) {
			echo __METHOD__ . '<br />';
			printf("Name:%20s<br />", $name);
			echo 'Value:';
			_debug_array($value);
			echo 'Cell:';
			_debug_array($cell);

			echo 'Readonlys:';
			_debug_array($readonlys);

			echo 'Extension_data:';
			_debug_array($extension_data);

		}

		// Get Options
		$options = array();
		if(!is_array($cell['size'])) {
			list(
				$options['get_rows'],
				$options['get_title'],
				$options['id_field'],
				$options['template'],
				$options['filter'],
				$options['filter2'],
				$options['link'],
				$options['icon']
			) = explode(',', $cell['size']);
		} else {
			$options = $cell['size'];
		}

		if(is_array($value)) {
			$options = array_merge($options, $value);
		}

		if(!$options['template']) {
			$options['template'] = 'etemplate.ajax_select_widget.row';
		}

		if(array_key_exists('values', $options)) {
			if($options['values']) {
				self::$static_values[$name] = $options['values'];
			}
			unset($options['values']);
		}

		$onchange = ($cell['onchange'] ? $cell['onchange'] : 'false');

		// Set current value
		if(!is_array($value)) {
			$current_value = $value;
		} elseif($value[$options['id_field']]) {
			$current_value = $value[$options['id_field']];
		}
		$extension_data['old_value'] = $value;

		list($title_app, $title_class, $title_method) = explode('.', $options['get_title']);
		if($title_app && $title_class) {
			if (is_object($GLOBALS[$title_class])) {       // use existing instance (put there by a previous CreateObject)
				$title_obj =& $GLOBALS[$title_class];
			} else {
				$title_obj =& CreateObject($title_app . '.' . $title_class);
			}
		}

		if(!is_object($title_obj) || !method_exists($title_obj,$title_method)) {
			echo "$entry_app.$entry_class.$entry_method is not a valid method for getting the title";
		} elseif($current_value) {
			if($title_method == 'array_title' && $title_class == __CLASS__) {
				$title = self::$title_method($current_value, $name);
			} else {
				$title = $title_obj->$title_method($current_value);
			}
		}

		// Check get_rows method
		list($get_rows_app, $get_rows_class, $get_rows_method) = explode('.', $options['get_rows']);
		if($get_rows_app && $get_rows_class) {
			if (is_object($GLOBALS[$get_rows_class])) {       // use existing instance (put there by a previous CreateObject)
				$get_rows_obj =& $GLOBALS[$get_rows_class];
			} else {
				$get_rows_obj =& CreateObject($get_rows_app . '.' . $get_rows_class);
			}

			if(!is_object($get_rows_obj) || !method_exists($get_rows_obj, $get_rows_method)) {
				echo "$get_rows_app.$get_rows_class.$get_rows_method is not a valid method for getting the rows";
			}
		}


		// Set up widget
		$cell['type'] = 'template';
		$cell['size'] = $cell['name'];
		$value = array('value' => $current_value, 'search' => $title);

		$widget = new etemplate('etemplate.ajax_select_widget');
		$widget->no_onclick = True;

		// Link if readonly & link is set
		$search =& $widget->get_widget_by_name('search');
		if(($cell['readonly'] || $readonlys['search']) && $options['link']) {
			$cell['readonly'] = false;
			if(!is_array($readonlys)) {
				$readonlys = array('search' => true);
			} else {
				$readonlys['search'] = true;
			}
			$search['type'] = 'label';
			$search['no_lang'] = 1;
			$search['size'] = ',' . $options['link'];
			$extension_data['readonly'] = true;
		} else {
			$search['type'] = 'text';
			$search['size'] = '';
			if($current_value == '' && $options['id_field'] == self::ARRAY_KEY) {
				$search['blur'] = lang('Search...');
			}
		}

		// Icon
		$icon =& $widget->get_widget_by_path('/0/1A');
		$icon['name'] = $options['icon'];

		$cell['obj'] = &$widget;

		// Save static values, if set
		if(self::$static_values[$name]) {
			$extension_data['values'] = self::$static_values[$name];
		}

		// Save options for post_processing
		$extension_data['options'] = $options;
		$extension_data['needed'] = $cell['needed'];

		// xajax
		$GLOBALS['egw_info']['flags']['include_xajax'] = True;

		// JavaScript
		// converter doesn't handle numeric well
		foreach($options as $key => &$value) {
			if(is_numeric($value)) {
				$value = (string)$value;
			}
			if($value === null) {
				unset($options[$key]);
			}
		}
		$options = $GLOBALS['egw']->js->convert_phparray_jsarray("options['$name']", $options, true);
		$GLOBALS['egw']->js->set_onload("if(!options) {
				var options = new Object();
			}\n
			$options;\n
			ajax_select_widget_setup('$name', '$onchange', options['$name'], '" . $GLOBALS['egw_info']['flags']['currentapp'] . "');
		");
		$GLOBALS['egw']->js->validate_file('.', 'ajax_select', 'etemplate');

		return True;	// no extra label
	}

	function post_process($name,&$value,&$extension_data,&$loop,&$tmpl,$value_in)
	{
		//echo "<p>ajax_select_widget.post_process: $name = "; _debug_array($value_in);_debug_array($extension_data);
		if(!is_array($value_in)) {
			$value_in = $extension_data['old_value'];
		}
		// Check for blur text left in
		if($extension_data['options']['id_field'] == self::ARRAY_KEY && $value_in['search'] == lang('Search...') ) {
			$value_in['search'] = '';
		}

		// They typed something in, but didn't choose a result
		if(!$value_in['value'] && $value_in['search']) {
			list($get_rows_app, $get_rows_class, $get_rows_method) = explode('.', $extension_data['options']['get_rows']);
			if($get_rows_app && $get_rows_class) {
				if (is_object($GLOBALS[$get_rows_class])) {       // use existing instance (put there by a previous CreateObject)
					$get_rows_obj =& $GLOBALS[$get_rows_class];
				} else {
					$get_rows_obj =& CreateObject($get_rows_app . '.' . $get_rows_class);
				}

				if(!is_object($get_rows_obj) || !method_exists($get_rows_obj, $get_rows_method)) {
					echo "$get_rows_app.$get_rows_class.$get_rows_method is not a valid method for getting the rows";
				} else {
					$query = array_merge($extension_data['options'], $value_in);
					$count = $get_rows_obj->$get_rows_method($query, $results);

					if($count == 1) {
						$value = $results[0][$extension_data['options']['id_field']];
						return true;
					} elseif ($count > 1) {
						etemplate::set_validation_error($name,lang("More than 1 match for '%1'",$value_in['search']));
						$loop = true;
						return false;
					} else {
						$value = $value_in['search'];
						return true;
					}
				}
			}
		} elseif ($extension_data['readonly']) {
			$value = $extension_data['old_value'];
			return true;
		} elseif ($value_in['search'] == '') {
			// They're trying to clear the form
			$value = null;

			// True if not needed, false if needed and they gave no value
			$return = !($extension_data['needed'] && trim($value_in['value']) == '');

			if(!$return) {
				$value = $extension_data['old_value'];
				etemplate::set_validation_error($name,lang('Required'));
				$loop = true;
			}

			if($this->debug && $loop) {
				echo 'Looping...<br />Returning ' . $return . '<br />';
			}
			return $return;
		} else {
			if (stripos($extension_data['options']['id_field'], ";")) {
				$expected_fields = array_flip(explode(";", $extension_data['options']['id_field']));
				$fields_n_values = explode(";", $value_in['value']);
				foreach ($fields_n_values as $field_n_value) {
					list($myfield, $myvalue) = explode(":", $field_n_value);
					if (array_key_exists($myfield, $expected_fields)) {
						$value_in[$myfield] = $myvalue;
					}
				}
				$value = $value_in;
			} else {
				$value = $value_in['value'];
			}
			return true;
		}
	}

	function ajax_search($id, $value, $set_id, $query, $etemplate_id) {
		$base_id = substr($id, 0, strrpos($id, '['));
		$result_id = ($set_id ? $set_id : $base_id . '[results]');
		$response = new xajaxResponse();
		if($query['get_rows']) {
			list($app, $class, $method) = explode('.', $query['get_rows']);
			$this->bo = CreateObject($app . '.' . $class);
			unset($query['get_rows']);
		} else {
			return $response->getXML();
		}

		// Expand lists
		foreach($query as $key => &$row) {
			if($row && strpos($row, ',')) {
				$query[$key] = explode(',', $row);
			}

			// sometimes it sends 'null' (not null)
			if($row == 'null') {
				unset($query[$key]);
			} elseif (is_string($row) && strtolower($row) == 'false') {
				$row = false;
			}
		}
		$query['search'] = $value;
		
		if($query['id_field'] == self::ARRAY_KEY) {
			// Pass base_id so we can get the right values
			$query['field_name'] = $base_id;

			// Check for a provided list of values
			if($request = etemplate_request::read($etemplate_id)) {
				$extension_data = $request->extension_data[$base_id];
				if(is_array($extension_data) && $extension_data['values']) {
					self::$static_values[$base_id] = $extension_data['values'];
				}
			}
		}

		$result_list = array();
		$readonlys = array();
		if(is_object($this->bo)) {
			$count = $this->bo->$method($query, $result_list, $readonlys);
		}
		if(is_array($count)) {
			$count = count($result_list);
		}

		$response->addScript("remove_ajax_results('$result_id')");
		if($count > 0) {
			$response->addScript("add_ajax_result('$result_id', '', '', '" . lang('Select') ."');");
			$count = 0;

			if(!$query['template'] || $query['template'] == 'etemplate.ajax_select_widget.row') {
				$query['template'] = 'etemplate.ajax_select_widget.row';
			}
			foreach($result_list as $key => &$row) {
				if(!is_array($row)) {
					if($query['id_field'] == self::ARRAY_KEY) {
						if(!is_array($row)) {
							// Restructure $row to be an array
							$row = array(
								self::ARRAY_KEY => $key,
								'id_field' => $key,
								'title' => $row
							);
						}
					} else {
						continue;
					}
				}
				
				//check for multiple id's
				//this if control statement is to determine if there are multiple ids in the ID FIELD of the Ajax Widget
				if(stristr($query['id_field'], ';') !=  FALSE) {
					$id_field_keys = explode(';', $query['id_field']);
					if($query['get_title']) {
						//the title will always be created using the first ID FIELD
						if($row[$id_field_keys[0]]) {
							$row['title'] = ExecMethod($query['get_title'], $row[$id_field_keys[0]]);
						}
					}
					foreach($id_field_keys as $value) {
						$id_field_keys_values[] = $value.':'.$row[$value];
					}		
					$row['id_field'] = implode(';',$id_field_keys_values); 
					unset($id_field_keys_values);
				} else {
					if($query['id_field'] && $query['get_title']) {
						if($row[$query['id_field']] && $query['id_field'] != self::ARRAY_KEY) {
							$row['title'] = ExecMethod($query['get_title'], $row[$query['id_field']]);
                                        	}
					}
					if($query['id_field'] != self::ARRAY_KEY) {
						$row['id_field'] = $row[$query['id_field']];
					}
				}
				// If we use htmlspecialchars, it causes issues with mixed quotes.  addslashes() seems to handle it.
				$row['id_field'] = addslashes($row['id_field']);

				$data = ($query['nextmatch_template']) ? array(1=>$row) : $row;
				$widget =& CreateObject('etemplate.etemplate', $query['template']);
				$html = addslashes(str_replace("\n", '', $widget->show($data, '', $readonlys)));
				$row['title'] = addslashes($row['title']);
				
				$response->addScript("add_ajax_result('$result_id', '${row['id_field']}', '" . $row['title'] . "', '$html');");
				$count++;
				if($count > $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs']) {
					$response->addScript("add_ajax_result('$result_id', '', '', '" . lang("%1 more...", (count($result_list) - $count)) . "');");
					break;
				}
			}
		} else {
			$response->addScript("add_ajax_result('$result_id', '', '', '" . lang('No matches found') ."');");
		}
		return $response->getXML();
	}

	/**
	*	Use a simple array to get the title
	*	Values should be passed in to the widget as an array in $size['values']
	*/
	protected function array_title($id, $name) {
		if(trim($id) == '') {
			return lang('Search');
		}
		return self::$static_values[$name][$id];
	}

	/**
	*	Use a simple array to get the results
	*	Values should be passed in to the widget as an array in $size['values']
	*/
	protected function array_rows(&$query, &$result) {
		foreach( self::$static_values[$query['field_name']] as $key => $value) {
			if($query['search'] && stripos($value, $query['search']) === false) continue;

			$result[$key] = $value;
		}
		$count = count($result);
		$result = array_slice($result, $query['start'], $query['num_rows']);
		return $count;
	}
}
