<?php

namespace App\Http\Resources\Views\WAPI;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class WAPIChatResourceCollection extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $WAPIChatResource) {
            // OJO! Transforma solo cada item del collection en un WAPIChatResource(WAPIChatDTO)
            $response[] = $WAPIChatResource;
        }
        return $response;
    }

}
