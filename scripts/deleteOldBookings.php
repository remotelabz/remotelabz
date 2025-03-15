#!/usr/bin/env php
<?php

use Symfony\Component\Dotenv\Dotenv;
require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// get old bookings
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "127.0.0.1/api/bookings/old");
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);
$bookings =  json_decode($response, true);
if (curl_errno($curl)) { 
    print curl_error($curl); 
    } 

curl_close($curl);
if (!$bookings == null) {
    foreach ($bookings as $booking) {
        //delete lab Instance of the lab if it exists
        if (isset($booking["lab_instance_uuid"])) {
            //stop the device instances before deleting the lab instance
            foreach ($booking["device_instances"] as $deviceInstance) {
                echo "device\n";
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, "127.0.0.1/api/instances/stop/by-uuid/".$deviceInstance['device_instance_uuid']);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
                curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
                curl_exec($curl);
                if (curl_errno($curl)) { 
                    print curl_error($curl); 
                } 
                
                curl_close($curl);
            }
        
            //delete the lab instance
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "127.0.0.1/api/instances/".$booking['lab_instance_uuid']);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
            curl_exec($curl);
            if (curl_errno($curl)) { 
                print curl_error($curl); 
            } 
            
            curl_close($curl);
            sleep(3);
        }
        //delete the booking
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "127.0.0.1/api/bookings/by_uuid/".$booking['booking_uuid']);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["cachecontrol: no-cache"]);
        curl_exec($curl);
        if (curl_errno($curl)) { 
            print curl_error($curl); 
        } 
        
        curl_close($curl);
        sleep(3);
    }
}