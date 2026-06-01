<?php

namespace App\Http\Resources\Views\GmailEmailNotification;

use App\Http\Resources\LeadResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class GmailEmailNotificationItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'type' => $this->type,
            'user_id' => $this->user_id,
            'gmail_id' => $this->gmail_id,
            'email_subject' => $this->email_subject,
            'email_name_from' => $this->email_name_from,
            'email_sent_date' => $this->email_sent_date,
            'email_address_from' => $this->email_address_from,
            'is_notification_viewed' => $this->is_notification_viewed,
        ];
        return $response;
    }

}
