<?php

namespace App\Service;

class JitsiJWTCreator {

    private $jwtSecret;
    private $urlJitsi;

    public function __construct()
    {
        $this->jwtSecret = (string)getenv('JITSI_CALL_SECRET');
        $this->urlJitsi = (string)getenv('JITSI_CALL_URL');
    }

    public function getToken(string $name, string $email, string $groupName, string $labName)
    {
        $groupNameFiltered = $this->filterName($groupName);
        $labNameFiltered = $this->filterName($labName);
        $roomName = $groupNameFiltered . $labNameFiltered;

        $header = json_encode(["alg" => "HS256", "typ" => "JWT"]);

        $payload = json_encode([
            "context" => [
                "user" => [
                    "name" => $name,
                    "email" => $email
                ]
            ],
            "room" => $roomName,
            "exp" => time() + 30,
            "aud" => "rl-jitsi-call",
            "iss" => "remotelabz"
        ]);

        $url = $this->urlJitsi . "/" . $roomName;

        $encodedHeader = str_replace(['+', '/', '='], ['-',  '_', ''], base64_encode($header));
        $encodedPayload = str_replace(['+', '/', '='], ['-',  '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $encodedHeader . "." . $encodedPayload, $this->jwtSecret, true);
        $encodedSignature = str_replace(['+', '/', '='], ['-',  '_', ''], base64_encode($signature));

        $token = $encodedHeader . "." . $encodedPayload . "." . $encodedSignature;

        $url .= "?jwt=" . $token;

        return $url;
    }

    private function filterName(string $name)
    {
        $filteredName = str_replace(' ', '', $name);
        
        return $filteredName;
    }
}