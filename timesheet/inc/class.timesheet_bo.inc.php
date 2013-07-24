<?php
/**
 * TimeSheet - business object
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package timesheet
 * @copyright (c) 2005-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: class.timesheet_bo.inc.php 41948 2013-03-07 12:40:52Z ralfbecker $
 */

if (!defined('TIMESHEET_APP'))
{
	define('TIMESHEET_APP','timesheet');
}

/**
 * Business object of the TimeSheet
 *
 * Uses eTemplate's so_sql as storage object (Table: egw_timesheet).
 *
 * @todo Implement sorting&filtering by and searching of custom fields
 */
class timesheet_bo extends so_sql_cf
{
	/**
	 * Timesheets config data
	 *
	 * @var array
	 */
	var $config_data = array();
	/**
	 * Should we show a quantity sum, makes only sense if we sum up identical units (can be used to sum up negative (over-)time)
	 *
	 * @var boolean
	 */
	var $quantity_sum=false;
	/**
	 * Timestaps that need to be adjusted to user-time on reading or saving
	 *
	 * @var array
	 */
	var $user;
	/**
	 * current user
	 *
	 * @var int
	 */
	var $timestamps = array(
		'ts_start','ts_modified'
	);
	/**
	 * Start of today in user-time
	 *
	 * @var int
	 */
	var $today;
	/**
	 * Filter for search limiting the date-range
	 *
	 * @var array
	 */
	var $date_filters = array(	// Start: year,month,day,week, End: year,month,day,week
		'Today'       => array(0,0,0,0,  0,0,1,0),
		'Yesterday'   => array(0,0,-1,0, 0,0,0,0),
		'This week'   => array(0,0,0,0,  0,0,0,1),
		'Last week'   => array(0,0,0,-1, 0,0,0,0),
		'This month'  => array(0,0,0,0,  0,1,0,0),
		'Last month'  => array(0,-1,0,0, 0,0,0,0),
		'2 month ago' => array(0,-2,0,0, 0,-1,0,0),
		'This year'   => array(0,0,0,0,  1,0,0,0),
		'Last year'   => array(-1,0,0,0, 0,0,0,0),
		'2 years ago' => array(-2,0,0,0, -1,0,0,0),
		'3 years ago' => array(-3,0,0,0, -2,0,0,0),
	);
	/**
	 * Grants: $GLOBALS['egw']->acl->get_grants(TIMESHEET_APP);
	 *
	 * @var array
	 */
	var $grants;
	/**
	 * Sums of the last search in keys duration and price
	 *
	 * @var array
	 */
	var $summary;
	/**
	 * Array with boolean values in keys 'day', 'week' or 'month', for the sums to return in the search
	 *
	 * @var array
	 */
	var $show_sums;
	/**
	 * Array with custom fileds
	 *
	 * @var array
	 */
	var $customfields=array();
	/**
	 * Array with status label
	 *
	 * @var array
	 */
	var $status_labels = array();
	/**
	 * Array with status label configuration
	 *
	 * @var array
	 */
	var $status_labels_config = array();
	/**
	 * Array with substatus of label configuration
	 */
	var $status_labels_substatus = array();
	/**
	 * Instance of the timesheet_tracking object
	 *
	 * @var timesheet_tracking
	 */
	var $tracking;
	/**
	 * Translates field / acl-names to labels
	 *
	 * @var array
	 */
	var $field2label = array(
		'ts_project'     => 'Project',
		'ts_title'     	 => 'Title',
		'cat_id'         => 'Category',
		'ts_description' => 'Description',
		'ts_start'       => 'Start',
		'ts_duration'    => 'Duration',
		'ts_quantity'    => 'Quantity',
		'ts_unitprice'   => 'Unitprice',
		'ts_owner'       => 'Owner',
		'ts_modifier'    => 'Modifier',
		'ts_status'      => 'Status',
		'pm_id'		     => 'Projectid',
		// pseudo fields used in edit
		//'link_to'        => 'Attachments & Links',
		'customfields'   => 'Custom fields',
	);
	/**
	 * setting field-name from DB to history status
	 *
	 * @var array
	 */
	var $field2history = array();
	/**
	 * Name of the timesheet table storing custom fields
	 */
	const EXTRA_TABLE = 'egw_timesheet_extra';

