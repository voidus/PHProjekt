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
 * @copyright  Copyright (c) 2012 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 */

/**
 * Bootstrap class for PHProjekt
 */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    protected function _initPhprojektConfig()
    {
        $this->bootstrap('autoloader');
        $config = new Zend_Config_Ini(PHPR_CONFIG_FILE, 'production', true);

        if (empty($config->webpath)) {
            $response        = new Zend_Controller_Request_Http();
            $config->webpath = $response->getScheme() . '://' . $response->getHttpHost()
                . $response->getBasePath() . '/';
        }

        defined('PHPR_TEMP_PATH') || define('PHPR_TEMP_PATH', $config->tmpPath);
        defined('PHPR_USER_CORE_PATH') || define('PHPR_USER_CORE_PATH', $config->applicationPath);
        set_include_path(PHPR_USER_CORE_PATH . PATH_SEPARATOR . get_include_path());

        return $config;
    }

    protected function _initLog()
    {
        $this->bootstrap('phprojektConfig');
        return new Phprojekt_Log($this->getResource('phprojektConfig'));
    }

    protected function _initDispatcherClass()
    {
        // Can be set in application.ini with ZF > 1.12
        $this->bootstrap('frontController');
        $front = Zend_Controller_Front::getInstance();
        $front->setDispatcher(new Phprojekt_Dispatcher());
        $front->setDefaultModule('Default');
    }

    protected function _initErrorHandler()
    {
        set_error_handler(Array("Phprojekt", "errorHandler"));
    }

    protected function _initModuleDirectories()
    {
        $this->bootstrap('dispatcherClass');
        $front = Zend_Controller_Front::getInstance();
        $front->addModuleDirectory(PHPR_CORE_PATH);
        $moduleDirectories = $this->_getControllersFolders($this->_getHelperPaths());
        foreach ($moduleDirectories as $moduleDirectory) {
            $front->addModuleDirectory($moduleDirectory);
        }

        $front->addModuleDirectory(PHPR_USER_CORE_PATH);
    }

    /**
     * Cache the folders with helpers files.
     *
     * @return array Array with 'module', 'path' and 'directory'.
     */
    private function _getHelperPaths()
    {
        $helperPathNamespace = new Zend_Session_Namespace('Phprojekt-_getHelperPaths');
        if (!isset($helperPathNamespace->helperPaths)) {
            $helperPaths = array();
            // System modules
            foreach (scandir(PHPR_CORE_PATH) as $module) {
                $dir = PHPR_CORE_PATH . DIRECTORY_SEPARATOR . $module;
                if ($module == '.'  || $module == '..' || !is_dir($dir)) {
                    continue;
                }

                $helperPaths[] = array('module'    => $module,
                                       'path'      => PHPR_CORE_PATH . DIRECTORY_SEPARATOR,
                                       'directory' => $dir . DIRECTORY_SEPARATOR . 'Helpers');
            }

            // User modules
            foreach (scandir(PHPR_USER_CORE_PATH) as $module) {
                $dir = PHPR_USER_CORE_PATH . $module;
                if ($module == '.'  || $module == '..' || !is_dir($dir)) {
                    continue;
                }

                $helperPaths[] = array('module'    => $module,
                                       'path'      => PHPR_USER_CORE_PATH,
                                       'directory' => $dir . DIRECTORY_SEPARATOR . 'Helpers');
            }

            $helperPathNamespace->helperPaths = $helperPaths;
        } else {
            $helperPaths = $helperPathNamespace->helperPaths;
        }

        return $helperPaths;
    }


    /**
     * Cache the SubModules folders with controllers files.
     *
     * @param array $helperPaths Array with all the folders with helpers.
     *
     * @return array Array with directories.
     */
    private function _getControllersFolders($helperPaths)
    {
        $controllerPathNamespace = new Zend_Session_Namespace('Phprojekt-_getControllersFolders');
        if (!isset($controllerPathNamespace->controllerPaths)) {
            $controllerPaths = array();
            foreach ($helperPaths as $helperPath) {
                $dir = $helperPath['path'] . $helperPath['module'] . DIRECTORY_SEPARATOR . 'SubModules';
                if (is_dir($dir)) {
                    if ($helperPath['module'] != 'Core') {
                        $controllerPaths[] = $dir;
                    } else {
                        $coreModules = scandir($dir);
                        foreach ($coreModules as $coreModule) {
                            $coreDir = $dir . DIRECTORY_SEPARATOR . $coreModule;
                            if ($coreModule != '.'  && $coreModule != '..' && is_dir($coreDir)) {
                                $controllerPaths[] = $coreDir;
                            }
                        }
                    }
                }
            }
            $controllerPathNamespace->controllerPaths = $controllerPaths;
        } else {
            $controllerPaths = $controllerPathNamespace->controllerPaths;
        }

        return $controllerPaths;
    }

    protected function _initModuleHelpers()
    {
        $helperPaths = $this->_getHelperPaths();
        $view        = $this->_setView($helperPaths);

        $viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer($view);
        $viewRenderer->setViewBasePathSpec(':moduleDir/Views');
        $viewRenderer->setViewScriptPathSpec(':action.:suffix');
        Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
        foreach ($helperPaths as $helperPath) {
            Zend_Controller_Action_HelperBroker::addPath($helperPath['directory']);
        }

        // Add SubModules directories with controlles
        $moduleDirectories = $this->_getControllersFolders($helperPaths);
        $front             = Zend_Controller_Front::getInstance();
        foreach ($moduleDirectories as $moduleDirectory) {
            $front->addModuleDirectory($moduleDirectory);
        }
    }

    /**
     * Cache the View Class.
     *
     * @param array $helperPaths Array with all the folders with helpers.
     *
     * @return Zend_View An instance of Zend_View.
     */
    private function _setView($helperPaths)
    {
        $viewNamespace = new Zend_Session_Namespace('Phprojekt-_setView');
        if (!isset($viewNamespace->view)) {
            $view = new Zend_View();
            $view->addScriptPath(PHPR_CORE_PATH . '/Default/Views/dojo/');
            foreach ($helperPaths as $helperPath) {
                if (is_dir($helperPath['directory'])) {
                    $view->addHelperPath($helperPath['directory'], $helperPath['module'] . '_' . 'Helpers');
                }
            }
            $viewNamespace->view = $view;
        } else {
            $view = $viewNamespace->view;
        }

        return $view;
    }

    protected function _initPhprojektCache()
    {
        $frontendOptions = array('automatic_serialization' => true);
        $backendOptions  = array('cache_dir' => PHPR_TEMP_PATH . 'zendCache' . DIRECTORY_SEPARATOR);
        $cache           = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);

        $this->_setupZendDbTableCache();
        $this->_setupZendLocaleCache();

        return $cache;
    }

    private function _setupZendDbTableCache()
    {
        $cacheDir = PHPR_TEMP_PATH . 'zendDbTable_cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700);
        }
        Zend_Db_Table_Abstract::setDefaultMetadataCache(
            Zend_Cache::factory(
                'Core',
                'File',
                array('automatic_serialization' => true),
                array('cache_dir' => $cacheDir)
            )
        );
    }

    /**
     * Set up a cache for Zend_Locale. See http://jira.opensource.mayflower.de/jira/browse/PHPROJEKT-150
     */
    private function _setupZendLocaleCache()
    {
        $cacheDir = PHPR_TEMP_PATH . 'zendLocale_cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0700);
        }
        Zend_Locale::setCache(
            Zend_Cache::factory(
                'Core',
                'File',
                array(),
                array('cache_dir' => $cacheDir)
            )
        );
    }

    protected function _initUserIncludePath()
    {
        $this->bootstrap('phprojektConfig');
        set_include_path(PHPR_USER_CORE_PATH . PATH_SEPARATOR . get_include_path());
    }

    protected function _initAutoloader()
    {
        require_once 'Phprojekt/Loader.php';
        $autoloader = Zend_Loader_Autoloader::getInstance();
        $autoloader->pushAutoloader(array('Phprojekt_Loader', 'autoload'));
    }
}
