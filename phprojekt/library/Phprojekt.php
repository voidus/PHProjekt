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
 * Phprojekt Class for initialize the Zend Framework.
 */
class Phprojekt
{
    /**
     * The first part of the version number.
     */
    const VERSION_MAJOR = 6;

    /**
     * The second part of the version number.
     */
    const VERSION_MINOR = 2;

    /**
     * The third part of the version number.
     */
    const VERSION_RELEASE = 0;

    /**
     * The extra part of the version number.
     */
    const VERSION_EXTRA = 'dev';

    /**
     * Name of the Registry for current project.
     */
    const CURRENT_PROJECT = 'currentProjectId';

    /**
     * Copyright.
     */
    const COPYRIGHT = 'PHProjekt 6.1.0 - Copyright (c) 2011 Mayflower GmbH';

    /**
     * Default Max size in bytes that is allowed to be uploaded per file.
     */
    const DEFAULT_MAX_UPLOAD_SIZE = 512000;

    /**
     * Integer that define the current API version.
     */
    const API_VERSION = 0;

    /**
     * Singleton instance.
     *
     * @var Phprojekt
     */
    protected static $_instance = null;

    /**
     * Db class.
     *
     * @var Zend_Db
     */
    protected $_db;

    /**
     * Log class.
     *
     * @var Phprojekt_Log
     */
    protected $_log;

    /**
     * Cache class.
     *
     * @var Zend_Cache
     */
    protected $_cache;

    /**
     * Array of blocked Modules.
     *
     * @var Array
     */
    protected $_blockedModules = array("Calendar");

    /**
     * Returns the current version of PHProjekt.
     *
     * @return string The current version.
     */
    public static function getVersion()
    {
        if (null !== self::VERSION_EXTRA) {
            return sprintf(
                "%d.%d.%d-%s", self::VERSION_MAJOR, self::VERSION_MINOR, self::VERSION_RELEASE,
                self::VERSION_EXTRA
            );
        } else {
            return sprintf("%d.%d.%d", self::VERSION_MAJOR, self::VERSION_MINOR, self::VERSION_RELEASE);
        }
    }

    /**
     * Returns the current api verison of PHProjekt.
     *
     * The Api version is an integer that is incremented everytime a
     * method is added or modified.
     *
     * @return integer
     */
    public static function getApiVersion()
    {
        return self::API_VERSION;
    }

    /**
     * Compares two PHProjekt version strings.
     *
     * Returns 1 if the first version is higher than the second one,
     * 0 if they are equal and -1 if the second version is higher.
     *
     * @param string $version1 The first string to check.
     * @param string $version2 The second string to check.
     *
     * @return integer Comparation value.
     */
    public static function compareVersion($version1, $version2)
    {
        if (preg_match("@^([0-9])\.([0-9])\.([0-9]+)(-[a-zA-Z0-9]+)?$@i", $version1, $matches)) {
            $v1elements = array_slice($matches, 1);
        } else {
            throw new InvalidArgumentException();
        }

        if (preg_match("@^([0-9])\.([0-9])\.([0-9]+)(-[a-zA-Z0-9]+)?$@i", $version2, $matches)) {
            $v2elements = array_slice($matches, 1);
        } else {
            throw new InvalidArgumentException();
        }

        for ($i = 0; $i < 3; $i++) {
            if ((int) $v1elements[$i] > (int) $v2elements[$i]) {
                return 1;
            }

            if ((int) $v1elements[$i] < (int) $v2elements[$i]) {
                return -1;
            }
        }

        if (count($v1elements) < count($v2elements)) {
            return 1;
        }

        if (count($v1elements) > count($v2elements)) {
            return -1;
        }

        if (isset($v1elements[3]) && isset($v2elements[3])) {
            return strcmp(strtolower($v1elements[3]), strtolower($v2elements[3]));
        } else if (!isset($v1elements[3]) && isset($v2elements[3])) {
            return 1;
        } else if (isset($v1elements[3]) && !isset($v2elements[3])) {
            return -1;
        } else {
            return 0;
        }
    }

    /**
     * Provided for backwards compatibility.
     */
    public static function getInstance()
    {
        return new self();
    }

