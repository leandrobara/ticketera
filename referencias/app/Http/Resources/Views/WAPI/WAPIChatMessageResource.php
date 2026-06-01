<?php

namespace App\Http\Resources\Views\WAPI;

use App\Http\Resources\UserResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\ClientResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class WAPIChatMessageResource extends JsonResource
{

    use VisibleFieldsFilter;


    public function toArray($request)
    {
        $wapiChatMessageDTO = $this->resource; // App\DTO\WAPI\WAPIChatMessageDTO
        $response = $wapiChatMessageDTO->toArray();
        return $response;
    }

}
