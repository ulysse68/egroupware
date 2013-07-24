<?php
/**
 * eGroupWare  eTemplates - DB-Tools
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright 2002-9 by RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage tools
 * @version $Id: class.db_tools.inc.php 27222 2009-06-08 16:21:14Z ralfbecker $
 */

/**
 * db-tools: creats and modifys eGroupWare schem-files (to be installed via setup)
 */
class db_tools
{
	/**
	 * Methods callable via menuaction
	 *
	 * @var array
	 */
	public $public_functions = array(
		'edit'         => True,
		'needs_save'   => True,
	);

	/**
	 * Debug Level: 0 = off, > 0 more diagnostics
	 *
	 * @var int
	 */
	protected $debug = 0;

	/**
	 * Table definitions
	 *
	 * @var array
	 */
	protected $data = array();
	/**
	 * Used app
	 *
	 * @var string
	 */
	protected $app;
	/**
	 * Used table
	 *
	 * @var string
	 */
	protected $table;
	/**
	 * Available colum types
	 *
	 * @var array
	 */
	protected $types = array(
		'varchar'	=> 'varchar',
		'int'		=> 'int',
		'auto'		=> 'auto',
		'blob'		=> 'blob',
		'char'		=> 'char',
		'date'		=> 'date',
		'decimal'	=> 'decimal',
		'float'		=> 'float',
		'longtext'	=> 'longtext',
		'text'		=> 'text',
		'timestamp'	=> 'timestamp',
		'bool'      => 'boolean',
//		'abstime'   => 'abstime (mysql:timestamp)',
	);

	/**
	 * constructor of class
	 */
	function __construct()
	{
		if (!is_array($GLOBALS['egw_info']['apps']) || !count($GLOBALS['egw_info']['apps']))
		{
			ExecMethod('phpgwapi.applications.read_installed_apps');
		}
		$GLOBALS['egw_info']['flags']['app_header'] =
			$GLOBALS['egw_info']['apps']['etemplate']['title'].' - '.lang('DB-Tools');
	}

