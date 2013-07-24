<?php
/**
 * InfoLog - User interface
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package infolog
 * @copyright (c) 2003-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.infolog_ui.inc.php 42718 2013-06-10 13:13:19Z leithoff $
 */

/**
 * This class is the UI-layer (user interface) of InfoLog
 */
class infolog_ui
{
	var $public_functions = array(
		'index'       => True,
		'edit'        => True,
		'delete'      => True,
		'close'       => True,
		'admin'       => True,
		'hook_view'   => True,
		'writeLangFile' => True,
		'import_mail' => True,
	);
	/**
	 * reference to the infolog preferences of the user
	 *
	 * @var array
	 */
	var $prefs;
	/**
	 * instance of the bo-class
	 *
	 * @var boinfolog
	 */
	var $bo;
	/**
	 * instance of the etemplate class
	 *
	 * @var etemplate
	 */
	var $tmpl;
	/**
	 * allowed units and hours per day, can be overwritten by the projectmanager configuration, default all units, 8h
	 *
	 * @var string
	 */
	var $duration_format = ',';	// comma is necessary!

	var $icons = array(
		'type' => array(
			'task'      => 'task.gif',      'task_alt'      => 'Task',
			'phone'     => 'phone.gif',     'phone_alt'     => 'Phonecall',
			'note'      => 'note.gif',      'note_alt'      => 'Note',
			'confirm'   => 'confirm.gif',   'confirm_alt'   => 'Confirmation',
			'reject'    => 'reject.gif',    'reject_alt'    => 'Reject',
			'email'     => 'email.gif',     'email_alt'     => 'Email' ),
		'action' => array(
			'new'       => 'new.gif',       'new_alt'       => 'Add Sub',
			'view'      => 'view.gif',      'view_alt'      => 'View Subs',
			'parent'    => 'parent.gif',    'parent_alt'    => 'View other Subs',
			'edit'      => 'edit.gif',      'edit_alt'      => 'Edit',
			'addfile'   => 'addfile.gif',   'addfile_alt'   => 'Add a file',
			'delete'    => 'delete.gif',    'delete_alt'    => 'Delete',
			'close'     => 'done.gif',      'close_alt'     => 'Close' ,
			'close_all' => 'done_all.gif',  'close_all_alt' => 'Close' ),
		'status' => array(
			'billed'    => 'billed.gif',    'billed_alt'    => 'billed',
			'done'      => 'done.gif',      'done_alt'      => 'done',
			'will-call' => 'will-call.gif', 'will-call_alt' => 'will-call',
			'call'      => 'call.gif',      'call_alt'      => 'call',
			'ongoing'   => 'ongoing.gif',   'ongoing_alt'   => 'ongoing',
			'offer'     => 'offer.gif',     'offer_alt'     => 'offer' )
	);
	var $filters;
	var $messages = array(
		'edit'    => 'InfoLog - Edit',
		'add'     => 'InfoLog - New',
		'add_sub' => 'InfoLog - New Subproject',
		'sp'      => '- Subprojects from',
		're'      => 'Re:'
	);

	/**
	 * Constructor
	 *
	 * @return infolog_ui
	 */
	function __construct()
	{
		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'infolog') $GLOBALS['egw']->translation->add_app('infolog');
		$this->bo = new infolog_bo();

		$this->tmpl = new etemplate();

		$this->user = $GLOBALS['egw_info']['user']['account_id'];

		$this->prefs =& $GLOBALS['egw_info']['user']['preferences']['infolog'];

