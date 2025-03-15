<?php

namespace App\Bridge;

use Symfony\Component\Process\Process;

class Bridge
{
    /**
     * Returns the command to add before exec.
     *
     * @return string
     */
    public static function getCommand() : string
    {
        return '';
    }

    /**
     * Small wrapper for running symfony's `Process` object.
     *
     * @param array $command The command to execute. If the environment variable `USE_SUDO_FOR_SYSTEM_COMMANDS` is set to 1, the function will add `sudo` to the command before running.
     * @param boolean $mustRun If set to true, throws an exception if the process didn't terminate successfully.
     * @return Process The executed process.
     */
    public static function exec(array $command, bool $mustRun = true) : Process
    {
        array_unshift($command, static::getCommand());

        $useSudo = getenv('USE_SUDO_FOR_SYSTEM_COMMANDS') == "1" ? true : false;

        if ($useSudo) {
            array_unshift($command, 'sudo');
        }

        $process = new Process($command);
        if ($mustRun) {
            $process->mustRun();
        } else {
            $process->run();
        }

        return $process;
    }
}