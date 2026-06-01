<?php

namespace App\Exceptions\Helpers\ClientyMailer;

use Exception;

class ClientyMailerException extends Exception
{
    public function __construct($message = null, $code = null)
    {
        $message  = $message ?? 'An Unkown Error Ocurred Sending The Email';
        $code = $code ?? 400;
        parent::__construct($message, $code);
    }
}