	/**
	 * table editor (and the callback/submit-method too)
	 *
	 * @param array $content=null
	 * @param string $msg=''
	 */
	function edit(array $content=null,$msg = '')
	{
		if (isset($_GET['app']))
		{
			$this->app = $_GET['app'];
		}
		if (is_array($content))
		{
			if ($this->debug)
			{
				echo "content ="; _debug_array($content);
			}
			$this->app = $content['app'];	// this is what the user selected
			$this->table = $content['table_name'];
			$posted_app = $content['posted_app'];	// this is the old selection
			$posted_table = $content['posted_table'];
		}
		if ($posted_app && $posted_table &&		// user changed app or table
			 ($posted_app != $this->app || $posted_table != $this->table))
		{
			if ($this->needs_save('',$posted_app,$posted_table,$this->content2table($content)))
			{
				return;
			}
			$this->renames = array();
		}
		if (!$this->app)
		{
			$this->table = '';
			$table_names = array('' => lang('none'));
		}
		else
		{
			$this->read($this->app,$this->data);

			foreach($this->data as $name => $table)
			{
				$table_names[$name] = $name;
			}
		}
		if (!$this->table || $this->app != $posted_app)
		{
			reset($this->data);
			list($this->table) = each($this->data);	// use first table
		}
		elseif ($this->app == $posted_app && $posted_table)
		{
			$this->data[$posted_table] = $this->content2table($content);
		}
		if ($content['write_tables'])
		{
			if ($this->needs_save('',$this->app,$this->table,$this->data[$posted_table]))
			{
				return;
			}
			$msg .= lang('Table unchanged, no write necessary !!!');
		}
		elseif ($content['delete'])
		{
			list($col) = each($content['delete']);
			@reset($this->data[$posted_table]['fd']);
			while ($col-- > 0 && list($key) = @each($this->data[$posted_table]['fd'])) ;
			unset($this->data[$posted_table]['fd'][$key]);
			$this->changes[$posted_table][$key] = '**deleted**';
		}
		elseif ($content['add_column'])
		{
			$this->data[$posted_table]['fd'][''] = array();
		}
		elseif ($content['add_table'] || $content['import'])
		{
			if (!$this->app)
			{
				$msg .= lang('Select an app first !!!');
			}
			elseif (!$content['new_table_name'])
			{
				$msg .= lang('Please enter table-name first !!!');
			}
			elseif ($content['add_table'])
			{
				$this->table = $content['new_table_name'];
				$this->data[$this->table] = array('fd' => array(),'pk' =>array(),'ix' => array(),'uc' => array(),'fk' => array());
				$msg .= lang('New table created');
			}
			else // import
			{
				$oProc =& CreateObject('phpgwapi.schema_proc',$GLOBALS['egw_info']['server']['db_type']);
				if (method_exists($oProc,'GetTableDefinition'))
				{
					$this->data[$this->table = $content['new_table_name']] = $oProc->GetTableDefinition($content['new_table_name']);
				}
				else	// to support eGW 1.0
				{
					$oProc->m_odb = clone($GLOBALS['egw']->db);
					$oProc->m_oTranslator->_GetColumns($oProc,$content['new_table_name'],$nul);

					while (list($key,$tbldata) = each ($oProc->m_oTranslator->sCol))
					{
						$cols .= $tbldata;
					}
					eval('$cols = array('. $cols . ');');

					$this->data[$this->table = $content['new_table_name']] = array(
						'fd' => $cols,
						'pk' => $oProc->m_oTranslator->pk,
						'fk' => $oProc->m_oTranslator->fk,
						'ix' => $oProc->m_oTranslator->ix,
						'uc' => $oProc->m_oTranslator->uc
					);
				}
			}
		}
		elseif ($content['editor'])
		{
			ExecMethod('etemplate.editor.edit');
			return;
		}
		$add_index = isset($content['add_index']);

		// from here on, filling new content for eTemplate
		$content = array(
			'msg' => $msg,
			'table_name' => $this->table,
			'app' => $this->app,
		);
		if (!isset($table_names[$this->table]))	// table is not jet written
		{
			$table_names[$this->table] = $this->table;
		}
		$sel_options = array(
			'table_name' => $table_names,
			'type' => $this->types
		);
		if ($this->table != '' && isset($this->data[$this->table]))
		{
			$content += $this->table2content($this->data[$this->table],$sel_options['Index'],$add_index);
		}
		$no_button = array( );
		if (!$this->app || !$this->table)
		{
			$no_button['write_tables'] = True;
		}
		if ($this->debug)
		{
			echo 'editor.edit: content ='; _debug_array($content);
		}
		$tpl = new etemplate('etemplate.db-tools.edit');
		$tpl->exec('etemplate.db_tools.edit',$content,$sel_options,$no_button,
			array('posted_table' => $this->table,'posted_app' => $this->app,'changes' => $this->changes));
	}

