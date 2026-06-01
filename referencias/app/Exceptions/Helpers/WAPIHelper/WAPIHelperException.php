<?php

namespace App\Exceptions\Helpers\WAPIHelper;

use Exception;


class WAPIHelperException extends Exception
{

    public function __construct($message = null, $code = null)
    {
        $message  = $message ?? 'An Unkown Error Occurred On Wapi';
        $code = $code ?? 400;
        parent::__construct($message, $code);
    }

}
