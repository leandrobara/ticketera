<?php

namespace App\DTO;

use Illuminate\Support\Collection;


class EmailMassiveSendParametersDTO
{

    public $body = null;
    public $subject = null;
    public $attachments = [];
    public $isProposal = false;
    public $leadContactEmails = [];


    public static function build(array $requestArray)
    {
        $dto = new EmailMassiveSendParametersDTO();

        $dto->body = $requestArray['body'];
        $dto->subject = $requestArray['subject'];
        $dto->isProposal = $requestArray['isProposal'] ?? false;
        $dto->leadContactEmails = $requestArray['leadContactEmails'];
        $dto->attachments = $requestArray["attachments"] ?? collect([]);

        return $dto;
    }

}
