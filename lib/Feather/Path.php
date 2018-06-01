<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Path
 * A small library for resolving paths
 *
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
class Path
{
    /**
     * normaliseStringPosix()
     * Normalise the supplied path, accounting for things like .. and .
     *
     * @access private
     * @param string $path
     * @return string
     */
    private static function normaliseStringPosix(string $path)
    {
        // If realpath() can handle this for us, then great!
        $realpath = realpath($path);
        if ($realpath !== NO) {
            return $realpath;
        }

        // Realpath let us down :(
        // This is likely because the path doesn't exist, but that doesn't means we can't resolve it.
        // Instead we're going to split it up into pieces and then work backward across the path until we can
        // find *some* part of it which we can resolve!

        $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
        while (end($parts) !== NO) {

            // Strip out the end
            array_pop($parts);

            // Try and resolve this portion
            $attempt = DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
            $realpath = realpath($attempt);

            // If this succeeds, then cat the remaining path on the end of the
            if ($realpath !== NO) {
                return $realpath.substr($path, strlen($attempt));
            }
        }

        return NO;
    }

    /**
     * posixResolver()
     * Resolve a supplied path, assuming the path is from a POSIX file system.
     *
     * @access private
     * @param array $paths - An array of components to resolve
     * @return string
     */
    private static function posixResolver(array $paths)
    {
        $resolved = "";

        // Loop over the paths in reverse order, so we build up the path from right to left.
        // This is so if we encounter root along the way, we don't need to continue processing.
        // We also loop from length to -1 -- where in the -1 case, if we have not yet encountered
        // the root, we can add in the current working directory.
        for ($index = count($paths) - 1; $index >= -1; $index -= 1) {

            // If we have run out of parts, then we're
            if ($index < 0) {
                $path = getcwd();
            } else {
                $path = $paths[$index];
            }

            // If there is nothing to this piece, then do nothing and continue
            if (empty($path) === YES) {
                continue;
            }

            // If there is any exiting data in the path, then cat onto that.
            if (strlen($resolved) > 0) {
                $resolved = $path . DIRECTORY_SEPARATOR . $resolved;
            } else {
                $resolved = $path;
            }

            // If the path looks to be absolute, then we're finished
            if ($path[0] === DIRECTORY_SEPARATOR) {
                break;
            }
        }

        // Normalize out things like .. and .
        return self::normaliseStringPosix($resolved);
    }

    /**
     * win32Resolver()
     * Resolve a supplied path, assuming the path is from a Windows-type file system.
     * I haven't done any of this yet.
     *
     * @access private
     * @param array $paths - An array of components to resolve
     * @return string
     */
    private static function win32Resolver(array $paths)
    {
        throw new Exception("Not Implemented");
    }

    /**
     * resolve()
     * Resolve a supplied path.
     *
     * @access private
     * @param ...array
     * @return string
     */
    public static function resolve()
    {
        $parts = func_get_args();

        if (strncasecmp(PHP_OS, "WIN", 3) === 0) {
            return self::win32Resolver($parts);
        }

        return self::posixResolver($parts);
    }
}