	function __construct()
	{
		parent::__construct(TIMESHEET_APP,'egw_timesheet',self::EXTRA_TABLE,'','ts_extra_name','ts_extra_value','ts_id');

		$this->config_data = config::read(TIMESHEET_APP);
		$this->quantity_sum = $this->config_data['quantity_sum'] == 'true';
		if($this->config_data['status_labels'])
		{
			$this->status_labels =&  $this->config_data['status_labels'];
			if (!is_array($this->status_labels)) $this->status_labels= array($this->status_labels);
			foreach ($this->status_labels as $status_id => $label)
			{
				if (!is_array($label))
				{	//old values, bevor parent status
					$name = $label;
					$label=array();
					$label['name'] = $name;
					$label['parent'] = '';
				}
				$sort_labels[$status_id][] = $label['name'];
				$this->status_labels_config[$status_id] = $label;
				if ($label['parent']!='')
				{
					$sort = $label['parent']?$label['parent']:$status_id;
					$sort_labels[$sort][$status_id]= $label['name'];
					$this->status_labels_substatus[$sort][$status_id] = $status_id;
					if (!isset($this->status_labels_substatus[$sort][$sort]))
					{
						$this->status_labels_substatus[$sort][$sort]= $sort;
					}
				}
			}
			unset($this->status_labels);
			foreach ($sort_labels as $status_id => $label_sub)
			{
				if (!isset($this->status_labels[$status_id])) $this->status_labels[$status_id] = $this->status_labels_config[$status_id]['name'];
				foreach ($sort_labels[$status_id] as $sub_status_id => $sub_label)
				{
					if (isset($this->status_labels_config[$sub_status_id]))
					{
						$level = '&nbsp;&nbsp;';
						if (isset($this->status_labels_substatus[$sub_status_id])) $this->status_labels_substatus['2level'][$sub_status_id] = $sub_status_id ;
						if (isset($this->status_labels_substatus['2level'][$status_id])) $level = '&nbsp;&nbsp;&nbsp;&nbsp;';
						$this->status_labels[$sub_status_id]= $level.$this->status_labels_config[$sub_status_id]['name'];
					}
				}
			}
		}
		$this->today = mktime(0,0,0,date('m',$this->now),date('d',$this->now),date('Y',$this->now));

		// save us in $GLOBALS['timesheet_bo'] for ExecMethod used in hooks
		if (!is_object($GLOBALS['timesheet_bo']))
		{
			$GLOBALS['timesheet_bo'] =& $this;
		}
		$this->grants = $GLOBALS['egw']->acl->get_grants(TIMESHEET_APP);
		//set fields for tracking
		$this->field2history = array_keys($this->db_cols);
		$this->field2history = array_diff(array_combine($this->field2history,$this->field2history),array('ts_modified'));
		$this->field2history += array('customfields'   => '#c');  //add custom fields for history
	}

	/**
	 * get list of specified grants as uid => Username pairs
	 *
	 * @param int $required=EGW_ACL_READ
	 * @return array with uid => Username pairs
	 */
	function grant_list($required=EGW_ACL_READ)
	{
		$result = array();
		foreach($this->grants as $uid => $grant)
		{
			if ($grant & $required)
			{
				$result[$uid] = common::grab_owner_name($uid);
			}
		}
		natcasesort($result);

		return $result;
	}

	/**
	 * checks if the user has enough rights for a certain operation
	 *
	 * Rights are given via owner grants or role based acl
	 *
	 * @param int $required EGW_ACL_READ, EGW_ACL_WRITE, EGW_ACL_ADD, EGW_ACL_DELETE, EGW_ACL_BUDGET, EGW_ACL_EDIT_BUDGET
	 * @param array/int $data=null project or project-id to use, default the project in $this->data
	 * @return boolean true if the rights are ok, null if not found, false if no rights
	 */
	function check_acl($required,$data=null)
	{
		//error_log(__METHOD__."($required,".array2string($data).")");
		if (is_null($data) || (int)$data == $this->data['ts_id'])
		{
			$data =& $this->data;
		}
		if (!is_array($data))
		{
			$save_data = $this->data;
			$data = $this->read($data,true);
			$this->data = $save_data;

			if (!$data) return null; 	// entry not found
		}
		$rights = $this->grants[$data['ts_owner']];

		return $data && !!($rights & $required);
	}

