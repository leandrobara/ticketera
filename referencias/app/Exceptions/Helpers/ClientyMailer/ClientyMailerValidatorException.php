<?php

namespace App\Exceptions\Helpers\ClientyMailer;

use Exception;

class ClientyMailerValidatorException extends Exception
{
    public function __construct($message = null, $code = null)
    {
        $message  = $message ?? 'Invalid Parameters';
        $code = $code ?? 400;
        parent::__construct($message, $code);
    }
}