	/**
	 * checks if table was changed and if so offers user to save changes
	 *
	 * @param array $cont the content of the form (if called by process_exec)
	 * @param string $posted_app the app the table is from
	 * @param string $posted_table the table-name
	 * @param array $edited_table the edited table-definitions
	 * @return only if no changes
	 */
	function needs_save($cont='',$posted_app='',$posted_table='',$edited_table='',$msg='')
	{
		//echo "<p>db_tools::needs_save(cont,'$posted_app','$posted_table',edited_table,'$msg')</p> cont=\n"; _debug_array($cont); echo "edited_table="; _debug_array($edited_table);
		if (!$posted_app && is_array($cont))
		{
			if (isset($cont['yes']))
			{
				$this->app   = $cont['app'];
				$this->table = $cont['table'];
				$this->read($this->app,$this->data);
				$this->data[$this->table] = $cont['edited_table'];
				$this->changes = $cont['changes'];
				if ($cont['new_version'])
				{
					$this->update($this->app,$this->data,$cont['new_version']);
				}
				else
				{
					foreach($this->data as $tname => $tinfo)
					{
						$tables .= ($tables ? ',' : '') . "'$tname'";
					}
					$this->setup_version($this->app,'',$tables);
				}
				if (!$this->write($this->app,$this->data))
				{
					$this->app = $cont['new_app'];	// these are the ones, the users whiches to change too
					$this->table = $cont['new_table'];

					return $this->needs_save('',$cont['app'],$cont['table'],$cont['edited_table'],
						lang('Error: writing file (no write-permission for the webserver) !!!'));
				}
				$msg = lang('File writen');
			}
			$this->changes = array();
			// return to edit with everything set, so the user gets the table he asked for
			$this->edit(array(
				'app' => $cont['new_app'],
				'table_name' => $cont['app']==$cont['new_app'] ? $cont['new_table'] : '',
				'posted_app' => $cont['new_app']
			),$msg);

			return True;
		}
		$new_app   = $this->app;	// these are the ones, the users whiches to change too
		$new_table = $this->table;

		$this->app = $posted_app;
		$this->data = array();
		$this->read($posted_app,$this->data);

		if (isset($this->data[$posted_table]) &&
			 $this->tables_identical($this->data[$posted_table],$edited_table))
		{
			if ($new_app != $this->app)	// are we changeing the app, or hit the user just write
			{
				$this->app = $new_app;	// if we change init the data empty
				$this->data = array();
			}
			return False;	// continue edit
		}
		$content = array(
			'msg' => $msg,
			'app' => $posted_app,
			'table' => $posted_table,
			'version' => $this->setup_version($posted_app)
		);
		$preserv = $content + array(
			'new_app' => $new_app,
			'new_table' => $new_table,
			'edited_table' => $edited_table,
			'changes' => $this->changes
		);
		$new_version = explode('.',$content['version']);
		$minor = count($new_version)-1;
		$new_version[$minor] = sprintf('%03d',1+$new_version[$minor]);
		$content['new_version'] = implode('.',$new_version);

		$tmpl = new etemplate('etemplate.db-tools.ask_save');

		if (!file_exists(EGW_SERVER_ROOT."/$posted_app/setup/tables_current.inc.php"))
		{
			$tmpl->disable_cells('version');
			$tmpl->disable_cells('new_version');
		}
		$tmpl->exec('etemplate.db_tools.needs_save',$content,array(),array(),$preserv);

		return True;	// dont continue in edit
	}

	/**
	 * checks if there is an index (only) on $col (not a multiple index incl. $col)
	 *
	 * @param string $col column name
	 * @param array $index ix or uc array of table-defintion
	 * @param string &$options db specific options
	 * @return True if $col has a single index
	 */
	function has_single_index($col,$index,&$options)
	{
		foreach($index as $in)
		{
			if ($in == $col || is_array($in) && $in[0] == $col && !isset($in[1]))
			{
				if ($in != $col && isset($in['options']))
				{
					foreach($in['options'] as $db => $opts)
					{
						$options[] = $db.'('.(is_array($opts)?implode(',',$opts):$opts).')';
					}
					$options = implode(', ',$options);
				}
				return True;
			}
		}
		return False;
	}

	/**
	 * creates content-array from a table
	 *
	 * @param array $table table-definition, eg. $phpgw_baseline[$table_name]
	 * @param array &$columns returns array with column-names
	 * @param bool $extra_index add an additional index-row
	 * @return array content-array to call exec with
	 */
	function table2content($table,&$columns,$extra_index=False)
	{
		if ($this->debug >= 3)
		{
			echo __METHOD__."(\$table,,$extra_index) \$table ="; _debug_array($table);
		}
		$content = $columns = array();
		$n = 1;
		foreach($table['fd'] as $col_name => $col_defs)
		{
			$col_defs['name'] = $col_name;
			$col_defs['pk'] = in_array($col_name,$table['pk']);
			$col_defs['uc']  = $this->has_single_index($col_name,$table['uc'],$col_defs['options']);
			$col_defs['ix'] = $this->has_single_index($col_name,$table['ix'],$col_defs['options']);
			$col_defs['fk'] = $table['fk'][$col_name];
			if (isset($col_defs['default']) && $col_defs['default'] == '')
			{
				$col_defs['default'] = is_int($col_defs['default']) ? '0' : "''";	// spezial value for empty, but set, default
			}
			$col_defs['notnull'] = isset($col_defs['nullable']) && !$col_defs['nullable'];

			$col_defs['n'] = $n;

			$content["Row$n"] = $col_defs;

			$columns[$n++] = $col_name;
		}
		$n = 2;
		foreach(array('uc','ix') as $type)
		{
			foreach($table[$type] as $index)
			{
				if (is_array($index) && isset($index[1]))	// multicolum index
				{
					$content['Index'][$n]['unique'] = $type == 'uc';
					$content['Index'][$n]['n'] = $n - 1;
					foreach($index as $col)
					{
						$content['Index'][$n][] = array_search($col,$columns);
					}
					++$n;
				}
			}
		}
		if ($extra_index)
		{
			$content['Index'][$n]['n'] = $n-1;
		}
		if ($this->debug >= 3)
		{
			echo "content ="; _debug_array($content);
			echo "columns ="; _debug_array($columns);
		}
		return $content;
	}

