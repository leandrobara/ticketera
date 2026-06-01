<?php

namespace App\Http\Requests\Notifications;

use DateTime;
use DateTimeZone;
use App\Models\User;
use App\Http\Requests\APIBaseRequest;
use App\Rules\InEmailTemplateReturnFields;
use App\DTO\Notifications\Mailer\OpenedEmailNotificationDTO;


class EmailOpenedNotificationRequest extends APIBaseRequest
{

    public function rules()
    {
        return [
            'email.id' => ['required', 'integer'],
            'email.app_custom_id' => ['required', 'string'],
            'email.massive_sending_id' => ['sometimes', 'nullable'],
            'email.app_custom_massive_id' => ['sometimes', 'nullable'],
            'email.opened_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'discard_if_already_opened' => ['sometimes', 'boolean', 'nullable'],
        ];
    }


    public function getValidatedDTO(): OpenedEmailNotificationDTO
    {
        $val = self::validated();

        $date = (new DateTime($val['email']['opened_at']))->setTimezone(new DateTimeZone('UTC'));
        $val['email']['opened_at'] = $date->format('Y-m-d\TH:i:sP');

        $dto = OpenedEmailNotificationDTO::buildFromMailerEmailData($val['email']);
        return $dto;
    }


    public function getRequestName(): string
    {
        $val = self::validated();
        return 'EmailOpenedNotification.' . $val['email']['id'];
    }


    public function getOpts(): array
    {
        $opts = [];
        $val = self::validated();
        if ($val['discard_if_already_opened'] ?? false) {
            $opts['discardIfAlreadyOpened'] = true;
        }
        return $opts;
    }

}
