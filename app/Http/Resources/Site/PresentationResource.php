<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['presentation']->id,
            'is_finished' => $this->resource['is_finished'],
            'notes' => $this->resource['presentation']->notes,
            'starts_at' => $this->resource['presentation']->starts_at,
            'tickets' => new TicketTypeResourceCollection($this->resource['ticket_types']),
        ];
    }
}
