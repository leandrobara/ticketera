<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class ClientSettingsResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

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

        $visibleFields = ['id', 'name', 'subdomain', 'country_code', 'version'];
        $clientRd = new ClientResource($this->resource->client);
        $clientRd->setVisibleFields($visibleFields);
        $response['client'] = $clientRd;

        return $response;
    }
}
