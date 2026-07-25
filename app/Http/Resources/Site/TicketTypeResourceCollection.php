<?php

namespace App\Http\Resources\Site;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TicketTypeResourceCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn ($ticketType) => (new TicketTypeResource($ticketType))->toArray($request))
            ->values()
            ->all()
        ;
    }
}
