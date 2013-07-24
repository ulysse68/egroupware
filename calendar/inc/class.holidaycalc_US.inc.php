<?php
	/**************************************************************************\
	* eGroupWare - holidaycalc_US                                              *
	* http://www.egroupware.org                                                *
	* Based on Yoshihiro Kamimura <your@itheart.com>                           *
	*          http://www.itheart.com                                          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id: class.holidaycalc_US.inc.php 19688 2005-11-08 23:15:14Z ralfbecker $ */

	/**
	 * Calculations for calendar US and other holidays
	 *
	 * @package calendar
	 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
	 */
	class holidaycalc
	{
		function add($holiday,&$holidays,$year,$day_offset=0)
		{
			if ($day_offset)
			{
				$holiday['name'] .= ' (Observed)';
			}
			$holiday['date'] = mktime(0,0,0,$holiday['month'],$holiday['day']+$day_offset,$year);
			foreach(array('day'=>'d','month'=>'m','occurence'=>'Y') as $key => $frmt)
			{
				$holiday[$key] = date($frmt,$holiday['date']);
			}
			$holiday['obervance_rule'] = 0;

			$holidays[]= $holiday;

			//echo "<p>holidaycalc::add(,,$year,,$day_offset)=".print_r($holiday,True)."</p>";
		}

		function calculate_date($holiday, &$holidays, $year)
		{
			//echo "<p>holidaycalc::calculate_date(".print_r($holiday,True).",,$year,)</p>";

			if($holiday['day'] == 0 && $holiday['occurence'] != 0)
			{
				if($holiday['occurence'] != 99)
				{
					$dow = $GLOBALS['egw']->datetime->day_of_week($year,$holiday['month'],1);
					$day = (((7 * $holiday['occurence']) - 6) + ((($holiday['dow'] + 7) - $dow) % 7));
					$day += ($day < 1 ? 7 : 0);

					// Sometimes the 5th occurance of a weekday (ie the 5th monday)
					// can spill over to the next month.  This prevents that.
					$ld = $GLOBALS['egw']->datetime->days_in_month($holiday['month'],$year);
					if ($day > $ld)
					{
						return;
					}
				}
				else
				{
					$ld = $GLOBALS['egw']->datetime->days_in_month($holiday['month'],$year);
					$dow = $GLOBALS['egw']->datetime->day_of_week($year,$holiday['month'],$ld);
					$day = $ld - (($dow + 7) - $holiday['dow']) % 7 ;
				}
			}
			else
			{
				$day = $holiday['day'];
				if($holiday['observance_rule'] == True)
				{
					$dow = $GLOBALS['egw']->datetime->day_of_week($year,$holiday['month'],$day);
					// This now calulates Observed holidays and creates a new entry for them.

					// 0 = sundays are observed on monday (+1), 6 = saturdays are observed on fridays (-1)
					if($dow == 0 || $dow == 6)
					{
						$this->add($holiday,$holidays,$year,$dow == 0 ? 1 : -1);
					}
					if ($holiday['month'] == 1 && $day == 1)
					{
						$dow = $GLOBALS['egw']->datetime->day_of_week($year+1,$holiday['month'],$day);
						// checking if next year's newyear might be observed in this year
						if ($dow == 6)
						{
							$this->add($holiday,$holidays,$year+1,-1);
						}
						// add the next years newyear, to show it in a week- or month-view
						$this->add($holiday,$holidays,$year+1);
					}
					// checking if last year's new year's eve might be observed in this year
					if ($holiday['month'] == 12 && $day == 31)
					{
						$dow = $GLOBALS['egw']->datetime->day_of_week($year-1,$holiday['month'],$day);
						if ($dow == 0)
						{
							$this->add($holiday,$holidays,$year-1,1);
						}
						// add the last years new year's eve, to show it in a week- or month-view
						$this->add($holiday,$holidays,$year-1);
					}
				}
			}
			$date = mktime(0,0,0,$holiday['month'],$day,$year);

			return $date;
		}
	}
?>
