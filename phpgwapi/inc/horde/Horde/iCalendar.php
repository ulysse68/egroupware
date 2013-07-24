<?php
/**
 * eGroupWare - iCalendar based on Horde 3
 *
 * Class representing iCalendar files.
 *
 *
 * Using the PEAR Log class (which need to be installed!)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage horde
 * @author Mike Cochrane <mike@graftonhall.co.nz>
 * @author Joerg Lehrke <jlehrke@noc.de>
 * @copyright (c) The Horde Project (http://www.horde.org/)
 * @version $Id: iCalendar.php 40739 2012-11-01 17:24:22Z ralfbecker $
 */

/**
 * Class representing iCalendar files.
 */
class Horde_iCalendar {

    /**
     * The parent (containing) iCalendar object.
     *
     * @var Horde_iCalendar
     */
    var $_container = false;

    /**
     * The name/value pairs of attributes for this object (UID,
     * DTSTART, etc.). Which are present depends on the object and on
     * what kind of component it is.
     *
     * @var array
     */
    var $_attributes = array();

    /**
     * Any children (contained) iCalendar components of this object.
     *
     * @var array
     */
    var $_components = array();

    /**
     * According to RFC 2425, we should always use CRLF-terminated lines.
     *
     * @var string
     */
    var $_newline = "\r\n";

    /**
     * iCalendar format version (different behavior for 1.0 and 2.0
     * especially with recurring events).
     *
     * @var string
     */
    var $_version;

    function Horde_iCalendar($version = '2.0')
    {
        $this->_version = $version;
        $this->setAttribute('VERSION', $version);
    }

    /**
     * Return a reference to a new component.
     *
     * @param string          $type       The type of component to return
     * @param Horde_iCalendar $container  A container that this component
     *                                    will be associated with.
     *
     * @return object  Reference to a Horde_iCalendar_* object as specified.
     *
     * @static
     */
    function &newComponent($type, &$container)
    {
        $type = String::lower($type);
        $class = 'Horde_iCalendar_' . $type;
        if (!class_exists($class)) {
            include 'Horde/iCalendar/' . $type . '.php';
        }
        if (class_exists($class)) {
            $component = new $class();
            if ($container !== false) {
                $component->_container = &$container;
                // Use version of container, not default set by component
                // constructor.
                $component->_version = $container->_version;
            }
        } else {
            // Should return an dummy x-unknown type class here.
            $component = false;
        }

        return $component;
    }

    /**
     * Sets the value of an attribute.
     *
     * @param string $name     The name of the attribute.
     * @param string $value    The value of the attribute.
     * @param array $params    Array containing any addition parameters for
     *                         this attribute.
     * @param boolean $append  True to append the attribute, False to replace
     *                         the first matching attribute found.
     * @param array $values    Array representation of $value.  For
     *                         comma/semicolon seperated lists of values.  If
     *                         not set use $value as single array element.
     */
    function setAttribute($name, $value, $params = array(), $append = true,
                          $values = false)
    {
        // Make sure we update the internal format version if
        // setAttribute('VERSION', ...) is called.
        if ($name == 'VERSION') {
            $this->_version = $value;
            if ($this->_container !== false) {
                $this->_container->_version = $value;
            }
        }

        if (!$values) {
            $values = array($value);
        }
        $found = false;
        if (!$append) {
            foreach (array_keys($this->_attributes) as $key) {
                if ($this->_attributes[$key]['name'] == String::upper($name)) {
                    $this->_attributes[$key]['params'] = $params;
                    $this->_attributes[$key]['value'] = $value;
                    $this->_attributes[$key]['values'] = $values;
                    $found = true;
                    break;
                }
            }
        }

        if ($append || !$found) {
            $this->_attributes[] = array(
                'name'      => String::upper($name),
                'params'    => $params,
                'value'     => $value,
                'values'    => $values
            );
        }
    }

    /**
     * Sets parameter(s) for an (already existing) attribute.  The
     * parameter set is merged into the existing set.
     *
     * @param string $name   The name of the attribute.
     * @param array $params  Array containing any additional parameters for
     *                       this attribute.
     * @return boolean  True on success, false if no attribute $name exists.
     */
    function setParameter($name, $params = array())
    {
        $keys = array_keys($this->_attributes);
        foreach ($keys as $key) {
            if ($this->_attributes[$key]['name'] == $name) {
                $this->_attributes[$key]['params'] =
                    array_merge($this->_attributes[$key]['params'], $params);
                return true;
            }
        }

        return false;
    }

    /**
     * Get the value of an attribute.
     *
     * @param string $name     The name of the attribute.
     * @param boolean $params  Return the parameters for this attribute instead
     *                         of its value.
     *
     * @return mixed (object)  PEAR_Error if the attribute does not exist.
     *               (string)  The value of the attribute.
     *               (array)   The parameters for the attribute or
     *                         multiple values for an attribute.
     */
    function getAttribute($name, $params = false)
    {
        $result = array();
        foreach ($this->_attributes as $attribute) {
            if ($attribute['name'] == $name) {
                if ($params) {
                    $result[] = $attribute['params'];
                } else {
                    $result[] = $attribute['value'];
                }
            }
        }
        if (!count($result)) {
            require_once 'PEAR.php';
            return PEAR::raiseError('Attribute "' . $name . '" Not Found');
        } if (count($result) == 1 && !$params) {
            return $result[0];
        } else {
            return $result;
        }
    }

    /**
     * Gets the values of an attribute as an array.  Multiple values
     * are possible due to:
     *
     *  a) multiplce occurences of 'name'
     *  b) (unsecapd) comma seperated lists.
     *
     * So for a vcard like "KEY:a,b\nKEY:c" getAttributesValues('KEY')
     * will return array('a', 'b', 'c').
     *
     * @param string  $name    The name of the attribute.
     * @return mixed (object)  PEAR_Error if the attribute does not exist.
     *               (array)   Multiple values for an attribute.
     */
    function getAttributeValues($name)
    {
        $result = array();
        foreach ($this->_attributes as $attribute) {
            if ($attribute['name'] == $name) {
                $result = array_merge($attribute['values'], $result);
            }
        }
        if (!count($result)) {
            return PEAR::raiseError('Attribute "' . $name . '" Not Found');
        }
        return $result;
    }

    /**
     * Returns the value of an attribute, or a specified default value
     * if the attribute does not exist.
     *
     * @param string $name    The name of the attribute.
     * @param mixed $default  What to return if the attribute specified by
     *                        $name does not exist.
     *
     * @return mixed (string) The value of $name.
     *               (mixed)  $default if $name does not exist.
     */
    function getAttributeDefault($name, $default = '')
    {
        $value = $this->getAttribute($name);
        return is_a($value, 'PEAR_Error') ? $default : $value;
    }