	/**
	 * creates table-definition from posted content
	 *
	 * It sets some reasonalbe defaults for not set precisions (else setup will not install)
	 *
	 * @param array $content posted content-array
	 * @return table-definition
	 */
	function content2table($content)
	{
		if (!is_array($this->data))
		{
			$this->read($content['posted_app'],$this->data);
		}
		$old_cols = $this->data[$posted_table = $content['posted_table']]['fd'];
		$this->changes = $content['changes'];

		$table = array();
		$table['fd'] = array();	// do it in the default order of tables_*
		$table['pk'] = array();
		$table['fk'] = array();
		$table['ix'] = array();
		$table['uc'] = array();
		for (reset($content),$n = 1; isset($content["Row$n"]); ++$n)
		{
			$col = $content["Row$n"];

			if ($col['type'] == 'auto')	// auto columns are the primary key and not null!
			{
				$col['pk'] = $col['notnull'] = true;	// set it, in case the user forgot
			}

			while ((list($old_name,$old_col) = @each($old_cols)) &&
						 $this->changes[$posted_table][$old_name] == '**deleted**') ;

			if (($name = $col['name']) != '')		// ignoring lines without column-name
			{
				if ($col['name'] != $old_name && $n <= count($old_cols))	// column renamed --> remeber it
				{
					$this->changes[$posted_table][$old_name] = $col['name'];
					//echo "<p>content2table: $posted_table.$old_name renamed to $col[name]</p>\n";
				}
				if ($col['precision'] <= 0)
				{
					switch ($col['type']) // set some defaults for precision, else setup fails
					{
						case 'float':
						case 'int':     $col['precision'] = 4; break;
						case 'char':    $col['precision'] = 1; break;
						case 'varchar': $col['precision'] = 255; break;
					}
				}
				foreach($col as $prop => $val)
				{
					switch ($prop)
					{
						case 'default':
						case 'type':	// selectbox ensures type is not empty
						case 'precision':
						case 'scale':
						case 'comment':
							if ($val != '')
							{
								$table['fd'][$name][$prop] = $prop=='default'&& $val=="''" ? '' : $val;
							}
							break;
						case 'notnull':
							if ($val)
							{
								$table['fd'][$name]['nullable'] = False;
							}
							break;
						case 'pk':
						case 'uc':
						case 'ix':
							if ($val)
							{
								if ($col['options'])
								{
									$opts = array();
									foreach(explode(',',$col['options']) as $opt)
									{
										list($db,$opt) = preg_split('/[(:)]/',$opt);
										$opts[$db] = is_numeric($opt) ? intval($opt) : $opt;
									}
									$table[$prop][] = array(
										$name,
										'options' => $opts
									);
								}
								else
								{
									$table[$prop][] = $name;
								}
							}
							break;
						case 'fk':
							if ($val != '')
							{
								$table['fk'][$name] = $val;
							}
							break;
					}
				}
				$num2col[$n] = $col['name'];
			}
		}
		foreach($content['Index'] as $n => $index)
		{
			$idx_arr = array();
			foreach($index as $key => $num)
			{
				if (is_numeric($key) && $num && @$num2col[$num])
				{
					$idx_arr[] = $num2col[$num];
				}
			}
			if (count($idx_arr) && !isset($content['delete_index'][$n]))
			{
				if ($index['unique'])
				{
					$table['uc'][] = $idx_arr;
				}
				else
				{
					$table['ix'][] = $idx_arr;
				}
			}
		}
		if ($this->debug >= 2)
		{
			echo "<p>content2table: table ="; _debug_array($table);
			echo "<p>changes = "; _debug_array($this->changes);
		}
		return $table;
	}

