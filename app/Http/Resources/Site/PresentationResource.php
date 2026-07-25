<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'notes' => $this->resource['notes'],
            'starts_at' => $this->resource['starts_at'],
            'is_finished' => $this->resource['is_finished'],
            'tickets' => new TicketTypeResourceCollection($this->resource['tickets']),
        ];
    }
}
