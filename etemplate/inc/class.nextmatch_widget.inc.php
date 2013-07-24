<?php
/**
 * eGroupWare  eTemplate Extension - Nextmatch Widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @copyright 2002-9 by RalfBecker@outdoor-training.de
 * @package etemplate
 * @subpackage extensions
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id: class.nextmatch_widget.inc.php 33657 2011-01-24 16:46:59Z nathangray $
 */

/**
 * eTemplate Extension: Widget that show only a certain number of data-rows and allows to modifiy the rows shown (scroll).
 *
 * This widget replaces the old nextmatch-class. It is independent of the UI,
 * as it only uses etemplate-widgets and has therefor no render-function
 *
 * $content[$id] = array(	// I = value set by the app, 0 = value on return / output
 * 	'get_rows'       =>		// I  method/callback to request the data for the rows eg. 'notes.bo.get_rows'
 * 	'filter_label'   =>		// I  label for filter    (optional)
 * 	'filter_help'    =>		// I  help-msg for filter (optional)
 * 	'no_filter'      => True// I  disable the 1. filter
 * 	'no_filter2'     => True// I  disable the 2. filter (params are the same as for filter)
 * 	'no_cat'         => True// I  disable the cat-selectbox
 *  'cat_app'        =>     // I  application the cat's should be from, default app in get_rows
 * 	'template'       =>		// I  template to use for the rows, if not set via options
 * 	'header_left'    =>		// I  template to show left of the range-value, left-aligned (optional)
 * 	'header_right'   =>		// I  template to show right of the range-value, right-aligned (optional)
 * 	'bottom_too'     => True// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
 *	'never_hide'     => True// I  never hide the nextmatch-line if less then maxmatch entries
 *  'lettersearch'   => True// I  show a lettersearch
 *  'searchletter'   =>     // I0 active letter of the lettersearch or false for [all]
 * 	'start'          =>		// IO position in list
 *	'num_rows'       =>     // IO number of rows to show, defaults to maxmatches from the general prefs
 * 	'cat_id'         =>		// IO category, if not 'no_cat' => True
 * 	'search'         =>		// IO search pattern
 * 	'order'          =>		// IO name of the column to sort after (optional for the sortheaders)
 * 	'sort'           =>		// IO direction of the sort: 'ASC' or 'DESC'
 * 	'col_filter'     =>		// IO array of column-name value pairs (optional for the filterheaders)
 * 	'filter'         =>		// IO filter, if not 'no_filter' => True
 * 	'filter_no_lang' => True// I  set no_lang for filter (=dont translate the options)
 *	'filter_onchange'=> 'this.form.submit();' // I onChange action for filter, default: this.form.submit();
 * 	'filter2'        =>		// IO filter2, if not 'no_filter2' => True
 * 	'filter2_no_lang'=> True// I  set no_lang for filter2 (=dont translate the options)
 *	'filter2_onchange'=> 'this.form.submit();' // I onChange action for filter2, default: this.form.submit();
 * 	'rows'           =>		//  O content set by callback
 * 	'total'          =>		//  O the total number of entries
 * 	'sel_options'    =>		//  O additional or changed sel_options set by the callback and merged into $tmpl->sel_options
 * 	'no_columnselection' => // I  turns off the columnselection completly, turned on by default
 * 	'columnselection-pref' => // I  name of the preference (plus 'nextmatch-' prefix), default = template-name
 * 	'default_cols'   => 	// I  columns to use if there's no user or default pref (! as first char uses all but the named columns), default all columns
 * 	'options-selectcols' => // I  array with name/label pairs for the column-selection, this gets autodetected by default. A name => false suppresses a column completly.
 *  'return'         =>     // IO allows to return something from the get_rows function if $query is a var-param!
 *  'csv_fields'     =>		// I  false=disable csv export, true or unset=enable it with auto-detected fieldnames,
 * or array with name=>label or name=>array('label'=>label,'type'=>type) pairs (type is a eT widget-type)
 * );
 */
class nextmatch_widget
{
	/**
	 * Prefix for custom field names
	 *
	 */
	const CF_PREFIX = '#';

	/**
	 * exported methods of this class
	 * @var array
	 */
	public $public_functions = array(
		'pre_process' => True,
		'post_process' => True,
	);
	/**
	 * availible extensions and there names for the editor
	 * @var array
	 */
	public $human_name = array(
		'nextmatch'               => 'Nextmatch',
		'nextmatch-sortheader'    => 'Nextmatch Sortheader',
		'nextmatch-filterheader'  => 'Nextmatch Filterheader',
		'nextmatch-accountfilter' => 'Nextmatch Accountfilter',
		'nextmatch-customfilter'  => 'Nextmatch Custom Filterheader',
		'nextmatch-header'        => 'Nextmatch Header',
		'nextmatch-customfields'  => 'Nextmatch Custom Fields Header',
	);

	/**
	 * Turn on debug messages (mostly in post_process)
	 *
	 * @var boolean
	 */
	public $debug = false;

	/**
	 * Vars used to comunicated for the custom field header
	 *
	 * @var unknown_type
	 */
	private $selectcols;
	public $cf_header;
	private $cfs;

	/**
	 * Constructor of the extension
	 *
	 * @param string $ui '' for html
	 */
	public function __construct($ui)
	{
	}

	/**
	 * returns last part of a form-name
	 *
	 * @param string $name
	 * @return string
	 */
	static private function last_part($name)
	{
		list($last) = self::get_parts($name,-1,1);
		return $last;
	}

	/**
	 * returns last part of a form-name
	 *
	 * @param string $name
	 * @param int $offset positive or negative offset (negative is count from the end)
	 * @param int $length=null positiv means return $length elements, negative return til negative offset in $length, default = null means all
	 * @return array
	 */
	static private function get_parts($name,$offset,$length=null)
	{
		$parts = explode('[',str_replace(']','',$name));
		// php5.1 seems to have a bug: array_slice($parts,$offeset) != array_slice($parts,$offeset,null)
		return is_null($length) ? array_slice($parts,$offset) : array_slice($parts,$offset,$length);
	}