	/**
	 * includes $app/setup/tables_current.inc.php
	 * @param string $app application name
	 * @param array &$phpgw_baseline where to return the data
	 * @return boolean True if file found, False else
	 */
	function read($app,&$phpgw_baseline)
	{
		$file = EGW_SERVER_ROOT."/$app/setup/tables_current.inc.php";

		$phpgw_baseline = array();

		if ($app != '' && file_exists($file))
		{
			include($file);
		}
		else
		{
			return False;
		}
		if ($this->debug >= 5)
		{
			echo "<p>read($app): file='$file', phpgw_baseline =";
			_debug_array($phpgw_baseline);
		}
		return True;
	}

	/**
	 * returns an array as string in php-notation
	 *
	 * @param array $arr
	 * @param int $depth for idention
	 * @param string $parent
	 * @return string
	 */
	function write_array($arr,$depth,$parent='')
	{
		if (in_array($parent,array('pk','fk','ix','uc')))
		{
			$depth = 0;
		}
		if ($depth)
		{
			$tabs = "\n".str_repeat("\t",$depth-1);
			++$depth;
		}
		$def = 'array('.$tabs.($tabs ? "\t" : '');

		$n = 0;
		foreach($arr as $key => $val)
		{
			if (!is_int($key))
			{
				$def .= "'$key' => ";
			}
			if (is_array($val))
			{
				$def .= $this->write_array($val,$parent == 'fd' ? 0 : $depth,$key);
			}
			else
			{
				if (!$only_vals && $key === 'nullable')
				{
					$def .= $val ? 'True' : 'False';
				}
				else
				{
					$def .= "'$val'";
				}
			}
			if ($n < count($arr)-1)
			{
				$def .= ','.$tabs.($tabs ? "\t" : '');
			}
			++$n;
		}
		$def .= $tabs.')';

		return $def;
	}

	/**
	 * writes tabledefinitions $phpgw_baseline to file /$app/setup/tables_current.inc.php
	 *
	 * @param string $app app-name
	 * @param array $phpgw_baseline tabledefinitions
	 * @return boolean True if file writen else False
	 */
	function write($app,$phpgw_baseline)
	{
		$file = EGW_SERVER_ROOT."/$app/setup/tables_current.inc.php";

		if (file_exists($file) && ($f = fopen($file,'r')))
		{
			$header = fread($f,filesize($file));
			if ($end = strpos($header,');'))
			{
				$footer = substr($header,$end+3);	// this preservs other stuff, which should not be there
			}
			$header = substr($header,0,strpos($header,'$phpgw_baseline'));
			fclose($f);

			if (is_writable(EGW_SERVER_ROOT."/$app/setup"))
			{
				$old_file = EGW_SERVER_ROOT . "/$app/setup/tables_current.old.inc.php";
				if (file_exists($old_file))
				{
					unlink($old_file);
				}
				rename($file,$old_file);
			}
			while ($header[strlen($header)-1] == "\t")
			{
				$header = substr($header,0,strlen($header)-1);
			}
		}
		if (!$header)
		{
			$header = $this->setup_header($this->app) . "\n\n";
		}
		if (!is_writeable(EGW_SERVER_ROOT."/$app/setup") || !($f = fopen($file,'w')))
		{
			return False;
		}
		$def .= "\$phpgw_baseline = ";
		$def .= $this->write_array($phpgw_baseline,1);
		$def .= ";\n";

		fwrite($f,$header . $def . $footer);
		fclose($f);

		return True;
	}

