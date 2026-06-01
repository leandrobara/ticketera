<?php

namespace App\Http\Resources\Views\WAPI;

use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAPIChatResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $wapiChatDTO = $this->resource; // App\DTO\WAPI\WAPIChatDTO
        $response = $wapiChatDTO->toArray();
        return $response;
    }

}
