<?php

namespace App\Models\Traits\ModelCache;


use App\Models\Client;


trait StatusModelRelationCache
{

    public function getStatusAttribute(): ?Client
    {
        return $this->getModelRelationFromCache('status', 'Status', $this->status_id);
    }

}
