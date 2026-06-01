<?php

namespace App\Http\Resources\Actions\LeadsBulkUpload;

use App\Http\Resources\UserResource;
use App\Http\Resources\StatusResource;
use App\Http\Resources\TagResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Resources\LeadContactResourceCollection;


class LeadsBulkUploadPreviewItemResource extends JsonResource
{

    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = [
            'isPersistable' => $this->resource->isPersistable,
            'nonPersistableReasons' => $this->resource->getNonPersistableReasons(),
            
            'notes' => $this->resource->notes,
            // 'method' => $this->resource->method,
            'status' => $this->resource->status,
            'company' => $this->resource->company,
            'message' => $this->resource->message,
            'userName' => $this->resource->userName,
            'tagNames' => $this->resource->tagNames,
            'statusName' => $this->resource->statusName,
            'customFields' => $this->resource->customFields,
            'contacts' => $this->resource->contacts->toArray(),
            'acquisitionChannelName' => $this->resource->acquisitionChannelName,

            'statusWasFound' => $this->resource->statusWasFound,
            'channelWasFound' => $this->resource->channelWasFound,
            'notFoundTagNames' => $this->resource->notFoundTagNames,

            'longLeadContactNames' => $this->resource->longLeadContactNames,
            'longLeadContactLastNames' => $this->resource->longLeadContactLastNames,
            'leadContactNameIsTooLong' => $this->resource->leadContactNameIsTooLong,
            'leadContactLastNameIsTooLong' => $this->resource->leadContactLastNameIsTooLong,
        ];

        $response = $this->loadUserField($response);
        $response = $this->loadTagsField($response);
        $response = $this->loadStatusField($response);
        $response = $this->loadAcquisitionChannelField($response);
        return $response;
    }


    private function loadUserField(array $response)
    {
        if (!$this->resource->user) {
            $response['user'] = null;
            return $response;
        }
        $visibleFields = ['id', 'type', 'username', 'name', 'last_name', 'email'];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);
        $response['user'] = $userRs;
        return $response;
    }


    private function loadAcquisitionChannelField(array $response): array
    {
        if (!$this->resource->channelWasFound) {
            $response['acquisitionChannel'] = null;
            return $response;
        }
        $visibleFields = ['id', 'name', 'text_color', 'background_color'];
        $acquisitionChannelRs = new AcquisitionChannelResource($this->resource->acquisitionChannel);
        $acquisitionChannelRs->setVisibleFields($visibleFields);
        $response['acquisitionChannel'] = $acquisitionChannelRs;
        return $response;
    }


    private function loadStatusField(array $response): array
    {
        if (!$this->resource->statusWasFound) {
            $response['status'] = null;
            return $response;
        }
        $visibleFields = ['id', 'name', 'text_color', 'background_color'];
        $rs = new StatusResource($this->resource->status);
        $rs->setVisibleFields($visibleFields);
        $response['status'] = $rs;
        return $response;
    }


    private function loadTagsField(array $response)
    {
        $rs = new TagResourceCollection($this->resource->tags);
        $response['tags'] = $rs;
        return $response;
    }

}
