<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Error
 * For error handling
 *
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
class Error extends \Exception
{
    /**
     * Meta data about this error
     * @var array
     * @access public
     */
    public $meta;

    /**
     * __construct()
     * New Error Constructor
     *
     * @param string $message - Error message
     * @param array $meta - Meta data about this error
     */
    public function __construct($message, array $meta = [])
    {
        $this->meta = $meta;
        parent::__construct($message);
    }

    /**
     * isError()
     * Tests if an object is an error
     *
     * @static
     * @param mixed $o - The variable to test
     * @return boolean - YES if this is an error
     */
    public static function isError($o)
    {
        return $o instanceof Error;
    }
}