	/**
	 * return SQL implementing filtering by date
	 *
	 * @param string $name
	 * @param int &$start
	 * @param int &$end_param
	 * @return string
	 */
	function date_filter($name,&$start,&$end_param)
	{
		$end = $end_param;

		if ($name == 'custom' && $start)
		{
			if ($end)
			{
				$end += 24*60*60;
			}
			else
			{
				$end = $start + 8*24*60*60;
			}
		}
		else
		{
			if (!isset($this->date_filters[$name]))
			{
				return '1=1';
			}
			$year  = (int) date('Y',$this->today);
			$month = (int) date('m',$this->today);
			$day   = (int) date('d',$this->today);

			list($syear,$smonth,$sday,$sweek,$eyear,$emonth,$eday,$eweek) = $this->date_filters[$name];

			if ($syear || $eyear)
			{
				$start = mktime(0,0,0,1,1,$syear+$year);
				$end   = mktime(0,0,0,1,1,$eyear+$year);
			}
			elseif ($smonth || $emonth)
			{
				$start = mktime(0,0,0,$smonth+$month,1,$year);
				$end   = mktime(0,0,0,$emonth+$month,1,$year);
			}
			elseif ($sday || $eday)
			{
				$start = mktime(0,0,0,$month,$sday+$day,$year);
				$end   = mktime(0,0,0,$month,$eday+$day,$year);
			}
			elseif ($sweek || $eweek)
			{
				$wday = (int) date('w',$this->today); // 0=sun, ..., 6=sat
				switch($GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'])
				{
					case 'Sunday':
						$weekstart = $this->today - $wday * 24*60*60;
						break;
					case 'Saturday':
						$weekstart = $this->today - (6-$wday) * 24*60*60;
						break;
					case 'Moday':
					default:
						$weekstart = $this->today - ($wday ? $wday-1 : 6) * 24*60*60;
						break;
				}
				$start = $weekstart + $sweek*7*24*60*60;
				$end   = $weekstart + $eweek*7*24*60*60;
			}
			$end_param = $end - 24*60*60;
		}
		//echo "<p align='right'>date_filter($name,$start,$end) today=".date('l, Y-m-d H:i',$this->today)." ==> ".date('l, Y-m-d H:i:s',$start)." <= date < ".date('l, Y-m-d H:i:s',$end)."</p>\n";
		// convert start + end from user to servertime for the filter
		return '('.($start-$this->tz_offset_s).' <= ts_start AND ts_start < '.($end-$this->tz_offset_s).')';
	}

	/**
	 * search the timesheet
	 *
	 * reimplemented to limit result to users we have grants from
	 *
	 * @param array/string $criteria array of key and data cols, OR a SQL query (content for WHERE), fully quoted (!)
	 * @param boolean/string $only_keys=true True returns only keys, False returns all cols. comma seperated list of keys to return
	 * @param string $order_by='' fieldnames + {ASC|DESC} separated by colons ',', can also contain a GROUP BY (if it contains ORDER BY)
	 * @param string/array $extra_cols='' string or array of strings to be added to the SELECT, eg. "count(*) as num"
	 * @param string $wildcard='' appended befor and after each criteria
	 * @param boolean $empty=false False=empty criteria are ignored in query, True=empty have to be empty in row
	 * @param string $op='AND' defaults to 'AND', can be set to 'OR' too, then criteria's are OR'ed together
	 * @param mixed $start=false if != false, return only maxmatch rows begining with start, or array($start,$num)
	 * @param array $filter=null if set (!=null) col-data pairs, to be and-ed (!) into the query without wildcards
	 * @param string $join='' sql to do a join, added as is after the table-name, eg. ", table2 WHERE x=y" or
	 *	"LEFT JOIN table2 ON (x=y)", Note: there's no quoting done on $join!
	 * @param boolean $need_full_no_count=false If true an unlimited query is run to determine the total number of rows, default false
	 * @param boolean $only_summary=false If true only return the sums as array with keys duration and price, default false
	 * @return array of matching rows (the row is an array of the cols) or False
	 */
	function &search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False,$op='AND',$start=false,$filter=null,$join='',$need_full_no_count=false,$only_summary=false)
	{
		//error_log(__METHOD__."(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')");
		//echo "<p>".__METHOD__."(".print_r($criteria,true).",'$only_keys','$order_by',".print_r($extra_cols,true).",'$wildcard','$empty','$op','$start',".print_r($filter,true).",'$join')</p>\n";
		// postgres can't round from double precission, only from numeric ;-)
		$total_sql = $this->db->Type != 'pgsql' ? "round(ts_quantity*ts_unitprice,2)" : "round(cast(ts_quantity*ts_unitprice AS numeric),2)";

		if (!is_array($extra_cols))
		{
			$extra_cols = $extra_cols ? explode(',',$extra_cols) : array();
		}
		if ($only_keys === false || $this->show_sums && strpos($order_by,'ts_start') !== false)
		{
			$extra_cols[] = $total_sql.' AS ts_total';
		}
		if (!isset($filter['ts_owner']) || !count($filter['ts_owner']))
		{
			$filter['ts_owner'] = array_keys($this->grants);
		}
		else
		{
			if (!is_array($filter['ts_owner'])) $filter['ts_owner'] = array($filter['ts_owner']);

			foreach($filter['ts_owner'] as $key => $owner)
			{
				if (!isset($this->grants[$owner]))
				{
					unset($filter['ts_owner'][$key]);
				}
			}
		}
		if (!count($filter['ts_owner']))
		{
			$this->total = 0;
			$this->summary = array();
			return array();
		}
		// if we only want to return the summary (sum of duration and sum of price) we have to take care that the customfield table
		// is not joined, as the join causes a multiplication of the sum per customfield found
		// joining of the cutomfield table is triggered by criteria being set with either a string or an array
		$this->summary = parent::search(($only_summary?false:$criteria),"SUM(ts_duration) AS duration,SUM($total_sql) AS price".
			($this->quantity_sum ? ",SUM(ts_quantity) AS quantity" : ''),
			'','',$wildcard,$empty,$op,false,($only_summary&&is_array($criteria)&&is_array($filter)?array_merge($criteria,$filter):$filter),($only_summary?'':$join));
		$this->summary = $this->summary[0];

		if ($only_summary) return $this->summary;

		if ($this->show_sums && strpos($order_by,'ts_start') !== false && 	// sums only make sense if ordered by ts_start
			$this->db->capabilities['union'] && ($from_unixtime_ts_start = $this->db->from_unixtime('ts_start')))
		{
			$sum_sql = array(
				'year'  => $this->db->date_format($from_unixtime_ts_start,'%Y'),
				'month' => $this->db->date_format($from_unixtime_ts_start,'%Y%m'),
				'week'  => $this->db->date_format($from_unixtime_ts_start,$GLOBALS['egw_info']['user']['preferences']['calendar']['weekdaystarts'] == 'Sunday' ? '%X%V' : '%x%v'),
				'day'   => $this->db->date_format($from_unixtime_ts_start,'%Y-%m-%d'),
			);
			foreach($this->show_sums as $type)
			{
				$extra_cols[] = $sum_sql[$type].' AS ts_'.$type;
				$extra_cols[] = '0 AS is_sum_'.$type;
				$sum_extra_cols[] = str_replace('ts_start','MIN(ts_start)',$sum_sql[$type]);	// as we dont group by ts_start
				$sum_extra_cols[$type] = '0 AS is_sum_'.$type;
			}
			// regular entries
			parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,'UNION',$filter,$join,$need_full_no_count);

			$sort = substr($order_by,8);
			$union_order = array();
			$sum_ts_id = array('year' => -3,'month' => -2,'week' => -1,'day' => 0);
			foreach($this->show_sums as $type)
			{
				$union_order[] = 'ts_'.$type . ' ' . $sort;
				$union_order[] = 'is_sum_'.$type;
				$sum_extra_cols[$type]{0} = '1';
				// the $type sum
				parent::search($criteria,$sum_ts_id[$type].",'','','',MIN(ts_start),SUM(ts_duration) AS ts_duration,".
					($this->quantity_sum ? "SUM(ts_quantity) AS ts_quantity" : '0').",0,NULL,0,0,0,0,0,SUM($total_sql) AS ts_total",
					'GROUP BY '.$sum_sql[$type],$sum_extra_cols,$wildcard,$empty,$op,'UNION',$filter,$join,$need_full_no_count);
				$sum_extra_cols[$type]{0} = '0';
			}
			$union_order[] = 'ts_start '.$sort;
			return parent::search('','',implode(',',$union_order),'','',false,'',$start);
		}
		return parent::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty,$op,$start,$filter,$join,$need_full_no_count);
	}

	/**
	 * read a timesheet entry
	 *
	 * @param int $ts_id
	 * @param boolean $ignore_acl=false should the acl be checked
	 * @return array/boolean array with timesheet entry, null if timesheet not found or false if no rights
	 */
	function read($ts_id,$ignore_acl=false)
	{
		//error_log(__METHOD__."($ts_id,$ignore_acl) ".function_backtrace());
		if (!(int)$ts_id || (int)$ts_id != $this->data['ts_id'] && !parent::read($ts_id))
		{
			return null;	// entry not found
		}
		if (!$ignore_acl && !($ret = $this->check_acl(EGW_ACL_READ)))
		{
			return false;	// no read rights
		}
		return $this->data;
	}

	/**
	 * saves a timesheet entry
	 *
	 * reimplemented to notify the link-class
	 *
	 * @param array $keys if given $keys are copied to data before saveing => allows a save as
	 * @param boolean $touch_modified=true should modification date+user be set, default yes
	 * @param boolean $ignore_acl=false should the acl be checked, returns true if no edit-rigts
	 * @return int 0 on success and errno != 0 else
	 */
	function save($keys=null,$touch_modified=true,$ignore_acl=false)
	{
		if ($keys) $this->data_merge($keys);

		if (!$ignore_acl && $this->data['ts_id'] && !$this->check_acl(EGW_ACL_EDIT))
		{
			return true;
		}
		if ($touch_modified)
		{
			$this->data['ts_modifier'] = $GLOBALS['egw_info']['user']['account_id'];
			$this->data['ts_modified'] = $this->now;
			$this->user = $this->data['ts_modifier'];
		}

		// check if we have a real modification
			// read the old record
			$new =& $this->data;
			unset($this->data);
			$this->read($new['ts_id']);
			$old =& $this->data;
			$this->data =& $new;
			$changed[] = array();
			if (isset($old)) foreach($old as $name => $value)
			{
				if (isset($new[$name]) && $new[$name] != $value) $changed[] = $name;
			}
			if (!$changed)
			{
				return false;
			}

			if (!is_object($this->tracking))
			{
				$this->tracking = new timesheet_tracking($this);

				$this->tracking->html_content_allow = true;
			}
			if ($this->customfields)
			{
				$data_custom = $old_custom = array();
				foreach($this->customfields as $name => $custom)
				{
					if (isset($this->data['#'.$name]) && (string)$this->data['#'.$name]!=='') $data_custom[] = $custom['label'].': '.$this->data['#'.$name];
					if (isset($old['#'.$name]) && (string)$old['#'.$name]!=='') $old_custom[] = $custom['label'].': '.$old['#'.$name];
				}
				$this->data['customfields'] = implode("\n",$data_custom);
				$old['customfields'] = implode("\n",$old_custom);
			}
			if (!$this->tracking->track($this->data,$old,$this->user))
			{
				return implode(', ',$this->tracking->errors);
			}
		if (!($err = parent::save()))
		{
			// notify the link-class about the update, as other apps may be subscribt to it
			egw_link::notify_update(TIMESHEET_APP,$this->data['ts_id'],$this->data);
		}


		return $err;
	}

	/**
	 * deletes a timesheet entry identified by $keys or the loaded one, reimplemented to notify the link class (unlink)
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @param boolean $ignore_acl=false should the acl be checked, returns false if no delete-rigts
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function delete($keys=null,$ignore_acl=false)
	{
		if (!is_array($keys) && (int) $keys)
		{
			$keys = array('ts_id' => (int) $keys);
		}
		$ts_id = is_null($keys) ? $this->data['ts_id'] : $keys['ts_id'];

		if (!$this->check_acl(EGW_ACL_DELETE,$ts_id))
		{
			return false;
		}
		if (($ret = parent::delete($keys)) && $ts_id)
		{
			// delete all links to timesheet entry $ts_id
			egw_link::unlink(0,TIMESHEET_APP,$ts_id);
		}
		return $ret;
	}

	/**
	 * delete / move all timesheets of a given user
	 *
	 * @param array $data
	 * @param int $data['account_id'] owner to change
	 * @param int $data['new_owner']  new owner or 0 for delete
	 */
	function deleteaccount($data)
	{
		$account_id = $data['account_id'];
		$new_owner =  $data['new_owner'];

		if (!$new_owner)
		{
			egw_link::unlink(0, TIMESHEET_APP, '', $account_id);
			parent::delete(array('ts_owner' => $account_id));
		}
		else
		{
			$this->db->update($this->table_name, array(
				'ts_owner' => $new_owner,
			), array(
				'ts_owner' => $account_id,
			), __LINE__, __FILE__, TIMESHEET_APP);
		}
	}

	/**
	 * set a status for timesheet entry identified by $keys
	 *
	 * @param array $keys if given array with col => value pairs to characterise the rows to delete
	 * @param boolean $status
	 * @return int affected rows, should be 1 if ok, 0 if an error
	 */
	function set_status($keys=null,$status)
	{
		$ret = true;
		if (!is_array($keys) && (int) $keys)
		{
			$keys = array('ts_id' => (int) $keys);
		}
		$ts_id = is_null($keys) ? $this->data['ts_id'] : $keys['ts_id'];

		if (!$this->check_acl(EGW_ACL_EDIT,$ts_id) || !$this->read($ts_id,true))
		{
			return false;
		}

		$this->data['ts_status'] = $status;
		if ($this->save($ts_id)!=0) $ret = false;

		return $ret;
	}

	/**
	 * Get the time- and pricesum for the given timesheet entries
	 *
	 * @param array $ids array of timesheet id's
	 * @return array with keys time and price
	 */
	function sum($ids)
	{
		return $this->search(array('ts_id'=>$ids),true,'','','',false,'AND',false,null,'',false,true);
	}

	/**
	 * get title for a timesheet entry identified by $entry
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param int/array $entry int ts_id or array with timesheet entry
	 * @return string/boolean string with title, null if timesheet not found, false if no perms to view it
	 */
	function link_title( $entry )
	{
		if (!is_array($entry))
		{
			$entry = $this->read( $entry,false,false);
		}
		if (!$entry)
		{
			return $entry;
		}
		$format = $GLOBALS['egw_info']['user']['preferences']['common']['dateformat'];
		if (date('H:i',$entry['ts_start']) != '00:00')	// dont show 00:00 time, as it means date only
		{
			$format .= ' '.($GLOBALS['egw_info']['user']['preferences']['common']['timeformat'] == 12 ? 'h:i a' : 'H:i');
		}
		return date($format,$entry['ts_start']).': '.$entry['ts_title'];
	}

	/**
	 * get title for multiple timesheet entries identified by $ids
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param array $ids array with ts_id's
	 * @return array with titles, see link_title
	 */
	function link_titles( array $ids )
	{
		$titles = array();
		if (($entries = $this->search(array('ts_id' => $ids),'ts_id,ts_title,ts_start')))
		{
			foreach($entries as $entry)
			{
				$titles[$entry['ts_id']] = $this->link_title($entry);
			}
		}
		// we assume all not returned entries are not readable by the user, as we notify egw_link about all deletes
		foreach($ids as $id)
		{
			if (!isset($titles[$id]))
			{
				$titles[$id] = false;
			}
		}
		return $titles;
	}

	/**
	 * query timesheet for entries matching $pattern
	 *
	 * Is called as hook to participate in the linking
	 *
	 * @param string $pattern pattern to search
	 * @param array $options Array of options for the search
	 * @return array with ts_id - title pairs of the matching entries
	 */
	function link_query( $pattern, Array &$options = array() )
	{
		$criteria = array();
		$limit = false;
		$need_count = false;
		foreach(array('ts_project','ts_title','ts_description') as $col)
		{
			$criteria[$col] = $pattern;
		}
		if($options['start'] || $options['num_rows']) {
			$limit = array($options['start'], $options['num_rows']);
			$need_count = true;
		}
		$result = array();
		foreach((array) $this->search($criteria,false,'','','%',false,'OR', $limit, null, '', $need_count) as $ts )
		{
			if ($ts) $result[$ts['ts_id']] = $this->link_title($ts);
		}
		$options['total'] = $need_count ? $this->total : count($result);
		return $result;
	}

	/**
	 * Check access to the projects file store
	 *
	 * @param int $id id of entry
	 * @param int $check EGW_ACL_READ for read and EGW_ACL_EDIT for write or delete access
	 * @return boolean true if access is granted or false otherwise
	 */
	function file_access($id,$check,$rel_path)
	{
		return $this->check_acl($check,$id);
	}

	/**
	 * updates the project titles in the timesheet application (called whenever a project name is changed in the project manager)
	 *
	 * Todo: implement via notification
	 *
	 * @param string $oldtitle => the origin title of the project
	 * @param string $newtitle => the new title of the project
	 * @return boolean true for success, false for invalid parameters
	 */
	 function update_ts_project($oldtitle='', $newtitle='')
	 {
		if(strlen($oldtitle) > 0 && strlen($newtitle) > 0)
		{
			$this->db->update('egw_timesheet',array(
				'ts_project' => $newtitle,
				'ts_title' => $newtitle,
			),array(
				'ts_project' => $oldtitle,
			),__LINE__,__FILE__,TIMESHEET_APP);

			return true;
		}
		return false;
	 }

	/**
	 * returns array with relation link_id and ts_id (necessary for project-selection)
	 *
	 * @param int $pm_id ID of selected project
	 * @return array containing link_id and ts_id
	 */
	function get_ts_links($pm_id=0)
	{
		if($pm_id && isset($GLOBALS['egw_info']['user']['apps']['projectmanager']))
		{
			$pm_ids = ExecMethod('projectmanager.projectmanager_bo.children',$pm_id);
			$pm_ids[] = $pm_id;
			$links = solink::get_links('projectmanager',$pm_ids,'timesheet');	// solink::get_links not egw_links::get_links!
			if ($links)
			{
				$links = array_unique(call_user_func_array('array_merge',$links));
			}
			return $links;
		}
		return array();
	}

	/**
	 * receives notifications from the link-class: new, deleted links to timesheets, or updated content of linked entries
	 *
	 * Function makes sure timesheets linked or unlinked to projects via projectmanager behave like ones
	 * linked via timesheets project-selector, thought timesheet only stores project-title, not the id!
	 *
	 * @param array $data array with keys type, id, target_app, target_id, link_id, data
	 */
	function notify($data)
	{
		//error_log(__METHOD__.'('.array2string($data).')');
		$backup =& $this->data;	// backup internal data in case class got re-used by ExecMethod
		unset($this->data);

		if ($data['target_app'] == 'projectmanager' && $this->read($data['id']))
		{
			switch($data['type'])
			{
				case 'link':
				case 'update':
					if (empty($this->data['ts_project']))	// timesheet has not yet project set --> set just linked one
					{
						$pm_id = $data['target_id'];
					}
					break;

				case 'unlink':	// if current project got unlinked --> unset it
					if ($this->data['ts_project'] == projectmanager_bo::link_title($data['target_id']))
					{
						$pm_id = 0;
					}
					break;
			}
			if (isset($pm_id))
			{
				$ts_project = $pm_id ? egw_link::title('projectmanager', $pm_id) : null;
				$this->update(array('ts_project' => $ts_project));
				//error_log(__METHOD__."() setting pm_id=$pm_id --> ts_project=".array2string($ts_project));
			}
		}
		if ($backup) $this->data = $backup;
	}
}
