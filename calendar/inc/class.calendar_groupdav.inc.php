<?php
/**
 * EGroupware: CalDAV / GroupDAV access: calendar handler
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id: class.calendar_groupdav.inc.php 41996 2013-03-14 14:48:53Z ralfbecker $
 */

require_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';

/**
 * EGroupware: CalDAV / GroupDAV access: calendar handler
 *
 * Permanent error_log() calls should use $this->groupdav->log($str) instead, to be send to PHP error_log()
 * and our request-log (prefixed with "### " after request and response, like exceptions).
 *
 * @ToDo: new properties on calendars and it's ressources specially from sharing:
 * - for the invite property: 5.2.2 in https://trac.calendarserver.org/browser/CalendarServer/trunk/doc/Extensions/caldav-sharing.txt
 * - https://trac.calendarserver.org/browser/CalendarServer/trunk/doc/Extensions/caldav-schedulingchanges.txt
 */
class calendar_groupdav extends groupdav_handler
{
	/**
	 * bo class of the application
	 *
	 * @var calendar_boupdate
	 */
	var $bo;

	/**
	 * vCalendar Instance for parsing
	 *
	 * @var Horde_iCalendar
	 */
	var $vCalendar;

	var $filter_prop2cal = array(
		'SUMMARY' => 'cal_title',
		'UID' => 'cal_uid',
		'DTSTART' => 'cal_start',
		'DTEND' => 'cal_end',
		// 'DURATION'
		//'RRULE' => 'recur_type',
		//'RDATE' => 'cal_start',
		//'EXRULE'
		//'EXDATE'
		//'RECURRENCE-ID'
	);

	/**
	 * Does client understand exceptions to be included in VCALENDAR component of series master sharing its UID
	 *
	 * That also means no EXDATE for these exceptions!
	 *
	 * Setting it to false, should give the old behavior used in 1.6 (hopefully) no client needs that.
	 *
	 * @var boolean
	 */
	var $client_shared_uid_exceptions = true;

	/**
	 * Enable or disable Schedule-Tag handling:
	 * - return Schedule-Tag header in PUT response
	 * - update only status and alarms of calendar owner, if If-Schedule-Tag-Match header in PUT
	 *
	 * Disabling Schedule-Tag for iCal, as current implementation seems to create too much trouble :-(
	 * - iCal on OS X always uses If-Schedule-Tag-Match, even if other stuff in event is changed (eg. title)
	 * - iCal on iOS allways uses both If-Schedule-Tag-Match and If-Match (ETag)
	 * - Lighting 1.0 is NOT using it
	 *
	 * @var boolean
	 */
	var $use_schedule_tag = true;

	/**
	 * Are we using id, uid or caldav_name for the path/url
	 *
	 * Get's set in constructor to 'caldav_name' and groupdav_handler::$path_extension = ''!
	 */
	static $path_attr = 'id';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param groupdav $groupdav calling class
	 */
	function __construct($app, groupdav $groupdav)
	{
		parent::__construct($app, $groupdav);

		$this->bo = new calendar_boupdate();
		$this->vCalendar = new Horde_iCalendar;

		// since 1.9.003 we allow clients to specify the URL when creating a new event, as specified by CalDAV
		if (version_compare($GLOBALS['egw_info']['apps']['calendar']['version'], '1.8.004', '>='))
		{
			self::$path_attr = 'caldav_name';
			groupdav_handler::$path_extension = '';
		}
	}

	/**
	 * Create the path for an event
	 *
	 * @param array|int $event
	 * @return string
	 */
	function get_path($event)
	{
		if (is_numeric($event) && self::$path_attr == 'id')
		{
			$name = $event;
		}
		else
		{
			if (!is_array($event)) $event = $this->bo->read($event);
			$name = $event[self::$path_attr];
		}
		$name .= groupdav_handler::$path_extension;
		//error_log(__METHOD__.'('.array2string($event).") path_attr='".self::$path_attr."', path_extension='".groupdav_handler::$path_extension."' returning ".array2string($name));
		return $name;
	}

