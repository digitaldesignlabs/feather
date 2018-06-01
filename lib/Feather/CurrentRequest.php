<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * CurrentRequest
 * Helper functions for dealing with this HTTP request
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 * @todo Better documentation
 */
class CurrentRequest
{
    public static function requestETag()
    {
        $headers = array_change_key_case(self::requestHeaders());
        return @$headers["if-none-match"];
    }

    public static function redirectWithStatusCode($statusCode, $location)
    {
        // If this is not an absolute redirect, assume local
        if (!strstr($location, '://')) {
            $scheme = isset($_SERVER["HTTPS"]) ? 'https' : 'http';
            $location = sprintf('%s://%s%s', $scheme, getenv('HTTP_HOST'), $location);
        }

        // Output redirect header
        header("Location: $location", YES, $statusCode);
        exit;
    }

    public static function language()
    {
        // Fetch the language header. If we can't find one, then
        // let's assume that it's English.
        $languages = self::requestHeaders("Accept-Language");
        if (!$languages) {
            return ["en"];
        }

        // Break up the language and normalize the q-values
        $languages = explode(",", $languages);
        foreach ($languages as &$language) {
            $language = explode(";q=", $language);
            if (isset($language[1])) {
                $language[1] = (float)$language[1];
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
        $return = array_map(function ($foo) {
            return array_shift($foo);
        }, $languages);
        return array_merge($return, ["en"]);
    }

    public static function setContentType($contentType)
    {
        header("Content-Type: " . $contentType, YES);
    }

    public static function setLastModified($time)
    {
        if (!is_integer($time)) {
            $time = strtotime($time);
        }

        if (empty($time)) {
            return;
        }

        header('Last-Modified: ' . str_replace(' +0000', ' UTC', gmdate('r', $time)), YES);
    }

    public static function isAjaxRequest()
    {
        return self::requestHeaders('X-Requested-With') === 'XMLHttpRequest';
    }

    public static function requestHeaders($header = null)
    {
        // Just get PHP to do it
        if (function_exists('apache_request_headers')) {

            $headers = apache_request_headers();

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

        if (is_null($header)) {
            return $headers;
        }

        if (isset($headers[$header])) {
            return $headers[$header];
        }

        return null;
    }

    public static function setHeader($header, $value)
    {
        header("$header: $value", YES);
    }

    public static function setVary($header)
    {
        header("Vary: $header", NO);
    }

    public static function returnCode($code)
    {
        http_response_code($code);
    }
}
