<?php

namespace App\Http\Resources\WAutomations;

use App\Http\Resources\ClientResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationProposalModifyLeadAfterSendResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $visibleFields = $this->getFieldsToShow();

        $response['add_sent_proposal_tag'] = $response['add_tags_ids'] ? true : false;

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('wAutomationProposal', $visibleFields)) {
            $response = $this->loadWAutomationProposalField($response);
        }
        if (in_array('addTags', $visibleFields)) {
            $response = $this->loadAddTagsField($response);
        }
        if (in_array('removeTags', $visibleFields)) {
            $response = $this->loadRemoveTagsField($response);
        }
        if (in_array('assignStatus', $visibleFields)) {
            $response = $this->loadAssignStatusField($response);
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


    public function loadAddTagsField($response)
    {
        $tags = $this->resource->tagsToAdd;
        $response['addTags'] = new TagResourceCollection($tags);
        return $response;
    }


    public function loadRemoveTagsField($response)
    {
        $tags = $this->resource->tagsToRemove;
        $response['removeTags'] = new TagResourceCollection($tags);
        return $response;
    }


    public function loadAssignStatusField($response)
    {
        if (!$this->resource->relationLoaded('statusToAssign')) {
            $this->resource->load('statusToAssign');
        }
        $response['assignStatus'] = new StatusResource($this->resource->statusToAssign);
        return $response;
    }

}