    /**
     * Remove all occurences of an attribute.
     *
     * @param string $name  The name of the attribute.
     */
    function removeAttribute($name)
    {
        $keys = array_keys($this->_attributes);
        foreach ($keys as $key) {
            if ($this->_attributes[$key]['name'] == $name) {
                unset($this->_attributes[$key]);
            }
        }
    }

    /**
     * Get attributes for all tags or for a given tag.
     *
     * @param string $tag  Return attributes for this tag, or all attributes if
     *                     not given.
     *
     * @return array  An array containing all the attributes and their types.
     */
    function getAllAttributes($tag = false)
    {
        if ($tag === false) {
            return $this->_attributes;
        }
        $result = array();
        foreach ($this->_attributes as $attribute) {
            if ($attribute['name'] == $tag) {
                $result[] = $attribute;
            }
        }
        return $result;
    }

    /**
     * Add a vCalendar component (eg vEvent, vTimezone, etc.).
     *
     * @param Horde_iCalendar $component  Component (subclass) to add.
     */
    function addComponent($component)
    {
        if (is_a($component, 'Horde_iCalendar')) {
            $component->_container = &$this;
            $this->_components[] = &$component;
        }
    }

    /**
     * Retrieve all the components.
     *
     * @return array  Array of Horde_iCalendar objects.
     */
    function getComponents()
    {
        return $this->_components;
    }

    function getType()
    {
        return 'vcalendar';
    }

    /**
     * Return the classes (entry types) we have.
     *
     * @return array  Hash with class names Horde_iCalendar_xxx as keys
     *                and number of components of this class as value.
     */
    function getComponentClasses()
    {
        $r = array();
        foreach ($this->_components as $c) {
            $cn = strtolower(get_class($c));
            if (empty($r[$cn])) {
                $r[$cn] = 1;
            } else {
                $r[$cn]++;
            }
        }

        return $r;
    }

    /**
     * Number of components in this container.
     *
     * @return integer  Number of components in this container.
     */
    function getComponentCount()
    {
        return count($this->_components);
    }

    /**
     * Retrieve a specific component.
     *
     * @param integer $idx  The index of the object to retrieve.
     *
     * @return mixed    (boolean) False if the index does not exist.
     *                  (Horde_iCalendar_*) The requested component.
     */
    function getComponent($idx)
    {
        if (isset($this->_components[$idx])) {
            return $this->_components[$idx];
        } else {
            return false;
        }
    }

    /**
     * Locates the first child component of the specified class, and returns a
     * reference to it.
     *
     * @param string $type  The type of component to find.
     *
     * @return boolean|Horde_iCalendar_*  False if no subcomponent of the
     *                                    specified class exists or a reference
     *                                    to the requested component.
     */
    function &findComponent($childclass)
    {
        $childclass = 'Horde_iCalendar_' . String::lower($childclass);
        $keys = array_keys($this->_components);
        foreach ($keys as $key) {
            if (is_a($this->_components[$key], $childclass)) {
                return $this->_components[$key];
            }
        }

        $component = false;
        return $component;
    }

    /**
     * Locates the first matching child component of the specified class, and
     * returns a reference to it.
     *
     * @param string $childclass  The type of component to find.
     * @param string $attribute   This attribute must be set in the component
     *                            for it to match.
     * @param string $value       Optional value that $attribute must match.
     *
     * @return boolean|Horde_iCalendar_*  False if no matching subcomponent of
     *                                    the specified class exists, or a
     *                                    reference to the requested component.
     */
    function &findComponentByAttribute($childclass, $attribute, $value = null)
    {
        $childclass = 'Horde_iCalendar_' . String::lower($childclass);
        $keys = array_keys($this->_components);
        foreach ($keys as $key) {
            if (is_a($this->_components[$key], $childclass)) {
                $attr = $this->_components[$key]->getAttribute($attribute);
                if (is_a($attr, 'PEAR_Error')) {
                    continue;
                }
                if ($value !== null && $value != $attr) {
                    continue;
                }
                return $this->_components[$key];
            }
        }

        $component = false;
        return $component;
    }

    /**
     * Clears the iCalendar object (resets the components and attributes
     * arrays).
     */
    function clear()
    {
        $this->_components = array();
        $this->_attributes = array();
    }

    /**
     * Checks if entry is vcalendar 1.0, vcard 2.1 or vnote 1.1.
     *
     * These 'old' formats are defined by www.imc.org. The 'new' (non-old)
     * formats icalendar 2.0 and vcard 3.0 are defined in rfc2426 and rfc2445
     * respectively.
     *
     * @since Horde 3.1.2
     */
    function isOldFormat()
    {
	$retval = true;
	switch ($this->getType()) {
		case 'vcard':
            		$retval = ($this->_version < 3);
			break;
		case 'vNote':
            		$retval = ($this->_version < 2);
			break;
		default:
			$retval = ($this->_version < 2);
			break;
	}
        return $retval;
    }

    /**
     * Export as vCalendar format.
     *
     * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, standard character set for old format.
     */
    function exportvCalendar($charset = null)
    {
        // Default values.
        $requiredAttributes['PRODID'] = '-//The Horde Project//Horde_iCalendar Library' . (defined('HORDE_VERSION') ? ', Horde ' . constant('HORDE_VERSION') : '') . '//EN';
        // METHOD is only required for iTip, but not for CalDAV, therefore removing it here calendar_ical sets it anyway by default
        //$requiredAttributes['METHOD'] = 'PUBLISH';

        foreach ($requiredAttributes as $name => $default_value) {
            if (is_a($this->getattribute($name), 'PEAR_Error')) {
                $this->setAttribute($name, $default_value);
            }
        }

        return $this->_exportvData('VCALENDAR', $charset);
    }

    /**
     * Export this entry as a hash array with tag names as keys.
     *
     * @param boolean $paramsInKeys
     *                If false, the operation can be quite lossy as the
     *                parameters are ignored when building the array keys.
     *                So if you export a vcard with
     *                LABEL;TYPE=WORK:foo
     *                LABEL;TYPE=HOME:bar
     *                the resulting hash contains only one label field!
     *                If set to true, array keys look like 'LABEL;TYPE=WORK'
     * @return array  A hash array with tag names as keys.
     */
    function toHash($paramsInKeys = false)
    {
        $hash = array();
        foreach ($this->_attributes as $a)  {
            $k = $a['name'];
            if ($paramsInKeys && is_array($a['params'])) {
                foreach ($a['params'] as $p => $v) {
                    $k .= ";$p=$v";
                }
            }
            $hash[$k] = $a['value'];
        }

        return $hash;
    }

