<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * Settings
 * This class will import into itself all the keys it finds in settings.ini
 * file that it finds somewhere in the directory tree above it
 *
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 */
class Settings extends Singleton
{
    public function __construct()
    {
        // Look for a settings.ini file in the project root
        $filename = ROOTDIR . "/settings.ini";
        if (is_readable($filename) === NO) {
            trigger_error("No readable settings.ini file found in the project root");
            return (object) null;
        }

        $settings = parse_ini_file($filename, YES);
        foreach ($settings as $key => $value) {
            $this->$key = $value;
        }
    }
}
