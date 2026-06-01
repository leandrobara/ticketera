<?php

namespace App\Http\Resources\Views\NewsModal;

use App\Models\Client;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class ClientNewsModalResource extends JsonResource
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
            'apply_to_future_clients' => $this->apply_to_future_clients,
        ];
        return $response;
    }

}
