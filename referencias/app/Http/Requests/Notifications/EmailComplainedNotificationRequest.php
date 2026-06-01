<?php

namespace App\Http\Requests\Notifications;

use DateTime;
use DateTimeZone;
use App\Models\User;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InEmailTemplateReturnFields;
use App\DTO\Notifications\Mailer\ComplainedEmailNotificationDTO;


class EmailComplainedNotificationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'email.id' => ['required', 'integer'],
            'email.app_custom_id' => ['required', 'string'],
            'email.massive_sending_id' => ['sometimes', 'nullable', 'string'],
            'email.app_custom_massive_id' => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function getValidatedDTO(): ComplainedEmailNotificationDTO
    {
        $val = self::validated();
        $dto = ComplainedEmailNotificationDTO::buildFromMailerEmailData($val['email']);
        return $dto;
    }


    public function getRequestName(): string
    {
        $val = self::validated();
        return 'EmailComplainedNotification.' . $val['email']['id'];
    }

}
