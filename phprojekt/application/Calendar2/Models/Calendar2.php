<?php
/**
 * Calendar2 model class.
 *
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details
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
 * Calendar2 model class.
 *
 * An object of this class corresponds to a series of objects.
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
class Calendar2_Models_Calendar2 extends Phprojekt_Item_Abstract
{
    /**
     * Values for the status value
     */
    const STATUS_PENDING  = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_REJECTED = 3;

    /**
     * Values for the visibility
     */
    const VISIBILITY_PUBLIC  = 1;
    const VISIBILITY_PRIVATE = 2;

    /** Overwrite field to display in the search results. */
    public $searchFirstDisplayField = 'summary';

    /**
     * The confirmation statuses of this event's participants.
     *
     * Must never be null if $_participantData is not null.
     *
     * @var array of (int => int) (ParticipantId => Status)
     */
    protected $_participantData = null;

    /**
     * The participant data of the event as it is stored in the database.
     *
     * @var array of (int => int) (ParticipantId => Status)
     */
    protected $_participantDataInDb = null;

    /**
     * Whether this is the first occurence of this series.
     *
     * @var boolean
     */
    protected $_isFirst = true;

    /**
     * Holds the original start date of this event as stored in the db.
     * Is never null if this event exists in the db.
     *
     * @var Datetime
     */
    protected $_originalStart = null;

    /**
     * Checks if the given value is a valid status.
     *
     * @param mixed $value The value to check.
     *
     * @return If $value is a valid status.
     */
    public static function isValidStatus($var)
    {
        if ($var === self::STATUS_PENDING
                || $var === self::STATUS_ACCEPTED
                || $var === self::STATUS_REJECTED) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the given value is a valid visibility.
     *
     * @param mixed $value The value to check.
     *
     * @return If $value is a valid status.
     */
    public static function isValidVisibility($var)
    {
        if ($var === self::VISIBILITY_PUBLIC
                || $var === self::VISIBILITY_PRIVATE) {
            return true;
        } elseif (is_string($var)) {
            return self::isValidVisibility((int) $var);
        } else {
            return false;
        }
    }

    /**
     * Constructor.
     */
    public function __construct($db = null)
    {
        parent::__construct($db);

        // This is needed to make fields read-only if we're not the owner.
        $this->_dbManager
            = new Calendar2_Models_CalendarInformation($this, $db);

        // UID generation method taken from rfc 5545
        $this->uid = rand()
                   . '-' . time()
                   . '-' . getMyPid()
                   . '@' . php_uname('n');
    }

    /**
     * Overwrite save function. Writes the data in this object into the
     * database.
     *
     * @return int The id of the saved object
     */
    public function save()
    {
        if (!$this->recordValidate()) {
            $errors = $this->getError();
            $error  = array_pop($errors);
            throw new Phprojekt_PublishedException($error['label'] . ': ' . $error['message']);
        }

        if ($this->_isFirst) {
            if (!self::isValidVisibility($this->visibility)) {
                throw new Phprojekt_PublishedException(
                    "Invalid visibility {$this->visibility}"
                );
            }

            $this->_fetchParticipantData();

            // We need to check this before we call parent::save() as it will be
            // set after.
            $isNew = empty($this->_storedId);

            $now = new Datetime('now', new DateTimeZone('UTC'));
            $this->lastModified = $now->format('Y-m-d H:i:s');
            parent::save();
            $this->_saveParticipantData();
            $this->_updateRights();

            // If the start time has changed, we have to adjust all the excluded
            // dates.
            $start = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($this->start)
            );
            if (!$isNew && $start != $this->_originalStart) {
                $delta = $this->_originalStart->diff($start);
                $db = $this->getAdapter();
                foreach ($this->getExcludedDates() as $date) {
                    $where  = $db->quoteInto('calendar2_id = ?', $this->id);
                    $where .= $db->quoteInto(
                        'AND date = ?',
                        $date->format('Y-m-d H:i:s')
                    );
                    $date->add($delta);
                    $db->update(
                        'calendar2_excluded_dates',
                        array('date' => $date->format('Y-m-d H:i:s')),
                        $where
                    );
                }
            }
        } else {
            // Split the series into two parts. $this will be the second part.
            $new = $this;
            $old = $this->create();
            $old->find($this->id);

            $splitDate = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($new->start)
            );
            $helper = $old->getRruleHelper();
            $rrules = $helper->splitRrule($splitDate);

            $old->rrule = $rrules['old'];
            $new->rrule = $rrules['new'];

            $old->save();
            $new->_saveToNewRow();

            // Update the excluded occurences
            $where  = "id = {$old->id} ";
            $where .= "AND date >= '{$splitDate->format('Y-m-d H:i:s')}'";

            $this->getAdapter()->update(
                'calendar2_excluded_dates',
                array('id' => $new->id),
                $where
            );
        }

        $this->_originalStart = new Datetime(
            '@' . Phprojekt_Converter_Time::userToUtc($this->start)
        );
        return $this->id;
    }

    /**
     * Saves this single Occurence.
     * If it's part of a recurring series, it will be extracted from the series.
     *
     * @return int The (new) id of this event.
     */
    public function saveSingleEvent()
    {
        if (!$this->recordValidate()) {
            $errors = $this->getError();
            $error  = array_pop($errors);
            throw new Phprojekt_PublishedException($error['label'] . ': ' . $error['message']);
        }

        if (is_null($this->_storedId)) {
            // This event is not saved yet. Reset the rrule and save it.
            $this->rrule = null;
            return $this->save();
        }

        $start = new Datetime(
            '@' . Phprojekt_Converter_Time::userToUtc($this->start)
        );

        $series = clone $this;
        $series->find($this->id);
        $series->_excludeDate($this->_originalStart);
        $series->save();

        $this->_data['rrule'] = null;
        $this->_isFirst       = true;
        $this->_saveToNewRow();

        return $this->id;
    }

    /**
     * Deletes all events in this series beginning with this one.
     *
     * @return void.
     */
    public function delete($sendNotifications = false)
    {
        $db = $this->getAdapter();

        if ($this->_isFirst) {
            $db->delete(
                'calendar2_user_relation',
                $db->quoteInto('calendar2_id = ?', $this->id)
            );
            $db->delete(
                'calendar2_excluded_dates',
                $db->quoteInto('calendar2_id = ?', $this->id)
            );

            if ($sendNotification) {
                $this->_notify('delete');
            }

            Phprojekt_Tags::getInstance()->deleteTagsByItem(
                Phprojekt_Module::getId('Calendar2'),
                $this->id
            );

            parent::delete();
        } else {
            $first = clone $this;
            $first->find($this->id);

            $start = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($this->start)
            );
            // Adjust the rrule.
            $helper = $first->getRruleHelper();
            $split  = $helper->splitRrule($start);
            $first->rrule = $split['old'];
            $first->save();

            // Delete all excludes after this event.
            $where  = $db->quoteInto('calendar2_id = ?', $this->id);
            $where .= $db->quoteInto(
                'AND date >= ?',
                $start->format('Y-m-d H:i:s')
            );
            $db->delete(
                'calendar2_excluded_dates',
                $where
            );

            if ($sendNotification) {
                $this->_notify('edit');
            }
        }
    }

    /**
     * Deletes just the single event out of this series.
     *
     * @return void
     */
    public function deleteSingleEvent($sendNotifications = false)
    {
        if (empty($this->rrule)) {
            // If this is a non-recurring event, call delete()
            $this->delete();
        } else {
            $start = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($this->start)
            );
            $this->_excludeDate($start);
        }

        // Update the lastModified-Time
        // We can't assume that the user calls save() after this method, so we
        // have to update the database here.
        $now = new Datetime('now', new DateTimeZone('UTC'));
        $this->lastModified = $now->format('Y-m-d H:i:s');
        $this->update(
            array('last_modified' => $now->format('Y-m-d H:i:s')),
            $this->getAdapter()->quoteInto('id = ?', $this->id)
        );

        if ($sendNotifications) {
            $this->_notify('edit');
        }
    }

    /**
     * Implemented because we need to reset the participant data.
     */
    public function find()
    {
        $this->_isFirst             = true;
        $this->_participantData     = null;
        $this->_participantDataInDb = null;

        // This is very uncool, but activeRecord declares find()
        // while expecting find($id)...
        $args = func_get_args();

        if (1 != count($args)) {
            throw new Phprojekt_ActiveRecord_Exception(
                'Wrong number of arguments for find'
            );
        }

        $find = parent::find($args[0]);
        if (is_object($find)) {
            $find->_originalStart = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($find->start)
            );
        }

        return $find;
    }

    /**
     * Implemented because we need to reset the participant data.
     */
    public function __clone()
    {
        parent::__clone();
        $this->_participantData     = null;
        $this->_participantDataInDb = null;
        $this->_originalStart       = null;
        $this->_isFirst             = true;
    }

    /**
     * Get all events in the period that the currently active user participates
     * in. Both given times are inclusive.
     *
     * Note that even if $user is given and different from the current user,
     * only public events will be returned.
     *
     * @param Datetime  $start The start of the period.
     * @param Datetime  $end   The end of the period.
     * @param int|array $users The id of the user(s) to query for. If omitted
     *                         or null, the currently logged in user is used.
     *
     * @return array of Calendar2_Models_Calendar
     */
    public function fetchAllForPeriod(Datetime $start, Datetime $end,
                                      $users = null)
    {
        // Normalize the $users argument to be an array of ids
        if (is_null($users)) {
            // Default to the current user.
            $users = array(Phprojekt_Auth::getUserId());
        } else if (!is_array($users)) {
            $users = array($users);
        }

        // Query the database for the events.
        $db    = $this->getAdapter();
        $where = $db->quoteInto('start <= ?', $end->format('Y-m-d H:i:s'));
        foreach ($users as $user) {
            $w = $db->quoteInto(
                'calendar2_user_relation.user_id = ? ',
                (int) $users
            );

            if (Phprojekt_Auth::getUserId() != $users) {
                $w = '(' . $where . $db->quoteInto(
                    ' AND calendar2.visibility != ?',
                    (int) self::VISIBILITY_PRIVATE
                ) . ')';
            }
            $where .= $w;
        }

        //TODO: This might query a lot of objects. Consider saving the last
        //      date of occurrence too so this is faster.
        $join  = 'JOIN calendar2_user_relation '
                    . 'ON calendar2.id = calendar2_user_relation.calendar2_id';
        // We order by id so that we can remove duplicates in linear time in
        // php. As id is an index on calendar2, this should not impact
        // performance much. (This is a guess, not a measurement.)
        $order  = 'calendar2.id';

        // This is a horrible hack. Phprojekt_ActiveRecord_Abstract::fetchAll is
        // not static, but has to be called that way because we need events that
        // the current user might not have rights to see.
        $models = Phprojekt_ActiveRecord_Abstract::fetchAll(
            $where, $order, null, null, null, $join
        );

        // If we have more than one user, we need to remove duplicated models
        if (count($users) > 1) {
            $lastId = -1; // Sql ids are always positive, so this is safe start.
            foreach ($models as $key => $model) {
                if ($model->id === $lastId) {
                    unset($models[$key]);
                } else {
                    $lastId = $model->id;
                }
            }
        }

        // Expand the recurrences.
        $ret = array();
        foreach ($models as $model) {
            $startDt  = new Datetime($model->start);
            $endDt    = new Datetime($model->end);
            $duration = $startDt->diff($endDt);

            $helper = $model->getRruleHelper();
            foreach ($helper->getDatesInPeriod($start, $end) as $date) {
                $m        = $model->copy();
                $m->start = Phprojekt_Converter_Time::utcToUser(
                    $date->format('Y-m-d H:i:s')
                );
                $m->_originalStart = clone $date;
                $date->add($duration);
                $m->end = Phprojekt_Converter_Time::utcToUser(
                    $date->format('Y-m-d H:i:s')
                );
                $m->_isFirst = ($m->start == $model->start);
                $isFirst     = false;
                $ret[]       = $m;
            }
        }

        return $ret;
    }

    /**
     * Finds a special occurence of an event. Will throw an exception
     * if the date is not part of this event.
     *
     * @param int      $id   The id of the event to find.
     * @param Datetime $date The date of the occurence.
     *
     * @throws Exception If $date is no occurence of this event.
     *
     * @return $this
     */
    public function findOccurrence($id, Datetime $date)
    {
        $find = $this->find($id);
        if ($find !== $this) {
            // Some error occured, maybe the id doesn't exist in the db
            return $find;
        }

        $date->setTimezone(new DateTimeZone('UTC'));

        $excludes = $this->getAdapter()->fetchCol(
            'SELECT date FROM calendar2_excluded_dates WHERE calendar2_id = ?',
            $id
        );
        if (in_array($date->format('Y-m-d H:i:s'), $excludes)) {
            return null;
        }

        $start = new Datetime(
            '@'.Phprojekt_Converter_Time::userToUtc($this->start)
        );

        if ($date != $start) {
            if (!$this->getRruleHelper()->containsDate($date)) {
                throw new Exception(
                    "Occurence on {$date->format('Y-m-d H:i:s')}, "
                    . "{$date->getTimezone()->getName()} not found."
                );
            }
            $end = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($this->end)
            );
            $duration = $start->diff($end);

            $start = $date;
            $end   = clone $start;
            $end->add($duration);

            $this->start = Phprojekt_Converter_Time::utcToUser(
                $start->format('Y-m-d H:i:s')
            );
            $this->end = Phprojekt_Converter_Time::utcToUser(
                $end->format('Y-m-d H:i:s')
            );

            $this->_originalStart = $start;
            $this->_isFirst = false;
        }

        return $this;
    }

    /**
     * Get the participants of this event.
     *
     * @return array of int
     */
    public function getParticipants()
    {
        $this->_fetchParticipantData();
        return array_keys($this->_participantData);
    }

    /**
     * Returns whether the given user participates in this event.
     *
     * @param int $id The id of the user to check for.
     *
     * @return boolean Whether the user participates in the event.
     */
    public function hasParticipant($id)
    {
        $this->_fetchParticipantData();
        return array_key_exists($id, $this->_participantData);
    }

    /**
     * Set the participants of this event.
     * All confirmation statuses will be set to pending.
     *
     * @param array of int $ids The ids of the participants.
     *
     * @return void
     */
    public function setParticipants(array $ids)
    {
        $hasOwner = !is_null($this->ownerId);
        if ($hasOwner) {
            $this->_fetchParticipantData();
            $ownerStatus = $this->_participantData[$this->ownerId];
        }

        $this->_participantData = array();

        if ($hasOwner) {
            $this->addParticipant($this->ownerId, $ownerStatus);
        }

        foreach ($ids as $id) {
            $this->addParticipant($id);
        }
    }

    /**
     * Add a single Participant to the event.
     *
     * @param int $id The id of the participant to add.
     *
     * @return void
     */
    public function addParticipant($id, $status = self::STATUS_PENDING)
    {
        $this->_fetchParticipantData();

        if ($this->hasParticipant($id)) {
            throw new Exception("Tried to add already participating user $id");
        } elseif (!self::isValidStatus($status)) {
            throw new Exception("Tried to save invalid status $status");
        }
        $this->_participantData[$id] = $status;
    }

    /**
     * Removes a single Participant from the event.
     *
     * @param int $id The id of the participant to remove.
     *
     * @return void
     */
    public function removeParticipant($id)
    {
        $this->_fetchParticipantData();

        if (!$this->hasParticipant($id)) {
            throw new Exception("Tried to remove unknown participant $id");
        } else if ($id == $this->ownerId) {
            throw new Exception('Must not remove owner');
        }
        unset($this->_participantData[$id]);
    }

    /**
     * Get a participant's confirmation status.
     *
     * If no id is given, the currently logged in user's status
     * will be returned.
     *
     * @param int $id The id of the participant.
     *
     * @return int
     */
    public function getConfirmationStatus($id = null)
    {
        if (is_null($id)) {
            $id = Phprojekt_Auth::getUserId();
        }
        $this->_fetchParticipantData();

        if (!$this->hasParticipant($id)) {
            // We can not throw an exception here because if a user edits a new
            // entry, a empty model object will be requested and serialized.
            // Returning null here will yield the default value as configured
            // in the database_manager table.
            return null;
        }
        return $this->_participantData[$id];
    }

    /**
     * Gets the confirmations status of all participants.
     *
     * @return array of (int => int) UserId => Confirmation status
     */
    public function getConfirmationStatuses()
    {
        $this->_fetchParticipantData();
        return $this->_participantData;
    }

    /**
     * Update a participant's confirmation status.
     *
     * @param int $id        The Id of the participant.
     * @param int $newStatus The new confirmation status.
     *
     * @return void.
     */
    public function setConfirmationStatus($id, $newStatus)
    {
        $this->_fetchParticipantData();

        if (!$this->hasParticipant($id)) {
            throw new Exception("Participant #$id not found");
        } elseif (!self::isValidStatus($newStatus)) {
            throw new Exception("Tried to save invalid status $newStatus");
        }

        $this->_participantData[$id] = $newStatus;
    }

    /**
     * Updates the confirmation statuses of all participants.
     * The owner's status will not be updated.
     *
     * @param int $status The new Status
     *
     * @return void.
     */
    public function setParticipantsConfirmationStatuses($status)
    {
        if (!self::isValidStatus($status)) {
            throw new Exception("Tried to save invalid status $status");
        }

        foreach ($this->participants as $p) {
            if ($p !== $this->ownerId) {
                $this->setConfirmationStatus($p, $status);
            }
        }
    }

    /**
     * Retrieves the recurrenceId as specified by the iCalendar standard.
     *
     * @return string The recurrence Id.
     */
    public function getRecurrenceId()
    {
        $dt = new Datetime(
            '@' . Phprojekt_Converter_Time::userToUtc($this->start)
        );
        return $dt->format('Ymd\THis');
    }

    /**
     * Returns the dates excluded from this reccurring event.
     *
     * @return array of Datetime
     */
    public function getExcludedDates()
    {
        //TODO: Maybe cache this?
        $excludes = $this->getAdapter()->fetchCol(
            'SELECT date FROM calendar2_excluded_dates WHERE calendar2_id = ?',
            $this->id
        );

        $ret = array();
        foreach ($excludes as $date) {
            $ret[] = new Datetime($date, new DateTimeZone('UTC'));
        }

        return $ret;
    }

    /**
     * Returns a textual representation of the rrule of this event.
     *
     * @return string The recurrence in human-readable form.
     */
    public function getRecurrence()
    {
        return $this->getRruleHelper()->getHumanreadableRrule();
    }

    /**
     * Returns a Calendar2_Helper_Rrule object initialized with this objects
     * start date, recurrence rule and excluded ocurrences.
     *
     * @return Calendar2_Helper_Rrule
     */
    public function getRruleHelper()
    {
        if ($this->_isFirst) {
            $start = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($this->start)
            );
        } else {
            $original = $this->create();
            $original->find($this->id);
            $start = new Datetime(
                '@' . Phprojekt_Converter_Time::userToUtc($original->start)
            );
        }

        return new Calendar2_Helper_Rrule(
            $start,
            $this->rrule,
            $this->getExcludedDates()
        );
    }

    /**
     * Sets the owner id.
     * The owner is also added as an participant with 'Accepted' as status.
     *
     * @param int $id The owner id.
     *
     * @return void.
     */
    public function setOwnerId($id)
    {
        $this->_fetchParticipantData();

        if ($this->hasParticipant($id)) {
            unset($this->_participantData[$id]);
        }
        $this->addParticipant($id, self::STATUS_ACCEPTED);

        $this->_data['ownerId'] = $id;
    }

    /**
     * Returns a copy of this model object.
     *
     * This function would best be written as __clone, but ActiveRecord
     * already implements __clone to create a new, empty model object
     * and we don't want to break the semantics of cloning model objects.
     */
    public function copy()
    {
        $m = new Calendar2_Models_Calendar2();
        $m->find($this->id);

        // use _data to bypass __set
        foreach ($this->_data as $k => $v) {
            $m->_data[$k] = $v;
        }
        $m->_participantData     = $this->_participantData;
        $m->_participantDataInDb = $this->_participantDataInDb;
        $m->_isFirst             = $this->_isFirst;
        $m->_originalStart       = $this->_originalStart;

        return $m;
    }

    /**
     * Send notification mails and (if configured) frontend messages for the
     * latest change.
     *
     * @return void
     **/
    public function notify(
            $method = Phprojekt_Notification::TRANSPORT_MAIL_TEXT)
    {
        $this->_notify(null, $method);
    }

    /**
     * Helper function to allow delete() et al to send notifications with for
     * their respective processes.
     */
    private function _notify(
            $process = null,
            $method = Phprojekt_Notification::TRANSPORT_MAIL_TEXT)
    {
        $notification = new Phprojekt_Notification();
        $notification->setModel($this);

        if (!is_null($process)) {
            $notification->setControllProcess($process);
        }
        $notification->send($method);
        if (Phprojekt::getInstance()->getConfig()->frontendMessages) {
            $notification->saveFrontendMessage();
        }
    }

    /**
     * Excludes the given date from this series.
     *
     * @param Datetime $date The date to remove
     *
     * @return void
     */
    protected function _excludeDate(Datetime $date)
    {
        // This function distinguishes three cases.
        // 1. This is the first event in the series.
        // 2. This is the last event in the series.
        // 3. Somewhere in between.
        if (empty($this->id)) {
            throw new Exception('Can only exclude dates from saved events');
        }

        $series = clone $this;
        $series->find($this->id);

        $helper = $series->getRruleHelper();

        if (!$helper->containsDate($date)) {
            throw new Exception(
                'Trying to exclude date that is not part of this series'
            );
        }
        $start  = new Datetime(
            '@' . Phprojekt_Converter_Time::userToUtc($series->start)
        );
        $end    = new Datetime(
            '@' . Phprojekt_Converter_Time::userToUtc($series->end)
        );

        if ($start == $date) {
            // If it's the first in it's series, adjust the start date,
            // remove excluded dates that we skipped while doing that and
            // finally, check if we still need a rrule at all.
            $duration = $start->diff($end);

            $newStart  = $helper->firstOccurrenceAfter($start);
            if (is_null($newStart)) {
                throw new Exception('$newStart should not be null');
            }
            $newEnd = clone $newStart;
            $newEnd->add($duration);

            $series->start = Phprojekt_Converter_Time::utcToUser(
                $newStart->format('Y-m-d H:i:s')
            );
            $series->end   = Phprojekt_Converter_Time::utcToUser(
                $newEnd->format('Y-m-d H:i:s')
            );

            // Delete all obsolete excludes
            $db     = $this->getAdapter();
            $where  = $db->quoteInto('calendar2_id = ?', $this->id);
            $where .= $db->quoteInto(
                'AND date < ?',
                $newStart->format('Y-m-d H:i:s')
            );
            $db->delete('calendar2_excluded_dates', $where);

            // Check if this is still a recurring event
            if ($helper->islastOccurrence($newStart)) {
                $series->rrule = null;
            }
            $series->save();
        } elseif ($helper->isLastOccurrence($date)) {
            // If it's the last in it's series, adjust the Rrule and delete
            // now obsolete excludes.
            $newLast = $helper->lastOccurrenceBefore($date);

            // Check if this is still a recurring event
            if ($helper->isFirstOccurrence($newLast)) {
                $series->rrule = null;
            } else {
                // Adjust the rrule
                $series->rrule = preg_replace(
                    '/UNTIL=[^;]*/',
                    "UNTIL={$newLast->format('Ymd\THis\Z')}",
                    $series->rrule
                );
            }
            $series->save();

            // Delete all obsolete excludes
            $db     = $this->getAdapter();
            $where  = $db->quoteInto('calendar2_id = ?', $this->id);
            $where .= $db->quoteInto(
                'AND date > ?',
                $newLast->format('Y-m-d H:i:s')
            );
            $db->delete('calendar2_excluded_dates', $where);
        } else {
            // If it's somewhere in between, just add it to the list of
            // excluded dates.
            $this->getAdapter()->insert(
                'calendar2_excluded_dates',
                array(
                    'calendar2_id' => $this->id,
                    'date'         => $date->format('Y-m-d H:i:s')
                )
            );
        }
    }

    /**
     * Get the participants and their confirmation statuses from the database.
     * Modifies $this->_participantData.
     *
     * @return void
     */
    private function _fetchParticipantData()
    {
        if (is_null($this->_participantDataInDb)) {
            if (null === $this->_storedId) {
                // We don't exist in the database. So we don't have any.
                $this->_participantDataInDb = array();
            } else {
                $this->_participantDataInDb = $this->getAdapter()->fetchPairs(
                    'SELECT user_id,confirmation_status '
                    . 'FROM calendar2_user_relation '
                    . 'WHERE calendar2_id = :id',
                    array('id' => $this->id)
                );
            }

            if (is_null($this->_participantData)) {
                // Avoid overwriting new data
                $this->_participantData = $this->_participantDataInDb;
            }
        }
    }

    /**
     * Saves the participants for this event.
     * This object must have already been save()d for this method to work.
     *
     * @return void
     */
    private function _saveParticipantData()
    {
        $db = $this->getAdapter();

        foreach ($this->_participantData as $id => $status) {
            if (!array_key_exists($id, $this->_participantDataInDb)) {
                $db->insert(
                    'calendar2_user_relation',
                    array(
                        'calendar2_id'        => $this->id,
                        'user_id'             => $id,
                        'confirmation_status' => $status
                    )
                );
            } else if ($status != $this->_participantDataInDb[$id]) {
                $where  = $db->quoteInto('calendar2_id = ?', $this->id);
                $where .= $db->quoteInto(' AND user_id = ?', $id);
                $db->update(
                    'calendar2_user_relation',
                    array('confirmation_status' => $status),
                    $where
                );
            }
        }

        foreach ($this->_participantDataInDb as $id => $status) {
            if (!array_key_exists($id, $this->_participantData)
                    && $id !== $this->ownerId) {
                $db->delete(
                    'calendar2_user_relation',
                    array(
                        $db->quoteInto('calendar2_id = ?', $this->id),
                        $db->quoteInto('user_id = ?', $id)
                    )
                );
            }
        }

        $this->_participantDataInDb = $this->_participantData;
    }

    /**
     * Updates the rights for added or removed participants.
     *
     * @return void
     */
    private function _updateRights()
    {
        foreach ($this->participants as $p) {
            $rights[$p] = Phprojekt_Acl::READ;
        }
        $rights[$this->ownerId] = Phprojekt_Acl::ALL;

        $this->saveRights($rights);
    }

    /**
     * Saves this object to a new row, even if it is already backed by the
     * database. After a call to this function, the id will be different.
     *
     * @return int The id of the saved row.
     */
    private function _saveToNewRow()
    {
        $tags_object = Phprojekt_Tags::getInstance();
        $moduleId = Phprojekt_Module::getId('Calendar2');
        $tags = array();
        foreach ($tags_object->getTagsByModule($moduleId, $this->id) as $val) {
            $tags[] = $val['string'];
        }

        $this->_fetchParticipantData();
        $excludedDates              = $this->getExcludedDates();
        $this->_storedId            = null;
        $this->_data['id']          = null;
        $this->_participantDataInDb = array();
        $this->_isFirst             = true;
        $this->save();

        $tags_object->saveTags($moduleId, $this->id, implode(' ', $tags));

        return $this->id;
    }
}
