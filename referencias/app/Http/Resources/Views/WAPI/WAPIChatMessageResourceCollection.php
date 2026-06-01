<?php

namespace App\Http\Resources\Views\WAPI;

use App\Http\Resources\Traits\HandlePagination;
use Illuminate\Http\Resources\Json\ResourceCollection;


class WAPIChatMessageResourceCollection extends ResourceCollection
{

    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $WAPIChatMessageResource) {
            // OJO! Transforma solo cada item del collection en un WAPIChatMessageResource(WAPIChatMessageDTO)
            $response[] = $WAPIChatMessageResource;
        }
        return $response;
    }

}
