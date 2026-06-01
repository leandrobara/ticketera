<?php

namespace App\Http\Resources\Views\ClientInteraction\External\Legacy;

use StdClass;
use Illuminate\Http\Resources\Json\ResourceCollection;


class LastInteractionsResourceCollection extends ResourceCollection
{

    public function getResponseAsObject()
    {
        $response = new StdClass();
        foreach ($this->collection as $entity) {
            $leadsClientId = "{$entity->client->leads_client_id}";
            $response->$leadsClientId = new LastInteractionsItemResource($entity);
        }
        return $response;
    }

}
