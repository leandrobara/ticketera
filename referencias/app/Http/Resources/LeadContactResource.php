<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class LeadContactResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {

        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('leadContactEmails', $visibleFields)) {
            $response = $this->loadLeadContactEmailsField($response);
        }
        if (in_array('leadContactPhones', $visibleFields)) {
            $response = $this->loadLeadContactPhonesField($response);
        }
        if (in_array('lead', $visibleFields)) {
            $response = $this->loadLeadField($response);
        }
        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }

        $response = $this->filterVisibleFields($response);

        return $response;
    }

    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }

        $visibleFields = ['id', 'name', 'subdomain','country_code', 'version'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;

        return $response;
    }

    private function loadLeadField(array $response): array
    {
        if (!$this->resource->relationLoaded('lead')) {
            $this->resource->load('lead');
        }
        $leadRs = new LeadResource($this->resource->lead);
        $response['lead'] =  $leadRs;

        return $response;
    }

    private function loadLeadContactPhonesField(array $response): array
    {
        if (!$this->resource->relationLoaded('leadContactPhones')) {
            $this->resource->load('leadContactPhones');
        }

        $visibleFields = ['id', 'phone', 'order', 'lead_contact_id', 'lead_ids_where_repeated'];
        $leadContactPhonesRs = new LeadContactPhoneResourceCollection($this->resource->leadContactPhones);
        $leadContactPhonesRs->setVisibleFields($visibleFields);

        $response['leadContactPhones'] = $leadContactPhonesRs;

        return $response;
    }


    private function loadLeadContactEmailsField(array $response): array
    {
        if (!$this->resource->relationLoaded('leadContactEmails')) {
            $this->resource->load('leadContactEmails');
        }

        $visibleFields = [
            'id',
            'email',
            'order',
            'lead_id',
            'bounced',
            'is_valid',
            'complained',
            'validations',
            'unsubscribed',
            'lead_contact_id',
            'lead_ids_where_repeated'
        ];
        $leadContactEmailsRs = new LeadContactEmailResourceCollection($this->resource->leadContactEmails);
        $leadContactEmailsRs->setVisibleFields($visibleFields);

        $response['leadContactEmails'] = $leadContactEmailsRs;

        return $response;
    }
}
