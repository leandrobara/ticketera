<?php

namespace App\Http\Resources\Views\StatusTime;

use Illuminate\Http\Resources\Json\ResourceCollection;


class ListSTatusTimeResourceCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $response = [];
        foreach ($this->collection as $key => $leads) {
            $response['leads'][] = [
                'id' => $key,
                'statuses' => new ListStatusTimeItemResource($leads)
            ];
        }

        return $response;
    }
}
