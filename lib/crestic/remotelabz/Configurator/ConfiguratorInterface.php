<?php

namespace RemoteLabz\Configurator;

use RemoteLabz\Logger;

/**
 * Implementation of system-wide RemoteLabz configuration commands.
 * 
 * @author Julien Hubert <julien.hubert1@univ-reims.fr>
 * @since 2.0.0
 */
interface ConfiguratorInterface
{
    static public function configure(array $options, array $operands, Logger $log) : int;
}