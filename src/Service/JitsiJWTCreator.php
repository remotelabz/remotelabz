<?php

namespace App\Service;

class JitsiJWTCreator {
    
    private $name;
    private $email;
    private $groupName;
    private $labName;
    private $jwtSecret;
    private $urlJitsi;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->groupName = $groupName;
        $this->labName = $labName;
        $this->jwtSecret = (string)getenv('JITSI_CALL_SECRET');
        $this->urlJitsi = (string)getenv('JITSI_CALL_URL');
    }

    public function getToken()
    {
        $roomName = filterRoomName($this->groupName . "-" . $this->labName);
        $header = json_encode(["alg" => "HS256", "typ" => "JWT"]);

        $payload = json_encode([
            "context" => [
                "user" => [
                    "name" => $this->name,
                    "email" => $this->email
                ]
            ],
            "room" => $roomName,
            "exp" => time() + 60,
            "aud" => "rl-jitsi-call",
            "iss" => "remotelabz"
        ]);

        $url = $this->urlJitsi . "/" . $this->groupName . "-" . $this->labName;

        $encodedHeader = str_replace(['+', '/', '='], ['-',  '_', ''], base64_encode($header));
        $encodedPayload = str_replace(['+', '/', '='], ['-',  '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $encodedHeader . "." . $encodedPayload, $this->jwtSecret, true);
        $encodedSignature = str_replace(['+', '/', '='], ['-',  '_', ''], base64_encode($signature));

        $token = $encodedHeader . "." . $encodedPayload . "." . $encodedSignature;

        $url .= "?jwt=" . $token;
    }

    private function filterRoomName(string $name)
    {
        $charset = iconv_get_encoding();
        $convertedName = iconv($charset, 'ASCII//TRANSLIT', $name);
    }
}