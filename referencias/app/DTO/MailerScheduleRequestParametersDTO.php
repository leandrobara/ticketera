<?php

namespace App\DTO;

use App\Models\User;
use App\Models\LeadContactEmail;
use App\Helpers\EmailVariablesHelper;


class MailerScheduleRequestParametersDTO implements MailerRequestDTO
{

    public $to = null;
    public $cc = null;
    public $body = null;
    public $from = null;
    public $subject = null;
    public $sendDate = null;
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
        EmailScheduleParametersDTO $scheduleParametersDTO
    ): MailerScheduleRequestParametersDTO {

        $dto = new MailerScheduleRequestParametersDTO();

        if ($scheduleParametersDTO->appCustomId) {
            $appCustomId = $scheduleParametersDTO->appCustomId;
        } else {
            $appCustomId = $leadContactEmail->buildExternalCustomId();
        }

        $dto->hasOpenTracking = true;
        $dto->appCustomId = $appCustomId;
        $dto->to = $leadContactEmail->email;
        $dto->cc = $scheduleParametersDTO->cc;
        $dto->from = $user->email_from_address;
        $dto->fromName = $user->email_from_name;
        $dto->body = $scheduleParametersDTO->body;
        $dto->subject = $scheduleParametersDTO->subject;
        $dto->sendDate = $scheduleParametersDTO->sendDate;
        $dto->appCustomMetadata = $leadContactEmail->buildExternalCustomMetadata();

        $dto->hasTrackingInfo = $user->client->clientSettings->enable_google_gmail_api;
        $dto->unsubscribeText = $user->client->clientSettings->massive_email_unsubscribe_text;

        if ($user->email_sign_enabled && $user->email_sign) {
            $dto->emailSign = $user->email_sign;
        }

        $variables = EmailVariablesHelper::getVariablesArray($dto->body, $leadContactEmail, $user);
        $variables += EmailVariablesHelper::getVariablesArray($dto->subject, $leadContactEmail, $user);
        $dto->variables = $variables ?: null;

        $attachments = collect($scheduleParametersDTO->attachments);
        $attachments = $attachments->map(function ($a) {
            return ['name' => $a->name, 'hash' => $a->hash];
        });
        $dto->attachments = $attachments;

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
            'sendDate' => $this->sendDate,
            'attachments' => $this->attachments,
            'appCustomId' => $this->appCustomId,
            'hasOpenTracking' => $this->hasOpenTracking,
            'hasTrackingInfo' => $this->hasTrackingInfo,
            'unsubscribeText' => $this->unsubscribeText,
            'appCustomMetadata' => $this->appCustomMetadata,
            'variables' => $this->variables ? json_encode($this->variables) : null,
        ];
    }

}
