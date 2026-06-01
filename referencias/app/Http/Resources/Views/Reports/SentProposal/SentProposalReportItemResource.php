<?php

namespace App\Http\Resources\Views\Reports\SentProposal;

use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use Illuminate\Http\Resources\Json\JsonResource;


class SentProposalReportItemResource extends JsonResource
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
            $this->resource->load([
                'lead' => function ($query) {
                    $query->withTrashed();
                },
            ]);
        }
        $rs = new LeadResource($this->resource->lead);
        $rs->setVisibleFields([
            'id',
            'user',
            'tags',
            'status',
            'company',
            'landing',
            'quality',
            'deleted_at',
            'mainLeadContact',
            'acquisitionChannel',
        ]);
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
