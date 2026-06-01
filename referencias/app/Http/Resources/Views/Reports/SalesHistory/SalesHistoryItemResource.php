<?php

namespace App\Http\Resources\Views\Reports\SalesHistory;

use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\JsonResource;


class SalesHistoryItemResource extends JsonResource
{

    public function toArray($request)
    {
        $resource = $this->resource->toArray();
        $resource = $this->loadLead($resource);
        $resource = $this->loadUser($resource);
        return $resource;
    }


    public function loadLead($resource)
    {
        if (!$this->resource->relationLoaded('lead')) {
            $this->resource->load('lead');
        }

        $rs = new LeadResource($this->resource->lead);
        $rs->setVisibleFields(
            ['id', 'company', 'user', 'landing', 'quality', 'status', 'tags', 'acquisitionChannel', 'mainLeadContact']
        );
        $resource['lead'] = $rs;
        return $resource;
    }


    public function loadUser($resource)
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }

        $rs = new UserResource($this->resource->user);
        $rs->setVisibleFields(['id', 'username', 'email', 'name', 'last_name']);
        $resource['user'] = $rs;
        return $resource;
    }

}
