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
     * @static
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function map(array $array, $callback)
    {
        return array_map($callback, array_values($array), array_keys($array));
    }

    /**
     * pick()
     * Discard all but the keys named in the second parameter
     *
     * @static
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
     * @static
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
     * @static
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
     * @static
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
     * @static
     * @param array $array
     * @param callable $callback, return truthy to keep
     * @return array
     */
    public static function filter(array $array, $callback = null)
    {
        // Default to filtering empty elements
        if (is_null($callback) === YES) {
            $callback = function ($element) {
                return empty($element) === NO;
            };
        }

        // Allow filtering based on a supplied key name
        if (is_string($callback) === YES && is_callable($callback) === NO) {
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
     * @static
     * @param array $haystack
     * @param mixed $needle
     * @return boolean
     */
    public static function has(array $haystack, $needle)
    {
        return in_array($needle, $haystack);
    }

    /**
     * contains()
     * Alias for Collection::has()
     *
     * @static
     * @param array $haystack
     * @param mixed $needle
     * @return boolean
     */
    public static function contains(array $haystack, $needle)
    {
        return self::has($haystack, $needle);
    }

    /**
     * hasKey()
     * Does the needle exist as a key in the haystack?
     *
     * @static
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
     * @static
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
     * @static
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
     * @static
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
     * @static
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
     * @static
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
     * rsort()
     * Reverse sorts an array, based on the comparator supplied. The comparator is the same as in Collection::sort()
     *
     * @static
     * @param array $array
     * @param mixed $comparator
     * @return boolean
     */
    public static function rsort(array $array, $callback)
    {
        return array_reverse(self::sort($array, $callback));
    }

    /**
     * first()
     * Gets the value of the first element in an array
     *
     * @static
     * @param array $array
     * @return mixed
     */
    public static function first(array $array)
    {
        if (empty($array)) {
            return null;
        }

        $slice = array_slice($array, 0, 1);
        return array_pop($slice);
    }

    /**
     * last()
     * Gets the value of the last element in an array
     *
     * @static
     * @param array $array
     * @return mixed
     */
    public static function last(array $array)
    {
        if (empty($array)) {
            return null;
        }

        $slice = array_slice($array, -1);
        return array_pop($slice);
    }

    /**
     * firstKey()
     * Gets the key of the first element in an array
     *
     * @static
     * @param array $array
     * @return mixed
     */
    public static function firstKey(array $array)
    {
        if (empty($array)) {
            return null;
        }

        return key(array_slice($array, 0, 1));
    }

    /**
     * lastKey()
     * Gets the key of the last element in an array
     *
     * @static
     * @param array $array
     * @return mixed
     */
    public static function lastKey(array $array)
    {
        if (empty($array)) {
            return null;
        }

        return key(array_slice($array, -1));
    }

    /**
     * unique()
     * Unique the values within an array
     *
     * @static
     * @param array $array
     * @return array $array with duplicates removed
     */
    public static function unique(array $array)
    {
        return array_reduce(
            $array,
            function ($carry, $element) {
                if (self::has($carry, $element) === NO) {
                    array_push($carry, $element);
                }
                return $carry;
            },
            array()
        );
    }

    /**
     * mapFilter()
     * Map over an array and remove and element which returns exactly false.
     *
     * @static
     * @param array $array
     * @return array $array processed array with falses removed
     */
    public static function mapFilter(array $array, callable $callback)
    {
        return array_reduce(
            $array,
            function ($carry, $element) use ($array, $callback) {
                $element = call_user_func($callback, $element, $array);
                if ($element !== NO) {
                    $carry[] = $element;
                }
                return $carry;
            },
            array()
        );
    }
}
