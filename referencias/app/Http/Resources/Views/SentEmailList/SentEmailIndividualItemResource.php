<?php

namespace App\Http\Resources\Views\SentEmailList;

use Illuminate\Http\Resources\Json\JsonResource;


class SentEmailIndividualItemResource extends JsonResource
{

    public function toArray($request)
    {
        $arr = [
            'id' => $this->resource->id,
            'send_date' => $this->resource->send_date,
            'sent_date' => $this->resource->sent_date,
            'opened_date' => $this->resource->opened_date,
            'bounced_date' => $this->resource->bounced_date,
            'email' => $this->resource->leadContactEmail->email,
            'complained_date' => $this->resource->complained_date,
            'unsubscribed_date' => $this->resource->unsubscribed_date,
            'lead_contact_email_id' => $this->resource->lead_contact_email_id,
        ];
        return $arr;
    }

}
