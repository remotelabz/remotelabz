<?php

namespace RemoteLabz;

use RemoteLabz\Exception\NotRootException;

class System
{
    /**
     * Check if a command is installed on a system.
     *
     * @param string $cmd The command
     * @return bool
     */
    static public function commandExists($cmd) : bool {
        $return = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
        return !empty($return);
    }

    /**
     * Check if this script is executed as root.
     *
     * @return void
     */
    static public function checkRoot() {
        $username = posix_getpwuid(posix_geteuid())['name'];
        if ($username != "root") {
            throw new NotRootException("Installation aborted, root is required! Please launch this script as root or with sudo.");
        }
    }
}