<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Response
 * Helper functions for dealing with the response we're building
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
class Response
{
    /**
     * setHeader()
     * Set a header.
     *
     * @param string $key
     * @param string $value
     */
    public static function setHeader($header, $value)
    {
        header("$header: $value", YES);
    }

    /**
     * setContentType()
     * Set the content-type of this response
     *
     * @param string $contentType
     */
    public static function setContentType($contentType)
    {
        self::setHeader("Content-Type", $contentType);
    }

    /**
     * setLastModified()
     * Set the last-modified date of this response
     *
     * @param string $contentType
     */
    public static function setLastModified($time)
    {
        if (is_integer($time) === NO) {
            $time = strtotime($time);
        }

        if (empty($time)) {
            return;
        }

        self::setHeader("Last-Modified", str_replace(" +0000", " UTC", gmdate("r", $time)));
    }

    /**
     * setVary()
     * Set the vary header of this response
     *
     * @param string $header
     */
    public static function setVary($header)
    {
        // Allow multiple calls with a "NO"
        header("Vary: $header", NO);
    }

    /**
     * returnCode()
     * Return a specific HTTP status code
     *
     * @param int $statusCode
     */
    public static function returnCode($statusCode)
    {
        http_response_code($statusCode);
    }

    /**
     * redirectWithStatusCode()
     * Redirect to another location, using the status code provided
     *
     * @param int $statusCode
     */
    public static function redirectWithStatusCode($statusCode, $location)
    {
        // If this is not an absolute redirect, assume local
        if (strpos($location, "://") === NO) {
            $scheme = isset($_SERVER["HTTPS"]) ? "https" : "http";
            $location = sprintf("%s://%s%s", $scheme, getenv("HTTP_HOST"), $location);
        }

        // Output redirect header with status param
        header("Location: $location", YES, $statusCode);
        exit;
    }
}
