<?php

namespace App\Http\Requests\Notifications;

use DateTime;
use DateTimeZone;
use App\Models\User;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InEmailTemplateReturnFields;
use App\DTO\Notifications\Mailer\SentQuickEmailNotificationDTO;


class QuickEmailSentNotificationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'quick-email' => ['required', 'array'],
            'quick-email.id' => ['required', 'integer'],
            'quick-email.app_custom_id' => ['required', 'string'],
            'quick-email.sent_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'quick-email.app_custom_metadata' => ['present', 'json', 'nullable'],
        ];
    }


    public function getValidatedDTO(): SentQuickEmailNotificationDTO
    {
        $val = self::validated();

        $date = (new DateTime($val['quick-email']['sent_at']))->setTimezone(new DateTimeZone('UTC'));
        $val['quick-email']['sent_at'] = $date->format('Y-m-d\TH:i:sP');

        $metadata = $val['quick-email']['app_custom_metadata'];
        $metadata = $metadata ? json_decode($metadata, true) : null;
        $val['quick-email']['app_custom_metadata'] = $metadata;

        $dto = SentQuickEmailNotificationDTO::buildFromMailerEmailData($val['quick-email']);
        return $dto;
    }


    public function getRequestName(): string
    {
        $val = self::validated();
        return 'QuickEmailSentNotification.' . $val['quick-email']['id'];
    }

}
