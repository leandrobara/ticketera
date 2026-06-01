<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class NPSPollsResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $visibleFields = $this->getFieldsToShow();
        $response = $this->resource->attributesToArray();
        $response = $this->filterVisibleFields($response);
        $response = $this->loadAppliedClientsCount($response);
        return $response;
    }


    private function loadNewsNotification(array $response)
    {
        if (!$this->resource->relationLoaded('newsNotifications')) {
            $this->resource->load('newsNotifications');
        }
        $response['newsNotifications'] = $this->resource->newsNotifications;
        return $response;
    }


    private function loadAppliedClientsCount(array $response)
    {
        $NPSPollAnswers = $this->resource->NPSPollAnswers;
        $response['applied_clients_count'] = $NPSPollAnswers->pluck('client_id')->unique()->count();
        if ($response['applied_clients_count'] == 1) {
            $client = $NPSPollAnswers->first()->client;
            $response['client'] = [
                'id' => $client->id,
                'name' => $client->name,
                'enabled' => $client->enabled,
                'subdomain' => $client->subdomain,
            ];
        }
        return $response;
    }
}
