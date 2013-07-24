<?php
/**
 * eGroupWare  eTemplates - Editor
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-8 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage tools
 * @version $Id: class.editor.inc.php 39865 2012-07-18 17:49:24Z ralfbecker $
 */

/**
 * template editor of the eTemplate package
 */
class editor
{
	var $debug;
	/**
	 * eTemplate we edit
	 *
	 * @var etemplate
	 */
	var $etemplate;
	/**
	 * editor eTemplate
	 *
	 * @var etemplate
	 */
	var $editor;
	var $aligns = array(
		'' => 'Left',
		'right' => 'Right',
		'center' => 'Center',
	);
	var $valigns = array(
		'' => 'Middle',
		'top' => 'Top',
		'bottom' => 'Bottom',
		'baseline' => 'Baseline',
	);
	var $parts = array(
		'' => 'Body',
		'header' => 'Header',
		'footer' => 'Footer',
	);
	var $edit_menu = array(
		'delete' => 'delete',
		'cut' => 'cut',
		'copy' => 'copy',
		'paste' => 'paste',
		'swap' => 'swap',
	);
	var $row_menu = array(
		'row_delete' => 'delete this row',
		'row_insert_above' => 'insert a row above',
		'row_insert_below' => 'insert a row below',
		'row_swap_next' => 'swap with next row',
	);
	var $column_menu = array(
		'column_delete' => 'delete this column',
		'column_insert_before' => 'insert a column before',
		'column_insert_behind' => 'insert a column behind',
		'column_swap_next' => 'swap with next column',
	);
	var $box_menu = array(
		'box_insert_before' => 'insert a widget before',
		'box_insert_behind' => 'insert a widget behind',
		'box_swap_next' => 'swap widget with next one',
	);
	var $options = array(
		'width',
		'height',
		'border',
		'class',
		'spacing',
		'padding',
		'overflow'
	);
	var $overflows = array(
		'' => 'visible',
		'hidden' => 'hidden',
		'scroll' => 'scroll',
		'auto' => 'auto'
	);
	var $onclick_types = array(
		''        => 'nothing',
		'confirm' => 'confirm',
		'popup'   => 'popup',
		'custom'  => 'custom',
	);
	var $onchange_types = array(
		''        => 'nothing',
		'submit'  => 'submit form',
		'custom'  => 'custom',
	);
	var $extensions = '';

	var $public_functions = array
	(
		'edit'         => True,
		'widget'       => True,
		'styles'       => True,
	);

	function __construct()
	{
		$this->etemplate = new etemplate();

		$this->extensions = $GLOBALS['egw']->session->appsession('extensions','etemplate');
	}

	function export_xml(&$xml,&$xml_label)
	{
		$name = $this->etemplate->name;
		$template = $this->etemplate->template != '' ? $this->etemplate->template : 'default';

		list($app) = explode('.',$name);

		if (!is_object($this->etemplate->xul_io))
		{
			$this->etemplate->xul_io =& CreateObject('etemplate.xul_io');
		}
		$xml = $this->etemplate->xul_io->export($this->etemplate);

		$dir = EGW_SERVER_ROOT . "/$app/templates/$template";
		if (($create_it = !is_dir($dir)))
		{
			$dir = EGW_SERVER_ROOT . "/$app/templates";
		}
		if (!is_writeable($dir))
		{
			// if dir is not writable, download file
			html::content_header($name.'.xet','application/xml',bytes($xml));
			echo $xml;
			common::egw_exit();
			//return lang("Error: webserver is not allowed to write into '%1' !!!",$dir);
		}
		if ($create_it)
		{
			mkdir($dir .= "/$template");
		}
		$file = $dir . '/' . substr($name,strlen($app)+1);
		if ($this->etemplate->lang)
		{
			$file .= '.' . $this->etemplate->lang;
		}
		$old_file = $file . '.old.xet';
		$file .= '.xet';
		if (file_exists($file))
		{
			if (file_exists($old_file))
			{
				unlink($old_file);
			}
			rename($file,$old_file);
		}

		if (!($f = fopen($xml_label=$file,'w')))
		{
			return 0;
		}
		if (!is_object($this->etemplate->xul_io))
		{
			$this->etemplate->xul_io =& CreateObject('etemplate.xul_io');
		}
		$xml = $this->etemplate->xul_io->export($this->etemplate);

		fwrite($f,$xml);
		fclose($f);

		return lang("eTemplate '%1' written to '%2'",$name,$file);
	}

	function import_xml($file,&$xml)
	{
		if ($file == 'none' || $file == '' || !($f = fopen($file,'r')))
		{
			return lang('no filename given or selected via Browse...')."file='$file'";
		}
		$xml = fread ($f, filesize ($file));
		fclose($f);

		if (!is_object($this->etemplate->xul_io))
		{
			$this->etemplate->xul_io =& CreateObject('etemplate.xul_io');
		}
		$imported = $this->etemplate->xul_io->import($this->etemplate,$xml);
		$this->etemplate->modified = @filemtime($f);
		$this->etemplate->modified_set = 'xul-import';

		if (is_array($imported))
		{
			if (count($imported) == 1)
			{
				$imported = lang("eTemplate '%1' imported, use Save to put it in the database",$this->etemplate->name);
			}
			else
			{
				$imported = lang('File contains more than one eTemplate, last one is shown !!!');
			}
		}
		return $imported;
	}

	function list_result($cont='',$msg='')
	{
		if ($this->debug)
		{
			echo "<p>etemplate.editor.list_result: cont="; _debug_array($cont);
		}
		if (!$cont || !is_array($cont))
		{
			return $this->edit('error');
		}
		if (!isset($cont['result']) || isset($cont['search']))
		{
			$cont['result'] = $this->etemplate->search($cont);
		}
		$result = $cont['result'];

		if (isset($cont['delete']))
		{
			list($delete) = each($cont['delete']);
			$this->etemplate->init($result[$delete-1]);
			if ($this->etemplate->delete())
			{
				$msg = lang('Template deleted');
				unset($result[$delete-1]);
				$result = array_values($result);
			}
			else
			{
				$msg = lang('Error: Template not found !!!');
			}
		}
		if (isset($cont['delete_selected']))
		{
			foreach($cont['selected'] as $row => $sel)
			{
				if ($sel)
				{
					$this->etemplate->init($result[$row-1]);
					if ($this->etemplate->delete())
					{
						unset($result[$row-1]);
						++$n;
					}
				}
			}
			if ($n)
			{
				$msg = lang('%1 eTemplates deleted',$n);
			}
			unset($cont['selected']);
			unset($cont['delete_selected']);
			$result = array_values($result);
		}
		if (isset($cont['read']))
		{
			list($read) = each($cont['read']);
			$this->etemplate->read($result[$read-1]);
			$this->edit();
			return;
		}
		if (!$msg)
		{
			$msg = lang('%1 eTemplates found',count($result));
		}
		unset($cont['result']);
		if (!isset($cont['name']))
		{
			$cont += $this->etemplate->as_array();
		}
		$content = $cont + array('msg' => $msg);

		reset($result);
		for ($row=1; list(,$param) = each($result); ++$row)
		{
			$content[$row] = $param;
		}
		$list_result = new etemplate('etemplate.editor.list_result');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Editable Templates - Search');
		$list_result->exec('etemplate.editor.list_result',$content,'','',array(
			'result' => $result,
		),'');
	}

