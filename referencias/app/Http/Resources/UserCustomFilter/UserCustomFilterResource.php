<?php

namespace App\Http\Resources\UserCustomFilter;

use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\UserResource;

class UserCustomFilterResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }
        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }

        $response = $this->filterVisibleFields($response);

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

}
