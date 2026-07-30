<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['ticket_type']->id,
            'name' => $this->resource['ticket_type']->name,
            'price' => $this->resource['ticket_type']->price,
            'has_stock' => $this->resource['available_tickets_for_purchase_count'] > 0,
            'max_purchase_quantity' => $this->resource['available_tickets_for_purchase_count'],
            'promotion' => $this->resource['promotion'],
        ];
    }
}
