<?php

namespace App\Http\Resources;

use App\Http\Resources\ClientResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        $response = $this->resource->attributesToArray();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }

        if (in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }

        $response = $this->filterVisibleFields($response);

        return $response;
    }

    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }

        $visibleFields = ['id', 'name', 'subdomain', 'country_code', 'version'];
        $clientRd = new ClientResource($this->resource->client);
        $clientRd->setVisibleFields($visibleFields);
        $response['client'] = $clientRd;

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

}
