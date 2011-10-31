<?php
/**
 * Calendar2 ProxyUsers model class.
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
 * @package    Phprojekt
 * @subpackage Auth
 * @copyright  Copyright (c) 2011 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Reno Reckling <reno.reckling@mayflower.de>
 */

/**
 * Authentication methods to do proxying.
 *
 * This class provides the necessary functions to switch the effective user.
 *
 * @category   PHProjekt
 * @package    Phprojekt
 * @subpackage Auth
 * @copyright  Copyright (c) 2011 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.1
 * @version    Release: @package_version@
 * @author     Reno Reckling <reno.reckling@mayflower.de>
 */
class Phprojekt_Auth_Proxy
{
    protected static $_proxyTable = null;
    protected static $_effectiveUser = null;

    /**
     * Switches the effective User.
     *
     * Checks whether the current user is allowed to impersonate the provided user.
     * If he is allowed to, it switches the effective user.
     * If not, it throws an exception
     *
     * @param integer $userId   The userId we switch to.
     *                          If not set, we switch back to the curren user.
     *
     * @throws Phprojekt_Auth_Exception
     *
     * @return void
     */
    public static function switchToUserById($userId = null)
    {
        if ($userId === null) {
            $userId = Phprojekt_Auth::getUserId();
        }

        if (self::hasProxyRightForUserById($userId)) {
            self::_setEffectiveUserById($userId);
        } else {
            throw new Phprojekt_Auth_Exception("No right to switch to user $userId");
        }
    }

    /**
     * Returns true if the user has proxy rights for the provided userid, false otherwise
     *
     * @return Boolean  true if the user has proxy rights, false otherwise
     */
    public static function hasProxyRightForUserById($userId)
    {
        if ($userId == self::getEffectiveUser()->id) {
            return true;
        }

        return self::_getProxyTable()->hasProxyRights(Phprojekt_Auth::getUserId(), $userId);
    }

    /**
     * Returns the current effective user.
     *
     * @return Phprojekt_User_User  The current effective user
     */
    public static function getEffectiveUser()
    {
        if (self::$_effectiveUser != null) {
            return self::$_effectiveUser;
        } else {
            $user = new Phprojekt_User_User();
            return $user->findUserById(Phprojekt_Auth::getUserId());
        }
    }

    /**
     * Returns the userId of the current effective user.
     *
     * @return Interger     The id of the current effective user
     */
    public static function getEffectiveUserId()
    {
        return self::getEffectiveUser()->id;
    }

    protected static function _setEffectiveUserById($userId)
    {
        $user = new Phprojekt_User_User();
        self::$_effectiveUser =  $user->findUserById($userId);
    }

    protected static function _getProxyTable()
    {
        if (self::$_proxyTable === null) {
            self::$_proxyTable = new Phprojekt_Auth_ProxyTable();
        }
        return self::$_proxyTable;
    }
}
