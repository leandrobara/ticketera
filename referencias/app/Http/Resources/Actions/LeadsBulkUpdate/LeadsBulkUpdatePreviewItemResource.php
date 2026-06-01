<?php

namespace App\Http\Resources\Actions\LeadsBulkUpdate;

use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;
use App\Http\Resources\AcquisitionChannelResource;
use App\Http\Resources\LeadContactResourceCollection;


class LeadsBulkUpdatePreviewItemResource extends JsonResource
{

    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = [
            'warningReasons' => $this->resource->getWarningReasons(),
            'nonPersistableReasons' => $this->resource->getNonPersistableReasons(),
            'userWasFound' => $this->resource->userWasFound,
            'channelWasFound' => $this->resource->channelWasFound,
            'acquisitionChannelName' => $this->resource->acquisitionChannelName,
            'customFieldsAreNotEnabled' => $this->resource->customFieldsAreNotEnabled,
            'phones' => $this->resource->phones,
            'emails' => $this->resource->emails,
            'leadId' => $this->resource->leadId,
            'notes' => $this->resource->notes,
            'method' => $this->resource->method,
            'company' => $this->resource->company,
            'userName' => $this->resource->userName,
            'customFields' => $this->resource->customFields,
            'contacts' => $this->resource->contacts->toArray(),
        ];

        $response = $this->loadUserField($response);
        $response = $this->loadAcquisitionChannelField($response);
        return $response;
    }


    private function loadUserField(array $response)
    {
        if (!$this->resource->userWasFound && !$this->resource->user) {
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
        if (!$this->resource->channelWasFound && !$this->acquisitionChannel) {
            $response['acquisitionChannel'] = null;
            return $response;
        }
        $visibleFields = ['id', 'name', 'text_color', 'background_color'];
        $acquisitionChannelRs = new AcquisitionChannelResource($this->resource->acquisitionChannel);
        $acquisitionChannelRs->setVisibleFields($visibleFields);
        $response['acquisitionChannel'] = $acquisitionChannelRs;
        return $response;
    }

}
