<?php

namespace App\Exceptions\Services\AuthService;

use Exception;

class AuthorizationException extends Exception
{
    public function __construct($message = null)
    {
        $message = $message ?? 'invalid_user_or_password';
        $code = $code ?? 401;
        parent::__construct($message, $code);
    }
}
