<?php
/**
 * Calendar2 recurrence rule helper.
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @category   PHProjekt
 * @package    Application
 * @subpackage Calendar2
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Simon Kohlmeyer <simon.kohlmeyer@mayflower.de>
 */

/**
 * Calendar2 recurrence rule Helper.
 *
 * This class is used to create a set of dates from a start date and an
 * recurrence rule.
 *
 * The rrule format is defined in RFC 5545.
 *
 * @category   PHProjekt
 * @package    Application
 * @subpackage Calendar2
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Simon Kohlmeyer <simon.kohlmeyer@mayflower.de>
 */
class Calendar2_Helper_Rrule
{
    /**
     * @var Datetime The first occurrence of the event.
     */
    private $_first;

    /**
     * @var array of String => mixed The rrule properties.
     *
     * 'FREQ'         => DateInterval between occurences
     * 'INTERVAL'     => int
     * 'FREQINTERVAL' => FREQUENCY, but INTERVAL times
     * 'UNTIL'        => DateTime (inclusive)
     *                   (Note that DatePeriod expects exclusive UNTIL values)
     */
    private $_rrule;

    /**
     * The original rrule string.
     *
     * @var string
     */
    private $_rruleString;

    /**
     * @var array of Datetime Dates to exclude.
     */
    private $_exceptions;

    /**
     * Constructor.
     *
     * @param Datetime $first   The first occurence of the event.
     * @param String   $rrule   The recurrence rule.
     * @param Array of Datetime Exceptions from the recurrence.
     */
    public function __construct(Datetime $first, $rrule, Array $exceptions = array())
    {
        $this->_first       = $first;
        $this->_rrule       = $this->_parseRrule($rrule);
        $this->_rruleString = $rrule;
        $this->_exceptions  = $exceptions;
    }

    /**
     * Retrieves all the single events in the given period.
     *
     * @param Datetime $start The start of the period.
     * @param datetime $end   The end of the period.
     *
     * @return Array of Datetime The single events.
     */
    public function getDatesInPeriod(Datetime $start, Datetime $end)
    {
        $firstTs = $this->_first->getTimestamp();
        $startTs = $start->getTimestamp();
        $endTs   = $end->getTimestamp();

        if (empty($this->_rrule)) {
            // There is no recurrence
            if ($firstTs >= $startTs && $firstTs <= $endTs) {
                return array($this->_first);
            } else {
                return array();
            }
        }

        if (!is_null($this->_rrule['UNTIL'])) {
            $until = clone $this->_rrule['UNTIL'];
        } else {
            $until = clone $end;
        }
        // php datePeriod also excludes the last occurence. we need it, so
        // we add one second.
        $until->modify('+1 second');
        $period = new DatePeriod(
            $this->_first,
            $this->_rrule['FREQINTERVAL'],
            $until
        );

        $ret = array();
        foreach ($period as $date) {
            // Work around http://bugs.php.net/bug.php?id=52454
            // 'Relative dates and getTimestamp increments by one day'
            $datestring = $date->format('Y-m-d H:i:s');
            $date       = new Datetime($datestring, new DateTimeZone('UTC'));

            $ts = $date->getTimestamp();
            if ($startTs <= $ts && $ts <= $endTs && !in_array($date, $this->_exceptions)) {
                $ret[] = new Datetime($datestring, new DateTimeZone('UTC'));
            } else if ($ts > $endTs) {
               break;
            }
        }
        return $ret;
    }

    /**
     * Checks whether a datetime is one of the dates described by this rrule.
     *
     * @param Datetime $date The time to check for.
     *
     * @return bool If the given time is an occurence of this rrule.
     */
    public function containsDate(Datetime $date)
    {
        $dates = $this->getDatesInPeriod($date, $date);

        return (0 < count($dates));
    }

    /**
     * Splits this helper's rrule in 2 parts, one for all events before the
     * split date and one for all other occurences.
     *
     * @param Datetime $splitDate The first occurence of the second part.
     *
     * @return array See description
     */
    public function splitRrule(Datetime $splitDate)
    {
        if (empty($this->_rrule)) {
            return array('old' => '', 'new' => '');
        } elseif (is_null($this->_rrule['UNTIL'])) {
            // The recurrence never ends, no need to calculate anything
            $last  = $this->lastOccurrenceBefore($splitDate);
            $until = "UNTIL={$last->format('Ymd\THis\Z')};";
            $old   = $until . $this->_rruleString;
        } else {
            $dates = $this->getDatesInPeriod($this->_first, $splitDate);
            $lastBeforeSplit = $dates[count($dates) - 2];

            $old = preg_replace(
                '/UNTIL=[^;]*/',
                "UNTIL={$lastBeforeSplit->format('Ymd\THis\Z')}",
                $this->_rruleString
            );
        }

        return array('old' => $old, 'new' => $this->_rruleString);
    }

    /**
     * Checks whether the given Datetime is the last occurrence of this series.
     *
     * @param Datetime $datetime The datetime to check for.
     *
     * @return bool Whether the given datetime is the last occurrence.
     */
    public function isLastOccurrence(Datetime $datetime)
    {
        if (empty($this->_rrule)) {
            return $datetime == $this->_first;
        }

        $until = $this->_rrule['UNTIL'];

        if (is_null($until)) {
            return false;
        } else {
            return $datetime->getTimestamp() == $until->getTimestamp();
        }
    }

