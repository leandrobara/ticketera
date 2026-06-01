<?php

namespace App\DTO;


class EmailScheduleParametersDTO
{

    public $cc = null;
    public $body = null;
    public $subject = null;
    public $sendDate = null;
    public $attachments = [];
    public $isProposal = null;
    public $appCustomId = null;
    public $automationLog = null;
    public $leadContactEmails = null;
    public $individualLeadSendHash = null;


    public static function buildFromRequestArray(array $requestArray)
    {
        $dto = new EmailScheduleParametersDTO();

        $dto->cc = $requestArray["cc"] ?? null;
        $dto->body = $requestArray["body"] ?? null;
        $dto->subject = $requestArray["subject"] ?? null;
        $dto->sendDate = $requestArray["sendDate"] ?? null;
        $dto->isProposal = $requestArray["isProposal"] ?? false;
        $dto->attachments = $requestArray["attachments"] ?? collect([]);
        $dto->leadContactEmails = $requestArray["leadContactEmails"] ?? collect([]);
        $dto->individualLeadSendHash = $requestArray["individualLeadSendHash"] ?? null;

        return $dto;
    }

}