    /**
     * Parses a string containing vCalendar data.
     *
     * @todo This method doesn't work well at all, if $base is VCARD.
     *
     * @param string $text     The data to parse.
     * @param string $base     The type of the base object.
     * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, iso-8859-1 for old format.
     * @param boolean $clear   If true clears the iCal object before parsing.
     *
     * @return boolean  True on successful import, false otherwise.
     */
    function parsevCalendar($text, $base = 'VCALENDAR', $charset = null, $clear = true)
    {
        if ($clear) {
            $this->clear();
        }

        if ($base == 'VTODO' &&
        	preg_match('/^BEGIN:VTODO(.*)^END:VEVENT/ism', $text, $matches)) {
        	// Workaround for Funambol VTODO bug in Mozilla Sync Plugins
        	Horde::logMessage('iCalendar: Funambol VTODO-bug detected, workaround activated...',
        		__FILE__, __LINE__, PEAR_LOG_WARNING);
            $container = true;
            $vCal = $matches[1];
        } elseif (preg_match('/^BEGIN:' . $base . '(.*)^END:' . $base . '/ism', $text, $matches)) {
            $container = true;
            $vCal = $matches[1];
        } else {
            // Text isn't enclosed in BEGIN:VCALENDAR
            // .. END:VCALENDAR. We'll try to parse it anyway.
            $container = false;
            $vCal = $text;
        }

        if (preg_match('/^VERSION:(\d\.\d)\s*$/ism', $vCal, $matches)) {
            // define the version asap
            #Horde::logMessage("iCalendar VERSION:" . $matches[1], __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $this->setAttribute('VERSION', $matches[1]);
        }

		// Preserve a trailing CR
        $vCal = trim($vCal) . "\n";

        // All subcomponents.
        $matches = null;
        // Workaround for Funambol VTODO bug in Mozilla Sync Plugins
        if (preg_match_all('/^BEGIN:(VTODO)(\r\n|\r|\n)(.*)^END:VEVENT/Uims', $vCal, $matches) ||
        	preg_match_all('/^BEGIN:(.*)(\r\n|\r|\n)(.*)^END:\1/Uims', $vCal, $matches)) {
            // vTimezone components are processed first. They are
            // needed to process vEvents that may use a TZID.
            foreach ($matches[0] as $key => $data) {
                $type = trim($matches[1][$key]);
                if ($type != 'VTIMEZONE') {
                    continue;
                }
                $component = &Horde_iCalendar::newComponent($type, $this);
                if ($component === false) {
                    return PEAR::raiseError("Unable to create object for type $type");
                }
                $component->parsevCalendar($data, $type, $charset);

                $this->addComponent($component);

                // Remove from the vCalendar data.
                $vCal = str_replace($data, '', $vCal);
            }

            // Now process the non-vTimezone components.
            foreach ($matches[0] as $key => $data) {
                $type = trim($matches[1][$key]);
                if ($type == 'VTIMEZONE') {
                    continue;
                }
                $component = &Horde_iCalendar::newComponent($type, $this);
                if ($component === false) {
                    return PEAR::raiseError("Unable to create object for type $type");
                }
                $component->parsevCalendar($data, $type, $charset);

                $this->addComponent($component);

                // Remove from the vCalendar data.
                $vCal = str_replace($data, '', $vCal);
            }
        } elseif (!$container) {
            return false;
        }

        // Unfold "quoted printable" folded lines like:
        //  BODY;ENCODING=QUOTED-PRINTABLE:=
        //  another=20line=
        //  last=20line
        while (preg_match_all('/^([^:]+;\s*((ENCODING=)?QUOTED-PRINTABLE|ENCODING=[Q|q])(.*=\r?\n)+(.*[^=])?\r?\n)/mU', $vCal, $matches)) {
            foreach ($matches[1] as $s) {
            	if ($this->isOldFormat()) {
                	$r = preg_replace('/=\r?\n([ \t])?/', '\1', $s);
            	} else {
            		$r = preg_replace('/=\r?\n[ \t]*/', '', $s);
            	}
                $vCal = str_replace($s, $r, $vCal);
            }
        }

        // Unfold any folded lines.
        if ($this->isOldFormat()) {
        	// old formats force folding at whitespace which must therefore be preserved
        	$vCal = preg_replace('/[\r\n]+([ \t])/', '\1', $vCal);
        } else {
        	$vCal = preg_replace('/[\r\n]+[ \t]/', '', $vCal);
        }

		$isDate = false;

        // Parse the remaining attributes.
        if (preg_match_all('/^((?:[^":]+|(?:"[^"]*")+)*):([^\r\n]*)\r?$/m', $vCal, $matches)) {
            foreach ($matches[0] as $attribute) {
                preg_match('/([^:;]*)((;(?:(?:[^":\\\]*(?:\\\.)?)+|(?:"[^"]*")+)*)?):([^\r\n]*)[\r\n]*/', $attribute, $parts);
                $tag = trim(String::upper($parts[1]));
                $value = $parts[4];
                $params = array();

                // Parse parameters.
                if (!empty($parts[2])) {
                    preg_match_all('/;(([^;=]*)(=("[^"]*"|(?:[^;\\\]*(?:\\\.)?)*))?)/', $parts[2], $param_parts);
                    foreach ($param_parts[2] as $key => $paramName) {
                        $paramName = String::upper($paramName);
                        $paramValue = $param_parts[4][$key];
                        if ($paramName == 'TYPE') {
                            $paramValue = preg_split('/(?<!\\\\),/', $paramValue);
                            if (isset($params[$paramName])) {
                        		if (!is_array($params[$paramName])) {
                        			$params[$paramName] = array($params[$paramName]);
                        		}
                        		$params[$paramName] = array_merge($params[$paramName], $paramValue);
                        	} else {
                        		if (count($paramValue) == 1) {
                                	$paramValue = $paramValue[0];
                            	}
                            	$params[$paramName] = $paramValue;
                        	}
                        } else {
                        	$params[$paramName] = $paramValue;
                        }
                    }
                }

                // Charset and encoding handling.
                if (isset($params['QUOTED-PRINTABLE'])) {
	                $params['ENCODING'] = 'QUOTED-PRINTABLE';
                }
                if (isset($params['BASE64'])) {
	                $params['ENCODING'] = 'BASE64';
                }
                if (isset($params['ENCODING'])) {
	                switch (String::upper($params['ENCODING'])) {
		                case 'Q':
		                case 'QUOTED-PRINTABLE':
			                $value = quoted_printable_decode($value);
			                if (isset($params['CHARSET'])) {
				                $value = $GLOBALS['egw']->translation->convert($value, $params['CHARSET']);
			                } else {
				                $value = $GLOBALS['egw']->translation->convert($value,
					                empty($charset) ? ($this->isOldFormat() ? 'iso-8859-1' : 'utf-8') : $charset);
			                }
			                // Funambol hack :-(
			                $value = str_replace('\\\\n', "\n", $value);
			                break;
		                case 'B':
		                case 'BASE64':
			                $value = base64_decode($value);
			                break;
	                }
                } elseif (isset($params['CHARSET'])) {
	                $value = $GLOBALS['egw']->translation->convert($value, $params['CHARSET']);
                } else {
	                // As per RFC 2279, assume UTF8 if we don't have an
	                // explicit charset parameter.
	                $value = $GLOBALS['egw']->translation->convert($value,
		                empty($charset) ? ($this->isOldFormat() ? 'iso-8859-1' : 'utf-8') : $charset);
                }

                // Get timezone info for date fields from $params.
                $tzid = isset($params['TZID']) ? trim($params['TZID'], '\"') : false;

                switch ($tag) {
	                case 'VERSION': // already processed
		                break;
		                // Date fields.
	                case 'COMPLETED':
	                case 'CREATED':
	                case 'LAST-MODIFIED':
		                $this->setAttribute($tag, $this->_parseDateTime($value, $tzid), $params);
		                break;

	                case 'BDAY':
	                case 'X-SYNCJE-ANNIVERSARY':
		                $this->setAttribute($tag, $value, $params, true, $this->_parseDate($value));
		                break;

	                case 'DTEND':
	                case 'DTSTART':
	                case 'DTSTAMP':
	                case 'DUE':
	                case 'AALARM':
	                case 'DALARM':
	                case 'RECURRENCE-ID':
	                case 'X-RECURRENCE-ID':
		                // types like AALARM may contain additional data after a ;
		                // ignore these.
		                $ts = explode(';', $value);
		                if (isset($params['VALUE']) && $params['VALUE'] == 'DATE') {
			                $isDate = true;
			                $this->setAttribute($tag, $this->_parseDateTime($ts[0], $tzid), $params, true, $this->_parseDate($ts[0]));
		                } else {
			                $this->setAttribute($tag, $this->_parseDateTime($ts[0], $tzid), $params);
		                }
		                break;

	                case 'TRIGGER':
		                if (isset($params['VALUE'])) {
			                if ($params['VALUE'] == 'DATE-TIME') {
				                $this->setAttribute($tag, $this->_parseDateTime($value, $tzid), $params);
			                } else {
				                $this->setAttribute($tag, $this->_parseDuration($value), $params);
			                }
		                } else {
			                $this->setAttribute($tag, $this->_parseDuration($value), $params);
		                }
		                break;

		                // Comma or semicolon seperated dates.
	                case 'EXDATE':
	                case 'RDATE':
		                $dates = array();
		                preg_match_all('/[;,]([^;,]*)/', ';' . $value, $values);

		                foreach ($values[1] as $value) {
			                if ((isset($params['VALUE'])
					                && $params['VALUE'] == 'DATE') || (!isset($params['VALUE']) && $isDate)) {
				                $dates[] = $this->_parseDate(trim($value));
			                } else {
				                $dates[] = $this->_parseDateTime(trim($value), $tzid);
			                }
		                }
		                $this->setAttribute($tag, isset($dates[0]) ? $dates[0] : null, $params, true, $dates);
		                break;

		                // Duration fields.
	                case 'DURATION':
		                $this->setAttribute($tag, $this->_parseDuration($value), $params);
		                break;

		                // Period of time fields.
	                case 'FREEBUSY':
		                $periods = array();
		                preg_match_all('/,([^,]*)/', ',' . $value, $values);
		                foreach ($values[1] as $value) {
			                $periods[] = $this->_parsePeriod($value);
		                }

		                $this->setAttribute($tag, isset($periods[0]) ? $periods[0] : null, $params, true, $periods);
		                break;

		                // UTC offset fields.
	                case 'TZOFFSETFROM':
	                case 'TZOFFSETTO':
		                $this->setAttribute($tag, $this->_parseUtcOffset($value), $params);
		                break;

		                // Integer fields.
	                case 'PERCENT-COMPLETE':
	                case 'PRIORITY':
	                case 'REPEAT':
	                case 'SEQUENCE':
		                $this->setAttribute($tag, intval($value), $params);
		                break;

		                // Geo fields.
	                case 'GEO':
		                if ($this->isOldFormat()) {
			                $floats = explode(',', $value);
			                $value = array('latitude' => floatval($floats[1]),
				                'longitude' => floatval($floats[0]));
		                } else {
			                $floats = explode(';', $value);
			                $value = array('latitude' => floatval($floats[0]),
				                'longitude' => floatval($floats[1]));
		                }
		                $this->setAttribute($tag, $value, $params);
		                break;

		                // Recursion fields. # add more flexibility
		                #case 'EXRULE':
		                #case 'RRULE':
		                #    $this->setAttribute($tag, trim($value), $params);
		                #    break;

		                // Binary fields.
	                case 'PHOTO':
		                $this->setAttribute($tag, $value, $params);
		                break;

		                // ADR, ORG and N are lists seperated by unescaped semicolons
		                // with a specific number of slots.
	                case 'ADR':
	                case 'N':
	                case 'ORG':
		                $value = trim($value);
		                // As of rfc 2426 2.4.2 semicolon, comma, and colon must
		                // be escaped (semicolon is unescaped after splitting below).
		                $value = str_replace(array('\\n', '\\N', '\\,', '\\:'),
			                array("\n", "\n", ',', ':'),
							$value);

		                // Split by unescaped semicolons:
		                $values = preg_split('/(?<!\\\\);/', $value);
		                $value = str_replace('\\;', ';', $value);
		                $values = str_replace('\\;', ';', $values);
		                $this->setAttribute($tag, trim($value), $params, true, $values);
		                break;

		                // CATEGORIES is a lists seperated by unescaped commas
		                // with a unspecific number of slots.
	                case 'CATEGORIES':
		                $value = trim($value);
		                // As of rfc 2426 2.4.2 semicolon, comma, and colon must
		                // be escaped (comma is unescaped after splitting below).
		                $value = str_replace(array('\\n', '\\N', '\\;', '\\:'),
			                array("\n", "\n", ';', ':'),
							$value);

		                // Split by unescaped commas:
		                $values = preg_split('/(?<!\\\\),/', $value);
		                $value = str_replace('\\,', ',', $value);
		                $values = str_replace('\\,', ',', $values);
		                $this->setAttribute($tag, trim($value), $params, true, $values);
		                break;

		                // String fields.
	                default:
		                if ($this->isOldFormat()) {
			                $value = trim($value);
			                // vCalendar 1.0 and vCard 2.1 only escape semicolons
			                // and use unescaped semicolons to create lists.
			                $value = str_replace(array('\\n', '\\N', '\\,', '\\:'),
				                array("\n", "\n", ',', ':'),
								$value);

			                // Split by unescaped semicolons:
			                $values = preg_split('/(?<!\\\\);/', $value);
			                $value = str_replace('\\;', ';', $value);
			                $values = str_replace('\\;', ';', $values);
			                $this->setAttribute($tag, trim($value), $params, true, $values);
		                } else {
			                $value = trim($value);
			                // As of rfc 2426 2.4.2 semicolon, comma, and colon
			                // must be escaped (comma is unescaped after splitting
			                // below).
			                $value = str_replace(array('\\n', '\\N', '\\;', '\\:', '\\\\'),
				                array("\n", "\n", ';', ':', '\\'),
								$value);

			                // Split by unescaped commas.
			                $values = preg_split('/(?<!\\\\),/', $value);
			                $value = str_replace('\\,', ',', $value);
			                $values = str_replace('\\,', ',', $values);
			                $this->setAttribute($tag, trim($value), $params, true, $values);
		                }
	                break;
                }
            }
        }

        return true;
    }

