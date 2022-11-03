<?php

namespace RemoteLabz\Service;

use GetOpt\Operand;
use RemoteLabz\Logger;
use RemoteLabz\System;
use RemoteLabz\Exception\NotRootException;

/**
 * Implementation of system-wide RemoteLabz service commands.
 * 
 * @author Julien Hubert <julien.hubert1@univ-reims.fr>
 * @since 2.0.0
 */
class Service
{
    static public function getRegisteredServices() : array {
        return [
            Apache::class,
            ConfigurableHttpProxy::class,
            Shibboleth::class
        ];
    }

    static public function handle(array $options, array $operands, Logger $log)
    {
        $action = $operands[0];

        try {
            System::checkRoot();
        } catch (NotRootException $e) {
            Logger::println("You must be root to execute this command.", Logger::COLOR_RED);
            return;
        }
        $log->debug("Command ".$action." invoked");

        foreach (self::getRegisteredServices() as $service) {
            $hasError = false;
            $return = call_user_func([$service, $action], $options, $operands, $log);
            if ($return) {
                $hasError = true;
            }
        }

        if ($hasError) {
            $log->error("Command terminated with errors!");
            Logger::print("Command terminated with errors!", Logger::COLOR_RED);
        } else {
            $log->debug("Command ended without error.");
            Logger::print("Command \"".$action."\" terminated succesfully." . PHP_EOL);
        }
    }
}