	/**
	 * Return global storages shared within the parts of a nextmatch widget (including all nextmatch-* subwidgets)
	 *
	 * @param string $name
	 * @param string $type
	 * @return mixed reference to storage: use =& to assign!
	 */
	static private function &get_nm_global($name,$type)
	{
		static $nm_globals = array();

		// extract the original nextmatch name from $name, taken into account the type of nextmatch-* subwidgets
		$nm_global = implode('/',self::get_parts($name,1,$type == 'nextmatch' ? null : -2));
		//echo '<p>'.__METHOD__."($name,$type) = $nm_global</p>\n";

		return $nm_globals[$nm_global];
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
	 * @param etemplate &$tmpl reference to the template we belong too
	 * @return boolean true if extra label is allowed, false otherwise
	 */
	public function pre_process($name,&$value,array &$cell,&$readonlys,&$extension_data,etemplate &$tmpl)
	{
		$nm_global =& self::get_nm_global($name,$cell['type']);
		//echo "<p>nextmatch_widget.pre_process(name='$name',type='$cell[type]'): value = "; _debug_array($value);
		//echo "<p>nextmatch_widget.pre_process(name='$name',type='$cell[type]'): nm_global = "; _debug_array($nm_global);

		$extension_data = array(
			'type' => $cell['type']
		);
		switch ($cell['type'])
		{
			case 'nextmatch-header':
				$cell['type'] = 'label';
				return true;	// false = no extra label

			case 'nextmatch-sortheader':	// Option: default sort: ASC(default) or DESC
				$extension_data['default_sort'] = $cell['size']&&preg_match('/^(ASC|DESC)/i',$cell['size'],$matches) ? strtoupper($matches[1]) : 'ASC';
				$cell['type'] = $cell['readonly'] ? 'label' : 'button';
				$cell['onchange'] = True;
				if (!$cell['help'])
				{
					$cell['help'] = 'click to order after that criteria';
				}
				if (self::last_part($name) == $nm_global['order'] && !$cell['readonly'])	// we're the active column
				{
					$sorting = $cell;
					unset($sorting['align']);
					unset($sorting['span']);
					$cell = etemplate::empty_cell('hbox','',array(
						'span' => $cell['span'],
						'size' => '2,,0,0',
						1 => $sorting,
						2 => etemplate::empty_cell('image',$nm_global['sort'] != 'DESC' ? 'down' : 'up'),
					));
					$class = 'activ_sortcolumn';
				}
				else
				{
					$class = 'inactiv_sortcolumn';
				}
				$parts = explode(',',$cell['span']);
				$parts[1] .= ($parts[1] ? ' ' : '').$class;
				$cell['span'] = implode(',',$parts);
				return True;

			case 'nextmatch-accountfilter':	// Option: as for selectbox: [extra-label(default ALL)[,#lines(default 1)]]
				$cell['size'] = 'select-account,'.$cell['size'];
				// fall through
			case 'nextmatch-customfilter':	// Option: widget-name, options as for selectbox
				list($type,$cell['size']) = explode(',',$cell['size'],2);
				// fall through
			case 'nextmatch-filterheader':	// Option: as for selectbox: [extra-label(default ALL)[,#lines(default 1)]]
				if (!$type) $type = 'select';
				$cell['type'] = $type;
				if (!$cell['size'] && $type != 'link-entry')	// link-entry without option shows application selection!
				{
					$cell['size'] = 'All';
				}
				if (!$cell['help'])
				{
					$cell['help'] = 'select which values to show';
				}
				$cell['onchange'] = $cell['noprint'] = True;
				$parts = explode(',',$cell['span']);
				$parts[1] .= ($parts[1] ? ' ' : '').'filterheader';
				$cell['span'] = implode(',',$parts);
				$extension_data['old_value'] = $value = $nm_global['col_filter'][self::last_part($name)];
				return True;

			case 'nextmatch-customfields':
				$extra_label = $this->pre_process_cf_header($cell,$tmpl, $value, $nm_global);
				$extension_data['old_value'] = $value;
				return $extra_label;
		}
		// does NOT work with php5.2.6+
		if (version_compare(PHP_VERSION,'5.2.6','>='))
		{
			$value['bottom_too'] = false;
		}
		// presetting the selectboxes with their default values, to NOT loop, because post-process thinks they changed
		if (!isset($value['cat_id'])) $value['cat_id'] = '';
		if (!isset($value['search'])) $value['search'] = '';
		foreach(array('filter','filter2') as $f)
		{
			if (!isset($value[$f]))
			{
				list($value[$f]) = isset($tmpl->sel_options[$f]) ? @each($tmpl->sel_options[$f]) : @each($value['options-'.$f]);
				if (!is_string($value[$f])) $value[$f] = (string) $value[$f];
			}
		}
		// save values in persistent extension_data to be able use it in post_process
		unset($value['rows']);
		$extension_data += $value;

		$value['no_csv_export'] = $value['csv_fields'] === false ||
			$GLOBALS['egw_info']['server']['export_limit'] && !is_numeric($GLOBALS['egw_info']['server']['export_limit']) &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']);

		if (!$value['filter_onchange']) $value['filter_onchange'] = 'this.form.submit();';
		if (!$value['filter2_onchange']) $value['filter2_onchange'] = 'this.form.submit();';
		if (!isset($value['cat_app'])) list($value['cat_app']) = explode('.',$value['get_rows']);	// if no cat_app set, use the app from the get_rows func

		if (!($max = (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'])) $max = 15;
		$row_options = array();
		foreach(array(5,12,25,50,100,200,500,999) as $n)
		{
			if ($n-5 <= $max && $max <= $n+5) $n = $max;
			$row_options[$n] = $n;
		}
		if (!isset($row_options[$max]) || !isset($row_options[$value['num_rows']]))
		{
			$row_options[$max] = $max;
			$row_options[$value['num_rows']] = $value['num_rows'];
			ksort($row_options);
		}
		$value['options-num_rows'] =& $row_options;

		if (!isset($value['num_rows'])) $extension_data['num_rows'] = $value['num_rows'] = $max;
		if ($value['num_rows'] != $max)
		{
			$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] = $max = (int)$value['num_rows'];
		}
		if (!$value['no_columnselection'])
		{
			// presetting the options for selectcols empty, so the get_rows function can set it
			$value['options-selectcols'] = is_array($value['options-selectcols']) ? $value['options-selectcols'] : array();
		}
		$rows = array();
		if (!is_array($readonlys)) $readonlys = array();
		if (($total = $extension_data['total'] = $value['total'] = self::call_get_rows($value,$rows,$readonlys['rows'])) === false)
		{
			//error_log(__METHOD__."() etemplate::set_validation_error('$name') '$value[get_rows]' is no valid method!!!");
			etemplate::set_validation_error($name,__METHOD__."($cell[name]): '$value[get_rows]' is no valid method !!!");
		}
		// allow the get_rows function to override / set sel_options
		if (isset($rows['sel_options']) && is_array($rows['sel_options']))
		{
			$tmpl->sel_options = array_merge($tmpl->sel_options,$rows['sel_options']);
			unset($rows['sel_options']);
		}
		$value['rows'] =& $rows;
		unset($rows);

		list($template,$options) = explode(',',$cell['size']);
		if (!$value['template'] && $template)	// template name can be supplied either in $value['template'] or the options-field
		{
			$value['template'] = $template;
		}
		if (!is_object($value['template']))
		{
			$value['template'] = new etemplate($value['template'],$tmpl->as_array());
		}
		if (is_array($value['rows'][0]))	// pad 0 based arrays with rows-1 false values
		{
			for($i = 1; $i < $value['template']->rows; $i++)
			{
				array_unshift($value['rows'],false);
			}
		}
		$extension_data['template'] = $value['template']->name;	// used for the column-selection, and might be set in get_rows()
		$extension_data['columnselection_pref'] = $value['columnselection_pref'];

		if ($total < 1 && $value['template']->rows > 1)
		{
			$value['template']->data[0]['h'.$value['template']->rows] .= ',1';	// disable the last data row
		}
		if (!$value['never_hide'] && $total <= $max && $options && $value['search'] == '' &&
			 ($value['no_cat'] || !$value['cat_id']) &&
			 ($value['no_filter'] || !$value['filter'] || $value['filter'] == 'none') &&
			 ($value['no_filter2'] || !$value['filter2'] || $value['filter2'] == 'none'))
		{											// disable whole nextmatch line if no scrolling necessary
			if ($value['header_left'] || $value['header_right'])
			{
				$nextmatch = new etemplate('etemplate.nextmatch_widget.header_only');
				$cell['size'] = $cell['name'];
				$cell['obj'] = &$nextmatch;
				$cell['name'] = $nextmatch->name;
			}
			else
			{
				$cell['size'] = $cell['name'].'[rows]';
				$cell['obj'] = &$value['template'];
				$cell['name'] = $value['template']->name;
			}
		}
		else
		{
			$nextmatch = new etemplate('etemplate.nextmatch_widget');
			$nextmatch->read('etemplate.nextmatch_widget');
			// keep the editor away from the generated tmpls
			$nextmatch->no_onclick = true;

			if ($value['lettersearch'])
			{
				$lettersearch =& $nextmatch->get_widget_by_name('lettersearch');	// hbox for the letters
				if (($alphabet = lang('alphabet')) == 'alphabet*') $alphabet = 'a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z';
				$alphabet = explode(',',$alphabet);
				$alphabet['all'] = lang('all');
				foreach($alphabet as $key => $letter)
				{
					// make each letter internally behave like a button
					$form_name = $name.'[searchletter]['.($key === 'all' ? $key : $letter).']';
					etemplate::$request->set_to_process($form_name,'button');

					if (!$key) $letterbox =& $lettersearch[1];	// to re-use the first child
					$letterbox = etemplate::empty_cell('label',$letter,array(
						'label'   => $letter,
						'span'    => ',lettersearch'.($letter == (string) $value['searchletter'] ||
							$key === 'all' && !$value['searchletter'] ? '_active' : ''),
						'no_lang' => 2,
						'align'   => $key == 'all' ? 'right' : '',
						'onclick' => 'return submitit('.etemplate::$name_form.",'$form_name');",
					));
					// if not the first (re-used) child, add it to the parent
					if ($key) etemplate::add_child($lettersearch,$letterbox);
					unset($letterbox);
				}
				//_debug_array(etemplate::$request->to_process);
			}
			if(isset($value['no_search'])) $value['no_start_search'] = $value['no_search'];
			foreach(array('no_cat'=>'cat_id','no_filter'=>'filter','no_filter2'=>'filter2', 'no_search' => 'search', 'no_start_search' => 'start_search' ) as $val_name => $cell_name)
			{
				if (isset($value[$val_name])) $nextmatch->disable_cells($cell_name,$value[$val_name]);
			}
			foreach(array('header_left','header_right') as $name)
			{
				if (!$value[$name]) $nextmatch->disable_cells('@'.$name);
			}
			foreach(array('filter','filter2') as $cell_name)
			{
				if (isset($value[$cell_name.'_no_lang'])) $nextmatch->set_cell_attribute($cell_name,'no_lang',$value[$cell_name.'_no_lang']);
			}
			$start = $value['start'];
			$end   = $start+$max > $total ? $total : $start+$max;
			$value['range'] = $total ? (1+$start) . ' - ' . $end : '0';
			$nextmatch->set_cell_attribute('first','readonly',$start <= 0);
			$nextmatch->set_cell_attribute('left', 'readonly',$start <= 0);
			$nextmatch->set_cell_attribute('right','readonly',$start+$max >= $total);
			$nextmatch->set_cell_attribute('last', 'readonly',$start+$max >= $total);

			$cell['size'] = $cell['name'];
			$cell['obj'] = &$nextmatch;
			$cell['name'] = $nextmatch->name;
		}
		// preset everything for the column selection
		if (!$value['no_columnselection'])
		{
			$name = is_object($value['template']) ? $value['template']->name : $value['template'];
			list($app) = explode('.',$name);
			if (isset($value['columnselection_pref'])) $name = $value['columnselection_pref'];
			$this->selectcols = $value['selectcols'] = $GLOBALS['egw_info']['user']['preferences'][$app]['nextmatch-'.$name];
			// fetching column-names & -labels from the template
			if ($this->cols_from_tpl($value['template'],$value['options-selectcols'],$name2col,$value['rows'],$value['selectcols']))
			{
				//_debug_array($name2col);
				//_debug_array($value['options-selectcols']);
				// getting the selected colums from the prefs (or if not set a given default or all)
				if (!$value['selectcols'])
				{
					$value['selectcols'] = array_keys($value['options-selectcols']);
					if (isset($value['default_cols']))
					{
						if ($value['default_cols'][0] == '!')
						{
							$value['selectcols'] = array_diff($value['selectcols'],explode(',',substr($value['default_cols'],1)));
						}
						else
						{
							$value['selectcols'] = $value['default_cols'];
						}
					}
					$this->selectcols = $value['selectcols'];
				}
				if (!is_array($value['selectcols'])) $value['selectcols'] = explode(',',$value['selectcols']);
				foreach(array_unique(array_keys($value['options-selectcols']+$name2col)) as $name)
				{
					// set 'no_'.$col for each column-name to true, if the column is not selected
					// (and the value is not set be the get_rows function / programmer!)
					if (!isset($value['rows']['no_'.$name])) $value['rows']['no_'.$name] = !in_array($name,$value['selectcols']);
					// setting '@no_'.$name as disabled attr for each column, that has only a single nextmatch-header
					if (is_object($value['template']))
					{
						$col = $name2col[$name];
						list(,$disabled) = $value['template']->set_column_attributes($col);
						//echo "<p>$col: $name: $disabled</p>\n";
						if (!isset($disabled)) $value['template']->set_column_attributes($col,0,'@no_'.$name);
					}
				}
				//_debug_array($value);
				if (is_object($nextmatch))
				{
					$size =& $nextmatch->get_cell_attribute('selectcols','size');
					if ($size > count($value['options-selectcols'])) $size = '0'.count($value['options-selectcols']);
					if (!$GLOBALS['egw_info']['user']['apps']['admin'])
					{
						$nextmatch->disable_cells('default_prefs');
					}
				}
				// should reset on each submit
				unset($value['default_prefs']);
			}
		}
		$cell['type'] = 'template';
		$cell['label'] = $cell['help'] = '';

		foreach(array('sort','order','col_filter') as $n)	// save them for the sortheader
		{
			$nm_global[$n] = $value[$n];
		}
		$value['bottom'] = $value;	// copy the values for the bottom-bar

		return False;	// NO extra Label
	}

	/**
	 * Calling our callback
	 *
	 * Signature of get_rows callback is either:
	 * a) int get_rows($query,&$rows,&$readonlys)
	 * b) int get_rows(&$query,&$rows,&$readonlys)
	 *
	 * If get_rows is called static (and php >= 5.2.3), it is always b) independent on how it's defined!
	 *
	 * @param array &$value
	 * @param array &$rows=null
	 * @param array &$readonlys=null
	 * @param object $obj=null (internal)
	 * @param string|array $method=null (internal)
	 * @return int|boolean total items found of false on error ($value['get_rows'] not callable)
	 */
	private static function call_get_rows(array &$value,array &$rows=null,array &$readonlys=null,$obj=null,$method=null)
	{
		if (is_null($method)) $method = $value['get_rows'];

		if (is_null($obj))
		{
			// allow static callbacks
			if(strpos($method,'::') !== false)
			{
				list($class,$method) = explode('::',$method);

				//  workaround for php < 5.2.3: do NOT call it static, but allow application code to specify static callbacks
				if (version_compare(PHP_VERSION,'5.2.3','>='))
				{
					$method = array($class,$method);
					unset($class);
				}
			}
			else
			{
				list($app,$class,$method) = explode('.',$value['get_rows']);
			}
			if ($class)
			{
				if (!$app && !is_object($GLOBALS[$class]))
				{
					$GLOBALS[$class] = new $class();
				}
				if (is_object($GLOBALS[$class]))	// use existing instance (put there by a previous CreateObject)
				{
					$obj = $GLOBALS[$class];
				}
				else
				{
					$obj = CreateObject($app.'.'.$class);
				}
			}
		}
		if(is_callable($method))	// php5.2.3+ static call (value is always a var param!)
		{
			$total = call_user_func_array($method,array(&$value,&$rows,&$readonlys));
		}
		elseif(is_object($obj) && method_exists($obj,$method))
		{
			if (!is_array($readonlys)) $readonlys = array();
			$total = $obj->$method($value,$rows,$readonlys);
		}
		else
		{
			$total = false;	// method not callable
		}
		if ($method && $total && $value['start'] >= $total)
		{
			$value['start'] = 0;
			$total = self::call_get_rows($value,$rows,$readonlys,$obj,$method);
		}
		// otherwise we get stoped by max_excutiontime
		if ($total > 200) @set_time_limit(0);
		//error_log($value['get_rows'].'() returning '.array2string($total).', method = '.array2string($method).', value = '.array2string($value));
		return $total;
	}

	/**
	 * Preprocess for the custom fields header
	 *
	 * @param array &$cell
	 */
	private function pre_process_cf_header(array &$cell,etemplate $tmpl, &$value, $nm_global)
	{
		//echo __CLASS__.'::'.__METHOD__."() selectcols=$this->selectcols\n";
		if (is_null($this->cfs))
		{
			list($app) = explode('.',$tmpl->name);

			$this->cfs = config::get_customfields($app);
		}

		// Needed for custom fields linking to other apps
		$cell_name = $cell['name'];

		$cell['type'] = 'vbox';
		$cell['name'] = '';
		$cell['size'] = '0,,0,0';

		if ($this->selectcols)
		{
			foreach(is_array($this->selectcols) ? $this->selectcols : explode(',',$this->selectcols) as $col)
			{
				if ($col[0] == self::CF_PREFIX) $allowed[] = $col;
			}
		}
		foreach($this->cfs as $name => $field)
		{
			if (!$allowed || in_array(self::CF_PREFIX.$name,$allowed))
			{
				if($field['type'] == 'select')
				{
					$header =& etemplate::empty_cell('nextmatch-filterheader',self::CF_PREFIX.$name,array(
						'sel_options' => $field['values'],
						'size'        => $field['label'],
						'no_lang'     => True,
						'readonly'    => $cell['readonly'],
					));
				}
				elseif($GLOBALS['egw_info']['apps'][$field['type']])
				{
					$header =& etemplate::empty_cell('link-entry', $cell_name . '['.self::CF_PREFIX.$name .']', array(
						'label'		=>	$field['label'],
						'size'		=>	$field['type'],
						'readonly'	=>	$cell['readonly'],
						'onchange'	=>	1
					));
					etemplate::add_child($cell,$header);
					unset($header);

					$header =& etemplate::empty_cell('label', '');
					$value[self::CF_PREFIX.$name] = $field['type'] . ':' . $nm_global['col_filter'][self::CF_PREFIX.$name];
				}
				else
				{
					$header =& etemplate::empty_cell('nextmatch-sortheader',self::CF_PREFIX.$name,array(
						'label'       => $field['label'],
						'readonly'    => $cell['readonly'],
					));
				}
				etemplate::add_child($cell,$header);
				unset($header);
			}
		}
		// do we have more then 5 cf's to display --> limit header height to 5 lines plus vertical scrollbar
		$num = !$allowed ? count($this->cfs) : count($allowed);
		if ($num > 5)
		{
			$vbox = $cell;
			$cell = etemplate::empty_cell('box','',array(
				'size' => '0,,0,0',
				'span' => ',cf_header_height_limit',
			));
			etemplate::add_child($cell,$vbox);
		}
		return false;	// no extra label
	}

	/**
	 * Extract the column names and labels from the template
	 *
	 * @param etemplate &$tmpl
	 * @param array &$cols here we add the column-name/-label
	 * @param array &$name2col
	 * @param array $content nextmatch content, to be able to resolve labels with @name
	 * @param array $selectcols selected colums
	 * @return int columns found, count($cols)
	 */
	private function cols_from_tpl(etemplate $tmpl,&$cols,&$name2col,&$content,$selectcols)
	{
		//_debug_array($cols);
		// fetching column-names & -labels from the template
		$cols['__content__'] =& $content;
		$tmpl->widget_tree_walk(array($this,'cols_from_tpl_walker'),$cols);
		unset($cols['__content__']);
		$col2names = is_array($cols['col2names']) ? $cols['col2names'] : array(); unset($cols['col2names']);
		//_debug_array($col2names);
		//_debug_array($cols);
		foreach($cols as $name => $label)
		{
			if (!$label) unset($cols[$name]);
		}
		//_debug_array($cols);
		$cols2 = $cols;
		$cols = array();
		foreach($cols2 as $name => $label)
		{
			$col = $name;
			// and replace the column letter then with the name concatinated by an underscore
			if (is_array($col2names[$name]))
			{
				$name = implode('_',$col2names[$name]);
				$name2col[$name] = $col;
			}
			$cols[$name] = $label;

			// we are behind the column of a custom fields header --> add the individual fields
			if ($name == $this->cf_header && (!$selectcols ||
				in_array($this->cf_header,explode(',',$selectcols))))
			{
				$cols[$name] .= ':';
				list($app) = explode('.',$tmpl->name);
				if (($this->cfs = config::get_customfields($app)))
				{
					foreach($this->cfs as $name => $field)
					{
						$cols[self::CF_PREFIX.$name] = '- '.$field['label'];
					}
				}
				else
				{
					unset($cols[$name]);	// no cf's defined -> no header
				}
			}
		}
		//_debug_array($cols);
		return count($cols);
	}

	/**
	 * Extract the column names and labels from the template (callback for etemplate::widget_tree_walk())
	 *
	 * @param array &$widget
	 * @param array &$cols here we add the column-name/-label
	 * @param string $path
	 */
	function cols_from_tpl_walker(&$widget,&$cols,$path)
	{
		list($type,$subtype) = explode('-',$widget['type']);

		if ($subtype == 'customfields')
		{
			if (!$widget['name']) $widget['name'] = 'customfields';
			if (!$widget['label']) $widget['label'] = 'Custom fields';
			$this->cf_header = $widget['name'];
		}
		if ($type != 'nextmatch' || !$subtype || !$widget['name'] || $widget['disabled'])
		{
			return;
		}
		$options = explode(',',$widget['size']);
		if (!($label = $widget['label']) || in_array($subtype,array('header','sortheader')) && $options[1])
		{
			if (in_array($subtype,array('customfilter','sortheader'))) $subtype = array_shift($options);

			$label = $options[0];

			// some widgets have label as second option (column name with _ as first), not a perfect detection ...
			if (strpos($label,'_') !== false && !empty($options[1])) $label = $options[1];
		}
		list(,,$col,$sub) = explode('/',$path);
		$row = (int)$col;
		$col = substr($col,$row > 9 ? 2 : 1);
		if (($label[0] == '@' || strchr($lable,'$') !== false) && is_array($cols['__content__']))
		{
			$label = etemplate::expand_name($label,$col,$row,'','',$cols['__content__']);
		}
		if (!isset($cols[$widget['name']]) && $label)
		{
			$label = substr($label,-3) == '...' ? lang(substr($label,0,-3)) : lang($label);

			if (empty($label) || strpos($cols[$col],$label) === false)
			{
				$cols[$col] .= ($cols[$col] ? ', ' : '').$label;
			}
		}
		$cols['col2names'][$col][] = $widget['name'];

		//echo "<p>$path: $widget[name] $label</p>\n";
	}

	/**
	 * postprocessing method, called after the submission of the form
	 *
	 * It has to copy the allowed/valid data from $value_in to $value, otherwise the widget
	 * will return no data (if it has a preprocessing method). The framework insures that
	 * the post-processing of all contained widget has been done before.
	 *
	 * Only used by select-dow so far
	 *
	 * @param string $name form-name of the widget
	 * @param mixed &$value the extension returns here it's input, if there's any
	 * @param mixed &$extension_data persistent storage between calls or pre- and post-process
	 * @param boolean &$loop can be set to true to request a re-submision of the form/dialog
	 * @param etemplate &$tmpl the eTemplate the widget belongs too
	 * @param mixed &value_in the posted values (already striped of magic-quotes)
	 * @return boolean true if $value has valid content, on false no content will be returned!
	 */
	public function post_process($name,&$value,&$extension_data,&$loop,etemplate &$tmpl,$value_in)
	{
		$nm_global =& self::get_nm_global($name,$extension_data['type']);

		if ($this->debug) { echo "<p>nextmatch_widget.post_process(type='$extension_data[type]', name='$name',value_in=".print_r($value_in,true).",order='$nm_global[order]'): value = "; _debug_array($value); }

		switch($extension_data['type'])
		{
			case 'nextmatch':
				break;

			case 'nextmatch-sortheader':
				if ($value_in)
				{
					$nm_global['order'] = self::last_part($name);
					$nm_global['default_sort'] = $extension_data['default_sort'];
				}
				return False;	// dont report value back, as it's in the wrong location (rows)

			case 'nextmatch-customfields':
				$this->post_process_cf_header($value, $extension_data, $tmpl, $value_in, $nm_global);
				return False;   // dont report value back, as it's in the wrong location (rows)
			case 'link-entry':	// allways return app:id, if an entry got selected, otherwise null
				if (is_array($value_in) && !empty($value_in['id']))
				{
					$value_in = (isset($value_in['app']) ? $value_in['app'] : $extension_data['app']).':'.$value_in['id'];
				}
				else
				{
					$value_in = null;
				}
				// fall through
			default:
			case 'select-account':		// used by nextmatch-accountfilter
			case 'nextmatch-filterheader':
				if ((string)$value_in != (string)$extension_data['old_value'])
				{
					if ($this->debug) echo "<p>setting nm_global[filter][".self::last_part($name)."]='$value_in' (was '$extension_data[old_value]')</p>\n";
					$nm_global['filter'][self::last_part($name)] = $value_in;
				}
				return False;	// dont report value back, as it's in the wrong location (rows)

			case 'nextmatch-header':
				return False;	// nothing to report
		}
		$old_value = $extension_data;
		if ($this->debug) { echo "old_value="; _debug_array($old_value); }

		$value['start'] = $old_value['start'];	// need to be set, to be reported back
		$value['return'] = $old_value['return'];

		if (is_array($value['bottom']))			// we have a second bottom-bar
		{
			$inputs = array('search','cat_id','filter','filter2','num_rows');
			foreach($inputs as $name)
			{
				if (isset($value['bottom'][$name]) && $value[$name] == $old_value[$name])
				{
					if ($this->debug) echo "value[$name] overwritten by bottom-value[$name]='".$value['bottom'][$name]."', old_value[$name]='".$old_value[$name]."'<br>\n";
					$value[$name] = $value['bottom'][$name];
				}
			}
			$buttons = array('start_search','first','left','right','last','export');
			foreach($buttons as $name)
			{
				if (isset($value['bottom'][$name]) && $value['bottom'][$name])
				{
					$value[$name] = $value['bottom'][$name];
				}
			}
			if ($value['bottom']['savecols'])
			{
				$value['selectcols'] = $value['bottom']['selectcols'];
				$value['default_prefs'] = $value['bottom']['default_prefs'];
			}
			unset($value['bottom']);
		}
		if (isset($old_value['num_rows']) && !is_null($value['num_rows']) && $value['num_rows'] != $old_value['num_rows'])
		{
			if ($this->debug) echo "<p>nextmatch_widget::post_process() num_rows changed {$old_value['num_rows']} --> {$value['num_rows']} ==> looping</p>\n";
			$loop = true;	// num_rows changed
		}
		// num_rows: use old value in extension data, if $value['num_rows'] is not set because nm-header is not shown
		$value['num_rows'] = isset($value['num_rows']) ? (int) $value['num_rows'] : (int) $extension_data['num_rows'];
		$max = $value['num_rows'] ? $value['num_rows'] : (int)$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];

		if(strpos($value['search'],'xjxquery')) {
			// We deal with advancedsearch
			$aXml = $value['search'];
			$value['search'] = '';
			$aXml = str_replace("<xjxquery><q>","&",$aXml);
			$aXml = str_replace("</q></xjxquery>","",$aXml);
			$value['advsearch_cont'] = explode('&',$aXml);
			//$GLOBALS['egw']->boetemplate = &CreateObject('etemplate.boetemplate');
			//print_r($GLOBALS['egw']->boetemplate->get_array($value['advsearch_cont'],'exec[]',true));
			//$value['advsearch_cont'] = preg_replace("/exec\[ (.*)/",'$1',$value['advsearch_cont']);
			//$value['advsearch_cont'] = preg_replace("\]",'',$value['advsearch_cont']);
			//print_r($value['advsearch_cont']);
		}

		if ($value['start_search'] || $value['search'] != $old_value['search'] ||
			isset($value['cat_id']) && $value['cat_id'] != $old_value['cat_id'] ||
			isset($value['filter']) && $value['filter'] != $old_value['filter'] ||
			isset($value['filter2']) && $value['filter2'] != $old_value['filter2'])
		{
			if ($this->debug)
			{
				echo "<p>search='$old_value[search]'->'$value[search]', filter='$old_value[filter]'->'$value[filter]', filter2='$old_value[filter2]'->'$value[filter2]'<br>";
				echo "new filter --> loop</p>";
				echo "value ="; _debug_array($value);
				echo "old_value ="; _debug_array($old_value);
			}
			$loop = True;
		}
		elseif ($value['first'] || $value['left'] && $old_value['start'] < $max)
		{
			$value['start'] = 0;
			unset($value['first']);
			$loop = True;
		}
		elseif ($value['left'])
		{
			$value['start'] = $old_value['start'] - $max;
			unset($value['left']);
			$loop = True;
		}
		elseif ($value['right'])
		{
			$value['start'] = $old_value['start'] + $max;
			unset($value['right']);
			$loop = True;
		}
		elseif ($value['last'])
		{
			$value['start'] = (int) (($old_value['total']-1) / $max) * $max;
			unset($value['last']);
			$loop = True;
		}
		elseif ($nm_global['order'])
		{
			$value['order'] = $nm_global['order'];
			if ($old_value['order'] != $value['order'])
			{
				$value['sort'] = $nm_global['default_sort'];
			}
			else
			{
				$value['sort'] = $old_value['sort'] != 'DESC' ? 'DESC' : 'ASC';
			}
			if ($this->debug) echo "<p>old_value=$old_value[order]/$old_value[sort] ==> $value[order]/$value[sort]</p>\n";
			$loop = True;
		}
		elseif ($nm_global['filter'])
		{
			if (!is_array($value['col_filter'])) $value['col_filter'] = array();

			$value['col_filter'] += $nm_global['filter'];
			$loop = True;
		}
		elseif (isset($value['searchletter']))
		{
			list($value['searchletter']) = @each($value['searchletter']);
			if ($value['searchletter'] === 'all') $value['searchletter'] = false;
			$loop = True;
		}
		if ($value['savecols'])
		{
			$name = is_object($extension_data['template']) ? $extension_data['template']->name : $extension_data['template'];
			list($app) = explode('.',$name);
			if (isset($extension_data['columnselection_pref'])) $name = $extension_data['columnselection_pref'];
			$pref = !$GLOBALS['egw_info']['user']['apps']['admin'] && $value['default_prefs'] ? 'default' : 'user';
			$GLOBALS['egw_info']['user']['preferences'] = $GLOBALS['egw']->preferences->add($app,'nextmatch-'.$name,is_array($value['selectcols']) ?
				implode(',',$value['selectcols']) : $value['selectcols'],$pref);
			$GLOBALS['egw']->preferences->save_repository(false,$pref);
			$loop = True;
		}
		if ($value['export'])
		{
			self::csv_export($extension_data);
		}
		return True;
	}

	/**
	 * Postprocess for the custom fields header to do filtering on custom fields that are links to other applications
	 *
	 * @param array &$cell
	 */
	private function post_process_cf_header(&$value, $extension_data, $tmpl, $value_in, &$nm_global)
	{
		if (is_null($this->cfs))
		{
			list($app) = explode('.',$tmpl->name);
			$this->cfs = config::get_customfields($app);
		}
		foreach($this->cfs as $name => $field) {
			if($GLOBALS['egw_info']['apps'][$field['type']]) {
				if(is_array($value_in[self::CF_PREFIX.$name])) {
					list($old_app, $old_id) = explode(':', $extension_data['old_value'][self::CF_PREFIX.$name]);
					if($value_in[self::CF_PREFIX.$name]['id'] != '' && $value_in[self::CF_PREFIX.$name]['id'] != $old_id)  {
						$nm_global['filter'][self::CF_PREFIX.$name] = $value_in[self::CF_PREFIX.$name]['id'];
					}

				}
				elseif ((string)$value_in[self::CF_PREFIX.$name] != (string)$extension_data['old_value'][self::CF_PREFIX.$name])
				{
					$nm_global['filter'][self::CF_PREFIX.$name] = $value_in[self::CF_PREFIX.$name]['id'];
				}
				$value = $value_in;
			}
		}
	}

	/**
	 * Export the list as csv file download
	 *
	 * @param array $value array('get_rows' => $method), further values see nextmatch widget $query parameter
	 * @param string $separator=';'
	 * @return boolean false=error, eg. get_rows callback does not exits, true=nothing to export, otherwise we do NOT return!
	 */
	static public function csv_export(&$value,$separator=';')
	{
		if (!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$export_limit = $GLOBALS['egw_info']['server']['export_limit'];
			//if (isset($value['export_limit'])) $export_limit = $value['export_limit'];
		}
		$charset = $charset_out = translation::charset();
		if (isset($value['csv_charset']))
		{
			$charset_out = $value['csv_charset'];
		}
		elseif ($GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'])
		{
			$charset_out = $GLOBALS['egw_info']['user']['preferences']['common']['csv_charset'];
		}
		$backup_start = $value['start'];
		$backup_num_rows = $value['num_rows'];

		$value['start'] = 0;
		$value['num_rows'] = 500;
		$value['csv_export'] = true;	// so get_rows method _can_ produce different content or not store state in the session
		do
		{
			if (!($total = self::call_get_rows($value,$rows)))
			{
				break;	// nothing to export
			}
			if ($export_limit && (!is_numeric($export_limit) || $export_limit < $total))
			{
				etemplate::set_validation_error($name,lang('You are not allowed to export more then %1 entries!',$export_limit));
				return false;
			}
			if (!isset($value['no_csv_support'])) $value['no_csv_support'] = !is_array($value['csv_fields']);

			//echo "<p>start=$value[start], num_rows=$value[num_rows]: total=$total, count(\$rows)=".count($rows)."</p>\n";
			if (!$value['start'])	// send the neccessary headers
			{
				// skip empty data row(s) used to adjust to number of header-lines
				foreach($rows as $row0)
				{
					if (is_array($row0) && count($row0) > 1) break;
				}
				$fp = self::csv_open($row0,$value['csv_fields'],$app,$charset_out,$charset,$separator);
			}
			foreach($rows as $key => $row)
			{
				if (!is_numeric($key) || !$row) continue;	// not a real rows
				fwrite($fp,self::csv_encode($row,$value['csv_fields'],true,$rows['sel_options'],$charset_out,$charset,$separator)."\n");
			}
			$value['start'] += $value['num_rows'];

			@set_time_limit(10);	// 10 more seconds
		}
		while($total > $value['start']);

		unset($value['csv_export']);
		$value['start'] = $backup_start;
		$value['num_rows'] = $backup_num_rows;
		if ($value['no_csv_support'])	// we need to call the get_rows method in case start&num_rows are stored in the session
		{
			self::call_get_rows($value);
		}
		if ($fp)
		{
			fclose($fp);
			common::egw_exit();
		}
		return true;
	}

	/**
	 * Opens the csv output (download) and writes the header line
	 *
	 * @param array $row0 first row to guess the available fields
	 * @param array $fields name=>label or name=>array('lable'=>label,'type'=>type) pairs
	 * @param string $app app-name
	 * @param string $charset_out=null output charset
	 * @param string $charset data charset
	 * @param string $separator=';'
	 * @return FILE
	 */
	private static function csv_open($row0,&$fields,$app,$charset_out=null,$charset=null,$separator=';')
	{
		if (!is_array($fields) || !count($fields))
		{
			$fields = self::autodetect_fields($row0,$app);
		}
		html::content_header('export.csv','text/comma-separated-values');
		//echo "<pre>";

		if (($fp = fopen('php://output','w')))
		{
			$labels = array();
			foreach($fields as $field => $label)
			{
				if (is_array($label)) $label = $label['label'];
				$labels[$field] = $label ? $label : $field;
			}
			fwrite($fp,self::csv_encode($labels,$fields,false,null,$charset_out,$charset,$separator)."\n");
		}
		return $fp;
	}

	/**
	 * CSV encode a single row, including some basic type conversation
	 *
	 * @param array $data
	 * @param array $fields
	 * @param boolean $use_type=true
	 * @param array $extra_sel_options=null
	 * @param string $charset_out=null output charset
	 * @param string $charset data charset
	 * @param string $separator=';'
	 * @return string
	 */
	private static function csv_encode($data,$fields,$use_type=true,$extra_sel_options=null,$charset_out=null,$charset=null,$separator=';')
	{
		$sel_options =& boetemplate::$request->sel_options;

		$out = array();
		foreach($fields as $field => $label)
		{
			$value = (array)$data[$field];
			if ($use_type && is_array($label) && in_array($label['type'],array('select-account','select-cat','date-time','date','select','int','float')))
			{
				foreach($value as $key => $val)
				{
					switch($label['type'])
					{
						case 'select-account':
							if ($val) $value[$key] = common::grab_owner_name($val);
							break;
						case 'select-cat':
							if ($val)
							{
								$cats = array();
								foreach(is_array($val) ? $val : explode(',',$val) as $cat_id)
								{
									$cats[] = $GLOBALS['egw']->categories->id2name($cat_id);
								}
								$value[$key] = implode('; ',$cats);
							}
							break;
						case 'date-time':
						case 'date':
							if ($val)
							{
								try {
									$value[$key] = egw_time::to($val,$label['type'] == 'date' ? true : '');
								}
								catch (Exception $e) {
									// ignore conversation errors, leave value unchanged (might be a wrongly as date(time) detected field
								}
							}
							break;
						case 'select':
							if (isset($sel_options[$field]))
							{
								if ($val) $value[$key] = lang($sel_options[$field][$val]);
							}
							elseif(is_array($extra_sel_options) && isset($extra_sel_options[$field]))
							{
								if ($val) $value[$key] = lang($extra_sel_options[$field][$val]);
							}
							break;
						case 'int':		// size: [min],[max],[len],[precission/sprint format]
						case 'float':
							list(,,,$pre) = explode(',',$label['size']);
							if (($label['type'] == 'float' || !is_numeric($pre)) && $val && $pre)
							{
								$val = str_replace(array(' ',','),array('','.'),$val);
								$value[$key] = is_numeric($pre) ? round($value,$pre) : sprintf($pre,$value);
							}
					}
				}
			}
			$value = implode(', ',$value);

			if (strpos($value,$separator) !== false || strpos($value,"\n") !== false || strpos($value,"\r") !== false)
			{
				$value = '"'.str_replace(array('\\', '"',),array('\\\\','""'),$value).'"';
				$value = str_replace("\r\n", "\n", $value); // to avoid early linebreak by Excel
			}
			$out[] = $value;
		}
		$out = implode($separator,$out);

		if ($charset_out && $charset != $charset_out)
		{
			$out = translation::convert($out,$charset,$charset_out);
		}
		return $out;
	}

	/**
	 * Try to autodetect the fields from the first data-row and the app-name
	 *
	 * @param array $row0 first data-row
	 * @param string $app
	 */
	private static function autodetect_fields($row0,$app)
	{
		$fields = array_combine(array_keys($row0),array_keys($row0));

		foreach($fields as $name => $label)
		{
			// try to guess field-type from the fieldname
			if (preg_match('/(modified|created|start|end)/',$name) && strpos($name,'by')===false &&
				(!$row0[$name] || is_numeric($row0[$name])))	// only use for real timestamps
			{
				$fields[$name] = array('label' => $label,'type' => 'date-time');
			}
			elseif (preg_match('/(cat_id|category|cat)/',$name))
			{
				$fields[$name] = array('label' => $label,'type' => 'select-cat');
			}
			elseif (preg_match('/(owner|creator|modifier|assigned|by|coordinator|responsible)/',$name))
			{
				$fields[$name] = array('label' => $label,'type' => 'select-account');
			}
			elseif(preg_match('/(jpeg|photo)/',$name))
			{
				unset($fields[$name]);
			}
		}
		if ($app)
		{
			$customfields = config::get_customfields($app);

			if (is_array($customfields))
			{
				foreach($customfields as $name => $data)
				{
					$fields['#'.$name] = array(
						'label' => $data['label'],
						'type'  => $data['type'],
					);
				}
			}
		}
		//_debug_array($fields);
		return $fields;
	}
}
