#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$filename = 'database_backup_'.date('d_m_Y_h_i_s').'.sql';
$result=exec('mysqldump '.$_SERVER['MYSQL_DATABASE'].' --password='.$_SERVER['MYSQL_PASSWORD'].' --user='.$_SERVER['MYSQL_USER'].' --host='.$_SERVER['MYSQL_SERVER'].' --no-tablespaces >/opt/remotelabz/backups/'.$filename,$output);
if(empty($output)){echo $filename;}
else {return false;}
