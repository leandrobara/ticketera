<?php

namespace App\Exceptions;

use Exception;

class DuplicatedException extends Exception
{
    public function __construct(?string $message = null, $code = 400)
    {
        parent::__construct($message, $code);
    }
}
