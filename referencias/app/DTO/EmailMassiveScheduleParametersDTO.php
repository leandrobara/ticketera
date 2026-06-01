<?php

namespace App\DTO;

use DateTime;
use Illuminate\Support\Collection;


class EmailMassiveScheduleParametersDTO
{

    public $body = null;
    public $subject = null;
    public $sendDate = null;
    public $attachments = [];
    public $isProposal = false;
    public $leadContactEmails = [];


    public static function build(array $requestArray)
    {
        $dto = new EmailMassiveScheduleParametersDTO();

        $dto->body = $requestArray['body'];
        $dto->subject = $requestArray['subject'];
        $dto->sendDate = $requestArray['sendDate'];
        $dto->isProposal = $requestArray['isProposal'] ?? false;
        $dto->leadContactEmails = $requestArray['leadContactEmails'];
        $dto->attachments = $requestArray['attachments'] ?? collect([]);

        return $dto;
    }


    public static function buildFromSendDTO(EmailMassiveSendParametersDTO $sendParamsDTO)
    {
        $dto = new EmailMassiveScheduleParametersDTO();

        $dto->body = $sendParamsDTO->body;
        $dto->subject = $sendParamsDTO->subject;
        $dto->isProposal = $sendParamsDTO->isProposal;
        $dto->sendDate = (new DateTime())->format('Y-m-d\TH:i:sP');
        $dto->leadContactEmails = $sendParamsDTO->leadContactEmails;

        $attachments = collect($sendParamsDTO->attachments);
        $attachments = $attachments->map(function ($a) {
            return ['name' => $a->name, 'hash' => $a->hash];
        });
        $dto->attachments = $attachments;

        return $dto;
    }

}