	/**
	 * reads and updates the version and tables info in file $app/setup/setup.inc.php
	 * @param string $app the app
	 * @param string $new new version number to set, if $new != ''
	 * @param string $tables new tables to include (comma delimited), if != ''
	 * @return the version or False if the file could not be read or written
	 */
	function setup_version($app,$new = '',$tables='')
	{
		//echo "<p>etemplate.db_tools.setup_version('$app','$new','$tables')</p>\n";

		$file = EGW_SERVER_ROOT."/$app/setup/setup.inc.php";
		if (file_exists($file))
		{
			include($file);
		}
		if (!is_array($setup_info[$app]) || !isset($setup_info[$app]['version']))
		{
			return False;
		}
		if (($new == '' || $setup_info[$app]['version'] == $new) &&
				(!$tables || $setup_info[$app]['tables'] && "'".implode("','",$setup_info[$app]['tables'])."'" == $tables))
		{
			return $setup_info[$app]['version'];	// no change requested or not necessary
		}
		if ($new == '')
		{
			$new = $setup_info[$app]['version'];
		}
		if (!($f = fopen($file,'r')))
		{
			return False;
		}
		$fcontent = fread($f,filesize($file));
		fclose ($f);

		$app_pattern = "'$app'";
		if (preg_match("/define\('([^']+)',$app_pattern\)/",$fcontent,$matches))
		{
			$app_pattern = $matches[1];
		}
		if (is_writable(EGW_SERVER_ROOT."/$app/setup"))
		{
			$old_file = EGW_SERVER_ROOT . "/$app/setup/setup.old.inc.php";
			if (file_exists($old_file))
			{
				unlink($old_file);
			}
			rename($file,$old_file);
		}
		$fnew = preg_replace('/(.*\\$'."setup_info\\[$app_pattern\\]\\['version'\\][ \\t]*=[ \\t]*)'[^']*'(.*)/i","\\1'$new'\\2",$fcontent);

		if ($tables != '')
		{
			if (isset($setup_info[$app]['tables']))	// if there is already tables array, update it
			{
				$fnew = preg_replace('/(.*\\$'."setup_info\\[$app_pattern\\]\\['tables'\\][ \\t]*=[ \\t]*array\()[^)]*/i","\\1$tables",$fwas=$fnew);

				if ($fwas == $fnew)	// nothing changed => tables are in single lines
				{
					$fwas = explode("\n",$fwas);
					$fnew = $prefix = '';
					$stage = 0;	// 0 = before, 1 = in, 2 = after tables section
					foreach($fwas as $line)
					{
						if (preg_match('/(.*\\$'."setup_info\\[$app_pattern\\]\\['tables'\\]\\[[ \\t]*\\][ \\t]*=[ \\t]*)'/i",$line,$parts))
						{
							if ($stage == 0)	// first line of tables-section
							{
								$stage = 1;
								$prefix = $parts[1];
							}
						}
						else					// not in table-section
						{
							if ($stage == 1)	// first line after tables-section ==> add it
							{
								$tables = explode(',',$tables);
								foreach ($tables as $table)
								{
									$fnew .= $prefix . $table . ";\n";
								}
								$stage = 2;
							}
							if (strpos($line,'?>') === False)	// dont write the closeing tag
							{
								$fnew .= $line . "\n";
							}
						}
					}
				}
			}
			else	// add the tables array
			{
				if (strpos($fnew,'?>') !== false)	// remove a closeing tag
				{
					$fnew = str_replace('?>','',$fnew);
				}
				$fnew .= "\t\$setup_info[$app_pattern]['tables'] = array($tables);\n";
			}
		}
		if (!is_writeable(EGW_SERVER_ROOT."/$app/setup") || !($f = fopen($file,'w')))
		{
			return False;
		}
		fwrite($f,$fnew);
		fclose($f);

		return $new;
	}

