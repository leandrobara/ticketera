<?php

namespace App\DTO\Notifications\Mailer;


class OpenedEmailNotificationDTO
{

    public $id = null;
    public $openedAt = null;
    public $appCustomId = null;
    public $massiveSendingId = null;
    public $appCustomMassiveId = null;


    public static function buildFromMailerEmailData(array $emailData): OpenedEmailNotificationDTO
    {
        $dto = new OpenedEmailNotificationDTO();

        $dto->id = $emailData['id'] ?? null;
        $dto->openedAt = $emailData['opened_at'] ?? null;
        $dto->appCustomId = $emailData['app_custom_id'] ?? null;
        $dto->massiveSendingId = $emailData['massive_sending_id'] ?? null;
        $dto->appCustomMassiveId = $emailData['app_custom_massive_id'] ?? null;

        return $dto;
    }

}
