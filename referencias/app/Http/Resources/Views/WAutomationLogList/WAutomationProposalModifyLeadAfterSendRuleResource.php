<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use App\Http\Resources\StatusResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationProposalModifyLeadAfterSendRuleResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'wautomation_proposal_id' => $this->wautomation_proposal_id,
                'created_at' => $this->created_at,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        $response = $this->loadTagsToAdd($response);
        $response = $this->loadRemoveTags($response);
        $response = $this->loadAssignStatus($response);
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadTagsToAdd(array $response)
    {
        $tagsToAdd = $this->resource->getTagsToAddAttribute(['withTrashed' => true]);
        if (empty($tagsToAdd)) {
            $response['tagsToAdd'] = null;
            return $response;
        }
        $response['tagsToAdd'] = $tagsToAdd;
        return $response;
    }


    private function loadRemoveTags(array $response)
    {
        $removeTags = $this->resource->getTagsToRemoveAttribute(['withTrashed' => true]);
        if (empty($removeTags)) {
            $response['removeTags'] = null;
            return $response;
        }
        $response['removeTags'] = $removeTags;
        return $response;
    }


    private function loadAssignStatus(array $response)
    {
        $statusToAssign = $this->resource->statusToAssign;
        if (empty($statusToAssign)) {
            $response['statusToAssign'] = null;
            return $response;
        }

        if (!$this->resource->relationLoaded('statusToAssign')) {
            $this->resource->load([
                'statusToAssign' => function ($q) {
                    $q->withTrashed();
                }
            ]);
        }
        $response['statusToAssign'] = new StatusResource($this->resource->statusToAssign);
        return $response;
    }

}
