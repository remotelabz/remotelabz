#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$filename = 'database_backup_'.date('d-m-Y_H-i-s').'.sql';

$mysqli = new mysqli($_SERVER['MYSQL_SERVER'], $_SERVER['MYSQL_USER'], $_SERVER['MYSQL_PASSWORD'], $_SERVER['MYSQL_DATABASE']);
$lines = $mysqli->query('SHOW TABLES');
$tables="";
foreach($lines as $line) {
    if(count(explode("instance", $line["Tables_in_remotelabz"])) == 1){ 
        //if ($tables == "") {
            $tables .= $line["Tables_in_remotelabz"] ." ";
        /*}
        else {
            $tables .= " ".$line["Tables_in_remotelabz"];
        }*/
    }
}

$result=exec('mysqldump '.$_SERVER['MYSQL_DATABASE'].' --password='.$_SERVER['MYSQL_PASSWORD'].' --user='.$_SERVER['MYSQL_USER'].' --host='.$_SERVER['MYSQL_SERVER'].' --no-tablespaces '.$tables.'>/opt/remotelabz/backups/'.$filename,$output);
if(empty($output)){echo $filename;}
else {return false;}
