<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class LeadContactPhoneResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('lead', $visibleFields)) {
            $response = $this->loadLeadField($response);
        }
        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('leadContact', $visibleFields)) {
            $response = $this->loadleadContactField($response);
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

    private function loadleadContactField(array $response): array
    {
        if (!$this->resource->relationLoaded('leadContact')) {
            $this->resource->load('leadContact');
        }
        $leadRs = new LeadContactResource($this->resource->leadContact);
        $response['leadContact'] =  $leadRs;

        return $response;
    }
}
