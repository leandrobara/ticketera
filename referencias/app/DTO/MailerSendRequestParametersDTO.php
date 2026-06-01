<?php

namespace App\DTO;

use App\Models\User;
use App\Models\LeadContactEmail;
use App\Helpers\EmailVariablesHelper;


class MailerSendRequestParametersDTO implements MailerRequestDTO
{

    public $to = null;
    public $cc = null;
    public $body = null;
    public $from = null;
    public $subject = null;
    public $fromName = null;
    public $variables = null;
    public $emailSign = null;
    public $attachments = [];
    public $appCustomId = null;
    public $unsubscribeText = null;
    public $hasOpenTracking = true;
    public $hasTrackingInfo = false;
    public $appCustomMetadata = null;


    public static function build(
        User $user,
        LeadContactEmail $leadContactEmail,
        EmailSendParametersDTO $sendParametersDTO
    ): MailerSendRequestParametersDTO {

        $dto = new MailerSendRequestParametersDTO();

        $dto->hasOpenTracking = true;
        $dto->cc = $sendParametersDTO->cc;
        $dto->to = $leadContactEmail->email;
        $dto->body = $sendParametersDTO->body;
        $dto->from = $user->email_from_address;
        $dto->fromName = $user->email_from_name;
        $dto->subject = $sendParametersDTO->subject;
        $dto->appCustomId = $leadContactEmail->buildExternalCustomId();
        $dto->appCustomMetadata = $leadContactEmail->buildExternalCustomMetadata();

        if ($user->email_sign_enabled && $user->email_sign) {
            $dto->emailSign = $user->email_sign;
        }
        
        $attachments = collect($sendParametersDTO->attachments);
        $attachments = $attachments->map(function ($a) {
            return ['name' => $a->name, 'hash' => $a->hash];
        });
        $dto->attachments = $attachments;

        $variables = EmailVariablesHelper::getVariablesArray($dto->body, $leadContactEmail, $user);
        $variables += EmailVariablesHelper::getVariablesArray($dto->subject, $leadContactEmail, $user);
        $dto->variables = $variables ?: null;

        $dto->hasTrackingInfo = $user->client->clientSettings->enable_google_gmail_api;
        $dto->unsubscribeText = $user->client->clientSettings->massive_email_unsubscribe_text;

        return $dto;
    }


    public function toArray()
    {
        return [
            'to' => $this->to,
            'cc' => $this->cc,
            'body' => $this->body,
            'from' => $this->from,
            'subject' => $this->subject,
            'fromName' => $this->fromName,
            'appCustomId' => $this->appCustomId,
            'attachments' => $this->attachments,
            'hasOpenTracking' => $this->hasOpenTracking,
            'hasTrackingInfo' => $this->hasTrackingInfo,
            'unsubscribeText' => $this->unsubscribeText,
            'appCustomMetadata' => $this->appCustomMetadata,
            'variables' => $this->variables ? json_encode($this->variables) : null,
        ];
    }

}