	/**
	 * new eTemplate editor, which edits widgets in a popup
	 *
	 * @param array $content content from the process_exec call
	 * @param string $msg message to show
	 */
	function edit($content=null,$msg = '')
	{
		if ($this->debug)
		{
			echo "<p>etemplate.editor.edit: content="; _debug_array($content);
		}
		if (!is_array($content)) $content = array();
		$preserv = array();

		if ($content['import_xml'])
		{
			$msg .= $this->import_xml($content['file']['tmp_name'],$xml);
			//$this->etemplate->echo_tmpl();
			$xml_label = $content['file']['name'];
			$preserv['import'] = $this->etemplate->as_array(1);
		}
		elseif (is_array($content['import']) && !$content['read'])	// imported not yet saved tmpl
		{
			$this->etemplate->init($content['import']);
			$preserv['import'] = $content['import'];
		}
		if ($content['save'])
		{
			if (!is_array($content['import'])) $this->etemplate->read($content['old_keys']);

			if (!$this->etemplate->modified_set || !$this->etemplate->modified)
			{
				$this->etemplate->modified = time();
			}
			$ok = $this->etemplate->save(trim($content['name']),trim($content['template']),trim($content['lang']),(int) $content['group'],trim($content['version']));
			$msg = $ok ? lang('Template saved') : lang('Error: while saving !!!');
			if ($ok) unset($preserv['import']);
		}
		elseif (!$content['import_xml'] && (isset($_GET['name']) || isset($content['name'])))
		{
			if ($_GET['name'])
			{
				foreach(etemplate::$db_key_cols as $var)
				{
					$content[$var] = $_GET[$var];
				}
			}
			if ($content['version'] != '')
			{
				$save_version = $content['version'];
				unset($content['version']);
				$this->etemplate->read($content);
				$newest_version = $this->etemplate->version;
				$content['version'] = $save_version;
			}
			if (!$this->etemplate->read($content))
			{
				if (isset($content['name']))
				{
					$version_backup = $content['version'];
					$content['version'] = '';	// trying it without version
					if ($this->etemplate->read($content))
					{
						$msg = lang('only an other Version found !!!');
					}
					else
					{
						$result = $this->etemplate->search($content);
						if (count($result) > 1)
						{
							return $this->list_result(array('result' => $result));
						}
						elseif (!count($result) || !$this->etemplate->read($result[0]))
						{
							$msg = lang('Error: Template not found !!!');
							$this->etemplate->version = $content['version'] = $version_backup;
						}
						elseif ($content['name'] == $result[0]['name'])
						{
							$msg = lang('only an other Version found !!!');
						}
					}
				}
				else
				{
					$msg = lang('Error: Template not found !!!');
				}
			}
			elseif ($newest_version != '' && $this->etemplate->version != $newest_version)
			{
				$link = $this->etemplate->as_array(-1);
				$link['menuaction'] = 'etemplate.editor.edit';
				$link['version'] = $newest_version;
				$msg = lang("newer version '%1' exists !!!",html::a_href($newest_version,$link));
			}
		}
		if (!is_array($this->extensions))
		{
			if (($extensions = $this->scan_for_extensions()))
			{
				$msg .= lang('Extensions loaded:') . ' ' . $extensions;
				$msg_ext_loaded = True;
			}
		}
		list($app) = explode('.',$this->etemplate->name);
		if ($app && $app != 'etemplate')
		{
			$GLOBALS['egw']->translation->add_app($app);	// load translations for app

			if (($extensions = $this->scan_for_extensions($app)))
			{
				$msg .= (!$msg_ext_loaded?lang('Extensions loaded:').' ':', ') . $extensions;
			}
		}
		if (!$msg && $content['delete'])
		{
			if (!$content['version'] && $this->etemplate->version)
			{
				$this->etemplate->version = '';	// else the newest would get deleted and not the one without version
			}
			$ok = $this->etemplate->delete();
			$msg = $ok ? lang('Template deleted') : lang('Error: Template not found !!!');
			$preserv['import'] = $this->etemplate->as_array(1);	// that way the content can be saved again
		}
		elseif ($content['dump'])
		{
			if (empty($app) || !@is_dir(EGW_SERVER_ROOT.'/'.$app))
			{
				$msg .= lang('Application name needed to write a langfile or dump the eTemplates !!!');
			}
			else
			{
				$msg .= $this->etemplate->dump4setup($app);
			}
		}
		elseif ($content['langfile'])
		{
			if (empty($app) || !@is_dir(EGW_SERVER_ROOT.'/'.$app))
			{
				$msg = lang('Application name needed to write a langfile or dump the eTemplates !!!');
			}
			else
			{
				$additional = array();
				if ($app == 'etemplate')
				{
					$additional = etemplate::$types + $this->extensions + $this->aligns + $this->valigns +
						$this->edit_menu + $this->box_menu + $this->row_menu + $this->column_menu + $this->onclick_types + $this->onchange_types;
				}
				else	// try to call the writeLangFile function of the app's ui-layer
				{
					foreach(array('ui'.$name,'ui',$name,'bo'.$name) as $class)
					{
						if (file_exists(EGW_INCLUDE_ROOT.'/'.$name.'/inc/class.'.$class.'.inc.php') &&
							($ui =& CreateObject($name.'.'.$class)) && is_object($ui))
						{
							break;
						}
					}
					if (is_object($ui) && @$ui->public_functions['writeLangFile'])
					{
						$msg = "$class::writeLangFile: ".$ui->writeLangFile();
					}
					unset($ui);
				}
				//if (empty($msg))
				{
					$msg = $this->etemplate->writeLangFile($app,'en',$additional);
				}
			}
		}
		elseif ($content['export_xml'])
		{
			$msg .= $this->export_xml($xml,$xml_label);
		}
		$new_content = $this->etemplate->as_array() + array(
			'msg' => $msg,
			'xml_label' => $xml_label,
			'xml' => $xml ? '<pre>'.html::htmlspecialchars($xml)."</pre>\n" : '',
		);

		$editor = new etemplate('etemplate.editor.new');
		if (isset($content['values']) && !isset($content['vals']))
		{
			$r = 1;
			foreach((array)$content['cont'] as $key => $val)
			{
				$vals["@$r"] = $key;
				$vals["A$r"] = is_array($val) ? serialize($val).'#SeR#' : $val;
				++$r;
			}
			$editor->data[$editor->rows]['A']['name'] = 'etemplate.editor.values';
			$editor->data[$editor->rows]['A']['size'] = 'vals';
			$new_content['vals'] = $vals;
		}
		else
		{
			// set onclick handler
			$this->etemplate->onclick_handler = "edit_widget('%p');";
			// setting the javascript via the content, allows looping too
			$new_content['onclick'] = '
			<script language="javascript">
				function edit_widget(path)
				{
					var url = "'.$GLOBALS['egw']->link('/index.php',$this->etemplate->as_array(-1)+array(
						'menuaction' => 'etemplate.editor.widget',
					)).'";
					url = url.replace(/index.php\\?/,"index.php?path="+path+"&");
					window.open(url,"etemplate_editor_widget","dependent=yes,width=640,height=480,location=no,menubar=no,toolbar=no,scrollbars=yes,status=yes");
				}
			</script>';
			if ($app != 'etemplate' && file_exists(EGW_SERVER_ROOT.'/'.$app.'/templates/default/app.css'))
			{
				$new_content['onclick'] .= html::style('@import url('.$GLOBALS['egw_info']['server']['webserver_url'].'/'.$app.'/templates/default/app.css);');
			}
			// check if application of template has a app.js file --> load it
			if (file_exists(EGW_SERVER_ROOT.'/'.$app.'/js/app.js'))
			{
				$GLOBALS['egw']->js->validate_file('.','app',$app,false);
			}
			$editor->data[$editor->rows]['A']['obj'] = &$this->etemplate;
			$vals = $content['vals'];
			$olds = $content['olds'];

