<?php

namespace App\Http\Resources\WAutomations;

use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationSequenceStepResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('sendWhatsAppTemplate', $visibleFields)) {
            $response = $this->loadSendWhatsAppTemplateField($response);
        }
        if (in_array('wAutomationSequence', $visibleFields)) {
            $response = $this->loadWAutomationSequenceField($response);
        }
        if (in_array('tagsToAdd', $visibleFields)) {
            $response = $this->loadTagsToAddField($response);
        }
        if (in_array('statusToAdd', $visibleFields)) {
            $response = $this->loadStatusToAddField($response);
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


    private function loadSendWhatsAppTemplateField(array $response): array
    {
        if (!$this->resource->relationLoaded('sendWhatsAppTemplate')) {
            $this->resource->load('sendWhatsAppTemplate');
        }
        $response['sendWhatsAppTemplate'] = $this->resource->sendWhatsAppTemplate;
        return $response;
    }


    private function loadWAutomationSequenceField(array $response): array
    {
        if (!$this->resource->relationLoaded('wAutomationSequence')) {
            $this->resource->load('wAutomationSequence');
        }
        $response['wAutomationSequence'] = $this->resource->wAutomationSequence;
        return $response;
    }


    private function loadTagsToAddField(array $response): array
    {
        $response['tagsToAdd'] = $this->resource->tagsToAdd;
        return $response;
    }


    private function loadStatusToAddField(array $response): array
    {
        $response['statusToAdd'] = $this->resource->statusToAdd;
        return $response;
    }

}
