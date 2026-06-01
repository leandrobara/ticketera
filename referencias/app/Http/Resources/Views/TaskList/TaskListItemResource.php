<?php

namespace App\Http\Resources\Views\TaskList;

use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class TaskListItemResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = $this->resource->attributesToArray();
        $response = $this->loadUser($response);
        $response = $this->loadClient($response);
        $response = $this->loadLead($response);
        return $response;
    }


    private function loadClient(array $response): array
    {
        if (!$this->resource->relationLoaded('client')) {
            $this->resource->load('client');
        }
        $visibleFields = ['id', 'name'];
        $clientRs = new ClientResource($this->resource->client);
        $clientRs->setVisibleFields($visibleFields);
        $response['client'] = $clientRs;
        return $response;
    }


    private function loadUser(array $response): array
    {
        if (!$this->resource->relationLoaded('user')) {
            $this->resource->load('user');
        }
        $visibleFields = ['id', 'type', 'username', 'name', 'last_name', 'email', 'phone'];
        $userRs = new UserResource($this->resource->user);
        $userRs->setVisibleFields($visibleFields);
        $response['user'] = $userRs;
        return $response;
    }


    private function loadLead(array $response): array
    {
        if (!$this->resource->relationLoaded('lead')) {
            $this->resource->load([
                'lead' => function ($query) {
                    $query->withTrashed();
                },
            ]);
        }
        $visibleFields = [
            'id',
            'user',
            'tags',
            'status',
            'method',
            'company',
            'message',
            'quality',
            'deleted_at',
            'leadContacts',
            'mainLeadContact',
        ];
        $leadRs = new LeadResource($this->resource->lead);
        $leadRs->setVisibleFields($visibleFields);
        $response['lead'] = $leadRs;
        return $response;
    }

}
