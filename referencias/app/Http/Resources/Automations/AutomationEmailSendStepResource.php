<?php

namespace App\Http\Resources\Automations;

use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationEmailSendStepResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('sendEmailTemplate', $visibleFields)) {
            $response = $this->loadSendEmailTemplateField($response);
        }
        if (in_array('automationEmailSend', $visibleFields)) {
            $response = $this->loadAutomationEmailSendField($response);
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


    private function loadSendEmailTemplateField(array $response): array
    {
        if (!$this->resource->relationLoaded('sendEmailTemplate')) {
            $this->resource->load('sendEmailTemplate');
        }
        $response['sendEmailTemplate'] = $this->resource->sendEmailTemplate;
        return $response;
    }


    private function loadAutomationEmailSendField(array $response): array
    {
        if (!$this->resource->relationLoaded('automationEmailSend')) {
            $this->resource->load('automationEmailSend');
        }
        $response['automationEmailSend'] = $this->resource->automationEmailSend;
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
