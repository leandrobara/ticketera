<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;

class AuthUserResource extends JsonResource
{
    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $response = [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'name' => $this->resource->name,
            'last_name' => $this->resource->last_name,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'api_token' => $this->resource->api_token
        ];

        return $response;
    }
}
