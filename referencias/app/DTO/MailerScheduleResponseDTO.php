<?php

namespace App\DTO;


class MailerScheduleResponseDTO
{

    public $id = null;
    public $sendAt = null;
    public $sentAt = null;
    public $appCustomId = null;
    public $massiveSendingId = null;
    public $senderExternalId = null;
    public $openTrackingGuid = null;


    public static function buildFromResponseArray(array $mailerResponse)
    {
        $dto = new MailerScheduleResponseDTO();

        $dto->id = $mailerResponse["id"] ?? null;
        $dto->appCustomId = $mailerResponse["app_custom_id"] ?? null;
        $dto->massiveSendingId = $mailerResponse["massive_sending_id"] ?? null;
        $dto->senderExternalId = $mailerResponse["sender_external_id"] ?? null;
        $dto->openTrackingGuid = $mailerResponse["open_tracking_guid"] ?? null;
        $dto->sendAt = $mailerResponse["send_at"] ?? null;
        $dto->sentAt = $mailerResponse["sent_at"] ?? null;

        return $dto;
    }

}