    /**
     * Export this component in vCal format.
     *
     * @param string $base  The type of the base object.
     * @param string $charset  The encoding charset for $text. Defaults to
     *                         utf-8 for new format, standard character set for old format.
     *
     * @return string  vCal format data.
     */
    function _exportvData($base = 'VCALENDAR', $charset = null)
    {
    	$base = String::upper($base);

        $result = 'BEGIN:' . $base . $this->_newline;

        // VERSION is not allowed for entries enclosed in VCALENDAR/ICALENDAR,
        // as it is part of the enclosing VCALENDAR/ICALENDAR. See rfc2445
        if ($base !== 'VEVENT' && $base !== 'VTODO' && $base !== 'VALARM' &&
            $base !== 'VJOURNAL' && $base !== 'VFREEBUSY' &&
            $base !== 'VTIMEZONE' && $base !== 'STANDARD' && $base != 'DAYLIGHT') {
            // Ensure that version is the first attribute.
            $result .= 'VERSION:' . $this->_version . $this->_newline;
        }

        if (empty($charset)) {
        	if ($this->isOldFormat()) {
        		$charset = NLS::getCharset();
        	} else {
        		$charset = 'utf-8';
        	}
        }

        foreach ($this->_attributes as $attribute) {
            $name = $attribute['name'];
            if ($name == 'VERSION') {
                // Already done.
                continue;
            }

            $params_str = '';
            $params = $attribute['params'];
            if ($params) {
                foreach ($params as $param_name => $param_value) {
                    /* Skip CHARSET for iCalendar 2.0 data, not allowed. */
                    if ($param_name == 'CHARSET') {
                    	if (!$this->isOldFormat() || empty($param_value)) {
                        	continue;
                    	} else {
                    		$param_value = String::Upper($param_value);
                    	}
                    }
                    if ($param_name == 'ENCODING') {
                    	continue;
                    }
                    /* Skip VALUE=DATE for vCalendar 1.0 data, not allowed. */
                    if ($this->isOldFormat() &&
                        $param_name == 'VALUE' && $param_value == 'DATE') {
                        continue;
                    }
                    /* Skip TZID for iCalendar 1.0 data, not supported. */
					if ($this->isOldFormat() && $param_name == 'TZID') {
                        continue;
                    }
                    // Skip CN in ATTENDEE adn ORGANIZER for vCalendar 1.0
                    if ($this->isOldFormat() && $param_name == 'CN' &&
                    	($name == 'ATTENDEE' || $name == 'ORGANIZER')) {
                    	continue;
                    }
                    if ($param_value === null) {
                        $params_str .= ";$param_name";
                    } else {
                        $params_str .= ";$param_name=$param_value";
                    }
                }
            }

            $value = $attribute['value'];

            switch ($name) {
            // Date fields.
            case 'COMPLETED':
            case 'CREATED':
            case 'DCREATED':
            case 'LAST-MODIFIED':
                $value = $this->_exportDateTime($value);
                break;

			// Support additional fields after date.
 			case 'AALARM':
            case 'DALARM':
            	if (isset($params['VALUE'])) {
                    if ($params['VALUE'] == 'DATE') {
                        // VCALENDAR 1.0 uses T000000 - T235959 for all day events:
                        if ($this->isOldFormat() && $name == 'DTEND') {
                            $d = new Horde_Date($value);
                            $value = new Horde_Date(array(
                                'year' => $d->year,
                                'month' => $d->month,
                                'mday' => $d->mday - 1));
                            $value->correct();
                            $value = $this->_exportDate($value, '235959');
                        } else {
                            $value = $this->_exportDate($value, '000000');
                        }
                    } else {
                        $value = $this->_exportDateTime($value);
                    }
                } else {
                    $value = $this->_exportDateTime($value);
                }

            	if (is_array($attribute['values']) &&
		            	count($attribute['values']) > 0) {
		            $values = $attribute['values'];
	            	if ($this->isOldFormat()) {
		            	$values = str_replace(';', '\\;', $values);
	            	} else {
                        // As of rfc 2426 2.5 semicolon and comma must be
                        // escaped.
                        $values = str_replace(array('\\', ';', ','),
                                              array('\\\\', '\\;', '\\,'),
                                              $values);
	            	}
	            	$value .= ';' . implode(';', $values);
		         }

                break;

            case 'DTEND':
            case 'DTSTART':
            case 'DTSTAMP':
            case 'DUE':
            case 'RECURRENCE-ID':
            case 'X-RECURRENCE-ID':
                if (isset($params['VALUE'])) {
                    if ($params['VALUE'] == 'DATE') {
                        // VCALENDAR 1.0 uses T000000 - T235959 for all day events:
                        if ($this->isOldFormat() && $name == 'DTEND') {
                            $d = new Horde_Date($value);
                            $value = new Horde_Date(array(
                                'year' => $d->year,
                                'month' => $d->month,
                                'mday' => $d->mday - 1));
                            $value->correct();
                            $value = $this->_exportDate($value, '235959');
                        } else {
                            $value = $this->_exportDate($value, '000000');
                        }
                    } else {
                        $value = $this->_exportDateTime($value);
                    }
                } else {
                    $value = $this->_exportDateTime($value);
                }
                break;

            // Comma or semicolon seperated dates.
            case 'EXDATE':
            case 'RDATE':
	            if (is_array($attribute['values'])) {
		            $values = $attribute['values'];
	            } elseif (!empty($value)) {
	            	if ($this->isOldFormat()) {
		            	$values = explode(';', $value);
	            	} else {
	            		$values = explode(',', $value);
	            	}
	            } else {
		            break;
	            }
                $dates = array();
                foreach ($values as $date) {
                    if (isset($params['VALUE'])) {
                        if ($params['VALUE'] == 'DATE') {
                            $dates[] = $this->_exportDate($date, '000000');
                        } elseif ($params['VALUE'] == 'PERIOD') {
                            $dates[] = $this->_exportPeriod($date);
                        } else {
                            $dates[] = $this->_exportDateTime($date);
                        }
                    } else {
                        $dates[] = $this->_exportDateTime($date);
                    }
                }
                if ($this->isOldFormat()) {
	                $value = implode(';', $dates);
                } else {
	                $value = implode(',', $dates);
                }
                break;

            case 'TRIGGER':
                if (isset($params['VALUE'])) {
                    if ($params['VALUE'] == 'DATE-TIME') {
                        $value = $this->_exportDateTime($value);
                    } elseif ($params['VALUE'] == 'DURATION') {
                        $value = $this->_exportDuration($value);
                    }
                } else {
                    $value = $this->_exportDuration($value);
                }
                break;

            // Duration fields.
            case 'DURATION':
                $value = $this->_exportDuration($value);
                break;

            // Period of time fields.
            case 'FREEBUSY':
                $value_str = '';
                foreach ($value as $period) {
                    $value_str .= empty($value_str) ? '' : ',';
                    $value_str .= $this->_exportPeriod($period);
                }
                $value = $value_str;
                break;

            // UTC offset fields.
            case 'TZOFFSETFROM':
            case 'TZOFFSETTO':
                $value = $this->_exportUtcOffset($value);
                break;

            // Integer fields.
            case 'PERCENT-COMPLETE':
            case 'PRIORITY':
            case 'REPEAT':
            case 'SEQUENCE':
                $value = "$value";
                break;

            // Geo fields.
            case 'GEO':
                if ($this->isOldFormat()) {
                    $value = $value['longitude'] . ',' . $value['latitude'];
                } else {
                    $value = $value['latitude'] . ';' . $value['longitude'];
                }
                break;

            // Recurrence fields.
            case 'EXRULE':
            	break;

            case 'RRULE':
                if (!empty($params_str) &&  $params_str[0] == ';')
                {
                	// The standard requires a double colon RRULE:...
                	 $params_str[0] = ':';
                }
                break;

            case 'PHOTO':
                break;

            default:
                if ($this->isOldFormat()) {
                    if (is_array($attribute['values']) &&
                        count($attribute['values']) > 1) {
                        $values = $attribute['values'];
                        if ($name == 'N' || $name == 'ADR' || $name == 'ORG') {
                            $glue = ';';
                        } else {
                            $glue = ',';
                        }
                        $values = str_replace(';', '\\;', $values);
                        $value = implode($glue, $values);
                    } else {
                        /* vcard 2.1 and vcalendar 1.0 escape only
                         * semicolons */
                        $value = str_replace(';', '\\;', $value);
                    }
                    // Text containing newlines or ASCII >= 127 must be BASE64
                    // or QUOTED-PRINTABLE encoded. Currently we use
                    // QUOTED-PRINTABLE as default.
                    if (preg_match('/[^\x20-\x7F]/', $value) &&
		                    !isset($params['ENCODING']))  {
	                    $params['ENCODING'] = 'QUOTED-PRINTABLE';
                    }
                    if (preg_match('/([\177-\377])/', $value) &&
		                    !isset($params['CHARSET'])) {
	                    // Add CHARSET as well. At least the synthesis client
	                    // gets confused otherwise
	                    $params['CHARSET'] = String::upper($charset);
	                    $params_str .= ';CHARSET=' . $params['CHARSET'];
                    }
                } else {
                    if (is_array($attribute['values']) &&
                        count($attribute['values']) > 1) {
                        $values = $attribute['values'];
                        if ($name == 'N' || $name == 'ADR' || $name == 'ORG') {
                            $glue = ';';
                        } else {
                            $glue = ',';
                        }
                        // As of rfc 2426 2.5 semicolon and comma must be
                        // escaped.
                        $values = str_replace(array('\\', ';', ','),
                                              array('\\\\', '\\;', '\\,'),
                                              $values);
                        $value = implode($glue, $values);
                    } else {
                        // As of rfc 2426 2.5 semicolon and comma must be
                        // escaped.
                        $value = str_replace(array('\\', ';', ','),
                                             array('\\\\', '\\;', '\\,'),
                                             $value);
                    }
                    $value = preg_replace('/\r?\n/', "\n", $value);
                }
            }

			$encoding = (!empty($params['ENCODING']) && strlen(trim($value)) > 0);

            if ($encoding) {
	            switch($params['ENCODING']) {
		            case 'Q':
		            case 'QUOTED-PRINTABLE':
		            	if (!$this->isOldFormat())
		            	{
		            		$encoding = false;
		            		break;
		            	}
			            $params_str .= ';ENCODING=' . $params['ENCODING'];
			            $value = str_replace("\r", '', $value);
			            $result .= $name . $params_str . ':'
				            . str_replace('=0A', '=0D=0A',
					            $this->_quotedPrintableEncode($value))
								. $this->_newline;
			            break;
		            case 'FUNAMBOL-QP':
		            	// Funambol needs some special quoting
			            $value = str_replace(array('<', "\r"), array('&lt;', ''), $value);
			            if (!$this->isOldFormat())
			            {
			            	$encoding = false;
			            	break;
			            }
			            $params_str .= ';ENCODING=QUOTED-PRINTABLE';
			            $result .= $name . $params_str . ':'
				            . str_replace('=0A', '=0D=0A',
					            $this->_quotedPrintableEncode($value, false))
								. $this->_newline;
			            break;
		            case 'B':
		            case 'BASE64':
			            $params_str .= ';ENCODING=' . $params['ENCODING'];
			            // using native php wordwrap to speed up encoding of images
			            $result .= wordwrap($name . $params_str . ':' . $this->_newline . ' ' .
			            	$this->_base64Encode($value),75,$this->_newline . ' ',true) . $this->_newline;
			            /*
			            $attr_string = $name . $params_str . ':' . $this->_newline . ' ' . $this->_base64Encode($value);
			            $attr_string = String::wordwrap($attr_string, 75, $this->_newline . ' ',
				            true, 'utf-8', true); // charset does not matter
			            $result .= $attr_string . $this->_newline;
			            */
			            if ($this->isOldFormat()) {
				            $result .= $this->_newline; // Append an empty line
			            }
	            }
            }

            if (!$encoding) {
                $value = str_replace(array("\r", "\n"), array('', '\\n'), $value);
                $attr_string = $name . $params_str;
                if (strlen($value) > 0) {
                	$attr_string .= ':' . $value;
                } elseif ($name != 'RRULE') {
                	$attr_string .= ':';
                }
                if (!$this->isOldFormat()) {
                    $attr_string = String::wordwrap($attr_string, 75, $this->_newline . ' ',
                                                    true, $charset, true);
                }
                $result .= $attr_string . $this->_newline;
            }
        }

        foreach ($this->_components as $component) {
            if ($this->isOldFormat() &&  $component->getType() == 'vTimeZone') {
    			// Not supported
				continue;
        	}
            $result .= $component->exportvCalendar($charset);
        }

        return $result . 'END:' . $base . $this->_newline;
    }

