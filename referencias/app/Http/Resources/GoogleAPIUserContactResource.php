<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class GoogleAPIUserContactResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'user_id' => $this->resource->user_id,
            'lead_id' => $this->resource->lead_id,
            'client_id' => $this->resource->client_id,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'deleted_at' => $this->resource->deleted_at,
            'resource_name' => $this->resource->resource_name,
            'deleted_at_ts' => $this->resource->deleted_at_ts,
        ];
        $visibleFields = $this->getFieldsToShow();

        if (in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
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


    private function loadUserField(array $response): array
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = ['id', 'type', 'name', 'last_name', 'phone', 'email'];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);

        $response['user'] = $userRs;
        return $response;
    }

}
