<?php

namespace App\Http\Resources\Views\GmailEmail;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\VisibleFieldsFilter;


class GmailEmailModalResource extends JsonResource
{

    use VisibleFieldsFilter;

    public function toArray($request)
    {
        $arr = $this->resource->toArray();
        return $arr;
    }

}
