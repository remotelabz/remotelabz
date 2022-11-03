<?php

include_once dirname(__DIR__).'/lib/paulyg/autoloader/Autoloader.php';

use Paulyg\Autoloader;

Autoloader::addPsr4('GetOpt',       dirname(__DIR__).'/lib/ulrichsg/getopt-php/src/');
Autoloader::addPsr4('M1\Env',       dirname(__DIR__).'/lib/m1/Env/src/');
Autoloader::addPsr4('RemoteLabz',   dirname(__DIR__).'/lib/remotelabz/');