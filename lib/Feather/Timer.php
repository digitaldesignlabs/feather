<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Timer
 * A basic page timer
 * @author Scott Culverhouse
 * @copyright GG.COM Ltd
 * @license MIT
 */
class Timer
{
    private $start;
    private $hostname;

    public function __construct()
    {
        // Initialise the timings array
        $this->start = microtime(YES);

        // Get the hostname
        $host = strtoupper(gethostname());

        // We just want the first part
        $host = explode('.', $host);
        $this->hostname = $host[0];
    }

    public function getString()
    {
        return sprintf(
            'Served in %2.3fs%s',
            microtime(YES) - $this->start,
            strlen($this->hostname) ? (' by ' . $this->hostname) : ''
        );
    }
}