    /**
     * Return the Config class.
     *
     * @return Zend_Config_Ini An instance of Zend_Config_Ini.
     */
    public function getConfig()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getOptions();
    }

    /**
     * Return the Db class.
     *
     * If don't exists, try to create it.
     *
     * @return Zend_Db An instance of Zend_Db.
     */
    public function getDb()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->bootstrap('db')->getResource('db');
    }

    /**
     * Return the Log class.
     *
     * If don't exists, try to create it.
     *
     * @return Phprojekt_Log An instance of Phprojekt_Log.
     */
    public function getLog()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->bootstrap('log')->getResource('log');
    }

    /**
     * Return the Translate class.
     *
     * If don't exists, try to create it.
     *
     * @param string|Zend_Locale $locale Locale/Language to set.
     *
     * @return Phprojekt_Language An instance of Phprojekt_Language.
     */
    public function getTranslate($locale = null)
    {
        if (null === $locale) {
            $locale = Phprojekt_Auth::getRealUser()->getSetting("language", $this->getConfig()->language);
        }

        if (!($translate = $this->getCache()->load('Phprojekt_getTranslate_' . $locale))) {
            $translate = new Phprojekt_Language(array('locale' => $locale));
            $this->getCache()->save($translate, 'Phprojekt_getTranslate_' . $locale, array('Language'));
        }

        return $translate;
    }

    /**
     * Set the current project ID.
     *
     * @param integer $projectId Current project ID.
     *
     * @return void
     */
    public static function setCurrentProjectId($projectId)
    {
        $project = new Project_Models_Project();
        if (!$project = $project->find($projectId)) {
            throw new Phprojekt_PublishedException("Project with id $projectId not found.");
        }

        Zend_Registry::set(self::CURRENT_PROJECT, $project);
    }

    /**
     * Return the current project ID.
     *
     * @return integer Current project ID.
     */
    public static function getCurrentProjectId()
    {
        return Zend_Registry::get(self::CURRENT_PROJECT)->id;
    }

    /**
     * Return the current project.
     *
     * @return Phprojekt_Models_Project Current project
     */
    public static function getCurrentProject()
    {
        return Zend_Registry::get(self::CURRENT_PROJECT);
    }

    /**
     * Translate a string using the current module.
     *
     * @param string             $message    Message to translate.
     * @param string|Zend_Locale $locale     Locale/Language to set.
     * @param string             $moduleName Module where search the string.
     *
     * @return string Translated string.
     */
    public function translate($message, $locale = null, $moduleName = null)
    {
        $translate = Phprojekt::getInstance()->getTranslate($locale);
        if (null === $moduleName) {
            if (Zend_Controller_Front::getInstance()->getRequest()) {
                $moduleName = Zend_Controller_Front::getInstance()->getRequest()->getModuleName();
            } else {
                return $message;
            }
        }

        // Fix for request to the core
        if ($moduleName == 'Core') {
            $paramModule = Zend_Controller_Front::getInstance()->getRequest()->getParam('moduleName', null);
            // Use a $moduleName param if is not a system Setting or Configuration
            if (null !== $paramModule && !in_array($paramModule, array('General', 'User', 'Notification'))) {
                $moduleName = $paramModule;
            }
        }

        if (null === $locale) {
            $locale = Phprojekt_Auth::getRealUser()->getSetting("language", $this->getConfig()->language);
        }

        return $translate->translate($message, $moduleName, $locale);
    }

    /**
     * Return the tooltip for a field.
     *
     * 1. Look for the tooltip in the current module language file.
     * 2. Look for the tooltip in the Default module language file.
     * 3. Look for the tooltip in the current module english file.
     * 4. Look for the tooltip in the Default module english file.
     * 5. return nothing.
     *
     * @param string $field The field for the tooltip.
     *
     * @return string Tooltip message.
     */
    public function getTooltip($field, $moduleName = null)
    {
        $translate  = Phprojekt::getInstance()->getTranslate();
        if (null == $moduleName) {
            $moduleName = Zend_Controller_Front::getInstance()->getRequest()->getModuleName();
        }

        $hints = $translate->translate('Tooltip', $moduleName);
        if (!is_array($hints)) {
            $hints = $translate->translate('Tooltip', $moduleName, 'en');
            if (!is_array($hints)) {
                $hints = array();
            }
        }

        return (isset($hints[$field])) ? $hints[$field] : '';
    }

    /**
     * Return the Cache class.
     *
     * @return Zend_Cache An instance of Zend_Cache.
     */
    public function getCache()
    {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')
            ->bootstrap('phprojektCache')->getResource('phprojektCache');
    }

    /**
     * Initialize the paths, the config values and all the render stuff.
     *
     * @return void
     */
    public function _initialize()
    {
        // Check the server
        $checkServer = Phprojekt::checkExtensionsAndSettings();

        // Check the PHP version
        if (!$checkServer['requirements']['php']['checked']) {
            $missingRequirements[] = "- You need the PHP Version '" . $checkServer['requirements']['php']['required']
                . "' or newer";
        }

        // Check required extension
        foreach ($checkServer['requirements']['extension'] as $name => $values) {
            if (!$values['checked']) {
                $missingRequirements[] = "- The '" . $name . "' extension must be enabled.";
            }
        }

        // Check required settings
        foreach ($checkServer['requirements']['settings'] as $name => $values) {
            if (!$values['checked']) {
                $missingRequirements[] = "- The php.ini setting of '" . $name ."' has to be '"
                    . $values['required'] . "'.";
            }
        }

        // Show message
        if (!empty($missingRequirements)) {
            $message = "Your PHP does not meet the requirements needed for P6.<br />"
                . implode("<br />", $missingRequirements);
            error_log($message);
            $this->_dieWithInternalServerError();
        }
    }

    /**
     * Remove cache of SubModules folders with controllers files.
     *
     * @return void.
     */
    public static function removeControllersFolders()
    {
        // Remove SubModules entries
        $controllerPathNamespace = new Zend_Session_Namespace('Phprojekt-_getControllersFolders');
        $controllerPathNamespace->unsetAll();
    }

    /**
     * Error handler.
     *
     * @param integer $errNumber Number of error.
     * @param integer $errStr    Description of the error.
     * @param integer $errFile   File that originated the error.
     * @param integer $errLine   Line of the file that originated the error.
     *
     * @return void/boolean True.
     */
    public static function errorHandler($errNumber, $errStr, $errFile, $errLine)
    {
        // Don´t treat the silenced errors
        if (error_reporting() == 0) {
            return;
        }

        // Whether, for E_NOTICE, E_USER_ERROR, E_RECOVERABLE_ERROR and default case, show errors to user and interrupt
        // script execution
        $throwErrors = false;
        // Write the error into the log file
        $useLog = true;
        // Get an Exception
        $useException = false;

        switch ($errNumber) {
            case E_WARNING:
                // Log error and continue script execution
                $errDesc = "Run-time warning (non-fatal error).";
                break;
            case E_NOTICE:
                // Log error, send advice to the user and -maybe- STOP script execution
                $errDesc = "Run-time notice. The script encountered something that could indicate an error, but could "
                    . "also happen in the normal course of running a script.";
                $useException = true;
                break;
            case E_USER_ERROR:
                // Log error, send advice to the user and -maybe- STOP script execution
                $errDesc      = "Error message intentionally generated by a programmer (trigger_error).";
                $useException = true;
                break;
            case E_USER_WARNING:
                // Log error and continue script execution
                $errDesc = "Warning message generated by a programmer (trigger_error)";
                break;
            case E_USER_NOTICE:
                // Log error and continue script execution
                $errDesc = "Notice message generated by a programmer (trigger_error).";
                break;
            case E_STRICT:
                // Log error and continue script execution
                // Skip the only error that we can´t resolve now
                // @TODO: fix it
                if (!strpos($errStr, 'Phprojekt_ActiveRecord_Abstract::delete()') &&
                    !strpos($errStr, 'Zend_Db_Table_Abstract::delete()')) {
                    $errDesc = "PHP code suggestion of change (non-fatal error).";
                } else {
                    $useLog = false;
                }
                break;
            case E_RECOVERABLE_ERROR:
                // Log error, send advice to the user and -maybe- STOP script execution
                $errDesc      = "Catchable fatal error. Probably a dangerous error occured.";
                $useException = true;
                break;
            case E_DEPRECATED:
                // Log error and continue script execution
                $errDesc = "Deprecated function or sentence warning.";
                break;
            case E_USER_DEPRECATED:
                // Log error and continue script execution
                $errDesc = "Deprecated warning generated intentionally by a programmer (trigger_error).";
                break;
            default:
                // Default option, just in case. Log error, send advice to the user and -maybe- STOP script execution
                $errDesc      = "Unknown error type.";
                $useException = true;
                break;
        }

        // Write into the error log
        if ($useLog) {
            $messageLog = sprintf(
                "%s\n File: %s - Line: %d\n Description: %s\n", $errDesc, $errFile, $errLine,
                $errStr
            );

            if (self::getInstance()->getConfig()->log->printStackTraces) {
                // The frames always contain the file and line where the function was called To get a format like
                // "2: File:line class->function" we print the file and line of the first frame (which is this function,
                // so we don't need the function part anyways) and then print class->function\nFile:line for each other
                // frame. The last frame will be the php script that has been called, so it's fine that we don't have
                // a function there.
                $messageLog .= "Stack trace:\n";
                $frames      = debug_backtrace();
                $frame       = array_shift($frames);
                $messageLog .= " 0: ";
                if (array_key_exists('file', $frame)) {
                    $messageLog .= "{$frame['file']}:{$frame['line']} ";
                }
                $depth = 1;
                foreach ($frames as $frame) {
                    if (array_key_exists('function', $frame)) {
                        // If this is a included php file, don't print class->function.
                        if (array_key_exists('class', $frame)) {
                            // It's a class method, print the class and type ('->' or '::')
                            $messageLog .= $frame['class'] . $frame['type'];
                        }
                        $messageLog .= $frame['function'] . "()";
                    }
                    $messageLog .= "\n";
                    // Begin of the next line is here.
                    $messageLog .= ' ' . $depth++ . ': ';
                    if (array_key_exists('file', $frame)) {
                        $messageLog .= "{$frame['file']}:{$frame['line']} ";
                    }
                }
                $messageLog .= "\n";
            }
            Phprojekt::getInstance()->getLog()->err($messageLog);
        }

        // Show a message to the user throw an exception
        if ($throwErrors && $useException) {
            $messageUser = Phprojekt::getInstance()->translate($errDesc);
            throw new Zend_Controller_Action_Exception($messageUser, 500);
        }

        // Don't execute PHP internal error handler
        return true;
    }

    /*
     * Make a random token for check it on each page.
     *
     * @return string Token generated.
     */
    public static function createCsrfToken()
    {
        $sessionName   = 'Phprojekt_CsrfToken';
        $csrfNamespace = new Zend_Session_Namespace($sessionName);
        $token         = uniqid(mt_rand(), true);

        $csrfNamespace->token = $token;

        return $token;
    }

    /**
     * Return an array with requirements and recommendations of extensions and settings.
     *
     * The array is like:
     * array(
     *       'requirements' =>
     *           array(
     *               'extension' => array(
     *                  'NAME' => array('required' => true, 'checked' => true | false, 'help' => link)
     *               ),
     *               'settings' => array(
     *                  'NAME' => array('required' => VALUE, 'checked' => true | false, 'help' => link)
     *               ),
     *               'php' => array('required' => VALUE, 'checked' => true | false, 'help' => link)
     *           ),
     *       'recommendations' =>
     *           array(
     *               'settings' => array(
     *                  'NAME' => array('required' => VALUE, 'checked' => true | false, 'help' => link)
     *               )
     *           )
     * )
     *
     * @return array Array as describe above
     */
    public static function checkExtensionsAndSettings()
    {
        // PHP version
        $requiredPhpVersion = "5.3.0";

        // The following extensions are either needed by components of the Zend Framework that are used
        // or by P6 components itself.
        $extensionsNeeded = array(
            'mbstring'   => 'http://us.php.net/manual/en/mbstring.installation.php',
            'iconv'      => 'http://us.php.net/manual/en/iconv.installation.php',
            'ctype'      => 'http://us.php.net/manual/en/ctype.installation.php',
            'pcre'       => 'http://us.php.net/manual/en/pcre.installation.php',
            'pdo'        => 'http://us.php.net/manual/en/pdo.installation.php',
            'Reflection' => 'http://us.php.net/manual/en/reflection.installation.php',
            'session'    => 'http://us.php.net/manual/en/session.installation.php',
            'SPL'        => 'http://us.php.net/manual/en/spl.installation.php',
            'zlib'       => 'http://us.php.net/manual/en/zlib.installation.php');

        // These settings need to be properly configured by the admin
        $settingsNeeded = array(
            'magic_quotes_gpc' =>
                array('value' => 0,
                      'help'  => 'http://us.php.net/manual/en/info.configuration.php#ini.magic-quotes-gpc'),
            'magic_quotes_runtime' =>
                array('value' => 0,
                      'help'  => 'http://us.php.net/manual/en/info.configuration.php#ini.magic-quotes-runtime'),
            'magic_quotes_sybase' =>
                array('value' => 0,
                      'help'  => 'http://us.php.net/manual/en/sybase.configuration.php#ini.magic-quotes-sybase'));

        // These settings should be properly configured by the admin
        $settingsRecommended = array(
            'register_globals' =>
                array('value' => 0,
                      'help'  => 'http://us.php.net/manual/en/ini.core.php#ini.register-globals'),
            'safe_mode' =>
                array('value' => 0,
                      'help'  => 'http://us.php.net/manual/en/features.safe-mode.php'));

        $requirements              = array();
        $recommendations           = array();
        $requirements['extension'] = array();
        $requirements['settings']  = array();

        // Check the PHP version
        $requirements['php']['required'] = $requiredPhpVersion;
        if (version_compare(phpversion(), $requiredPhpVersion, '<')) {
            // This is a requirement of the Zend Framework
            $requirements['php']['checked'] = false;
        } else {
            $requirements['php']['checked'] = true;
        }
        $requirements['php']['help'] = 'http://us.php.net/';

        // Check the extensions needed
        foreach ($extensionsNeeded as $extension => $link) {
            $requirements['extension'][$extension]['required'] = true;
            if (!extension_loaded($extension)) {
                $requirements['extension'][$extension]['checked'] = false;
            } else {
                $requirements['extension'][$extension]['checked'] = true;
            }
            $requirements['extension'][$extension]['help'] = $link;
        }

        // Check pdo library
        $mysql  = extension_loaded('pdo_mysql');
        $sqlite = extension_loaded('pdo_sqlite2');
        $pgsql  = extension_loaded('pdo_pgsql');

        $requirements['extension']['pdo_mysql | pdo_sqlite2 | pdo_pgsql']['required'] = true;
        if (!$mysql && !$sqlite && !$pgsql) {
            $requirements['extension']['pdo_mysql | pdo_sqlite2 | pdo_pgsql']['checked'] = false;
        } else {
            $requirements['extension']['pdo_mysql | pdo_sqlite2 | pdo_pgsql']['checked'] = true;
        }
        $requirements['extension']['pdo_mysql | pdo_sqlite2 | pdo_pgsql']['help'] =
            'http://us.php.net/manual/en/pdo.installation.php';

        // Check the settings needed
        foreach ($settingsNeeded as $conf => $values) {
            $requirements['settings'][$conf]['required'] = $values['value'];
            if (ini_get($conf) != $values['value']) {
                $requirements['settings'][$conf]['checked'] = false;
            } else {
                $requirements['settings'][$conf]['checked'] = true;
            }
            $requirements['settings'][$conf]['help'] = $values['help'];
        }

        // Check the settings recommended
        foreach ($settingsRecommended as $conf => $values) {
            $recommendations['settings'][$conf]['required'] = $values['value'];
            if (ini_get($conf) != $values['value']) {
                $recommendations['settings'][$conf]['checked'] = false;
            } else {
                $recommendations['settings'][$conf]['checked'] = true;
            }
            $recommendations['settings'][$conf]['help'] = $values['help'];
        }

        return array('requirements'    => $requirements,
                     'recommendations' => $recommendations);
    }

    /**
     * Tests whether the prodived module is blocked.
     *
     * @param string $name  Module name to test.
     *
     * @return bool     True if the given module is blocked
     */
    public function isBlockedModule($name)
    {
        return in_array($name, $this->_blockedModules);
    }

    /**
     * Generates a unique identifier, usable for example as a uri or uid.
     *
     * @return string
     */
    public static function generateUniqueIdentifier()
    {
        return rand() . '-' . time() . '-' . getMyPid() . '@' . php_uname('n');
    }

    private function _dieWithInternalServerError()
    {
        $response = new Zend_Controller_Response_Http();
        $response->setHttpResponseCode(500);
        $response->setBody('Internal Server Error. Please contact an administrator.');
        $response->sendResponse();
        die();
    }
}
