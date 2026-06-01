<?php

namespace App\Http\Resources\WAutomations;

use App\Http\Resources\ClientResource;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\StatusResourceCollection;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\Views\WAutomationSequenceStep\WAutomationSequenceStepCollectionResource;


class WAutomationSequenceResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('triggeringStatus', $visibleFields)) {
            $response = $this->loadTriggeringStatusField($response);
        }
        if (in_array('triggeringTags', $visibleFields)) {
            $response = $this->loadTriggeringTagsField($response);
        }
        if (in_array('cancellingStatus', $visibleFields)) {
            $response = $this->loadcancellingStatusField($response);
        }
        if (in_array('cancellingTags', $visibleFields)) {
            $response = $this->loadcancellingTagsField($response);
        }

        $response = $this->loadSendMessageSteps($response);
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


    private function loadTriggeringStatusField(array $response): array
    {

        $statusRs = new StatusResourceCollection($this->resource->triggeringStatus);
        $statusRs->setVisibleFields(['id', 'name', 'tag_category_id', 'text_color', 'background_color']);
        $response['triggeringStatus'] = $statusRs;
        return $response;
    }


    private function loadTriggeringTagsField(array $response): array
    {
        $tagsRs = new TagResourceCollection($this->resource->triggeringTags);
        $tagsRs->setVisibleFields(['id', 'name', 'text_color', 'background_color', 'sale_probability', 'order']);
        $response['triggeringTags'] = $tagsRs;
        return $response;
    }


    private function loadCancellingStatusField(array $response): array
    {
        $statusRs = new StatusResourceCollection($this->resource->cancellingStatus);
        $statusRs->setVisibleFields(['id', 'name', 'tag_category_id', 'text_color', 'background_color']);
        $response['cancellingStatus'] = $statusRs;
        return $response;
    }


    private function loadCancellingTagsField(array $response): array
    {
        $tagsRs = new TagResourceCollection($this->resource->cancellingTags);
        $tagsRs->setVisibleFields(['id', 'name', 'text_color', 'background_color', 'sale_probability', 'order']);
        $response['cancellingTags'] = $tagsRs;
        return $response;
    }


    private function loadSendMessageSteps($response)
    {
        if (!$this->resource->relationLoaded('wAutomationSequenceSteps')) {
            $this->resource->load('wAutomationSequenceSteps');
        }
        $wAutomationSequenceStepRs = new WAutomationSequenceStepCollectionResource(
            $this->resource->wAutomationSequenceSteps
        );
        $response['wAutomationSequenceSteps'] = $wAutomationSequenceStepRs;
        return $response;
    }

}
