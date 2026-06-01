<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientyConfigWhatsAppTemplateResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->toArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }
        if (in_array('businessArea', $visibleFields)) {
            $response = $this->loadBusinessAreaField($response);
        }
        if (in_array('businessAreaChild', $visibleFields)) {
            $response = $this->loadBusinessAreaChildField($response);
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


    private function loadBusinessAreaField(array $response): array
    {
        $businessAreaRow = null;
        if (!$this->resource->relationLoaded('businessArea')) {
            $this->resource->load('businessArea');
        }
        if ($this->resource->businessArea) {
            $businessArea = $this->resource->businessArea;
            $businessAreaRow = ['id' => $businessArea->id, 'name' => $businessArea->name];
        }
        $response['businessArea'] = $businessAreaRow;
        return $response;
    }


    private function loadBusinessAreaChildField(array $response): array
    {
        $businessAreaChildRow = null;
        if (!$this->resource->relationLoaded('businessAreaChild')) {
            $this->resource->load('businessAreaChild');
        }
        if ($this->resource->businessAreaChild) {
            $businessAreaChild = $this->resource->businessAreaChild->toArray();
            $businessAreaChildRow = ['id' => $businessAreaChild['id'], 'name' => $businessAreaChild['name']];
        }
        $response['businessAreaChild'] = $businessAreaChildRow;
        return $response;
    }

}
