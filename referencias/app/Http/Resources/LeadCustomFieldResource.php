<?php

namespace App\Http\Resources;

use App\Models\LeadCustomFieldValue;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class LeadCustomFieldResource extends JsonResource
{

    use VisibleFieldsFilter;

    private $leadCustomFieldValue = null;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        
        $visibleFields = $this->getFieldsToShow();
        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('leadCustomFieldValue', $visibleFields)) {
            $response = $this->loadLeadCustomFieldValue($response);
        }
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    public function setLeadCustomFieldValue(?LeadCustomFieldValue $leadCustomFieldValue)
    {
        $this->leadCustomFieldValue = $leadCustomFieldValue;
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


    private function loadLeadCustomFieldValue(array $response): array
    {
        $visibleFields = ['id', 'value', 'hash'];
        $rs = null;
        if ($this->leadCustomFieldValue) {
            $rs = new LeadCustomFieldValueResource($this->leadCustomFieldValue);
            $rs->setVisibleFields($visibleFields);
        }
        $response['leadCustomFieldValue'] = $rs;
        return $response;
    }

}
