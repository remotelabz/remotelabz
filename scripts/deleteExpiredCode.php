#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// get expired codes with lab and devices instances
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $_SERVER['PUBLIC_ADDRESS']."/api/expiredToken/instances");
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);
$codes =  json_decode($response, true);
if (curl_errno($curl)) { 
    print curl_error($curl); 
    } 

curl_close($curl);

if ($codes !== null) {
    echo "codes\n";
    foreach($codes as $code) {
        foreach($code['device_instances'] as $deviceInstance) {
            //stop device instances linked to the user
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $_SERVER['PUBLIC_ADDRESS']."/api/instances/stop/by-uuid/".$deviceInstance['device_instance_uuid']);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
            curl_exec($curl);
            if (curl_errno($curl)) { 
                print curl_error($curl); 
            } 
            
            curl_close($curl);
        }
    
        //delete lab instance linked to the user
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $_SERVER['PUBLIC_ADDRESS']."/api/instances/".$code['lab_instance_uuid']);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
        curl_exec($curl);
        if (curl_errno($curl)) { 
            print curl_error($curl); 
        } 
        
        curl_close($curl);
        sleep(3);
        //delete user
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $_SERVER['PUBLIC_ADDRESS']."/api/codes/".$code['guest_uuid']);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
        curl_exec($curl);
        if (curl_errno($curl)) { 
            print curl_error($curl); 
        } 
        
        curl_close($curl);
    
    }
}

// get expired codes without instance
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $_SERVER['PUBLIC_ADDRESS']."/api/expiredToken");
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);
$codesWithoutInstance =  json_decode($response, true);

if (curl_errno($curl)) { 
    print curl_error($curl); 
    } 

curl_close($curl);

if ($codesWithoutInstance !== null) {
    echo "codesWithoutInstance\n";
    foreach($codesWithoutInstance as $code) {
        //delete user
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $_SERVER['PUBLIC_ADDRESS']."/api/codes/".$code['guest_uuid']);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
        curl_exec($curl);
        if (curl_errno($curl)) { 
            print curl_error($curl); 
        } 

        curl_close($curl);
    }
    
}
?>