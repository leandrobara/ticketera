<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class NoteResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $visibleFields = $this->getFieldsToShow();
        $response = $this->resource->attributesToArray();
        $response = $this->filterVisibleFields($response);

        if ($visibleFields && in_array('user', $visibleFields)) {
            $response = $this->loadUserField($response);
        }

        if ($visibleFields && in_array('lead', $visibleFields)) {
            $response = $this->loadLeadField($response);
        }

        return $response;
    }

    private function loadLeadField(array $response): array
    {
        if (!$this->resource->relationLoaded('lead')) {
            $this->resource->load('lead');
        }
        $leadRs = new LeadResource($this->resource->lead);
        $response['lead'] =  $leadRs;

        return $response;
    }

    private function loadUserField(array $response)
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = [
            'id',
            'type',
            'username',
            'name',
            'last_name',
            'email',
            'phone'
        ];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);
        $response['user'] = $userRs;

        return $response;
    }
}
