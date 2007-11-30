<?php
/**
 * A item, with database manager support
 *
 * LICENSE: Licensed under the terms of the PHProjekt 6 License
 *
 * @copyright  2007 Mayflower GmbH (http://www.mayflower.de)
 * @package    PHProjekt
 * @license    http://phprojekt.com/license PHProjekt 6 License
 * @version    CVS: $Id$
 * @link       http://www.phprojekt.com
 * @author     Gustavao Solt <solt@mayflower.de>
 * @since      File available since Release 1.0
 */

/**
 * A item, with database manager support
 *
 * @copyright  2007 Mayflower GmbH (http://www.mayflower.de)
 * @package    PHProjekt
 * @license    http://phprojekt.com/license PHProjekt 6 License
 * @version    Release: @package_version@
 * @link       http://www.phprojekt.com
 * @since      File available since Release 1.0
 * @author     Gustavao Solt <solt@mayflower.de>
 */
abstract class Phprojekt_Item_Abstract extends Phprojekt_ActiveRecord_Abstract
{
    /**
     * Represents the database_manager class
     *
     * @var Phprojekt_ActiveRecord_Abstract
     */
    protected $_dbManager = null;

    /**
     * Error object
     *
     * @var Phprojekt_Error
     */
    protected $_error = null;

    /**
     * History object
     *
     * @var Phprojekt_Histoy
     */
    protected $_history = null;

    /**
     * Config for inicializes children objects
     *
     * @var array
     */
    protected $_config = null;

    /**
     * Full text Search object
     *
     * @var Phprojekt_SearchWords
     */
    protected $_search = null;

    /**
     * History data of the fields
     *
     * @var array
     */
    public $history = array();

    /**
     * Filter class for clean the input
     */
    private $_clean = null;

    /**
     * Initialize new object
     *
     * @param array $db Configuration for Zend_Db_Table
     */
    public function __construct($db = null)
    {
        parent::__construct($db);

        $this->_dbManager = new Phprojekt_DatabaseManager($this, $db);
        $this->_error     = new Phprojekt_Error();
        $this->_history   = new Phprojekt_History($db);
        $this->_search    = new Phprojekt_SearchWords($db);
        $this->_clean     = new Phprojekt_InputFilter($this->getXssFilters());

        $config        = Zend_Registry::get('config');
        $this->_config = $config;
    }

    /**
     * Returns the database manager instance used by this phprojekt item
     *
     * @return Phprojekt_DatabaseManager
     */
    public function getDatabaseManager()
    {
        return $this->_dbManager;
    }

    /**
     * Enter description here...
     *
     */
    public function current()
    {
        return new Phprojekt_DatabaseManager_Field($this->getDatabaseManager(),
                                                   $this->key(),
                                                   parent::current());
    }

    /**
     * Assign a value to a var using some validations from the table data
     *
     * @param string $varname Name of the var to assign
     * @param mixed  $value   Value for assign to the var
     *
     * @return void
     */
    public function __set($varname, $value)
    {
        $info = $this->info();

        /* Clean the value use the InputFilter */
        $value = $this->_clean->process($value);

        if (true == isset($info['metadata'][$varname])) {

            $type = $info['metadata'][$varname]['DATA_TYPE'];

            if ($type == 'int') {
                $value = (int) $value;
            }

            if ($type == 'float') {
                if (false === empty($value)) {
                    $value = Zend_Locale_Format::getFloat($value, array('precision' => 2));
                } else {
                    $value = 0;
                }
            }
        }
        parent::__set($varname, $value);
    }

