<?php

namespace App\DTO;

use App\Models\User;
use App\Models\Email;
use App\Models\LeadContactEmail;
use App\Helpers\EmailVariablesHelper;


class MailerMassiveScheduleRequestParametersDTO
{

    public $body = null;
    public $from = null;
    public $subject = null;
    public $sendDate = null;
    public $fromName = null;
    public $attachments = [];
    public $massiveData = [];
    public $emailSign = null;
    public $appCustomId = null;
    public $hasOpenTracking = true;
    public $hasTrackingInfo = false;
    public $unsubscribeText = null;
    public $appCustomMetadata = null;
    public $appCustomMassiveId = null;


    public static function build(
        User $user,
        EmailMassiveScheduleParametersDTO $scheduleParamsDTO
    ): MailerMassiveScheduleRequestParametersDTO {

        $dto = new MailerMassiveScheduleRequestParametersDTO();
        $body = $scheduleParamsDTO->body;
        $subject = $scheduleParamsDTO->subject;

        foreach ($scheduleParamsDTO->leadContactEmails as $leadContactEmail) {
            $variables = EmailVariablesHelper::getVariablesArray($body, $leadContactEmail, $user);
            $variables += EmailVariablesHelper::getVariablesArray($subject, $leadContactEmail, $user);

            $dto->massiveData[] = [
                'to' => $leadContactEmail->email,
                'variables' => $variables ? json_encode($variables) : null,
                'appCustomId' => $leadContactEmail->buildExternalCustomId(),
                'appCustomMetadata' => $leadContactEmail->buildExternalCustomMetadata(),
            ];
        }

        $dto->body = $body;
        $dto->subject = $subject;
        $dto->hasOpenTracking = true;
        $dto->from = $user->email_from_address;
        $dto->fromName = $user->email_from_name;
        $dto->sendDate = $scheduleParamsDTO->sendDate;
        $dto->attachments = $scheduleParamsDTO->attachments;
        $dto->appCustomMassiveId = Email::buildMassiveSendingId();

        if ($user->email_sign_enabled && $user->email_sign) {
            $dto->emailSign = $user->email_sign;
        }
        $dto->hasTrackingInfo = $user->client->clientSettings->enable_google_gmail_api;
        $dto->unsubscribeText = $user->client->clientSettings->massive_email_unsubscribe_text;

        return $dto;
    }


    public function toArray()
    {
        return [
            'body' => $this->body,
            'from' => $this->from,
            'subject' => $this->subject,
            'fromName' => $this->fromName,
            'sendDate' => $this->sendDate,
            'attachments' => $this->attachments,
            'massiveData' => $this->massiveData,
            'unsubscribeText' => $this->unsubscribeText,
            'hasOpenTracking' => $this->hasOpenTracking,
            'hasTrackingInfo' => $this->hasTrackingInfo,
            'appCustomMassiveId' => $this->appCustomMassiveId,
        ];
    }
}