	/**
	 * updates file /$app/setup/tables_update.inc.php to reflect changes in $current
	 *
	 * @param string $app app-name
	 * @param array $current new tabledefinitions
	 * @param string $version new version
	 * @return boolean True if file writen else False
	 */
	function update($app,$current,$version)
	{
		//echo "<p>etemplate.db_tools.update('$app',...,'$version')</p>\n";
		if (!is_writable(EGW_SERVER_ROOT."/$app/setup"))
		{
			return False;
		}
		$file_current  = EGW_SERVER_ROOT."/$app/setup/tables_current.inc.php";
		$file_update   = EGW_SERVER_ROOT."/$app/setup/tables_update.inc.php";

		$old_version = $this->setup_version($app);
		$old_version_ = str_replace('.','_',$old_version);

		if (file_exists($file_update))
		{
			$f = fopen($file_update,'r');
			$update = fread($f,filesize($file_update));
			$update = str_replace('?>','',$update);
			fclose($f);
			$old_file = EGW_SERVER_ROOT . "/$app/setup/tables_update.old.inc.php";
			if (file_exists($old_file))
			{
				unlink($old_file);
			}
			rename($file_update,$old_file);
		}
		else
		{
			$update = $this->setup_header($this->app);
		}
		$update .= "
function $app"."_upgrade$old_version_()
{\n";

			$update .= $this->update_schema($app,$current,$tables);

			$update .= "
	return \$GLOBALS['setup_info']['$app']['currentver'] = '$version';
}
\n";
		if (!($f = fopen($file_update,'w')))
		{
			//echo "<p>Cant open '$update' for writing !!!</p>\n";
			return False;
		}
		fwrite($f,$update);
		fclose($f);

		$this->setup_version($app,$version,$tables);

		return True;
	}

	/**
	 * unsets all keys in an array which have a given value
	 *
	 * @param array &$arr
	 * @param mixed $val value to check against
	 */
	function remove_from_array(&$arr,$value)
	{
		foreach($arr as $key => $val)
		{
			if ($val == $value)
			{
				unset($arr[$key]);
			}
		}
	}

	/**
	 * creates an update-script
	 *
	 * @param string $app app-name
	 * @param array $current new table-defintion
	 * @param string &$tables returns comma delimited list of new table-names
	 * @return string the update-script
	 */
	function update_schema($app,$current,&$tables)
	{
		$this->read($app,$old);

		$tables = '';
		foreach($old as $name => $table_def)
		{
			if (!isset($current[$name]))	// table $name droped
			{
				$update .= "\t\$GLOBALS['egw_setup']->oProc->DropTable('$name');\n";
			}
			else
			{
				$tables .= ($tables ? ',' : '') . "'$name'";

				$new_table_def = $table_def;
				foreach($table_def['fd'] as $col => $col_def)
				{
					if (!isset($current[$name]['fd'][$col]))	// column $col droped
					{
						if (!isset($this->changes[$name][$col]) || $this->changes[$name][$col] == '**deleted**')
						{
							unset($new_table_def['fd'][$col]);
							$this->remove_from_array($new_table_def['pk'],$col);
							$this->remove_from_array($new_table_def['fk'],$col);
							$this->remove_from_array($new_table_def['ix'],$col);
							$this->remove_from_array($new_table_def['uc'],$col);
							$update .= "\t\$GLOBALS['egw_setup']->oProc->DropColumn('$name',";
							$update .= $this->write_array($new_table_def,2).",'$col');\n";
						}
						else	// column $col renamed
						{
							$new_col = $this->changes[$name][$col];
							$update .= "\t\$GLOBALS['egw_setup']->oProc->RenameColumn('$name','$col','$new_col');\n";
						}
					}
				}
				if (is_array($this->changes[$name]))
				{
					foreach($this->changes[$name] as $col => $new_col)
					{
						if ($new_col != '**deleted**')
						{
							$old[$name]['fd'][$new_col] = $old[$name]['fd'][$col];	// to be able to detect further changes of the definition
							unset($old[$name]['fd'][$col]);
						}
					}
				}
			}
		}
		foreach($current as $name => $table_def)
		{
			if (!isset($old[$name]))	// table $name added
			{
				$tables .= ($tables ? ',' : '') . "'$name'";

				$update .= "\t\$GLOBALS['egw_setup']->oProc->CreateTable('$name',";
				$update .= $this->write_array($table_def,2).");\n";
			}
			else
			{
				$old_norm = $this->normalize($old[$name]);
				$new_norm = $this->normalize($table_def);
				$old_norm_fd = $old_norm['fd']; unset($old_norm['fd']);
				$new_norm_fd = $new_norm['fd']; unset($new_norm['fd']);

				// check if the indices are changed and refresh the table if so
				$do_refresh = serialize($old_norm) != serialize($new_norm);
				// we comment out the Add or AlterColumn code as it is not needed, but might be useful for more complex updates
				foreach($table_def['fd'] as $col => $col_def)
				{
					if (($add = !isset($old[$name]['fd'][$col])) ||	// column $col added
						 serialize($old_norm_fd[$col]) != serialize($new_norm_fd[$col])) // column definition altered
					{
						$update .= "\t".($do_refresh ? "/* done by RefreshTable() anyway\n\t" : '').
							"\$GLOBALS['egw_setup']->oProc->".($add ? 'Add' : 'Alter')."Column('$name','$col',";
						$update .= $this->write_array($col_def,2) . ');' . ($do_refresh ? '*/' : '') . "\n";
					}
				}
				if ($do_refresh)
				{
					$update .= "\t\$GLOBALS['egw_setup']->oProc->RefreshTable('$name',";
					$update .= $this->write_array($table_def,2).");\n";
				}
			}
		}
		if ($this->debug)
		{
			echo "<p>update_schema($app, ...) =<br><pre>$update</pre>)</p>\n";
		}
		return $update;
	}