    /**
     * Parse a UTC Offset field.
     */
    function _parseUtcOffset($text)
    {
        $offset = array();
        if (preg_match('/(\+|-)([0-9]{2})([0-9]{2})([0-9]{2})?/', $text, $timeParts)) {
            $offset['ahead']  = (bool)($timeParts[1] == '+');
            $offset['hour']   = intval($timeParts[2]);
            $offset['minute'] = intval($timeParts[3]);
            if (isset($timeParts[4])) {
                $offset['second'] = intval($timeParts[4]);
            }
            return $offset;
        } else {
            return false;
        }
    }

    /**
     * Export a UTC Offset field.
     */
    function _exportUtcOffset($value)
    {
        $offset = $value['ahead'] ? '+' : '-';
        $offset .= sprintf('%02d%02d',
                           $value['hour'], $value['minute']);
        if (isset($value['second'])) {
            $offset .= sprintf('%02d', $value['second']);
        }

        return $offset;
    }

    /**
     * Parse a Time Period field.
     */
    function _parsePeriod($text)
    {
        $periodParts = explode('/', $text);

        $start = $this->_parseDateTime($periodParts[0]);

        if ($duration = $this->_parseDuration($periodParts[1])) {
            return array('start' => $start, 'duration' => $duration);
        } elseif ($end = $this->_parseDateTime($periodParts[1])) {
            return array('start' => $start, 'end' => $end);
        }
    }

