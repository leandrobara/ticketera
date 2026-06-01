<?php

namespace App\Http\Resources\Views\StatusTime;

use Illuminate\Http\Resources\Json\JsonResource;


class ListStatusTimeItemResource extends JsonResource
{

    public function toArray($request)
    {
        return $this->resource;
    }
}
