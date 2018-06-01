<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Request
 * Helper functions for dealing with the current HTTP request
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
class Request
{
    /**
     * headers()
     * Get a specific header from this request, or all headers if none specified
     *
     * @param string $header (optional)
     * @return string null on error
     */
    public static function headers($header = null)
    {
        // Just get PHP to do it
        if (function_exists("getallheaders") === YES) {
            $headers = getallheaders();

        } else {

            // Work it out by hand
            static $headers;
            if (empty($headers)) {
                foreach ($_SERVER as $key => $value) {
                    if (substr($key, 0, 5) === 'HTTP_') {
                        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                        $headers[$key] = $value;
                    }
                }
            }
        }

        if (is_null($header) === YES) {
            return $headers;
        }

        return Collection::find($headers, function ($ignore, $candidate) use ($header) {
            return strcasecmp($candidate, $header) === 0;
        });
    }

    /**
     * etag()
     * Get the etag of the this request
     *
     * @return string null on error
     */
    public static function etag()
    {
        return self::headers("If-None-Match");
    }

    /**
     * language()
     * Get the language of the this request
     *
     * @return array
     */
    public static function language()
    {
        // Fetch the language header.
        // If we can't find one, then let's assume that it's English.
        $languages = self::headers("Accept-Language");
        if (empty($languages) === YES) {
            return ["en"];
        }

        // Break up the language and normalize the q-values
        $languages = explode(",", $languages);
        foreach ($languages as &$language) {
            $language = explode(";q=", $language);
            if (isset($language[1])) {
                $language[1] = floatval($language[1]);
            } else {
                $language[1] = 1;
            }
        }
        unset($language); // Break the reference

        // Sort into q-value order. We can't just do a simple subtraction here,
        // because PHP casts the return value to an integer for comparison, so
        // small returns like 0.3 are cast to 0.
        usort($languages, function ($a, $b) {
            return (100 * $b[1]) - (100 * $a[1]);
        });

        // Return the correct language, with english as a backstop
        $return = Collection::map($languages, function ($foo) {
            return array_shift($foo);
        });

        return array_merge($return, ["en"]);
    }

    /**
     * isAjaxRequest()
     * Detect an old-style jQuery AJAX request
     *
     * @return boolean
     */
    public static function isAjaxRequest()
    {
        return self::headers("X-Requested-With") === "XMLHttpRequest";
    }
}
