<?php

namespace App\DTO\Notifications\Mailer;


class SentEmailNotificationDTO
{

    public $id = null;
    public $sentAt = null;
    public $appCustomId = null;
    public $massiveSendingId = null;
    public $appCustomMassiveId = null;


    public static function buildFromMailerEmailData(array $emailData): SentEmailNotificationDTO
    {
        $dto = new SentEmailNotificationDTO();

        $dto->id = $emailData['id'] ?? null;
        $dto->sentAt = $emailData['sent_at'] ?? null;
        $dto->appCustomId = $emailData['app_custom_id'] ?? null;
        $dto->massiveSendingId = $emailData['massive_sending_id'] ?? null;
        $dto->appCustomMassiveId = $emailData['app_custom_massive_id'] ?? null;

        return $dto;
    }

}
