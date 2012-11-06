<?php
/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 */

/**
 * Represents a contract of employment. It is used as a reference to calculate overtime.
 */
class Timecard_Models_Contract extends Phprojekt_ActiveRecord_Abstract implements Phprojekt_Model_Interface
{
    private $information = null;

    public function save()
    {
        if (!$this->isNew() && $this->ownerId !== Phprojekt_Auth_Proxy::getEffectiveUserId()) {
            throw new Zend_Controller_Action_Exception('You are not allowed to update this item', 403);
        }
        $this->userId = Phprojekt_Auth_Proxy::getEffectiveUserId();
        return parent::save();
    }

    /**
     * Get the information manager
     *
     * @see Phprojekt_Model_Interface::getInformation()
     *
     * @return Phprojekt_ModelInformation_Interface
     */
    public function getInformation()
    {
        if (!$this->information) {
            $this->information = new Timecard_Models_ContractInformation();
        }
        return $this->information;
    }

    /**
     * Save the rigths
     *
     * We don't support any rights, but this is called anyways, so we'll just ignore it.
     *
     * @return void
     */
    public function saveRights()
    {
    }

    /**
     * Delete only the own records
     *
     * @return boolean
     */
    public function delete()
    {
        if ($this->ownerId == Phprojekt_Auth_Proxy::getEffectiveUserId()) {
            return parent::delete();
        } else {
            throw new Zend_Controller_Action_Exception('You are not allowed to delete this item', 403);
        }
    }

    /**
     * Need to copy this from Item_Abstract as IndexController assumes it's here
     */
    public function getUsersRights()
    {
        return array_merge(
            Phprojekt_Acl::convertBitmaskToArray(Phprojekt_Acl::ALL),
            array(
                'moduleId' => 1,
                'itemId'   => $this->id,
                'userId'   => $this->ownerId
            )
        );
    }

    public function fetchAll($where = null, $sort = null, $count = null, $start = null)
    {
        $db     = Phprojekt::getInstance()->getDb();
        $where  = (empty($where) ? '' : "({$where}) AND ");
        $where .= $db->quoteInto('owner_id = ?', Phprojekt_Auth_Proxy::getEffectiveUserId());

        return parent::fetchAll($where, $sort, $count, $start);
    }
}
