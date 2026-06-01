<?php

namespace App\Http\Resources\Views\SentEmailList;

use Illuminate\Support\Collection;
use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class SentEmailListItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // $lead = $this->resource->lead()->withTrashed()->first();
        $lead = $this->resource->lead;
        $lead->leadContactEmail = $this->resource->leadContactEmail;
        $lead->leadContact = $lead->leadContactEmail->leadContact;
        // $lead->status = $lead->status()->withTrashed()->first();
        // $lead->acquisitionChannel = $lead->acquisitionChannel()->withTrashed()->first();

        $arr = [
            'lead' => $lead,
            'id' => $this->resource['id'],
            'is_proposal' => $this->resource['is_proposal'],
            'user_id' => $this->resource['user_id'],
            'client_id' => $this->resource['client_id'],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
            'deleted_at' => $this->resource['deleted_at'],
            'external_id' => $this->resource['external_id'],
            'cancelled_date' => $this->resource['cancelled_date'],
            'external_custom_id' => $this->resource['external_custom_id'],
            'external_massive_id' => $this->resource['external_massive_id'],
            'external_custom_massive_id' => $this->resource['external_custom_massive_id'],
            'mailerInfo' => null,
            'user' => $this->loadUserResource(),
            // 'individualEmails' => $this->loadIndividualEmails(),

            'send_date' => $this->resource->send_date,
            'sent_date' => $this->resource->sent_date,
            'opened_date' => $this->resource->opened_date,
            'bounced_date' => $this->resource->bounced_date,
            'email' => $this->resource->leadContactEmail->email,
            'complained_date' => $this->resource->complained_date,
            'unsubscribed_date' => $this->resource->unsubscribed_date,
            'lead_contact_email_id' => $this->resource->lead_contact_email_id,
        ];

        $dto = $this->resource->getMailerDTO();
        if ($dto) {
            $arr['mailerInfo'] = [
                'id' => $dto->get('id'),
                'subject' => $dto->get('subject'),
                'opened_date' => $dto->get('openedDate'),
                'bounced_date' => $dto->get('bouncedDate'),
                'complained_date' => $dto->get('complainedDate'),
                'unsubscribed_date' => $dto->get('unsubscribedDate'),
            ];
        }

        return $arr;
    }

    private function loadIndividualEmails()
    {
        if (!$this->resource->relationLoaded('leadSendIndividualEmails')) {
            $this->resource->load('leadSendIndividualEmails');
        }

        return  SentEmailIndividualItemResource::collection($this->resource->leadSendIndividualEmails);
    }


    private function loadUserResource(): UserResource
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = ['id', 'type', 'name', 'last_name', 'email'];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);
        return $userRs;
    }

}