	/**
	 * orders the single-colum-indices after the columns and the multicolunm ones behind
	 *
	 * @param array $index array with indices
	 * @param array $cols array with column-defs (col-name is the key)
	 * @return array the new array of indices
	 */
	function normalize_index($index,$cols)
	{
		$normalized = array();
		foreach($cols as $col => $data)
		{
			foreach($index as $n => $idx)
			{
				if ($idx == $col || is_array($idx) && $idx[0] == $col && !isset($idx[1]))
				{
					$normalized[] = isset($idx['options']) ? $idx : $col;
					unset($index[$n]);
					break;
				}
			}
		}
		foreach($index as $idx)
		{
			$normalized[] = $idx;
		}
		return $normalized;
	}

	/**
	 * normalices all properties in a table-definiton, eg. all nullable properties to True or False
	 *
	 * this is necessary to compare two table-defitions
	 *
	 * @param array $table table-definition
	 * @return array the normaliced defintion
	 */
	function normalize($table)
	{
		foreach($table['fd'] as $col => $props)
		{
			$table['fd'][$col] = array(
				'type' => (string)$props['type'],
				'precision' => 0+$props['precision'],
				'scale' => 0+$props['scale'],
				'nullable' => !isset($props['nullable']) || !!$props['nullable'],
				'default' => (string)$props['default'],
				'comment' => (string)$props['comment'],
			);
		}
		return array(
			'fd' => $table['fd'],
			'pk' => $table['pk'],
			'fk' => $table['fk'],
			'ix' => $this->normalize_index($table['ix'],$table['fd']),
			'uc' => $this->normalize_index($table['uc'],$table['fd'])
		);
	}

	/**
	 * compares two table-definitions, by comparing normaliced string-representations (serialize)
	 *
	 * @param array $a
	 * @param array $b
	 * @return boolean true if they are identical (would create an identical schema), false otherwise
	 *
	 */
	function tables_identical($a,$b)
	{
		$a = serialize($this->normalize($a));
		$b = serialize($this->normalize($b));

		//echo "<p>checking if tables identical = ".($a == $b ? 'True' : 'False')."<br>\n";
		//echo "a: $a<br>\nb: $b</p>\n";

		return $a == $b;
	}

	/**
	 * creates file header
	 *
	 */
	function setup_header($app)
	{
		return '<?php
/**
 * eGroupWare - Setup
 * http://www.egroupware.org
 * Created by eTemplates DB-Tools written by ralfbecker@outdoor-training.de
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package '. $app. '
 * @subpackage setup
 * @version $Id'.'$
 */
';
	}
}