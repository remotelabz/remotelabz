#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$files = scandir(dirname(__DIR__).'/backups/import/');
$databases = [];
foreach ($files as $file) {
    if (preg_match('/^.+\.sql$/', $file)) {
        array_push($databases, $file);
    }
}
if (count($databases) <1){
    return false;
}

$result=exec('mysql --user='.$_SERVER['MYSQL_USER'].' --password='.$_SERVER['MYSQL_PASSWORD'].' --host='.$_SERVER['MYSQL_SERVER'].'  '.$_SERVER['MYSQL_DATABASE'].' <'.dirname(__DIR__).'/backups/import/'.$databases[0],$output);
return $output;