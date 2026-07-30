<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class VenueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'has_bar' => $this->has_bar,
            'address' => $this->address,
            'has_parking' => $this->has_parking,
            'neighborhood' => $this->neighborhood,
            'is_accessible' => $this->is_accessible,
            'google_maps_url' => $this->google_maps_url,
        ];
    }
}
