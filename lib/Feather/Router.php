<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Router
 * A simple and hopefully nippy router
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 * @todo Measure performance to back up the nippy claim!
 */
class Router
{
    /**
     * Static routes are stored in here, in the format ["GET"]["/"] = $callback
     * @var array
     * @access private
     */
    private $fastRoutes = [];

    /**
     * Slower, wildcard, routes are stored in these variables
     * @var array
     * @access private
     */
    private $slowRoutes = [];
    private $routeRegexes = [];

    /**
     * This determines how many regex routes we check at once. Too big and
     * we use a load of memory. Too small and we hit the PCRE engine too often.
     * A chunk size of 10 seems to be a happy medium.
     * @var int
     * @access public
     */
    public $chunkSize = 10;

    /**
     * cleanMethod()
     * If the passed verb is POST, it returns POST. Otherwise, it returns GET. Escher\Router only
     * supports POST and GET verbs. We could do others, I suppose? But is there really a point for
     * a browser-based application framework?
     *
     * @access private
     * @param string $method - The method to clean
     * @return string - A valid HTTP method
     */
    private function cleanMethod($method)
    {
        $method = strtoupper($method);
        if ($method === "POST") {
            return "POST";
        }

        if ($method === "*") {
            return "*";
        }

        return "GET";
    }

    /**
     * convertPathToRegex()
     * Translates the [:any]-style route placeholders into regex so we can use it for matching.
     *
     * @access private
     * @param string $path - The path to translate
     * @return string - The translated path
     */
    private function convertPathToRegex($path)
    {
        return strtr($path, array(
            '[:any]' => '([^/]+)',
            '[:all]' => '([^/]+)',
            '[:num]' => '([0-9]+)',
            '[:nonum]' => '([^0-9]+)',
            '[:alpha]' => '([A-Za-z]+)',
            '[:alnum]' => '([A-Za-z0-9]+)',
            '[:hex]' => '([A-Fa-f0-9]+)',
            '[:slug]' => '([0-9a-z\-]+)',
        ));
    }

    /**
     * registerWildcardRoute()
     * Registers a wildcare route in the routing table, and stores the regex
     * in an equivalent position in the regex table.
     *
     * @access private
     * @param string $method - The HTTP-verb
     * @param string $path - The path to register
     * @param callable $callback - The function/method to run for this route
     * @return void
     */
    private function registerWildcardRoute($method, $path, $callback)
    {
        // Translate to regex format
        $regex = $this->convertPathToRegex($path);

        // Add the regex into the regex table
        $this->routeRegexes[$method][] = $regex;

        // Add the callback to the slow route table
        $this->slowRoutes[$method][]   = $callback;
    }

    /**
     * compileRegexChunk()
     * Compile a list of regular expressions into a single regex chunk
     * and mark each chunk with a number of empty "dummy" groups at the
     * end of the expression. We can count these dummy groups later to find out
     * which of the expressions matched, and therefore where we should be routing.
     *
     * @access private
     * @param array $chunk - List of paths to match, in regex format
     * @return string - Compiled regular expression
     */
    private function compileRegexChunk($chunk)
    {
        $compiled = '#^(?|';
        foreach ($chunk as $i => $regex) {
            $compiled .= $regex . str_repeat("()", $i) . "|";
        }
        $compiled = rtrim($compiled, "|") . ")$#x";
        return $compiled;
    }

