<?php

namespace App\Http\Resources\Automations;

use App\Http\Resources\ClientResource;
use App\Http\Resources\StatusResourceCollection;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class AutomationProposalUserNotificationOnInteractionResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }

        if (in_array('cancellingTags', $visibleFields)) {
            $response = $this->loadCancellingTagsField($response);
        }

        if (in_array('cancellingStatus', $visibleFields)) {
            $response = $this->loadCancellingStatusField($response);
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
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;

        return $response;
    }

    public function loadCancellingTagsField($response)
    {
        $tags = $this->resource->cancellingTags;

        $response['cancellingTags'] = new TagResourceCollection($tags);

        return $response;
    }

    public function loadCancellingStatusField($response)
    {
        $tags = $this->resource->cancellingStatusList;

        $response['cancellingStatus'] = new StatusResourceCollection($tags);

        return $response;
    }
}
