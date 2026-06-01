<?php

namespace App\Http\Resources\Views\SentEmailModal;

use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\Views\SentEmailList\SentEmailIndividualItemResource;


class SentEmailModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $arr = [
            'mailerInfo' => null,
            'id' => $this->resource['id'],
            'user' => $this->loadUserResource(),
            'user_id' => $this->resource['user_id'],
            'client_id' => $this->resource['client_id'],
            'send_date' => $this->resource['send_date'],
            'sent_date' => $this->resource['sent_date'],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
            'deleted_at' => $this->resource['deleted_at'],
            'external_id' => $this->resource['external_id'],
            'is_proposal' => $this->resource['is_proposal'],
            'attachments' => $this->resource['attachments'],
            'external_custom_id' => $this->resource['external_custom_id'],
            'external_massive_id' => $this->resource['external_massive_id'],
            'external_custom_massive_id' => $this->resource['external_custom_massive_id'],
            'individualEmails' => $this->loadIndividualEmails(),
        ];

        $dto = $this->resource->getMailerDTO();
        $arr['mailerInfo'] = [
            'id' => $dto->get('id'),
            'body' => $dto->get('body'),
            'from' => $dto->get('from'),
            'subject' => $dto->get('subject'),
            'fromName' => $dto->get('fromName'),
            'opened_date' => $dto->get('openedDate'),
            'attachments' => $dto->get('attachments'),
            'bounced_date' => $dto->get('bouncedDate'),
            'complained_date' => $dto->get('complainedDate'),
            'unsubscribed_date' => $dto->get('unsubscribedDate'),
        ];
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
