<?php

namespace App\DTO\Notifications\Mailer;


class ComplainedEmailNotificationDTO
{

    public $id = null;
    public $appCustomId = null;
    public $massiveSendingId = null;
    public $appCustomMassiveId = null;


    public static function buildFromMailerEmailData(array $emailData): ComplainedEmailNotificationDTO
    {
        $dto = new ComplainedEmailNotificationDTO();

        $dto->id = $emailData['id'] ?? null;
        $dto->appCustomId = $emailData['app_custom_id'] ?? null;
        $dto->massiveSendingId = $emailData['massive_sending_id'] ?? null;
        $dto->appCustomMassiveId = $emailData['app_custom_massive_id'] ?? null;
        return $dto;
    }

}
