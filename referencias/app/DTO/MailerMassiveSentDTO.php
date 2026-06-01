<?php

namespace App\DTO;

class MailerMassiveSentDTO
{
    public $massiveSendingId = null;
    public $subject = null;
    public $sent = null;
    public $opened = null;
    public $bounced = null;
    public $complained = null;
    public $unsubscribed = null;

    public static function buildFromSentInfo($sentInfo)
    {
        $dto = new MailerMassiveSentDTO();
        $dto->massiveSendingId = $sentInfo['massive_sending_id'];
        $dto->subject = $sentInfo['subject'];
        $dto->sent = $sentInfo['sent'];
        $dto->opened = $sentInfo['opened'];
        $dto->bounced = $sentInfo['bounced'];
        $dto->complained = $sentInfo['complained'];
        $dto->unsubscribed = $sentInfo['unsubscribed'];

        return $dto;
    }

    public function toArray()
    {
        return [
            'massiveSendingId' => $this->massiveSendingId,
            'subject' => $this->subject,
            'sent' => $this->sent,
            'opened' => $this->opened,
            'bounced' => $this->bounced,
            'complained' => $this->complained,
            'unsubscribed' => $this->unsubscribed
        ];
    }
}
