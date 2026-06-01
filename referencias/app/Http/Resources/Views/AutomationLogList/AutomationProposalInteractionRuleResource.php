<?php

namespace App\Http\Resources\Views\AutomationLogList;

use App\Http\Resources\StatusResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationProposalInteractionRuleResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'created_at' => $this->created_at,
                'trigger_type' => $this->trigger_type,
                'send_notification_email_to_user' => $this->send_notification_email_to_user,
                'notify_only_if_lead_quality_is_gt' => $this->notify_only_if_lead_quality_is_gt,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        // $response = $this->loadCancellingTags($response);
        // $response = $this->loadCancellingStatus($response);
        $response = $this->loadTagsToAdd($response);
        $response = $this->loadRemoveTags($response);
        $response = $this->loadAssignStatus($response);

        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadAssignStatus(array $response)
    {
        $statusToAssign = $this->resource->statusToAssign();
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


    private function loadCancellingTags(array $response)
    {
        $cancellingTags = $this->resource->cancellingTags;
        if (empty($cancellingTags)) {
            $response['cancellingTags'] = null;
            return $response;
        }
        $response['cancellingTags'] = $cancellingTags;
        return $response;
    }


    private function loadCancellingStatus(array $response)
    {
        $cancellingStatus = $this->resource->cancellingStatusList;
        if (empty($cancellingStatus)) {
            $response['cancellingStatus'] = null;
            return $response;
        }
        $response['cancellingStatus'] = $cancellingStatus;
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

}
