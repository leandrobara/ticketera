<?php

namespace App\Helpers;

use Exception;


class SimpleEncrypter
{

    public static function encryptString(string $val): string
    {
        $encrypted = '';
        $secret = config('app.simple_encrypter_key');
        $val = $val . $secret;
        $chars = str_split($val);
        foreach ($chars as $char) {
            //paso cada letra del string a su representacion en ascii a la cual le sumo 5
            $encrypted .= chr(ord($char) + 5);
        }
        $base64Encrypted = base64_encode($encrypted);
        $base64Encrypted = str_replace('=', 'FFlb7Jp', $base64Encrypted);
        $base64Encrypted = str_replace('/', 'Jplb7FF', $base64Encrypted);
        return $base64Encrypted;
    }
    

    public static function decryptString(string $val): string
    {
        $decrypted = '';
        $secret = config('app.simple_encrypter_key');
        $val = str_replace('FFlb7Jp', '=', $val);
        $val = str_replace('Jplb7FF', '/', $val);
        $chars = str_split(base64_decode($val));
        foreach ($chars as $char) {
            //paso cada letra del string a su representacion en ascii a la cual le resto 5
            $decrypted .= chr(ord($char) - 5);
        }
        $decrypted = str_replace($secret, '', $decrypted);
        return $decrypted;
    }


    public static function encryptInt(int $val): string
    {
        $base64Encrypted = self::encryptString((string) $val);
        return $base64Encrypted;
    }


    public static function decryptInt(string $encryptedInt): int
    {
        $decrypted = self::decryptString($encryptedInt);
        if (!is_numeric($decrypted)) {
            throw new Exception('simple_encrypter_invalid_parameter_encryption');
        }
        return (int) $decrypted;
    }

}