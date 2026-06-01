<?php

namespace App\DTO;


class EmailSendParametersDTO
{

    public $cc = null;
    public $to = null;
    public $body = null;
    public $subject = null;
    public $attachments = [];
    public $isProposal = null;
    public $leadContactEmails = null;
    public $individualLeadSendHash = null;


    public static function buildFromRequestArray(array $requestArray)
    {
        $dto = new EmailSendParametersDTO();

        $dto->cc = $requestArray["cc"] ?? null;
        $dto->body = $requestArray["body"] ?? null;
        $dto->subject = $requestArray["subject"] ?? null;
        $dto->isProposal = $requestArray["isProposal"] ?? false;
        $dto->attachments = $requestArray["attachments"] ?? collect([]);
        $dto->leadContactEmails = $requestArray["leadContactEmails"] ?? collect([]);
        $dto->individualLeadSendHash = $requestArray["individualLeadSendHash"] ?? null;

        return $dto;
    }
}