    /**
     * searchWildcardRoutes()
     * Searches the slow routing table for a match for the passed method and path
     * In the event that one is found, it returns the callback to run. Otherwise,
     * it return NO.
     *
     * @access private
     * @param string $method - The HTTP-verb to check (or * for generic)
     * @param string $path - The path to check
     * @return callable - A route, or NO for no match
     */
    private function searchWildcardRoutes($method, $path)
    {
        // If there are no entries in this routing table at all, there
        // is no point in checking it.
        if (!isset($this->routeRegexes[$method])) {
            return NO;
        }

        // Get the list of regex routes for this method, and split it up into chunks
        $regexes = array_chunk($this->routeRegexes[$method], $this->chunkSize);
        foreach ($regexes as $tens => $chunk) {

            // Merge this chunk into a single regex expression
            // and test it. If there is no match, try the next chunk.
            $regex = $this->compileRegexChunk($chunk);
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            // Great we have a match. Ditch the first group, as this
            // simply contains the full match, which we don't care about.
            array_shift($matches);

            // To locate the right route, we count the number of "dummy"
            // empty matches we have at the end of the match array.
            // Combining this number with the chunk number we are on, gives us
            // the array offset of the callback in the slowRoutes table.
            $matches = array_reverse($matches);
            for ($dummyMatches = 0; "" === $matches[$dummyMatches]; $dummyMatches += 1) {
                // Nothing to do in here.
            }

            // What's left in the matches array will be the arguments to pass into the callback
            $matches = array_reverse(array_slice($matches, $dummyMatches));

            // Find which route this was - by taking into account how many chunks
            // we have and how many dummy matches there were.
            $routeNumber = ($tens * $this->chunkSize) + $dummyMatches;

            // Return a callback to execute the route, with parameters
            if (isset($this->slowRoutes[$method][$routeNumber])) {
                $route = $this->slowRoutes[$method][$routeNumber];
                if (is_callable($route)) {
                    return function () use ($route, $matches) {
                        return call_user_func_array($route, $matches);
                    };
                }
            }
        }

        // No matches found
        return NO;
    }

    /**
     * map()
     * Takes either ($path, $callback) or ($method, $path, $callback)
     * and adds to the routing tables so we can route the request later.
     *
     * @access public
     * @param string $method - The HTTP verb
     * @param string $path - The path for this route
     * @param callable $callback - Where we route to
     * @return void
     */
    public function map()
    {
        // Variable number of arguments means we have to parse them out manually.
        $arguments = func_get_args();

        // Two arguments means we got ($path, $callback)
        if (sizeof($arguments) === 2) {
            $method   = "*";
            $path     = strtolower(array_shift($arguments));
            $callback = array_shift($arguments);

        // Three arguments means we got ($method, $path, $callback)
        } elseif (sizeof($arguments) === 3) {
            $method   = $this->cleanMethod(array_shift($arguments));
            $path     = strtolower(array_shift($arguments));
            $callback = array_shift($arguments);

        // Anything else is an error
        } else {
            trigger_error("Invalid number of parameters passed to Router::map", E_USER_ERROR);
            return;
        }

        // If the path contains a [ character, then we consider it to
        // be a wildcard route, which means it uses regular expressions
        // for parsing. If not, it's a static route. We should use static
        // routes where possible, as they're much faster.
        $isStaticRoute = strpos($path, "[") === NO;

        // If this is a static route, just add it to the routing
        // table and we are already done.
        if ($isStaticRoute) {
            $this->fastRoutes[$method][$path] = $callback;
            return;
        }

        // Otherwise, we need to translate route into PCRE and store it
        // in the slower routing table.
        $this->registerWildcardRoute($method, $path, $callback);
    }

    /**
     * route()
     * Takes a method and a path, and returns an appropriate callback.
     *
     * @access public
     * @param string $method - The HTTP-verb to check
     * @param string $path - The path to check
     * @param callable - An appropriate callback for this route
     */
    public function route($method, $path)
    {
        // Check for static routes for this method
        if (isset($this->fastRoutes[$method][$path])) {
            return $this->fastRoutes[$method][$path];
        }

        // Check for a generic static route
        if (isset($this->fastRoutes["*"][$path])) {
            return $this->fastRoutes["*"][$path];
        }

        // Check for a wildcard route for this method
        $route = $this->searchWildcardRoutes($method, $path);
        if (is_callable($route)) {
            return $route;
        }

        // Check for a generic wildcard route
        $route = $this->searchWildcardRoutes("*", $path);
        if (is_callable($route)) {
            return $route;
        }

        return null;
    }
}
