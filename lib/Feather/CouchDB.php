<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

// Load required classes
use GuzzleHttp\Client;

/**
 * CouchDB
 * Interface to CouchDB databases
 * @copyright GG.COM Ltd
 * @license MIT
 * @author Mike Hall
 */
class CouchDB extends Singleton
{
    /**
     * Keeps a copy of the Guzzle client
     * @var Guzzle\Client
     * @access private
     */
    private $guzzle;

    /**
     * Constructor. Creates a Guzzle object, with the BaseURI set to whatever
     * is in the Escher settings file for the couch host, or whatever is passed as a param instead.
     * @param string $base - The base path to use (optional)
     * @access protected
     */
    protected function __construct($base = null)
    {
        $settings = Settings::instance()->couchdb;
        $this->guzzle = new Client([
            "base_uri" => $base ?: $settings["host"]
        ]);
    }

    /**
     * Process response objects from guzzle
     * @param Guzzle\Http\Message\Response $response - a guzzle response
     * @return array
     */
    private function parse($response)
    {
        // Status code is important, we need to check this
        $statusCode = $response->getStatusCode();
        return array(

            // Any 200-ish status code is considered OK
            "ok" => $statusCode >= 200 && $statusCode <= 299,

            // Return the interesting header data
            "statusCode" => $statusCode,
            "headers" => $response->getHeaders(),

            // The body should always be json
            "body" => json_decode($response->getBody(), YES),
        );
    }

    /**
     * Performs an HTTP request with the specified parameters
     * @param string $method - The HTTP verb to use
     * @param string $path - The path to request
     * @param array $options - The options for this request
     * @return array - The parsed response
     */
    private function request($method, $path, array $options)
    {
        try {
            $response = $this->guzzle->request(strtoupper($method), $path, $options);
        } catch (\Exception $e) {
            if ($e->hasResponse() === NO) {
                return ["ok" => NO, "status" => 500, "body" => "Internal Server Error"];
            }
            $response = $e->getResponse();
        }
        return $this->parse($response);
    }

    /**
     * Gets a Couch document from the specified path
     * @param string $path - The resource to fetch
     * @param array $headers - Any additional headers to supply (optional)
     * @param array $options - Any options to pass to Guzzle (optional)
     * @return array - The parsed response
     */
    public static function get($path, array $headers = [], array $options = [])
    {
        $couch = self::instance();
        return $couch->request("GET", $path, array_merge($options, ["headers" => $headers]));
    }

    /**
     * Gets a Couch View from the specified location
     * @param string $db - The database name (supply an empty string if this is in the base path already)
     * @param string $design - The name of the design document
     * @param string $view - The name of the view within the design document
     * @param array $query - Any parameters to pass on the query string (optional)
     * @param array $headers - Any additional headers to supply (optional)
     * @param array $options - Any options to pass to Guzzle (optional)
     * @return array - The parsed response
     */
    public static function view($db, $design, $view, array $query = [], array $headers = [], array $options = [])
    {
        // JSON encode any parameters, with the exception of this list
        $transparent = ["stale", "startkey_docid", "endkey_docid"];
        $params = [];
        foreach ($query as $key => $value) {
            if (in_array($key, $transparent) === NO) {
                $value = json_encode($value);
            }
            $params[$key] = $value;
        }

        $uri = sprintf(
            "%s/_design/%s/_view/%s?%s",
            $db,
            urlencode($design),
            urlencode($view),
            http_build_query($params)
        );

        return self::get($uri, $headers, $options);
    }

    /**
     * Deletes the resource at the specified path
     * @param string $path - The resource to delete
     * @param string $rev - The latest revision of the resource
     * @param array $headers - Any additional headers to supply (optional)
     * @param array $options - Any options to pass to Guzzle (optional)
     * @return array - The parsed response
     */
    public static function delete($path, $rev, array $headers = [], $options = [])
    {
        $couch = self::instance();
        $uri = $path . "?" . http_build_query(["rev" => $rev]);
        return $couch->request("DELETE", $uri, array_merge($options, ["headers" => $headers]));
    }

    /**
     * Posts data to the database
     * @param string $db - The database name (supply an empty string if this is in the base path already)
     * @param array $params - The data to post
     * @param array $headers - Any additional headers to supply (optional)
     * @param array $options - Any options to pass to Guzzle (optional)
     * @return array - The parsed response
     */
    public static function post($path, array $params, array $headers = [], array $options = [])
    {
        $couch = self::instance();

        $options = array_merge(
            $options,
            array(
                "headers" => $headers,
                "json" => $params,
            )
        );

        return $couch->request("POST", $path, $options);
    }

    /**
     * Puts data to the database
     * @param string $db - The database name (supply an empty string if this is in the base path already)
     * @param array $params - The data to put
     * @param array $headers - Any additional headers to supply (optional)
     * @param array $options - Any options to pass to Guzzle (optional)
     * @return array - The parsed response
     */
    public static function put($path, array $params, array $headers = [], array $options = [])
    {
        $couch = self::instance();

        $options = array_merge(
            $options,
            array(
                "headers" => $headers,
                "json" => $params,
            )
        );

        return $couch->request("PUT", $path, $options);
    }

    /**
     * Removes Couch meta data from the document
     * @param array $doc - The document to clean
     * @return array - The cleaned document
     */
    public static function cleanup(array $doc)
    {
        return Collection::omit($doc, ["_id", "_rev", "type"]);
    }

    /**
     * Removes Couch meta data from output of a view
     * @param array $doc - The list of documents to clean
     * @return array - The cleaned documents
     */
    public static function cleanupView(array $docs)
    {
        return Collection::map($docs, function ($doc) {
            if (isset($doc["doc"]) === YES) {
                $doc = $doc["doc"];
            }
            return self::cleanup($doc);
        });
    }
}