    /**
     * Checks whether the given Datetime is the first occurrence of this series.
     *
     * @param Datetime $datetime The datetime to check for.
     *
     * @return bool Whether the given datetime is the first occurrence.
     */
    public function isFirstOccurrence(Datetime $datetime)
    {
        return $this->_first == $datetime;
    }

    /**
     * Returns the first occurrence after the given datetime
     * This assumes that the given date is a valid occurrence.
     * If this is the last occurrence, null will be returned.
     *
     * @param Datetime $datetime The datetime after which to look.
     *
     * @return Datetime The first occurrence after $datetime or null.
     */
    public function firstOccurrenceAfter(Datetime $datetime)
    {
        if (!$this->containsDate($datetime)) {
            throw new Exception('Invalid Datetime given.');
        }

        if (empty($this->_rrule)) {
            return null;
        }

        $occurrence = clone $datetime;
        do {
            $occurrence->add($this->_rrule['FREQINTERVAL']);
        } while (in_array($occurrence, $this->_exceptions));

        $until = $this->_rrule['UNTIL'];
        if (!is_null($until)) {
            $untilTs = $until->getTimestamp();
            $occurrenceTs = $occurrence->getTimestamp();
            if ($untilTs < $occurrenceTs) {
                return null;
            }
        }

        return $occurrence;
    }
    /**
     * Returns the last occurrence before the given datetime
     * This assumes that the given date is a valid occurrence.
     * If this is the first occurrence, null will be returned.
     *
     * @param Datetime $datetime The datetime until which to look.
     *
     * @return Datetime The last occurrence before $datetime
     */
    public function lastOccurrenceBefore(Datetime $datetime)
    {
        if (!$this->containsDate($datetime)) {
            throw new Exception('Invalid Datetime given.');
        }

        if ($datetime == $this->_first) {
            return null;
        }

        $occurrence = clone $datetime;
        do {
            $occurrence->sub($this->_rrule['FREQINTERVAL']);
        } while (in_array($occurrence, $this->_exceptions));

        return $occurrence;
    }

    /**
     * Parses a rrule string into a dictionary while working around all
     * specialities of iCalendar, so we have values in $this->_rrule that
     * a php programmer would expect. See there for exact documentation.
     *
     * @param string $rrule The rrule.
     *
     * @return array of string => mixed The properties and their values.
     */
    private function _parseRrule($rrule)
    {
        if (empty($rrule)) {
            return array();
        }

        $ret = array();
        $ret['INTERVAL']  = self::_parseInterval($rrule);
        $ret['FREQ']      = self::_parseFreq($rrule);
        $ret['UNTIL']     = self::_parseUntil($rrule);

        // Apply FREQ INTERVAL times
        $tmp  = new Datetime();
        $tmp2 = clone $tmp;
        for ($i = 0; $i < $ret['INTERVAL']; $i++) {
            $tmp2->add($ret['FREQ']);
        }
        $ret['FREQINTERVAL'] = $tmp->diff($tmp2);

        return $ret;
    }

    private static function _parseInterval($rrule)
    {
        $interval = self::_extractFromRrule($rrule, 'INTERVAL');
        if (is_null($interval)) {
            $interval = 1;
        } else if (0 >= $interval) {
            throw new Exception('Negative or Zero Intervals not permitted.');
        }
        return (int) $interval;
    }

    private static function _parseFreq($rrule)
    {
        $freq = self::_extractFromRrule($rrule, 'FREQ');
        if (empty($freq)) {
            // Violates RFC 5545
            throw new Exception('Rrule contains no FREQ');
        }
        switch ($freq) {
            case 'DAILY':
                return new DateInterval("P1D");
                break;
            case 'WEEKLY':
                return new DateInterval("P1W");
                break;
            case 'MONTHLY':
                return new DateInterval("P1M");
                break;
            case 'YEARLY':
                return new DateInterval("P1Y");
                break;
            default:
                // We don't know how to handle anything else.
                throw new Exception("Cannot handle rrule frequency $freq");
        }
    }

    private static function _parseUntil($rrule)
    {
        // Format is yyyymmddThhiissZ.
        $until = self::_extractFromRrule($rrule, 'UNTIL');
        if (!empty($until)) {
            if (false !== strpos($until, 'TZID')) {
                // We have a time with timezone, can't handle that
                throw new Exception('Cannot handle rrules with timezone information');
            } else if (false === strpos($until, 'Z')) {
                // A floating time, can't handle those either.
                throw new Exception('Cannot handle floating times in rrules.');
            } else if (!preg_match('/\d{8}T\d{6}Z/', $until)) {
                throw new Exception('Malformed until');
            }

            // php doesn't understand ...Z as an alias for UTC
            $return = new Datetime(substr($until, 0, 15), new DateTimeZone('UTC'));
            return $return;
        }
        return null;
    }

    /**
     * Helper function for parseRrule
     */
    private static function _extractFromRrule($rrule, $prop)
    {
        //TODO: Maybe optimize this to a single match?
        $matches = array();
        $prop = preg_quote($prop, '/');
        preg_match("/$prop=([^;]+)/", $rrule, $matches);

        if (array_key_exists(1, $matches)) {
            return $matches[1];
        } else {
            return null;
        }
    }

}