    /**
     * Export a Time Period field.
     */
    function _exportPeriod($value)
    {
        $period = $this->_exportDateTime($value['start']);
        $period .= '/';
        if (isset($value['duration'])) {
            $period .= $this->_exportDuration($value['duration']);
        } else {
            $period .= $this->_exportDateTime($value['end']);
        }
        return $period;
    }

    /**
     * Grok the TZID and return an offset in seconds from UTC for this
     * date and time.
     */
    function _parseTZID($date, $time, $tzid)
    {
        $vtimezone = $this->_container->findComponentByAttribute('vtimezone', 'TZID', $tzid);
        if (!$vtimezone) {
            return false;
        }

        $change_times = array();
        foreach ($vtimezone->getComponents() as $o) {
            $t = $vtimezone->parseChild($o, $date['year']);
            if ($t !== false) {
                $change_times[] = $t;
            }
        }

        if (!$change_times) {
            return false;
        }

        sort($change_times);

        // Time is arbitrarily based on UTC for comparison.
        $t = @gmmktime($time['hour'], $time['minute'], $time['second'],
                       $date['month'], $date['mday'], $date['year']);

        if ($t < $change_times[0]['time']) {
            return $change_times[0]['from'];
        }

        for ($i = 0, $n = count($change_times); $i < $n - 1; $i++) {
            if (($t >= $change_times[$i]['time']) &&
                ($t < $change_times[$i + 1]['time'])) {
                return $change_times[$i]['to'];
            }
        }

        if ($t >= $change_times[$n - 1]['time']) {
            return $change_times[$n - 1]['to'];
        }

        return false;
    }

