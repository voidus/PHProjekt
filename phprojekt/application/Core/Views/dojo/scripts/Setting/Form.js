/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 2.1 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    $Id$
 * @author     Gustavo Solt <solt@mayflower.de>
 * @package    PHProjekt
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 */

dojo.provide("phpr.Setting.Form");

dojo.declare("phpr.Setting.Form", phpr.Core.Form, {
    customActionOnSuccess:function() {
        if (phpr.submodule == 'User') {
            var result     = Array();
            result.type    = 'warning';
            result.message = phpr.nls.get('You need to log out and log in again in order to let changes have effect');
            new phpr.handleResponse('serverFeedback', result);
        }
    },


    setBreadCrumbItem:function() {
        phpr.BreadCrumb.setItem(phpr.nls.get(phpr.submodule));
    }
});