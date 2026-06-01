<?php

namespace App\Http\Requests\Notifications;

use DateTime;
use DateTimeZone;
use App\Models\User;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InEmailTemplateReturnFields;
use App\DTO\Notifications\Mailer\SentEmailNotificationDTO;


class EmailSentNotificationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'email.id' => ['required', 'integer'],
            'email.app_custom_id' => ['required', 'string'],
            'email.massive_sending_id' => ['sometimes', 'nullable'],
            'email.app_custom_massive_id' => ['sometimes', 'nullable'],
            'email.sent_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }


    public function getValidatedDTO(): SentEmailNotificationDTO
    {
        $val = self::validated();

        $date = (new DateTime($val['email']['sent_at']))->setTimezone(new DateTimeZone('UTC'));
        $val['email']['sent_at'] = $date->format('Y-m-d\TH:i:sP');

        $dto = SentEmailNotificationDTO::buildFromMailerEmailData($val['email']);
        return $dto;
    }


    public function getRequestName(): string
    {
        $val = self::validated();
        return 'EmailSentNotification.' . $val['email']['id'];
    }

}
