<?php

namespace App\Exceptions\JWT;

use Exception;

/**
 * @uses Exception
 * @author Juan Deladoey <juanpablojuanpablo@gmail.com>
 */
class InvalidJWTSignatureException extends Exception
{
    public function __construct()
    {
        $this->message = "Invalid Token";
        $this->code = 403;
    }
}
