<?php

namespace App\Http\Resources\Views\NewsModal;

use App\Models\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientyConfigNewsModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $response = [
            'id' => $this->id,
            'body' => $this->body,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'youtube_url' => $this->youtube_url,
            'force_modal_show' => $this->force_modal_show,
            'apply_to_future_clients' => $this->apply_to_future_clients,
        ];
        $response = $this->loadClientIds($response);
        return $response;
    }


    private function loadClientIds(array $response): array
    {
        if (!$this->resource->relationLoaded('newsNotifications')) {
            $this->resource->load('newsNotifications');
        }
        $newsNotifications = $this->resource->newsNotifications;
        $response['client_id'] = $newsNotifications->pluck('client_id')->unique()->toArray();
        return $response;
    }
}
