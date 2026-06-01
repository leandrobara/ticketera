<?php

namespace App\Helpers;

use DateTimeZone;
use App\Models\Email;


class AutomationUserNotificationHelper
{

    const BODY_TEMPLATE_ROUTE = 'api.automations.automation_user_notification.index';


    public function getNotificationEmailBody(Email $openedEmail)
    {
        $timezone = $openedEmail->client->timezone;
        $sentDate = $openedEmail->sent_date->setTimezone(new DateTimeZone($timezone));
        $openedDate = $openedEmail->opened_date->setTimezone(new DateTimeZone($timezone));

        $leadContact = $openedEmail->leadContactEmail->leadContact;
        $leadContactPhone = $leadContact->leadContactPhones->first();

        $data = [
            'sentDate' => $sentDate,
            'openedDate' => $openedDate,
            'leadName' => $leadContact->name,
            'userName' => $openedEmail->user->name,
            'leadLastName' => $leadContact->last_name,
            'leadCompany' => $openedEmail->lead->company,
            'leadPhone' => $leadContactPhone?->phone ?? '',
            'leadEmail' => $openedEmail->leadContactEmail->email,
        ];
        $html = view(self::BODY_TEMPLATE_ROUTE, $data)->render();
        return $html;
    }


    public function getNotificationEmailSubject(Email $openedEmail)
    {
        $leadName = $openedEmail->lead->mainFullName;
        if (!$leadName) {
            $leadName = $openedEmail->leadContactEmail->email;
        }
        $subject = "El prospecto $leadName ha abierto el presupuesto enviado";
        return $subject;
    }

}
