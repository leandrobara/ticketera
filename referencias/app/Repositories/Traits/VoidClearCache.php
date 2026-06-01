<?php

namespace App\Repositories\Traits;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;


trait VoidClearCache
{

    // Se deja para tener compatibilidad con ReposCache.
    public function clearCacheForClient(int $clientId): bool
    {
        return true;
    }

    // Se deja para tener compatibilidad con ReposCache. Borra el cache de un repo para todos los clientes.
    public function clearCacheForAllClients(): bool
    {
        return true;
    }

}
