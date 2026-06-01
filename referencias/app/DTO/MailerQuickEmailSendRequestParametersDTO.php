<?php

namespace App\DTO;


class MailerQuickEmailSendRequestParametersDTO implements MailerRequestDTO
{

    public $to = null;
    public $cc = null;
    public $bcc = null;
    public $body = null;
    public $from = null;
    public $subject = null;
    public $fromName = null;
    public $appCustomId = null;
    public $hasOpenTracking = true;
    public $appCustomMetadata = null;


    public static function buildFromArray(array $data): MailerQuickEmailSendRequestParametersDTO
    {
        self::validate($data);

        $dto = new MailerQuickEmailSendRequestParametersDTO();
        $dto->to = $data['to'];
        $dto->body = $data['body'];
        $dto->from = $data['from'];
        $dto->cc = $data['cc'] ?? null;
        $dto->bcc = $data['bcc'] ?? null;
        $dto->subject = $data['subject'];
        $dto->fromName = $data['fromName'];
        $dto->appCustomId = $data['appCustomId'] ?? null;
        $dto->hasOpenTracking = $data['hasOpenTracking'] ?? true;
        $dto->appCustomMetadata = $data['appCustomMetadata'] ?? null;
        return $dto;
    }


    public static function validate(array $data): bool
    {
        if (
            !($data['to'] ?? null) ||
            !($data['body'] ?? null) ||
            !($data['from'] ?? null) ||
            !($data['subject'] ?? null) ||
            !($data['fromName'] ?? null)
        ) {
            throw new \Exception('MailerQuickEmailSendRequestParametersDTO missing fields');
        }
        return true;
    }


    public function toArray()
    {
        return [
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'body' => $this->body,
            'from' => $this->from,
            'subject' => $this->subject,
            'fromName' => $this->fromName,
            'appCustomId' => $this->appCustomId,
            'hasOpenTracking' => $this->hasOpenTracking,
            'appCustomMetadata' => $this->appCustomMetadata,
        ];
    }

}
