<?php

namespace RemoteLabz\Service;

use RemoteLabz\Logger;
use RemoteLabz\System;

class Shibboleth implements ServiceInterface
{
    static public function start(array $options, array $operands, Logger $log) : int
    {
        $return = 0;
        
        $log->debug("Starting Shibboleth service");
        if (!System::commandExists('shibd')) {
            $log->warning("shibd hasn't been found on your system! Shibboleth will not be available.");
        } else {
            $log->debug("Starting Shibboleth daemon");
            unset($output);
            exec("service shibd start 2>&1", $output, $return);
            $log->debug($output);
            if ($return) {
                $log->error("Failed to start Shibboleth daemon ! See the logs in ".$log->getLogPath()." to get more information.");
                Logger::print("Failed to start Shibboleth daemon ! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
            }
        }

        return $return;
    }
    
    static public function stop(array $options, array $operands, Logger $log) : int
    {
        $return = 0;
        
        $log->debug("Stopping Shibboleth service");
        if (!System::commandExists('shibd')) {
            $log->warning("shibd hasn't been found on your system!");
        } else {
            $log->debug("Stopping Shibboleth daemon");
            unset($output);
            exec("service shibd stop 2>&1", $output, $return);
            $log->debug($output);
            if ($return) {
                $log->error("Failed to stop Shibboleth daemon ! See the logs in ".$log->getLogPath()." to get more information.");
                Logger::print("Failed to stop Shibboleth daemon ! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
            }
            $log->debug("configurable-http-proxy is started.");
        }

        return $return;
    }

    static public function restart(array $options, array $operands, Logger $log) : int
    {
        $return = 0;
        
        $log->debug("Restarting Shibboleth service");
        if (!System::commandExists('shibd')) {
            $log->warning("shibd hasn't been found on your system!");
        } else {
            $log->debug("Restarting Shibboleth daemon");
            unset($output);
            exec("service shibd restart 2>&1", $output, $return);
            $log->debug($output);
            if ($return) {
                $log->error("Failed to restart Shibboleth daemon ! See the logs in ".$log->getLogPath()." to get more information.");
                Logger::print("Failed to restart Shibboleth daemon ! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
            }
        }

        return $return;
    }

    static public function reload(array $options, array $operands, Logger $log) : int
    {
        $return = 0;

        return $return;
    }
}