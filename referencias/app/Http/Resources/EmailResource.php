<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class EmailResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if ($visibleFields['client'] ?? false) {
            $response = $this->loadClientField($response);
        }
        if ($visibleFields['lead'] ?? false) {
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
        $response['lead'] = new LeadResource($this->resource->lead);
        return $response;
    }


    private function loadClientField(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $clientRs = new ClientResource($this->resource->client);
        $response['client'] = $clientRs;
        return $response;
    }
}
