<?php

/**
 * Feather Framework
 * @package DDL\Feather
 */

namespace DDL\Feather;

/**
 * ProcessManager
 * A Process Manager for PHP batch scripts, with support for locking and logging
 * @author Mike Hall
 * @copyright GG.COM Ltd
 * @license MIT
 * @todo Needs a complete overall, makes some unwarranted assumptions about writable paths
 */
class ProcessManager
{
    /**
     * Contains the path to the lock file
     * @var string
     * @access private
     */
    private static $lockFilePath = null;

    /**
     * Contains the path to the log file
     * @var string
     * @access private
     */
    private static $logFilePath = null;

    /**
     * Contains an instance of this object
     * @var object
     * @access private
     */
    private static $processHandler = null;

    /**
     * Should I echo log messages?
     * @var bool
     * @access public
     */
    public static $verbose = NO;

    /**
     * Constructor
     *
     * @access private
     * @return void
     */
    private function __construct()
    {
        // Load the settings
        $settings = Settings::instance();
        if (property_exists($settings, "process") === NO) {
            trigger_error("No process settings found", E_USER_ERROR);
        }

        // Configure the path to the lock files
        self::$lockFilePath = sprintf(
            '%s/%s.lock',
            $settings->process["lockpath"],
            strtolower(basename($_SERVER['PHP_SELF'], '.php'))
        );

        // Configure path to the log files
        self::$logFilePath = sprintf(
            '%s/%s.log',
            $settings->process["logpath"],
            strtolower(basename($_SERVER['PHP_SELF'], '.php'))
        );
    }

    /**
     * realLock()
     * Lock current script
     *
     * @access private
     * @return bool - YES on successful lock, NO on error
     */
    private static function realLock()
    {
        clearstatcache();
        if (file_exists(self::$lockFilePath) === NO) {
            return !!file_put_contents(self::$lockFilePath, getmypid());
        }
        return NO;
    }

    /**
     * realUnlock()
     * Unlock current script. Removes the current lock file, if it was created by this process
     *
     * @access private
     */
    private static function realUnlock()
    {
        clearstatcache();
        if (file_exists(self::$lockFilePath) && trim(file_get_contents(self::$lockFilePath)) == getmypid()) {
            @unlink(self::$lockFilePath);
        }
    }

    /**
     * log()
     * Write log file entry
     *
     * @access public
     * @param string $msg - Text to log
     */
    public static function log()
    {
        $args = func_get_args();

        if (sizeof($args) > 1) {
            $msg = vsprintf(array_shift($args), $args);
        } else {
            $msg = $args[0];
        }

        self::init();
        $msg = sprintf("%s[%s]: %s\n", date('Y-m-d H:i:s'), getmypid(), $msg);
        if (self::$verbose) {
            echo $msg;
        }

        @error_log($msg, 3, self::$logFilePath);
    }

    /**
     * lock()
     * Public lock file interface
     *
     * @access public
     * @return bool - YES on successful lock, NO on error
     */
    public static function lock()
    {
        self::init();
        clearstatcache();
        if (file_exists(self::$lockFilePath)) {
            self::log('Process is locked');
            return NO;
        }

        if (self::realLock()) {
            self::log('Lock obtained');
            return YES;
        }

        self::log('Failed to obtain file lock');
        return NO;
    }

    /**
     * unlock()
     * Public unlock file interface

     * @access public
     */
    public static function unlock()
    {
        self::init();
        self::realUnlock();
    }

    /**
     * whoLocked()
     * What process holds the lock?
     *
     * @access public
     * @return string - The PID of the locking process, or null if can't get PID
     */
    public static function whoLocked()
    {
        self::init();
        return file_exists(self::$lockFilePath)
            ? file_get_contents(self::$lockFilePath)
            : null;
    }

    /**
     * init()
     * Initialise system
     *
     * @access public
     */
    public static function init()
    {
        if (self::$processHandler instanceof ProcessManager === NO) {
            self::$processHandler = new ProcessManager();
        }
    }

    /**
     * __destruct()
     * Automatically unlocks the process at shutdown
     *
     * @access public
     */
    public function __destruct()
    {
        self::realUnlock();
    }
}
