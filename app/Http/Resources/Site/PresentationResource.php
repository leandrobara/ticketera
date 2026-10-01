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
            'is_sold_out' => $this->resource['presentation']->status === 'sold_out',
            'notes' => $this->resource['presentation']->notes,
            'starts_at' => $this->resource['presentation']->starts_at,
            'ends_at' => $this->resource['presentation']->ends_at,
            'duration_minutes' => (int) $this->resource['presentation']->starts_at->diffInMinutes($this->resource['presentation']->ends_at),
            'tickets' => new TicketTypeResourceCollection($this->resource['ticket_types']),
        ];
    }
}
