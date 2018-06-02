<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Singleton
 * Abstract class for creating singletons. Extend a class from this
 * abstract class, and then use $instance = YourClass::instance() to get
 * a singleton instance of that class.
 *
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
abstract class Singleton
{
    /**
     * instance()
     *
     * @static
     * @param boolean $new - Get a new instance, not a singleton
     * @return object - The instance
     **/
    public static function &instance($new = NO)
    {
        // Get the name of this class
        $className = get_called_class();

        if ($new) {
            $newInstance = new $className();
            return $newInstance;
        }

        static $instance;
        if ($instance instanceof $className) {
            return $instance;
        }

        // Create a fresh template
        $instance = new $className();
        return $instance;
    }
}
