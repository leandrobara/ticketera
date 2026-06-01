<?php

namespace App\Models\Traits\ModelCache;


use App\Models\Client;


trait ClientModelRelationCache
{

    public function getClientAttribute(): ?Client
    {
        return $this->getModelRelationFromCache('client', 'Client', $this->client_id);
    }

}
