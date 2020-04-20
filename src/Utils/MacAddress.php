<?php

namespace App\Utils;

class MacAddress
{
    /**
     * Regular expression for matching and validating a MAC address
     * @var string
     */
    private static $validMacAddress = "([0-9A-F]{2}[:-]){5}([0-9A-F]{2})";

    /**
     * An array of valid MAC address characters
     * @var array
     */
    private static $macAddressValues = array(
        "0", "1", "2", "3", "4", "5", "6", "7",
        "8", "9", "A", "B", "C", "D", "E", "F"
    );

    /**
     * @param array $prefix If set, set the first digits manually.
     * @return string generated MAC address.
     */
    public static function generate(array $prefix = []): string
    {
        $vals = self::$macAddressValues;
        if (count($vals) >= 1) {
            $mac = count($prefix) > 0 ? $prefix : array("00"); // set first two digits manually
            while (count($mac) < 6) {
                shuffle($vals);
                $mac[] = $vals[0] . $vals[1];
            }
            $_mac = implode(":", $mac);
        }
        return $_mac;
    }

    /**
     * Make sure the provided MAC address is in the correct format
     * @param string $mac
     * @return bool true if valid; otherwise false
     */
    public static function validate($mac)
    {
        return (bool) preg_match("/^" . self::$validMacAddress . "$/i", $mac);
    }
}
