<?php

namespace App\Http\Resources\WAutomations;

use App\Http\Resources\ClientResource;
use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\StatusResourceCollection;
use App\Http\Resources\WhatsAppTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationProposalResendResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('wAutomationProposal', $visibleFields)) {
            $response = $this->loadWAutomationProposalField($response);
        }
        if (in_array('cancellingTags', $visibleFields)) {
            $response = $this->loadCancellingTagsField($response);
        }
        if (in_array('cancellingStatus', $visibleFields)) {
            $response = $this->loadCancellingStatusField($response);
        }
        if (in_array('sendWhatsAppTemplate', $visibleFields)) {
            $response = $this->loadSendWhatsAppTemplateField($response);
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


    public function loadWAutomationProposalField($response)
    {
        if (!$this->resource->relationLoaded('wAutomationProposal')) {
            $this->resource->load('wAutomationProposal');
        }
        $response['wAutomationProposal'] = new WAutomationProposalResource($this->resource->wAutomationProposal);
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


    public function loadSendWhatsAppTemplateField($response)
    {
        if (!$this->resource->relationLoaded('sendWhatsAppTemplate')) {
            $this->resource->load('sendWhatsAppTemplate');
        }
        $sendWhatsAppTemplateRs = new WhatsAppTemplateResource($this->resource->whatsAppTemplate);
        $sendWhatsAppTemplateRs->setVisibleFields(['id', 'title', 'subject', 'is_proposal']);
        $response['sendWhatsAppTemplate'] = $sendWhatsAppTemplateRs;
        return $response;
    }

}
