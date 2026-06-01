<?php

namespace App\Http\Resources\Views\ClientInteraction\External\Legacy;

use DateTime;
use Illuminate\Http\Resources\Json\JsonResource;


class LastInteractionsItemResource extends JsonResource
{

    // receives ClientInteraction
    public function toArray($request)
    {
        $lastInteractionDate = $this->resource->updated_at;
        $weekDate = new DateTime($this->resource->week_date);

        $response = [
            'client_id' => (string) $this->resource->client_id,
            'day_of_week' => (string) $this->resource->week_date,
            'day' => (string) $lastInteractionDate->format('Y-m-d'),
            'leads_client_id' => (string) $this->resource->client->leads_client_id,
        ];
        return $response;
    }


    

}
