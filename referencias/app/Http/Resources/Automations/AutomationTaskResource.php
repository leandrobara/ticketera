<?php

namespace App\Http\Resources\Automations;

use App\Http\Resources\ClientResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TaskTemplateResource;
use App\Http\Resources\TagResourceCollection;
use App\Http\Resources\StatusResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class AutomationTaskResource extends JsonResource
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
            $response = $this->loadCancellingStatusField($response);
        }
        if (in_array('cancellingTags', $visibleFields)) {
            $response = $this->loadCancellingTagsField($response);
        }
        if (in_array('allowingStatus', $visibleFields)) {
            $response = $this->loadAllowingStatusField($response);
        }
        if (in_array('allowingTags', $visibleFields)) {
            $response = $this->loadAllowingTagsField($response);
        }
        if (in_array('statusToAssign', $visibleFields)) {
            $response = $this->loadStatusToAssignField($response);
        }
        if (in_array('tagsToAssign', $visibleFields)) {
            $response = $this->loadTagsToAssignField($response);
        }
        if (in_array('taskTemplate', $visibleFields)) {
            $response = $this->loadTaskTemplateField($response);
        }
        $response = $this->filterVisibleFields($response);
        return $response;
    }


    private function loadTaskTemplateField(array $response): array
    {
        if (!$this->resource->relationLoaded('taskTemplate')) {
            $this->resource->load('taskTemplate');
        }
        $response['taskTemplate'] = $this->resource->taskTemplate;
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


    private function loadAllowingStatusField(array $response): array
    {
        $statusRs = new StatusResourceCollection($this->resource->allowingStatus);
        $statusRs->setVisibleFields(['id', 'name', 'tag_category_id', 'text_color', 'background_color']);
        $response['allowingStatus'] = $statusRs;
        return $response;
    }


    private function loadAllowingTagsField(array $response): array
    {
        $tagsRs = new TagResourceCollection($this->resource->allowingTags);
        $tagsRs->setVisibleFields(['id', 'name', 'text_color', 'background_color', 'sale_probability', 'order']);
        $response['allowingTags'] = $tagsRs;
        return $response;
    }


    private function loadStatusToAssignField(array $response): array
    {
        if (!$this->resource->relationLoaded('statusToAssign')) {
            $this->resource->load('statusToAssign');
        }
        $visibleFields = ['id', 'name', 'tag_category_id', 'text_color', 'background_color'];
        $statusRs = new StatusResource($this->resource->statusToAssign);
        $statusRs->setVisibleFields($visibleFields);
        $response['statusToAssign'] = $statusRs;
        return $response;
    }


    private function loadTagsToAssignField(array $response): array
    {
        $tagsRs = new TagResourceCollection($this->resource->tagsToAssign);
        $tagsRs->setVisibleFields(['id', 'name', 'text_color', 'background_color', 'sale_probability', 'order']);
        $response['tagsToAssign'] = $tagsRs;
        return $response;
    }

}
