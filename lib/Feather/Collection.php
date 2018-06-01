<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Collection
 * A suite of function for working with PHP arrays.
 *
 * @copyright GG.COM Ltd
 * @license MIT
 * @author Mike Hall
 */
class Collection
{
    /**
     * map()
     * Apply a callback function to each element of an array
     *
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function map(array $array, $callback)
    {
        return array_map($callback, $array);
    }

    /**
     * pick()
     * Discard all but the keys named in the second parameter
     *
     * @param array $array
     * @param mixed $keys
     * @return array
     */
    public static function pick(array $array, $keys)
    {
        if (is_scalar($keys)) {
            $keys = array($keys);
        }

        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * omit()
     * Discard the keys named in the second parameter
     *
     * @param array $array
     * @param mixed $keys
     * @return array
     */
    public static function omit(array $array, $keys)
    {
        if (is_scalar($keys)) {
            $keys = array($keys);
        }

        return array_diff_key($array, array_flip($keys));
    }

    /**
     * slice()
     * Return a portion of the array, starting at index $start and continuing for $length elements
     *
     * @param array $array
     * @param int $start - The initial offset
     * @param int $length (optional) - The length of the slice, or null for "the rest of the array"
     * @param bool $preserve - Should keys be preserved, default no
     * @return array
     */
    public static function slice(array $array, $start, $length = null, $preserve = NO)
    {
        return array_slice($array, $start, $length, !!$preserve);
    }

    /**
     * reduce()
     * Reduce an array to a scalar value. Simple wrapper around the standard function
     *
     * @param array $array
     * @param callable $callback
     * @param mixed $initial (optional)
     * @return mixed The reduced value
     */
    public static function reduce(array $array, $callback, $initial = null)
    {
        if (is_null($initial) === YES) {
            $initial = array_shift($array);
        }
        return array_reduce($array, $callback, $initial);
    }

    /**
     * filter()
     * Discard array elements, based upon a callback function. Does not maintain key-value association.
     *
     * @param array $array
     * @param callable $callback, return truthy to keep
     * @return array
     */
    public static function filter(array $array, $callback)
    {
        if (is_string($callback) === YES) {
            $callback = function ($element) use ($callback) {
                return empty($element[$callback]) === NO;
            };
        }

        $array = array_filter($array, $callback);
        return array_values($array);
    }

    /**
     * has()
     * Does the needle exist in the haystack?
     *
     * @param array $haystack
     * @param mixed $needle
     * @return boolean
     */
    public static function has(array $haystack, $needle)
    {
        return in_array($needle, $haystack);
    }

    /**
     * hasKey()
     * Does the needle exist as a key in the haystack?
     *
     * @param array $haystack
     * @param mixed $needle
     * @return boolean
     */
    public static function hasKey(array $haystack, $needle)
    {
        return array_key_exists($needle, $haystack);
    }

    /**
     * flatten()
     * Flatten an array-of-arrays down into a single linear array.
     * Optional $deep parameter determines whether to recurse into sub arrays, or just do a single pass
     *
     * @param array $array
     * @param boolean $deep (optional)
     * @return array
     */
    public static function flatten(array $array, $deep = NO)
    {
        if ($deep === YES) {
            $output = array();
            array_walk_recursive($array, function ($v) use (&$output) {
                $output[] = $v;
            });
            return $output;
        }

        return call_user_func_array("array_merge", $array);
    }

    /**
     * find()
     * Find the first array element which matches the supplied callback
     *
     * @param array $array
     * @param callable $callback
     * @return mixed matching element, or NULL for no match
     */
    public static function find(array $array, callable $callback)
    {
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $value, $key, $array)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * every()
     * Returns true if every element of the array matches a supplied callback
     *
     * @param array $array
     * @param callable $callback
     * @return boolean
     */
    public static function every(array $array, callable $callback)
    {
        foreach ($array as $key => $value) {
            if (!call_user_func($callback, $value, $key, $array)) {
                return NO;
            }
        }

        return YES;
    }

    /**
     * some()
     * Returns true if at least one element of the array matches a supplied callback
     *
     * @param array $array
     * @param callable $callback
     * @return boolean
     */
    public static function some(array $array, callable $callback)
    {
        foreach ($array as $key => $value) {
            if (call_user_func($callback, $value, $key, $array)) {
                return YES;
            }
        }

        return NO;
    }

    /**
     * sort()
     * Sorts an array, based on the comparator supplied. This can be a callable, a string referencing
     * a key whose value we want to be the comparator, or an array of such strings.
     *
     * @param array $array
     * @param mixed $comparator
     * @return boolean
     */
    public static function sort(array $array, $comparator)
    {
        // This sorting function will take a few kinds of arguments as the comparator.
        // The first is a string, where the value of the array element with that key will be
        // used to compare.  The second is a dotted-string, where the dot-notation describes
        // a path to a nested object.  Next you can provide an array of these strings, and we
        // will sort on each one. And finally, you can provide a custom function.

        // If the user has supplied a custom function, great let's use it. If they haven't
        // then we need to supply a default callback, which wil do the magic string/array business
        // we have just described.

        // First, convert the string notation to the array notation on the fly, so we only need to
        // implement one of these things.
        if (is_string($comparator) === YES) {
            $comparator = [$comparator];
        }

        // If what we have is not a callback, then we should provide our default
        if (is_callable($comparator) === NO) {
            $comparator = function ($left, $right) use ($comparator) {

                $cmp = 0;

                foreach ($comparator as $part) {

                    $path = explode(".", $part);

                    $leftValue = $left;
                    foreach ($path as $k) {
                        $leftValue = $leftValue[$k];
                    }

                    $rightValue = $right;
                    foreach ($path as $k) {
                        $rightValue = $rightValue[$k];
                    }

                    if (is_scalar($leftValue) === NO) {
                        $leftValue = json_encode($leftValue);
                    }

                    if (is_scalar($rightValue) === NO) {
                        $rightValue = json_encode($rightValue);
                    }

                    $cmp = strcmp($leftValue, $rightValue);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }

                return $cmp;
            };
        }

        usort($array, $comparator);
        return array_values($array);
    }

    /**
     * esort()
     * Reverse sorts an array, based on the comparator supplied. The comparator is the same as in Collection::sort()
     *
     * @param array $array
     * @param mixed $comparator
     * @return boolean
     */
    public static function rsort(array $array, $callback)
    {
        return array_reverse(self::sort($array, $callback));
    }
}
