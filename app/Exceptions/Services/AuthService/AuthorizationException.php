<?php

namespace App\Exceptions\Services\AuthService;

use Exception;

class AuthorizationException extends Exception
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? 'invalid_user_or_password', 401);
    }
}
