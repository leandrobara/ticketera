<?php

namespace App\Http\Resources;

use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class TagResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('tagCategory', $visibleFields)) {
            $response = $this->loadTagCategoryField($response);
        }
        if (in_array('automationsEmailSend', $visibleFields)) {
            $response = $this->loadAutomationsEmailSend($response);
        }
        if (in_array('wAutomationsSequence', $visibleFields)) {
            $response = $this->loadWAutomationsSequence($response);
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


    private function loadTagCategoryField(array $response): array
    {
        if (!$this->resource->relationLoaded('tagCategory')) {
            $this->resource->load('tagCategory');
        }
        $visibleFields = ['id', 'name'];
        $rs = new TagCategoryResource($this->resource->tagCategory);
        $rs->setVisibleFields($visibleFields);
        $response['tagCategory'] = $rs;
        return $response;
    }


    private function loadAutomationsEmailSend(array $response): array
    {
        $response['automationsEmailSend'] = $this->resource->automationsEmailSend;
        return $response;
    }


    private function loadWAutomationsSequence(array $response): array
    {
        $response['wAutomationsSequence'] = $this->resource->wAutomationsSequence ?? new Collection([]);
        return $response;
    }

}
