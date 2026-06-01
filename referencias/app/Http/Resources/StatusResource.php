<?php

namespace App\Http\Resources;

use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class StatusResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('automationsEmailSend', $visibleFields)) {
            $response = $this->loadAutomationsEmailSend($response);
        }
        if (in_array('wAutomationsSequence', $visibleFields)) {
            $response = $this->loadWAutomationsSequence($response);
        }
        if (in_array('automationsProposalInteractionRule', $visibleFields)) {
            $response = $this->loadAutomationsProposalInteractionRule($response);
        }
        if (in_array('automationsProposalModifyLeadAfterSendRule', $visibleFields)) {
            $response = $this->loadAutomationsProposalModifyLeadAfterSendRule($response);
        }

        $response = $this->loadStatusCategory($response);
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadStatusCategory(array $response): array
    {
        if (!$this->resource->relationLoaded('statusCategory')) {
            $this->resource->load('statusCategory');
        }
        $visibleFields = ['id', 'name'];
        $statusCategoryRs = new StatusCategoryResource($this->resource->statusCategory);
        $statusCategoryRs->setVisibleFields($visibleFields);
        $response['statusCategory'] = $statusCategoryRs;
        return $response;
    }


    private function loadAutomationsProposalInteractionRule(array $response): array
    {
        if (!$this->resource->relationLoaded('automationsProposalInteractionRule')) {
            $this->resource->load('automationsProposalInteractionRule');
        }
        $response['automationsProposalInteractionRule'] = $this->resource->automationsProposalInteractionRule;
        return $response;
    }


    private function loadAutomationsProposalModifyLeadAfterSendRule(array $response): array
    {
        if (!$this->resource->relationLoaded('automationsProposalModifyLeadAfterSendRule')) {
            $this->resource->load('automationsProposalModifyLeadAfterSendRule');
        }
        $response['automationsProposalModifyLeadAfterSendRule'] = $this->resource
            ->automationsProposalModifyLeadAfterSendRule
        ;
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
