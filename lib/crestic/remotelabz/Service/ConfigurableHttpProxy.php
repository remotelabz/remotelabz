<?php

namespace RemoteLabz\Service;

use RemoteLabz\Logger;
use RemoteLabz\System;

class ConfigurableHttpProxy implements ServiceInterface
{
    static public function start(array $options, array $operands, Logger $log) : int
    {
        $return = 0;
        $port = $options['port'];

        $log->debug("Starting configurable-http-proxy with port ".$port);
        if (!System::commandExists('configurable-http-proxy')) {
            $log->warning("configurable-http-proxy hasn't been found on your system! Proxy will not be available.");
        } else {
            exec("ps -aux | grep -E 'configurable-http-proxy.*".$port."' | grep -v grep", $output, $return);
            if (count($output) == 0) {
                unset($output);
                exec("nohup configurable-http-proxy --port ".$port." > /dev/null 2>&1 &", $output, $return);
                // $log->debug($output);
                if ($return) {
                    $log->error("Failed to start configurable-http-proxy! See the logs in ".$log->getLogPath()." to get more information.");
                    Logger::print("Failed to start configurable-http-proxy! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
                }
                $log->debug("configurable-http-proxy is started.");
            } else {
                $log->warning("configurable-http-proxy seems to be already started.");
            }
        }

        return $return;
    }
    
    static public function stop(array $options, array $operands, Logger $log) : int
    {
        $return = 0;
        $port = $options['port'];

        $log->debug("Stopping configurable-http-proxy with port ".$port);
        if (!System::commandExists('configurable-http-proxy')) {
            $log->warning("configurable-http-proxy hasn't been found on your system!");
        } else {
            exec("ps -aux | grep -E 'configurable-http-proxy.*".$port."' | grep -v grep | awk '{print $2}'", $output, $return);
            if (count($output) !== 0) {
                $pid = $output[0];
                unset($output);
                exec("kill ".$pid, $output, $return);
                $log->debug($output);
                if ($return) {
                    $log->error("Failed to stop configurable-http-proxy! See the logs in ".$log->getLogPath()." to get more information.");
                    Logger::print("Failed to stop configurable-http-proxy! See the logs in ".$log->getLogPath()." to get more information.", Logger::COLOR_RED);
                }
                $log->debug('configurable-http-proxy is stopped.');
            } else {
                $log->warning("configurable-http-proxy seems not to be started.");
            }
        }

        return $return;
    }

    static public function restart(array $options, array $operands, Logger $log) : int
    {
        $return = 0;

        $return = self::stop($options, $operands, $log);
        $return = self::start($options, $operands, $log);

        return $return;
    }

    static public function reload(array $options, array $operands, Logger $log) : int
    {
        $return = 0;

        return $return;
    }
}