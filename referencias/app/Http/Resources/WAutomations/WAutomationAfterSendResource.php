<?php

namespace App\Http\Resources\WAutomations;

use App\Http\Resources\LeadResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\ClientResource;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationAfterSendResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        if (!$this->resource) {
            return [];
        }
        
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('lead', $visibleFields)) {
            $response = $this->loadLeadField($response);
        }
        if (in_array('tagsToAdd', $visibleFields)) {
            $response = $this->loadTagsToAddField($response);
        }
        if (in_array('tagsToRemove', $visibleFields)) {
            $response = $this->loadTagsToRemoveField($response);
        }
        if (in_array('statusToAssign', $visibleFields)) {
            $response = $this->loadStatusToAssignField($response);
        }

        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadClientField(array $response): array
    {
        $visibleFields = ['id', 'name', 'subdomain', 'country_code', 'version'];
        $rs = new ClientResource($this->resource->client);
        $rs->setVisibleFields($visibleFields);
        $response['client'] = $rs;
        return $response;
    }


    private function loadLeadField(array $response): array
    {
        $rs = new LeadResource($this->resource->lead);
        $response['lead'] = $rs;
        return $response;
    }


    private function loadTagsToAddField(array $response): array
    {
        $rs = new TagResourceCollection($this->resource->tagsToAdd);
        $response['tagsToAdd'] = $rs;
        return $response;
    }


    private function loadTagsToRemoveField(array $response): array
    {
        $rs = new TagResourceCollection($this->resource->tagsToRemove);
        $response['tagsToRemove'] = $rs;
        return $response;
    }


    private function loadStatusToAssignField(array $response): array
    {
        $status = $this->resource->statusToAssign;
        $response['statusToAssign'] = $status ? new StatusResource($status) : null;
        return $response;
    }

}