    /**
     * Parses a DateTime field and returns a unix timestamp. If the
     * field cannot be parsed then the original text is returned
     * unmodified.
     *
     * @todo This function should be moved to Horde_Date and made public.
     */
    function _parseDateTime($text, $tzid = false)
    {
        $dateParts = explode('T', $text);
        if (count($dateParts) != 2 && !empty($text)) {
            // Not a datetime field but may be just a date field.
            if (!preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $text, $match)) {
                // Or not
                return $text;
            }
            $newtext = $text.'T000000';
            $dateParts = explode('T', $newtext);
        }

        if (!$date = Horde_iCalendar::_parseDate($dateParts[0])) {
            return $text;
        }
        if (!$time = Horde_iCalendar::_parseTime($dateParts[1])) {
            return $text;
        }

        // Get timezone info for date fields from $tzid and container.
        $tzoffset = ($time['zone'] == 'Local' && $tzid && is_a($this->_container, 'Horde_iCalendar'))
            ? $this->_parseTZID($date, $time, $tzid) : false;
        if ($time['zone'] == 'UTC' || $tzoffset !== false) {
            $result = @gmmktime($time['hour'], $time['minute'], $time['second'],
                                $date['month'], $date['mday'], $date['year']);
            if ($tzoffset) {
                $result -= $tzoffset;
            }
        } else {
            // We don't know the timezone so assume local timezone.
            // FIXME: shouldn't this be based on the user's timezone
            // preference rather than the server's timezone?
            $result = @mktime($time['hour'], $time['minute'], $time['second'],
                              $date['month'], $date['mday'], $date['year']);
        }

