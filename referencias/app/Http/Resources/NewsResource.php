<?php

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class NewsResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $visibleFields = $this->getFieldsToShow();
        $response = $this->resource->attributesToArray();
        $response = $this->parseBody($response);
        $response = $this->filterVisibleFields($response);
        if (in_array('newsNotifications', $visibleFields)) {
            $response = $this->loadNewsNotification($response);
        }
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
        $newsNotifications = $this->resource->newsNotifications;
        $response['applied_clients_count'] = $newsNotifications->pluck('client_id')->unique()->count();
        return $response;
    }


    private function parseBody(array $response)
    {

        $body = str_replace('&nbsp;', ' ', strip_tags($response['body']));
        $bodySubstr = substr($body, 0, 120);
        $response['body'] = $bodySubstr;
        return $response;
    }

}
