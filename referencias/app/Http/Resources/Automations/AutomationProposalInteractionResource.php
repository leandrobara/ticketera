<?php

namespace App\Http\Resources\Automations;

use App\Http\Resources\ClientResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\StatusResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationProposalInteractionResource extends JsonResource
{
    
    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $response['add_opened_proposal_tag'] = $response['add_tags_ids'] ? true : false;
        $visibleFields = $this->getFieldsToShow();

        if (in_array('client', $visibleFields)) {
            $response = $this->loadClientField($response);
        }
        if (in_array('automationProposal', $visibleFields)) {
            $response = $this->loadAutomationProposalField($response);
        }
        if (in_array('cancellingTags', $visibleFields)) {
            $response = $this->loadCancellingTagsField($response);
        }
        if (in_array('cancellingStatus', $visibleFields)) {
            $response = $this->loadCancellingStatusField($response);
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


    public function loadClientField($response)
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $response['client'] = new ClientResource($this->resource->client);
        return $response;
    }


    public function loadAutomationProposalField($response)
    {
        if (!$this->resource->relationLoaded('automationProposal')) {
            $this->resource->load('automationProposal');
        }
        $response['automationProposal'] = new AutomationProposalResource($this->resource->automationProposal);
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
