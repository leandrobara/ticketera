<?php

namespace App\Http\Resources\Views\WAPI;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAPIMessageModalResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $leadContactPhones = $this->resource->leadContactPhones->map(function ($leadContactPhone) {
            return [
                'id' => $leadContactPhone->id,
                'phone' => $leadContactPhone->phone,
                'lead_id' => $leadContactPhone->lead_id,
                'lead_contact_id' => $leadContactPhone->lead_contact_id,
            ];
        });
        $response = [
            'leadContactPhones' => $leadContactPhones,
        ];
        return $response;
    }

}
