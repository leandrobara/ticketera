<?php

namespace App\Http\Resources\Views\WAutomationLogList;

use App\Models\User;
use App\Http\Resources\UserResourceCollection;
use App\Http\Resources\WhatsAppTemplateResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAutomationAfterSendResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        // this is restricted to prevent data leakage
        $visibleFields = $this->getFieldsToShow();
        if (!$visibleFields) {
            $response = [
                'id' => $this->resource->id,
                'created_at' => $this->resource->created_at,
                'add_new_note' => $this->resource->add_new_note,
                'new_note_text' => $this->resource->new_note_text,
                'apply_only_once' => $this->resource->apply_only_once,
                'only_apply_to_massive_sendings' => $this->resource->only_apply_to_massive_sendings,
            ];
        } else {
            $response = $this->resource->attributesToArray();
        }

        $response = $this->loadTagsToAdd($response);
        $response = $this->loadTagsToRemove($response);
        $response = $this->loadStatusToAssign($response);

        // $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadTagsToAdd(array $response)
    {
        $tags = $this->resource->getTagsToAddAttribute(['withTrashed' => true]);
        $response['tagsToAdd'] = $tags;
        return $response;
    }


    private function loadTagsToRemove(array $response)
    {
        $tags = $this->resource->getTagsToRemoveAttribute(['withTrashed' => true]);
        $response['tagsToRemove'] = $tags;
        return $response;
    }


    private function loadStatusToAssign(array $response)
    {
        $status = $this->resource->statusToAssign()->withTrashed()->first();
        $response['status'] = $status;
        return $response;
    }

}
