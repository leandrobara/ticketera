<?php

namespace App\Exceptions\Services\LeadContactService;

use Exception;

class DeleteMainLeadContactException extends Exception
{
    public function __construct(?string $message = null, ?int $code = 0)
    {
        $message = $message ? $message : 'Last contact, lead contacts can not be empty';
        $code = $code != 0 ? $code : 400;

        parent::__construct($message, $code);
    }
}
