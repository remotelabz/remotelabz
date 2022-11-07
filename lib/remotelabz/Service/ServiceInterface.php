<?php

namespace RemoteLabz\Service;

use RemoteLabz\Logger;

/**
 * Implementation of system-wide RemoteLabz service commands.
 * 
 * @author Julien Hubert <julien.hubert1@univ-reims.fr>
 * @since 2.0.0
 */
interface ServiceInterface
{
    static public function start(array $options, array $operands, Logger $log) : int;

    static public function stop(array $options, array $operands, Logger $log) : int;

    static public function restart(array $options, array $operands, Logger $log): int;

    static public function reload(array $options, array $operands, Logger $log): int;
}