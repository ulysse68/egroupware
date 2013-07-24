<?php
/**
 * eGroupWare - resources
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package resources
 * @link http://www.egroupware.org
 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
 * @version $Id: class.resources_ui.inc.php 38733 2012-03-31 14:12:25Z ralfbecker $
 */

/**
 * General userinterface object for resources
 *
 * @package resources
 */
class resources_ui
{
	var $public_functions = array(
		'index'		=> True,
		'edit'		=> True,
		'show'		=> True,
		'select'	=> True,
		'writeLangFile'	=> True
	);

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
// 		print_r($GLOBALS['egw_info']); die();
		$this->tmpl	= new etemplate('resources.show');
		$this->bo	= new resources_bo();
// 		$this->calui	= CreateObject('resources.ui_calviews');

	}

	/**
	 * main resources list.
	 *
	 * Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * @param array $content content from eTemplate callback
	 *
	 * FIXME don't translate cats in nextmach
	 */
	function index($content='')
	{
		if (is_array($content))
		{
			$sessiondata = $content['nm'];
			unset($sessiondata['rows']);
			$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);

			if (isset($content['back']))
			{
				unset($sessiondata['view_accs_of']);
				unset($sessiondata['no_filter']);
				$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);
				return $this->index();
			}
			if (isset($content['btn_delete_selected']))
			{
				foreach($content['nm']['rows'] as $row)
				{
					if($res_id = $row['checkbox'][0])
					{
						$msg .= '<p>'. $this->bo->delete($res_id). '</p><br>';
					}
				}
				return $this->index($msg);
			}

			foreach($content['nm']['rows'] as $row)
			{
				if(isset($row['delete']))
				{
					$res_id = array_search('pressed',$row['delete']);
					return $this->index($this->bo->delete($res_id));
				}
				if(isset($row['view_acc']))
				{
					$sessiondata['view_accs_of'] = array_search('pressed',$row['view_acc']);
					$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$sessiondata);
					return $this->index();
				}
			}
		}
		$msg = $content;
		$content = array();
		$content['msg'] = $msg;

		$content['nm']['header_left']	= 'resources.resource_select.header';
		$content['nm']['get_rows'] 	= 'resources.resources_bo.get_rows';
		$content['nm']['no_filter'] 	= False;
		$content['nm']['filter_label']	= lang('Category');
		$content['nm']['filter_help']	= lang('Select a category'); // is this used???
		$content['nm']['no_filter2']	= true;
		$content['nm']['filter_no_lang'] = true;
		$content['nm']['no_cat']	= true;
		$content['nm']['bottom_too']	= true;
		$content['nm']['order']		= 'name';
		$content['nm']['sort']		= 'ASC';
		$content['nm']['store_state']	= 'get_rows';

		$nm_session_data = $GLOBALS['egw']->session->appsession('session_data','resources_index_nm');
		if($nm_session_data)
		{
			$content['nm'] = $nm_session_data;
		}
		$content['nm']['options-filter']= array(''=>lang('all categories'))+(array)$this->bo->acl->get_cats(EGW_ACL_READ);
		if($_GET['search']) {
			$content['nm']['search'] = $_GET['search'];
		}

		// check if user is permitted to add resources
		if(!$this->bo->acl->get_cats(EGW_ACL_ADD))
		{
			$no_button['add'] = true;
		}
		$no_button['back'] = true;
		$no_button['add_sub'] = true;
		$GLOBALS['egw_info']['flags']['app_header'] = lang('resources');

		$GLOBALS['egw_info']['flags']['java_script'] .= "<script LANGUAGE=\"JavaScript\">
			function js_btn_book_selected(form)
			{
				resources = '';

				el = form.getElementsByTagName(\"input\");
				for (var i = 0; i < el.length; i++)
				{
					if(el[i].name.substr(el[i].name.length-12,el[i].name.length) == '[checkbox][]' && el[i].checked)
					{
						if(resources.length > 0)
						{
							resources += ',';
						}
						resources += 'r' + el[i].value;
					}
				}
				if(resources.length == 0)
				{
					alert('". lang('No resources selected'). "');
					return false;
				}
				return resources;
			}
		</script>";

		if($content['nm']['view_accs_of'])
		{
			$master = $this->bo->so->read(array('res_id' => $content['nm']['view_accs_of']));
			$content['view_accs_of'] = $content['nm']['view_accs_of'];
			$content['nm']['get_rows'] 	= 'resources.resources_bo.get_rows';
			$content['nm']['no_filter'] 	= true;
			$content['nm']['no_filter2'] 	= true;
			$no_button['back'] = false;
			$no_button['add'] = true;
			$no_button['add_sub'] = false;
			$GLOBALS['egw_info']['flags']['app_header'] = lang('resources') . ' - ' . lang('accessories of '). ' '. $master['name'] .
				($master['short_description'] ? ' [' . $master['short_description'] . ']' : '');
		}
		$preserv = $content;
		$GLOBALS['egw']->session->appsession('session_data','resources_index_nm',$content['nm']);
		$this->tmpl->read('resources.show');
		return $this->tmpl->exec('resources.resources_ui.index',$content,$sel_options,$no_button,$preserv);
	}

	/**
	 * @author Cornelius Weiss <egw@von-und-zu-weiss.de>
	 * invokes add or edit dialog for resources
	 *
	 * @param $content   Content from the eTemplate Exec call or id on inital call
	 */
	function edit($content=0,$accessory_of = -1)
	{
		if (is_array($content))
		{
			if(isset($content['save']) || isset($content['delete']))
			{
				if(isset($content['save']))
				{
					unset($content['save']);
// 					if($content['id'] != 0)
// 					{
// 						// links are already saved by eTemplate
// 						unset($resource['link_to']['to_id']);
// 					}
					$content['msg'] = $this->bo->save($content);
				}
				if(isset($content['delete']))
				{
					unset($content['delete']);
					$content['msg'] = $this->bo->delete($content['res_id']);
				}

				if($content['msg'])
				{
					return $this->edit($content);
				}
				$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',
					array('menuaction' => 'resources.resources_ui.index'))."';";
				$js .= 'window.close();';
				echo "<html><body><script>$js</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
		}
		else
		{
			$res_id = $content;
			if (isset($_GET['res_id'])) $res_id = $_GET['res_id'];
			if (isset($_GET['accessory_of'])) $accessory_of = $_GET['accessory_of'];
			$content = array('res_id' => $res_id);

			if ($res_id > 0)
			{
				$content = $this->bo->read($res_id);
				$content['gen_src_list'] = strpos($content['picture_src'],'.') !== false ? $content['picture_src'] : false;
				$content['picture_src'] = strpos($content['picture_src'],'.') !== false ? 'gen_src' : $content['picture_src'];
				$content['link_to'] = array(
					'to_id' => $res_id,
					'to_app' => 'resources'
				);
			}
			if ($_GET['msg']) $content['msg'] = strip_tags($_GET['msg']);
		}
		// some presetes
		$content['resource_picture'] = $this->bo->get_picture($content['res_id'],$content['picture_src'],$size=true);
		$content['quantity'] = $content['quantity'] ? $content['quantity'] : 1;
		$content['useable'] = $content['useable'] ? $content['useable'] : 1;
		$content['accessory_of'] = $content['accessory_of'] ? $content['accessory_of'] : $accessory_of;

		$sel_options['gen_src_list'] = $this->bo->get_genpicturelist();
		$sel_options['cat_id'] =  $this->bo->acl->get_cats(EGW_ACL_ADD);
		$sel_options['cat_id'] = count($sel_options['cat_id']) == 1 ? $sel_options['cat_id'] :
			$content['cat_id'] ? $sel_options['cat_id'] : array('' => lang('select one')) + $sel_options['cat_id'];
		if($accessory_of > 0 || $content['accessory_of'] > 0)
		{
			$content['accessory_of'] = $content['accessory_of'] ? $content['accessory_of'] : $accessory_of;
			$catofmaster = $this->bo->so->get_value('cat_id',$content['accessory_of']);
			$sel_options['cat_id'] = array($catofmaster => $sel_options['cat_id'][$catofmaster]);
		}

// 		$content['general|page|pictures|links'] = 'resources.edit_tabs.page';  //debug
		$no_button = array(); // TODO: show delete button only if allowed to delete resource
		$preserv = $content;
		$this->tmpl->read('resources.edit');
		return $this->tmpl->exec('resources.resources_ui.edit',$content,$sel_options,$no_button,$preserv,2);

	}

	/**
	 * showes a single resource
	 *
	 * @param int $res_id resource id
	 * @author Lukas Weiss <wnz.gh05t@users.sourceforge.net>
	 */
	function show($res_id=0)
	{
		if (is_array($content = $res_id))
		{
			if(isset($content['btn_delete']))
			{
				$content['msg'] = $this->bo->delete($content['res_id']);
				if($content['msg'])
				{
					return $this->show($content);
				}
				$js = "opener.location.href='".$GLOBALS['egw']->link('/index.php',
					array('menuaction' => 'resources.resources_ui.index'))."';";
				$js .= 'window.close();';
				echo "<html><body><script>$js</script></body></html>\n";
				$GLOBALS['egw']->common->egw_exit();
			}
			if(isset($content['btn_edit']))
			{
				return $this->edit($content['res_id']);
			}

		}
		if (isset($_GET['res_id'])) $res_id = $_GET['res_id'];

		$content = array('res_id' => $res_id);
		$content = $this->bo->read($res_id);
		$content['gen_src_list'] = strpos($content['picture_src'],'.') !== false ? $content['picture_src'] : false;
		$content['picture_src'] = strpos($content['picture_src'],'.') !== false ? 'gen_src' : $content['picture_src'];
		$content['link_to'] = array(
				'to_id' => $res_id,
				'to_app' => 'resources'
		);

		$content['resource_picture'] = $this->bo->get_picture($content['res_id'],$content['picture_src'],$size=true);
		$content['quantity'] = $content['quantity'] ? $content['quantity'] : 1;
		$content['useable'] = $content['useable'] ? $content['useable'] : 1;

		$content['quantity'] = ($content['useable'] == $content['quantity']) ? $content['quantity'] : $content['quantity'].' ('.lang('useable').' '.$content['useable'].')';

		//$sel_options['gen_src_list'] = $this->bo->get_genpicturelist();

		$content['cat_name'] =  $this->bo->acl->get_cat_name($content['cat_id']);
		$content['cat_admin'] = $this->bo->acl->get_cat_admin($content['cat_id']);

/*		if($content['accessory_of'] > 0)
		{
			$catofmaster = $this->bo->so->get_value('cat_id',$content['accessory_of']);
			$sel_options['cat_id'] = array($catofmaster => $sel_options['cat_id'][$catofmaster]);
		}
*/
		$content['description'] = chop($content['long_description']) ? $content['long_description'] : (chop($content['short_description']) ? $content['short_description'] : lang("no description available"));
		$content['description'] = $content['description'] ? $content['description'] : lang('no description available');
		$content['link_to'] = array(
					'to_id' => $res_id,
					'to_app' => 'resources'
				);
		$sel_options = array();
		$no_button = array(
			'btn_buy' => !$content['buyable'],
			'btn_book' => !$content['bookable'],
			'btn_calendar' => !$content['bookable'],
			'btn_edit' => !$this->bo->acl->is_permitted($content['cat_id'],EGW_ACL_EDIT),
			'btn_delete' => !$this->bo->acl->is_permitted($content['cat_id'],EGW_ACL_DELETE)
			);
		$preserv = $content;
		$this->tmpl->read('resources.showdetails');
		return $this->tmpl->exec('resources.resources_ui.show',$content,$sel_options,$no_button,$preserv,2);

	}

	/**
	 * select resources
	 *
	 * @author Lukas Weiss <wnz.gh05t@users.sourceforge.net>
	 */
	function select($content='')
	{
		$GLOBALS['phpgw']->js->set_onload("copyOptions('exec[resources][selectbox]');");

		$GLOBALS['egw_info']['flags']['java_script'] .= "<script LANGUAGE=\"JavaScript\">
			window.focus();

			openerid='resources_selectbox';
			id='exec[nm][rows][selectbox]';

			function addOption(label,value,button_id,useable)
			{
				var quantity = document.getElementById(button_id+'[default_qty]').value;
				value = value+':'+quantity;
				if(quantity>useable) {
					alert('".lang('You chose more resources than available')."');
					return false;
				}
				label = label+'['+quantity+'/'+useable+']';
				openerSelectBox = opener.document.getElementById(openerid);
				if (openerSelectBox) {
					select = '';
					for(i=0; i < openerSelectBox.length; i++) {
						with (openerSelectBox.options[i]) {
							if (selected || openerSelectBox.selectedIndex == i) {
								select += (value.slice(0,1)==',' ? '' : ',')+value;
							}
						}
					}
					select += (select ? ',' : '')+value;
					opener.selectbox_add_option(openerid,label,value,0);
				}
				selectBox = document.getElementById(id);
				if (selectBox) {
					var resource_value = value.split(':');
					for (i=0; i < selectBox.length; i++) {
						var selectvalue = selectBox.options[i].value.split(':');
						if (selectvalue[0] == resource_value[0]) {
							selectBox.options[i] = null;
							selectBox.options[selectBox.length] = new Option(label,value,false,true);
							break;
						}
					}
					if (i >= selectBox.length) {
						selectBox.options[selectBox.length] = new Option(label,value,false,true);
					}
				}
			}

			function removeSelectedOptions()
			{
				openerSelectBox = opener.document.getElementById(openerid);
				if (openerSelectBox == null) window.close();
				selectBox = document.getElementById(id);
				for (i=0; i < selectBox.length; i++) {
					if (selectBox.options[i].selected) {
						for (j=0; j < openerSelectBox.length; j++) {
							if (openerSelectBox[j].value == selectBox.options[i].value) {
								openerSelectBox.removeChild(openerSelectBox[j]);
							}
						}
						selectBox.options[i--] = null;
					}
				}
			}

			function copyOptions()
			{
				openerSelectBox = opener.document.getElementById(openerid);
				selectBox = document.getElementById(id);
				for (i=0; i < openerSelectBox.length; i++) {
					with (openerSelectBox.options[i]) {
						if (selected && value.slice(0,1) != ',') {
							selectBox.options[selectBox.length] =  new Option(text,value);
						}
					}
				}
			}

			function oneLineSubmit()
			{
			/*
				openerSelectBox = opener.document.getElementById(openerid);

				if (openerSelectBox) {
					if (openerSelectBox.selectedIndex >= 0) {
						selected = openerSelectBox.options[openerSelectBox.selectedIndex].value;
						if (selected.slice(0,1) == ',') selected = selected.slice(1);
						opener.selectbox_add_option(openerid,'multiple*',selected,1);
					}
					else {
						for (i=0; i < openerSelectBox.length; i++) {
							with (openerSelectBox.options[i]) {
								if (selected) {
									opener.selectbox_add_option(openerid,text,value,1);
									break;
								}
							}
						}
					}
				}
			*/
				window.close();
			}</script>";

		if (!is_array($content))
		{
			if (!($content['nm'] = egw_cache::getSession('resources','get_rows')))
			{
				$content['nm'] = array(
					'header_left'   => 'resources.resource_select.header',
					'show_bookable' => true,
					'get_rows' 	    => 'resources.resources_bo.get_rows',
					'filter_label'	=> 'Category',
					'filter_help'	=> lang('Select a category'),
					'options-filter'=> array(''=>lang('all categories'))+(array)$this->bo->acl->get_cats(EGW_ACL_READ),
					'no_filter2'	=> true,
					'filter_no_lang'=> true,
					'no_cat'	    => true,
					'rows'          => array('js_id' => 1),
					'csv_fields'    => false,
					'default_cols'  => 'name,cat_id,quantity',	// I  columns to use if there's no user or default pref
					'store_state' => 'get_rows',	// store in session as for location get_rows
				);
				$content['nm']['filter'] = $GLOBALS['egw_info']['user']['preferences']['resources']['filter'];
			}
		}
		$sel_options = array();
		$no_button = array();
		$this->tmpl->read('resources.resource_select');
		return $this->tmpl->exec('resources.resources_ui.select',$content,$sel_options,$no_button,$preserv,2);
	}

	/**
	 * get_calendar_sidebox
	 * get data für calendar sidebox
	 *
	 * @author Lukas Weiss <wnz_gh05t@users.sourceforge.net>
	 * @param array $param with keys menuaction, owner and optional date and return_array
	 * @return array with: label=>link or array with text
	 */
	function get_calendar_sidebox($param)
	{
		$cats = $this->bo->acl->get_cats(EGW_ACL_READ);
		if (!$cats) return array();

		if(array_key_exists('return_array', $param))
		{
			$return_array = $param['return_array'];
			unset($param['return_array']);
		}

		$owners = explode(',',$param['owner']);
		unset($param['owner']);
		$res_cats = $selected = array();

		// this gets the resource-ids of the cats and implodes them to the array-key of the selectbox,
		// so it is possible to select all resources of a category
		foreach($cats as $cat_id => $cat_name)
		{
			if ($resources = $this->bo->so->search(array('cat_id' => $cat_id, 'bookable' => '1'),'res_id'))
			{
				$keys = array();
				foreach($resources as $res)
				{
					$keys[] = 'r'.$res['res_id'];
				}
				$res_cats[implode(',',$keys)] = $cat_name;

				if (count(array_intersect($keys,$owners)) == count($keys))
				{
					$selected[] = implode(',',$keys);
					$owners = array_diff($owners,$keys);
				}
			}
		}
		// add already selected single resources to the selectbox, eg. call of the resource-calendar from the resources app
		$resources = array('r0' => lang('none'));
		$res_ids = array();
		foreach($owners as $key => $owner)
		{
			if ($owner{0} == 'r')
			{
				$res_ids[] = (int) substr($owner,1);
				$selected[] = $owner;
			}
		}
		if (count($res_ids))
		{
			foreach($this->bo->so->search(array('res_id' => $res_ids),'res_id,name') as $data)
			{
				$resources['r'.$data['res_id']] = $data['name'];
			}
		}
		if(!isset($return_array))
		{
			$selectbox = html::select(
				'owner',
				$selected,
				array_merge($resources,$res_cats),
				$no_lang=true,
				$options='style="width: 100%;" onchange="load_cal(\''.
					egw::link('/index.php',$param,false).'\',\'uical_select_resource\');" id="uical_select_resource"',
				$multiple=count($selected) ? 4 : 0
			);
			return array(
				array(
					'text' => $selectbox,
					'no_lang' => True,
					'link' => False
				)
			);
		}
		else
		{
			return array_merge($resources,$res_cats);
		}
	}
}