        return ($result !== false) ? $result : $text;
    }

    /**
     * Export a DateTime field.
     */
    function _exportDateTime($value)
    {
        if (is_numeric($value)) {
            $temp = array();
            $tz = date('O', $value);
            $TZOffset = (3600 * substr($tz, 0, 3)) + (60 * substr(date('O', $value), 3, 2));
            $value -= $TZOffset;

            $temp['zone']   = 'UTC';
            $temp['year']   = date('Y', $value);
            $temp['month']  = date('n', $value);
            $temp['mday']   = date('j', $value);
            $temp['hour']   = date('G', $value);
            $temp['minute'] = date('i', $value);
            $temp['second'] = date('s', $value);
            return Horde_iCalendar::_exportDate($temp) . 'T' . Horde_iCalendar::_exportTime($temp);
        } else if (is_object($value) || is_array($value)) {
            $dateOb = new Horde_Date($value);
            return Horde_iCalendar::_exportDateTime($dateOb->timestamp());
        }
        return $value; // nothing to do with us, let's not touch it
    }

    /**
     * Parses a Time field.
     *
     * @static
     */
    function _parseTime($text)
    {
        if (preg_match('/([0-9]{2})([0-9]{2})([0-9]{2})(Z)?/', $text, $timeParts)) {
            $time['hour'] = intval($timeParts[1]);
            $time['minute'] = intval($timeParts[2]);
            $time['second'] = intval($timeParts[3]);
            if (isset($timeParts[4])) {
                $time['zone'] = 'UTC';
            } else {
                $time['zone'] = 'Local';
            }
            return $time;
        } else {
            return false;
        }
    }

    /**
     * Exports a Time field.
     */
    function _exportTime($value)
    {
        $time = sprintf('%02d%02d%02d',
                        $value['hour'], $value['minute'], $value['second']);
        if ($value['zone'] == 'UTC') {
            $time .= 'Z';
        }
        return $time;
    }

    /**
     * Parses a Date field.
     *
     * @static
     */
    function _parseDate($text)
    {
        $parts = explode('T', $text);
        if (count($parts) == 2) {
            $text = $parts[0];
        }

        if (!preg_match('/^(\d{4})-?(\d{2})-?(\d{2})$/', $text, $match)) {
            return false;
        }

        return array('year' => $match[1],
                     'month' => $match[2],
                     'mday' => $match[3]);
    }

    /**
     * Exports a date field.
     *
     * @param object|array $value  Date object or hash.
     * @param string $autoconvert  If set, use this as time part to export the
     *                             date as datetime when exporting to Vcalendar
     *                             1.0. Examples: '000000' or '235959'
     */
    function _exportDate($value, $autoconvert = false)
    {
        if (is_object($value)) {
            $value = array('year' => $value->year, 'month' => $value->month, 'mday' => $value->mday);
        }
        if ($autoconvert !== false && $this->isOldFormat()) {
            return sprintf('%04d%02d%02dT%s', $value['year'], $value['month'], $value['mday'], $autoconvert);
        } else {
            return sprintf('%04d%02d%02d', $value['year'], $value['month'], $value['mday']);
        }
    }

    /**
     * Parse a Duration Value field.
     */
    function _parseDuration($text)
    {
        if (preg_match('/([+]?|[-])P(([0-9]+W)|([0-9]+D)|)(T(([0-9]+H)|([0-9]+M)|([0-9]+S))+)?/', trim($text), $durvalue)) {
            // Weeks.
            $duration = 7 * 86400 * intval($durvalue[3]);

            if (count($durvalue) > 4) {
                // Days.
                $duration += 86400 * intval($durvalue[4]);
            }
            if (count($durvalue) > 5) {
                // Hours.
                $duration += 3600 * intval($durvalue[7]);

                // Mins.
                if (isset($durvalue[8])) {
                    $duration += 60 * intval($durvalue[8]);
                }

                // Secs.
                if (isset($durvalue[9])) {
                    $duration += intval($durvalue[9]);
                }
            }

            // Sign.
            if ($durvalue[1] == "-") {
                $duration *= -1;
            }

            return $duration;
        } else {
            return false;
        }
    }

    /**
     * Export a duration value.
     */
    function _exportDuration($value)
    {
        $duration = '';
        if ($value < 0) {
            $value *= -1;
            $duration .= '-';
        }
        $duration .= 'P';

        $weeks = floor($value / (7 * 86400));
        $value = $value % (7 * 86400);
        if ($weeks) {
            $duration .= $weeks . 'W';
        }

        $days = floor($value / (86400));
        $value = $value % (86400);
        if ($days) {
            $duration .= $days . 'D';
        }

        if ($value) {
            $duration .= 'T';

            $hours = floor($value / 3600);
            $value = $value % 3600;
            if ($hours) {
                $duration .= $hours . 'H';
            }

            $mins = floor($value / 60);
            $value = $value % 60;
            if ($mins) {
                $duration .= $mins . 'M';
            }

            if ($value) {
                $duration .= $value . 'S';
            }
        }

        return $duration;
    }


   /**
     * Convert an 8bit string to a base64 string
     * to RFC2045, section 6.7.
     *
     * @param  string $input  The string to be encoded.
     *
     * @return string         The base64 encoded string.
     */
    function _base64Encode($input = '')
    {
        return base64_encode($input);
    }


    /**
     * Converts an 8bit string to a quoted-printable string according to RFC
     * 2045, section 6.7.
     *
     * imap_8bit() does not apply all necessary rules.
     *
     * @param string $input  The string to be encoded.
     *
     * @return string  The quoted-printable encoded string.
     */
    function _quotedPrintableEncode($input = '', $withFolding=true)
    {
        $output = $line = '';
        $len = bytes($input);

        for ($i = 0; $i < $len; ++$i) {
            $ord = ord($input[$i]);
            // Encode non-printable characters (rule 2).
            if ($ord == 9 ||
                ($ord >= 32 && $ord <= 60) ||
                ($ord >= 62 && $ord <= 126)) {
                $chunk = $input[$i];
            } else {
                // Quoted printable encoding (rule 1).
                $chunk = '=' . String::upper(sprintf('%02X', $ord));
            }
            $line .= $chunk;
            // Wrap long lines (rule 5)
            if ($withFolding && strlen($line) + 1 > 76) {
                $line = String::wordwrap($line, 75, "=\r\n", true, 'us-ascii', true);
                $newline = strrchr($line, "\r\n");
                if ($newline !== false) {
                    $output .= substr($line, 0, -strlen($newline) + 2);
                    $line = substr($newline, 2);
                } else {
                    $output .= $line;
                }
                continue;
            }
            // Wrap at line breaks for better readability (rule 4).
            if ($withFolding && substr($line, -3) == '=0A') {
                $output .= $line . "=\r\n";
                $line = '';
            }
        }
        $output .= $line;

        // Trailing whitespace must be encoded (rule 3).
        $lastpos = strlen($output) - 1;
        if ($output[$lastpos] == chr(9) ||
            $output[$lastpos] == chr(32)) {
            $output[$lastpos] = '=';
            $output .= String::upper(sprintf('%02X', ord($output[$lastpos])));
        }

        return $output;
    }

}
