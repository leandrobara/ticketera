<?php

namespace App\Http\Resources\Views\ClientInteraction\External\Legacy;

use DateTime;
use Illuminate\Http\Resources\Json\JsonResource;


class InteractionsItemResource extends JsonResource
{

    // receives ClientInteraction
    public function toArray($request)
    {
        $weekDate = new DateTime($this->resource->week_date);

        $response = [
            'id' => (string) $this->resource->id,
            'year' => (string) $weekDate->format('Y'),
            'month' => (string) $weekDate->format('n'),
            'count' => (string) $this->resource->count,
            'day_of_week' => (string) $weekDate->format('j'),
            'name' => (string) $this->resource->client->name,
            'client_id' => (string) $this->resource->client_id,
            'leads_client_id' => (string) $this->resource->client->leads_client_id,
        ];
        return $response;
    }


    

}
