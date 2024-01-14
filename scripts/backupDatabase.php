#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$date = date('d-m-Y_H-i-s');
$filename = 'database_backup_'.$date.'.sql';
$fileSystem = new Filesystem();
$folderName = 'backup_'.$date;
$fileSystem->mkdir(dirname(__DIR__).'/backups/'.$folderName);

$mysqli = new mysqli($_SERVER['MYSQL_SERVER'], $_SERVER['MYSQL_USER'], $_SERVER['MYSQL_PASSWORD'], $_SERVER['MYSQL_DATABASE']);
$lines = $mysqli->query('SHOW TABLES');
$tables="";
foreach($lines as $line) {
    if(count(explode("instance", $line["Tables_in_remotelabz"])) == 1){ 
        $tables .= $line["Tables_in_remotelabz"] ." ";
    }
}

$result=exec('mysqldump '.$_SERVER['MYSQL_DATABASE'].' --password='.$_SERVER['MYSQL_PASSWORD'].' --user='.$_SERVER['MYSQL_USER'].' --host='.$_SERVER['MYSQL_SERVER'].' --no-tablespaces '.$tables.'>'.dirname(__DIR__).'/backups/'.$folderName.'/'.$filename,$output);
$render = ["folderName"=> $folderName, "filename"=> $filename];
if(empty($output)){echo $folderName;}
else {return false;}
