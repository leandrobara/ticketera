<?php

namespace App\Exceptions\Middleware;

use Exception;

class AuthenticationException extends Exception
{
    public function __construct($message = null, $code = 403)
    {
        $message = $message ?? 'user_not_authenticated';
        parent::__construct($message, $code);
    }
}
