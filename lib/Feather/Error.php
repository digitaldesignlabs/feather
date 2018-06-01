<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * For error handling
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
     * New Error Constructor
     * @param string $message - Error message
     * @param array $meta - Meta data about this error
     */
    public function __construct($message, array $meta = [])
    {
        $this->meta = $meta;
        parent::__construct($message);
    }

    /**
     * Tests if an object is an error
     * @param mixed $o - The variable to test
     * @return boolean - YES if this is an error
     */
    public static function isError($o)
    {
        return $o instanceof Error;
    }
}
