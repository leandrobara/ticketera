<?php

namespace App\Exceptions\Helpers\EventsLog;

use Throwable;
use Exception;


class GmailMessagesLogResponseException extends Exception
{
    
    public $debugInfo;


    public function __construct(string $message = "", int $code = 0, ?array $debugInfo = null)
    {
        parent::__construct($message, $code);
        $this->debugInfo = $debugInfo;
    }

}