	/**
	 * Handle propfind in the calendar folder
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @param string $id=''
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function propfind($path,&$options,&$files,$user,$id='')
	{
		if ($this->debug)
		{
			error_log(__METHOD__."($path,".array2string($options).",,$user,$id)");
		}

		if ($options['root']['name'] == 'free-busy-query')
		{
			return $this->free_busy_report($path, $options, $user);
		}

		// ToDo: add parameter to only return id & etag
		$filter = array(
			'users' => $user,
			'start' => $this->bo->now - 100*24*3600,	// default one month back -30 breaks all sync recurrences
			'end' => $this->bo->now + 365*24*3600,	// default one year into the future +365
			'enum_recuring' => false,
			'daywise' => false,
			'date_format' => 'server',
			'no_total' => true,	// we need no total number of rows (saves extra query)
		);
		if ($this->client_shared_uid_exceptions)	// do NOT return (non-virtual) exceptions
		{
			$filter['query'] = array('cal_reference' => 0);
		}

		if ($path == '/calendar/')
		{
			$filter['filter'] = 'owner';
		}
		// scheduling inbox, shows only not yet accepted or rejected events
		elseif (substr($path,-7) == '/inbox/')
		{
			$filter['filter'] = 'unknown';
			$filter['start'] = $this->bo->now;	// only return future invitations
		}
		// ToDo: not sure what scheduling outbox is supposed to show, leave it empty for now
		elseif (substr($path,-8) == '/outbox/')
		{
			return true;
		}
		else
		{
			$filter['filter'] = 'default'; // not rejected
		}

		// process REPORT filters or multiget href's
		if (($id || $options['root']['name'] != 'propfind') && !$this->_report_filters($options,$filter,$id))
		{
			// return empty collection, as iCal under iOS 5 had problems with returning "404 Not found" status
			// when trying to request not supported components, eg. VTODO on a calendar collection
			return true;
		}
		if ($id) $path = dirname($path).'/';	// caldav_name get's added anyway in the callback

		if ($this->debug > 1)
		{
			error_log(__METHOD__."($path,,,$user,$id) filter=".array2string($filter));
		}

		// return iterator, calling ourself to return result in chunks
		$files['files'] = new groupdav_propfind_iterator($this,$path,$filter,$files['files']);

		return true;
	}

	/**
	 * Callback for profind interator
	 *
	 * @param string $path
	 * @param array $filter
	 * @param array|boolean $start=false false=return all or array(start,num)
	 * @return array with "files" array with values for keys path and props
	 */
	function propfind_callback($path,array $filter,$start=false)
	{
		if ($this->debug) $starttime = microtime(true);

		$calendar_data = $this->groupdav->prop_requested('calendar-data', groupdav::CALDAV, true);
		if (!is_array($calendar_data)) $calendar_data = false;	// not in allprop or autoindex

		$files = array();

		if (is_array($start))
		{
			$filter['offset'] = $start[0];
			$filter['num_rows'] = $start[1];
		}
		$events =& $this->bo->search($filter);
		if ($events)
		{
			foreach($events as $event)
			{
				$etag = $this->get_etag($event, $schedule_tag);

				//header('X-EGROUPWARE-EVENT-'.$event['id'].': '.$event['title'].': '.date('Y-m-d H:i:s',$event['start']).' - '.date('Y-m-d H:i:s',$event['end']));
				$props = array(
					'getcontenttype' => $this->agent != 'kde' ? 'text/calendar; charset=utf-8; component=VEVENT' : 'text/calendar',
					'getetag' => '"'.$etag.'"',
					'getlastmodified' => max($event['modified'], $event['max_user_modified']),
					// user and timestamp of creation or last modification of event, used in calendarserver only for shared calendars
					'created-by' => HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER, 'created-by',
						$this->_created_updated_by_prop($event['creator'], $event['created'])),
					'updated-by' => HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER, 'updated-by',
						$this->_created_updated_by_prop($event['modifier'], $event['modified'])),
				);
				if ($this->use_schedule_tag)
				{
					$props['schedule-tag'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV, 'schedule-tag', '"'.$schedule_tag.'"');
				}
				//error_log(__FILE__ . __METHOD__ . "Calendar Data : $calendar_data");
				if ($calendar_data)
				{
					$content = $this->iCal($event, $filter['users'],
						strpos($path, '/inbox/') !== false ? 'REQUEST' : null,
						!isset($calendar_data['children']['expand']) ? false :
							($calendar_data['children']['expand']['attrs'] ? $calendar_data['children']['expand']['attrs'] : true));
					$props['getcontentlength'] = bytes($content);
					$props['calendar-data'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data',$content);
				}
				/* Calendarserver reports new events with schedule-changes: action: create, which iCal request
				 * adding it, unfortunately does not lead to showing the new event in the users inbox
				if (strpos($path, '/inbox/') !== false && $this->groupdav->prop_requested('schedule-changes'))
				{
					$props['schedule-changes'] = HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'schedule-changes',array(
						HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'dtstamp',gmdate('Ymd\THis',$event['created']).'Z'),
						HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'action',array(
							HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER,'create',''),
						)),
					));
				}*/
				$files[] = $this->add_resource($path, $event, $props);
			}
		}
		if ($this->debug)
		{
			error_log(__METHOD__."($path) took ".(microtime(true) - $starttime).
				' to return '.count($files['files']).' items');
		}
		return $files;
	}

	/**
	 * Return Calendarserver:(created|updated)-by sub-properties for a given user and time
	 *
	 * <created-by xmlns='http://calendarserver.org/ns/'>
	 *  <first-name>Ralf</first-name>
	 *  <last-name>Becker</last-name>
	 *  <dtstamp>20121002T092006Z</dtstamp>
	 *  <href xmlns='DAV:'>mailto:farktronix@me.com</href>
	 * </created-by>
	 *
	 * @param int $user
	 * @param int $time
	 * @return array with subprops
	 */
	private function _created_updated_by_prop($user, $time)
	{
		$props = array();
		foreach(array(
			'first-name' => 'account_firstname',
			'last-name' => 'account_lastname',
			'href' => 'account_email',
		) as $prop => $name)
		{
			if ($user && ($val = $this->accounts->id2name($user, $name)))
			{
				$ns = groupdav::CALENDARSERVER;
				if ($prop == 'href')
				{
					$ns = '';
					$val = 'mailto:'.$val;
				}
				$props[$prop] = $ns ? HTTP_WebDAV_Server::mkprop($ns, $prop, $val) : HTTP_WebDAV_Server::mkprop($prop, $val);
			}
		}
		if ($time)
		{
			$props['dtstamp'] = HTTP_WebDAV_Server::mkprop(groupdav::CALENDARSERVER, 'dtstamp', gmdate('Ymd\\This\\Z', $time));
		}
		//error_log(__METHOD__."($user, $time) returning ".array2string($props));
		return $props ? $props : '';
	}

	/**
	 * Process the filters from the CalDAV REPORT request
	 *
	 * @param array $options
	 * @param array &$cal_filters
	 * @param string $id
	 * @return boolean true if filter could be processed, false for requesting not here supported VTODO items
	 */
	function _report_filters($options,&$cal_filters,$id)
	{
		if ($options['filters'])
		{
			// unset default start & end
			$cal_start = $cal_filters['start']; unset($cal_filters['start']);
			$cal_end = $cal_filters['end']; unset($cal_filters['end']);
			$num_filters = count($cal_filters);

			foreach($options['filters'] as $filter)
			{
				switch($filter['name'])
				{
					case 'comp-filter':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) comp-filter='{$filter['attrs']['name']}'");

						switch($filter['attrs']['name'])
						{
							case 'VTODO':
								return false;	// return nothing for now, todo: check if we can pass it on to the infolog handler
								// todos are handled by the infolog handler
								//$infolog_handler = new groupdav_infolog();
								//return $infolog_handler->propfind($options['path'],$options,$options['files'],$user,$method);
							case 'VCALENDAR':
							case 'VEVENT':
								break;			// that's our default anyway
						}
						break;
					case 'prop-filter':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) prop-filter='{$filter['attrs']['name']}'");
						$prop_filter = $filter['attrs']['name'];
						break;
					case 'text-match':
						if ($this->debug > 1) error_log(__METHOD__."($options[path],...) text-match: $prop_filter='{$filter['data']}'");
						if (!isset($this->filter_prop2cal[strtoupper($prop_filter)]))
						{
							if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown property '$prop_filter' --> ignored");
						}
						else
						{
							$cal_filters['query'][$this->filter_prop2cal[strtoupper($prop_filter)]] = $filter['data'];
						}
						unset($prop_filter);
						break;
					case 'param-filter':
						if ($this->debug) error_log(__METHOD__."($options[path],...) param-filter='{$filter['attrs']['name']}' not (yet) implemented!");
						break;
					case 'time-range':
				 		if ($this->debug > 1) error_log(__FILE__ . __METHOD__."($options[path],...) time-range={$filter['attrs']['start']}-{$filter['attrs']['end']}");
				 		if (!empty($filter['attrs']['start']))
				 		{
					 		$cal_filters['start'] = $this->vCalendar->_parseDateTime($filter['attrs']['start']);
				 		}
				 		if (!empty($filter['attrs']['end']))
				 		{
					 		$cal_filters['end']   = $this->vCalendar->_parseDateTime($filter['attrs']['end']);
				 		}
						break;
					default:
						if ($this->debug) error_log(__METHOD__."($options[path],".array2string($options).",...) unknown filter --> ignored");
						break;
				}
			}
			if (count($cal_filters) == $num_filters)	// no filters set --> restore default start and end time
			{
				$cal_filters['start'] = $cal_start;
				$cal_filters['end']   = $cal_end;
			}
		}

		// multiget or propfind on a given id
		//error_log(__FILE__ . __METHOD__ . "multiget of propfind:");
		if ($options['root']['name'] == 'calendar-multiget' || $id)
		{
			// no standard time-range!
			unset($cal_filters['start']);
			unset($cal_filters['end']);

			$ids = array();

			if ($id)
			{
				$cal_filters['query'][self::$path_attr] = groupdav_handler::$path_extension ?
					basename($id,groupdav_handler::$path_extension) : $id;
			}
			else	// fetch all given url's
			{
				foreach($options['other'] as $option)
				{
					if ($option['name'] == 'href')
					{
						$parts = explode('/',$option['data']);
						if (($id = array_pop($parts)))
						{
							$cal_filters['query'][self::$path_attr][] = groupdav_handler::$path_extension ?
								basename($id,groupdav_handler::$path_extension) : $id;
						}
					}
				}
			}

			if ($this->debug > 1) error_log(__FILE__ . __METHOD__ ."($options[path],...,$id) calendar-multiget: ids=".implode(',',$ids).', cal_filters='.array2string($cal_filters));
		}
		return true;
	}

	/**
	 * Handle get request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function get(&$options,$id,$user=null)
	{
		if (!is_array($event = $this->_common_get_put_delete('GET',$options,$id)))
		{
			return $event;
		}

		$options['data'] = $this->iCal($event, $user, strpos($options['path'], '/inbox/') !== false ? 'REQUEST' : null);
		$options['mimetype'] = 'text/calendar; charset=utf-8';
		header('Content-Encoding: identity');
		header('ETag: "'.$this->get_etag($event, $schedule_tag).'"');
		if ($this->use_schedule_tag)
		{
			header('Schedule-Tag: "'.$schedule_tag.'"');
		}
		return true;
	}

	/**
	 * Generate an iCal for the given event
	 *
	 * Taking into account virtual an real exceptions for recuring events
	 *
	 * @param array $event
	 * @param int $user=null account_id of calendar to display
	 * @param string $method=null eg. 'PUBLISH' for inbox, nothing anywhere else
	 * @param boolean|array $expand=false true or array with values for 'start', 'end' to expand recurrences
	 * @return string
	 */
	private function iCal(array $event,$user=null, $method=null, $expand=false)
	{
		static $handler = null;
		if (is_null($handler)) $handler = $this->_get_handler();

		if (!$user) $user = $GLOBALS['egw_info']['user']['account_id'];

		// only return alarms in own calendar, not other users calendars
		if ($user != $GLOBALS['egw_info']['user']['account_id'])
		{
			//error_log(__METHOD__.'('.array2string($event).", $user) clearing alarms");
			$event['alarm'] = array();
		}

		$events = array($event);

		// for recuring events we have to add the exceptions
		if ($this->client_shared_uid_exceptions && $event['recur_type'] && !empty($event['uid']))
		{
			if (is_array($expand))
			{
				if (isset($expand['start'])) $expand['start'] = $this->vCalendar->_parseDateTime($expand['start']);
				if (isset($expand['end'])) $expand['end'] = $this->vCalendar->_parseDateTime($expand['end']);
			}
			$events =& self::get_series($event['uid'], $this->bo, $expand);
		}
		elseif(!$this->client_shared_uid_exceptions && $event['reference'])
		{
			$events[0]['uid'] .= '-'.$event['id'];	// force a different uid
		}
		return $handler->exportVCal($events, '2.0', $method);
	}

	/**
	 * Get array with events of a series identified by its UID (master and all exceptions)
	 *
	 * Maybe that should be part of calendar_bo
	 *
	 * @param string $uid UID
	 * @param calendar_bo $bo=null calendar_bo object to reuse for search call
	 * @param boolean|array $expand=false true or array with values for 'start', 'end' to expand recurrences
	 * @return array
	 */
	private static function &get_series($uid,calendar_bo $bo=null, $expand=false)
	{
		if (is_null($bo)) $bo = new calendar_bopdate();

		if (!($masterId = array_shift($bo->find_event(array('uid' => $uid), 'master')))
				|| !($master = $bo->read($masterId, 0, false, 'server')))
		{
			return array(); // should never happen
		}
		$exceptions =& $master['recur_exception'];

		$params = array(
			'query' => array('cal_uid' => $uid),
			'filter' => 'owner',  // return all possible entries
			'daywise' => false,
			'date_format' => 'server',
		);
		if (is_array($expand)) $params += $expand;

		$events =& $bo->search($params);

		foreach($events as $k => &$recurrence)
		{
			//error_log(__FILE__.'['.__LINE__.'] '.__METHOD__."($uid)[$k]:" . array2string($recurrence));
			if ($recurrence['id'] != $master['id'])	// real exception
			{
				//error_log('real exception: '.array2string($recurrence));
				// remove from masters recur_exception, as exception is include
				// at least Lightning "understands" EXDATE as exception from what's included
				// in the whole resource / VCALENDAR component
				// not removing it causes Lightning to remove the exception itself
				if (($e = array_search($recurrence['recurrence'],$exceptions)) !== false)
				{
					unset($exceptions[$e]);
				}
				continue;	// nothing to change
			}
			// now we need to check if this recurrence is an exception
			if (!$expand && $master['participants'] == $recurrence['participants'])
			{
				//error_log('NO exception: '.array2string($recurrence));
				unset($events[$k]);	// no exception --> remove it
				continue;
			}
			// this is a virtual exception now (no extra event/cal_id in DB)
			//error_log('virtual exception: '.array2string($recurrence));
			$recurrence['recurrence'] = $recurrence['start'];
			$recurrence['reference'] = $master['id'];
			$recurrence['recur_type'] = MCAL_RECUR_NONE;	// is set, as this is a copy of the master
			// not for included exceptions (Lightning): $master['recur_exception'][] = $recurrence['start'];
		}
		if (!$expand)
		{
			$events = array_merge(array($master), $events);
		}
		return $events;
	}

	/**
	 * Handle put request for an event
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @param string $prefix=null user prefix from path (eg. /ralf from /ralf/addressbook)
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function put(&$options,$id,$user=null,$prefix=null)
	{
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));

		if (!$prefix) $user = null;	// /infolog/ does not imply setting the current user (for new entries it's done anyway)

		// fix for iCal4OL using WinHTTP only supporting a certain header length
		if (isset($_SERVER['HTTP_IF_SCHEDULE']) && !isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']))
		{
			$_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH'] = $_SERVER['HTTP_IF_SCHEDULE'];
		}
		$return_no_access = true;	// as handled by importVCal anyway and allows it to set the status for participants
		$oldEvent = $this->_common_get_put_delete('PUT',$options,$id,$return_no_access,
			isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']));	// dont fail with 412 Precondition Failed in that case
		if (!is_null($oldEvent) && !is_array($oldEvent))
		{
			if ($this->debug) error_log(__METHOD__.': '.print_r($oldEvent,true).function_backtrace());
			return $oldEvent;
		}

		if (is_null($oldEvent) && ($user >= 0 && !$this->bo->check_perms(EGW_ACL_ADD, 0, $user) ||
			// if we require an extra invite grant, we fail if that does not exist (bind privilege is not given in that case)
			$this->bo->require_acl_invite && $user && $user != $GLOBALS['egw_info']['user']['account_id'] &&
				!$this->bo->check_acl_invite($user)))
		{
			// we have no add permission on this user's calendar
			// ToDo: create event in current users calendar and invite only $user
			if ($this->debug) error_log(__METHOD__."(,,$user) we have not enough rights on this calendar");
			return '403 Forbidden';
		}

		$handler = $this->_get_handler();
		$vCalendar = htmlspecialchars_decode($options['content']);
		$charset = null;
		if (!empty($options['content_type']))
		{
			$content_type = explode(';', $options['content_type']);
			if (count($content_type) > 1)
			{
				array_shift($content_type);
				foreach ($content_type as $attribute)
				{
					trim($attribute);
					list($key, $value) = explode('=', $attribute);
					switch (strtolower($key))
					{
						case 'charset':
							$charset = strtoupper(substr($value,1,-1));
					}
				}
			}
		}

		if (is_array($oldEvent))
		{
			$eventId = $oldEvent['id'];

			//client specified a CalDAV Scheduling schedule-tag AND an etag If-Match precondition
			if ($this->use_schedule_tag && isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']) &&
				isset($_SERVER['HTTP_IF_MATCH']))
			{
				if ($oldEvent['owner'] == $GLOBALS['egw_info']['user']['account_id'])
				{
					$this->groupdav->log("Both If-Match and If-Schedule-Tag-Match header given: If-Schedule-Tag-Match ignored for event owner!");
					unset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']);
				}
				else
				{
					$this->groupdav->log("Both If-Match and If-Schedule-Tag-Match header given: If-Schedule-Tag-Match takes precedence for participants!");
				}
			}
			//client specified a CalDAV Scheduling schedule-tag precondition
			if ($this->use_schedule_tag && isset($_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH']))
			{
				$schedule_tag_match = $_SERVER['HTTP_IF_SCHEDULE_TAG_MATCH'];
				if ($schedule_tag_match[0] == '"') $schedule_tag_match = substr($schedule_tag_match, 1, -1);
				$this->get_etag($oldEvent, $schedule_tag);

				if ($schedule_tag_match !== $schedule_tag)
				{
					if ($this->debug) error_log(__METHOD__."(,,$user) schedule_tag missmatch: given '$schedule_tag_match' != '$schedule_tag'");
					return '412 Precondition Failed';
				}
			}
			// if no edit-rights (aka no organizer), update only attendee stuff: status and alarms
			if (!$this->check_access(EGW_ACL_EDIT, $oldEvent))
			{
				$user_and_memberships = $GLOBALS['egw']->accounts->memberships($user, true);
				$user_and_memberships[] = $user;
				if (!array_intersect(array_keys($oldEvent['participants']), $user_and_memberships))
				{
					if ($this->debug) error_log(__METHOD__."(,,$user) user $user is NOT an attendee!");
					return '403 Forbidden';
				}
				// update only participant status and alarms of current user
				if (($events = $handler->icaltoegw($vCalendar)))
				{
					$modified = 0;
					foreach($events as $n => $event)
					{
						// for recurrances of event series, we need to read correct recurrence (or if series master is no first event)
						if ($event['recurrence'] || $n && !$event['recurrence'])
						{
							// first try reading (virtual and real) exceptions
							if (!isset($series))
							{
								$series = self::get_series($event['uid'], $this->bo);
								//foreach($series as $s => $sEvent) error_log("series[$s]: ".array2string($sEvent));
							}
							foreach($series as $oldEvent)
							{
								if ($oldEvent['recurrence'] == $event['recurrence']) break;
							}
							// if no exception found, check if it might be just a recurrence (no exception)
							if ($oldEvent['recurrence'] != $event['recurrence'])
							{
								if (!($oldEvent = $this->bo->read($eventId, $event['recurrence'], true)) ||
									// virtual exceptions have recurrence=0 and recur_date=recurrence (series master or real exceptions have recurence=0)
									!($oldEvent['recur_date'] == $event['recurrence'] || !$event['recurrence'] && !$oldEvent['recurrence']))
								{
									// if recurrence not found --> log it and continue with other recurrence
									$this->groupdav->log(__METHOD__."(,,$user) could NOT find recurrence=$event[recurrence]=".egw_time::to($event['recurrence']).' of event series! event='.array2string($event));
									continue;
								}
							}
						}
						if ($this->debug) error_log(__METHOD__."(, $id, $user, '$prefix') eventId=$eventId ($oldEvent[id]), user=$user, old-status='{$oldEvent['participants'][$user]}', new-status='{$event['participants'][$user]}', recurrence=$event[recurrence]=".egw_time::to($event['recurrence']).", event=".array2string($event));
						if (isset($event['participants']) && $event['participants'][$user] !== $oldEvent['participants'][$user])
						{
							if (!$this->bo->set_status($oldEvent['id'], $user, $event['participants'][$user],
								// real (not virtual) exceptions use recurrence 0 in egw_cal_user.cal_recurrence!
								$recurrence = $eventId == $oldEvent['id'] ? $event['recurrence'] : 0))
							{
								if ($this->debug) error_log(__METHOD__."(,,$user) failed to set_status($oldEvent[id], $user, '{$event['participants'][$user]}', $recurrence=".egw_time::to($recurrence).')');
								return '403 Forbidden';
							}
							else
							{
								++$modified;
								if ($this->debug) error_log(__METHOD__."() set_status($oldEvent[id], $user, {$event['participants'][$user]} , $recurrence=".egw_time::to($recurrence).')');
							}
						}
						// import alarms, if given and changed
						if ((array)$event['alarm'] !== (array)$oldEvent['alarm'])
						{
							$modified += $this->sync_alarms($oldEvent['id'], (array)$event['alarm'], (array)$oldEvent['alarm'], $user, $event['start']);
						}
					}
					if (!$modified)	// NO modififictions, or none we understood --> log it and return Ok: "204 No Content"
					{
						$this->groupdav->log(__METHOD__."(,,$user) schedule-tag given, but NO changes for current user events=".array2string($events).', old-event='.array2string($oldEvent));
					}
					$this->put_response_headers($eventId, $options['path'], '204 No Content', self::$path_attr == 'caldav_name');

					return '204 No Content';
				}
				if ($this->debug && !isset($events)) error_log(__METHOD__."(,,$user) only schedule-tag given for event without participants (only calendar owner) --> handle as regular PUT");
			}
			if ($return_no_access)
			{
				$retval = true;
			}
			else
			{
				$retval = '204 No Content';

				// lightning will pop up the alarm, as long as the Sequence (etag) does NOT change
				// --> update the etag alone, if user has no edit rights
				if ($this->agent == 'lightning' && !$this->check_access(EGW_ACL_EDIT, $oldEvent) &&
					isset($oldEvent['participants'][$GLOBALS['egw_info']['user']['account_id']]))
				{
					// just update etag in database
					$GLOBALS['egw']->db->update($this->bo->so->cal_table,'cal_etag=cal_etag+1',array(
						'cal_id' => $eventId,
					),__LINE__,__FILE__,'calendar');
				}
			}
		}
		else
		{
			// new entry
			$eventId = -1;
			$retval = '201 Created';
		}

		if (!($cal_id = $handler->importVCal($vCalendar, $eventId,
			self::etag2value($this->http_if_match), false, 0, $this->groupdav->current_user_principal, $user, $charset, $id)))
		{
			if ($this->debug) error_log(__METHOD__."(,$id) eventId=$eventId: importVCal('$options[content]') returned ".array2string($cal_id));
			if ($eventId && $cal_id === false)
			{
				// ignore import failures
				$cal_id = $eventId;
				$retval = true;
			}
			elseif ($cal_id === 0)	// etag failure
			{
				return '412 Precondition Failed';
			}
			else
			{
				return '403 Forbidden';
			}
		}

		// send evtl. necessary respose headers: Location, etag, ...
		$this->put_response_headers($cal_id, $options['path'], $retval, self::$path_attr == 'caldav_name');

		return $retval;
	}

	/**
	 * Sync alarms of current user: add alarms added on client and remove the ones removed
	 *
	 * @param int $cal_id of event to set alarms
	 * @param array $alarms
	 * @param array $old_alarms
	 * @param int $user account_id of user to create alarm for
	 * @param int $start start-time of event
	 * @ToDo store other alarm properties like: ACTION, DESCRIPTION, X-WR-ALARMUID
	 * @return int number of modified alarms
	 */
	private function sync_alarms($cal_id, array $alarms, array $old_alarms, $user, $start)
	{
		if ($this->debug) error_log(__METHOD__."($cal_id, ".array2string($alarms).', '.array2string($old_alarms).", $user, $start)");
		$modified = 0;
		foreach($alarms as $alarm)
		{
			if ($alarm['owner'] != $this->user) continue;	// only import alarms of current user

			// check if alarm is already stored or from other users
			foreach($old_alarms as $id => $old_alarm)
			{
				if ($old_alarm['owner'] != $user || $alarm['offset'] == $old_alarm['offset'])
				{
					unset($old_alarms[$id]);	// remove alarms of other user, or already existing alarms
				}
			}
			// alarm not found --> add it
			if ($alarm['offset'] != $old_alarm['offset'] || $old_alarm['owner'] != $user)
			{
				$alarm['owner'] = $user;
				$alarm['time'] = $start - $alarm['offset'];
				if ($this->debug) error_log(__METHOD__."() adding new alarm from client ".array2string($alarm));
				$this->bo->save_alarm($cal_id, $alarm);
				++$modified;
			}
		}
		// remove all old alarms left from current user
		foreach($old_alarms as $id => $old_alarm)
		{
			if ($this->debug) error_log(__METHOD__."() deleting alarm '$id' deleted on client ".array2string($old_alarm));
			$this->bo->delete_alarm($id);
			++$modified;
		}
		return $modified;
	}

	/**
	 * Handle post request for a schedule entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function post(&$options,$id,$user=null)
	{
		if ($this->debug) error_log(__METHOD__."($id, $user)".print_r($options,true));

		$vCalendar = htmlspecialchars_decode($options['content']);
		$charset = null;
		if (!empty($options['content_type']))
		{
			$content_type = explode(';', $options['content_type']);
			if (count($content_type) > 1)
			{
				array_shift($content_type);
				foreach ($content_type as $attribute)
				{
					trim($attribute);
					list($key, $value) = explode('=', $attribute);
					switch (strtolower($key))
					{
						case 'charset':
							$charset = strtoupper(substr($value,1,-1));
					}
				}
			}
		}

		if (substr($options['path'],-8) == '/outbox/')
		{
			if (preg_match('/^METHOD:REQUEST(\r\n|\r|\n)(.*)^BEGIN:VFREEBUSY/ism', $vCalendar))
			{
				if ($user != $GLOBALS['egw_info']['user']['account_id'])
				{
					error_log(__METHOD__."() freebusy request only allowed to own outbox!");
					return '403 Forbidden';
				}
				// do freebusy request
				return $this->outbox_freebusy_request($vCalendar, $charset, $user, $options);
			}
			else
			{
				// POST to deliver an invitation, containing http headers:
				// Originator: mailto:<organizer-email>
				// Recipient: mailto:<attendee-email>
				// --> currently we simply ignore these posts, as EGroupware does it's own notifications based on user preferences
				return '204 No Content';
			}
		}
		if (preg_match('/^METHOD:(PUBLISH|REQUEST)(\r\n|\r|\n)(.*)^BEGIN:VEVENT/ism', $options['content']))
		{
			$handler = $this->_get_handler();
			if (($foundEvents = $handler->search($vCalendar, null, false, $charset)))
			{
				$eventId = array_shift($foundEvents);
				list($eventId) = explode(':', $eventId);

				if (!($cal_id = $handler->importVCal($vCalendar, $eventId, null,
					false, 0, $this->groupdav->current_user_principal, $user, $charset)))
				{
					if ($this->debug) error_log(__METHOD__."() importVCal($eventId) returned false");
				}
				// we should not return an etag here, as we never store the ical byte-by-byte
				//header('ETag: "'.$this->get_etag($eventId).'"');
			}
		}
		return true;
	}

	/**
	 * Handle outbox freebusy request
	 *
	 * @param string $ical
	 * @param string $charset of ical
	 * @param int $user account_id of owner
	 * @param array &$options
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	protected function outbox_freebusy_request($ical, $charset, $user, array &$options)
	{
		include_once EGW_SERVER_ROOT.'/phpgwapi/inc/horde/lib/core.php';
		$vcal = new Horde_iCalendar();
		if (!$vcal->parsevCalendar($ical, 'VCALENDAR', $charset))
		{
			return '400 Bad request';
		}
		$version = $vcal->getAttribute('VERSION');

		//echo $ical."\n";

		$handler = $this->_get_handler();
		$handler->setSupportedFields('groupdav');
		$handler->calendarOwner = $handler->user = 0;	// to NOT default owner/organizer to something
		if (!($component = $vcal->getComponent(0)) ||
			!($event = $handler->vevent2egw($component, $version, $handler->supportedFields, $this->groupdav->current_user_principal, 'Horde_iCalendar_vfreebusy')))
		{
			return '400 Bad request';
		}
		if ($event['owner'] != $user)
		{
			$this->groupdav->log(__METHOD__."('$ical',,$user) ORGANIZER is NOT principal!");
			return '403 Forbidden';
		}
		//print_r($event);
		$organizer = $component->getAttribute('ORGANIZER');
		$attendees = (array)$component->getAttribute('ATTENDEE');
		// X-CALENDARSERVER-MASK-UID specifies to exclude given event from busy-time
		$mask_uid = $component->getAttribute('X-CALENDARSERVER-MASK-UID');

		header('Content-type: text/xml; charset=UTF-8');

		$xml = new XMLWriter;
		$xml->openMemory();
		$xml->setIndent(true);
		$xml->startDocument('1.0', 'UTF-8');
		$xml->startElementNs('C', 'schedule-response', groupdav::CALDAV);

		foreach($event['participants'] as $uid => $status)
		{
			$xml->startElementNs('C', 'response', null);

			$xml->startElementNs('C', 'recipient', null);
			$xml->writeElementNs('D', 'href', 'DAV:', $attendee=array_shift($attendees));
			$xml->endElement();	// recipient

			$xml->writeElementNs('C', 'request-status', null, '2.0;Success');
			$xml->writeElementNs('C', 'calendar-data', null,
				$handler->freebusy($uid, $event['end'], true, 'utf-8', $event['start'], 'REPLY', array(
					'UID' => $event['uid'],
					'ORGANIZER' => $organizer,
					'ATTENDEE' => $attendee,
				)+(empty($mask_uid) || !is_string($mask_uid) ? array() : array(
					'X-CALENDARSERVER-MASK-UID' => $mask_uid,
				))));

			$xml->endElement();	// response
		}
		$xml->endElement();	// schedule-response
		$xml->endDocument();
		echo $xml->outputMemory();

		return true;
	}

	/**
	 * Handle free-busy-query report
	 *
	 * @param string $path
	 * @param array $options
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function free_busy_report($path,$options,$user)
	{
		if (!$this->bo->check_perms(EGW_ACL_FREEBUSY, 0, $user))
		{
			return '403 Forbidden';
		}
		foreach($options['other'] as $filter)
		{
			if ($filter['name'] == 'time-range')
			{
				$start = $this->vCalendar->_parseDateTime($filter['attrs']['start']);
				$end = $this->vCalendar->_parseDateTime($filter['attrs']['end']);
			}
		}
		$handler = $this->_get_handler();
		header('Content-Type: text/calendar');
		echo $handler->freebusy($user, $end, true, 'utf-8', $start, 'REPLY', array());

		common::egw_exit();	// otherwise we get a 207 multistatus, not 200 Ok
	}

	/**
	 * Return priviledges for current user, default is read and read-current-user-privilege-set
	 *
	 * Reimplemented to add read-free-busy and schedule-deliver privilege
	 *
	 * @param string $path path of collection
	 * @param int $user=null owner of the collection, default current user
	 * @return array with privileges
	 */
	public function current_user_privileges($path, $user=null)
	{
		$privileges = parent::current_user_privileges($path, $user);
		//error_log(__METHOD__."('$path', $user) parent gave ".array2string($privileges));

		if ($this->bo->check_perms(EGW_ACL_FREEBUSY, 0, $user))
		{
			$privileges['read-free-busy'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV, 'read-free-busy', '');

			if (substr($path, -8) == '/outbox/' && $this->bo->check_acl_invite($user))
			{
				$privileges['schedule-send'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV, 'schedule-send', '');
			}
		}
		if (substr($path, -7) == '/inbox/' && $this->bo->check_acl_invite($user))
		{
			$privileges['schedule-deliver'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV, 'schedule-deliver', '');
		}
		// remove bind privilege on other users or groups calendars, if calendar config require_acl_invite is set
		// and current user has no invite grant
		if ($user && $user != $GLOBALS['egw_info']['user']['account_id'] && isset($privileges['bind']) &&
			!$this->bo->check_acl_invite($user))
		{
			unset($privileges['bind']);
		}
		//error_log(__METHOD__."('$path', $user) returning ".array2string($privileges));
		return $privileges;
	}

	/**
	 * Fix event series with exceptions, called by calendar_ical::importVCal():
	 *	a) only series master = first event got cal_id from URL
	 *	b) exceptions need to be checked if they are already in DB or new
	 *	c) recurrence-id of (real not virtual) exceptions need to be re-added to master
	 *
	 * @param array &$events
	 */
	static function fix_series(array &$events)
	{
		$bo = new calendar_boupdate();

		// get array with orginal recurrences indexed by recurrence-id
		$org_recurrences = $exceptions = array();
		foreach(self::get_series($events[0]['uid'],$bo) as $k => $event)
		{
			if (!$k) $master = $event;
			if ($event['recurrence'])
			{
				$org_recurrences[$event['recurrence']] = $event;
			}
		}

		// assign cal_id's to already existing recurrences and evtl. re-add recur_exception to master
		foreach($events as $k => &$recurrence)
		{
			if (!$recurrence['recurrence'])
			{
				// master
				$recurrence['id'] = $master['id'];
				$master =& $events[$k];
				continue;
			}

			// from now on we deal with exceptions
			$org_recurrence = $org_recurrences[$recurrence['recurrence']];
			if (isset($org_recurrence))	// already existing recurrence
			{
				//error_log(__METHOD__.'() setting id #'.$org_recurrence['id']).' for '.$recurrence['recurrence'].' = '.date('Y-m-d H:i:s',$recurrence['recurrence']);
				$recurrence['id'] = $org_recurrence['id'];

				// re-add (non-virtual) exceptions to master's recur_exception
				if ($recurrence['id'] != $master['id'])
				{
					//error_log(__METHOD__.'() re-adding recur_exception '.$recurrence['recurrence'].' = '.date('Y-m-d H:i:s',$recurrence['recurrence']));
					$exceptions[] = $recurrence['recurrence'];
				}
				// remove recurrence to be able to detect deleted exceptions
				unset($org_recurrences[$recurrence['recurrence']]);
			}
		}
		$master['recur_exception'] = array_merge($exceptions, $master['recur_exception']);

		// delete not longer existing recurrences
		foreach($org_recurrences as $org_recurrence)
		{
			if ($org_recurrence['id'] != $master['id'])	// non-virtual recurrence
			{
				//error_log(__METHOD__.'() deleting #'.$org_recurrence['id']);
				$bo->delete($org_recurrence['id']);	// might fail because of permissions
			}
			else	// virtual recurrence
			{
				//error_log(__METHOD__.'() delete virtual exception '.$org_recurrence['recurrence'].' = '.date('Y-m-d H:i:s',$org_recurrence['recurrence']));
				$bo->update_status($master, $org_recurrence, $org_recurrence['recurrence']);
			}
		}
		//foreach($events as $n => $event) error_log(__METHOD__." $n after: ".array2string($event));
	}

	/**
	 * Handle delete request for an event
	 *
	 * If current user has no right to delete the event, but is an attendee, we reject the event for him.
	 *
	 * @todo remove (non-virtual) exceptions, if series master gets deleted
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	function delete(&$options,$id)
	{
		if (strpos($options['path'], '/inbox/') !== false)
		{
			return true;	// simply ignore DELETE in inbox for now
		}
		$return_no_access = true;	// to allow to check if current use is a participant and reject the event for him
		if (!is_array($event = $this->_common_get_put_delete('DELETE',$options,$id,$return_no_access)) || !$return_no_access)
		{
 			if (!$return_no_access)
			{
				// check if user is a participant or one of the groups he is a member of --> reject the meeting request
				$ret = '403 Forbidden';
				$memberships = $GLOBALS['egw']->accounts->memberships($this->bo->user, true);
				foreach($event['participants'] as $uid => $status)
				{
					if ($this->bo->user == $uid || in_array($uid, $memberships))
					{
						$this->bo->set_status($event,$this->bo->user, 'R');
						$ret = true;
						break;
					}
				}
			}
			else
			{
				$ret = $event;
			}
		}
		else
		{
			$ret = $this->bo->delete($event['id']);
		}
		if ($this->debug) error_log(__METHOD__."(,$id) return_no_access=$return_no_access, event[participants]=".array2string(is_array($event)?$event['participants']:null).", user={$this->bo->user} --> return ".array2string($ret));
		return $ret;
	}

	/**
	 * Read an entry
	 *
	 * We have to make sure to not return or even consider in read deleted events, as the might have
	 * the same UID and/or caldav_name as not deleted events and would block access to valid entries
	 *
	 * @param string|id $id
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	function read($id)
	{
		if (strpos($column=self::$path_attr,'_') === false) $column = 'cal_'.$column;

		$event = $this->bo->read(array($column => $id, 'cal_deleted IS NULL', 'cal_reference=0'), null, true, 'server');
		if ($event) $event = array_shift($event);	// read with array as 1. param, returns an array of events!

		if (!($retval = $this->bo->check_perms(EGW_ACL_FREEBUSY,$event, 0, 'server')))
		{
			if ($this->debug > 0) error_log(__METHOD__."($id) no READ or FREEBUSY rights returning ".array2string($retval));
			return $retval;
		}
		if (!$this->bo->check_perms(EGW_ACL_READ, $event, 0, 'server'))
		{
			$this->bo->clear_private_infos($event, array($this->bo->user, $event['owner']));
		}
		// handle deleted events, as not existing
		if ($event['deleted']) $event = null;

		if ($this->debug > 1) error_log(__METHOD__."($id) returning ".array2string($event));

		return $event;
	}

	/**
	 * Query ctag for calendar
	 *
	 * @return string
	 */
	public function getctag($path,$user)
	{
		$ctag = $this->bo->get_ctag($user,$path == '/calendar/' ? 'owner' : 'default'); // default = not rejected

		if ($this->debug > 1) error_log(__FILE__.'['.__LINE__.'] '.__METHOD__. "($path)[$user] = $ctag");

		return $ctag;
	}

	/**
	 * Get the etag for an entry
	 *
	 * @param array|int $event array with event or cal_id
	 * @return string|boolean string with etag or false
	 */
	function get_etag($entry, &$schedule_tag=null)
	{
		$etag = $this->bo->get_etag($entry, $schedule_tag, $this->client_shared_uid_exceptions);

		//error_log(__METHOD__ . "($entry[id] ($entry[etag]): $entry[title] --> etag=$etag");
		return $etag;
	}

	/**
	 * Send response-headers for a PUT (or POST with add-member query parameter)
	 *
	 * Reimplemented to send
	 *
	 * @param int|array $entry id or array of new created entry
	 * @param string $path
	 * @param int|string $retval
	 * @param boolean $path_attr_is_name=true true: path_attr is ca(l|rd)dav_name, false: id (GroupDAV needs Location header)
	 */
	function put_response_headers($entry, $path, $retval, $path_attr_is_name=true)
	{
		$etag = $this->get_etag($entry, $schedule_tag);

		if ($this->use_schedule_tag)
		{
			header('Schedule-Tag: "'.$schedule_tag.'"');
		}
		parent::put_response_headers($entry, $path, $retval, $path_attr_is_name, $etag);
	}

	/**
	 * Check if user has the neccessary rights on an event
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array|int $event event-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	function check_access($acl,$event)
	{
		if ($acl == EGW_ACL_READ)
		{
			// we need at least EGW_ACL_FREEBUSY to get some information
			$acl = EGW_ACL_FREEBUSY;
		}
		return $this->bo->check_perms($acl,$event,0,'server');
	}

	/**
	 * Add extra properties for calendar collections
	 *
	 * @param array $props=array() regular props by the groupdav handler
	 * @param string $displayname
	 * @param string $base_uri=null base url of handler
	 * @param int $user=null account_id of owner of current collection
	 * @param string $path=null path of the collection
	 * @return array
	 */
	public function extra_properties(array $props=array(), $displayname, $base_uri=null, $user=null, $path=null)
	{
		if (!isset($props['calendar-description']))
		{
			// default calendar description: can be overwritten via PROPPATCH, in which case it's already set
			$props['calendar-description'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-description',$displayname);
		}
		$supported_components = array(
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VCALENDAR')),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VEVENT')),
		);
		// outbox supports VFREEBUSY too, it is required from OS X iCal to autocomplete locations
		if (substr($path,-8) == '/outbox/')
		{
			$supported_components[] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'comp',array('name' => 'VFREEBUSY'));
		}
		$props['supported-calendar-component-set'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,
			'supported-calendar-component-set',$supported_components);
		$props['supported-report-set'] = HTTP_WebDAV_Server::mkprop('supported-report-set',array(
			HTTP_WebDAV_Server::mkprop('supported-report',array(
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-query',''))),
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-multiget',''))),
				HTTP_WebDAV_Server::mkprop('report',array(
					HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'free-busy-query',''))),
		))));
		$props['supported-calendar-data'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'supported-calendar-data',array(
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data', array('content-type' => 'text/calendar', 'version'=> '2.0')),
			HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-data', array('content-type' => 'text/x-calendar', 'version'=> '1.0'))));

		// get timezone of calendar
		if ($this->groupdav->prop_requested('calendar-timezone'))
		{
			$props['calendar-timezone'] = HTTP_WebDAV_Server::mkprop(groupdav::CALDAV,'calendar-timezone',
				calendar_timezones::user_timezone($user));
		}
		return $props;
	}

	/**
	 * Get the handler and set the supported fields
	 *
	 * @return calendar_ical
	 */
	private function _get_handler()
	{
		$handler = new calendar_ical();
		$handler->setSupportedFields('GroupDAV',$this->agent);
		if ($this->debug > 1) error_log("ical Handler called: " . $this->agent);
		return $handler;
	}

	/**
	 * Return calendars/addressbooks shared from other users with the current one
	 *
	 * return array account_id => account_lid pairs
	 */
	function get_shared()
	{
		$shared = array();
		$calendar_home_set = $GLOBALS['egw_info']['user']['preferences']['groupdav']['calendar-home-set'];
		$calendar_home_set = $calendar_home_set ? explode(',',$calendar_home_set) : array();
		// replace symbolic id's with real nummeric id's
		foreach(array(
			'G' => $GLOBALS['egw_info']['user']['account_primary_group'],
		) as $sym => $id)
		{
			if (($key = array_search($sym, $calendar_home_set)) !== false)
			{
				$calendar_home_set[$key] = $id;
			}
		}
		foreach(ExecMethod('calendar.calendar_bo.list_cals') as $entry)
		{
			$id = $entry['grantor'];
			if ($id && $GLOBALS['egw_info']['user']['account_id'] != $id &&	// no current user
				(in_array('A',$calendar_home_set) || in_array((string)$id,$calendar_home_set)) &&
				is_numeric($id) && ($owner = $this->accounts->id2name($id)))
			{
				$shared[$id] = $owner;
			}
		}
		return $shared;
	}

	/**
	 * Return appliction specific settings
	 *
	 * @param array $hook_data
	 * @return array of array with settings
	 */
	static function get_settings($hook_data)
	{
		$calendars = array();
		if (!isset($hook_data['setup']))
		{
			$user = $GLOBALS['egw_info']['user']['account_id'];
			$cal_bo = new calendar_bo();
			foreach ($cal_bo->list_cals() as $entry)
			{
				$calendars[$entry['grantor']] = $entry['name'];
			}
			unset($calendars[$user]);
		}
		$calendars = array(
			'A'	=> lang('All'),
			'G'	=> lang('Primary Group'),
		) + $calendars;

		$settings = array();
		$settings['calendar-home-set'] = array(
			'type'   => 'multiselect',
			'label'  => 'Calendars to sync in addition to personal calendar',
			'name'   => 'calendar-home-set',
			'help'   => lang('Only supported by a few fully conformant clients (eg. from Apple). If you have to enter a URL, it will most likly not be suppored!').'<br/>'.lang('They will be sub-folders in users home (%1 attribute).','CalDAV "calendar-home-set"'),
			'values' => $calendars,
			'xmlrpc' => True,
			'admin'  => False,
		);
		return $settings;
	}
}