			for ($r = 1; isset($vals["A$r"]); ++$r)
			{
				$new_content['cont'][$olds["@$r"]] = substr($vals["A$r"],-5)=='#SeR#' ?
					unserialize(substr($vals["A$r"],0,-5)) : $vals["A$r"];
			}
		}
		// check if bo class of $app exists and has a labels method --> add labels to content to display them in the editor
		if(class_exists($app.'_bo') && method_exists($app.'_bo','labels'))
		{
			$new_content['labels'] = ExecMethod($acm=$app.'.'.$app.'_bo.labels');
			$new_content['cont']['labels'] =& $new_content['labels'];
			//echo $acm; _debug_array($new_content['labels']);
		}
		$preserv['olds'] = $vals;
		$preserv['old_keys'] = $this->etemplate->as_array(-1);	// in case we do a save as

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Editable Templates - Show Template');
		$editor->exec('etemplate.editor.edit',$new_content,array(),'',$preserv,0,'/^cont/');
	}

	/**
	 * initialises the children arrays for the new widget type, converts boxes <--> grids
	 *
	 * @internal
	 * @param array &$widget reference to the new widget data
	 * @param array $old the old widget data
	 */
	function change_widget_type(&$widget,$old)
	{
		//echo "<p>editor::change_widget_type($widget[type]=$old[type])</p>\n";
		$old_type = $old['type'];
		$old_had_children = isset(etemplate::$widgets_with_children[$old_type]);

		if (!isset(etemplate::$widgets_with_children[$widget['type']]) ||
			($old_type == 'grid') == ($widget['type'] == 'grid'))
		{
			if (etemplate::$widgets_with_children[$widget['type']] == 'box')	// box
			{
				if ((int) $widget['size'] < 1)	// min. 1 child
				{
					list(,$options) = explode(',',$widget['size'],2);
					$widget['size'] = '1'.($options ? ','.$options : '');
				}
				// create the needed cells, if they dont exist
				for ($n = 1; $n <= (int) $widget['size']; ++$n)
				{
					if (!is_array($widget[$n])) $widget[$n] = $n == 1 ? $old : etemplate::empty_cell();
				}
				unset($widget['onclick']);	// not valid for a box
			}
			return; // no change necessary, eg. between different box-types
		}
		switch (etemplate::$widgets_with_children[$widget['type']])
		{
			case 'grid':
				$widget['data'] = array(array());
				$widget['cols'] = $widget['rows'] = 0;

				if ($old_had_children)	// box --> grid: hbox --> 1 row, other boxes --> 1 column
				{
					list($num) = explode(',',$old['size']);
					for ($n = 1; is_array($old[$n]) && $n <= $num; ++$n)
					{
						$new_line = null;
						if ($old_type != 'hbox') etemplate::add_child($widget,$new_line);
						etemplate::add_child($widget,$old[$n]);
						unset($widget[$n]);
					}
					$widget['size'] = '';
				}
				else	// 1 row with 1 column/child
				{
					etemplate::add_child($widget,$cell=etemplate::empty_cell());
				}
				break;

			case 'box':
				$widget['size'] = 0;

				if ($old_type == 'grid')
				{
					if (preg_match('/,(vertical|horizontal)/',$widget['size'],$matches))
					{
						$orient = $matches[1];
					}
					else
					{
						$orient = $widget['type'] == 'hbox' ? 'horizontal' : 'vertical';
					}
					if ($orient == 'horizontal')	// ==> use first row
					{
						$row =& $old['data'][1];
						for ($n = 0; $n < $old['cols']; ++$n)
						{
							$cell =& $row[etemplate::num2chrs($n)];
							etemplate::add_child($widget,$cell);
							list($span) = (int)explode(',',$cell['span']);
							if ($span == 'all') break;
							while ($span-- > 1) ++$n;
						}
					}
					else	// vertical ==> use 1 column
					{
						for ($n = 1; $n <= $old['rows']; ++$n)
						{
							etemplate::add_child($widget,$old['data'][$n][etemplate::num2chrs(0)]);
						}
					}
				}
				if (!$widget['size']) // minimum one child
				{
					etemplate::add_child($widget,etemplate::empty_cell());
				}
				break;
		}
		//_debug_array($widget);
	}

	/**
	 * returns array with path => type pairs for each parent of $path
	 *
	 * @param string $path path to the widget not the parent!
	 * @return array
	 */
	function path_components($path)
	{
		$path_parts = explode('/',$path);
		array_pop($path_parts);		// removed the widget itself
		array_shift($path_parts);	// removed the leading empty string

		$components = array();
		$part_path = '';
		foreach($path_parts as $part)
		{
			$part_path .= '/'.$part;
			$parent =& $this->etemplate->get_widget_by_path($part_path);
			$components[$part_path] = $parent['type'];
		}
		return $components;
	}

	/**
	 * returns array with path => type pairs for each parent of $path
	 *
	 * @param array $parent the parent
	 * @param string $child_id id of child
	 * @param string $parent_path path of the parent
	 * @return array with keys left, right, up and down and their pathes set (if applicable)
	 */
	function parent_navigation($parent,$parent_path,$child_id,$widget)
	{
		if ($parent['type'] == 'grid' && preg_match('/^([0-9]+)([A-Z]+)$/',$child_id,$matches))
		{
			list(,$r,$c) = $matches;
			// find the column-number (base 0) for $c (A, B, C, ...)
			for($col = 0; etemplate::num2chrs($col) != $c && $col < 100; ++$col) ;

			if ($col > 0) $left = $parent_path.'/'.$r.etemplate::num2chrs($col-1);

			if ($col < $parent['cols']-1) $right = $parent_path.'/'.$r.etemplate::num2chrs($col+1);

			if ($r > 1) $up = $parent_path.'/'.($r-1).$c;

			if ($r < $parent['rows']) $down = $parent_path.'/'.($r+1).$c;
		}
		elseif ($parent['type']) // any box
		{
			if ($child_id > 1) $previous = $parent_path.'/'.($child_id-1);

			if ($child_id < (int) $parent['size'])  $next = $parent_path.'/'.($child_id+1);
		}
		else // template
		{
			if ($child_id > 0) $previous = '/'.($child_id-1);

			if ($child_id < count($this->etemplate->children)-1)  $next = '/'.($child_id+1);
		}
		if ($widget['type'] == 'grid')
		{
			$in = $parent_path.'/'.$child_id.'/1A';
		}
		elseif (isset(etemplate::$widgets_with_children[$widget['type']]) && $widget['type'] != 'template')
		{
			if ($widget['type'])	// box
			{
				$in = $parent_path.'/'.$child_id.'/1';
			}
			else
			{
				$in = '/0';
			}
		}
		$navi = array();
		foreach(array('left'=>'&larr;','up'=>'&nbsp;&uarr;&nbsp;','down'=>'&nbsp;&darr;&nbsp;',
			'right'=>'&rarr;','previous'=>'&larr;&uarr;','next'=>'&darr;&rarr;','in'=>'&times;') as $var => $dir)
		{
			if ($$var) $navi[$$var] = $dir;
		}
		return $navi;
	}

	/**
	 * functions of the edit-menu: paste, swap, cut, delete, copy
	 *
	 * @internal
	 * @param string &$action row_delete, row_insert_above, row_insert_below, row_swap, row_prefs
	 * @param array &$parent referece to the parent
	 * @param array &$content reference to the content-array
	 * @param string $child_id id of a cell
	 * @return string msg to display
	 */
	function edit_actions(&$action,&$parent,&$content,$child_id)
	{
		switch ($action)
		{
			case 'paste':
			case 'swap':
				$clipboard = $GLOBALS['egw']->session->appsession('clipboard','etemplate');
				if (!is_array($clipboard))
				{
					return lang('nothing in clipboard to paste !!!');
				}
				if ($action == 'swap')
				{
					$GLOBALS['egw']->session->appsession('clipboard','etemplate',$content['cell']);
				}
				$content['cell'] = $clipboard;
				break;

			case 'copy':
			case 'cut':
				$GLOBALS['egw']->session->appsession('clipboard','etemplate',$content['cell']);
				if ($action != 'cut')
				{
					return lang('widget copied into clipboard');
				}
				// fall-through
			case 'delete':
				if ($parent['type'] != 'grid')
				{
					// delete widget from parent
					if ($parent['type'])	// box
					{
						list($num,$options) = explode(',',$parent['size'],2);
						if ($num <= 1)	// cant delete last child --> only empty it
						{
							$parent[$num=1] = etemplate::empty_cell();
						}
						else
						{
							for($n = $child_id; $n < $num; ++$n)
							{
								$parent[$n] = $parent[1+$n];
							}
							unset($parent[$num--]);
						}
						$parent['size'] = $num . ($options ? ','.$options : '');
					}
					else	// template itself
					{
						if (count($this->etemplate->children) <= 1)	// cant delete last child
						{
							$this->etemplate->children[0] = etemplate::empty_cell();
						}
						else
						{
							unset($parent[$child_id]);
							$this->etemplate->children = array_values($this->etemplate->children);
						}
					}
					$action = 'save-no-merge';
				}
				else
				{
					$content['cell'] = etemplate::empty_cell();
					return lang('cant delete a single widget from a grid !!!');
				}
				break;
		}
		return '';
	}

	/**
	 * functions of the box-menu: insert-before, -behind und swap
	 *
	 * @internal
	 * @param string &$action row_delete, row_insert_above, row_insert_below, row_swap, row_prefs
	 * @param array &$parent referece to the parent
	 * @param array &$content reference to the content-array
	 * @param string &$child_id id of a cell, may change to the next cell if inserting behind
	 * @param string $parent_path path of parent
	 * @return string msg to display
	 */
	function box_actions(&$action,&$parent,&$content,&$child_id,$parent_path)
	{
		switch ($action)
		{
			case 'box_insert_before':
			case 'box_insert_behind':
				$n = $child_id + (int)($action == 'box_insert_behind');
				if (!$parent['type'])	// template
				{
					$num = count($parent)-1;	// 0..count()-1
				}
				else // boxes
				{
					list($num,$options) = explode(',',$parent['size'],2);
				}
				for($i = $num; $i >= $n; --$i)
				{
					$parent[1+$i] = $parent[$i];
				}
				$parent[$n] = $content['cell'] = etemplate::empty_cell();
				$child_id = $n;
				if ($parent['type']) $parent['size'] = (1+$num) . ($options ? ','.$options : '');
				break;

			case 'box_swap_next':
				if (!$parent['type'])	// template
				{
					$num = count($parent)-1;	// 0..count()-1
				}
				else // boxes
				{
					list($num) = explode(',',$parent['size'],2);
				}
				if ($child_id == $num)	// if on last cell, swap with the one before
				{
					$this->swap($parent[$child_id],$parent[$child_id-1]);
					--$child_id;
				}
				else
				{
					$this->swap($parent[$child_id],$parent[$child_id+1]);
					++$child_id;
				}
				break;
		}
		$action = 'apply-no-merge';

		return '';
	}

	/**
	 * functions of the row-menu: insert, deleting & swaping of rows
	 *
	 * @internal
	 * @param string &$action row_delete, row_insert_above, row_insert_below, row_swap_next, row_prefs
	 * @param array &$grid grid
	 * @param string $child_id id of a cell
	 * @return string msg to display
	 */
	function row_actions(&$action,&$grid,$child_id)
	{
		$data =& $grid['data'];
		$rows =& $grid['rows'];
		$cols =& $grid['cols'];
		$opts =& $data[0];

		if (preg_match('/^([0-9]+)([A-Z]+)$/',$child_id,$matches)) list(,$r,$c) = $matches;

		if (!$c || !$r || $r > $rows) return "wrong child_id='$child_id' => r='$r', c='$c'";

		switch($action)
		{
			case 'row_swap_next':
				if ($r > $rows-1)
				{
					if ($r != $rows) return lang('no row to swap with !!!');
					--$r;	// in last row swap with row above
				}
				$this->swap($data[$r],$data[1+$r]);
				$this->swap($opts['c'.$r],$opts['c'.(1+$r)]);
				$this->swap($opts['h'.$r],$opts['h'.(1+$r)]);
				break;

			case 'row_delete':
				if ($rows <= 1)	// one row only => delete whole grid
				{
					return lang('cant delete the only row in a grid !!!');
					// todo: delete whole grid instead
				}
				for($i = $r; $i < $rows; ++$i)
				{
					$opts['c'.$i] = $opts['c'.(1+$i)];
					$opts['h'.$i] = $opts['h'.(1+$i)];
					$data[$i] = $data[1+$i];
				}
				unset($opts['c'.$rows]);
				unset($opts['h'.$rows]);
				unset($data[$rows--]);
				break;

			case 'row_insert_above':
				--$r;
				// fall-through
			case 'row_insert_below':
				//echo "row_insert_below($r) rows=$rows, cols=$cols"; _debug_array($grid);
				// move height + class options of rows
				for($i = $rows; $i > $r; --$i)
				{
					echo ($i+1)."=$i<br>\n";
					$data[1+$i] = $data[$i];
					$opts['c'.(1+$i)] = $opts['c'.$i];
					$opts['h'.(1+$i)] = $opts['h'.$i];
				}
				for($i = 0; $i < $cols; ++$i)
				{
					echo (1+$r).":$i=".etemplate::num2chrs($i)."=empty_cell()<br>\n";
					$data[1+$r][etemplate::num2chrs($i)] = etemplate::empty_cell();
				}
				$opts['c'.(1+$r)] = $opts['h'.(1+$r)] = '';
				++$rows;
				//_debug_array($grid); return '';
				break;
		}
		$action = 'save-no-merge';

		return '';
	}

	/**
	 * functions of the column-menu: insert, deleting & swaping of columns
	 *
	 * @internal
	 * @param string &$action column_delete, column_insert_before, column_insert_behind, column_swap_next, column_prefs
	 * @param array &$grid grid
	 * @param string $child_id id of a cell
	 * @return string msg to display
	 */
	function column_actions(&$action,&$grid,$child_id)
	{
		$data =& $grid['data'];
		$rows =& $grid['rows'];
		$cols =& $grid['cols'];
		$opts =& $data[0];

		if (preg_match('/^([0-9]+)([A-Z]+)$/',$child_id,$matches)) list(,$r,$c) = $matches;
		// find the column-number (base 0) for $c (A, B, C, ...)
		for($col = 0; etemplate::num2chrs($col) != $c && $col < 100; ++$col) ;

		if (!$c || !$r || $r > $rows || $col >= $cols) return "wrong child_id='$child_id' => r='$r', c='$c', col=$col";

		switch($action)
		{
			case 'column_swap_next':
				if ($col >= $cols-1)
				{
					if ($col != $cols-1) return lang('no column to swap with !!!');
					$c = etemplate::num2chrs(--$col); // in last column swap with the one before
				}
				$c_next = etemplate::num2chrs(1+$col);
				for($row = 1; $row <= $rows; ++$row)
				{
					$this->swap($data[$row][$c],$data[$row][$c_next]);
				}
				$this->swap($opts[$c],$opts[$c_next]);
				//_debug_array($grid); return '';
				break;

			case 'column_insert_behind':
				++$col;
			case 'column_insert_before':
				//echo "<p>column_insert_before: col=$col</p>\n";
				// $col is where the new column data goes
				for ($row = 1; $row <= $rows; ++$row)
				{
					for ($i = $cols; $i > $col; --$i)
					{
						$data[$row][etemplate::num2chrs($i)] = $data[$row][etemplate::num2chrs($i-1)];
					}
					$data[$row][etemplate::num2chrs($col)] = etemplate::empty_cell();
				}
				for ($i = $cols; $i > $col; --$i)
				{
					$opts[etemplate::num2chrs($i)] = $opts[etemplate::num2chrs($i-1)];
				}
				unset($opts[etemplate::num2chrs($col)]);
				++$cols;
				//_debug_array($grid); return '';
				break;

			case 'column_delete':
				if ($cols <= 1)
				{
					return lang('cant delete the only column of a grid !!!');
					// todo: delete whole grid instead
				}
				for ($row = 1; $row <= $rows; ++$row)
				{
					for ($i = $col; $i < $cols-1; ++$i)
					{
						$data[$row][etemplate::num2chrs($i)] = $data[$row][etemplate::num2chrs($i+1)];
					}
					unset($data[$row][etemplate::num2chrs($cols-1)]);
				}
				for ($i = $col; $i < $cols-1; ++$i)
				{
					$opts[etemplate::num2chrs($i)] = $opts[etemplate::num2chrs($i+1)];
				}
				unset($opts[etemplate::num2chrs(--$cols)]);
				break;
		}
		$action = 'save-no-merge';

		return '';
	}

	/**
	 * converts onclick selectbox and onclick text to one javascript call
	 *
	 * @param array &$widget reference into the widget-tree
	 * @param array &$cell_content cell array in content
	 * @param boolean $widget2content=true copy from widget to content or other direction
	 */
	function fix_set_onclick(&$widget,&$cell_content,$widget2content=true)
	{
		if ($widget2content)
		{
			if (preg_match('/^return confirm\(["\']{1}?(.*)["\']{1}\);?$/',$widget['onclick'],$matches))
			{
				$cell_content['onclick'] = $matches[1];
				$cell_content['onclick_type'] = 'confirm';
			}
			elseif (preg_match('/^window.open\(egw::link\(\'\/index.php\',\'([^\']+)\'\)(\+values2url\(.*\))?,\'([^\']+)\',\'dependent=yes,width=([0-9]+),height=([0-9]+),scrollbars=yes,status=yes\'\); return false;$/',$widget['onclick'],$matches))
			{
				$cell_content['onclick'] = $matches[1].($matches[2] ? str_replace('+values2url(this.form,','&values2url(',$matches[2]) : '');
				if ($matches[3] != '_blank')
				{
					$cell_content['onclick'] .= ','.$matches[3];
				}
				if ($matches[4] != '600')
				{
					$cell_content['onclick'] .= ($matches[3]=='_blank' ? ',':'').','.$matches[4];
				}
				if ($matches[5] != '450')
				{
					$cell_content['onclick'] .= ($matches[4]=='600' ? ','.($matches[3]=='_blank' ? ',':'') : '').
						','.$matches[5];
				}
				$cell_content['onclick_type'] = 'popup';
			}
			else
			{
				$cell_content['onclick_type'] = !$widget['onclick'] ? '' : 'custom';
			}
		}
		else	// content --> widget
		{
			if (preg_match('/^return confirm\(["\']{1}?(.*)["\']{1}\);?$/',$cell_content['onclick'],$matches) ||
				$cell_content['onclick_type'] == 'confirm' && $cell_content['onclick'])
			{
				$cell_content['onclick_type'] = 'confirm';
				$cell_content['onclick'] = is_array($matches) && $matches[1] ? $matches[1] : $cell_content['onclick'];
				$widget['onclick'] = "return confirm('".$cell_content['onclick']."');";
			}
			elseif ($cell_content['onclick_type'] == 'popup' && $cell_content['onclick'])
			{
				// eg: menuaction=calendar.uiforms.freetimesearch&values2url('start,end,participants'),ft_search,700,500
				if  (($values2url = preg_match('/&values2url\((\'[^\']+\')\)/',$cell_content['onclick'],$matches)))
				{
					$values2url = $matches[1];
					$onclick = str_replace('&values2url('.$values2url.')','',$cell_content['onclick']);
				}
				list($get,$target,$width,$height) = explode(',',$values2url ? $onclick : $cell_content['onclick']);
				if (!$target) $target = '_blank';
				if (!$width)  $width  = 600;
				if (!$height) $height = 450;
				$widget['onclick'] = "window.open(egw::link('/index.php','$get')".($values2url ? "+values2url(this.form,$values2url)" : '').
					",'$target','dependent=yes,width=$width,height=$height,scrollbars=yes,status=yes'); return false;";
			}
			elseif ($cell_content['onclick'])
			{
				$wiget['onclick'] = $cell_content['onclick'];
				$cell_content['onclick_type'] = 'custom';
			}
			else
			{
				$cell_content['onclick_type'] = '';
			}
			unset($widget['onclick_type']);
		}
		//echo "<p>editor::fix_set_onclick(,,widget2content=".(int)$widget2content.") widget="; _debug_array($widget); echo "content="; _debug_array($cell_content);
	}

	/**
	 * converts onchange selectbox and onchange text to one javascript call
	 *
	 * @param array &$widget reference into the widget-tree
	 * @param array &$cell_content cell array in content
	 * @param boolean $widget2content=true copy from widget to content or other direction
	 */
	function fix_set_onchange(&$widget,&$cell_content,$widget2content=true)
	{
		if ($widget2content)
		{
			if (!$widget['onchange'])
			{
				$cell_content['onchange_type'] = $cell_content['onchange'] = '';
			}
			elseif ($widget['onchange'] == 1 || $widget['onchange'] == 'this.form.submit();')
			{
				$cell_content['onchange'] = '';
				$cell_content['onchange_type'] = 'submit';
			}
			else
			{
				$cell_content['onchange_type'] = 'custom';
			}
		}
		else	// content --> widget
		{
			if ($cell_content['onchange_type'] == 'submit' || $cell_content['onchange'] == 'this.form.submit();')
			{
				$widget['onchange'] = 1;
			}
			elseif(!$cell_content['onchange'])
			{
				$widget['onchange'] = 0;
			}
			unset($widget['onchange_type']);
		}
		//echo "<p>editor::fix_set_onchange(,,widget2content=".(int)$widget2content.") widget="; _debug_array($widget); echo "content="; _debug_array($cell_content);
	}

	/**
	 * edit dialog for a widget
	 *
	 * @param array $content the submitted content of the etemplate::exec function, default null
	 * @param string $msg msg to display, default ''
	 */
	function widget($content=null,$msg='')
	{
		if (is_array($content))
		{
			$path = $content['goto'] ? $content['goto'] : ($content['goto2'] ? $content['goto2'] : $content['path']);
			$Ok = $this->etemplate->read($content['name'],$content['template'],$content['lang'],0,$content['goto'] || $content['goto2'] ? $content['version'] : $content['old_version']);

			// build size from options array, if applicable
			if (is_array($content['cell']['options']))
			{
				$size = '';
				for ($n = max(array_keys($content['cell']['options'])); $n >= 0; --$n)
				{
					if (strlen($content['cell']['options'][$n]) || strlen($size))
					{
						$size = $content['cell']['options'][$n].(strlen($size) ? ','.$size : '');
					}
				}
				$content['cell']['size'] = $size;
			}
		}
		else
		{
			//echo "<p><b>".($_GET['path']).":</b></p>\n";
			list($name,$version,$path) = explode(':',$_GET['path'],3);	// <name>:<version>:<path>
			$Ok = $this->etemplate->read(array(
				'name'    => $name,
				'version' => $version,
			));
		}
		if (!$Ok && !$content['cancel'])
		{
			$msg .= lang('Error: Template not found !!!');
		}
		$path_parts = explode('/',$path);
		$child_id = array_pop($path_parts);
		$parent_path = implode('/',$path_parts);
		//echo "<p>path='$path': child_id='$child_id', parent_path='$parent_path'</p>\n";
		$parent =& $this->etemplate->get_widget_by_path($parent_path);

		if (is_array($content))
		{
			foreach(array('save','apply','cancel','goto','goto2','edit_menu','box_menu','row_menu','column_menu') as $n => $name)
			{
				if (($action = $content[$name] ? ($n < 5 ? $name : $content[$name]) : false)) break;
				$name = '';
			}
			unset($content[$name]);

			//echo "<p>name='$name', parent-type='$parent[type]', action='$action'</p>\n";
			if (($name == 'row_menu' || $name == 'column_menu') && $parent['type'] != 'grid' ||
				$name == 'box_menu' && $parent['type'] == 'grid')
			{
				$msg .= lang("parent is a '%1' !!!",lang($parent['type'] ? $parent['type'] : 'template'));
				$action = false;
			}
			switch($name)
			{
				case 'edit_menu':
					$msg .= $this->edit_actions($action,$parent,$content,$child_id);
					break;

				case 'box_menu':
					$msg .= $this->box_actions($action,$parent,$content,$child_id,$parent_path);
					break;

				case 'row_menu':
					$msg .= $this->row_actions($action,$parent,$child_id);
					break;

				case 'column_menu':
					$msg .= $this->column_actions($action,$parent,$child_id);
					break;

				case '':	// reload, eg. by changing the type
					$widget = $content['cell'];
					break;

				default:
					// all menu's are (only) working on the parent, referencing widget is unnecessary
					// and gives unexpected results, if parent is changed (eg. content gets copied)
					$widget =& $this->etemplate->get_widget_by_path($path);
					break;
			}
			switch ($action)
			{
				case 'goto':
				case 'goto2':
					$content['cell'] = $widget;
					$this->fix_set_onclick($widget,$content['cell'],true);
					$this->fix_set_onchange($widget,$content['cell'],true);
					break;

				case '':
				case 'save': case 'apply':
					// initialise the children arrays if type is changed to a widget with children
					//echo "<p>$content[path]: $widget[type] --> ".$content['cell']['type']."</p>\n";
					if (isset(etemplate::$widgets_with_children[$content['cell']['type']]))
					{
						$this->change_widget_type($content['cell'],$widget);
					}
					if (!$action) break;
					// save+apply only
					$widget = $content['cell'];
					if ($content['cell']['onclick_type'] || $content['cell']['onclick'])
					{
						$this->fix_set_onclick($widget,$content['cell'],false);
					}
					if ($content['cell']['onchange_type'] || $content['cell']['onchange'])
					{
						$this->fix_set_onchange($widget,$content['cell'],false);
					}
					// row- and column-attr for a grid
					if ($parent['type'] == 'grid' && preg_match('/^([0-9]+)([A-Z]+)$/',$child_id,$matches))
					{
						list(,$row,$col) = $matches;
						$parent['data'][0]['h'.$row] = $content['grid_row']['height'].
							($content['grid_row']['disabled']||$content['grid_row']['part']?','.$content['grid_row']['disabled']:'').
							($content['grid_row']['part']?','.$content['grid_row']['part']:'');
						$parent['data'][0]['c'.$row] = $content['grid_row']['class'].
							($content['grid_row']['valign']?','.$content['grid_row']['valign']:'');
						$parent['data'][0][$col] = $content['grid_column']['width'].
							($content['grid_column']['disabled']?','.$content['grid_column']['disabled']:'');
					}
					// fall-through
				case 'save-no-merge':
				case 'apply-no-merge':
					//$this->etemplate->echo_tmpl();
					$ok = $this->etemplate->save($content);
					$msg .= $ok ? lang('Template saved') : lang('Error: while saving !!!');

					// if necessary fix the version of our opener
					if ($content['opener']['name'] == $content['name'] &&
						$content['opener']['template'] == $content['template'] &&
						$content['opener']['group'] == $content['group'] &&
						$content['opener']['lang'] == $content['lang'])
					{
						$content['opener']['version'] = $content['version'];
					}
					$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
							'menuaction' => 'etemplate.editor.edit',
						)+$content['opener'])."';";
					if ($action == 'apply' || $action == 'apply-no-merge') break;
					// fall through
				case 'cancel':
					$js .= 'window.close();';
					echo "<html><body><script>$js</script></body></html>\n";
					$GLOBALS['egw']->common->egw_exit();
					break;
			}
			if ($js)
			{
				$content['java_script'] = "<script>$js</script>";
			}
		}
		else
		{
			$widget =& $this->etemplate->get_widget_by_path($path);

			$content = $this->etemplate->as_array(-1);
			$content['cell'] = $widget;
			$this->fix_set_onclick($widget,$content['cell'],true);
			$this->fix_set_onchange($widget,$content['cell'],true);

			foreach(etemplate::$db_key_cols as $var)
			{
				if (isset($_GET[$var]))
				{
					$content['opener'][$var] = $_GET[$var];
				}
			}
		}
		unset($content['cell']['obj']);	// just in case it contains a template-object

		if ($parent['type'] == 'grid' && preg_match('/^([0-9]+)([A-Z]+)$/',$child_id,$matches))
		{
			list(,$row,$col) = $matches;

			$grid_row =& $content['grid_row'];
			list($grid_row['height'],$grid_row['disabled'],$grid_row['part']) = explode(',',$parent['data'][0]['h'.$row]);
			list($grid_row['class'],$grid_row['valign']) = explode(',',$parent['data'][0]['c'.$row]);

			$grid_column =& $content['grid_column'];
			list($grid_column['width'],$grid_column['disabled']) = explode(',',$parent['data'][0][$col]);
			//echo "<p>grid_row($row)=".print_r($grid_row,true).", grid_column($col)=".print_r($grid_column,true)."</p>\n";

			list(,,$previous_part) = explode(',',$parent['data'][0]['h'.($row-1)]);
			list(,,$next_part) = explode(',',$parent['data'][0]['h'.($row+1)]);
			$allowed_parts = $this->get_allowed_parts($row,$previous_part,$next_part);
			//echo "<p>$row: previous=$previous_part, current={$grid_row['part']}, next=$next_part".(!isset($allowed_parts[$grid_row['part']])?': current part is NOT allowed!!!':'')."</p>\n"; _debug_array($allowed_parts);
		}
		else
		{
			unset($content['grid_row']);
			unset($content['grid_column']);
		}
		$content['path'] = ($parent_path!='/'?$parent_path:'').'/'.$child_id;
		$content['msg'] = $msg;
		$content['goto'] = $this->path_components($content['path']);
		$content['goto2'] = $this->parent_navigation($parent,$parent_path,$child_id,$widget);

		$content['cell']['options'] = explode(',',$content['cell']['size']);

		$editor = new etemplate('etemplate.editor.widget');
		$type_tmpl = new etemplate;

		list($ext_type) = explode('-',$widget['type']);
		// allow to read template of app-specific widgets from their app: eg. "infolog-value" --> "infolog.widget.infolog-value"
		if (isset($GLOBALS['egw_info']['apps'][$ext_type]) && $type_tmpl->read($ext_type.'.widget.'.$widget['type']) ||
			$type_tmpl->read('etemplate.editor.widget.'.$widget['type']) ||
			$type_tmpl->read('etemplate.editor.widget.'.$ext_type))
		{
			$editor->set_cell_attribute('etemplate.editor.widget.generic','obj',$type_tmpl);
		}
		if ($parent['type'] == 'grid')
		{
			$editor->disable_cells('box_menu');
		}
		else
		{
			$editor->disable_cells('row_menu');
			$editor->disable_cells('column_menu');
		}
		$preserv = $this->etemplate->as_array()+array(
			'path'        => $content['path'],
			'old_version' => $this->etemplate->version,
			'opener'      => $content['opener'],
			'cell'        => $content['cell'],
			'goto'        => $content['goto'],
		);
		unset($preserv['cell']['options']);	// otherwise we never know if content is returned via options array or size

		$GLOBALS['egw_info']['flags']['java_script'] = "<script>window.focus();</script>\n";
		$GLOBALS['egw_info']['flags']['app_header'] = lang('Editable Templates - Editor');
		$editor->exec('etemplate.editor.widget',$content,array(
				'type'       => array_merge(etemplate::$types,$this->extensions),
				'align'      => &$this->aligns,
				'valign'     => &$this->valigns,
				'part'       => $allowed_parts,
				'edit_menu'  => &$this->edit_menu,
				'box_menu'   => &$this->box_menu,
				'row_menu'   => &$this->row_menu,
				'column_menu'=> &$this->column_menu,
				'onclick_type'=>&$this->onclick_types,
				'onchange_type'=>&$this->onchange_types,
				'options[6]' => &$this->overflows,
			),'',$preserv,2);
	}

	/**
	 * Get the allowed tables-part for a table rows based on row-number, previous and next part
	 *
	 * @param int $row (1-based!)
	 * @param string $previous_part ''=body, 'h'=header, 'f'=footer
	 * @param string $next_part see above
	 * @return array
	 */
	private function get_allowed_parts($row,$previous_part,$next_part)
	{
		$allowed_parts = $this->parts;
		switch((string)$previous_part)
		{
			case 'footer':
				unset($allowed_parts['header']);
				break;
			case '':
				if ($row > 1) $allowed_parts = array('' => $allowed_parts['']);
				break;
		}
		switch($next_part)
		{
			case 'header':
				$allowed_parts = array('header' => $allowed_parts['header']);
				break;
			case 'footer':
				unset($allowed_parts['']);
				break;
		}
		return $allowed_parts;
	}

	/**
	 * edit dialog for the styles of a templat or app
	 *
	 * @param array $content the submitted content of the etemplate::exec function, default null
	 * @param string $msg msg to display, default ''
	 */
	function styles($content=null,$msg='')
	{
		if (!is_array($content))
		{
			foreach(etemplate::$db_key_cols as $var)
			{
				if (isset($_GET[$var])) $content[$var] = $_GET[$var];
			}
		}
		//_debug_array($content);
		// security check for content[from]
		if ($content['from'] && !preg_match('/^[A-Za-z0-9_-]+\/templates\/[A-Za-z0-9_-]+\/app.css$/',$content['from']))
		{
			$content['from'] = '';	// someone tried to trick us reading a file we are not suppost to read
		}
		if (!$this->etemplate->read($content))
		{
			$msg .= lang('Error: Template not found !!!');
		}
		if ($content['save'] || $content['apply'])
		{
			if ($content['from'])
			{
				$path = EGW_SERVER_ROOT.'/'.$content['from'];
				if (is_writable(dirname($path)) && file_exists($path))
				{
					rename($path,str_replace('.css','.old.css',$path));
				}
				if (file_exists($path) && !is_writable(dirname($path)))
				{
					$msg .= lang("Error: webserver is not allowed to write into '%1' !!!",dirname($path));
				}
				else
				{
					$fp = fopen($path,'w');
					if (!$fp || !fwrite($fp,$content['styles']))
					{
						$msg .= lang('Error: while saving !!!');
					}
					else
					{
						$msg .= lang("File writen",$path);
					}
					@fclose($fp);
				}
			}
			else	// the templates own embeded styles;
			{
				$this->etemplate->style = $content['styles'];
				$ok = $this->etemplate->save();
				$msg = $ok ? lang('Template saved') : lang('Error: while saving !!!');
			}
			$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'etemplate.editor.edit',
				)+$this->etemplate->as_array(-1))."';";
		}
		if ($content['save'] || $content['cancel'])
		{
			$js .= 'window.close();';
			echo "<html><body><script>$js</script></body></html>\n";
			$GLOBALS['egw']->common->egw_exit();
		}
		$content = array(
			'from' => $content['from'],
			'java_script' => $js ? '<script>'.$js.'</script>' : '',
			'msg' => $msg
		);
		$tmpl = new etemplate('etemplate.editor.styles');

		if ($content['from'])
		{
			$path = EGW_SERVER_ROOT.'/'.$content['from'];
			$content['styles'] = file_exists($path) && is_readable($path) ? implode('',file($path)) : '';
			if (!is_writable(dirname($path)) && (!file_exists($path) || !is_writable($path)))
			{
				$tmpl->set_cell_attribute('styles','readonly',true);
			}
		}
		else
		{
			$content['styles'] = $this->etemplate->style;
		}
		// generate list of style-sources
		$keys = $this->etemplate->as_array(-1); unset($keys['group']);
		$sources[''] = lang('eTemplate').': '.implode(':',$keys);
		list($app) = explode('.',$this->etemplate->name);
		$app_templates = @opendir(EGW_SERVER_ROOT.'/'.$app.'/templates');
		while (($template = @readdir($app_templates)) !== false)
		{
			$dir = EGW_SERVER_ROOT.'/'.$app.'/templates/'.$template;
			if ($template[0] == '.' || $template == 'CVS' || !is_dir($dir.'/images')) continue;	// not a template-dir
			$exists = file_exists($dir.'/app.css');
			$writable = is_writable($dir) || $exists && is_writable($dir.'/app.css');
			if (!$exists && !$writable) continue;	// nothing to show
			$rel_path = $app.'/templates/'.$template.'/app.css';
			$sources[$rel_path] = lang('file').': '.$rel_path.($exists && !$writable ? ' ('.lang('readonly').')' : '');
		}
		$GLOBALS['egw_info']['flags']['java_script'] = "<script>window.focus();</script>\n";
		$GLOBALS['egw_info']['flags']['app_header'] = lang('etemplate').' - '.lang('CSS-styles');
		$tmpl->exec('etemplate.editor.styles',$content,array('from'=>$sources),'',$keys,2);
	}

	/**
	 * search the inc-dirs of etemplate and the app whichs template is edited for extensions / custom widgets
	 *
	 * extensions are class-files in $app/inc/class.${name}_widget.inc.php
	 * the extensions found will be saved in a class-var and in the session
	 *
	 * @param string $app='etemplate' app to scan
	 * @return string comma delimited list of new found extensions
	 */
	function scan_for_extensions($app='etemplate')
	{
		if (!is_array($this->extensions)) $this->extensions = array();

		if (isset($this->extensions['**loaded**'][$app])) return '';	// already loaded

		$labels = array();
		$dir = @opendir(EGW_SERVER_ROOT.'/'.$app.'/inc');
		while ($dir && ($file = readdir($dir)))
		{
			if (preg_match('/class\\.([a-zA-Z0-9_]*)_widget.inc.php/',$file,$regs) &&
				($regs[1] != 'xslt' || $this->etemplate->xslt) &&
				($ext = $this->etemplate->loadExtension($regs[1].'.'.$app,$this->etemplate)))
			{
				if (is_array($ext))
				{
					$this->extensions += $ext;
					$labels += $ext;
				}
				else
				{
					$this->extensions[$regs[1]] = $ext;
					$labels[] = $ext;
				}
			}
		}
		// store the information in the session, our constructor loads it from there
		$GLOBALS['egw']->session->appsession('extensions','etemplate',$this->extensions);
		$apps_loaded = $GLOBALS['egw']->session->appsession('apps_loaded','etemplate');
		$apps_loaded[$app] = true;
		$GLOBALS['egw']->session->appsession('apps_loaded','etemplate',$apps_loaded);
		//_debug_array($this->extensions); _debug_array($apps_loaded);

		return implode(', ',$labels);
	}

	/**
	 * swap the values of $a and $b
	 *
	 * @param mixed &$a
	 * @param mixed &$b
	 */
	function swap(&$a,&$b)
	{
		$h = $a;
		$a = $b;
		$b = $h;
	}
}