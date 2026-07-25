<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'price' => $this->resource['price'],
            'has_stock' => $this->resource['has_stock'],
            'max_purchase_quantity' => $this->resource['max_purchase_quantity'],
            'promotion' => $this->resource['promotion'],
        ];
    }
}
