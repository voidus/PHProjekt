<?php
/**
 * Bootstrap file.
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
 * @category  PHProjekt
 * @package   Htdocs
 * @copyright Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license   LGPL v3 (See LICENSE file)
 * @link      http://www.phprojekt.com
 * @since     File available since Release 6.0
 * @version   Release: 6.1.0
 * @author    David Soria Parra <david.soria_parra@mayflower.de>
 */
defined('PHPR_ROOT_PATH') || define('PHPR_ROOT_PATH', realpath(dirname(__FILE__) . '/../'));

defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(PHPR_ROOT_PATH . '/application'));
defined('APPLICATION_ENV')  || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'production');

if ('production' !== APPLICATION_ENV) {
    error_reporting(-1);
}

defined('PHPR_CONFIG_SECTION') || define('PHPR_CONFIG_SECTION', APPLICATION_ENV);
defined('PHPR_CORE_PATH') || define('PHPR_CORE_PATH', APPLICATION_PATH);
defined('PHPR_LIBRARY_PATH') || define('PHPR_LIBRARY_PATH', PHPR_ROOT_PATH . DIRECTORY_SEPARATOR . 'library');
defined('PHPR_CONFIG_FILE') || define('PHPR_CONFIG_FILE', PHPR_ROOT_PATH . DIRECTORY_SEPARATOR . 'configuration.php');

set_include_path(realpath(PHPR_ROOT_PATH . '/library/') . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';
require_once 'Zend/Config/Ini.php';

if (!file_exists(PHPR_CONFIG_FILE)) {
    $script = $_SERVER['SCRIPT_NAME'];
    $url = "http://". $_SERVER['SERVER_NAME'] . substr($script, 0, strlen($script) - strlen('index.php')) . 'setup.php';
    header("Location: $url", true, 307); // Moved temporarily
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        echo "Redirecting to <a>$url</a>";
    }
} else {
    $config = new Zend_Config_Ini(
        APPLICATION_PATH . '/configs/application.ini',
        APPLICATION_ENV,
        array('allowModifications' => true)
    );
    $config->merge(new Zend_Config_Ini(APPLICATION_PATH . '/configs/db.ini'));

    $application = new Zend_Application(APPLICATION_ENV, $config);
    $application->bootstrap()->run();
}
