<?php

namespace App\Helpers;

use App\Exceptions\JWT\ExpiredJWTException;
use App\Exceptions\JWT\InvalidJWTSecretException;
use App\Exceptions\JWT\InvalidJWTAlgorithmException;
use App\Exceptions\JWT\InvalidJWTFormatException;
use App\Exceptions\JWT\InvalidJWTSignatureException;

class JwtHelper
{
    /**
     * Encodes JWT tokens
     * @param $data
     * @param $secret
     * @param string $algo
     * @param array $header
     *
     * @return null|string
     *
     * @throws InvalidJWTAlgorithmException
     */
    public static function encode(
        array $data,
        string $secret,
        string $algo = 'sha512',
        array $header = []
    ): ?string {
        $alg = self::convertAlgoToJwtFormat($algo);
        if ($alg === null) {
            throw new InvalidJWTAlgorithmException();
        }
        //JWT Parts
        $segments = [];
        //Create the header
        $fullHeader = json_encode(array_merge(
            ['alg' => $alg, 'typ' => 'JWT'],
            $header
        ));
        $segments[] = self::base64urlEncode($fullHeader);
        //Encode the data
        $data = json_encode($data);

        $segments[] = self::base64urlEncode($data);
        //Create the signature
        $segments[] = self::createSignature($segments, $secret, $algo);

        return implode('.', $segments);
    }


    /**
     * Verifies a JWT Token
     *
     * @param $token
     * @param $secret
     * @param string $algo
     * @param null $use
     *
     * @return void
     *
     * @throws InvalidJWTAlgorithmException
     * @throws InvalidJWTSecretException
     * @throws InvalidJWTFormatException
     * @throws ExpiredJWTException
     * @throws InvalidJWTSignatureException
     */
    public static function verify($token, $secret, $algo = 'sha512'): void
    {
        $alg = self::convertAlgoToJwtFormat($algo);
        if ($alg === null) {
            throw new InvalidJWTAlgorithmException();
        }
        if (empty($secret)) {
            throw new InvalidJWTSecretException();
        }
        $segments = explode('.', self::cleanToken($token));
        if (count($segments) !== 3) {
            throw new InvalidJWTFormatException();
        }
        //Get the header
        $header = json_decode(self::base64urlDecode($segments[0]), true);
        //Validate the header
        if (
            !isset($header['alg']) ||
            !isset($header['typ']) ||
            $header['alg'] !== $alg ||
            $header['typ'] !== 'JWT'
        ) {
            throw new InvalidJWTFormatException();
        }
        //Re-encode the header
        $header = self::base64urlEncode(json_encode($header));
        //Get the payload
        $payload = $segments[1];
        $data = json_decode(self::base64urlDecode($payload), true);
        $exp = $data['exp'] ?? 0;
        if ($exp < time()) {
            throw new ExpiredJWTException();
        }
        //Get the signature
        $signature = $segments[2];
        //Create the segments
        $segments = [];
        $segments[] = $header;
        $segments[] = $payload;
        $hash = self::createSignature($segments, $secret, $algo);
        // compare
        if (function_exists('hash_equals')) {
            $status =  hash_equals($signature, $hash);
        } else {
            $len = min(self::safeStrlen($signature), self::safeStrlen($hash));
            $status = 0;
            for ($i = 0; $i < $len; $i++) {
                $status |= (\ord($signature[$i]) ^ \ord($hash[$i]));
            }
            $status |= (self::safeStrlen($signature) ^ self::safeStrlen($hash));
            $status = ($status == 0);
        }

        if (!$status) {
            throw new InvalidJWTSignatureException();
        }
    }

    /**
     * Get the number of bytes in cryptographic strings.
     *
     * @param string $str
     *
     * @return int
     */
    private static function safeStrlen($str)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, '8bit');
        }

        return strlen($str);
    }

    private static function createSignature($segments, $secret, $algo)
    {
        $combined = implode('.', $segments);
        $signature = hash_hmac(
            strtoupper($algo),
            $combined,
            $secret,
            true
        );
        return self::base64urlEncode($signature);
    }


    public static function getPayloadData($token)
    {
        $segments = explode('.', self::cleanToken($token));
        $payload = $segments[1];
        $payload = self::base64urlDecode($payload);
        $payload = json_decode($payload, true);

        return $payload;
    }


    /**
     * cleanToken
     *
     * @param string $token
     *
     * @return string
     */
    private static function cleanToken(string $token): string
    {
        return trim(str_replace('Bearer', '', $token));
    }


    /**
     * @param string $data
     *
     * @return string
     */
    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }


    /**
     * @param string $data
     *
     * @return string
     */
    private static function base64urlDecode(string $data): string
    {
        return base64_decode(
            str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)
        );
    }

    /**
     * @param string $algo
     *
     * @return string
     */
    private static function convertAlgoToJwtFormat(string $algo)
    {
        //Create the alg string for the header
        $jwtFmt = null;
        switch (strtoupper($algo)) {
            case 'SHA256':
                $jwtFmt = 'HS256';
                break;
            case 'SHA512':
                $jwtFmt = 'HS512';
                break;
        }
        return $jwtFmt;
    }
}
