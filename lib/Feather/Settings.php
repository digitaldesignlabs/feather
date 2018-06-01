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
        // Ascend the directory tree, looking for a settings.ini file
        $basedir = dirname(__DIR__);
        while ($basedir !== DIRECTORY_SEPARATOR) {

            $basedir = dirname($basedir);

            $file = $basedir . "/settings.ini";
            if (is_readable($file) === YES) {
                $settings = parse_ini_file($file, YES);
                foreach ($settings as $key => $value) {
                    $this->$key = $value;
                }
                return;
            }
        }

        // Found nothing
        trigger_error("Unable to find settings file in any parent directory");
    }
}
