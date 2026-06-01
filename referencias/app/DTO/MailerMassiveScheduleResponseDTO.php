<?php

namespace App\DTO;


class MailerMassiveScheduleResponseDTO
{

    public $emails = null;
    public $massiveSendingId = null;
    public $appCustomMassiveId = null;


    public static function buildFromResponseArray(array $mailerResponse)
    {
        $dto = new MailerMassiveScheduleResponseDTO();
        $dto->emails = $mailerResponse["emails"] ?? [];
        $dto->massiveSendingId = $mailerResponse["massive_sending_id"] ?? null;
        $dto->appCustomMassiveId = $mailerResponse["app_custom_massive_id"] ?? null;
        return $dto;
    }

}
