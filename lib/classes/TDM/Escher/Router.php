<?php

/**
 * Escher Framework v2.0
 *
 * @copyright 2000-2014 Twist Digital Media
 * @package   \TDM\Escher
 * @license   https://raw.github.com/twistdigital/escher/master/LICENSE
 *
 * Copyright (c) 2000-2014, Twist Digital Media
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice, this
 *    list of conditions and the following disclaimer in the documentation and/or
 *    other materials provided with the distribution.
 *
 * 3. Neither the name of the {organization} nor the names of its
 *    contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace TDM\Escher;

/**
 * Router
 *
 * A really simple router
 *
 * @author      Mike Hall <mike.hall@twistdigital.co.uk>
 * @copyright   2014 Twist Digital Media
 */

class Router
{
    // Static routes are stored in here, in the format ["GET"]["/"] = $callback
    private $fastRoutes   = [];

    // Slower, wildcard, routes are stored in these variables
    private $slowRoutes   = [];
    private $routeRegexes = [];

    // This determines how many regex routes we check at once. Too big and
    // we use a load of memory. Too small and we hit the PCRE engine too often.
    // A chunk size of 10 seems to be a happy medium.
    public $chunkSize = 10;

    /**
     * Sanitise the HTTP verb.
     *
     * If the passed verb is POST, it returns POST. Otherwise, it returns GET. Escher\Router only
     * supports POST and GET verbs. We could do others, I suppose? But is there really a point for
     * a browser-based application framework?
     *
     * @param String $method The method to clean
     * @return String A valid HTTP method
     */
    private function cleanMethod($method)
    {
        $method = strtoupper($method);
        if ($method === "POST") {
            return "POST";
        }
        return "GET";
    }

    /**
     * Convert placeholders to regex format
     *
     * Translates the [:any]-style route placeholders into regex so we can use it for matching.
     *
     * @param String $path The path to translate
     * @return String The translated path
     */
    private function convertPathToRegex($path)
    {
        return strtr($path, array(
            '[:any]'   => '([^/]+)',
            '[:all]'   => '([^/]+)',
            '[:num]'   => '([0-9]+)',
            '[:nonum]' => '([^0-9]+)',
            '[:alpha]' => '([A-Za-z]+)',
            '[:alnum]' => '([A-Za-z0-9]+)',
            '[:hex]'   => '([A-Fa-f0-9]+)',
        ));
    }

    /**
     * Register a wildcard route
     *
     * Registers a wildcare route in the routing table, and stores the regex
     * in an equivalent position in the regex table.
     *
     * @param String $method The HTTP-verb
     * @param String $path The path to register
     * @param Callable $callback The function/method to run for this route
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
     * Compile a regex statement
     *
     * Compile a list of regular expressions into a single regex chunk
     * and mark each chunk with a number of empty "dummy" groups at the
     * end of the expression. We can count these dummy groups later to find out
     * which of the expressions matched, and therefore where we should be routing.
     *
     * @param Array $chunk List of paths to match, in regex format
     * @return String Compiled regular expression
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
     * Search the slow routing table
     *
     * Searches the slow routing table for a match for the passed method and path
     * In the event that one is found, it returns the callback to run. Otherwise,
     * it return false.
     *
     * @param String $method The HTTP-verb to check (or * for generic)
     * @param String $path The path to check
     * @return Callable A route, or false on no match
     */
    private function searchWildcardRoutes($method, $path)
    {
        // If there are no entries in this routing table at all, there
        // is no point in checking it.
        if (!isset($this->routeRegexes[$method])) {
            return false;
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
                return function () use ($route, $matches) {
                    call_user_func_array($route, $matches);
                };
            }
            
            // Failed to route this request
            return function () {
                HTTP\StatusCode::returnCode(500);
                exit;
            };
        }

        // No matches found
        return false;
    }

    /**
     * Specifies a route to map to a request
     *
     * Takes either ($path, $callback) or ($method, $path, $callback)
     * and adds to the routing tables so we can route the request later.
     *
     * @param String $method The HTTP verb
     * @param String $path The path for this route
     * @param Callable $callback Where we route to
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
        } elseif (sizeof($arguments === 3)) {
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
        $isStaticRoute = strpos($path, "[") === false;

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
     * Route a request
     *
     * Takes a method and a path, and returns an appropriate callback.
     *
     * @param String $method The HTTP-verb to check
     * @param String $path The path to check
     * @param Callable An appropriate callback for this route
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

        // Cannot route this request - return a not found
        return function () {
            HTTP\StatusCode::returnCode(404);
            exit;
        };
    }
}