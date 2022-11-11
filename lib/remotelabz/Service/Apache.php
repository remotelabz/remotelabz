<?php

namespace RemoteLabz\Service;

use RemoteLabz\Logger;

class Apache implements ServiceInterface
{
    static public function start(array $options, array $operands, Logger $log) : int
    {
        $log->debug("Starting Apache service");
        exec("service apache2 start 2>&1", $output, $return);
        $log->debug($output);
        if ($return) {
            $log->error("Failed to start Apache ! See the logs in ".$log->getLogPath()." to get more information.");
            Logger::print("Failed to start Apache ! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
        }
        $log->debug("configurable-http-proxy is started.");

        return $return;
    }

    static public function stop(array $options, array $operands, Logger $log) : int
    {
        $log->debug("Stopping Apache service");
        exec("service apache2 stop 2>&1", $output, $return);
        $log->debug($output);
        if ($return) {
            $log->error("Failed to stop Apache ! See the logs in ".$log->getLogPath()." to get more information.");
            Logger::print("Failed to stop Apache ! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
        }

        return $return;
    }

    static public function restart(array $options, array $operands, Logger $log) : int
    {
        $log->debug("Restarting Apache service");
        exec("service apache2 restart 2>&1", $output, $return);
        $log->debug($output);
        if ($return) {
            $log->error("Failed to restart Apache ! See the logs in ".$log->getLogPath()." to get more information.");
            Logger::print("Failed to restart Apache ! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
        }

        return $return;
    }

    static public function reload(array $options, array $operands, Logger $log) : int
    {
        $return = 0;

        return $return;
    }
}