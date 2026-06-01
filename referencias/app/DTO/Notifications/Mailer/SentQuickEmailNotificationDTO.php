<?php

namespace App\DTO\Notifications\Mailer;


class SentQuickEmailNotificationDTO
{

    public $id = null;
    public $sentAt = null;
    public $appCustomId = null;
    public $appCustomMetadata = null;
    public $appCustomMassiveId = null;


    public static function buildFromMailerEmailData(array $quickEmailData): SentQuickEmailNotificationDTO
    {
        $dto = new SentQuickEmailNotificationDTO();

        $dto->id = $quickEmailData['id'] ?? null;
        $dto->sentAt = $quickEmailData['sent_at'] ?? null;
        $dto->appCustomId = $quickEmailData['app_custom_id'] ?? null;
        $dto->massiveSendingId = $quickEmailData['massive_sending_id'] ?? null;
        $dto->appCustomMetadata = $quickEmailData['app_custom_metadata'] ?? null;

        return $dto;
    }

}
