<?php
error_reporting(-1);

defined('PHPR_ROOT_PATH') || define('PHPR_ROOT_PATH', realpath(dirname(__FILE__) . '/../../'));

defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(PHPR_ROOT_PATH . '/application'));
defined('APPLICATION_ENV')  || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ?: 'testing');

defined('PHPR_CORE_PATH') || define('PHPR_CORE_PATH', APPLICATION_PATH);
defined('PHPR_LIBRARY_PATH') || define('PHPR_LIBRARY_PATH', PHPR_ROOT_PATH . DIRECTORY_SEPARATOR . 'library');
defined('PHPR_CONFIG_FILE') || define('PHPR_CONFIG_FILE', getenv('P6_TEST_CONFIG') ?: 'configuration.php');

set_include_path(realpath(PHPR_ROOT_PATH . '/library/') . PATH_SEPARATOR . get_include_path());
require_once 'Zend/Application.php';
require_once 'Zend/Config/Ini.php';

$config = new Zend_Config_Ini(
    APPLICATION_PATH . '/configs/application.ini',
    APPLICATION_ENV,
    array('allowModifications' => true)
);
$config->merge(new Zend_Config_Ini(realpath(dirname(__FILE__) . '/db.ini'), APPLICATION_ENV));

$application = new Zend_Application(APPLICATION_ENV, $config);
$application->bootstrap();
$front = $application->getBootstrap()->getResource('FrontController');
$front->setParam('bootstrap', $application->getBootstrap());

// We set the error handler to Phprojekt::errorHandler(), which makes them not appear on the command line
restore_error_handler();

$authNamespace         = new Zend_Session_Namespace('Phprojekt_Auth-login');
$authNamespace->userId = 1;
$authNamespace->admin  = 1;

Zend_Db_Table_Abstract::getDefaultMetadataCache()->clean();

try {
    $application->getBootstrap()->getResource('db')->query('SET sql_mode="STRICT_ALL_TABLES"');
} catch (Zend_Db_Adapter_Exception $e) {
    error_log('There has been an error with setting up the database, please check phprojekt/tests/UnitTests/db.ini');
    error_log($e->getMessage());
    die;
}

Zend_Controller_Front::getInstance()->setBaseUrl('');
