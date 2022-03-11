<?php

namespace RemoteLabz\Configurator;

use RemoteLabz\Logger;
use RemoteLabz\System;
use RemoteLabz\Configurator\Database;
use RemoteLabz\Exception\NotRootException;
use RemoteLabz\Exception\ConfigurationException;

class Configurator
{
    static public function getRegisteredServices(): array
    {
        return [
            "database" => Database::class,
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

        if ($action === "all") {
            foreach (self::getRegisteredServices() as $service) {
                $hasError = false;
                $return = call_user_func([$service, 'configure'], $options, $operands, $log);
                if ($return) {
                    $hasError = true;
                }
            }
        } else {
            $log->debug("Command " . $action . " invoked");
            $hasError = false;
            try {
                call_user_func([self::getRegisteredServices()[$action], 'configure'], $options, $operands, $log);
            } catch (ConfigurationException $e) {
                Logger::println($e->getMessage(), Logger::COLOR_RED);
                $hasError = true;
            }
        }

        if ($hasError) {
            $log->error("Command terminated with errors!");
            Logger::println("Command terminated with errors! See logs at " . $log->getLogPath() . " to get more information.", Logger::COLOR_RED);
        } else {
            $log->debug("Command ended without error.");
            Logger::println("Command \"" . $action . "\" terminated successfully.");
        }
    }
}