		// read the duration format from project-manager
		if ($GLOBALS['egw_info']['apps']['projectmanager'])
		{
			$pm_config = config::read('projectmanager');
			$this->duration_format = str_replace(',','',$pm_config['duration_units']).','.$pm_config['hours_per_workday'];
			unset($pm_config);
		}
		$this->filters =& $this->bo->filters;
		/* these are just for testing of the notifications
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['delegated-open-enddate'.date('Y-m-d',time()+$i*24*60*60)] = "delegated due in $i day(s)";
		}
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['responsible-open-enddate'.date('Y-m-d',time()+$i*24*60*60)] = "responsible due in $i day(s)";
		}
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['delegated-open-date'.date('Y-m-d',time()+$i*24*60*60)] = "delegated starting in $i day(s)";
		}
		for($i = -1; $i <= 3; ++$i)
		{
			$this->filters['responsible-open-date'.date('Y-m-d',time()+$i*24*60*60)] = "responsible starting in $i day(s)";
		}
		*/
		$GLOBALS['infolog_ui'] =& $this;	// make ourself availible for ExecMethod of get_rows function
	}

	/**
	 * Sets additional fields for one infolog entry, which are not persistent in the DB
	 *
	 * @param array $info infolog entry read from the db
	 * @param array &$readonlys ACL specific settings for the buttons
	 * @param string $action
	 * @param string/int $action_id
	 * @param boolean $show_links
	 * @param int $details
	 * @return array
	 */
	function get_info($info,&$readonlys,$action='',$action_id='',$show_links=false,$details = 1)
	{
		if (!is_array($info))
		{
			$info = $this->bo->read($info);
		}
		$id = $info['info_id'];
		$done = $info['info_status'] == 'done' || $info['info_status'] == 'billed' || $info['info_status'] == 'cancelled'; //cancelled is regarded as a completed status as well in bo
		// regard an infolog as done/billed/cancelled if its percentage is 100% when there is to status like the above for that type
		if (!$done && !isset($this->bo->status[$info['info_type']]['done']) && !isset($this->bo->status[$info['info_type']]['billed']) &&
			!isset($this->bo->status[$info['info_type']]['cancelled']) && (int)$info['info_percent']==100) $done = true ;
		$info['sub_class'] = $this->bo->enums['priority'][$info['info_priority']] . ($done ? '_done' : '');
		if (!$done && $info['info_enddate'] < $this->bo->user_time_now)
		{
			$info['end_class'] = 'overdue';
		}
		if (!isset($info['info_anz_subs'])) $info['info_anz_subs'] = $this->bo->anzSubs($id);
		$this->bo->link_id2from($info,$action,$action_id);	// unset from for $action:$action_id
		$info['info_percent'] = (int) $info['info_percent'].'%';
		$editrights = $this->bo->check_access($info,EGW_ACL_EDIT);
		$isresposible = $this->bo->is_responsible($info);
		$readonlys["edit[$id]"] = !($editrights || // edit rights or more then standard responsible rights
			$isresposible && array_diff($this->bo->responsible_edit,array('info_status','info_percent','info_datecompleted')));
		$readonlys["close[$id]"] = $done || ($readonlys["edit_status[$id]"] =
			!($editrights || $isresposible));
		$readonlys["close_all[$id]"] = ($done) || !$info['info_anz_subs'] || ($readonlys["edit_status[$id]"] =
			!($editrights || $isresposible)); // this one is supressed, when you are not allowed to edit, or not responsible, or the entry is closed
			// and has no children. If you want that this one is shown if there are children regardless of the status of the current or its childs,
			// then modify ($done) to ($done && !$info['info_anz_subs'])
		$readonlys["edit_status[$id]"] = $readonlys["edit_percent[$id]"] =
			!$editrights && !$isresposible &&
			!$this->bo->check_access($info,EGW_ACL_UNDELETE);	// undelete is handled like status edit
		$readonlys["delete[$id]"] = !$this->bo->check_access($info,EGW_ACL_DELETE);
		$readonlys["sp[$id]"] = !$this->bo->check_access($info,EGW_ACL_ADD);
		$readonlys["view[$id]"] = $info['info_anz_subs'] < 1;
		$readonlys['view[0]'] = True;	// no parent
		$readonlys["timesheet[$id]"] = !isset($GLOBALS['egw_info']['user']['apps']['timesheet']);

		if (!$show_links) $show_links = $this->prefs['show_links'];
		if (($show_links != 'none' && $show_links != 'no_describtion' ||
			 $this->prefs['show_times'] || isset($GLOBALS['egw_info']['user']['apps']['timesheet'])) &&
			(isset($info['links']) || ($info['links'] = egw_link::get_links('infolog',$info['info_id'],'','link_lastmod DESC',true))))
		{
			$timesheets = array();
			foreach ($info['links'] as $link)
			{
				if ($show_links != 'none' && $show_links != 'no_describtion' &&
					$link['link_id'] != $info['info_link_id'] &&
				    ($link['app'] != $action || $link['id'] != $action_id) &&
					($show_links == 'all' || ($show_links == 'links') === ($link['app'] != egw_link::VFS_APPNAME)))
				{
					$info['filelinks'][] = $link;
				}
				if (!$info['pm_id'] && $link['app'] == 'projectmanager')
				{
					$info['pm_id'] = $link['id'];
				}
				if ($link['app'] == 'timesheet') $timesheets[] = $link['id'];

				if ($link['app'] != 'timesheet' && $link['app'] != egw_link::VFS_APPNAME)
				{
					$info['extra_links'] .= '&link_app[]='.$link['app'].'&link_id[]='.$link['id'];
				}
			}
			if ($this->prefs['show_times'] && isset($GLOBALS['egw_info']['user']['apps']['timesheet']) && $timesheets)
			{
				$sum = ExecMethod('timesheet.timesheet_bo.sum',$timesheets);
				$info['info_sum_timesheets'] = $sum['duration'];
			}
		}
		$info['info_type_label'] = $this->bo->enums['type'][$info['info_type']];
		$info['info_status_label'] = isset($this->bo->status[$info['info_type']][$info['info_status']]) ?
			$this->bo->status[$info['info_type']][$info['info_status']] : $info['info_status'];

		if (!$this->prefs['show_percent'] || $this->prefs['show_percent'] == 2 && !$details)
		{
			if ($info['info_status'] == 'ongoing' && $info['info_type'] != 'phone')
			{
				$info['info_status'] = $info['info_status_label'] = $info['info_percent'];
			}
			$readonlys["edit_percent[$id]"] = true;
		}
		elseif($readonlys["edit_percent[$id]"])	// show percent, but button is switched off
		{
			$info['info_percent2'] = $info['info_percent'];
		}
		if ($this->prefs['show_id'] == 1 || $this->prefs['show_id'] == 2 && $details)
		{
			$info['info_number'] = $info['info_id'];
		}
		return $info;
	}

	/**
	 * Saves state of the infolog list in the session
	 *
	 * @param array $values
	 */
	function save_sessiondata($values)
	{
		$for = @$values['session_for'] ? $values['session_for'] : @$this->called_by;
		//echo "<p>$for: ".__METHOD__.'('.print_r($values,True).") called_by='$this->called_by', for='$for'<br />".function_backtrace()."</p>\n";

		$arrayToStore = array(
			'search' => $values['search'],
			'start'  => $values['start'],
			'num_rows' => $values['num_rows'],
			'filter' => $values['filter'],
			'filter2' => $values['filter2'],
			'cat_id' => $values['cat_id'],
			'order'  => $values['order'],
			'sort'   => $values['sort'],
			'action' => $values['action'],
			'action_id' => $values['action_id'],
			'action_title' => $values['action_title'],
			'col_filter' => $values['col_filter'],
			'session_for' => $for
		);
		if ($values['filter']=='bydate')
		{
			$arrayToStore['startdate'] = $values['startdate'];
			$arrayToStore['enddate'] = $values['enddate'];
		}
		$GLOBALS['egw']->session->appsession($for.'session_data','infolog',$arrayToStore);
	}

	/**
	 * reads list-state from the session
	 *
	 * @return array
	 */
	function read_sessiondata()
	{
		$values = $GLOBALS['egw']->session->appsession(@$this->called_by.'session_data','infolog');
		if (!@$values['session_for'] && $this->called_by)
		{
			$values['session_for'] = $this->called_by;
			$this->save_sessiondata($values);
		}
		//echo "<p>called_by='$this->called_by': ".__METHOD__."() = ".print_r($values,True)."</p>\n";
		return $values;
	}

	/**
	 * Callback for nextmatch widget
	 *
	 * @param array &$query
	 * @param array &$rows
	 * @param array &$readonlys
	 * @return int
	 */
	function get_rows(&$query,&$rows,&$readonlys)
	{
		$orginal_colfilter = $query['col_filter'];
		if ($query['filter'] == 'bydate')
		{
			$query['header_left'] = 'infolog.index.dates';
			$GLOBALS['egw']->js->set_onload("set_style_by_class('table','custom_hide','visibility','visible');");
			if (is_int($query['startdate'])) $query['col_filter'][] = 'info_startdate >= '.$GLOBALS['egw']->db->quote($query['startdate']);
			if (is_int($query['enddate'])) $query['col_filter'][] = 'info_startdate <= '.$GLOBALS['egw']->db->quote($query['enddate']+(60*60*24)-1);
			//unset($query['startdate']);
			//unset($query['enddate']);
		}
		else
		{
			unset($query['header_left']);
			unset($query['startdate']);
			unset($query['enddate']);
		}
//_debug_array($query);
//_debug_array($query['col_filter']);

		//echo "<p>infolog_ui.get_rows(start=$query[start],search='$query[search]',filter='$query[filter]',cat_id=$query[cat_id],action='$query[action]/$query[action_id]',col_filter=".print_r($query['col_filter'],True).",sort=$query[sort],order=$query[order])</p>\n";
		if (!isset($query['start'])) $query['start'] = 0;

		if (!$query['csv_export'])
		{
			$this->save_sessiondata($query);
		}
		else
		{
			$query['csv_fields'] = $this->csv_export_fields($query['col_filter']['info_type']);
		}
		// handle linked filter (show only entries linked to a certain other entry)
		unset($query['col_filter']['info_id']);
		if ($query['col_filter']['linked'])
		{
			list($app,$id) = explode(':',$query['col_filter']['linked']);
			if (!($links = egw_link::get_links($app,$id,'infolog')))
			{
				$rows = array();	// no infologs linked to project --> no rows to return
				return 0;
			}
			$query['col_filter']['info_id'] = array_values(array_unique($links));
			$linked = $query['col_filter']['linked'];
		}
		unset($query['col_filter']['linked']);

		// check if we have a custom, type-specific template
		unset($query['template']);
		unset($query['custom_fields']);
		if ($query['col_filter']['info_type'])
		{
			$tpl = new etemplate;
			if ($tpl->read('infolog.index.rows.'.$query['col_filter']['info_type']))
			{
				$query['template'] =& $tpl;
				$query['custom_fields'] = true;	// read the custom fields too
			}
			//echo "<p align=right>template ='".'infolog.index.rows.'.$query['col_filter']['info_type']."'".(!$query['template'] ? ' not' : '')." found</p>\n";
		}
		// do we need to read the custom fields, depends on the column is enabled and customfields exist, prefs are filter specific
		// so we have to check that as well
		$details = $query['filter2'] == 'all';
		$columselection = $this->prefs['nextmatch-infolog.index.rows'.($details?'-details':'')];
		//_debug_array($columselection);
		if ($columselection)
		{
			$query['selectcols'] = $columselection;
			$columselection = explode(',',$columselection);
		}
		else
		{
			$columselection = $query['selectcols'] ? explode(',',$query['selectcols']) : array();
		}
		// do we need to query the cf's
		$query['custom_fields'] = $this->bo->customfields && (!$columselection || in_array('customfields',$columselection));

		$infos = $this->bo->search($query);
		$query['col_filter'] = $orginal_colfilter;
		if (!is_array($infos))
		{
			$infos = array( );
		}
		$details = $query['filter2'] == 'all';
		// add a '-details' to the name of the columnselection pref
		if ($details)
		{
			$query['columnselection_pref'] = (is_object($query['template'])?$query['template']->name:'infolog.index.rows').'-details';
			$query['default_cols'] = '!cat_id,info_used_time_info_planned_time,info_used_time_info_planned_time_info_replanned_time,info_id';
		}
		else
		{
			$query['columnselection_pref'] = 'infolog.index.rows';
			$query['default_cols'] = '!cat_id,info_datemodified,info_used_time_info_planned_time,info_used_time_info_planned_time_info_replanned_time,info_id';
		}
		// set old show_times pref, that get_info calculates the cumulated time of the timesheets (we only check used&planned to work for both time cols)
		$this->prefs['show_times'] = strpos($this->prefs['nextmatch-'.$query['columnselection_pref']],'info_used_time_info_planned_time') !== false;

		// query all links and sub counts in one go
		if ($infos && !$query['csv_export'])
		{
			$links = bolink::get_links_multiple('infolog',array_keys($infos));
			$anzSubs = $this->bo->anzSubs(array_keys($infos));
		}
		$readonlys = $rows = array();
		foreach($infos as $id => $info)
		{
			if (!$query['csv_export'])
			{
				$info['links'] =& $links[$id];
				$info['info_anz_subs'] = (int)$anzSubs[$id];
				$info = $this->get_info($info,$readonlys,$query['action'],$query['action_id'],$query['filter2'],$details);

				if (!$query['filter2'] && $this->prefs['show_links'] == 'no_describtion' ||
					$query['filter2'] == 'no_describtion')
				{
					unset($info['info_des']);
				}
			}
			$rows[] = $info;
		}
		unset($links);

		if ($query['cat_id']) $rows['no_cat_id'] = true;
		if ($query['no_actions']) $rows['no_actions'] = true;
		$rows['no_timesheet'] = !isset($GLOBALS['egw_info']['user']['apps']['timesheet']);
		$rows['duration_format'] = ','.$this->duration_format.',,1';

		// switch cf column off, if we have no cf's
		if (!$query['custom_fields']) $rows['no_customfields'] = true;

		if ($GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']))
		{
			$rows['no_info_owner_info_responsible'] = true;
			// dont show owner, responsible in the columnselection
			$query['options-selectcols']['info_owner'] = $query['options-selectcols']['info_responsible'] = false;
		}
		//echo "<p>readonlys = "; _debug_array($readonlys);
		//echo "rows=<pre>".print_r($rows,True)."</pre>\n";

		// if filtered by type, show only the stati of the filtered type
		if ($query['col_filter']['info_type'] && isset($this->bo->status[$query['col_filter']['info_type']]))
		{
			$rows['sel_options']['info_status'] = $this->bo->status[$query['col_filter']['info_type']];
		}
		else	// show all stati
		{
			$rows['sel_options']['info_status'] = array();
			foreach($this->bo->status as $typ => $stati)
			{
				if ($typ != 'defaults' && !empty($stati)) $rows['sel_options']['info_status'] += $stati;
			}
		}
		if ($this->bo->history)
		{
			$rows['sel_options']['info_status']['deleted'] = 'deleted';
		}

		if ($GLOBALS['egw_info']['flags']['currentapp'] == 'infolog')
		{
			$GLOBALS['egw_info']['flags']['app_header'] = lang('Infolog');
			if ($query['filter'] != 'none')
			{
				$GLOBALS['egw_info']['flags']['app_header'] .= ' - '.lang($this->filters[$query['filter']]);
			}
			if ($query['action'] && ($title = $query['action_title'] ? $query['action_title'] : egw_link::title($query['action'],$query['action_id'])))
			{
				$GLOBALS['egw_info']['flags']['app_header'] .= ': '.$title;
			}
		}
		// disable filemanager icon, if user has no access to it
		$readonlys['filemanager/navbar'] = !isset($GLOBALS['egw_info']['user']['apps']['filemanager']);

		if (isset($linked)) $query['col_filter']['linked'] = $linked;  // add linked back to the colfilter

		return $query['total'];
	}

	/**
	 * Shows the infolog list
	 *
	 * @param array/string $values=null etemplate content or 'reset_action_view' if called by index.php to reset an action-view
	 * @param string $action='' if set only entries liked to that $action:$action_id are shown
	 * @param string $action_id='' if set only entries liked to that $action:$action_id are shown
	 * @param mixed $called_as=0 this is how we got called, for a hook eg. the call-params of that page containing the hook
	 * @param boolean $extra_app_header=false
	 * @param boolean $return_html=false
	 * @param string $own_referer='' this is our own referer
	 * @param string $action_title='' app_header for the action, if '' we try the link-title
	 */
	function index($values = null,$action='',$action_id='',$called_as=0,$extra_app_header=False,$return_html=False,$own_referer='',$action_title='')
	{
		if (is_array($values))
		{
			$called_as = $values['called_as'];
			$own_referer = $values['own_referer'];
		}
		elseif ($own_referer === '')
		{
			$own_referer = $GLOBALS['egw']->common->get_referer();
			if (strpos($own_referer,'menuaction=infolog.infolog_ui.edit') !== false)
			{
				$own_referer = $GLOBALS['egw']->session->appsession('own_session','infolog');
			}
			else
			{
				$GLOBALS['egw']->session->appsession('own_session','infolog',$own_referer);
			}
		}
		if (!$action)
		{
			$action = (is_array($values) && $values['action']) ? $values['action'] : get_var('action',array('POST','GET'));
			$action_id = (is_array($values) && $values['action_id']) ? $values['action_id'] : get_var('action_id',array('POST','GET'));
			$action_title = (is_array($values) && $values['action_title']) ? $values['action_title'] : get_var('action_title',array('POST','GET'));

			if ($values === 'reset_action_view')	// only read action from session, if not called by index.php
			{
				$session = $this->read_sessiondata();
				$session['action'] = $action = '';
				$session['action_id'] = $action_id = 0;
				$session['action_title'] = $action_title = '';
				$this->save_sessiondata($session);
				unset($session);
			}
			elseif (!$action)
			{
				$session = $this->read_sessiondata();
				$action = $session['action'];
				$action_id = $session['action_id'];
				$action_title = $session['action_title'];
				// no action -> try to restore the previous colfilter
				$colfilter = $session['col_filter'];
				unset($session);
			}
		}
		//echo "<p>".__METHOD__."(action='$action/$action_id',called_as='$called_as/$values[referer]',own_referer='$own_referer') values=\n"; _debug_array($values);
		if (!is_array($values))
		{
			$values = array('nm' => $this->read_sessiondata());
			if (isset($_GET['filter']) && $_GET['filter'] != 'default' || !isset($values['nm']['filter']) && !$this->called_by)
			{
				$values['nm']['filter'] = $_GET['filter'] && $_GET['filter'] != 'default' ? $_GET['filter'] :
					$this->prefs['defaultFilter'];
			}
			if (!isset($values['nm']['order']) || !$values['nm']['order'])
			{
				$values['nm']['order'] = 'info_datemodified';
				$values['nm']['sort'] = 'DESC';
			}
			$values['msg'] = $_GET['msg'];
			$values['action'] = $action;
			$values['action_id'] = $action_id;
		}
		if($_GET['search']) {
			$values['nm']['search'] = $_GET['search'];
		}
		if ($values['nm']['add'])
		{
			$values['add'] = $values['nm']['add'];
			unset($values['nm']['add']);
		}
		if ($values['add'] || $values['cancel'] || isset($values['nm']['rows']) || isset($values['main']))
		{
			if ($values['add'])
			{
				list($type) = each($values['add']);
				return $this->edit(0,$action,$action_id,$type,$called_as);
			}
			elseif ($values['cancel'] && $own_referer)
			{
				$session = $this->read_sessiondata();
				unset($session['action']);
				unset($session['action_id']);
				$this->save_sessiondata($session);
				$this->tmpl->location($own_referer);
			}
			else
			{
				list($do,$do_id) = isset($values['main']) ? each($values['main']) : @each($values['nm']['rows']);
				list($do_id) = @each($do_id);
				//echo "<p>infolog::index: do='$do/$do_id', referer="; _debug_array($called_as);
				switch($do)
				{
					case 'edit':
					case 'edit_status':
						return $this->edit($do_id,$action,$action_id,'',$called_as);
					case 'delete':
						if (!($values['msg'] = $this->delete($do_id,$called_as,$called_as ? '' : 'index'))) return;
						// did we deleted the entries, whos subentries we are showing?
						if ($action == 'sp' && $action_id == $do_id)
						{
							// redirect to our referer or reset the subentry view
							if (!$called_as && $own_referer)
							{
								$this->tmpl->location($own_referer);	// eg. redirect back to calendar
							}
							else
							{
								unset($action_id); unset($action);
							}
						}
						break;
					case 'close':
						$closesingle=true;
					case 'close_all':
						$this->close($do_id,$called_as,$closesingle);
						break;
					case 'sp':
						return $this->edit(0,'sp',$do_id,'',$called_as);
					case 'view':
						$value = array();
						$action = 'sp';
						$action_id = $do_id;
						break;
					default:
						$value = array();
						$action = '';
						$action_id = 0;
						break;
				}
			}
		}
		switch ($action)
		{
			case 'sp':
				if (!$this->bo->read($action_id))
				{
					$action = '';
					$action_id = 0;
					break;
				}
				$values['main'][1] = $this->get_info($action_id,$readonlys['main']);
				break;
		}
		$readonlys['cancel'] = $action != 'sp';

		$this->tmpl->read('infolog.index');
		if ($colfilter) $values['nm']['col_filter'] = $persist['col_filter'] = $colfilter;
		$values['nm']['options-filter'] = $this->filters;
		$values['nm']['get_rows'] = 'infolog.infolog_ui.get_rows';
		$values['nm']['options-filter2'] = (in_array($this->prefs['show_links'],array('all','no_describtion')) ? array() : array(
			''               => 'default',
		)) + array(
			'no_describtion' => 'no details',
			'all'            => 'details',
		);
		if(!isset($values['nm']['filter2'])) $values['nm']['filter2'] = $this->prefs['show_links'];
		// disable columns for main entry as set in the pref for details or no details
		if ($action == 'sp')
		{
			$pref = 'nextmatch-infolog.index.rows'.($values['nm']['filter2']=='all'?'-details':'');
			foreach(array('info_used_time_info_planned_time_info_replanned_time','info_datemodified','info_owner_info_responsible','customfields') as $name)
			{
				$values['main']['no_'.$name] = strpos($this->prefs[$pref],$name) === false;
			}
			if (!$values['main']['no_customfields'])
			{
				// set the column-header of the main table for the customfields.
				foreach($this->bo->customfields as $lname => $data)
				{
					$values['main']['customfields'].=$lname."\n";
				}
			}
		}
		$values['nm']['header_right'] = 'infolog.index.header_right';
		if ($extra_app_header && $values['nm']['filter']!='bydate')
		{
			$values['nm']['header_left'] = 'infolog.index.header_left';
		}
		if ($values['nm']['filter']=='bydate')
		{
			foreach (array_keys($values['nm']['col_filter']) as $colfk) if (is_int($colfk)) unset($values['nm']['col_filter']);
			$values['nm']['header_left'] = 'infolog.index.dates';
			$GLOBALS['egw']->js->set_onload("set_style_by_class('table','custom_hide','visibility','visible');");
		}
		$values['nm']['bottom_too'] = True;
		$values['nm']['never_hide'] = isset($this->prefs['never_hide']) ?
			$this->prefs['never_hide'] : $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] > 15;
		$values['action'] = $persist['action'] = $values['nm']['action'] = $action;
		$values['action_id'] = $persist['action_id'] = $values['nm']['action_id'] = $action_id;
		$values['action_title'] = $persist['action_title'] = $values['nm']['action_title'] = $action_title;
		$persist['called_as'] = $called_as;
		$persist['own_referer'] = $own_referer;
		$values['nm']['csv_fields'] = true;		// get set in get_rows to not include all custom fields
		$persist['nm'] = array(
			'sort' => $values['nm']['sort'],
			'order' => $values['nm']['order'],
		);
		if (!$called_as)
		{
			$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualInfologIndex');
		}
		else
		{
			$values['css'] = '<style type="text/css">@import url('.$GLOBALS['egw_info']['server']['webserver_url'].'/infolog/templates/default/app.css);'."</style>";
		}
		// add scrollbar to long describtion, if user choose so in his prefs
		if ($this->prefs['limit_des_lines'] > 0 || (string)$this->prefs['limit_des_lines'] == '')
		{
			$values['css'] .= '<style type="text/css">@media screen { .infoDes {  max-height: '.
				(($this->prefs['limit_des_lines'] ? $this->prefs['limit_des_lines'] : 5) * 1.35).	// dono why em is not real lines
				'em; overflow: auto; }}</style>';
		}
		$sel_options = array(
		'info_type'     => $this->bo->enums['type'],
		'pm_id'      => array(lang('No project')),
		);
		return $this->tmpl->exec('infolog.infolog_ui.index',$values,$sel_options,$readonlys,$persist,$return_html ? -1 : 0);
	}

	/**
	 * Closes an infolog
	 *
	 * @param int|array $values=0 info_id (default _GET[info_id])
	 * @param string $referer=''
	 * @param boolean $closesingle=false
	 */
	function close($values=0,$referer='',$closesingle=false)
	{
		//echo "<p>".__METHOD__."($values,$referer,$closeall)</p>\n";
		$info_id = (int) (is_array($values) ? $values['info_id'] : ($values ? $values : $_GET['info_id']));
		$referer = is_array($values) ? $values['referer'] : $referer;

		if ($info_id)
		{
			$info = $this->bo->read($info_id);
			#_debug_array($info);
			$status = $info['info_status'];
			// closed stati assumed array('done','billed','cancelled')
			if (isset($this->bo->status[$info['info_type']]['done'])) {
				$status ='done';
			} elseif (isset($this->bo->status[$info['info_type']]['billed'])) {
				$status ='billed';
			} elseif (isset($this->bo->status[$info['info_type']]['cancelled'])) {
				$status ='cancelled';
			}
			#_debug_array($status);
			$values = array(
				'info_id'     => $info_id,
				'info_type'   => $info['info_type'],
				'info_status' => $status,
				'info_percent'=> 100,
				'info_datecompleted' => $this->bo->now_su,
			);
			$this->bo->write($values);

			$query = array('action'=>'sp','action_id'=>$info_id);
			if (!$closesingle) {
				foreach((array)$this->bo->search($query) as $info)
				{
					if ($info['info_id_parent'] == $info_id)	// search also returns linked entries!
					{
						$this->close($info['info_id'],$referer,$closeall);	// we call ourselfs recursive to process subs from subs too
					}
				}
			}
		}
		if ($referer) $this->tmpl->location($referer);
	}

	/**
	 * Deletes an InfoLog entry
	 *
	 * @param array|int $values=0 info_id (default _GET[info_id])
	 * @param string $referer=''
	 * @param string $called_by=''
	 */
	function delete($values=0,$referer='',$called_by='')
	{
		$info_id = (int) (is_array($values) ? $values['info_id'] : ($values ? $values : $_GET['info_id']));
		$referer = is_array($values) ? $values['referer'] : $referer;

		if (!is_array($values) && $info_id > 0 && !$this->bo->anzSubs($info_id))	// entries without subs get confirmed by javascript
		{
			$values = array('delete' => true);
		}
		//echo "<p>infolog_ui::delete(".print_r($values,true).",'$referer','$called_by') info_id=$info_id</p>\n";

		if (is_array($values) || $info_id <= 0)
		{
			if (($values['delete'] || $values['delete_subs']) && $info_id > 0 && $this->bo->check_access($info_id,EGW_ACL_DELETE))
			{
				$deleted = $this->bo->delete($info_id,$values['delete_subs'],$values['info_id_parent']);
			}
			if ($called_by)		// direct call from the same request
			{
				return $deleted ? lang('InfoLog entry deleted') : '';
			}
			if ($values['called_by'] == 'edit')	// we run in the edit popup => give control back to edit
			{
				$this->edit(array(
					'info_id' => $info_id,
					'button'  => array('deleted' => true),	// not delete!
					'referer' => $referer,
					'msg'     => $deleted ? lang('Infolog entry deleted') : '',
				));
			}
			return $referer ? $this->tmpl->location($referer) : $this->index();
		}
		$readonlys = $values = array();
		$values['main'][1] = $this->get_info($info_id,$readonlys['main']);

		$this->tmpl->read('infolog.delete');

		$values['nm'] = array(
			'action'         => 'sp',
			'action_id'      => $info_id,
			'options-filter' => $this->filters,
			'get_rows'       => 'infolog.infolog_ui.get_rows',
			'no_filter2'     => True,
			'never_hide'     => isset($this->prefs['never_hide']) ?
				$this->prefs['never_hide'] :
				$GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'] > 15,
		);
		$values['main']['no_actions'] = $values['nm']['no_actions'] = True;

		$persist['info_id'] = $info_id;
		$persist['referer'] = $referer;
		$persist['info_id_parent'] = $values['main'][1]['info_id_parent'];
		$persist['called_by'] = $called_by;

		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Delete');
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => 'ManualInfologDelete');

		$this->tmpl->exec('infolog.infolog_ui.delete',$values,'',$readonlys,$persist,$called_by == 'edit' ? 2 : 0);
	}

	/**
	 * Edit/Create an InfoLog Entry
	 *
	 * @param array $content=null Content from the eTemplate Exec call or info_id on inital call
	 * @param string $action='' Name of an app of 'sp' for a infolog-sub
	 * @param int $action_id=0 Id of app-entry to which a link is created
	 * @param string $type='' Type of log-entry: note,todo,task
	 * @param string $referer='' array with param/get-vars of the refering page
	 */
	function edit($content = null,$action = '',$action_id=0,$type='',$referer='')
	{
		$tabs = 'description|links|delegation|project|customfields|history';
		if (is_array($content))
		{
			//echo "infolog_ui::edit: content="; _debug_array($content);
			$info_id   = $content['info_id'];
			$action    = $content['action'];    unset($content['action']);
			$action_id = $content['action_id']; unset($content['action_id']);
			$referer   = $content['referer'];   unset($content['referer']);
			$no_popup  = $content['no_popup'];  unset($content['no_popup']);
			$caller    = $content['caller'];    unset($content['caller']);
			// convert custom from to 0 or 1, it's unset if not checked, which starts the detection
			$content['info_custom_from'] = (int)$content['info_custom_from'];

			list($button) = @each($content['button']);
			if (!$button && $action) $button = $action;	// action selectbox
			unset($content['button']);
			if ($button)
			{
				//Copy Infolog
				if (($button == 'copy'))
				{
					unset($content['info_id']);
					unset ($info_id);
					unset($content['info_datemodified']);
					unset($content['info_modifier']);

					// Get links to be copied
					$content['link_to']['to_id'] = egw_link::get_links($content['link_to']['to_app'], $content['link_to']['to_id']);
					if($content['info_link_id']) {
						$info_link_id = $content['info_link_id'];
						unset($content['info_link_id']);
					}

					$content['info_owner'] = !(int)$this->owner || !$this->bo->check_perms(EGW_ACL_ADD,0,$this->owner) ? $this->user : $this->owner;
					$content['msg'] = lang('Infolog copied - the copy can now be edited');
					$content['info_subject'] = lang('Copy of:').' '.$content['info_subject'];
				}
				if ($button == 'print')
				{
					$content['js'] = $this->custom_print($content,!$content['info_id'])."\n".$js;	// first open the new window and then update the view
				}
				//echo "<p>infolog_ui::edit(info_id=$info_id) '$button' button pressed, content="; _debug_array($content);
				if (($button == 'save' || $button == 'apply') && isset($content['info_subject']) && empty($content['info_subject']))
				{
					$this->tmpl->set_validation_error('info_subject',lang('Field must not be empty !!!'));
					$button = '';	// stop save or apply
				}
				if (($button == 'save' || $button == 'apply') && $info_id)
				{
					if (!($edit_acl = $this->bo->check_access($info_id,EGW_ACL_EDIT)))
					{
						$old = $this->bo->read($info_id);
						$status_only = $this->bo->is_responsible($old);
						$undelete = $this->bo->check_access($old,EGW_ACL_UNDELETE);
					}
				}
				if (($button == 'save' || $button == 'apply') && (!$info_id || $edit_acl || $status_only || $undelete))
				{
					if ($content['info_contact'] &&
						(list($app,$id) = explode(':',$content['info_contact'], 2)) && $id)
					{
						$old_link_id = (int)$content['info_link_id'];
						$content['info_link_id'] = (int)($info_link_id = egw_link::link('infolog',$content['link_to']['to_id'],$app,$id));
							if ($old_link_id && $old_link_id != $content['info_link_id']) egw_link::unlink($old_link_id);
					}
					if (is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
					{
						$content['info_link_id'] = 0;	// as field has to be int
					}
					$active_tab = $content[$tabs];
					if (!($info_id = $this->bo->write($content)))
					{
						$content['msg'] = $info_id !== 0 || !$content['info_id'] ? lang('Error: saving the entry') :
							lang('Error: the entry has been updated since you opened it for editing!').'<br />'.
							lang('Copy your changes to the clipboard, %1reload the entry%2 and merge them.','<a href="'.
								htmlspecialchars($GLOBALS['egw']->link('/index.php',array(
									'menuaction' => 'infolog.infolog_ui.edit',
									'info_id'    => $content['info_id'],
									'no_popup'   => $no_popup,
									'referer'    => $referer,
								))).'">','</a>');
						$button = '';	// not exiting edit
						$info_id = $content['info_id'];
					}
					else
					{
						$content['msg'] = lang('InfoLog entry saved');
						if ($referer !== false)
						{
							$content['js'] = "opener.location.href='".($link=$GLOBALS['egw']->link($referer,array('msg' => $content['msg'])))."';";
						}
					}
					$content[$tabs] = $active_tab;
					if ((int) $content['pm_id'] != (int) $content['old_pm_id'])
					{
						//echo "<p>pm_id changed: $content[old_pm_id] -> $content[pm_id]</p>\n";
						// update links accordingly, if selected project changed
						if ($content['pm_id'])
						{
							egw_link::link('infolog',$content['link_to']['to_id'],'projectmanager',$content['pm_id']);
							// making the project the selected link, if no other link selected
							if (!$info_link_id || $info_link_id == 'projectmanager:'.$content['old_pm_id'])
							{
								$info_link_id = 'projectmanager:'.$content['pm_id'];
							}
							//echo "<p>this->link->link('infolog',".array2string($content['link_to']['to_id']).", 'projectmanager',{$content['pm_id']}); info_link_id=$info_link_id, info_id=$info_id</p>";
						}
						if ($content['old_pm_id'])
						{
							//echo "<p>this->link->unlink2(0,infolog,{$content['link_to']['to_id']},0,'projectmanager',{$content['old_pm_id']});</p>\n";
							egw_link::unlink2(0,infolog,$content['link_to']['to_id'],0,'projectmanager',$content['old_pm_id']);
							$content['old_pm_id'] = $content['pm_id'];
						}
					}
					// writing links for a new entry
					if ($info_id && is_array($content['link_to']['to_id']) && count($content['link_to']['to_id']))
					{
						//echo "<p>writing links for new entry $info_id</p>\n"; _debug_array($content['link_to']['to_id']);
						egw_link::link('infolog',$info_id,$content['link_to']['to_id']);
						$content['link_to']['to_id'] = $info_id;
					}
					if ($info_link_id && strpos($info_link_id,':') !== false)	// updating info_link_id if necessary
					{
						list($app,$id) = explode(':',$info_link_id);
						$link = egw_link::get_link('infolog',$info_id,$app,$id);
						if ((int) $content['info_link_id'] != (int) $link['link_id'])
						{
							$content['info_link_id'] = $link['link_id'];

							$to_write = array(
								'info_id'      => $content['info_id'],
								'info_link_id' => $content['info_link_id'],
								'info_from'    => $content['info_from'],
								'info_type'    => $content['info_type'],
								'info_owner'   => $content['info_owner'],
								'info_custom_from' => $content['info_custom_from'],
							);
							//echo "<p>updating info_link_id: ".print_r($to_write,true)."</p>\n";
							$this->bo->write($to_write,False,true,true,true);	// last true = no notifications, as no real change

							// we need eg. the new modification date, for further updates
							$content = array_merge($content,$to_write);
						}
					}
				}
				elseif ($button == 'delete' && $info_id > 0)
				{
					if (!$referer && $action) $referer = array(
						'menuaction' => 'infolog.infolog_ui.index',
						'action' => $action,
						'action_id' => $action_id
					);
					if (!($content['msg'] = $this->delete($info_id,$referer,'edit'))) return;	// checks ACL first

					$content['js'] = "opener.location.href='".$GLOBALS['egw']->link($referer,array('msg' => $content['msg']))."';";
				}
				// called again after delete confirmation dialog
				elseif ($button == 'deleted'  && $content['msg'])
				{
					$content['js'] = "opener.location.href='".$GLOBALS['egw']->link($referer,array('msg' => $content['msg']))."';";
				}
				if ($button == 'save' || $button == 'cancel' || $button == 'delete' || $button == 'deleted')
				{
					if ($no_popup)
					{
						$GLOBALS['egw']->redirect_link($referer,array('msg' => $content['msg']));
					}
					$content['js'] .= 'window.close();';
					echo '<html><body onload="'.$content['js'].'"></body></html>';
					$GLOBALS['egw']->common->egw_exit();
				}
				if ($content['js']) $content['js'] = '<script>'.$content['js'].'</script>';
			}
			// on a type-change, set the status to the default status of that type, if the actual status is not supported by the new type
			if (!array_key_exists($content['info_status'],$this->bo->status[$content['info_type']]))
			{
				$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
				if ($content['info_status'] != 'done') $content['info_datecompleted'] = '';
			}
		}
		else
		{
			//echo "<p>infolog_ui::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";
			$action    = $action    ? $action    : get_var('action',   array('POST','GET'));
			$action_id = $action_id ? $action_id : get_var('action_id',array('POST','GET'));
			$info_id   = $content   ? $content   : get_var('info_id',  array('POST','GET'));
			$type      = $type      ? $type      : get_var('type',     array('POST','GET'));
			$ref=$referer   = $referer !== '' ? $referer : ($_GET['referer'] ? $_GET['referer'] :
				$GLOBALS['egw']->common->get_referer('/index.php?menuaction=infolog.infolog_ui.index'));
			$referer = preg_replace('/([&?]{1})msg=[^&]+&?/','\\1',$referer);	// remove previou/old msg from referer
			$no_popup  = $_GET['no_popup'];
			$print = (int) $_REQUEST['print'];
			//echo "<p>infolog_ui::edit: info_id=$info_id,  action='$action', action_id='$action_id', type='$type', referer='$referer'</p>\n";

			$content = $this->bo->read( $info_id || $action != 'sp' ? $info_id : $action_id );
			if (is_numeric($_REQUEST['cat_id']))
			{
				$content['info_cat'] = (int) $_REQUEST['cat_id'];
			}
			if (!$content)
			{
				$content['info_cat'] = $this->prefs['cat_add_default'];
			}
			if ($_GET['msg']) $content['msg'] = strip_tags($_GET['msg']);	// dont allow HTML!

			switch($this->prefs['set_start'])
			{
				case 'date': default: $set_startdate = mktime(0,0,0,date('m',$this->bo->user_time_now),date('d',$this->bo->user_time_now),date('Y',$this->bo->user_time_now)); break;
				case 'datetime':      $set_startdate = $this->bo->user_time_now; break;
				case 'empty':         $set_startdate = 0; break;
			}
			if ((int)$content['info_link_id'] > 0 && !egw_link::get_link($content['info_link_id']))
			{
				$content['info_link_id'] = 0;	// link has been deleted
				if (!$content['info_custom_link']) $content['info_from'] = '';
			}
			if (!$info_id && $action_id && $action == 'sp')    // new SubProject
			{
				if (!$this->bo->check_access($action_id,EGW_ACL_ADD))
				{
					return $referer ? $this->tmpl->location($referer) : $this->index(0,$action,$action_id);
				}
				$parent = $this->bo->so->data;
				$content['info_id'] = $info_id = 0;
				$content['info_uid'] = ''; // ensure that we have our own UID
				$content['caldav_name'] = ''; // ensure that we have our own caldav_name
				$content['info_owner'] = $this->user;
				$content['info_id_parent'] = $parent['info_id'];
				/*
				if ($parent['info_type']=='task' && $parent['info_status']=='offer')
				{
					$content['info_type'] = 'confirm';   // confirmation to parent
					$content['info_responsible'] = $parent['info_owner'];
				}
				*/
				$content['info_type'] = $parent['info_type'];
				$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
				$content['info_percent'] = $content['info_status'] == 'done' ? '100%' : '0%';
				$content['info_datecompleted'] =$content['info_status'] == 'done' ? $this->bo->user_time_now : 0;
				$content['info_confirm'] = 'not';
				$content['info_subject']=lang($this->messages['re']).' '.$parent['info_subject'];
				$content['info_des'] = '';
				$content['info_lastmodified'] = '';
				if ($content['info_startdate'] < $this->bo->user_time_now)	// parent-startdate is in the past => today
				{
					$content['info_startdate'] = $set_startdate;
				}
				if ($content['info_enddate'] < $this->bo->user_time_now)		// parent-enddate is in the past => empty
				{
					$content['info_enddate'] = '';
				}
			}
			else
			{
				if ($info_id && !$this->bo->check_access($info_id,EGW_ACL_EDIT) &&
					!($undelete = $this->bo->check_access($info_id,EGW_ACL_UNDELETE)) &&
					!$this->bo->is_responsible($content))
				{
					if ($no_popup)
					{
						$GLOBALS['egw']->common->egw_header();
						parse_navbar();
						echo '<p class="redItalic" align="center">'.lang('Permission denied')."</p>\n";
						$GLOBALS['egw']->common->egw_exit();
					}
					$js = "alert('".lang('Permission denied')."'); window.close();";
					echo '<html><body onload="'.$js.'"></body></html>';
					$GLOBALS['egw']->common->egw_exit();
				}
			}
			$content['links'] = $content['link_to'] = array(
				'to_id' => $info_id,
				'to_app' => 'infolog',
			);
				switch ($action)
			{
				case 'sp':
					$links = egw_link::get_links('infolog',$parent['info_id'],'!'.egw_link::VFS_APPNAME);
					foreach($links as $link)
					{
						$link_id = egw_link::link('infolog',$content['link_to']['to_id'],$link['app'],$link['id'],$link['remark']);

						if ($parent['info_link_id'] == $link['link_id'])
						{
							$content['info_link_id'] = $link_id;
						}
					}
					break;

				case 'tracker':
					if ($action_id) {
						egw_link::link('infolog',$content['link_to']['to_id'],$action,$action_id);
						$content['blur_title']   = egw_link::title($action,$action_id);
					}
					$content['info_contact'] = $action.':'.$action_id;
					$t_bo = new tracker_bo();
					$tracker = $t_bo->read($action_id);
					$content['info_subject'] = $tracker['tr_summary'];
					$content['info_des'] = $tracker['tr_description'];
					foreach($this->bo->customfields as $name => $value)
					{
						if(array_key_exists('#'.$name, $tracker)) {
							$content['#'.$name] = $tracker['#'.$name];
						}
					}
					break;

				case 'projectmanager':
					$pm_links = array($action_id);
				case 'addressbook':
				case 'projects':
				case 'calendar':
				default:	// to allow other apps to participate
					if (strpos($action_id,',') !== false)
					{
						foreach (explode(',',$action_id) as $id)
						{
							egw_link::link('infolog',$content['link_to']['to_id'],$action,$id);
						}
						$content['blur_title']   = egw_link::title($action,'$id').",...";
					} else {
						if ($action_id) {
							egw_link::link('infolog',$content['link_to']['to_id'],$action,$action_id);
							$content['blur_title']   = egw_link::title($action,$action_id);
						}
					}
					$content['info_contact'] = $action.':'.$action_id;

				case '':
					if ($info_id)
					{
						if (!isset($pm_links))
						{
							$pm_links = egw_link::get_links('infolog',$info_id,'projectmanager');
						}
						break;	// normal edit
					}
				case 'new':		// new entry
					$content['info_startdate'] = (int) $_GET['startdate'] ? (int) $_GET['startdate'] : $set_startdate;
					$content['info_priority'] = 1; // normal
					$content['info_owner'] = $this->user;
					if ($type != '')
					{
						$content['info_type'] = $type;
					}
					$content['info_status'] = $this->bo->status['defaults'][$content['info_type']];
					$content['info_percent'] = $content['info_status'] == 'done' ? '100%' : '0%';
					break;
			}
			if (!isset($this->bo->enums['type'][$content['info_type']]))
			{
				$content['info_type'] = 'note';
			}
		}
		// group owners
		$types = $this->bo->enums['type'];
		if ($this->bo->group_owners)
		{
			// remove types owned by groups the user has no edit grant (current type is made readonly)
			foreach($this->bo->group_owners as $type => $group)
			{
				if (!($this->bo->grants[$group] & EGW_ACL_EDIT))
				{
					if ($type == $content['info_type'])
					{
						//echo "<p>setting type to r/o as user has no edit rights from group #$group</p>\n";
						$readonlys['info_type'] = true;
					}
					else
					{
						unset($types[$type]);
					}
				}
			}
			// set group as owner if type has a group-owner set
			if (isset($this->bo->group_owners[$content['info_type']]))
			{
				$content['info_owner'] = $this->bo->group_owners[$content['info_type']];
				// Dont allow to change the type, if user has no delete rights from the group-owner
				if ($info_id && !($this->bo->grants[$content['info_owner']] & EGW_ACL_DELETE))
				{
					//echo "<p>setting type to r/o as user has no delete rights from group #$group</p>\n";
					$readonlys['info_type'] = true;
				}
				// disable info_access for group-owners
				$readonlys['info_access'] = true;
			}
			elseif($GLOBALS['egw']->accounts->get_type($content['info_owner']) == 'g')
			{
				$content['info_owner'] = $this->user;
			}
		}
		$preserv = $content;
		// for no edit rights or implizit edit of responsible user make all fields readonly, but status and percent
		if ($info_id && !$this->bo->check_access($info_id,EGW_ACL_EDIT) && !$undelete)
		{
			$content['status_only'] = !in_array('link_to',$this->bo->responsible_edit);
			foreach(array_diff(array_merge(array_keys($content),array('pm_id')),$this->bo->responsible_edit) as $name)
			{
				foreach($this->bo->responsible_edit as $name)
				{
					$readonlys[$name] = false;
				}
				$readonlys['button[edit]'] = $readonlys['button[save]'] = $readonlys['button[apply]'] = $readonlys['no_notifications'] = false;
			}
			unset($readonlys[$tabs]);
			// need to set all customfields extra, as they are not set if empty
			foreach($this->bo->customfields as $name => $value)
			{
				$readonlys['#'.$name] = true;
			}
		}
		// ToDo: use the old status before the delete
		if ($undelete) $content['info_status'] = $this->bo->status['defaults'][$content['info_type']];

		$content['hide_from_css'] = $content['info_custom_from'] ? '' : 'hideFrom';

		if (!($readonlys['button[delete]'] = !$info_id || !$this->bo->check_access($info_id,EGW_ACL_DELETE)))
		{
			$content['info_anz_subs'] = $this->bo->anzSubs($info_id);	// to determine js confirmation of delete or not
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang($this->messages[$info_id ? 'edit' : ($action == 'sp' ? 'add_sub' : 'add')]);

		// use a typ-specific template (infolog.edit.xyz), if one exists, otherwise fall back to the generic one
		if (!$this->tmpl->read('infolog.edit.'.$content['info_type']))
		{
			$this->tmpl->read($print ? 'infolog.edit.print':'infolog.edit');
		}
		if ($this->bo->has_customfields($content['info_type']))
		{
			$content['customfields'] = $content['info_type'];
		}
		else
		{
			$readonlys[$tabs]['customfields'] = true;
		}
		if (!isset($GLOBALS['egw_info']['user']['apps']['projectmanager']))
		{
			$readonlys[$tabs]['project'] = true;	// disable the project tab
		}
		$readonlys[$tabs]['delegation'] = $GLOBALS['egw_info']['user']['preferences']['common']['account_selection'] == 'none' &&
			!isset($GLOBALS['egw_info']['user']['apps']['admin']);

		$content['duration_format'] = $this->duration_format;
		if ($this->prefs['show_id']) $content['info_number'] = $info_id;

		$old_pm_id = is_array($pm_links) ? array_shift($pm_links) : $content['old_pm_id'];
		if (!isset($content['pm_id']) && $old_pm_id) $content['pm_id'] = $old_pm_id;

		if ($info_id && $this->bo->history)
		{
			$content['history'] = array(
				'id'  => $info_id,
				'app' => 'infolog',
				'status-widgets' => array(
					'Ty' => $types,
					//'Li',	// info_link_id
					'Ca' => 'select-cat',
					'Pr' => $this->bo->enums['priority'],
					'Ow' => 'select-account',
					//'Ac',	//	info_access: private||public
					'St' => $this->bo->status[$content['info_type']]+array('deleted' => 'deleted'),
					'Pe' => 'select-percent',
					'Co' => 'date-time',
					'st' => 'date-time',
					'En' => 'date',
					'Re' => 'select-account',
					// PM fields, ToDo: access control!!!
					'pT' => 'date-duration',
					'uT' => 'date-duration',
//					'pL' => 'projectmanager-pricelist',
					'pr' => 'float',
				),
			);
			$history_stati = array();
			require_once(EGW_INCLUDE_ROOT.'/infolog/inc/class.infolog_tracking.inc.php');
			$tracking = new infolog_tracking($this);
			foreach($tracking->field2history as $field => $history)
			{
				$history_stati[$history] = $tracking->field2label[$field];
			}
			unset($tracking);
		}
		else
		{
			$readonlys[$tabs]['history'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.
			($content['status_only'] ? lang('Edit Status') : lang('Edit'));
		$GLOBALS['egw_info']['flags']['params']['manual'] = array('page' => ($info_id ? 'ManualInfologEdit' : 'ManualInfologAdd'));
		//echo "<p>infolog_ui.edit(info_id='$info_id',action='$action',action_id='$action_id') readonlys="; print_r($readonlys); echo ", content = "; _debug_array($content);
		$this->tmpl->exec('infolog.infolog_ui.edit',$content,array(
			'info_type'     => $types,
			'info_priority' => $this->bo->enums['priority'],
			'info_confirm'  => $this->bo->enums['confirm'],
			'info_status'   => $this->bo->status[$content['info_type']],
			'status'        => $history_stati,
			'action'     => array(
				'copy' => array('label' => 'Copy', 'title' => 'Copy this Infolog'),
				'print' => array('label' => 'Print', 'title' => 'Print this Infolog'),
			),
		),$readonlys,$preserv+array(	// preserved values
			'info_id'       => $info_id,
			'action'        => $action,
			'action_id'     => $action_id,
			'referer'       => $referer,
			'no_popup'      => $no_popup,
			'old_pm_id'     => $old_pm_id,
		),$no_popup ? 0 : 2);
	}

	function icon($cat,$id,$status='')
	{
		if (!$status || !($icon = $this->icons[$cat][$id.'_'.$status]))
		{
			$icon = $this->icons[$cat][$id];
		}
		if ($icon && !is_readable($GLOBALS['egw']->common->get_image_dir() . '/' . $icon))
		{
			$icon = False;
		}
		if (!$status || !($alt = $this->icons[$cat][$id.'_'.$status.'_alt']))
		{
			if (!($alt = $this->icons[$cat][$id.'_alt']))
			{
				$alt = $id;
			}
		}
		return $icon ? html::image('infolog',$icon,lang($alt),'border=0') : lang($alt);
	}

	/**
	 * stripping slashes from an array
	 *
	 * @static
	 * @param array $arr
	 * @return array
	 */
	function array_stripslashes($arr)
	{
		foreach($arr as $key => $val)
		{
			if (is_array($val))
			{
				$arr[$key] = self::array_stripslashes($var);
			}
			else
			{
				$arr[$key] = stripslashes($val);
			}
		}
		return $arr;
	}

	/**
	 * Infolog's site configuration
	 *
	 */
	function admin( )
	{
		$fields = array(
			'info_cat'      => 'Category',
			'info_from'     => 'Contact',
			'info_addr'     => 'Phone/Email',
			'info_subject'  => 'Subject',
			'info_des'      => 'Description',
			'link_to'       => 'Links',
			'info_priority' => 'Priority',
			'info_location' => 'Location',
			'info_planned_time' => 'Planned time',
			'info_used_time'    => 'Used time',
		);
		if($_POST['save'] || $_POST['apply'])
		{
			if (get_magic_quotes_gpc())
			{
				$_POST = self::array_stripslashes($_POST);
			}
			$this->bo->responsible_edit = array('info_status','info_percent','info_datecompleted');

			if ($_POST['responsible_edit'])
			{
				$extra = array_intersect((array)$_POST['responsible_edit'],array_keys($fields));
				$this->bo->responsible_edit = array_merge($this->bo->responsible_edit,$extra);
			}
			config::save_value('responsible_edit',$this->bo->responsible_edit,'infolog');
			config::save_value('implicit_rights',$this->bo->implicit_rights = $_POST['implicit_rights'] == 'edit' ? 'edit' : 'read','infolog');
			config::save_value('history',$this->bo->history = $_POST['history'],'infolog');
		}
		if($_POST['cancel'] || $_POST['save'])
		{
			$GLOBALS['egw']->redirect_link('/infolog/index.php');
		}

		$GLOBALS['egw_info']['flags']['app_header'] = lang('InfoLog').' - '.lang('Site configuration');
		$GLOBALS['egw']->common->egw_header();

		$GLOBALS['egw']->template->set_file(array('info_admin_t' => 'admin.tpl'));
		$GLOBALS['egw']->template->set_block('info_admin_t', 'info_admin');

		$GLOBALS['egw']->template->set_var(Array(
			'lang_responsible_rights' => lang('Rights for the responsible'),
			'lang_implicit_rights' => lang('Which implicit ACL rights should the responsible get?'),
			'implicit_rights' => html::select('implicit_rights',$this->bo->implicit_rights,array(
				'read' => 'read rights (default)',
				'edit' => 'edit rights (full edit rights incl. making someone else responsible!)',
			)),
			'lang_responsible_edit' => lang('Which additional fields should the responsible be allowed to edit without having edit rights?<br />Status, percent and date completed are always allowed.'),
			'responsible_edit' => html::checkbox_multiselect('responsible_edit',$this->bo->responsible_edit,$fields,false,'',11),
			'text' => lang('<b>file-attachments via symlinks</b> instead of uploads and retrieval via file:/path for direct lan-clients'),
			'action_url'  => html::link('/index.php',array('menuaction'=>'infolog.infolog_ui.admin')),
			'save_button' => html::submit_button('save','Save'),
			'apply_button' => html::submit_button('apply','Apply'),
			'cancel_button' => html::submit_button('cancel','Cancel'),
			'lang_history'=> lang('History logging'),
			'lang_history2'=> lang('History logging and deleting of items'),
			'history'     => html::select('history',$this->bo->history,array(
				'' => lang('No'),
				'history' => lang('Yes, with purging of deleted items possible'),
				'history_admin_delete' => lang('Yes, only admins can purge deleted items'),
				'history_no_delete' => lang('Yes, noone can purge deleted items'),
			))
		));

		echo parse_navbar();
		$GLOBALS['egw']->template->pfp('phpgw_body','info_admin');
	}

	/**
	 * imports a mail as infolog
	 * two possible calls:
	 * 1. with function args set. (we come from send mail)
	 * 2. with $_GET['uid] = someuid (we come from display mail)
	 *
	 * @author Cornelius Weiss <nelius@cwtech.de>
	 * @param string $_to_emailAddress
	 * @param string $_subject
	 * @param string $_body
	 * @param array $_attachments
	 * @param string $_date
	 */
	function import_mail($_to_emailAddress=false,$_subject=false,$_body=false,$_attachments=false,$_date=false)
	{
		$uid = $_GET['uid'];
		$partid = $_GET['part'];
		$mailbox = base64_decode($_GET['mailbox']);
		if ($_date == false || empty($_date)) $_date = $this->bo->user_time_now;
		if (!empty($_to_emailAddress))
		{
			$GLOBALS['egw_info']['flags']['currentapp'] = 'infolog';
			echo '<script>window.resizeTo(750,550);</script>';

			if (is_array($_attachments))
			{
				//echo __METHOD__.'<br>';
				//_debug_array($_attachments);
				$bofelamimail = CreateObject('felamimail.bofelamimail',$GLOBALS['egw']->translation->charset());
				$bopreferences = CreateObject('felamimail.bopreferences');
				$bofelamimail->openConnection();
				foreach ($_attachments as $attachment)
				{
					if ($attachment['type'] == 'MESSAGE/RFC822')
					{
						$bofelamimail->reopen($attachment['folder']);

						$mailcontent = self::get_mailcontent($bofelamimail,$attachment['uid'],$attachment['partID'],$attachment['folder']);
						//_debug_array($mailcontent['attachments']);
						foreach($mailcontent['attachments'] as $tmpattach => $tmpval)
						{
							$attachments[] = $tmpval;
						}
					}
					else
					{
						if (!empty($attachment['folder']))
						{
							$is_winmail = $_GET['is_winmail'] ? $_GET['is_winmail'] : 0;
							$bofelamimail->reopen($attachment['folder']);
							$attachmentData = $bofelamimail->getAttachment($attachment['uid'],$attachment['partID'],$is_winmail);
							$attachment['file'] =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
							$tmpfile = fopen($attachment['file'],'w');
							fwrite($tmpfile,$attachmentData['attachment']);
							fclose($tmpfile);
						}

						$attachments[] = array(
							'name' => $attachment['name'],
							'mimeType' => $attachment['type'],
							'tmp_name' => $attachment['file'],
							'size' => $attachment['size'],
						);
					}
				}
				$bofelamimail->closeConnection();
			}
			//_debug_array($attachments);
			$body = strip_tags($_body);
			$this->edit($this->bo->import_mail(
				implode(',',$_to_emailAddress),$_subject,$body,$attachments,$_date
			));
			exit;
		}
		elseif ($uid && $mailbox)
		{
			$bofelamimail = CreateObject('felamimail.bofelamimail',$GLOBALS['egw']->translation->charset());
			$bopreferences = CreateObject('felamimail.bopreferences');
			$bofelamimail->openConnection();
			$bofelamimail->reopen($mailbox);

			$mailcontent = self::get_mailcontent($bofelamimail,$uid,$partid,$mailbox);

			return $this->edit($this->bo->import_mail(
				$mailcontent['mailaddress'],
				$mailcontent['subject'],
				$mailcontent['message'],
				$mailcontent['attachments'],
				strtotime($mailcontent['headers']['DATE'])
			));
		}
		$GLOBALS['egw']->common->egw_header();
		echo "<script> window.close(); alert('Error: no mail (Mailbox / UID) given!');</script>";
		$GLOBALS['egw']->common->egw_exit();
		exit;
	}

	/**
	 * fetches the actual mailcontent
	 */
	static function get_mailcontent(&$bofelamimail,$uid,$partid='',$mailbox='')
	{
			//echo __METHOD__." called for $uid,$partid <br>";
			$headers = $bofelamimail->getMessageHeader($uid,$partid);
			// dont force retrieval of the textpart, let felamimail preferences decide
			$bodyParts = $bofelamimail->getMessageBody($uid,'',$partid);
			$attachments = $bofelamimail->getMessageAttachments($uid,$partid);

			if ($bofelamimail->isSentFolder($mailbox)) $mailaddress = $bofelamimail->decode_header($headers['TO']);
			elseif (isset($headers['FROM'])) $mailaddress = $bofelamimail->decode_header($headers['FROM']);
			elseif (isset($headers['SENDER'])) $mailaddress = $bofelamimail->decode_header($headers['SENDER']);
			if (isset($headers['CC'])) $mailaddress .= ','.$headers['CC'];
			//_debug_array($headers);
			$subject = $bofelamimail->decode_header($headers['SUBJECT']);

			$message = bofelamimail::getdisplayableBody($bofelamimail, $bodyParts);
			//echo __METHOD__.'<br>';
			//_debug_array($attachments);
			if (is_array($attachments))
			{
				foreach ($attachments as $num => $attachment)
				{
					if ($attachment['mimeType'] == 'MESSAGE/RFC822')
					{
						//_debug_array($bofelamimail->getMessageHeader($uid, $attachment['partID']));
						//_debug_array($bofelamimail->getMessageBody($uid,'', $attachment['partID']));
						//_debug_array($bofelamimail->getMessageAttachments($uid, $attachment['partID']));
						$mailcontent = self::get_mailcontent($bofelamimail,$uid,$attachment['partID']);
						$headdata ='';
						if ($mailcontent['headers'])
						{
							if ($mailcontent['headers']['SUBJECT']) $headdata = lang('subject').': '.$mailcontent['headers']['SUBJECT']."\n";
							if ($mailcontent['headers']['FROM']) $headdata .= lang('from').': '.$mailcontent['headers']['FROM']."\n";
							if ($mailcontent['headers']['SENDER']) $headdata .= lang('sender').': '.$mailcontent['headers']['SENDER']."\n";
							if ($mailcontent['headers']['TO']) $headdata .= lang('to').': '.$mailcontent['headers']['TO']."\n";
							if ($mailcontent['headers']['CC']) $headdata .= lang('cc').': '.$mailcontent['headers']['CC']."\n";
							if ($mailcontent['headers']['DATE']) $headdata .= lang('date').': '.$mailcontent['headers']['DATE']."\n";
							if ($mailcontent['headers']['PRIORITY'] && $mailcontent['headers']['PRIORITY'] != 'normal') $headdata .= lang('priority').': '.$mailcontent['headers']['PRIORITY']."\n";
							if ($mailcontent['headers']['IMPORTANCE'] && $mailcontent['headers']['IMPORTANCE'] !='normal') $headdata .= lang('importance').': '.$mailcontent['headers']['IMPORTANCE']."\n";
							//if ($mailcontent['headers']['ORGANIZATION']) $headdata .= lang('organization').': '.$mailcontent['headers']['ORGANIZATION']."\n";
							if (!empty($headdata)) $headdata .= "--------------------------------------------------------\n";
						}
						if ($mailcontent['message'])
						{
							$tempname =tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
							$attachedMessages[] = array(
								'type' => 'TEXT/PLAIN',
								'name' => $mailcontent['subject'].'.txt',
								'tmp_name' => $tempname,
							);
							$tmpfile = fopen($tempname,'w');
							fwrite($tmpfile,$headdata.$mailcontent['message']);
							fclose($tmpfile);
						}
						foreach($mailcontent['attachments'] as $tmpattach => $tmpval)
						{
							$attachedMessages[] = $tmpval;
						}
						unset($attachments[$num]);
					}
					else
					{
						$attachments[$num] = array_merge($attachments[$num],$bofelamimail->getAttachment($uid, $attachment['partID']));
						if (isset($attachments[$num]['charset'])) {
							$GLOBALS['egw']->translation->convert($attachments[$num]['attachment'],$attachments[$num]['charset']);
						}
						$attachments[$num]['type'] = $attachments[$num]['mimeType'];
						$attachments[$num]['tmp_name'] = tempnam($GLOBALS['egw_info']['server']['temp_dir'],$GLOBALS['egw_info']['flags']['currentapp']."_");
						$tmpfile = fopen($attachments[$num]['tmp_name'],'w');
						fwrite($tmpfile,$attachments[$num]['attachment']);
						fclose($tmpfile);
						unset($attachments[$num]['attachment']);
					}
				}
				if (is_array($attachedMessages)) $attachments = array_merge($attachments,$attachedMessages);
			}
			return array(
					'mailaddress'=>$mailaddress,
					'subject'=>$subject,
					'message'=>$message,
					'attachments'=>$attachments,
					'headers'=>$headers,
					);
	}

	/**
	 * return javascript to open compose window to print the Infolog
	 *
	 * @param array $event
	 * @param boolean $added
	 * @return string javascript window.open command
	 */
	function custom_print($content,$added)
	{
			$vars = array(
			'menuaction'      => 'infolog.infolog_ui.edit',
			'info_id'         => $content['info_id'],
			'print'           => true,
			);
		return "window.open('".egw::link('/index.php',$vars)."','_blank','width=700,height=700,scrollbars=yes,status=no');";
	}

	/**
	 * shows infolog in other applications
	 *
	 * @param $args['location'] location des hooks: {addressbook|projects|calendar}_view|infolog
	 * @param $args['view']     menuaction to view, if location == 'infolog'
	 * @param $args['app']      app-name, if location == 'infolog'
	 * @param $args['view_id']  name of the id-var for location == 'infolog'
	 * @param $args[$args['view_id']] id of the entry
	 * this function can be called for any app, which should include infolog: \
	 * 	$GLOBALS['egw']->hooks->process(array( \
	 * 		 * 'location' => 'infolog', \
	 * 		 * 'app'      => <your app>, \
	 * 		 * 'view_id'  => <id name>, \
	 * 		 * <id name>  => <id value>, \
	 * 		 * 'view'     => <menuaction to view an entry in your app> \
	 * 	));
	 */
	function hook_view($args)
	{
		switch ($args['location'])
		{
			case 'addressbook_view':
				$app     = 'addressbook';
				$view_id = 'ab_id';
				$view_id2 = 'contact_id';
				$view    = 'addressbook.addressbook_ui.view';
				break;
			case 'projects_view':
				$app     = 'projects';
				$view_id = 'project_id';
				$view    = 'projects.uiprojects.view';
				break;
			default:
				$app     = $args['app'];
				$view_id = $args['view_id'];
				$view    = $args['view'];
		}
		if (!is_array($args) || $args['debug'])
		{
			echo "<p>infolog_ui::hook_view("; print_r($args); echo "): app='$app', $view_id='$args[$view_id]', view='$view'</p>\n";
		}
		if (!isset($app) || !isset($args[$view_id]))
		{
			return False;
		}
		$this->called_by = $app;	// for read/save_sessiondata, to have different sessions for the hooks

		$GLOBALS['egw']->translation->add_app('infolog');

		etemplate::$hooked = true;
		$this->index(0,$app,$args[$view_id],array(
			'menuaction' => $view,
			isset($view_id2) ? $view_id2 : $view_id => $args[$view_id]
		),True);
		etemplate::$hooked = false;
	}

	/**
	 * Defines the fields for the csv export
	 *
	 * @param string $type=null infolog type to include only the matching custom fields if set
	 * @return array
	 */
	function csv_export_fields($type=null)
	{
		$fields = array(
			'info_type'          => lang('Type'),
			'info_from'          => lang('Contact'),
			'info_addr'          => lang('Phone/Email'),
//			'info_link_id'       => lang('primary link'),
			'info_cat'           => array('label' => lang('Category'),'type' => 'select-cat'),
			'info_priority'      => lang('Priority'),
			'info_owner'         => array('label' => lang('Owner'),'type' => 'select-account'),
			'info_access'        => lang('Access'),
			'info_status'        => lang('Status'),
			'info_percent'       => lang('Completed'),
			'info_datecompleted' => lang('Date completed'),
			'info_datemodified'  => lang('Last modified'),
			'info_modifier'      => array('label' => lang('Modifier'),'type' => 'select-account'),
			'info_location'      => lang('Location'),
			'info_startdate'     => lang('Startdate'),
			'info_enddate'       => lang('Enddate'),
			'info_responsible'   => array('label' => lang('Responsible'),'type' => 'select-account'),
			'info_subject'       => lang('Subject'),
			'info_des'           => lang('Description'),
			'info_id'            => lang('Id'),
			// PM fields
			'info_planned_time'  => lang('planned time'),
			'info_used_time'     => lang('used time'),
			'pl_id'              => lang('pricelist'),
			'info_price'         => lang('price'),
		);
		foreach($this->bo->timestamps as $name)
		{
			$fields[$name] = array('label' => $fields[$name],'type' => 'date-time');
		}
		foreach($this->bo->customfields as $name => $data)
		{
			if ($data['type2'] && $type && !in_array($type,explode(',',$data['type2']))) continue;

			$fields['#'.$name] = array(
				'label' => $data['label'],
				'type'  => $data['type'],
			);
		}
		return $fields;
	}
}
