<?php
/**
 * Language Interface for use the PHProject lang files
 *
 * LICENSE: Licensed under the terms of the PHProjekt 6 License
 *
 * @copyright  2007 Mayflower GmbH (http://www.mayflower.de)
 * @license    http://phprojekt.com/license PHProjekt 6 License
 * @version    CVS: $Id:
 * @author     Gustavo Solt <solt@mayflower.de>
 * @package    PHProjekt
 * @link       http://www.phprojekt.com
 * @since      File available since Release 1.0
 */

/* Zend_Locale */
require_once 'Zend/Locale.php';

/* Zend_Translate_Exception */
require_once 'Zend/Translate/Exception.php';

/* Zend_Translate_Adapter */
require_once 'Zend/Translate/Adapter.php';

/* Zend_Session_Namespace */
require_once 'Zend/Session/Namespace.php';

/**
 * Adapter class for use the PHProject lang files
 *
 * @copyright  2007 Mayflower GmbH (http://www.mayflower.de)
 * @version    Release: @package_version@
 * @license    http://phprojekt.com/license PHProjekt 6 License
 * @package    PHProjekt
 * @link       http://www.phprojekt.com
 * @since      File available since Release 1.0
 * @author     Gustavo Solt <solt@mayflower.de>
 */
class Phproject_LanguageAdapter extends Zend_Translate_Adapter
{
    /**
     * Generates the adapter
     *
     * @param  array               $data     Translation data
     * @param  string|Zend_Locale  $locale   OPTIONAL Locale/Language to set, identical with locale identifier,
     *                                       see Zend_Locale for more information
     */
    public function __construct($data, $locale = null)
    {
        parent::__construct($data, $locale);
    }

    /**
     * Load translation data
     *
     * @param  string|array  $filename  Filename and full path to the translation source
     * @param  string        $locale    Locale/Language to add data for, identical with locale identifier,
     *                                  see Zend_Locale for more information
     * @param  array         $option    OPTIONAL Options to use
     */
    protected function _loadTranslationData($filename, $locale, array $options = array())
    {
        $options = array_merge($this->_options, $options);
        if (($options['clear'] == true) ||  !isset($this->_translate[$locale])) {
            $this->_translate[$locale] = array();
        }

        /* Get the translated string from the session if exists */
        $session = new Zend_Session_Namespace();
        if (isset($session->translatedStrings)) {
            $this->_translate = $session->translatedStrings;
        } else {
            $session->translatedStrings = array();
        }

        /* Collect a new trasnaltion set */
        if (empty($this->_translate[$locale])) {

            /* Get the translation file */
            require_once ($filename);

            foreach ($_lang as $word => $translation) {
                $this->_translate[$locale][$word] = $translation;
            }
            $session->translatedStrings = $this->_translate;
        }
    }

    /**
     * returns the adapters name
     *
     * @return string
     */
    public function toString()
    {
        return "Phproject";
    }
}