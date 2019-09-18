<?php

namespace RemoteLabz;

class Logger
{
    /** @var string $file Log file path. */
    private $file;

    /**
     * @var string $dateFormat Date format for the log.
     */
    private $dateFormat;
    
    public const PRINT_DEFAULT  = "\e[39m";
    public const PRINT_YELLOW   = "\e[33m";
    public const PRINT_RED      = "\e[31m";

    function __construct($file = null, $dateFormat = DATE_RFC2822) {
        $this->file = $file;
        $this->dateFormat = $dateFormat;
    }

    static public function open($file = null, $mode = "a") {
        if ($file !== null) {
            $dir = dirname($file);
            if ( !is_dir($dir) ) {
                mkdir($dir);
                chgrp($dir, "adm");
            }
            $handle = fopen($file, $mode);
            return $handle;
        }
    }

    /**
     * Print debug information in file.
     *
     * @param mixed $string String or array to print
     */
    public function debug($string) {
        $handle = self::open($this->file);
        if ($handle !== null) {
            if (is_array($string)) {
                foreach ($string as $line) {
                    fprintf($handle, "[%s] %s\n", date($this->dateFormat), $line);
                }
            } elseif (is_string($string))
                fprintf($handle, "[%s] %s\n", date($this->dateFormat), $string);
        }
    }

    function warning($string) {
        $this->debug("WARNING: $string");
    }

    function error($string) {
        $this->debug("ERROR: $string");
    }

    /**
     * Print a string in console.
     *
     * @param string $string
     * @param string $color
     * @return void
     */
    static public function print($string, $color = Logger::PRINT_DEFAULT) {
        printf($color . "%s" . Logger::PRINT_DEFAULT, $string);
    }

    /**
     * Print a string in console. Adds PHP_EOL at the end.
     *
     * @param string $string
     * @param string $color
     * @return void
     */
    static public function println($string, $color = Logger::PRINT_DEFAULT) {
        printf($color . "%s" . Logger::PRINT_DEFAULT . PHP_EOL, $string);
    }

    /**
     * Returns log file path.
     * 
     * @return string
     */
    function getLogPath() {
        return $this->file;
    }

    /**
     * Get the value of file
     */ 
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Set the value of file
     *
     * @return  self
     */ 
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Get $dateFormat Date format for the log.
     *
     * @return  string
     */ 
    public function getDateFormat()
    {
        return $this->dateFormat;
    }

    /**
     * Set $dateFormat Date format for the log.
     *
     * @param  string  $dateFormat  $dateFormat Date format for the log.
     *
     * @return  self
     */ 
    public function setDateFormat(string $dateFormat)
    {
        $this->dateFormat = $dateFormat;

        return $this;
    }
}