<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class TaskResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $visibleFields = $this->getFieldsToShow();
        $response = $this->resource->attributesToArray();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }
        if (in_array('lead', $visibleFields)) {
            $response = $this->loadLeadField($response);
        }

        $response = $this->filterVisibleFields($response);

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

    private function loadUserField(array $response)
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = [
            'id',
            'type',
            'username',
            'name',
            'last_name',
            'email',
            'phone'
        ];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);
        $response['user'] = $userRs;

        return $response;
    }

    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }

        $visibleFields = ['id', 'name', 'subdomain', 'country_code', 'version'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;

        return $response;
    }
}