    /**
     * Return if the values are valid or not
     *
     * @return boolean If are valid or not
     */
    public function recordValidate()
    {
        $validated = true;
        $data      = $this->_data;
        $fields    = $this->_dbManager->getFieldsForForm();

        foreach ($data as $varname => $value) {
            if ($this->keyExists($varname)) {
                /* Validate with the database_manager stuff */
                foreach ($fields as $field) {
                    if ($field->tableField == $varname) {
                        $validations = $field;

                        if ($validations->isRequired) {
                            $error = $this->validateIsRequired($value);
                            if (null != $error) {
                                $validated = false;
                                $this->_error->addError(array(
                                    'field'   => $varname,
                                    'message' => $error));
                            }
                        }

                        if ($validations->formType == 'date') {
                            $error = $this->validateDate($value);
                            if (null != $error) {
                                $validated = false;
                                $this->_error->addError(array(
                                    'field'   => $varname,
                                    'message' => $error));
                            }
                        }
                        break;
                    }
                }

                /* Validate an special fieldName */
                $validater  = 'validate' . ucfirst($varname);
                if ( ($validater != 'validateIsRequired') &&
                    ($validater != 'validateDate')) {
                    if (in_array($validater, get_class_methods($this))) {
                        $error = call_user_method($validater, $this, $value);
                        if (null != $error) {
                            $validated = false;
                            $this->_error->addError(array(
                                'field'   => $varname,
                                'message' => $error));
                        }
                    }
                }
            }
        }
        return $validated;
    }

    /**
     * Configuration for the InputFilter class
     * Each module can rewrite this class for
     * allow or denied some tags or atributes
     *
     * @see InputFilter class
     *
     * @return array
     */
    public function getXssFilters()
    {
        $filter = array('tagsArray'    => array(),
                        'attrArray'    => array(),
                        'tagsMethod'   => 0,
                        'attrMethod'   => 0,
                        'xssAuto'      => 1,
                        'tagBlacklist' => array('applet', 'body', 'bgsound', 'base', 'basefont',
                                                'embed', 'frame', 'frameset', 'head', 'html', 'id',
                                                'iframe', 'ilayer', 'layer', 'link', 'meta', 'name',
                                                'object', 'script', 'style', 'title', 'xml'),
                        'attrBlacklist' => array('action', 'background', 'codebase', 'dynsrc', 'lowsrc'));

        return $filter;
    }

    /**
     * Validate date values
     * return the msg error if exists
     *
     * @param string $value The date value to check
     *
     * @return string Error string or null
     */
    public function validateDate($value)
    {
        $error = null;
        if (!empty($value)) {
            if (!Zend_Date::isDate($value, 'yyyy-MM-dd')) {
                $error = 'Invalid format for date';
            }
        }
        return $error;
    }

    /**
     * Validate required fields
     * return the msg error if exists
     *
     * @param mix $value The value to check
     *
     * @return string Error string or null
     */
    public function validateIsRequired($value)
    {
        $error = null;
        if (empty($value)) {
            $error = 'Is a required field';
        }
        return $error;
    }

    /**
     * Get a value of a var.
     * Is the var is a float, return the locale float
     *
     * @param string $varname Name of the var to assign
     *
     * @return mixed
     */
    public function __get($varname)
    {
        $info = $this->info();

        $value = parent::__get($varname);

        if (true == isset($info['metadata'][$varname])) {
            $type = $info['metadata'][$varname]['DATA_TYPE'];
            if ($type == 'float') {
                $value = Zend_Locale_Format::toFloat($value, array('precision' => 2));
            }
        }
        return $value;
    }

    /**
     * Return the error data
     *
     * @return array
     */
    public function getError()
    {
        return (array) $this->_error->getError();
    }

    /**
     * Extencion of the Abstarct Record for save the history
     *
     * @return void
     */
    public function save()
    {
        if ($this->id > 0) {
            $this->_history->saveFields($this, 'edit');
            parent::save();
        } else {
            parent::save();
            $this->_history->saveFields($this, 'add');
        }
        $this->_search->indexObjectItem($this);
    }

    /**
     * Extencion of the Abstarct Record for save the history
     *
     * @return void
     */
    public function delete()
    {
        $this->_history->saveFields($this, 'delete');
        $this->_search->deleteObjectItem($this);
        parent::delete();
    }

    /**
     * Return wich submodules use this module
     *
     * @return array
     */
    public function getSubModules()
    {
        return array();
    }

    /**
     * Return the fields that can be filtered
     *
     * This function must be here for be overwrited by the default module
     *
     * @return array
     */
    public function getFieldsForFilter()
    {
        return $this->getDatabaseManager()->getInfo(Phprojekt_DatabaseManager::LIST_ORDER, Phprojekt_DatabaseManager::COLUMN_NAME);
    }
}